<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class SokinPay_Blocks_Class extends AbstractPaymentMethodType {

  private $gateway;
  protected $name = 'sokinpay_gateway'; // your payment gateway name
	public function initialize() {
	  $this->settings = get_option('woocommerce_sokinpay_gateway_settings', []);
	  $this->gateway  = new WooCpay_Gateway();
	}
	public function is_active() {
	  return $this->gateway->is_available();
	}
	public function get_payment_method_script_handles() {
	  wp_register_script(
		'sokinpay_gateway-blocks-integration',
		plugin_dir_url(__FILE__) . 'checkout.js',
		[
		'wc-blocks-registry',
		'wc-settings',
		'wp-element',
		'wp-html-entities',
		'wp-i18n',
		],
		null,
		true
	  );
		if (function_exists('wp_set_script_translations')) {
		  wp_set_script_translations('sokinpay_gateway-blocks-integration');
		}
	  return ['sokinpay_gateway-blocks-integration'];
	}

	public function get_payment_method_data() {
	  return [
		'title' => $this->gateway->title,
		'description' => $this->gateway->description,
	  ];
	}
}
