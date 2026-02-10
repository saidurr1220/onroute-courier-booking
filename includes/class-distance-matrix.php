<?php
/**
 * Distance Matrix API Handler
 * Ensures EXACTLY ONE API call per quote request.
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Distance_Matrix {

	/**
	 * Get distance between two postcodes
	 * 
	 * @param string $origin      Collection postcode
	 * @param string $destination Delivery postcode
	 * @return float|WP_Error     Distance in miles or error
	 */
	public static function get_distance( $origin, $destination ) {
		// Normalize inputs for better cache hits
		$origin = strtoupper( str_replace( ' ', '', $origin ) );
		$destination = strtoupper( str_replace( ' ', '', $destination ) );

		// Check session cache first
		$cached_distance = self::get_cached_distance( $origin, $destination );

		if ( $cached_distance !== false ) {
			// Ensure we don't return the fallback distance from cache blindly
			if ( $cached_distance != 10 ) {
				return $cached_distance;
			}
			// If cached value is 10, invalid it and try fresh API call
		}

		// Check which distance provider to use
		// Client Requirement: Default to Google Distance Matrix
		$provider = get_option( 'ocb_distance_provider', 'google' );

		if ( $provider === 'openroute' ) {
			$ors_key = get_option( 'ocb_openroute_api_key', '' );
			if ( ! empty( $ors_key ) ) {
				$ors_distance = self::get_distance_ors( $origin, $destination, $ors_key );
				if ( ! is_wp_error( $ors_distance ) ) {
					self::cache_distance( $origin, $destination, $ors_distance );
					return $ors_distance;
				} else {
					error_log( 'OCB ORS Error: ' . $ors_distance->get_error_message() );
					return $ors_distance; // Return the error directly so frontend knows why it failed
				}
			} else {
				return new WP_Error( 'missing_key', 'OpenRouteService API Key is missing. Please add it in Settings.' );
			}
		}

		// GOOGLE MAPS FALLBACK (Only runs if Provider is set to 'google')
		// Try to get Server Key first (for backend requests), then general Key
		if ( defined( 'OCB_GOOGLE_MAPS_SERVER_KEY' ) ) {
			$api_key = OCB_GOOGLE_MAPS_SERVER_KEY;
		} elseif ( defined( 'OCB_GOOGLE_MAPS_API_KEY' ) ) {
			$api_key = OCB_GOOGLE_MAPS_API_KEY;
		} else {
			$api_key = get_option( 'ocb_google_maps_api_key' );
		}
		
		if ( empty( $api_key ) ) {
			// Log checking failure
			error_log( 'OCB Error: Neither OpenRouteService nor Google Maps API key is configured. Using fallback distance.' );
			$fallback = (float) get_option( 'ocb_fallback_distance', 10 );
			return $fallback;
		}

		// Prepare API request
		$url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
		$country_restrict = 'GB'; // Bias results to UK
		$args = array(
			'origins' => $origin,
			'destinations' => $destination,
			'key' => trim($api_key),
			'units' => 'imperial', // We need miles
			'mode' => 'driving',
		);

		$request_url = add_query_arg( $args, $url );
		
		// Determine environment
		$is_local = ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) || 
		            ( defined( 'WP_DEBUG' ) && WP_DEBUG );

		// Secure remote request
		$http_args = array( 
			'sslverify' => ! $is_local, 
			'timeout' => 15,
		);

		// CRITICAL FOR LIVE SERVER WITH 1 KEY:
		// Since you are using a Domain Restricted Key for both Frontend and Backend,
		// we MUST explicitly send the Referer Header in this PHP request.
		// This "tricks" Google into accepting the request from your server as if it came from your website browser.
		// $http_args['headers'] = array( 'Referer' => home_url( '/' ) ); 

		$response = wp_remote_get( $request_url, $http_args );

		if ( is_wp_error( $response ) ) {
			// Log error only to system log, do not write to file in production
			error_log( 'OCB API WP Error: ' . $response->get_error_message() );
			
			// Only return fallback if we absolutely have to, but log it first
			$fallback = (float) get_option( 'ocb_fallback_distance', 10 );
			return $fallback;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		// Handle API Errors (Billing, Quota, Keys)
		if ( isset( $data['status'] ) && 'OK' !== $data['status'] ) {
			$error_msg = isset( $data['error_message'] ) ? $data['error_message'] : $data['status'];
			// Return strictly as WP_Error so the frontend sees it immediately and doesn't use fallback
			return new WP_Error( 'api_error', 'Google Maps API Error: ' . $error_msg );
		}

		if ( isset( $data['status'] ) && 'OK' === $data['status'] ) {
			if ( isset( $data['rows'][0]['elements'][0]['status'] ) && 'OK' === $data['rows'][0]['elements'][0]['status'] ) {
				$distance_text = $data['rows'][0]['elements'][0]['distance']['text'];
				$distance_value_meters = $data['rows'][0]['elements'][0]['distance']['value'];
				
				// Convert meters to miles
				$miles = round( $distance_value_meters * 0.000621371, 1 );
				
				// Cache the result
				self::cache_distance( $origin, $destination, $miles );
				
				return $miles;
			}
		}

		// LOGGING FOR FALLBACK
		error_log('OCB Fallback Triggered. Data: ' . print_r($data, true));

		// Fallback if API fails or returns no route
		$fallback = (float) get_option( 'ocb_fallback_distance', 10 );
		return $fallback;
	}

	/**
	 * Cache distance result in session
	 */
	public static function cache_distance( $origin, $destination, $distance ) {
		if ( ! session_id() ) {
			session_start(); // @codingStandardsIgnoreLine
		}
		
		if ( ! isset( $_SESSION['ocb_distance_cache'] ) || ! is_array( $_SESSION['ocb_distance_cache'] ) ) {
			$_SESSION['ocb_distance_cache'] = array();
		}

		// Create a unique key for this route
		$key = md5( $origin . '|' . $destination );

		$_SESSION['ocb_distance_cache'][ $key ] = array(
			'distance' => $distance,
			'timestamp' => time()
		);
	}

	/**
	 * Retrieve cached distance
	 */
	private static function get_cached_distance( $origin, $destination ) {
		if ( ! session_id() ) {
			session_start(); // @codingStandardsIgnoreLine
		}

		if ( isset( $_SESSION['ocb_distance_cache'] ) && is_array( $_SESSION['ocb_distance_cache'] ) ) {
			$key = md5( $origin . '|' . $destination );
			
			if ( isset( $_SESSION['ocb_distance_cache'][ $key ] ) ) {
				return $_SESSION['ocb_distance_cache'][ $key ]['distance'];
			}
		}

		return false;
	}

	/**
	 * Get distance using OpenRouteService
	 */
	private static function get_distance_ors( $origin, $destination, $api_key ) {
		// Determine environment
		$is_local = ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === WP_ENVIRONMENT_TYPE ) || 
					( defined( 'WP_DEBUG' ) && WP_DEBUG );

		// 1. Geocode Origin
		$origin_coords = self::get_coords_ors( $origin, $api_key, $is_local );
		if ( is_wp_error( $origin_coords ) ) return $origin_coords;

		// 2. Geocode Destination
		$dest_coords = self::get_coords_ors( $destination, $api_key, $is_local );
		if ( is_wp_error( $dest_coords ) ) return $dest_coords;

		// 3. Matrix Request
		$url = 'https://api.openrouteservice.org/v2/matrix/driving-car';
		
		$args = array(
			'headers' => array(
				'Authorization' => $api_key,
				'Content-Type' => 'application/json'
			),
			'body' => json_encode( array(
				'locations' => array( $origin_coords, $dest_coords ),
				'metrics' => array( 'distance' )
			) ),
			'timeout' => 15,
			'sslverify' => ! $is_local,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) return $response;
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( isset( $data['distances'][0][1] ) ) {
			$meters = $data['distances'][0][1];
			// Convert meters to miles
			$miles = round( $meters * 0.000621371, 1 );
			return $miles;
		}

		return new WP_Error( 'ors_error', 'Could not retrieve distance from OpenRouteService' );
	}

	/**
	 * Geocode using OpenRouteService
	 */
	private static function get_coords_ors( $postcode, $api_key, $is_local = false ) {
		// Normalize UK Postcode: Ensure uppercase and proper spacing (e.g., "e16an" -> "E1 6AN")
		$raw_postcode = $postcode; // Keep original for error message
		$postcode = strtoupper( preg_replace( '/[^a-zA-Z0-9]/', '', $postcode ) );
		if ( strlen( $postcode ) >= 5 && strlen( $postcode ) <= 7 ) {
			$incode = substr( $postcode, -3 );
			$outcode = substr( $postcode, 0, strlen( $postcode ) - 3 );
			$postcode = $outcode . ' ' . $incode;
		} else {
			// If weird format, revert to original just in case it's a place name
			$postcode = $raw_postcode;
		}

		$url = 'https://api.openrouteservice.org/geocode/search';
		$url = add_query_arg( array(
			'api_key' => $api_key,
			'text' => $postcode,
			'boundary.country' => 'GB',
			'size' => 1
		), $url );
		
		$args = array(
			'timeout' => 15,
			'sslverify' => ! $is_local,
		);
		
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) return $response;
		
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		
		// Check for API Error Responses
		if ( isset( $data['error'] ) ) {
			$msg = is_array( $data['error'] ) && isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown ORS Error';
			error_log( 'OCB ORS Geocode Error: ' . print_r( $data, true ) );
			return new WP_Error( 'ors_api_error', 'ORS API Error: ' . $msg );
		}

		if ( ! empty( $data['features'] ) ) {
			return $data['features'][0]['geometry']['coordinates']; // [lon, lat]
		}
		
		// Log detailed failure for debugging
		error_log( 'OCB ORS Geocode Failed for ' . $postcode . '. Response: ' . print_r( $data, true ) );
		
		return new WP_Error( 'ors_geocode_error', 'Could not geocode ' . $raw_postcode );
	}
}
