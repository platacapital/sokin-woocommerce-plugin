<?php
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

add_action('woocommerce_refund_created', 'sokinpay_create_refund', 10, 2);

/**
 * Function for `woocommerce_refund_created` action-hook.
 *
 * @param int   $refund_id Refund post ID.
 * @param array $args      Refund arguments including amount and reason.
 *
 * @return void
 */
function sokinpay_create_refund($refund_id, $args) {

	// Get the refund and order objects
	$refund = wc_get_order($refund_id);

	// Get Order Details
	$order = wc_get_order($refund->get_parent_id());

	// Assign Payment Gateway ID
	$payment_gateway_id = 'sokinpay_gateway';

	// Get an instance of the WC_Payment_Gateways object
	$payment_gateways = WC_Payment_Gateways::instance();

	// Get the desired WC_Payment_Gateway object
	$gateways = $payment_gateways->payment_gateways();
	if (!isset($gateways[$payment_gateway_id])) {
		return;
	}
	$payment_gateway = $gateways[$payment_gateway_id];

	// Get Sokin Order Details
	if ($order->get_meta('orderId')) {
		$order_args = array(
			'headers' => array(
				'x-api-key' => $payment_gateway->settings['woo_cpay_x_api_key'],
				'Content-Type' => 'application/json'
			),
		);

		// Order Details API call
		$order_request_url = $payment_gateway->woo_cpay_api_url . '/orders/' . $order->get_meta('orderId');
		$order_request     = wp_remote_get($order_request_url, $order_args);

		if (is_wp_error($order_request)) {
			return;
		}

		$order_res_body = wp_remote_retrieve_body($order_request);
		$json_data = json_decode($order_res_body, true);

		// Verify payments array exists and has elements before accessing
		if (!isset($json_data['data']['order']['payments']) || !is_array($json_data['data']['order']['payments']) || empty($json_data['data']['order']['payments'])) {
			return;
		}

		// Making Refund Request Body
		$body = array(
			'paymentId' => $json_data['data']['order']['payments'][0]['paymentId'],
			'currency' => $order->get_currency(),
			'amount' => $args['amount'],
			'description' => $args['reason'],
			'referenceNo' => gmdate('Ymds'),
			'memo' => ''
		);

		// API Headers
		$header_args = array(
			'headers' => array(
				'x-api-key' => $payment_gateway->settings['woo_cpay_x_api_key'],
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($body)
		);

		// Refund API Call
		$response = wp_remote_post($payment_gateway->woo_cpay_api_url . '/refunds', $header_args);

		if (is_wp_error($response)) {
			return;
		}

		$res_body = wp_remote_retrieve_body($response);

		$json_data = json_decode($res_body, true);
		// Show error message if we are doing partial payment on same day which is not allowed.
		// Same day transfer will be fully refunded.
		if (is_array($json_data) && isset($json_data['success'], $json_data['status']) && !$json_data['success'] && 400 === (int) $json_data['status']) {
			throw new Exception(esc_attr($json_data['message']));
		}
	}
}

/**
 * Log a warning with WooCommerce logger when writable; otherwise fall back to PHP error_log.
 *
 * This prevents permission warnings when the uploads directory is not writable
 * (e.g., demo environments) while retaining security-relevant visibility.
 *
 * @param string $message  Message to log.
 * @param array  $context  Optional context. Sensitive values are minimized in fallback.
 *
 * @return void
 */
function sokin_gateway_log_warning($message, $context = array()) {
	$source = isset($context['source']) ? $context['source'] : 'sokinpay-gateway';

	$uploads = wp_upload_dir();
	$basedir = isset($uploads['basedir']) ? $uploads['basedir'] : '';
	$logs_dir = trailingslashit($basedir) . 'wc-logs';
	$base_writable = is_dir($basedir) && (function_exists('wp_is_writable') ? wp_is_writable($basedir) : is_writable($basedir));
	$logs_writable = is_dir($logs_dir)
		? (function_exists('wp_is_writable') ? wp_is_writable($logs_dir) : is_writable($logs_dir))
		: $base_writable;

	if (function_exists('wc_get_logger') && $logs_writable) {
		$logger = wc_get_logger();
		$context['source'] = $source;
		$logger->warning($message, $context);
		return;
	}

	// Fallback to PHP error_log with reduced context to avoid leaking sensitive data.
	$safe_context = array('src' => $source);
	if (isset($context['order_id'])) {
		$safe_context['order_id'] = $context['order_id'];
	}
	if (isset($context['current_user_id'])) {
		$safe_context['current_user_id'] = $context['current_user_id'];
	}
	if (isset($context['stored_order_id'])) {
		$safe_context['stored_order_id'] = $context['stored_order_id'];
	}
	if (isset($context['get_order_id'])) {
		$safe_context['get_order_id'] = $context['get_order_id'];
	}
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log('Sokin Pay WARNING: ' . $message . ' ' . wp_json_encode($safe_context));
}

// Thank you page
add_action('woocommerce_thankyou', 'action_woocommerce_thankyou', 10, 1);

/**
 * Handle thank-you page logic for Sokin Pay orders.
 *
 * @param int $order_id WooCommerce order ID.
 *
 * @return void
 */
function action_woocommerce_thankyou($order_id) {
	$order = wc_get_order($order_id);
	if (!$order || 'sokinpay_gateway' !== $order->get_payment_method()) {
		return;
	}

	// Verify order ownership for logged-in users
	$current_user_id = get_current_user_id();
	if ($current_user_id > 0) {
		$order_customer_id = $order->get_customer_id();
		if ($order_customer_id !== $current_user_id) {
			// Log security violation for monitoring
			sokin_gateway_log_warning(
				'Sokin Pay: Unauthorized order access attempt',
				array(
					'source' => 'sokinpay-gateway',
					'order_id' => $order_id,
					'current_user_id' => $current_user_id,
					'order_customer_id' => $order_customer_id,
				)
			);
			return;
		}
	}
	// For guest checkout, WooCommerce already validates access via order keys

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
	if ('return' === $status) {
		$order->update_status('pending', __('Customer returned from Sokin without paying.', 'sokin-pay'));
		$order->save();
		wc_add_notice(__('You cancelled the payment. Please try again.', 'sokin-pay'), 'notice');
		wp_safe_redirect($order->get_checkout_payment_url());
		exit;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if (isset($_GET['orderId']) && '' !== $_GET['orderId']) {
		// Validate that the orderId from GET parameter matches the order's stored orderId meta
		$stored_order_id = $order->get_meta('orderId');
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$get_order_id = sanitize_text_field(wp_unslash($_GET['orderId']));
		
		// Only proceed if the orderId matches the stored value for this order
		if (empty($stored_order_id) || $stored_order_id !== $get_order_id) {
			// Log mismatch for debugging (could indicate data inconsistency or manipulation attempt)
			sokin_gateway_log_warning(
				'Sokin Pay: orderId validation failed',
				array(
					'source' => 'sokinpay-gateway',
					'order_id' => $order_id,
					'stored_order_id' => $stored_order_id,
					'get_order_id' => $get_order_id,
				)
			);
			return;
		}

		$payment_gateway_id = 'sokinpay_gateway';
		$payment_gateways   = WC_Payment_Gateways::instance();
		$gateways           = $payment_gateways->payment_gateways();
		if (!isset($gateways[$payment_gateway_id])) {
			return;
		}
		$payment_gateway    = $gateways[$payment_gateway_id];

		$args = array(
			'headers' => array(
				'x-api-key' => $payment_gateway->settings['woo_cpay_x_api_key'],
				'Content-Type' => 'application/json'
			),
		);

		$url     = $payment_gateway->woo_cpay_api_url . '/orders/' . $get_order_id;
		$request = wp_remote_get($url, $args);

		if (!is_wp_error($request)) {
			$res_body  = wp_remote_retrieve_body($request);
			$json_data = json_decode($res_body, true);

			if (isset($json_data['data']['order']['payments']) && is_array($json_data['data']['order']['payments']) && !empty($json_data['data']['order']['payments']) && isset($json_data['data']['order']['payments'][0]['status'])) {
				$order_status   = $json_data['data']['order']['orderStatus'];
				$payment_status = strtolower((string) $json_data['data']['order']['payments'][0]['status']);
				if ('declined' === $payment_status) {
					$order->update_status('failed', __('Payment declined by Sokin.', 'sokin-pay'));
					$order->save();
					wc_add_notice(__('Your payment was declined. Please try again or choose a different payment method.', 'sokin-pay'), 'error');
					wp_safe_redirect($order->get_checkout_payment_url());
					exit;
				} elseif ((
					'PROCESSED' === $order_status ||
					'IN-PROGRESS' === $order_status ||
					'PENDING' === $order_status
				) && 'declined' !== $payment_status) {
					$order->update_status('processing');
					$order->save();
					$order->payment_complete();
				}
			}
		}
	}
}

add_filter('woocommerce_payment_gateways', 'woo_cpay_add_gateway_class');

function woo_cpay_add_gateway_class($gateways) {
	$gateways[] = 'WooCpay_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'woo_cpay_init_gateway_class');

function woo_cpay_init_gateway_class() {
	if (!class_exists('WC_Payment_Gateway')) {
		return; // if the WC payment gateway class
	}

	class WooCpay_Gateway extends WC_Payment_Gateway {
		/**
		 * Declared properties to avoid dynamic property creation (PHP 8.2+).
		 */
		public $woo_cpay_enabled;
		public $woo_cpay_redirect_url;
		public $woo_cpay_x_api_key;
		public $woo_cpay_api_url;

		/**
		 * Class constructor, more about it in Step 3
		 */

		public function __construct() {
			
			$this->id         = 'sokinpay_gateway'; // payment gateway plugin ID
			$this->icon       = plugin_dir_url(__FILE__) . '../assets/images/payment_methods.svg'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form

			// Set default option values for Payment Methods setting page
			$this->method_title       = 'Sokin Pay';
			$this->method_description = 'Enable a variety of secure payment options, including credit cards and pay by bank.';

			// Method with all the options fields
			$this->init_form_fields();
			// Load the settings.
			$this->init_settings();
			$this->title                 = $this->get_option('title');
			$this->description           = $this->get_option('description');
			$this->woo_cpay_enabled      = $this->get_option('woo_cpay_enabled');
			$this->woo_cpay_redirect_url =  $this->get_option('woo_cpay_redirect_url');
			$this->woo_cpay_x_api_key    = $this->get_option('woo_cpay_x_api_key');
			$this->woo_cpay_api_url      = $this->get_option('woo_cpay_api_url');

			// This action hook saves the settings
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		}

		/**
		 * Plugin options, we deal with it in Step 3 too
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'woo_cpay_enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Sokin Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'Payment Title that the customer will see on your checkout.',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __('Description', 'sokin-pay'),
					'type'        => 'text',
					'description' => __('Payment description that the customer will see on your checkout.', 'sokin-pay'),
					'desc_tip'    => true
				),
				'woo_cpay_redirect_url' => array(
					'title'       => 'Checkout URL',
					'type'        => 'text'
				),

				'woo_cpay_x_api_key' => array(
					'title'       => 'X API Key',
					'type'        => 'text'
				),

				'woo_cpay_api_url' => array(
					'title'       => 'API URL',
					'type'        => 'text'
				),
			);
		}


		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
			// I will echo() the form, but you can close PHP tags and print it directly in HTML
			echo '<p style="margin:0">' . esc_attr($this->description) . '</p>';

			// Add this action hook if you want your custom payment gateway to support it
			do_action('woocommerce_credit_card_form_start', $this->id);

			do_action('woocommerce_credit_card_form_end', $this->id);

			echo '<div class="clear"></div></fieldset>';
		}

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
		public function payment_scripts() {
			wp_register_script(
				'woocommerce_woo_cpay_js',
				plugins_url('woo_cpay_js.js', __FILE__),
				array('jquery'),
				'1.1.1',
				true
			);
			wp_enqueue_script('woocommerce_woo_cpay_js');
		}

		/*
		  * Fields validation, more in Step 5
		 */
		public function validate_fields() {
			return true;
		}

		/**
		 * Map ISO alpha-2 codes to ISO 3166-1 numeric strings.
		 *
		 * @param string $alpha2 Country code.
		 * @return string|null
		 */
		private function getIsoNumericCountryCode($alpha2) {
			static $numeric_map = array(
				'AD' => '020', 'AE' => '784', 'AF' => '004', 'AG' => '028', 'AI' => '660',
				'AL' => '008', 'AM' => '051', 'AN' => '530', 'AO' => '024', 'AQ' => '010',
				'AR' => '032', 'AS' => '016', 'AT' => '040', 'AU' => '036', 'AW' => '533',
				'AX' => '248', 'AZ' => '031', 'BA' => '070', 'BB' => '052', 'BD' => '050',
				'BE' => '056', 'BF' => '854', 'BG' => '100', 'BH' => '048', 'BI' => '108',
				'BJ' => '204', 'BL' => '652', 'BM' => '060', 'BN' => '096', 'BO' => '068',
				'BQ' => '535', 'BR' => '076', 'BS' => '044', 'BT' => '064', 'BV' => '074',
				'BW' => '072', 'BY' => '112', 'BZ' => '084', 'CA' => '124', 'CC' => '166',
				'CD' => '180', 'CF' => '140', 'CG' => '178', 'CH' => '756', 'CI' => '384',
				'CK' => '184', 'CL' => '152', 'CM' => '120', 'CN' => '156', 'CO' => '170',
				'CR' => '188', 'CU' => '192', 'CV' => '132', 'CW' => '531', 'CX' => '162',
				'CY' => '196', 'CZ' => '203', 'DE' => '276', 'DJ' => '262', 'DK' => '208',
				'DM' => '212', 'DO' => '214', 'DZ' => '012', 'EC' => '218', 'EE' => '233',
				'EG' => '818', 'EH' => '732', 'ER' => '232', 'ES' => '724', 'ET' => '231',
				'FI' => '246', 'FJ' => '242', 'FK' => '238', 'FM' => '583', 'FO' => '234',
				'FR' => '250', 'GA' => '266', 'GB' => '826', 'GD' => '308', 'GE' => '268',
				'GF' => '254', 'GG' => '831', 'GH' => '288', 'GI' => '292', 'GL' => '304',
				'GM' => '270', 'GN' => '324', 'GP' => '312', 'GQ' => '226', 'GR' => '300',
				'GS' => '239', 'GT' => '320', 'GU' => '316', 'GW' => '624', 'GY' => '328',
				'HK' => '344', 'HM' => '334', 'HN' => '340', 'HR' => '191', 'HT' => '332',
				'HU' => '348', 'ID' => '360', 'IE' => '372', 'IL' => '376', 'IM' => '833',
				'IN' => '356', 'IO' => '086', 'IQ' => '368', 'IR' => '364', 'IS' => '352',
				'IT' => '380', 'JE' => '832', 'JM' => '388', 'JO' => '400', 'JP' => '392',
				'KE' => '404', 'KG' => '417', 'KH' => '116', 'KI' => '296', 'KM' => '174',
				'KN' => '659', 'KP' => '408', 'KR' => '410', 'KW' => '414', 'KY' => '136',
				'KZ' => '398', 'LA' => '418', 'LB' => '422', 'LC' => '662', 'LI' => '438',
				'LK' => '144', 'LR' => '430', 'LS' => '426', 'LT' => '440', 'LU' => '442',
				'LV' => '428', 'LY' => '434', 'MA' => '504', 'MC' => '492', 'MD' => '498',
				'ME' => '499', 'MF' => '663', 'MG' => '450', 'MH' => '584', 'MK' => '807',
				'ML' => '466', 'MM' => '104', 'MN' => '496', 'MO' => '446', 'MP' => '580',
				'MQ' => '474', 'MR' => '478', 'MS' => '500', 'MT' => '470', 'MU' => '480',
				'MV' => '462', 'MW' => '454', 'MX' => '484', 'MY' => '458', 'MZ' => '508',
				'NA' => '516', 'NC' => '540', 'NE' => '562', 'NF' => '574', 'NG' => '566',
				'NI' => '558', 'NL' => '528', 'NO' => '578', 'NP' => '524', 'NR' => '520',
				'NU' => '570', 'NZ' => '554', 'OM' => '512', 'PA' => '591', 'PE' => '604',
				'PF' => '258', 'PG' => '598', 'PH' => '608', 'PK' => '586', 'PL' => '616',
				'PM' => '666', 'PN' => '612', 'PR' => '630', 'PS' => '275', 'PT' => '620',
				'PW' => '585', 'PY' => '600', 'QA' => '634', 'RE' => '638', 'RO' => '642',
				'RS' => '688', 'RU' => '643', 'RW' => '646', 'SA' => '682', 'SB' => '090',
				'SC' => '690', 'SD' => '729', 'SE' => '752', 'SG' => '702', 'SH' => '654',
				'SI' => '705', 'SJ' => '744', 'SK' => '703', 'SL' => '694', 'SM' => '674',
				'SN' => '686', 'SO' => '706', 'SR' => '740', 'SS' => '728', 'ST' => '678',
				'SV' => '222', 'SX' => '534', 'SY' => '760', 'SZ' => '748', 'TC' => '796',
				'TD' => '148', 'TF' => '260', 'TG' => '768', 'TH' => '764', 'TJ' => '762',
				'TK' => '772', 'TL' => '626', 'TM' => '795', 'TN' => '788', 'TO' => '776',
				'TR' => '792', 'TT' => '780', 'TV' => '798', 'TW' => '158', 'TZ' => '834',
				'UA' => '804', 'UG' => '800', 'UM' => '581', 'US' => '840', 'UY' => '858',
				'UZ' => '860', 'VA' => '336', 'VC' => '670', 'VE' => '862', 'VG' => '092',
				'VI' => '850', 'VN' => '704', 'VU' => '548', 'WF' => '876', 'WS' => '882',
				'XK' => '383', 'YE' => '887', 'YT' => '175', 'ZA' => '710', 'ZM' => '894',
				'ZW' => '716',
			);

			$alpha2 = strtoupper((string) $alpha2);

			if ('' === $alpha2) {
				return null;
			}

			return $numeric_map[$alpha2] ?? null;
		}

		/**
		 * Normalize address values for comparison and payload generation.
		 * Ensures null and empty strings are treated equivalently.
		 *
		 * @param mixed $value
		 * @return string|mixed
		 */
		private function normalizeAddressValue($value) {
			return (null === $value || '' === $value) ? '' : $value;
		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment($order_id) {
			
			$order = wc_get_order($order_id);

		// Assign status value to $status
		if (isset($this->order_status)) {
			$status = 'wc-' === substr($this->order_status, 0, 3) ? substr($this->order_status, 3) : $this->order_status;
		} else {
			$status = $order->get_status();
		}

		// Set order status
		$order->update_status($status, __('Checkout with custom payment. ', 'sokin-pay'));

		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		WC()->cart->empty_cart();

		// Initiating Order on Sokin Pay
		$order_date = gmdate('Y-m-d', strtotime($order->get_date_created()));

		$billing_address = array(
			'line1' => $order->get_billing_address_1(),
			'line2' => $order->get_billing_address_2(),
			'city' => $order->get_billing_city(),
			'state' => $order->get_billing_state(),
			'post_code' => $order->get_billing_postcode(),
			'country' => $this->getIsoNumericCountryCode($order->get_billing_country()),
		);

		$shipping_address = array(
			'line1' => $order->get_shipping_address_1(),
			'line2' => $order->get_shipping_address_2(),
			'city' => $order->get_shipping_city(),
			'state' => $order->get_shipping_state(),
			'post_code' => $order->get_shipping_postcode(),
			'country' => $this->getIsoNumericCountryCode($order->get_shipping_country()),
		);

		$should_include_shipping = false;

		if ($order->has_shipping_address()) {
			$shipping_values = array_filter($shipping_address, function ($value) {
				return null !== $value && '' !== $value;
			});

			if (!empty($shipping_values)) {
				foreach ($shipping_address as $key => $value) {
					$billing_value = $billing_address[$key] ?? null;

					$normalized_billing = $this->normalizeAddressValue($billing_value);
					$normalized_shipping = $this->normalizeAddressValue($value);

					if ($normalized_shipping !== $normalized_billing) {
						$should_include_shipping = true;
						break;
					}
				}
			}
		}

		$body = array(
			'type' => 'SINGLE',
			'currency' => $order->get_currency(),
			'totalAmount' => $order->get_total(),
			'description' => '',
			'redirectURL' => $order->get_checkout_order_received_url(),
			'referenceNo' => gmdate('Ymds'),
			'memo' => '',
			'recurring' => array(
				'frequency' => 'ONCE',
				'paymentCount' => 1,
				'firstPaymentDate' => $order_date,
				'firstPaymentAmount' => $order->get_total()
			),
			'firstName' => $order->get_billing_first_name(),
			'lastName' => $order->get_billing_last_name(),
			'email' => $order->get_billing_email(),
			'billing_address' => $billing_address,
			'save_card' => true,
			'payment_method' => [],
			'isExternal' => true
		);

		if ($should_include_shipping) {
			$body['shipping_address'] = $shipping_address;
		}

		$args = array(
			'headers' => array(
				'x-api-key' => $this->settings['woo_cpay_x_api_key'],
				'Content-Type' => 'application/json'
			),
			'body' => wp_json_encode($body)
		);

		$response     = wp_remote_post($this->settings['woo_cpay_api_url'] . '/orders', $args);
		$responseBody = wp_remote_retrieve_body($response);

		$responceData = ( !is_wp_error($response) ) ? json_decode($responseBody, true) : null;
		$redirect_url = null;

		if (!is_wp_error($response) && is_array($responceData) && isset($responceData['corporateId'], $responceData['orderId'])) {
			$redirect_url = $this->settings['woo_cpay_redirect_url'] . '/' . $responceData['corporateId'] . '/' . $responceData['orderId'];

			// Add Sokin's Payment meta data
			$order = new WC_Order($order_id);
			$order->update_meta_data('orderId', $responceData['orderId']);
			$order->update_meta_data('corporateId', $responceData['corporateId']);
			$order->save();
		}

		if (is_array($responceData) && isset($responceData['success'], $responceData['status']) && !$responceData['success'] && 400 === (int) $responceData['status']) {
			$message = isset($responceData['message']) ? $responceData['message'] : 'Unexpected error while creating the payment.';
			wc_add_notice('Payment Error: ' . esc_html($message), 'error');
			return array(
				'result'   => 'failure',
				'redirect' => $order->get_checkout_payment_url(),
			);
		}

		// If we don't have a valid redirect URL by now, treat as failure
		if (empty($redirect_url)) {
			wc_add_notice('Payment Error: Unable to initialize payment. Please try again.', 'error');
			return array(
				'result'   => 'failure',
				'redirect' => $order->get_checkout_payment_url(),
			);
		}

		// Return thankyou redirect
		return array(
			'result'    => 'success',
			'redirect'  => $redirect_url
		);
		}
	}
}

// Remove Refund button if Order status is Failed
add_filter('woocommerce_order_actions', 'remove_refund_button_for_failed_orders', 10, 2);

/**
 * Hide refund button for failed orders.
 *
 * @param array    $actions Order actions.
 * @param WC_Order $order   WooCommerce order object.
 *
 * @return array
 */
function remove_refund_button_for_failed_orders($actions, $order) {
	if ('failed' === $order->get_status()) {
		$css = '<style>.button.refund-items{display: none;}</style>';
		echo wp_kses($css, array('style' => array()));
	}
	return $actions;
}

