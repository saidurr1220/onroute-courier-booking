<?php
/**
 * Quote Summary Handler - Step 2: Vehicle Selection & Pricing
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Quote_Summary {

	/**
	 * Get vehicle options with pricing (Deprecated - Removed)
	 */
	public static function get_vehicle_options() {
		// This method is deprecated and should not be used.
		// Use OnRoute_Courier_Booking_Pricing->get_vehicles() instead.
		return array();
	}

	/**
	 * Get service types
	 */
	public static function get_service_types() {
		return array(
			'same_day' => array(
				'name' => 'Same Day',
				'description' => 'Our most cost-effective service, by we\'ll collect by 15:00 and delivery by 17:15',
			),
			'timed' => array(
				'name' => 'Timed',
				'description' => 'The most popular option, we\'ll collect by 09:00 or delivery time of your choice',
			),
			'dedicated' => array(
				'name' => 'Dedicated',
				'description' => 'Our most secure service, we\'ll collect by 09:00 or delivery time of your choice with no other items on board',
			),
		);
	}

	/**
	 * Render quote summary HTML
	 */
	public static function render_quote_summary( $session_data = array() ) {
		if ( empty( $session_data ) ) {
			return '';
		}

		$quote_summary = '<div class="ocb-quote-summary-wrapper">';
		$quote_summary .= '<div class="ocb-quote-header">';
		$quote_summary .= '<p><strong>Collection postcode:</strong> ' . esc_html( $session_data['step1_collection_postcode'] ?? '' ) . '</p>';
		$quote_summary .= '<p><strong>Delivery postcode:</strong> ' . esc_html( $session_data['step1_delivery_postcode'] ?? '' ) . '</p>';
		$quote_summary .= '</div>';
		$quote_summary .= '</div>';

		return $quote_summary;
	}

	/**
	 * Render vehicle options for selection
	 */
	public static function render_vehicle_options( $service_type = 'same_day' ) {
		OnRoute_Courier_Booking_Session::init();
		$session_data = OnRoute_Courier_Booking_Session::get_step( 1 );
		$distance = isset( $session_data['step1_distance_miles'] ) ? floatval( $session_data['step1_distance_miles'] ) : 10;
		$collection_time = isset($session_data['step3_collection_time']) ? $session_data['step3_collection_time'] : '09:00'; 

		// Get vehicles from database instead of static list
		$pricing_calculator = new OnRoute_Courier_Booking_Pricing();
		$vehicles = $pricing_calculator->get_vehicles();
		
		if ( empty( $vehicles ) ) {
			return '<p>No vehicles available. Please contact support.</p>';
		}
		
		$html = '<div class="ocb-vehicles-grid">';

		foreach ( $vehicles as $vehicle ) {
			// Skip inactive vehicles
			if ( empty( $vehicle['active'] ) ) {
				continue;
			}

			$vehicle_id = $vehicle['id'];
			$vehicle_name = $vehicle['name'];

			// Calculate dynamic price using the service_id
			$base_price = $pricing_calculator->calculate_price( $distance, $vehicle_id, $collection_time, $service_type );
			
			if ( $base_price <= 0 ) {
				continue; // Skip if price calculation failed
			}

			$totals = $pricing_calculator->calculate_total( $base_price );
			
			// Get vehicle icon (default based on name if needed)
			$icon = '🚐'; // Default
			if ( stripos( $vehicle_name, 'bike' ) !== false || stripos( $vehicle_name, 'motor' ) !== false ) {
				$icon = '🏍️';
			} elseif ( stripos( $vehicle_name, 'small' ) !== false ) {
				$icon = '🚐';
			} elseif ( stripos( $vehicle_name, 'large' ) !== false || stripos( $vehicle_name, 'xl' ) !== false ) {
				$icon = '🚛';
			}

			$html .= '
				<div class="ocb-vehicle-card" data-vehicle="' . esc_attr( $vehicle_id ) . '">
					<div class="ocb-vehicle-icon">' . $icon . '</div>
					<h4>' . esc_html( $vehicle_name ) . '</h4>
					<div class="ocb-vehicle-pricing">
						<p class="ocb-price-base">£' . number_format( $totals['base_price'], 2 ) . '</p>
						<p class="ocb-price-vat">+ £' . number_format( $totals['vat_amount'], 2 ) . ' VAT</p>
						<p class="ocb-price-total">£' . number_format( $totals['total'], 2 ) . ' Total inc. VAT</p>
					</div>
					<button type="button" class="ocb-btn ocb-btn-secondary ocb-select-vehicle" data-vehicle="' . esc_attr( $vehicle_id ) . '">
						Select
					</button>
				</div>
			';
		}

		$html .= '</div>';
		return $html;
	}
}
