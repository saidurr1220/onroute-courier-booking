<?php
/**
 * GetAddress.io API Handler
 * Returns UK street addresses for postcode lookup (Royal Mail style)
 * Used for address dropdown when "Select address" is clicked
 *
 * @package OnRoute_Courier_Booking
 * @see https://documentation.getaddress.io/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OCB_GetAddress_API {

	/**
	 * Get API key from option
	 */
	public static function get_api_key() {
		return sanitize_text_field( get_option( 'ocb_getaddress_io_api_key', '' ) );
	}

	/**
	 * Search addresses by postcode - returns list like "21a Quarry Road, Headington, Oxford"
	 *
	 * @param string $postcode UK postcode (e.g. OX3 8NT)
	 * @return array { success: bool, data: array, message?: string }
	 */
	public static function search_by_postcode( $postcode ) {
		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => 'GetAddress.io API key not configured',
				'data'    => array()
			);
		}

		$postcode_clean = strtoupper( preg_replace( '/\s+/', '', $postcode ) );
		$postcode_formatted = trim( $postcode );
		if ( empty( $postcode_clean ) ) {
			return array( 'success' => false, 'message' => 'Postcode required', 'data' => array() );
		}

		$url = 'https://api.getaddress.io/autocomplete/' . rawurlencode( $postcode_formatted );
		$url = add_query_arg( array(
			'api-key' => $api_key,
			'all'     => 'true',
		), $url );

		$response = wp_remote_get( $url, array(
			'timeout'   => 10,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false )
		) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
				'data'    => array()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$msg = isset( $data['Message'] ) ? $data['Message'] : 'Address lookup failed';
			return array( 'success' => false, 'message' => $msg, 'data' => array() );
		}

		$suggestions = isset( $data['suggestions'] ) ? $data['suggestions'] : array();
		$addresses = array();

		foreach ( $suggestions as $i => $s ) {
			$addr = isset( $s['address'] ) ? $s['address'] : '';
			$id   = isset( $s['id'] ) ? $s['id'] : '';
			if ( ! empty( $addr ) ) {
				$addresses[] = array(
					'id'        => $id,
					'name'      => '',
					'formatted' => $addr . ', ' . $postcode_formatted,
					'lat'       => null,
					'lon'       => null,
					'postcode'  => $postcode_formatted,
					'source'    => 'getaddress'
				);
			}
		}

		return array(
			'success' => true,
			'data'    => $addresses
		);
	}

	/**
	 * Get full address details by GetAddress.io id (for form population)
	 *
	 * @param string $getaddress_id
	 * @return array { success: bool, data?: array, message?: string }
	 */
	public static function get_address_details( $getaddress_id ) {
		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			return array( 'success' => false, 'message' => 'GetAddress.io API key not configured' );
		}

		$url = 'https://api.getAddress.io/get/' . rawurlencode( $getaddress_id );
		$url = add_query_arg( 'api-key', $api_key, $url );

		$response = wp_remote_get( $url, array(
			'timeout'   => 10,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false )
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 || empty( $data ) ) {
			return array( 'success' => false, 'message' => 'Address not found' );
		}

		$fa = isset( $data['formatted_address'] ) ? $data['formatted_address'] : array();
		$line1 = isset( $fa[0] ) ? $fa[0] : '';
		$line2 = isset( $fa[1] ) ? $fa[1] : '';
		$line3 = isset( $fa[2] ) ? $fa[2] : '';
		$line4 = isset( $fa[3] ) ? $fa[3] : '';
		$town  = isset( $data['town_or_city'] ) ? $data['town_or_city'] : ( isset( $fa[3] ) ? $fa[3] : '' );
		$county = isset( $data['county'] ) ? $data['county'] : ( isset( $fa[4] ) ? $fa[4] : '' );
		$postcode = isset( $data['postcode'] ) ? $data['postcode'] : '';

		$address_parts = array_filter( array( $line1, $line2, $line3, $line4 ) );
		$address_line1 = ! empty( $address_parts ) ? array_shift( $address_parts ) : '';
		$address_line2 = implode( ', ', array_filter( $address_parts ) );

		return array(
			'success' => true,
			'data'    => array(
				'address_components' => array(
					array( 'long_name' => $line1, 'types' => array( 'street_number', 'route' ) ),
					array( 'long_name' => $town, 'types' => array( 'locality', 'postal_town' ) ),
					array( 'long_name' => $postcode, 'types' => array( 'postal_code' ) ),
				),
				'formatted_address'  => trim( $line1 . ', ' . $line2 . ', ' . $line3 . ', ' . $town . ', ' . $county . ', ' . $postcode, ', ' ),
				'name'               => '',
				'address_line1'      => $address_line1,
				'address_line2'      => $address_line2,
				'city'               => $town,
				'postcode'           => $postcode,
			)
		);
	}
}
