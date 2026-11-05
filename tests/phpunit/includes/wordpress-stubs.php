<?php
/**
 * Doubles for the WordPress and WooCommerce functions the gateway file calls.
 *
 * Each one reads or records through Platasokin_Test_Env so tests can drive inputs
 * and assert on outputs without a WordPress install.
 *
 * @package Platasokin
 */

/**
 * @param string                $hook     Hook name.
 * @param callable|string       $callback Callback.
 * @param int                   $priority Priority.
 * @param int                   $args     Accepted args.
 * @return true
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	Platasokin_Test_Env::$hooks[] = array(
		'type'     => 'action',
		'hook'     => $hook,
		'callback' => $callback,
		'priority' => $priority,
		'args'     => $args,
	);
	return true;
}

/**
 * @param string          $hook     Hook name.
 * @param callable|string $callback Callback.
 * @param int             $priority Priority.
 * @param int             $args     Accepted args.
 * @return true
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	Platasokin_Test_Env::$hooks[] = array(
		'type'     => 'filter',
		'hook'     => $hook,
		'callback' => $callback,
		'priority' => $priority,
		'args'     => $args,
	);
	return true;
}

/**
 * @param int $order_id Order ID.
 * @return Platasokin_Test_Order|false
 */
function wc_get_order( $order_id ) {
	$order_id = (int) $order_id;
	return isset( Platasokin_Test_Env::$orders[ $order_id ] )
		? Platasokin_Test_Env::$orders[ $order_id ]
		: false;
}

/**
 * @return int
 */
function get_current_user_id() {
	return Platasokin_Test_Env::$current_user_id;
}

/**
 * @param mixed $value Raw value.
 * @return string
 */
function sanitize_text_field( $value ) {
	return trim( wp_strip_all_tags( (string) $value ) );
}

/**
 * @param string $value Raw value.
 * @return string
 */
function wp_strip_all_tags( $value ) {
	return strip_tags( $value );
}

/**
 * @param mixed $value Possibly slashed value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

/**
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function __( $text, $domain = 'default' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.textFound
	return $text;
}

/**
 * @param string $message Notice body.
 * @param string $type    Notice type.
 */
function wc_add_notice( $message, $type = 'success' ) {
	Platasokin_Test_Env::$notices[] = array(
		'message' => $message,
		'type'    => $type,
	);
}

/**
 * Records the redirect and throws, because production code calls exit straight after.
 *
 * @param string $location Target URL.
 * @param int    $status   HTTP status.
 * @throws Platasokin_Test_Redirect_Exception Always.
 */
function wp_safe_redirect( $location, $status = 302 ) {
	Platasokin_Test_Env::$redirects[] = $location;
	throw new Platasokin_Test_Redirect_Exception( $location );
}

/**
 * @param string               $url  Request URL.
 * @param array<string, mixed> $args Request args.
 * @return mixed Next queued response, or an empty 200 when the queue is empty.
 */
function wp_remote_get( $url, $args = array() ) {
	Platasokin_Test_Env::$remote_get_calls[] = array(
		'url'  => $url,
		'args' => $args,
	);

	if ( empty( Platasokin_Test_Env::$remote_get_responses ) ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '',
		);
	}

	return array_shift( Platasokin_Test_Env::$remote_get_responses );
}

/**
 * @param mixed $thing Value to inspect.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * @param mixed $response Response from wp_remote_get().
 * @return string
 */
function wp_remote_retrieve_body( $response ) {
	if ( is_array( $response ) && isset( $response['body'] ) ) {
		return (string) $response['body'];
	}
	return '';
}

/**
 * @return array<string, string>
 */
function wp_upload_dir() {
	return array( 'basedir' => Platasokin_Test_Env::$uploads_basedir );
}

/**
 * @param string $value Path or URL.
 * @return string
 */
function trailingslashit( $value ) {
	return rtrim( (string) $value, "/\\" ) . '/';
}

/**
 * @param string $path Path to check.
 * @return bool
 */
function wp_is_writable( $path ) {
	if ( array_key_exists( $path, Platasokin_Test_Env::$writable_overrides ) ) {
		return Platasokin_Test_Env::$writable_overrides[ $path ];
	}
	return Platasokin_Test_Env::$writable;
}

/**
 * @return Platasokin_Test_Logger
 */
function wc_get_logger() {
	return Platasokin_Test_Env::$logger;
}

/**
 * @param mixed $data    Value to encode.
 * @param int   $options Encode options.
 * @param int   $depth   Max depth.
 * @return string|false
 */
function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
}
