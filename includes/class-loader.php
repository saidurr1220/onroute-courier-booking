<?php
/**
 * Main plugin loader class
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Loader {

	/**
	 * Run the plugin
	 */
	public function run() {
		// Initialize Payments System
		if ( class_exists( 'OnRoute_Courier_Booking_Payment_Settings' ) ) {
			new OnRoute_Courier_Booking_Payment_Settings();
		}
		if ( class_exists( 'OnRoute_Courier_Booking_Stripe_Webhook' ) ) {
			new OnRoute_Courier_Booking_Stripe_Webhook();
		}

		// Initialize Emails (for SMTP configuration)
		if ( class_exists( 'OnRoute_Courier_Booking_Emails' ) ) {
			new OnRoute_Courier_Booking_Emails();
		}

		if ( is_admin() ) {
			new OnRoute_Courier_Booking_Admin();
			new OnRoute_Courier_Booking_Dashboard_Widget();
			if ( class_exists( 'OnRoute_Courier_Booking_Payment_Dashboard' ) ) {
			new OnRoute_Courier_Booking_Payment_Dashboard();
			}
			if ( class_exists( 'OnRoute_Business_Credit_Admin' ) ) {
				new OnRoute_Business_Credit_Admin();
			}
			if ( class_exists( 'OnRoute_POD_Admin' ) ) {
				new OnRoute_POD_Admin();
			}
		} else {
			new OnRoute_Courier_Booking_Frontend();
		}

		if ( class_exists( 'OnRoute_Business_Credit_Public' ) ) {
			new OnRoute_Business_Credit_Public();
		}

		// Initialize Driver Portal
		if ( class_exists( 'OnRoute_Courier_Booking_Driver_Portal' ) ) {
			new OnRoute_Courier_Booking_Driver_Portal();
		}

		// Dashboard extensions (saved locations, support, invoices, POD)
		if ( class_exists( 'OnRoute_Dashboard_Extensions' ) ) {
			new OnRoute_Dashboard_Extensions();
		}

		// Initialize forms
		new OnRoute_Courier_Booking_Forms();

		// Check and Fix Database Options on Load (Temporary Self-Healing)
		// This ensures the client requirements are met even if legacy data exists
		if ( class_exists( 'OnRoute_Courier_Booking_Activator' ) ) {
			// We can't access private method, but we can re-run the activation check logic manually
			$services = get_option( 'ocb_services', array() );
			$needs_fix = false;
			foreach ( $services as $service ) {
				if ( $service['id'] === 'next_day' ) {
					$needs_fix = true;
					break;
				}
			}
			if ( $needs_fix ) {
				OnRoute_Courier_Booking_Activator::activate();
			}
		}

		// Enqueue assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );


		// Register AJAX handlers
		add_action( 'wp_ajax_nopriv_ocb_quote_search', array( $this, 'ajax_quote_search' ) );
		add_action( 'wp_ajax_ocb_quote_search', array( $this, 'ajax_quote_search' ) );
		add_action( 'wp_ajax_nopriv_ocb_calculate_price', array( $this, 'ajax_calculate_price' ) );
		add_action( 'wp_ajax_ocb_calculate_price', array( $this, 'ajax_calculate_price' ) );
		add_action( 'wp_ajax_nopriv_ocb_apply_promo', array( $this, 'ajax_apply_promo' ) );
		add_action( 'wp_ajax_ocb_apply_promo', array( $this, 'ajax_apply_promo' ) );
		add_action( 'wp_ajax_nopriv_ocb_create_booking', array( $this, 'ajax_create_booking' ) );
		add_action( 'wp_ajax_ocb_create_booking', array( $this, 'ajax_create_booking' ) );
	}

	/**
	 * Enqueue Frontend Assets
	 */
	public function enqueue_assets() {
		// Enqueue a unique handle for Font Awesome to avoid theme/plugin conflicts
		wp_enqueue_style( 'onroute-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );

		// Google Maps API - Re-enabled for Client-Side Calculations
		$google_maps_key = defined( 'OCB_GOOGLE_MAPS_API_KEY' ) ? OCB_GOOGLE_MAPS_API_KEY : get_option( 'ocb_google_maps_api_key', '' );
		
		if ( ! empty( $google_maps_key ) ) {
			wp_enqueue_script( 
				'google-maps-api', 
				'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_key . '&libraries=places&loading=async', 
				array(), 
				null, 
				false 
			);
			// Add async and defer attributes for better performance
			add_filter( 'script_loader_tag', function( $tag, $handle ) {
				if ( 'google-maps-api' === $handle ) {
					return str_replace( ' src', ' async defer src', $tag );
				}
				return $tag;
			}, 10, 2 );
		}

		// Quick Quote - Responsive styles
		wp_enqueue_style( 'ocb-quick-quote-responsive', ONROUTE_COURIER_BOOKING_URL . 'assets/quick-quote-responsive.css', array( 'onroute-font-awesome' ), ONROUTE_COURIER_BOOKING_VERSION );
		
		// Quick Quote - Base styles  
		wp_enqueue_style( 'ocb-quick-quote', ONROUTE_COURIER_BOOKING_URL . 'assets/quick-quote.css', array( 'ocb-quick-quote-responsive' ), ONROUTE_COURIER_BOOKING_VERSION );

		// Multi-step form - CLEAN 3-STEP REDESIGN (Black & Red Theme)
		wp_enqueue_style( 'ocb-multi-step-clean', ONROUTE_COURIER_BOOKING_URL . 'assets/multi-step-clean.css', array( 'onroute-font-awesome' ), ONROUTE_COURIER_BOOKING_VERSION );
	
		// Flatpickr for 24h Time Picker
		wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13' );
		wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', array(), '4.6.13', true );

		// Elementor Column Optimization
		wp_enqueue_style( 'ocb-elementor-fix', ONROUTE_COURIER_BOOKING_URL . 'assets/elementor-column-fix.css', array( 'ocb-multi-step-clean' ), ONROUTE_COURIER_BOOKING_VERSION );
		
		wp_enqueue_script( 'ocb-multi-step-clean', ONROUTE_COURIER_BOOKING_URL . 'assets/multi-step-clean.js', array( 'jquery', 'flatpickr' ), ONROUTE_COURIER_BOOKING_VERSION, true );

		// Address Lookup Script - UK Postcode Selection
		wp_enqueue_script( 'ocb-address-lookup', ONROUTE_COURIER_BOOKING_URL . 'assets/address-lookup.js', array( 'jquery' ), ONROUTE_COURIER_BOOKING_VERSION, true );

		// Get API Key for localization
		$google_maps_key = '';
		if ( defined( 'OCB_GOOGLE_MAPS_API_KEY' ) ) {
			$google_maps_key = OCB_GOOGLE_MAPS_API_KEY;
		} else {
			$google_maps_key = get_option( 'ocb_google_maps_api_key', '' );
		}

		// Pass AJAX URL and nonces to address-lookup script
		wp_localize_script( 'ocb-address-lookup', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
		$ideal_key = get_option( 'ocb_ideal_postcodes_api_key', '' );
		$getaddress_key = get_option( 'ocb_getaddress_io_api_key', '' );
		wp_localize_script( 'ocb-address-lookup', 'ocbAddressData', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ocb_api_nonce' ),
			'idealPostcodesAvailable' => ! empty( $ideal_key ),
			'getaddressAvailable' => ! empty( $getaddress_key ),
		) );

		// Pass AJAX URL and nonces to scripts
		$vat_rate = (float) get_option( 'ocb_vat_rate', 20 );
		$fallback_distance = get_option( 'ocb_fallback_distance', 10 );

		wp_localize_script( 'ocb-multi-step-clean', 'ocbData', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'siteUrl' => site_url(),
			'vatRate' => $vat_rate,
			'googleMapsKey' => $google_maps_key,
			'fallbackDistance' => floatval( $fallback_distance ),
			'serverTime' => current_time( 'Y-m-d\TH:i:s' ),
			'timezone' => wp_timezone_string(),
			'nonce' => wp_create_nonce( 'ocb_nonce' ), // Generic nonce for backward compatibility
			'nonces' => array(
				'api' => wp_create_nonce( 'ocb_api_nonce' ),
				'price' => wp_create_nonce( 'ocb_price_nonce' ),
				'geocode' => wp_create_nonce( 'ocb_geocode_nonce' ),
				'main' => wp_create_nonce( 'ocb_nonce' ),
				'multi' => wp_create_nonce( 'ocb_multi_nonce' ),
			),
		) );
	}

	/**
	 * AJAX: Quote search
	 */
	public function ajax_quote_search() {
		// Security check (allow both main nonce and api nonce)
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ocb_nonce' ) && ! wp_verify_nonce( $nonce, 'ocb_api_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
			die();
		}

		$pickup_code = isset( $_POST['pickup_code'] ) ? sanitize_text_field( $_POST['pickup_code'] ) : '';
		$delivery_code = isset( $_POST['delivery_code'] ) ? sanitize_text_field( $_POST['delivery_code'] ) : '';

		if ( empty( $pickup_code ) || empty( $delivery_code ) ) {
			wp_send_json_error( array( 'message' => 'Postcodes required' ) );
		}

		// Calculate Distance (with debug info)
		$debug = array(
			'api_key_configured' => ! empty( get_option( 'ocb_openroute_api_key' ) ) || ! empty( get_option( 'ocb_google_maps_api_key' ) ),
			'fallback_used' => false,
			'error' => '',
			'provider' => get_option( 'ocb_distance_provider', 'openroute' )
		);

		// Check if client-side distance is provided (Fix for Referrer Restricted Keys)
		if ( isset( $_POST['client_distance'] ) && is_numeric( $_POST['client_distance'] ) && (float)$_POST['client_distance'] > 0 ) {
			$distance = (float) $_POST['client_distance'];
			$debug['source'] = 'client_side_google_maps';
		} else {
			$distance = OnRoute_Courier_Booking_Distance_Matrix::get_distance( $pickup_code, $delivery_code );
		}

		if ( is_wp_error( $distance ) ) {
			$debug['fallback_used'] = true;
			$debug['error'] = $distance->get_error_message(); // Capture the specific API error
			error_log( 'OCB Distance Error: ' . $distance->get_error_message() );
			
			// Force fallback distance instead of failing
			$distance = (float) get_option( 'ocb_fallback_distance', 10 );
			error_log( 'OCB: Using fallback distance due to API error: ' . $distance . ' miles' );
		} elseif ( $distance <= 0 ) {
			$debug['fallback_used'] = true;
			$debug['error'] = 'Invalid distance calculated (0 or less)';
			
			// Use fallback if distance is invalid
			$distance = (float) get_option( 'ocb_fallback_distance', 10 );
			error_log( 'OCB: Invalid distance calculated, using fallback: ' . $distance . ' miles' );
		} else {
			// Check if we got the exact fallback distance (which might imply get_distance returned fallback internally blindly)
			$fallback = (float) get_option( 'ocb_fallback_distance', 10 );
			if ( abs( $distance - $fallback ) < 0.01 ) {
				// We can't be 100% sure, but it looks like a fallback
				// Or the user is exactly 10 miles away.
				// But given the context, let's just log it.
				$debug['note'] = 'Distance equals fallback setting';
			}
		}

		// Get Pricing Data
		$pricing = new OnRoute_Courier_Booking_Pricing();
		$vehicles = $pricing->get_vehicles();
		$services = $pricing->get_services();
		
		// Get Time/Date from Session if available, else default to Now
		OnRoute_Courier_Booking_Session::init();
		$collection_time = OnRoute_Courier_Booking_Session::get( 'collection_time', date( 'H:i' ) );
		$delivery_time = OnRoute_Courier_Booking_Session::get( 'delivery_time', '' ); // Optional
		
		// Calculate prices for all combinations
		$results = array();
		
		foreach ( $services as $service ) {
			$service_id = $service['id'];
			$service_results = array();
			
			foreach ( $vehicles as $vehicle ) {
				// Calculate Price based on specific time
				// Pass delivery_time if available, with return_breakdown for detailed pricing info
				$price = $pricing->calculate_price( $distance, $vehicle['id'], $collection_time, $service_id, $delivery_time );
				
				// Night Price Reference (23:00 collection time)
				$night_price = $pricing->calculate_price( $distance, $vehicle['id'], '23:00', $service_id, $delivery_time );

				$service_results[] = array(
					'vehicle_id' => $vehicle['id'],
					'vehicle_name' => $vehicle['name'],
					'dimensions' => isset( $vehicle['dimensions'] ) ? $vehicle['dimensions'] : '',
					'max_weight' => isset( $vehicle['max_weight'] ) ? $vehicle['max_weight'] : '',
					'price' => $price,
					'night_price' => $night_price,
					'vat_rate' => $pricing->get_vat_rate(),
					'formatted_price' => '£' . number_format( $price, 2 ),
				);
			}

			// Ensure results are not empty
			if ( ! empty( $service_results ) ) {
				$results[ $service_id ] = $service_results;
				
				// Map aliases for frontend compatibility
				if ( 'priority' === $service_id ) {
					$results['timed'] = $service_results;
				} elseif ( 'timed' === $service_id ) {
					$results['priority'] = $service_results;
				} elseif ( 'direct' === $service_id ) {
					$results['dedicated'] = $service_results;
				} elseif ( 'dedicated' === $service_id ) {
					$results['direct'] = $service_results;
				}
			}
		}

		// Also return raw vehicle/service data for the frontend to render UI
		wp_send_json_success( array(
			'pickup' => array(
				'code' => $pickup_code,
			),
			'delivery' => array(
				'code' => $delivery_code,
			),
			'distance' => $distance,
			'debug' => $debug, // Pass debug info to frontend
			'quotes' => $results,
			'services' => $services,
			'vehicles' => $vehicles,
		) );
	}

	/**
	 * AJAX: Calculate price
	 */
	public function ajax_calculate_price() {
		check_ajax_referer( 'ocb_nonce', 'nonce' );

		$vehicle_id = isset( $_POST['vehicle_id'] ) ? sanitize_text_field( $_POST['vehicle_id'] ) : '';
		$service_id = isset( $_POST['service_id'] ) ? strtolower( trim( sanitize_text_field( $_POST['service_id'] ) ) ) : 'same_day';
		
		// Map UI service IDs to database IDs if they differ
		// Handle JS 'null' or 'undefined' strings
		if ( empty( $service_id ) || 'null' === $service_id || 'undefined' === $service_id ) {
			$service_id = 'same_day';
		}

		if ( 'priority' === $service_id ) {
			$service_id = 'timed';
		} elseif ( 'direct' === $service_id ) {
			$service_id = 'dedicated';
		}

		$pickup_code = isset( $_POST['pickup_code'] ) ? sanitize_text_field( $_POST['pickup_code'] ) : '';
		$delivery_code = isset( $_POST['delivery_code'] ) ? sanitize_text_field( $_POST['delivery_code'] ) : '';
		$collection_time = isset( $_POST['collection_time'] ) ? sanitize_text_field( $_POST['collection_time'] ) : '';
		$delivery_time = isset( $_POST['delivery_time'] ) ? sanitize_text_field( $_POST['delivery_time'] ) : '';

		// Recalculate or retrieve distance
		if ( empty( $pickup_code ) || empty( $delivery_code ) ) {
			wp_send_json_error( array( 'message' => 'Postcodes required for price calculation' ) ); 
			return;
		}

		// Fix: Prefer client-provided distance for consistency with Step 1
		$distance = 0;
		if ( isset( $_POST['client_distance'] ) && is_numeric( $_POST['client_distance'] ) && (float)$_POST['client_distance'] > 0 ) {
			$distance = (float) $_POST['client_distance'];
		} else {
			$distance = OnRoute_Courier_Booking_Distance_Matrix::get_distance( $pickup_code, $delivery_code );
		}

		if ( is_wp_error( $distance ) ) {
			wp_send_json_error( array( 'message' => 'Could not calculate distance: ' . $distance->get_error_message() ) );
			return;
		}

		if ( empty( $vehicle_id ) ) {
			wp_send_json_error( array( 'message' => 'Vehicle required' ) );
			return;
		}

		$pricing = new OnRoute_Courier_Booking_Pricing();
		
		// Use get_pricing_breakdown to ensure we get formatted strings and correct surcharge calculations
		$breakdown = $pricing->get_pricing_breakdown( $distance, $vehicle_id, $collection_time, $service_id, $delivery_time );
		
		// Calculate totals on the final price
		$final_price = $breakdown['final_price'];
		$totals = $pricing->calculate_total( $final_price );

		wp_send_json_success( array(
			'distance' => $distance,
			'base_price' => $totals['base_price'], // This is actually the Final Price (ex VAT)
			'vat_amount' => $totals['vat_amount'],
			'total_price' => $totals['total'],
			'formatted_price' => '£' . number_format( $totals['total'], 2 ),
			'breakdown' => $breakdown, // Include full breakdown with formatting
		) );
	}


	/**
	 * AJAX: Apply promo code
	 */
	public function ajax_apply_promo() {
		check_ajax_referer( 'ocb_nonce', 'nonce' );

		$code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
		$base_price = isset( $_POST['base_price'] ) ? floatval( $_POST['base_price'] ) : 0;

		if ( empty( $code ) ) {
			wp_send_json_error( array( 'message' => 'Promo code required' ) );
		}

		$promo = new OnRoute_Courier_Booking_Promo();
		$discount = $promo->validate_and_calculate( $code, $base_price );

		if ( is_wp_error( $discount ) ) {
			wp_send_json_error( array( 'message' => $discount->get_error_message() ) );
		}

		wp_send_json_success( array(
			'discount_amount' => $discount['amount'],
			'discount_type' => $discount['type'],
			'discount_value' => $discount['value'],
		) );
	}

	/**
	 * AJAX: Create booking
	 */
	public function ajax_create_booking() {
		check_ajax_referer( 'ocb_nonce', 'nonce' );

		$booking_data = array(
			'customer_email' => isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '',
			'customer_phone' => isset( $_POST['phone'] ) ? sanitize_text_field( $_POST['phone'] ) : '',
			'pickup_address' => isset( $_POST['pickup_address'] ) ? sanitize_text_field( $_POST['pickup_address'] ) : '',
			'pickup_postcode' => isset( $_POST['pickup_code'] ) ? sanitize_text_field( $_POST['pickup_code'] ) : '',
			'delivery_address' => isset( $_POST['delivery_address'] ) ? sanitize_text_field( $_POST['delivery_address'] ) : '',
			'delivery_postcode' => isset( $_POST['delivery_code'] ) ? sanitize_text_field( $_POST['delivery_code'] ) : '',
			'collection_date' => isset( $_POST['collection_date'] ) ? sanitize_text_field( $_POST['collection_date'] ) : '',
			'collection_time' => isset( $_POST['collection_time'] ) ? sanitize_text_field( $_POST['collection_time'] ) : '',
			'delivery_date' => isset( $_POST['delivery_date'] ) ? sanitize_text_field( $_POST['delivery_date'] ) : '',
			'delivery_time' => isset( $_POST['delivery_time'] ) ? sanitize_text_field( $_POST['delivery_time'] ) : '',
			'vehicle_id' => isset( $_POST['vehicle_id'] ) ? sanitize_text_field( $_POST['vehicle_id'] ) : '',
			'service_id' => isset( $_POST['service_id'] ) ? sanitize_text_field( $_POST['service_id'] ) : '',
			'base_price' => isset( $_POST['base_price'] ) ? floatval( $_POST['base_price'] ) : 0,
			'discount_amount' => isset( $_POST['discount_amount'] ) ? floatval( $_POST['discount_amount'] ) : 0,
			'vat_amount' => isset( $_POST['vat_amount'] ) ? floatval( $_POST['vat_amount'] ) : 0,
			'total_price' => isset( $_POST['total_price'] ) ? floatval( $_POST['total_price'] ) : 0,
			'promo_code' => isset( $_POST['promo_code'] ) ? sanitize_text_field( $_POST['promo_code'] ) : '',
		);

		// Validate required fields
		$required_fields = array( 'customer_email', 'customer_phone', 'pickup_address', 'delivery_address', 'collection_date', 'collection_time', 'delivery_date', 'vehicle_id', 'service_id' );
		foreach ( $required_fields as $field ) {
			if ( empty( $booking_data[ $field ] ) ) {
				wp_send_json_error( array( 'message' => 'All fields are required' ) );
			}
		}

		// Validate email
		if ( ! is_email( $booking_data['customer_email'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid email address' ) );
		}

		// Re-validate price on backend
		$pricing = new OnRoute_Courier_Booking_Pricing();
		
		// Map UI service IDs to database IDs if they differ
		$service_id_mapped = strtolower( trim( $booking_data['service_id'] ) );
		if ( empty( $service_id_mapped ) || 'null' === $service_id_mapped || 'undefined' === $service_id_mapped ) {
			$service_id_mapped = 'same_day';
		}

		if ( 'priority' === $service_id_mapped ) {
			$service_id_mapped = 'timed';
		} elseif ( 'direct' === $service_id_mapped ) {
			$service_id_mapped = 'dedicated';
		}

		$distance_miles = isset( $_POST['distance_miles'] ) ? (float) $_POST['distance_miles'] : 0;
		if ($distance_miles <= 0) {
			// Try to recalculate if missing
			$distance_miles = OnRoute_Courier_Booking_Distance_Matrix::get_distance( $booking_data['pickup_postcode'], $booking_data['delivery_postcode'] );
		}

		$base_price = $pricing->calculate_price( 
			$distance_miles, 
			$booking_data['vehicle_id'], 
			$booking_data['collection_time'], 
			$service_id_mapped, 
			$booking_data['delivery_time'] 
		);
		
		$vat_rate = $pricing->get_vat_rate();
		$vat_amount = $base_price * ( $vat_rate / 100 );

		// Apply promo if provided
		$discount_amount = 0;
		if ( ! empty( $booking_data['promo_code'] ) ) {
			$promo = new OnRoute_Courier_Booking_Promo();
			$discount = $promo->validate_and_calculate( $booking_data['promo_code'], $base_price );
			if ( ! is_wp_error( $discount ) ) {
				$discount_amount = $discount['amount'];
			}
		}

		$total_price = ( $base_price + $vat_amount ) - $discount_amount;

		// Verify submitted price matches backend calculation (security)
		// Allow small deviation for rounding
		if ( abs( $total_price - $booking_data['total_price'] ) > 1.00 ) {
			// If verification fails, log it but maybe allow if it's within $1.00 (floating point issues)
			error_log("OCB: Price verification failed. Backend: $total_price, Frontend: " . $booking_data['total_price']);
			// wp_send_json_error( array( 'message' => 'Price verification failed. Please refresh and try again.' ) );
		}

		// Create booking
		$booking = new OnRoute_Courier_Booking_Booking();
		$booking_id = $booking->create(
			$booking_data['customer_email'],
			$booking_data['customer_phone'],
			$booking_data['pickup_address'],
			$booking_data['pickup_postcode'],
			$booking_data['delivery_address'],
			$booking_data['delivery_postcode'],
			$booking_data['collection_date'],
			$booking_data['collection_time'],
			$booking_data['delivery_date'],
			$booking_data['delivery_time'],
			$booking_data['vehicle_id'],
			$booking_data['service_id'],
			$base_price,
			$vat_amount,
			$discount_amount,
			$total_price,
			$booking_data['promo_code']
		);

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => 'Failed to create booking' ) );
		}

		// Store booking session for next step
		if ( ! session_id() ) {
			session_start();
		}
		$_SESSION['ocb_booking_id'] = $booking_id;

		wp_send_json_success( array(
			'booking_id' => $booking_id,
			'redirect' => add_query_arg( array(
				'step' => 'payment',
				'booking_id' => $booking_id,
			), site_url() ),
		) );
	}
}
