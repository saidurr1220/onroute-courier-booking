<?php
/**
 * Stripe Webhook Handler
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Stripe_Webhook {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );
	}

	public function register_webhook_route() {
		register_rest_route( 'ocb/v1', '/stripe-webhook', array(
			'methods'  => 'POST',
			'callback' => array( $this, 'handle_webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function handle_webhook( $request ) {
		$payload = $request->get_body();
		$sig_header = $request->get_header( 'stripe-signature' );
		$event = null;

		// Note: Signature verification requires the Webhook Signing Secret which we didn't add to settings yet. 
		// For now we will trust the payload but in production you MUST verify signature.
		// We will assume the event object is valid JSON.
		
		$event = json_decode( $payload, true );

		if ( ! $event ) {
			return new WP_Error( 'invalid_json', 'Invalid JSON', array( 'status' => 400 ) );
		}

		if ( $event['type'] === 'checkout.session.completed' ) {
			$session = $event['data']['object'];
			$this->process_payment( $session );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	private function process_payment( $session ) {
		$booking_id = isset( $session['client_reference_id'] ) ? $session['client_reference_id'] : null;
		
		if ( ! $booking_id ) {
			// Try metadata
			$booking_id = isset( $session['metadata']['booking_id'] ) ? $session['metadata']['booking_id'] : null;
		}

		if ( ! $booking_id ) return;

		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );

		if ( ! $booking ) return;

		$amount_paid_cents = $session['amount_total'];
		$amount_paid = $amount_paid_cents / 100;
		$stripe_id = $session['payment_intent'];

		// Update Booking
		$current_paid = floatval( $booking->amount_paid ?? 0 );
		$new_paid = $current_paid + $amount_paid;
		$total = floatval( $booking->total_price );

		// Determine new status
		$payment_status = 'partially-paid';
		if ( $new_paid >= $total - 0.01 ) { // buffer for float precision
			$payment_status = 'paid';
		} elseif ( $new_paid <= 0 ) {
			$payment_status = 'unpaid';
		}

		$wpdb->update(
			$table,
			array(
				'amount_paid' => $new_paid,
				'payment_status' => $payment_status,
				'stripe_payment_id' => $stripe_id,
				'payment_method' => 'stripe',
				'status' => 'confirmed'
			),
			array( 'id' => $booking_id ),
			array( '%f', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Send Confirmation Emails
		if ( $payment_status === 'paid' && class_exists( 'OnRoute_Courier_Booking_Emails' ) ) {
			OnRoute_Courier_Booking_Emails::send_booking_confirmation( $booking_id );
		}
	}
}
