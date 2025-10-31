<?php
/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

add_action('woocommerce_refund_created', 'sokinpay_create_refund', 10, 2);

/**
 * Function for `woocommerce_refund_created` action-hook.
 * 
 * @param  $refund_id 
 * @param  $args      
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
	$payment_gateway = $payment_gateways->payment_gateways()[$payment_gateway_id];

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
			'currency' => $order->get_order_currency(),
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

		//update_option('ced_utk_test', $response);

		if (is_wp_error($response)) {
			return;
		}

		$res_body = wp_remote_retrieve_body($response);

		//update_option('ced_utk_test_2', $res_body );

		$json_data = json_decode($res_body, true);
		// Show error message if we are doing partial payment on same day which is not allowed.
		// Same day transfer will be fully refunded.
		if (is_array($json_data) && isset($json_data['success'], $json_data['status']) && !$json_data['success'] && 400 == $json_data['status']) {
			throw new Exception(esc_attr($json_data['message']));
			wp_die();
		}
	}
}

// Thank you page
add_action('woocommerce_thankyou', 'action_woocommerce_thankyou', 10, 1);
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
			if (function_exists('wc_get_logger')) {
				$logger = wc_get_logger();
				$logger->warning(
					'Sokin Pay: Unauthorized order access attempt',
					array(
						'source' => 'sokinpay-gateway',
						'order_id' => $order_id,
						'current_user_id' => $current_user_id,
						'order_customer_id' => $order_customer_id,
					)
				);
			}
			return;
		}
	}
	// For guest checkout, WooCommerce already validates access via order keys

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if (isset($_GET['status']) && 'return' === $_GET['status']) {
		$order->update_status('pending', __('Customer returned from Sokin without paying.', 'sokinpay'));
		$order->save();
		wc_add_notice(__('You cancelled the payment. Please try again.', 'sokinpay'), 'notice');
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
			if (function_exists('wc_get_logger')) {
				$logger = wc_get_logger();
				$logger->warning(
					'Sokin Pay: orderId validation failed',
					array(
						'source' => 'sokinpay-gateway',
						'order_id' => $order_id,
						'stored_order_id' => $stored_order_id,
						'get_order_id' => $get_order_id,
					)
				);
			}
			return;
		}

		$payment_gateway_id = 'sokinpay_gateway';
		$payment_gateways   = WC_Payment_Gateways::instance();
		$payment_gateway    = $payment_gateways->payment_gateways()[$payment_gateway_id];

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
				$order_status = $json_data['data']['order']['orderStatus'];
				if (strtolower($json_data['data']['order']['payments'][0]['status']) == 'declined') {
					$order->update_status('failed', __('Payment declined by Sokin.', 'sokinpay'));
					$order->save();
					wc_add_notice(__('Your payment was declined. Please try again or choose a different payment method.', 'sokinpay'), 'error');
					wp_safe_redirect($order->get_checkout_payment_url());
					exit;
				} elseif (( 'PROCESSED' == $order_status || 'IN-PROGRESS' == $order_status ) && strtolower($json_data['data']['order']['payments'][0]['status']) != 'declined') {
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
					'title'       => __('Description', 'sokinpay'),
					'type'        => 'text',
					'description' => __('Payment description that the customer will see on your checkout.', 'sokinpay'),
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
			wp_enqueue_script('woocommerce_woo_cpay_js');
			wp_register_script('woocommerce_woo_cpay_js', plugins_url('woo_cpay_js.js', __FILE__), array('jquery', 'woo_cpay_js'), '1.0.3', array('in_footer' => true));
		}

		/*
		  * Fields validation, more in Step 5
		 */
		public function validate_fields() {
			return true;
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
				$status = $order->status;
			}

			// Set order status
			$order->update_status($status, __('Checkout with custom payment. ', 'sokinpay'));

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// Initiating Order on Sokin Pay
			$order_date = gmdate('Y-m-d', strtotime($order->get_date_created()));

			$body = array(
				'type' => 'SINGLE',
				'currency' => $order->get_order_currency(),
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
				'country' => $order->get_billing_country(),
				'addressLine1' => $order->get_billing_address_1(),
				'addressLine2' => $order->get_billing_address_2(),
				'postTown' => '',
				'postCode' => $order->get_billing_postcode(),
				'city' => $order->get_billing_city(),
				'save_card' => true,
				'payment_method' => [],
				'isExternal' => true
			);

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

			if (is_array($responceData) && isset($responceData['success'], $responceData['status']) && !$responceData['success'] && 400 == $responceData['status']) {
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
function remove_refund_button_for_failed_orders($actions, $order) {
	// Check if the order status is 'failed'
	if ('failed' == $order->status) {
		// Unset the refund button
		echo '<style>.button.refund-items{display: none;}</style>';
	}
	return $actions;
}

// Replace the default message if Order failed or returned without making payment.
//add_action('template_redirect', 'custom_return_status_action');
//function custom_return_status_action() {
	// Check if it's the checkout page
//	if (is_checkout()) {

		// Check for the 'status' parameter in the URL, e.g., ?status=return
//		add_filter('woocommerce_thankyou_order_received_text', 'custom_thank_you_message', 10, 2);

//		function custom_thank_you_message($custom_message, $order) {

//			if (isset($order) && !empty($order)) {

//				$payment_method = $order->get_payment_method();

//				if ('sokinpay_gateway' !=  $payment_method) {

//					return $custom_message;
//				}


//				$payment_gateway_id = 'sokinpay_gateway';

				// Get an instance of the WC_Payment_Gateways object
//				$payment_gateways = WC_Payment_Gateways::instance();

				// Get the desired WC_Payment_Gateway object
//				$payment_gateway = $payment_gateways->payment_gateways()[$payment_gateway_id];

//				$args = array(
//					'headers' => array(
//						'x-api-key' => $payment_gateway->settings['woo_cpay_x_api_key'],
//						'Content-Type' => 'application/json'
//					),
//				);

					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
//					$orderId = isset($_GET['orderId']) ? sanitize_text_field(wp_unslash($_GET['orderId'])) : '';

					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
//					$status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

				

//					$url = $payment_gateway->woo_cpay_api_url . '/orders/' . sanitize_text_field(wp_unslash($orderId));

//					$request = wp_remote_get($url, $args);

//				if (!is_wp_error($request)) {
//					$res_body  = wp_remote_retrieve_body($request);
//					$json_data = json_decode($res_body, true);

//					if ('declined' == strtolower($json_data['data']['order']['payments'][0]['status'])) {
//						$custom_message = '<div style="position: relative; padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;" role="alert">Order #' . $order->id . ' is failed</div>';
//					}
//				}
//				if (wp_unslash('return' == $status)) {
//					$custom_message = '<div style="position: relative; padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;" role="alert">Order #' . $order->id . ' is Pending payment</div>';
//				}
//					return $custom_message;
				
//			}



			

//		}
//	}
//}
