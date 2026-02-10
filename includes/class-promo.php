<?php
/**
 * Promo code management class
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Promo {

	/**
	 * Create a promo code
	 */
	public function create( $code, $type, $value, $expiry_date = null, $max_uses = null ) {
		global $wpdb;

		$code = strtoupper( sanitize_text_field( $code ) );

		// Check if code already exists
		$existing = $this->get_by_code( $code );
		if ( $existing ) {
			return new WP_Error( 'code_exists', 'Promo code already exists' );
		}

		$result = $wpdb->insert(
			OnRoute_Courier_Booking_Database::get_promos_table(),
			array(
				'code' => $code,
				'type' => $type,
				'value' => $value,
				'expiry_date' => $expiry_date,
				'max_uses' => $max_uses,
				'active' => 1,
			),
			array( '%s', '%s', '%f', '%s', '%d', '%d' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get promo by code
	 */
	public function get_by_code( $code ) {
		global $wpdb;
		$code = strtoupper( sanitize_text_field( $code ) );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . OnRoute_Courier_Booking_Database::get_promos_table() . " WHERE code = %s",
				$code
			)
		);
	}

	/**
	 * Validate and calculate discount
	 */
	public function validate_and_calculate( $code, $base_price ) {
		$promo = $this->get_by_code( $code );

		if ( ! $promo ) {
			return new WP_Error( 'invalid_code', 'Invalid promo code' );
		}

		if ( ! $promo->active ) {
			return new WP_Error( 'inactive_code', 'Promo code is inactive' );
		}

		// Check expiry date
		if ( $promo->expiry_date && strtotime( $promo->expiry_date ) < current_time( 'timestamp' ) ) {
			return new WP_Error( 'expired_code', 'Promo code has expired' );
		}

		// Check max uses
		if ( $promo->max_uses && $promo->times_used >= $promo->max_uses ) {
			return new WP_Error( 'max_uses_reached', 'Promo code max uses reached' );
		}

		// Calculate discount
		$discount_amount = 0;
		if ( 'fixed' === $promo->type ) {
			$discount_amount = $promo->value;
		} elseif ( 'percentage' === $promo->type ) {
			$discount_amount = $base_price * ( $promo->value / 100 );
		}

		return array(
			'amount' => round( $discount_amount, 2 ),
			'type' => $promo->type,
			'value' => $promo->value,
		);
	}

	/**
	 * Increment usage count
	 */
	public function increment_usage( $code ) {
		global $wpdb;
		$promo = $this->get_by_code( $code );
		if ( $promo ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE " . OnRoute_Courier_Booking_Database::get_promos_table() . " SET times_used = times_used + 1 WHERE code = %s",
					strtoupper( $code )
				)
			);
		}
	}

	/**
	 * Get all promo codes
	 */
	public function get_all( $limit = 50, $offset = 0 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM " . OnRoute_Courier_Booking_Database::get_promos_table() . " ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Delete promo code
	 */
	public function delete( $promo_id ) {
		global $wpdb;
		return $wpdb->delete(
			OnRoute_Courier_Booking_Database::get_promos_table(),
			array( 'id' => $promo_id )
		);
	}
}
