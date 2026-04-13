<?php
/**
 * WooCommerce Blocks payment method integration.
 *
 * @package Platasokin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registers Sokin Pay for the Blocks checkout.
 */
final class Platasokin_Blocks_Payment_Method extends AbstractPaymentMethodType {

	/**
	 * Gateway instance.
	 *
	 * @var Platasokin_WC_Gateway|null
	 */
	private $gateway;

	/**
	 * Payment method id (matches classic gateway).
	 *
	 * @var string
	 */
	protected $name = 'sokinpay_gateway';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_sokinpay_gateway_settings', array() );
		$this->gateway  = new Platasokin_WC_Gateway();
	}

	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'platasokin_gateway_blocks',
			plugin_dir_url( __FILE__ ) . 'checkout.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			PLATASOKIN_VERSION,
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'platasokin_gateway_blocks', 'sokin-pay' );
		}
		return array( 'platasokin_gateway_blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
		);
	}
}
