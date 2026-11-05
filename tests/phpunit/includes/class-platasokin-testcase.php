<?php
/**
 * Shared base class for the gateway unit tests.
 *
 * @package Platasokin
 */

use PHPUnit\Framework\TestCase;

/**
 * Resets the doubles between tests and offers fixture builders.
 */
abstract class Platasokin_TestCase extends TestCase {

	const ORDER_ID = 4242;

	/**
	 * Temporary directory standing in for the uploads basedir.
	 *
	 * @var string
	 */
	protected $uploads_dir;

	protected function setUp(): void {
		parent::setUp();

		$this->uploads_dir = sys_get_temp_dir() . '/platasokin-uploads-' . uniqid( '', true );
		mkdir( $this->uploads_dir, 0777, true );

		Platasokin_Test_Env::reset();
		Platasokin_Test_Env::$uploads_basedir = $this->uploads_dir;

		$_GET = array();
	}

	protected function tearDown(): void {
		$_GET = array();

		$this->deleteTree( $this->uploads_dir );

		parent::tearDown();
	}

	/**
	 * Remove a directory and everything under it.
	 *
	 * @param string $path Directory path.
	 */
	private function deleteTree( $path ) {
		if ( ! is_dir( $path ) ) {
			return;
		}
		foreach ( array_diff( scandir( $path ), array( '.', '..' ) ) as $entry ) {
			$child = $path . '/' . $entry;
			if ( is_dir( $child ) ) {
				$this->deleteTree( $child );
			} else {
				unlink( $child );
			}
		}
		rmdir( $path );
	}

	/**
	 * Build an order, register it with wc_get_order(), and return it.
	 *
	 * @param array<string, mixed> $overrides Property overrides.
	 * @return Platasokin_Test_Order
	 */
	protected function makeOrder( array $overrides = array() ) {
		$order       = new Platasokin_Test_Order( self::ORDER_ID );
		$order->meta = array( 'orderId' => 'sokin-order-1' );

		foreach ( $overrides as $property => $value ) {
			$order->$property = $value;
		}

		Platasokin_Test_Env::$orders[ $order->id ] = $order;

		return $order;
	}

	/**
	 * Register the Sokin gateway in the WooCommerce gateway registry double.
	 *
	 * @return Platasokin_Test_Gateway
	 */
	protected function registerGateway() {
		$gateway = new Platasokin_Test_Gateway();

		WC_Payment_Gateways::$registered = array( 'sokinpay_gateway' => $gateway );

		return $gateway;
	}

	/**
	 * Queue a JSON body for the next wp_remote_get() call.
	 *
	 * @param mixed $payload Value to JSON encode.
	 */
	protected function queueJsonResponse( $payload ) {
		$this->queueRawResponse( json_encode( $payload ) );
	}

	/**
	 * Queue a raw (possibly malformed) body for the next wp_remote_get() call.
	 *
	 * @param string $body Response body.
	 */
	protected function queueRawResponse( $body ) {
		Platasokin_Test_Env::$remote_get_responses[] = array(
			'response' => array( 'code' => 200 ),
			'body'     => $body,
		);
	}

	/**
	 * Queue a transport failure for the next wp_remote_get() call.
	 */
	protected function queueWpError() {
		Platasokin_Test_Env::$remote_get_responses[] = new WP_Error( 'http_request_failed', 'Connection timed out' );
	}

	/**
	 * Shape of a successful Sokin order lookup.
	 *
	 * @param string $order_status   Value for data.order.orderStatus.
	 * @param string $payment_status Value for data.order.payments[0].status.
	 * @return array<string, mixed>
	 */
	protected function orderLookupPayload( $order_status, $payment_status ) {
		return array(
			'data' => array(
				'order' => array(
					'orderStatus' => $order_status,
					'payments'    => array(
						array(
							'paymentId' => 'pay-1',
							'status'    => $payment_status,
						),
					),
				),
			),
		);
	}

	/**
	 * Run the thank-you handler, swallowing the redirect that stands in for exit.
	 *
	 * @param int|null $order_id Order ID, defaulting to the fixture order.
	 * @return string|null Redirect target, or null when the handler returned normally.
	 */
	protected function runHandler( $order_id = null ) {
		$order_id = null === $order_id ? self::ORDER_ID : $order_id;

		try {
			platasokin_thankyou_handler( $order_id );
		} catch ( Platasokin_Test_Redirect_Exception $e ) {
			return $e->location;
		}

		return null;
	}

	/**
	 * Assert the order was left exactly as the gateway found it.
	 *
	 * @param Platasokin_Test_Order $order Order under test.
	 */
	protected function assertOrderUntouched( Platasokin_Test_Order $order ) {
		$this->assertSame( array(), $order->status_transitions, 'Expected no status transition.' );
		$this->assertSame( 0, $order->save_calls, 'Expected the order not to be saved.' );
		$this->assertSame( 0, $order->payment_complete_calls, 'Expected payment_complete() not to be called.' );
		$this->assertSame( array(), Platasokin_Test_Env::$redirects, 'Expected no redirect.' );
	}
}
