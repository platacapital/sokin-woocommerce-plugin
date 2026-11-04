<?php
/**
 * Tests for platasokin_thankyou_handler(), the function reworked by GWEM-1301 so
 * that a refused payment (Alipay included) can no longer land on "order received".
 *
 * @package Platasokin
 */

/**
 * @covers ::platasokin_thankyou_handler
 */
final class ThankyouHandlerTest extends Platasokin_TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->registerGateway();
	}

	/*
	 * Guard clauses.
	 */

	public function test_unknown_order_is_ignored() {
		$this->runHandler( 999999 );

		$this->assertSame( array(), Platasokin_Test_Env::$remote_get_calls );
		$this->assertSame( array(), Platasokin_Test_Env::$notices );
		$this->assertSame( array(), Platasokin_Test_Env::$logger->warnings );
	}

	public function test_order_paid_with_another_gateway_is_ignored() {
		$order = $this->makeOrder( array( 'payment_method' => 'stripe' ) );

		$this->runHandler();

		$this->assertSame( array(), Platasokin_Test_Env::$remote_get_calls );
		$this->assertOrderUntouched( $order );
	}

	public function test_logged_in_user_viewing_someone_elses_order_is_blocked() {
		$order                                = $this->makeOrder( array( 'customer_id' => 7 ) );
		Platasokin_Test_Env::$current_user_id = 9;

		$this->runHandler();

		$warnings = Platasokin_Test_Env::$logger->warnings;
		$this->assertCount( 1, $warnings );
		$this->assertSame( 'Sokin Pay: Unauthorized order access attempt', $warnings[0]['message'] );
		$this->assertSame( self::ORDER_ID, $warnings[0]['context']['order_id'] );
		$this->assertSame( 9, $warnings[0]['context']['current_user_id'] );
		$this->assertSame( 7, $warnings[0]['context']['order_customer_id'] );

		$this->assertSame( array(), Platasokin_Test_Env::$remote_get_calls );
		$this->assertOrderUntouched( $order );
	}

	public function test_guest_request_skips_the_ownership_check() {
		$order                                = $this->makeOrder( array( 'customer_id' => 7 ) );
		Platasokin_Test_Env::$current_user_id = 0;
		$this->queueJsonResponse( $this->orderLookupPayload( 'PROCESSED', 'processed' ) );

		$this->runHandler();

		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( 1, $order->payment_complete_calls );
	}

	public function test_owner_passes_the_ownership_check() {
		$order                                = $this->makeOrder( array( 'customer_id' => 7 ) );
		Platasokin_Test_Env::$current_user_id = 7;
		$this->queueJsonResponse( $this->orderLookupPayload( 'PROCESSED', 'processed' ) );

		$this->runHandler();

		$this->assertSame( array(), Platasokin_Test_Env::$logger->warnings );
		$this->assertSame( 'processing', $order->get_status() );
	}

	/*
	 * Customer abandoned the hosted payment page.
	 */

	public function test_return_status_sets_order_pending_and_redirects_without_calling_api() {
		$order          = $this->makeOrder();
		$_GET['status'] = 'return';

		$redirect = $this->runHandler();

		$this->assertSame( $order->get_checkout_payment_url(), $redirect );
		$this->assertSame(
			array(
				array(
					'status' => 'pending',
					'note'   => 'Customer returned from Sokin without paying.',
				),
			),
			$order->status_transitions
		);
		$this->assertSame( 1, $order->save_calls );
		$this->assertSame( 0, $order->payment_complete_calls );
		$this->assertSame(
			array(
				array(
					'message' => 'You cancelled the payment. Please try again.',
					'type'    => 'notice',
				),
			),
			Platasokin_Test_Env::$notices
		);
		$this->assertSame( array(), Platasokin_Test_Env::$remote_get_calls );
	}

	/*
	 * Missing Sokin order ID. GWEM-1301 replaced the old $_GET['orderId'] check
	 * with a meta lookup plus this warning.
	 */

	public function test_missing_order_id_meta_logs_warning_and_skips_api_call() {
		$order = $this->makeOrder( array( 'meta' => array() ) );

		$this->runHandler();

		$warnings = Platasokin_Test_Env::$logger->warnings;
		$this->assertCount( 1, $warnings );
		$this->assertSame( 'Sokin Pay: Sokin order id empty', $warnings[0]['message'] );
		$this->assertSame( self::ORDER_ID, $warnings[0]['context']['order_id'] );
		$this->assertSame( 'platasokin-gateway', $warnings[0]['context']['source'] );

		$this->assertSame( array(), Platasokin_Test_Env::$remote_get_calls );
		$this->assertOrderUntouched( $order );
	}

	/**
	 * @dataProvider provide_falsy_order_id_meta
	 *
	 * @param mixed $meta_value Stored orderId meta value.
	 */
	public function test_falsy_order_id_meta_is_treated_as_missing( $meta_value ) {
		$order = $this->makeOrder( array( 'meta' => array( 'orderId' => $meta_value ) ) );

		$this->runHandler();

		$this->assertSame( 'Sokin Pay: Sokin order id empty', Platasokin_Test_Env::$logger->warnings[0]['message'] );
		$this->assertSame( array(), Platasokin_Test_Env::$remote_get_calls );
		$this->assertOrderUntouched( $order );
	}

	/**
	 * The handler gates on empty(), so these all short-circuit. The '0' and 0 cases
	 * mean a Sokin order ID of zero would never be looked up.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function provide_falsy_order_id_meta() {
		return array(
			'empty string'  => array( '' ),
			'null'          => array( null ),
			'string zero'   => array( '0' ),
			'integer zero'  => array( 0 ),
			'boolean false' => array( false ),
			'empty array'   => array( array() ),
		);
	}

	/*
	 * Gateway missing from the registry. Also added by GWEM-1301.
	 */

	public function test_unregistered_gateway_logs_warning_and_skips_api_call() {
		$order                           = $this->makeOrder();
		WC_Payment_Gateways::$registered = array();

		$this->runHandler();

		$warnings = Platasokin_Test_Env::$logger->warnings;
		$this->assertCount( 1, $warnings );
		$this->assertSame( 'Sokin Pay: payment gateway not found', $warnings[0]['message'] );
		$this->assertSame( 'sokinpay_gateway', $warnings[0]['context']['payment_gateway_id'] );

		$this->assertSame( array(), Platasokin_Test_Env::$remote_get_calls );
		$this->assertOrderUntouched( $order );
	}

	public function test_a_different_gateway_in_the_registry_does_not_satisfy_the_lookup() {
		$order                           = $this->makeOrder();
		WC_Payment_Gateways::$registered = array( 'stripe' => new Platasokin_Test_Gateway() );

		$this->runHandler();

		$this->assertSame( 'Sokin Pay: payment gateway not found', Platasokin_Test_Env::$logger->warnings[0]['message'] );
		$this->assertOrderUntouched( $order );
	}

	/*
	 * Outbound request construction.
	 */

	public function test_lookup_url_and_headers_come_from_order_meta_and_gateway_settings() {
		$this->makeOrder( array( 'meta' => array( 'orderId' => 'sokin-order-9' ) ) );
		$this->queueJsonResponse( $this->orderLookupPayload( 'PROCESSED', 'processed' ) );

		$this->runHandler();

		$this->assertCount( 1, Platasokin_Test_Env::$remote_get_calls );
		$call = Platasokin_Test_Env::$remote_get_calls[0];
		$this->assertSame( 'https://api.sokin.test/v1/orders/sokin-order-9', $call['url'] );
		$this->assertSame( 'test-x-api-key', $call['args']['headers']['x-api-key'] );
		$this->assertSame( 'application/json', $call['args']['headers']['Content-Type'] );
		$this->assertSame( 30, $call['args']['timeout'] );
	}

	/**
	 * GWEM-1301 dropped the "stored meta must equal ?orderId" comparison, so a
	 * mismatched query parameter no longer aborts the handler and never reaches
	 * the Sokin API.
	 */
	public function test_order_id_query_parameter_is_ignored() {
		$order           = $this->makeOrder( array( 'meta' => array( 'orderId' => 'sokin-order-9' ) ) );
		$_GET['orderId'] = 'attacker-supplied-id';
		$this->queueJsonResponse( $this->orderLookupPayload( 'PROCESSED', 'processed' ) );

		$this->runHandler();

		$this->assertSame(
			'https://api.sokin.test/v1/orders/sokin-order-9',
			Platasokin_Test_Env::$remote_get_calls[0]['url']
		);
		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( 1, $order->payment_complete_calls );
	}

	/*
	 * Transport and payload failures.
	 */

	public function test_transport_error_leaves_order_untouched() {
		$order = $this->makeOrder();
		$this->queueWpError();

		$this->runHandler();

		$this->assertCount( 1, Platasokin_Test_Env::$remote_get_calls );
		$this->assertOrderUntouched( $order );
	}

	/**
	 * @dataProvider provide_unusable_response_bodies
	 *
	 * @param string $body Raw response body.
	 */
	public function test_unusable_response_body_leaves_order_untouched( $body ) {
		$order = $this->makeOrder();
		$this->queueRawResponse( $body );

		$this->runHandler();

		$this->assertOrderUntouched( $order );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public function provide_unusable_response_bodies() {
		return array(
			'empty body'       => array( '' ),
			'truncated json'   => array( '{"data":{"order":' ),
			'html error page'  => array( '<html><body>502 Bad Gateway</body></html>' ),
			'json null'        => array( 'null' ),
			'json scalar'      => array( '"unavailable"' ),
			'json array'       => array( '[]' ),
		);
	}

	/**
	 * @dataProvider provide_incomplete_lookup_payloads
	 *
	 * @param array<string, mixed> $payload Decoded Sokin response.
	 */
	public function test_incomplete_lookup_payload_leaves_order_untouched( array $payload ) {
		$order = $this->makeOrder();
		$this->queueJsonResponse( $payload );

		$this->runHandler();

		$this->assertOrderUntouched( $order );
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function provide_incomplete_lookup_payloads() {
		return array(
			'no data key'              => array( array( 'success' => true ) ),
			'no order key'             => array( array( 'data' => array() ) ),
			'no payments key'          => array( array( 'data' => array( 'order' => array( 'orderStatus' => 'PROCESSED' ) ) ) ),
			'payments empty'           => array( array( 'data' => array( 'order' => array( 'orderStatus' => 'PROCESSED', 'payments' => array() ) ) ) ),
			'payments not an array'    => array( array( 'data' => array( 'order' => array( 'orderStatus' => 'PROCESSED', 'payments' => 'unavailable' ) ) ) ),
			'payments null'            => array( array( 'data' => array( 'order' => array( 'orderStatus' => 'PROCESSED', 'payments' => null ) ) ) ),
			'first payment has no status' => array( array( 'data' => array( 'order' => array( 'orderStatus' => 'PROCESSED', 'payments' => array( array( 'paymentId' => 'pay-1' ) ) ) ) ) ),
		);
	}

	/*
	 * Refused payments. This is the GWEM-1301 fix: 'failed' joined 'declined' in the
	 * refusal list, and refusal is now checked before the order status.
	 */

	public function test_declined_payment_fails_the_order_and_redirects_to_pay_again() {
		$order = $this->makeOrder();
		$this->queueJsonResponse( $this->orderLookupPayload( 'PROCESSED', 'declined' ) );

		$redirect = $this->runHandler();

		$this->assertSame( $order->get_checkout_payment_url(), $redirect );
		$this->assertSame(
			array(
				array(
					'status' => 'failed',
					'note'   => 'Payment declined by Sokin.',
				),
			),
			$order->status_transitions
		);
		$this->assertSame( 1, $order->save_calls );
		$this->assertSame( 0, $order->payment_complete_calls );
		$this->assertSame(
			array(
				array(
					'message' => 'Your payment was declined. Please try again or choose a different payment method.',
					'type'    => 'error',
				),
			),
			Platasokin_Test_Env::$notices
		);
	}

	/**
	 * The Alipay refusal reported in GWEM-1301 comes back as "failed", which the
	 * previous implementation treated as a success.
	 */
	public function test_failed_payment_status_fails_the_order() {
		$order = $this->makeOrder();
		$this->queueJsonResponse( $this->orderLookupPayload( 'PROCESSED', 'failed' ) );

		$redirect = $this->runHandler();

		$this->assertSame( 'failed', $order->get_status() );
		$this->assertSame( 0, $order->payment_complete_calls );
		$this->assertSame( $order->get_checkout_payment_url(), $redirect );
	}

	/**
	 * @dataProvider provide_refused_payment_statuses
	 *
	 * @param string $payment_status Status as returned by Sokin.
	 */
	public function test_refused_payment_statuses_are_matched_case_insensitively( $payment_status ) {
		$order = $this->makeOrder();
		$this->queueJsonResponse( $this->orderLookupPayload( 'PROCESSED', $payment_status ) );

		$this->runHandler();

		$this->assertSame( 'failed', $order->get_status() );
		$this->assertSame( 0, $order->payment_complete_calls );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public function provide_refused_payment_statuses() {
		return array(
			'declined lower' => array( 'declined' ),
			'declined upper' => array( 'DECLINED' ),
			'declined title' => array( 'Declined' ),
			'failed lower'   => array( 'failed' ),
			'failed upper'   => array( 'FAILED' ),
			'failed title'   => array( 'Failed' ),
			'failed mixed'   => array( 'fAiLeD' ),
		);
	}

	/**
	 * The regression GWEM-1301 describes: the wrapper order reports a terminal
	 * status while the payment itself was refused.
	 *
	 * @dataProvider provide_completing_order_statuses
	 *
	 * @param string $order_status Sokin order status.
	 */
	public function test_completing_order_status_does_not_override_a_refused_payment( $order_status ) {
		$order = $this->makeOrder();
		$this->queueJsonResponse( $this->orderLookupPayload( $order_status, 'failed' ) );

		$this->runHandler();

		$this->assertSame( 'failed', $order->get_status() );
		$this->assertSame( 0, $order->payment_complete_calls );
		$this->assertSame( 'error', Platasokin_Test_Env::$notices[0]['type'] );
	}

	/*
	 * Successful payments.
	 */

	/**
	 * @dataProvider provide_completing_order_statuses
	 *
	 * @param string $order_status Sokin order status.
	 */
	public function test_completing_order_status_moves_order_to_processing( $order_status ) {
		$order = $this->makeOrder();
		$this->queueJsonResponse( $this->orderLookupPayload( $order_status, 'processed' ) );

		$this->runHandler();

		$this->assertSame(
			array(
				array(
					'status' => 'processing',
					'note'   => '',
				),
			),
			$order->status_transitions
		);
		$this->assertSame( 1, $order->save_calls );
		$this->assertSame( 1, $order->payment_complete_calls );
		$this->assertSame( array(), Platasokin_Test_Env::$redirects );
		$this->assertSame( array(), Platasokin_Test_Env::$notices );
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public function provide_completing_order_statuses() {
		return array(
			'processed'   => array( 'PROCESSED' ),
			'in progress' => array( 'IN-PROGRESS' ),
			'pending'     => array( 'PENDING' ),
		);
	}

	/**
	 * Only 'declined' and 'failed' block completion, so anything else Sokin sends
	 * back is treated as good.
	 */
	public function test_unrecognised_payment_status_still_completes_the_order() {
		$order = $this->makeOrder();
		$this->queueJsonResponse( $this->orderLookupPayload( 'PROCESSED', 'authorised' ) );

		$this->runHandler();

		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( 1, $order->payment_complete_calls );
	}

	/**
	 * @dataProvider provide_non_completing_order_statuses
	 *
	 * @param string $order_status Sokin order status.
	 */
	public function test_other_order_statuses_leave_the_order_untouched( $order_status ) {
		$order = $this->makeOrder();
		$this->queueJsonResponse( $this->orderLookupPayload( $order_status, 'processed' ) );

		$this->runHandler();

		$this->assertOrderUntouched( $order );
	}

	/**
	 * Unlike the payment status, the order status is compared without normalising
	 * case, so the lower-case spellings below fall through to no-op.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function provide_non_completing_order_statuses() {
		return array(
			'cancelled'             => array( 'CANCELLED' ),
			'rejected'              => array( 'REJECTED' ),
			'expired'               => array( 'EXPIRED' ),
			'empty'                 => array( '' ),
			'processed lower case'  => array( 'processed' ),
			'in progress lower'     => array( 'in-progress' ),
			'in progress underscore' => array( 'IN_PROGRESS' ),
		);
	}

	/**
	 * data.order.orderStatus is read before it is needed, so a payload that omits
	 * it raises a PHP diagnostic even though the handler then no-ops.
	 */
	public function test_payload_without_order_status_raises_a_php_diagnostic() {
		$order = $this->makeOrder();
		$this->queueJsonResponse(
			array(
				'data' => array(
					'order' => array(
						'payments' => array( array( 'status' => 'authorised' ) ),
					),
				),
			)
		);

		$diagnostics = array();
		set_error_handler(
			function ( $errno, $errstr ) use ( &$diagnostics ) {
				$diagnostics[] = $errstr;
				return true;
			},
			E_WARNING | E_NOTICE
		);

		try {
			$this->runHandler();
		} finally {
			restore_error_handler();
		}

		$this->assertNotEmpty( $diagnostics, 'Expected an undefined-key diagnostic for orderStatus.' );
		$this->assertStringContainsString( 'orderStatus', $diagnostics[0] );
		$this->assertOrderUntouched( $order );
	}
}
