<?php
/**
 * Booking management class
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Booking {

	/**
	 * Create a new booking
	 */
	public function create(
		$customer_email,
		$customer_phone,
		$pickup_address,
		$pickup_postcode,
		$delivery_address,
		$delivery_postcode,
		$collection_date,
		$collection_time,
		$delivery_date,
		$delivery_time,
		$vehicle_id,
		$service_id,
		$base_price,
		$vat_amount,
		$discount_amount,
		$total_price,
		$promo_code = ''
	) {
		global $wpdb;

		$booking_reference = $this->generate_reference();

		$result = $wpdb->insert(
			OnRoute_Courier_Booking_Database::get_bookings_table(),
			array(
				'booking_reference' => $booking_reference,
				'customer_email' => $customer_email,
				'customer_phone' => $customer_phone,
				'pickup_address' => $pickup_address,
				'pickup_postcode' => $pickup_postcode,
				'delivery_address' => $delivery_address,
				'delivery_postcode' => $delivery_postcode,
				'collection_date' => $collection_date,
				'collection_time' => $collection_time,
				'delivery_date' => $delivery_date,
				'delivery_time' => $delivery_time,
				'vehicle_id' => $vehicle_id,
				'service_id' => $service_id,
				'base_price' => $base_price,
				'vat_amount' => $vat_amount,
				'discount_amount' => $discount_amount,
				'total_price' => $total_price,
				'promo_code' => $promo_code,
				'status' => 'booked',
				'payment_status' => 'unpaid',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get booking by ID
	 */
	public function get( $booking_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . OnRoute_Courier_Booking_Database::get_bookings_table() . " WHERE id = %d",
				$booking_id
			)
		);
	}

	/**
	 * Get booking by reference
	 */
	public function get_by_reference( $reference ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . OnRoute_Courier_Booking_Database::get_bookings_table() . " WHERE booking_reference = %s",
				$reference
			)
		);
	}

	/**
	 * Update booking
	 */
	public function update( $booking_id, $data ) {
		global $wpdb;
		return $wpdb->update(
			OnRoute_Courier_Booking_Database::get_bookings_table(),
			$data,
			array( 'id' => $booking_id ),
			array_fill( 0, count( $data ), '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update payment status
	 */
	public function update_payment_status( $booking_id, $payment_status, $stripe_payment_id = null ) {
		global $wpdb;
		$data = array( 'payment_status' => $payment_status );
		if ( $stripe_payment_id ) {
			$data['stripe_payment_id'] = $stripe_payment_id;
		}
		return $wpdb->update(
			OnRoute_Courier_Booking_Database::get_bookings_table(),
			$data,
			array( 'id' => $booking_id )
		);
	}

	/**
	 * Get bookings by email
	 */
	public function get_by_email( $email ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . OnRoute_Courier_Booking_Database::get_bookings_table() . " WHERE customer_email = %s ORDER BY created_at DESC",
				$email
			)
		);
	}

	/**
	 * Get all bookings (with pagination and optional filters)
	 */
	public function get_all( $limit = 50, $offset = 0, $status = '', $service_id = '', $exclude_service_id = '', $exclude_agents = false ) {
		global $wpdb;
		// Strict casting for security
		$limit = (int) $limit;
		$offset = (int) $offset;
		
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		
		$where_clauses = array( '1=1' );
		$params = array();
		
		if ( ! empty( $status ) ) {
			$where_clauses[] = 'status = %s';
			$params[] = $status;
		}

		if ( ! empty( $service_id ) ) {
			if ( is_array( $service_id ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $service_id ), '%s' ) );
				$where_clauses[] = "service_id IN ($placeholders)";
				$params = array_merge( $params, $service_id );
			} else {
				$where_clauses[] = 'service_id = %s';
				$params[] = $service_id;
			}
		}

		if ( ! empty( $exclude_service_id ) ) {
			if ( is_array( $exclude_service_id ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $exclude_service_id ), '%s' ) );
				$where_clauses[] = "service_id NOT IN ($placeholders)";
				$params = array_merge( $params, $exclude_service_id );
			} else {
				$where_clauses[] = 'service_id != %s';
				$params[] = $exclude_service_id;
			}
		}

		if ( $exclude_agents ) {
			$where_clauses[] = '(user_id IS NULL OR user_id = 0)';
		}
		
		$sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where_clauses ) . " ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;
		
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Get total bookings count (with optional filters)
	 */
	public function count_all( $status = '', $service_id = '', $exclude_service_id = '', $exclude_agents = false ) {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		
		$where_clauses = array( '1=1' );
		$params = array();
		
		if ( ! empty( $status ) ) {
			$where_clauses[] = 'status = %s';
			$params[] = $status;
		}

		if ( ! empty( $service_id ) ) {
			if ( is_array( $service_id ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $service_id ), '%s' ) );
				$where_clauses[] = "service_id IN ($placeholders)";
				$params = array_merge( $params, $service_id );
			} else {
				$where_clauses[] = 'service_id = %s';
				$params[] = $service_id;
			}
		}

		if ( ! empty( $exclude_service_id ) ) {
			if ( is_array( $exclude_service_id ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $exclude_service_id ), '%s' ) );
				$where_clauses[] = "service_id NOT IN ($placeholders)";
				$params = array_merge( $params, $exclude_service_id );
			} else {
				$where_clauses[] = 'service_id != %s';
				$params[] = $exclude_service_id;
			}
		}

		if ( $exclude_agents ) {
			$where_clauses[] = '(user_id IS NULL OR user_id = 0)';
		}
		
		$sql = "SELECT COUNT(*) FROM $table WHERE " . implode( ' AND ', $where_clauses );
		
		if ( ! empty( $params ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}
		
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Generate unique booking reference
	 */
	private function generate_reference() {
		$prefix = 'OCB';
		$timestamp = time();
		$random = wp_rand( 1000, 9999 );
		return strtoupper( $prefix . '-' . date( 'YmdHis', $timestamp ) . '-' . $random );
	}
}
