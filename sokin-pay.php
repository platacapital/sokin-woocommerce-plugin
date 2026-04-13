<?php
/**
 * Plugin Name: Sokin Pay
 * Plugin URI:
 * Description: This plugin seamlessly integrates with your WooCommerce store, providing a secure and efficient way to process payments. Enable a variety of secure payment options, including credit cards and pay by bank.
 * Version: 1.1.3
 * Author: Sokin
 * Author URI:
 * Requires at least: 6.5
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sokin-pay
 *
 * @package Platasokin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PLATASOKIN_VERSION', '1.1.3' );

/**
 * Plugin activation callback.
 */
function platasokin_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-platasokin-activator.php';
	Platasokin_Activator::activate();
}

/**
 * Register front-end stylesheet.
 */
function platasokin_register_gateway_styles() {
	wp_register_style(
		'platasokin_gateway_style',
		plugin_dir_url( __FILE__ ) . 'assets/css/style.css',
		array(),
		PLATASOKIN_VERSION,
		'all'
	);
	wp_enqueue_style( 'platasokin_gateway_style' );
}

add_action( 'wp_enqueue_scripts', 'platasokin_register_gateway_styles' );

/**
 * Register admin script for WooCommerce order screens.
 *
 * @param string $hook Current admin hook suffix.
 */
function platasokin_register_gateway_scripts( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	if ( function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( ! $screen || 'shop_order' !== $screen->post_type ) {
			return;
		}
	}

	wp_register_script(
		'platasokin_gateway_admin',
		plugin_dir_url( __FILE__ ) . 'includes/js/platasokin-admin-order.js',
		array( 'jquery' ),
		PLATASOKIN_VERSION,
		true
	);
	wp_enqueue_script( 'platasokin_gateway_admin' );
}

add_action( 'admin_enqueue_scripts', 'platasokin_register_gateway_scripts' );

/**
 * Plugin deactivation callback.
 */
function platasokin_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-platasokin-deactivator.php';
	Platasokin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'platasokin_activate' );
register_deactivation_hook( __FILE__, 'platasokin_deactivate' );

require plugin_dir_path( __FILE__ ) . 'includes/class-platasokin-plugin.php';

/**
 * Bootstrap plugin runtime (WooCommerce gateway is loaded from here).
 */
function platasokin_run() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-platasokin-wc-gateway.php';
	$plugin = new Platasokin_Plugin();
	$plugin->run();
}

platasokin_run();

/**
 * Declare compatibility with cart/checkout blocks.
 */
function platasokin_declare_cart_checkout_blocks_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}

add_action( 'before_woocommerce_init', 'platasokin_declare_cart_checkout_blocks_compatibility' );

/**
 * Register Blocks checkout payment method type.
 */
function platasokin_register_blocks_payment_method_type() {
	if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'includes/class-platasokin-blocks.php';

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
			$payment_method_registry->register( new Platasokin_Blocks_Payment_Method() );
		}
	);
}

add_action( 'woocommerce_blocks_loaded', 'platasokin_register_blocks_payment_method_type' );
