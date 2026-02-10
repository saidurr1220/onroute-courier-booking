<?php
/**
 * Google Places API Handler
 * 
 * Handles communication with Google Places API securely from PHP backend
 * to avoid exposing API key to frontend
 * 
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Courier_Booking_Places_API {

	/**
	 * Get Google Places API Key from WordPress option (ocb_google_maps_api_key)
	 * Uses existing OnRoute Courier plugin API key configuration
	 */
	public static function get_api_key() {
		// Get from OnRoute's existing option
		$key = get_option( 'ocb_google_maps_api_key', '' );
		return sanitize_text_field( $key );
	}

	/**
	 * Update Google Places API Key in WordPress option
	 */
	public static function set_api_key( $api_key ) {
		return update_option( 'ocb_google_maps_api_key', sanitize_text_field( $api_key ) );
	}

	/**
	 * Search for places by text (postcode or location)
	 * 
	 * @param string $query Postcode or search query (full postcode with space)
	 * @param string $region Region bias (e.g., 'GB' for UK)
	 * @return array Formatted places results
	 */
	public static function search_places( $query, $region = 'GB', $lat = '', $lon = '' ) {
		$api_key = self::get_api_key();
		
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => 'Google Places API key not configured'
			);
		}

		// Log removed for production
		// error_log( 'Address Lookup: Searching for postcode: ' . $query );

		$all_places = array();
		$seen = array();

		// Extract outcode for validation (e.g., "M1" from "M1 1AE")
		$query_clean = strtoupper( preg_replace( '/\s+/', '', $query ) );
		$outcode_match = '';
		if ( preg_match( '/^([A-Z]{1,2}[0-9]{1,2})/', $query_clean, $matches ) ) {
			$outcode_match = $matches[1];
		}

		// Strategy 1: Nearby Search (if coordinates are available) - Best for finding businesses at a location
		if ( ! empty( $lat ) && ! empty( $lon ) ) {
			$nearby_url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
			$nearby_args = array(
				'location' => $lat . ',' . $lon,
				'radius'   => 250, // Increased to 250 meters radius from postcode point
				'key'      => $api_key
			);
			
			$response = wp_remote_get( 
				add_query_arg( $nearby_args, $nearby_url ),
				array( 'timeout' => 10, 'sslverify' => apply_filters( 'https_local_ssl_verify', false ) )
			);

			if ( ! is_wp_error( $response ) ) {
				$body = wp_remote_retrieve_body( $response );
				$data = json_decode( $body, true );
				if ( ! empty( $data['results'] ) ) {
					foreach ( $data['results'] as $place ) {
						$place_id = $place['place_id'] ?? '';
						if ( ! isset( $seen[ $place_id ] ) ) {
							$seen[ $place_id ] = true;
							$name = $place['name'] ?? '';
							$vicinity = $place['vicinity'] ?? '';
							$formatted = $place['formatted_address'] ?? '';
							
							// If name is empty, extract it from the formatted address
							if ( empty( $name ) && ! empty( $formatted ) ) {
								$name = explode( ',', $formatted )[0]; // Get first part before comma
							}
							
							// If name is just the postcode, skip it unless it's the only result
							if ( strtoupper( trim( $name ) ) === strtoupper( trim( $query ) ) && count( $data['results'] ) > 1 ) {
								continue;
							}

							// For nearby search, vicinity is often just the street. 
							// ALWAYS use formatted_address if available - it's the complete address
							$display_address = $formatted ?: $vicinity;
							
							$all_places[] = array(
								'id'        => $place_id,
								'name'      => $name,
								'formatted' => $display_address, 
								'lat'       => isset( $place['geometry']['location']['lat'] ) ? $place['geometry']['location']['lat'] : null,
								'lon'       => isset( $place['geometry']['location']['lng'] ) ? $place['geometry']['location']['lng'] : null,
								'types'     => $place['types'] ?? array(),
								'postcode'  => $query // Include original postcode
							);
						}
					}
				}
			}
		}

		// Strategy 2: Text Search with postcode
		$url = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
		
		$search_queries = array();
		// Build multiple search variations to find detailed addresses
		$search_queries[] = $query; // Full postcode search
		$search_queries[] = 'addresses in ' . $query; // Explicit address search
		$search_queries[] = 'houses ' . $query; // House level search
		$search_queries[] = 'businesses ' . $query; // Business level search
		
		// Remove duplicates while preserving order
		$search_queries = array_unique( $search_queries );

		foreach ( $search_queries as $search_query ) {
			// Don't over-fetch if we have plenty of results
			if ( count( $all_places ) >= 100 ) break;

			$args = array(
				'query'  => $search_query,
				'key'    => $api_key,
				'region' => strtolower( $region ),
			);
			// Bias towards the postcode location if known
			if ( ! empty( $lat ) && ! empty( $lon ) ) {
				$args['location'] = $lat . ',' . $lon;
				$args['radius'] = 2000; // Increased radius bias
			}

			$response = wp_remote_get( 
				add_query_arg( $args, $url ),
				array(
					'timeout'   => 10,
					'sslverify' => apply_filters( 'https_local_ssl_verify', false )
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! empty( $data['results'] ) ) {
				foreach ( $data['results'] as $place ) {
					$place_id = $place['place_id'] ?? '';
					if ( ! isset( $seen[ $place_id ] ) ) {
						$seen[ $place_id ] = true;
						$name = $place['name'] ?? '';
						$formatted = $place['formatted_address'] ?? '';
						
						// If name is empty, extract it from the formatted address
						if ( empty( $name ) && ! empty( $formatted ) ) {
							$name = explode( ',', $formatted )[0]; // Get first part before comma
						}
						
						$all_places[] = array(
							'id'        => $place_id,
							'name'      => $name,
							'formatted' => $formatted,
							'lat'       => isset( $place['geometry']['location']['lat'] ) ? $place['geometry']['location']['lat'] : null,
							'lon'       => isset( $place['geometry']['location']['lng'] ) ? $place['geometry']['location']['lng'] : null,
							'types'     => $place['types'] ?? array(),
							'postcode'  => $query // Include original postcode
						);
					}
				}
			}
		}

		if ( empty( $all_places ) ) {
			return array(
				'success' => false,
				'message' => 'No places found',
				'data'    => array()
			);
		}

		return array(
			'success' => true,
			'data'    => $all_places
		);
	}

	/**
	 * Get detailed place information
	 * 
	 * @param string $place_id Google Place ID
	 * @return array Place details
	 */
	public static function get_place_details( $place_id ) {
		$api_key = self::get_api_key();
		
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => 'Google Places API key not configured'
			);
		}

		$url = 'https://maps.googleapis.com/maps/api/place/details/json';
		
		$args = array(
			'place_id' => $place_id,
			'key'      => $api_key,
			'fields'   => 'formatted_address,name,address_components,geometry,formatted_phone_number,website'
		);

		$response = wp_remote_get( 
			add_query_arg( $args, $url ),
			array(
				'timeout'   => 10,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false )
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message()
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['result'] ) ) {
			return array(
				'success' => false,
				'message' => 'Place not found'
			);
		}

		return array(
			'success' => true,
			'data'    => $data['result']
		);
	}
}
