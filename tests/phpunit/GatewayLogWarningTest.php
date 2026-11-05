<?php
/**
 * Tests for platasokin_gateway_log_warning(), whose redaction allowlist GWEM-1301
 * renamed from stored_order_id to sokin_order_id.
 *
 * @package Platasokin
 */

/**
 * @covers ::platasokin_gateway_log_warning
 */
final class GatewayLogWarningTest extends Platasokin_TestCase {

	/**
	 * @var string
	 */
	private $error_log_file;

	/**
	 * @var string|false
	 */
	private $previous_error_log;

	protected function setUp(): void {
		parent::setUp();

		$this->error_log_file     = sys_get_temp_dir() . '/platasokin-error-' . uniqid( '', true ) . '.log';
		$this->previous_error_log = ini_get( 'error_log' );
		ini_set( 'error_log', $this->error_log_file );
	}

	protected function tearDown(): void {
		ini_set( 'error_log', false === $this->previous_error_log ? '' : $this->previous_error_log );

		if ( file_exists( $this->error_log_file ) ) {
			unlink( $this->error_log_file );
		}

		parent::tearDown();
	}

	/**
	 * Everything PHP wrote to the error log during this test.
	 *
	 * @return string
	 */
	private function errorLogContents() {
		return file_exists( $this->error_log_file ) ? file_get_contents( $this->error_log_file ) : '';
	}

	/**
	 * The JSON blob appended to the fallback error_log line, decoded.
	 *
	 * @return array<string, mixed>
	 */
	private function loggedContext() {
		$contents = $this->errorLogContents();
		$start    = strpos( $contents, '{' );

		$this->assertNotFalse( $start, 'Expected a JSON context in the error log output.' );

		return json_decode( trim( substr( $contents, $start ) ), true );
	}

	/**
	 * Make the uploads tree look unwritable so the error_log fallback is taken.
	 */
	private function forceFallback() {
		Platasokin_Test_Env::$writable = false;
	}

	/*
	 * Choosing between the WooCommerce logger and the error_log fallback.
	 */

	public function test_uses_woocommerce_logger_when_uploads_basedir_is_writable() {
		platasokin_gateway_log_warning( 'Sokin Pay: something happened' );

		$this->assertCount( 1, Platasokin_Test_Env::$logger->warnings );
		$this->assertSame( 'Sokin Pay: something happened', Platasokin_Test_Env::$logger->warnings[0]['message'] );
		$this->assertSame( '', $this->errorLogContents() );
	}

	public function test_uses_woocommerce_logger_when_wc_logs_directory_exists_and_is_writable() {
		mkdir( $this->uploads_dir . '/wc-logs' );

		platasokin_gateway_log_warning( 'Sokin Pay: something happened' );

		$this->assertCount( 1, Platasokin_Test_Env::$logger->warnings );
		$this->assertSame( '', $this->errorLogContents() );
	}

	public function test_falls_back_when_wc_logs_directory_exists_but_is_not_writable() {
		$logs_dir = $this->uploads_dir . '/wc-logs';
		mkdir( $logs_dir );
		Platasokin_Test_Env::$writable_overrides[ $logs_dir ] = false;

		platasokin_gateway_log_warning( 'Sokin Pay: something happened' );

		$this->assertSame( array(), Platasokin_Test_Env::$logger->warnings );
		$this->assertStringContainsString( 'Sokin Pay WARNING: Sokin Pay: something happened', $this->errorLogContents() );
	}

	public function test_falls_back_when_uploads_basedir_is_not_writable() {
		$this->forceFallback();

		platasokin_gateway_log_warning( 'Sokin Pay: something happened' );

		$this->assertSame( array(), Platasokin_Test_Env::$logger->warnings );
		$this->assertStringContainsString( 'Sokin Pay WARNING: Sokin Pay: something happened', $this->errorLogContents() );
	}

	public function test_falls_back_when_uploads_basedir_does_not_exist() {
		Platasokin_Test_Env::$uploads_basedir = $this->uploads_dir . '/missing';

		platasokin_gateway_log_warning( 'Sokin Pay: something happened' );

		$this->assertSame( array(), Platasokin_Test_Env::$logger->warnings );
		$this->assertStringContainsString( 'Sokin Pay WARNING', $this->errorLogContents() );
	}

	public function test_falls_back_when_uploads_basedir_is_empty() {
		Platasokin_Test_Env::$uploads_basedir = '';

		platasokin_gateway_log_warning( 'Sokin Pay: something happened' );

		$this->assertSame( array(), Platasokin_Test_Env::$logger->warnings );
		$this->assertStringContainsString( 'Sokin Pay WARNING', $this->errorLogContents() );
	}

	/*
	 * Logger path: context is passed through untouched, with source filled in.
	 */

	public function test_logger_receives_the_full_context() {
		platasokin_gateway_log_warning(
			'Sokin Pay: payment gateway not found',
			array(
				'source'             => 'platasokin-gateway',
				'payment_gateway_id' => 'sokinpay_gateway',
			)
		);

		$context = Platasokin_Test_Env::$logger->warnings[0]['context'];
		$this->assertSame( 'platasokin-gateway', $context['source'] );
		$this->assertSame( 'sokinpay_gateway', $context['payment_gateway_id'] );
	}

	public function test_logger_context_gets_a_default_source() {
		platasokin_gateway_log_warning( 'Sokin Pay: something happened', array( 'order_id' => 4242 ) );

		$context = Platasokin_Test_Env::$logger->warnings[0]['context'];
		$this->assertSame( 'platasokin-gateway', $context['source'] );
		$this->assertSame( 4242, $context['order_id'] );
	}

	/*
	 * Fallback path: only allowlisted keys survive.
	 */

	public function test_fallback_keeps_every_allowlisted_key() {
		$this->forceFallback();

		platasokin_gateway_log_warning(
			'Sokin Pay: something happened',
			array(
				'source'          => 'platasokin-gateway',
				'order_id'        => 4242,
				'current_user_id' => 9,
				'sokin_order_id'  => 'sokin-order-9',
				'get_order_id'    => 'sokin-order-9',
			)
		);

		$this->assertSame(
			array(
				'src'             => 'platasokin-gateway',
				'order_id'        => 4242,
				'current_user_id' => 9,
				'sokin_order_id'  => 'sokin-order-9',
				'get_order_id'    => 'sokin-order-9',
			),
			$this->loggedContext()
		);
	}

	/**
	 * GWEM-1301 renamed the allowlisted key, so the new spelling has to survive.
	 */
	public function test_fallback_keeps_the_sokin_order_id_key() {
		$this->forceFallback();

		platasokin_gateway_log_warning(
			'Sokin Pay: Sokin order id empty',
			array( 'sokin_order_id' => 'sokin-order-9' )
		);

		$this->assertSame( 'sokin-order-9', $this->loggedContext()['sokin_order_id'] );
	}

	/**
	 * The pre-GWEM-1301 spelling is no longer allowlisted and must be dropped.
	 */
	public function test_fallback_drops_the_legacy_stored_order_id_key() {
		$this->forceFallback();

		platasokin_gateway_log_warning(
			'Sokin Pay: something happened',
			array( 'stored_order_id' => 'sokin-order-9' )
		);

		$this->assertSame( array( 'src' => 'platasokin-gateway' ), $this->loggedContext() );
	}

	/**
	 * @dataProvider provide_non_allowlisted_context_keys
	 *
	 * @param string $key   Context key.
	 * @param mixed  $value Context value.
	 */
	public function test_fallback_drops_non_allowlisted_keys( $key, $value ) {
		$this->forceFallback();

		platasokin_gateway_log_warning( 'Sokin Pay: something happened', array( $key => $value ) );

		$context = $this->loggedContext();
		$this->assertArrayNotHasKey( $key, $context );
		$this->assertStringNotContainsString( (string) $value, $this->errorLogContents() );
	}

	/**
	 * @return array<string, array<int, mixed>>
	 */
	public function provide_non_allowlisted_context_keys() {
		return array(
			'order customer id'  => array( 'order_customer_id', 'customer-7' ),
			'payment gateway id' => array( 'payment_gateway_id', 'sokinpay_gateway' ),
			'api key'            => array( 'x-api-key', 'super-secret-key' ),
			'billing email'      => array( 'email', 'shopper@example.test' ),
		);
	}

	public function test_fallback_defaults_the_source_when_none_is_given() {
		$this->forceFallback();

		platasokin_gateway_log_warning( 'Sokin Pay: something happened' );

		$this->assertSame( array( 'src' => 'platasokin-gateway' ), $this->loggedContext() );
	}

	public function test_fallback_uses_a_custom_source_when_given() {
		$this->forceFallback();

		platasokin_gateway_log_warning( 'Sokin Pay: something happened', array( 'source' => 'platasokin-refunds' ) );

		$this->assertSame( 'platasokin-refunds', $this->loggedContext()['src'] );
	}
}
