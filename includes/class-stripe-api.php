<?php
/**
 * Stripe API Handler
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Stripe_API {

	private $secret_key;
	private $api_url = 'https://api.stripe.com/v1';

	public function __construct() {
		$keys = OnRoute_Courier_Booking_Payment_Settings::get_stripe_keys();
		$this->secret_key = $keys['secret'];
	}

	/**
	 * Send request to Stripe
	 */
	private function request( $endpoint, $method = 'POST', $args = array() ) {
		if ( empty( $this->secret_key ) ) {
			return new WP_Error( 'stripe_error', 'Stripe API key is missing' );
		}

		$url = $this->api_url . $endpoint;
		$headers = array(
			'Authorization' => 'Bearer ' . $this->secret_key,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		);

		$request_args = array(
			'method'    => $method,
			'headers'   => $headers,
			'body'      => $args,
			'timeout'   => 45,
			'sslverify' => true,
		);

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'stripe_api_error', $data['error']['message'] );
		}

		return $data;
	}

	/**
	 * Create Checkout Session
	 */
	public function create_checkout_session( $booking, $amount_to_pay, $payment_mode, $success_url, $cancel_url ) {
		// Calculate amount in cents/pence
		$amount_cents = round( $amount_to_pay * 100 );

		$args = array(
			'payment_method_types' => array( 'card' ),
			'line_items' => array(
				array(
					'price_data' => array(
						'currency' => 'gbp',
						'product_data' => array(
							'name' => 'Courier Booking: ' . $booking->booking_reference,
							'description' => $payment_mode === 'deposit' ? 'Deposit Payment' : 'Full Payment',
						),
						'unit_amount' => $amount_cents,
					),
					'quantity' => 1,
				),
			),
			'mode' => 'payment',
			'success_url' => $success_url,
			'cancel_url' => $cancel_url,
			'client_reference_id' => $booking->id,
			'metadata' => array(
				'booking_id' => $booking->id,
				'booking_reference' => $booking->booking_reference,
				'payment_mode' => $payment_mode,
			),
			'customer_email' => $booking->customer_email,
		);

		return $this->request( '/checkout/sessions', 'POST', http_build_query( $args ) );
	}

	/**
	 * Retrieve Checkout Session
	 */
	public function retrieve_session( $session_id ) {
		return $this->request( '/checkout/sessions/' . $session_id, 'GET' );
	}
}
