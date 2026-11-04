<?php
/**
 * Mutable state shared by the WordPress/WooCommerce function doubles.
 *
 * @package Platasokin
 */

/**
 * Registry the stubbed WordPress functions read from and write to.
 */
class Platasokin_Test_Env {

	/**
	 * @var int
	 */
	public static $current_user_id = 0;

	/**
	 * Orders keyed by order ID, as returned by wc_get_order().
	 *
	 * @var array<int, Platasokin_Test_Order>
	 */
	public static $orders = array();

	/**
	 * Notices recorded by wc_add_notice(), each as array{message: string, type: string}.
	 *
	 * @var array<int, array<string, string>>
	 */
	public static $notices = array();

	/**
	 * Locations passed to wp_safe_redirect().
	 *
	 * @var array<int, string>
	 */
	public static $redirects = array();

	/**
	 * Calls made to wp_remote_get(), each as array{url: string, args: array}.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $remote_get_calls = array();

	/**
	 * Queued wp_remote_get() return values, consumed in order.
	 *
	 * @var array<int, mixed>
	 */
	public static $remote_get_responses = array();

	/**
	 * Hook registrations recorded by add_action()/add_filter().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public static $hooks = array();

	/**
	 * Value returned as the uploads basedir.
	 *
	 * @var string
	 */
	public static $uploads_basedir = '';

	/**
	 * Default answer for wp_is_writable().
	 *
	 * @var bool
	 */
	public static $writable = true;

	/**
	 * Per-path wp_is_writable() answers that win over the default.
	 *
	 * @var array<string, bool>
	 */
	public static $writable_overrides = array();

	/**
	 * @var Platasokin_Test_Logger|null
	 */
	public static $logger = null;

	/**
	 * Restore every static to its default. Hook registrations survive because the
	 * plugin file registers them once, at include time.
	 */
	public static function reset() {
		self::$current_user_id      = 0;
		self::$orders               = array();
		self::$notices              = array();
		self::$redirects            = array();
		self::$remote_get_calls     = array();
		self::$remote_get_responses = array();
		self::$uploads_basedir      = '';
		self::$writable             = true;
		self::$writable_overrides   = array();
		self::$logger               = new Platasokin_Test_Logger();

		WC_Payment_Gateways::reset();
	}
}

/**
 * Stand-in for WC_Logger that keeps every warning in memory.
 */
class Platasokin_Test_Logger {

	/**
	 * @var array<int, array<string, mixed>>
	 */
	public $warnings = array();

	/**
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function warning( $message, $context = array() ) {
		$this->warnings[] = array(
			'message' => $message,
			'context' => $context,
		);
	}
}

/**
 * Thrown by the wp_safe_redirect() double so tests can observe the redirect that
 * production code immediately follows with exit.
 */
class Platasokin_Test_Redirect_Exception extends RuntimeException {

	/**
	 * @var string
	 */
	public $location;

	/**
	 * @param string $location Redirect target.
	 */
	public function __construct( $location ) {
		$this->location = $location;
		parent::__construct( 'Redirected to ' . $location );
	}
}

/**
 * Minimal WC_Order double covering the surface the thank-you handler touches.
 */
class Platasokin_Test_Order {

	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var string
	 */
	public $payment_method = 'sokinpay_gateway';

	/**
	 * @var int
	 */
	public $customer_id = 0;

	/**
	 * @var array<string, mixed>
	 */
	public $meta = array();

	/**
	 * @var string
	 */
	public $status = 'pending';

	/**
	 * @var string
	 */
	public $checkout_payment_url = 'https://shop.test/checkout/order-pay/4242/';

	/**
	 * Every update_status() call, as array{status: string, note: string}.
	 *
	 * @var array<int, array<string, string>>
	 */
	public $status_transitions = array();

	/**
	 * @var int
	 */
	public $save_calls = 0;

	/**
	 * @var int
	 */
	public $payment_complete_calls = 0;

	/**
	 * @param int $id Order ID.
	 */
	public function __construct( $id ) {
		$this->id = $id;
	}

	/**
	 * @return string
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * @return int
	 */
	public function get_customer_id() {
		return $this->customer_id;
	}

	/**
	 * @param string $key Meta key.
	 * @return mixed Empty string when unset, matching WC_Data::get_meta().
	 */
	public function get_meta( $key ) {
		return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : '';
	}

	/**
	 * @param string $status New status.
	 * @param string $note   Optional note.
	 */
	public function update_status( $status, $note = '' ) {
		$this->status               = $status;
		$this->status_transitions[] = array(
			'status' => $status,
			'note'   => $note,
		);
	}

	/**
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	public function save() {
		++$this->save_calls;
	}

	public function payment_complete() {
		++$this->payment_complete_calls;
	}

	/**
	 * @return string
	 */
	public function get_checkout_payment_url() {
		return $this->checkout_payment_url;
	}
}

/**
 * Gateway double exposing only the two members the handler reads.
 */
class Platasokin_Test_Gateway {

	/**
	 * @var array<string, string>
	 */
	public $settings = array( 'platasokin_x_api_key' => 'test-x-api-key' );

	/**
	 * @var string
	 */
	public $platasokin_api_url = 'https://api.sokin.test/v1';
}

/**
 * Stand-in for the WooCommerce gateway registry singleton.
 */
class WC_Payment_Gateways {

	/**
	 * @var array<string, mixed>
	 */
	public static $registered = array();

	/**
	 * @var WC_Payment_Gateways|null
	 */
	private static $instance = null;

	/**
	 * @return WC_Payment_Gateways
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function reset() {
		self::$registered = array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function payment_gateways() {
		return self::$registered;
	}
}

/**
 * Minimal WP_Error double.
 */
class WP_Error {

	/**
	 * @var string
	 */
	public $code;

	/**
	 * @var string
	 */
	public $message;

	/**
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}
}
