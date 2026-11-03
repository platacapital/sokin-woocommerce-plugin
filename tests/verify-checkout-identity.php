<?php
/**
 * Verify the renamed plugin against WordPress and WooCommerce using WP-CLI.
 *
 * Run: wp eval-file wp-content/plugins/sokin-checkout/tests/verify-checkout-identity.php
 */

function platasokin_assert_checkout_identity( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo 'PASS: ', $message, PHP_EOL;
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';
$metadata = get_plugin_data( WP_PLUGIN_DIR . '/sokin-checkout/sokin-checkout.php', false, false );
platasokin_assert_checkout_identity( 'Sokin Checkout' === $metadata['Name'], 'Plugin display name is Sokin Checkout' );
platasokin_assert_checkout_identity( 'sokin-checkout' === $metadata['TextDomain'], 'Text domain matches the WordPress.org slug' );

$option_filter = static function () {
	return null;
};
add_filter( 'pre_option_woocommerce_sokinpay_gateway_settings', $option_filter );
$fresh = new Platasokin_WC_Gateway();
remove_filter( 'pre_option_woocommerce_sokinpay_gateway_settings', $option_filter );
platasokin_assert_checkout_identity( 'Sokin Checkout' === $fresh->title, 'Fresh checkout title uses the new brand' );
platasokin_assert_checkout_identity( 'no' === $fresh->enabled, 'Fresh gateway remains disabled' );
platasokin_assert_checkout_identity( 'https://pay.sokin.com/sokinpay/customerPay' === $fresh->platasokin_redirect_url, 'Hosted-checkout API path is preserved' );

$option_filter = static function () {
	return array(
		'enabled'                  => 'yes',
		'title'                    => 'Existing merchant title',
		'description'              => 'Existing merchant description',
		'platasokin_x_api_key'      => 'existing-test-key',
		'platasokin_api_url'        => 'https://payment-gateway.uat.sokin.com/api/services/v1',
		'platasokin_redirect_url'   => 'https://pay.uat.sokin.com/sokinpay/customerPay',
	);
};
add_filter( 'pre_option_woocommerce_sokinpay_gateway_settings', $option_filter );
try {
	$existing = new Platasokin_WC_Gateway();
	platasokin_assert_checkout_identity( 'sokinpay_gateway' === $existing->id, 'Existing order payment-method ID is preserved' );
	platasokin_assert_checkout_identity( 'Sokin Checkout' === $existing->method_title, 'Admin gateway name uses the new brand' );
	platasokin_assert_checkout_identity( 'Existing merchant title' === $existing->title, 'Merchant checkout title is preserved' );
	platasokin_assert_checkout_identity( 'existing-test-key' === $existing->platasokin_x_api_key, 'Stored API-key setting is preserved' );
	platasokin_assert_checkout_identity( 'https://payment-gateway.uat.sokin.com/api/services/v1' === $existing->platasokin_api_url, 'Stored API URL is preserved' );
	platasokin_assert_checkout_identity( 'yes' === $existing->enabled, 'Stored enabled setting is preserved' );

	$blocks = new Platasokin_Blocks_Payment_Method();
	$blocks->initialize();
	platasokin_assert_checkout_identity( 'sokinpay_gateway' === $blocks->get_name(), 'Blocks payment-method ID is preserved' );
	platasokin_assert_checkout_identity( 'Existing merchant title' === $blocks->get_payment_method_data()['title'], 'Blocks checkout uses the saved merchant title' );
	$handles = $blocks->get_payment_method_script_handles();
	platasokin_assert_checkout_identity( 'sokin-checkout' === wp_scripts()->registered[ $handles[0] ]->textdomain, 'Blocks scripts load the renamed translation domain' );
} finally {
	remove_filter( 'pre_option_woocommerce_sokinpay_gateway_settings', $option_filter );
}
