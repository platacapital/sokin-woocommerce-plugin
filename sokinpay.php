<?php

/**
 * Plugin Name: Sokin Pay
 * Plugin URI:
 * Description: This plugin seamlessly integrates with your WooCommerce store, providing a secure and efficient way to process payments. Enable a variety of secure payment options, including credit cards and pay by bank.
 * Version: 1.1.1
 * Author: Sokin
 * Author URI:
 * Requires at least: 6.5
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Stable tag: sokinpay
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sokin-pay
 */


/**
 * If this file is called directly, abort.
 */
if (!defined('WPINC')) {
	die;
}

/**
 * Define plugin's Version constant
 */
define('WOO_CUSTOM_PAYMENT', '1.1.1');

/**
 * The code that runs during plugin activation
 */
function activate_woo_cpay() {
	require_once plugin_dir_path(__FILE__) . 'includes/class_woo_cpay_activator.php';
	WooCPay_Activator::activate();
}

/**
 * Register the stylesheet
 */
function register_sokinpay_gateway_styles() {
	wp_register_style('sokinpay_gateway_style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.1.1', 'all');
	wp_enqueue_style('sokinpay_gateway_style');
}

// Enqueue the stylesheet - sokinpay_gatewaysokinpay_style
add_action('wp_enqueue_scripts', 'register_sokinpay_gateway_styles');

/**
 * Register custom javascript file for Admin panel pages use only
 */
function register_sokinpay_gateway_scripts($hook) {
	wp_register_script('sokinpay_gateway_js', plugin_dir_url(__FILE__) . 'includes/js/woo_cpay_js.js', array(), '1.1.1', array('in_footer' => true));
	wp_enqueue_script('sokinpay_gateway_js');
}

// Enqueue the javascript - sokinpay_gateway_js
add_action('admin_enqueue_scripts', 'register_sokinpay_gateway_scripts');

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_woo_cpay() {
	require_once plugin_dir_path(__FILE__) . 'includes/class_woo_cpay_deactivator.php';
	WooCPay_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_woo_cpay');
register_deactivation_hook(__FILE__, 'deactivate_woo_cpay');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class_woo_cpay.php';

/**
 * Function that executes the plugin
 */
function run_woo_cpay() {
	require_once plugin_dir_path(__FILE__) . 'includes/class_woo_cpay_woo_functions.php';
	$plugin = new WooCPay();
	$plugin->run();
}

/**
 * Execute the plugin
 */
run_woo_cpay();

// Checkout Block Support implementation
add_filter('woocommerce_payment_gateways', 'add_sokinpay_gateway');

function add_sokinpay_gateway($gateways) {
	$gateways[] = 'WooCpay_Gateway';
	return $gateways;
}

/**
 * Custom function to declare compatibility with cart_checkout_blocks feature
 */
function declare_sokinpay_gateway_cart_checkout_blocks_compatibility() {
	// Check if the required class exists
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		// Declare compatibility for 'cart_checkout_blocks'
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_sokinpay_gateway_cart_checkout_blocks_compatibility');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action('woocommerce_blocks_loaded', 'sokinpay_gateway_register_order_approval_payment_method_type');
/**
 * Custom function to register a payment method type
 */
function sokinpay_gateway_register_order_approval_payment_method_type() {
	// Check if the required class exists
	if (! class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		return;
	}
	// Include the custom Blocks Checkout class
	require_once plugin_dir_path(__FILE__) . 'includes/class-block.php';
	// Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
			// Register an instance of SokinPay_Blocks_Class
			$payment_method_registry->register(new SokinPay_Blocks_Class());
		}
	);
}
