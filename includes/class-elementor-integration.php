<?php
/**
 * Elementor Form Integration
 * Saves Elementor Pro form submissions to OnRoute Booking DB
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Elementor_Integration {

	public function __construct() {
		add_action( 'elementor_pro/forms/new_record', array( $this, 'process_elementor_form' ), 10, 2 );
	}

	/**
	 * Process Elementor Form Submission
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function process_elementor_form( $record, $ajax_handler ) {
		$form_settings = $record->get( 'form_settings' );
		$form_name     = isset( $form_settings['form_name'] ) ? $form_settings['form_name'] : '';
		
		// Only process forms with "VIP" in the name or specific ID if needed
		// You can add more specific checks here if you have multiple forms
		if ( stripos( $form_name, 'vip' ) === false && stripos( $form_name, 'courier' ) === false ) {
			return;
		}

		$raw_fields = $record->get( 'fields' );
		$fields     = array();

		// Normalize fields (key => value)
		foreach ( $raw_fields as $id => $field ) {
			$fields[ $id ] = $field['value'];
		}

		// Map Fields (Try to find common names)
		$name    = $this->find_field( $fields, array( 'name', 'full_name', 'your-name', 'customer_name' ) );
		$email   = $this->find_field( $fields, array( 'email', 'your-email', 'contact_email', 'mail' ) );
		$phone   = $this->find_field( $fields, array( 'phone', 'tel', 'mobile', 'cell', 'f_name' ) ); // f_name sometimes used for phone? unlikely but possible
		$message = $this->find_field( $fields, array( 'message', 'msg', 'details', 'delivery_details', 'notes' ) );
		
		// Pickup/Delivery specific (if they exist in the form)
		$pickup   = $this->find_field( $fields, array( 'pickup', 'from', 'collection', 'pickup_postcode' ) );
		$delivery = $this->find_field( $fields, array( 'delivery', 'to', 'destination', 'delivery_postcode' ) );

		if ( empty( $email ) ) {
			return; // Email is required for our system
		}

		// Prepare Data
		global $wpdb;
		$table = $wpdb->prefix . 'ocb_bookings';

		// Generate Reference
		$reference = 'VIP-E-' . strtoupper( substr( md5( uniqid() ), 0, 6 ) );

		// Combine all fields into notes for safety
		$full_details  = "VIP Enquiry via Elementor Form ($form_name)\n";
		$full_details .= "----------------------------------------\n";
		foreach ( $fields as $key => $val ) {
			if ( is_array( $val ) ) {
				$val = implode( ', ', $val );
			}
			$full_details .= ucfirst( $key ) . ": $val\n";
		}

		$data = array(
			'booking_reference' => $reference,
			'customer_name'     => $name ? $name : 'Elementor User',
			'customer_email'    => $email,
			'customer_phone'    => $phone ? $phone : 'Not Provided',
			'pickup_address'    => $pickup ? $pickup : 'See Notes',
			'pickup_postcode'   => 'VIP', // Placeholder
			'delivery_address'  => $delivery ? $delivery : 'See Notes',
			'delivery_postcode' => 'VIP', // Placeholder
			'collection_date'   => current_time( 'Y-m-d' ),
			'collection_time'   => '09:00:00',
			'delivery_date'     => current_time( 'Y-m-d' ),
			'delivery_time'     => '17:00:00',
			'vehicle_id'        => 'vip_white_glove',
			'service_id'        => 'vip_secure',
			'base_price'        => 0.00,
			'vat_amount'        => 0.00,
			'total_price'       => 0.00,
			'notes'             => $full_details,
			'status'            => 'pending',
			'payment_status'    => 'unpaid',
			'created_at'        => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $table, $data );

		if ( $result ) {
			// Optional: Trigger internal notification (if not handled by Elementor actions)
			// Elementor usually handles emails, so we might duplicate emails if we do it here.
			// Ideally, rely on Elementor's native email action for admins.
		}
	}

	/**
	 * Helper to find value from array of possible keys
	 */
	private function find_field( $fields, $possible_keys ) {
		foreach ( $fields as $key => $val ) {
			// Check exact match
			if ( in_array( strtolower( $key ), $possible_keys ) ) {
				return sanitize_text_field( $val );
			}
			// Check partial match in key (e.g. field_email)
			foreach ( $possible_keys as $pkey ) {
				if ( strpos( strtolower( $key ), $pkey ) !== false ) {
					return sanitize_text_field( $val );
				}
			}
		}
		return '';
	}
}

new OnRoute_Elementor_Integration();
