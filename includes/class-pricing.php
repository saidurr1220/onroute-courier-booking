<?php
/**
 * Pricing management class
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Pricing {

	/**
	 * Get VAT rate
	 */
	public function get_vat_rate() {
		// VAT disabled per client request "no vat"
		return 0; // Force 0% VAT
	}

	/**
	 * Get all services
	 */
	public function get_services() {
		return get_option( 'ocb_services', array() );
	}

	/**
	 * Get service by ID
	 */
	public function get_service( $service_id ) {
		$services = $this->get_services();
		
		// Normalize ID
		$service_id = strtolower( trim( $service_id ) );
		
		// Map common aliases to handle potential database vs UI mismatches
		$aliases = array(
			'priority'  => 'timed',
			'timed'     => 'priority',
			'direct'    => 'dedicated',
			'dedicated' => 'direct',
			'standard'  => 'same_day',
			'same_day'  => 'standard',
		);

		// If no services in DB, use hardcoded defaults based on client spec
		if ( empty( $services ) ) {
			if ( $service_id === 'timed' || $service_id === 'priority' ) {
				return array( 'id' => $service_id, 'multiplier' => 1.5, 'active' => 1 );
			}
			if ( $service_id === 'dedicated' || $service_id === 'direct' ) {
				return array( 'id' => $service_id, 'multiplier' => 2.0, 'active' => 1 );
			}
			return array( 'id' => 'same_day', 'multiplier' => 1.0, 'active' => 1 );
		}

		foreach ( $services as $service ) {
			$db_id = strtolower( trim( $service['id'] ) );
			
			if ( $db_id === $service_id && $service['active'] ) {
				return $service;
			}
			
			// Check alias if exact match fails
			if ( isset( $aliases[$service_id] ) && $db_id === $aliases[$service_id] && $service['active'] ) {
				return $service;
			}
		}

		// Final fallback for common IDs even if not in DB
		if ( $service_id === 'timed' || $service_id === 'priority' ) {
			return array( 'id' => $service_id, 'multiplier' => 1.5, 'active' => 1 );
		}
		if ( $service_id === 'dedicated' || $service_id === 'direct' ) {
			return array( 'id' => $service_id, 'multiplier' => 2.0, 'active' => 1 );
		}

		return array( 'id' => 'same_day', 'multiplier' => 1.0, 'active' => 1 );
	}

	/**
	 * Get all vehicles (Hardcoded based on client spec)
	 */
	public function get_vehicles() {
		// Define defaults with new specs
		$defaults = array(
			array(
				'id' => 'small_van',
				'name' => 'Small Van',
				'description' => 'Compact | Up to 400kg',
				'dimensions' => '1 x 1.2 x 1m (LxWxH)',
				'max_weight' => 'Max 400kg',
				'rate_per_mile' => 1.35,
				'admin_fee' => 15.00,
				'min_charge' => 45.00,
				'active' => 1,
			),
			array(
				'id' => 'mwb',
				'name' => 'Medium Van',
				'description' => 'Standard | Up to 800kg',
				'dimensions' => '2 x 1.2 x 1m (LxWxH)',
				'max_weight' => 'Max 800kg',
				'rate_per_mile' => 1.55,
				'admin_fee' => 20.00,
				'min_charge' => 55.00,
				'active' => 1,
			),
			array(
				'id' => 'lwb',
				'name' => 'Large Van',
				'description' => 'Luton | Up to 1,200kg',
				'dimensions' => '3 x 1.2 x 1.7m (LxWxH)',
				'max_weight' => 'Max 1100kg',
				'rate_per_mile' => 1.75,
				'admin_fee' => 25.00,
				'min_charge' => 65.00,
				'active' => 1,
			),
		);

		// Merge with database settings if available
		$stored = get_option( 'ocb_vehicles', array() );
		
		if ( ! empty( $stored ) && is_array( $stored ) ) {
			foreach ( $defaults as $key => $default ) {
				foreach ( $stored as $s ) {
					// Match by ID (preferred) or Name (fallback)
					$match = false;
					if ( isset( $s['id'] ) && $s['id'] === $default['id'] ) {
						$match = true;
					} elseif ( isset( $s['name'] ) && strtolower($s['name']) === strtolower($default['name']) ) {
						$match = true;
					}

					if ( $match ) {
						// Merge pricing fields
						// Logic: Only override default if DB value is > 0. This prevents accidental 0 prices if fields are left empty in settings.
						
						if ( isset( $s['rate_per_mile'] ) && floatval( $s['rate_per_mile'] ) > 0 ) {
							$defaults[$key]['rate_per_mile'] = floatval( $s['rate_per_mile'] );
						}
						
						if ( isset( $s['admin_fee'] ) && floatval( $s['admin_fee'] ) > 0 ) {
							$defaults[$key]['admin_fee'] = floatval( $s['admin_fee'] );
						}
						
						if ( isset( $s['min_charge'] ) && floatval( $s['min_charge'] ) > 0 ) {
							$defaults[$key]['min_charge'] = floatval( $s['min_charge'] );
						}
						
						if ( isset( $s['active'] ) ) $defaults[$key]['active'] = $s['active'];
						
						// Note: We deliberately do NOT merge the Name here to ensure 
						// the new "Small van" / "Large van (SWB)" / "XL van (3.2m)" names persist
						// over any old "MWB"/"LWB" names in the database.
						// Dimensions/Weight are code-controlled and not in DB yet.
					}
				}
			}
		}

		return $defaults;
	}

	/**
	 * Get vehicle by ID
	 */
	public function get_vehicle( $vehicle_id ) {
		$vehicles = $this->get_vehicles();
		foreach ( $vehicles as $vehicle ) {
			if ( $vehicle['id'] === $vehicle_id && $vehicle['active'] ) {
				return $vehicle;
			}
		}
		return null;
	}

	/**
	 * Calculate price based on distance, vehicle, time, and service level
	 * 
	 * PRICING LOGIC (NON-NEGOTIABLE - Per Client Spec):
	 * =========================================================
	 * STEP 1: Detect Day or Night time
	 * STEP 2: Set per-mile rate (if night, multiply by 2)
	 * STEP 3: distance_cost = distance × per-mile rate
	 * STEP 4: chargeable_cost = max(distance_cost, vehicle_min_charge)
	 * STEP 5: final_price = chargeable_cost + admin_fee
	 * 
	 * CRITICAL RULES:
	 * - Admin fee is NEVER multiplied
	 * - Admin fee is ALWAYS added last
	 * - Night rate multiplies ONLY the per-mile rate, not the admin fee
	 * - Business Credit users: Admin fee is WAIVED (set to 0)
	 * 
	 * @param float  $distance_miles     Distance in miles
	 * @param string $vehicle_id         Vehicle ID
	 * @param string $collection_time    HH:mm format (optional)
	 * @param string $service_id         Service ID (optional, default 'same_day')
	 * @param string $delivery_time      HH:mm format (optional)
	 * @param bool   $return_breakdown   Return detailed breakdown
	 * @param bool   $is_business_credit Business credit user (admin fee waived)
	 * @return float|array Price as float, or array with breakdown
	 */
	public function calculate_price( $distance_miles, $vehicle_id, $collection_time = '', $service_id = 'same_day', $delivery_time = '', $return_breakdown = false, $is_business_credit = false ) {
		$vehicle = $this->get_vehicle( $vehicle_id );
		$service = $this->get_service( $service_id );

		if ( ! $vehicle ) {
			return 0;
		}

		// Get rates from vehicle settings (defaulting to simple rates if not set)
		$base_rate_per_mile = isset( $vehicle['rate_per_mile'] ) ? (float) $vehicle['rate_per_mile'] : 1.50;
		$admin_fee          = isset( $vehicle['admin_fee'] ) ? (float) $vehicle['admin_fee'] : 15.00;
		$min_charge         = isset( $vehicle['min_charge'] ) ? (float) $vehicle['min_charge'] : 45.00;

		// Business Credit: Waive admin fees
		if ( $is_business_credit ) {
			$admin_fee = 0;
		}

		// ========================================================================
		// STEP 1: Determine if day or night time
		// ========================================================================
		$night_applied = false;
		$night_start = (int) get_option( 'ocb_night_start', 22 );    // Default 22:00
		$night_end = (int) get_option( 'ocb_night_end', 6 );        // Default 06:00
		$night_multiplier_value = (float) get_option( 'ocb_night_multiplier', 2 ); // Default 2.0x
		$night_apply_mode = get_option( 'ocb_night_apply_mode', 'either' ); // collection_only or either
		$night_enabled = (bool) get_option( 'ocb_night_enabled', 1 );

		if ( $night_enabled ) {
			// Check if collection time is night
			if ( ! empty( $collection_time ) ) {
				$is_collection_night = $this->is_time_in_night_window( $collection_time, $night_start, $night_end );
				if ( $is_collection_night ) {
					$night_applied = true;
				}
			}

			// If collection is NOT night, check delivery (based on apply mode)
			if ( ! $night_applied && ( $night_apply_mode === 'either' || $night_apply_mode === 'both' ) ) {
				if ( ! empty( $delivery_time ) ) {
					$is_delivery_night = $this->is_time_in_night_window( $delivery_time, $night_start, $night_end );
					if ( $is_delivery_night ) {
						$night_applied = true;
					}
				}
			}
		}

		// ========================================================================
		// STEP 2: Set per-mile rate (apply night multiplier to rate ONLY)
		// ========================================================================
		$rate_per_mile = $base_rate_per_mile;
		if ( $night_applied ) {
			$rate_per_mile = $base_rate_per_mile * $night_multiplier_value;
		}

		// ========================================================================
		// STEP 3: Calculate distance cost using the (possibly doubled) rate
		// ========================================================================
		$distance_cost = round( $distance_miles * $rate_per_mile, 2 );

		// ========================================================================
		// STEP 4: Apply minimum charge to distance cost only (NOT including admin)
		// ========================================================================
		$chargeable_cost = max( $distance_cost, $min_charge );
		$chargeable_cost = round( $chargeable_cost, 2 );

		// ========================================================================
		// STEP 5: Apply service multiplier (to chargeable cost BEFORE adding admin)
		// ========================================================================
		$service_multiplier = 1.0;
		if ( $service && isset( $service['multiplier'] ) ) {
			$service_multiplier = (float) $service['multiplier'];
			if ( $service_multiplier > 0 && $service_multiplier != 1.0 ) {
				$chargeable_cost = $chargeable_cost * $service_multiplier;
				$chargeable_cost = round( $chargeable_cost, 2 );
			}
		}

		// ========================================================================
		// STEP 6: Add admin fee LAST (NEVER multiplied)
		// ========================================================================
		$final_price = $chargeable_cost + $admin_fee;
		$final_price = round( $final_price, 2 );

		// For display purposes, calculate what the base price would be without night
		$base_rate_for_display = $base_rate_per_mile;
		$distance_cost_day = round( $distance_miles * $base_rate_for_display, 2 );
		$chargeable_cost_day = max( $distance_cost_day, $min_charge );
		if ( $service && isset( $service['multiplier'] ) && $service_multiplier != 1.0 ) {
			$chargeable_cost_day = $chargeable_cost_day * $service_multiplier;
		}
		$chargeable_cost_day = round( $chargeable_cost_day, 2 );
		$base_price = $chargeable_cost_day + $admin_fee;
		$base_price = round( $base_price, 2 );

		// Calculate the night surcharge for transparency
		$night_surcharge = round( $final_price - $base_price, 2 );

		// Return breakdown if requested
		if ( $return_breakdown ) {
			return array(
				'distance_miles' => $distance_miles,
				'rate_per_mile' => $base_rate_per_mile,
				'rate_per_mile_applied' => $rate_per_mile,
				'distance_cost' => $distance_cost,
				'admin_fee' => $admin_fee,
				'chargeable_cost' => $chargeable_cost,
				'service_multiplier' => $service_multiplier,
				'service_id' => $service_id,
				'min_charge' => $min_charge,
				'base_price' => $base_price,
				'night_enabled' => $night_enabled,
				'night_start' => $night_start,
				'night_end' => $night_end,
				'collection_time' => $collection_time,
				'delivery_time' => $delivery_time,
				'night_applied' => $night_applied,
				'night_multiplier_value' => $night_multiplier_value,
				'night_surcharge' => $night_surcharge,
				'final_price' => $final_price,
				'distance_cost_day' => $distance_cost_day, // Added for clearer breakdown
				'chargeable_cost_day' => $chargeable_cost_day, // Added for clearer breakdown
				'is_business_credit' => $is_business_credit, // Track business credit status
			);
		}

		return $final_price;
	}

	/**
	 * Check if a time (HH:mm format) falls within night window
	 * 
	 * @param string $time        Time in HH:mm format
	 * @param int    $night_start Night start hour (0-23)
	 * @param int    $night_end   Night end hour (0-23)
	 * @return bool True if time is in night window
	 */
	private function is_time_in_night_window( $time, $night_start, $night_end ) {
		$parts = explode( ':', $time );
		if ( count( $parts ) < 1 ) {
			return false;
		}

		$hour = (int) $parts[0];

		if ( $night_start > $night_end ) {
			// Crossover midnight (e.g., 22:00 to 06:00)
			return ( $hour >= $night_start ) || ( $hour < $night_end );
		} else {
			// Same day (e.g., 00:00 to 06:00)
			return ( $hour >= $night_start && $hour < $night_end );
		}
	}

	/**
	 * Calculate total with VAT
	 */
	public function calculate_total( $base_price, $discount_amount = 0 ) {
		$vat_rate = $this->get_vat_rate();
		$vat_amount = $base_price * ( $vat_rate / 100 );
		$total = ( $base_price + $vat_amount ) - $discount_amount;

		return array(
			'base_price' => round( $base_price, 2 ),
			'subtotal' => round( $base_price, 2 ), // Alias for backwards compatibility
			'vat_amount' => round( $vat_amount, 2 ),
			'discount_amount' => $discount_amount,
			'total' => round( $total, 2 ),
		);
	}

	/**
	 * Get pricing breakdown for transparency
	 * Returns detailed breakdown with all components visible
	 * 
	 * @param float  $distance_miles     Distance in miles
	 * @param string $vehicle_id         Vehicle ID
	 * @param string $collection_time    HH:mm format (optional)
	 * @param string $service_id         Service ID (optional, default 'same_day')
	 * @param string $delivery_time      HH:mm format (optional)
	 * @param bool   $is_business_credit Business credit user (admin fee waived)
	 * @return array Pricing breakdown
	 */
	public function get_pricing_breakdown( $distance_miles, $vehicle_id, $collection_time = '', $service_id = 'same_day', $delivery_time = '', $is_business_credit = false ) {
		// Get the full calculation with breakdown (night surcharge is expected to be calculated there)
		$breakdown = $this->calculate_price( $distance_miles, $vehicle_id, $collection_time, $service_id, $delivery_time, true, $is_business_credit );
		
		// Add formatted prices for front-end display
		$breakdown['base_price_formatted'] = '£' . number_format( $breakdown['base_price'], 2 );
		
		// Ensure night surcharge is set (backward compat if calculate_price didn't return it for some reason)
		if (!isset($breakdown['night_surcharge'])) {
			$breakdown['night_surcharge'] = 0;
            // Fallback logic if needed, but calculate_price should handle it now
            if ( !empty($breakdown['night_applied']) ) {
                // If missing, we assume the old full-multiplication applied, so surcharge is final - base
                $breakdown['night_surcharge'] = round( $breakdown['final_price'] - $breakdown['base_price'], 2 );
            }
		}
		
		$breakdown['night_surcharge_formatted'] = '£' . number_format( $breakdown['night_surcharge'], 2 );
		$breakdown['final_price_formatted'] = '£' . number_format( $breakdown['final_price'], 2 );
		
		return $breakdown;
	}
}
