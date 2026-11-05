<?php
/**
 * PHPUnit bootstrap. Installs the WordPress/WooCommerce doubles, then loads the
 * gateway file under test.
 *
 * @package Platasokin
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'PLATASOKIN_VERSION', 'test' );

require_once __DIR__ . '/includes/class-platasokin-test-env.php';
require_once __DIR__ . '/includes/wordpress-stubs.php';
require_once __DIR__ . '/includes/class-platasokin-testcase.php';

Platasokin_Test_Env::reset();

require_once ABSPATH . 'includes/class-platasokin-wc-gateway.php';
