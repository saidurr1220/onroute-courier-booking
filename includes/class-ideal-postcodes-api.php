<?php
/**
 * Ideal Postcodes API Handler
 * Returns UK street addresses for postcode lookup (Royal Mail PAF style)
 * Same format as reference: "21a Quarry Road, Headington, Oxford, OX3 8NT"
 *
 * @package OnRoute_Courier_Booking
 * @see https://docs.ideal-postcodes.co.uk/docs/api/postcodes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OCB_Ideal_Postcodes_API {

	/**
	 * Get API key from option
	 */
	public static function get_api_key() {
		return sanitize_text_field( get_option( 'ocb_ideal_postcodes_api_key', '' ) );
	}

	/**
	 * Search addresses by postcode - returns list like "21a Quarry Road, Headington, Oxford, OX3 8NT"
	 *
	 * @param string $postcode UK postcode (e.g. OX3 8NT)
	 * @param int    $page     Page for pagination (postcodes with 100+ addresses)
	 * @return array { success: bool, data: array, message?: string }
	 */
	public static function search_by_postcode( $postcode, $page = 0 ) {
		$api_key = self::get_api_key();
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => 'Ideal Postcodes API key not configured',
				'data'    => array()
			);
		}

		$postcode_clean = strtoupper( preg_replace( '/\s+/', '', trim( $postcode ) ) );
		if ( empty( $postcode_clean ) ) {
			return array( 'success' => false, 'message' => 'Postcode required', 'data' => array() );
		}

		// Format postcode with space (e.g. OX3 8NT) - API is space/case insensitive
		$postcode_formatted = trim( preg_replace( '/\s+/', ' ', $postcode ) );

		$url = 'https://api.ideal-postcodes.co.uk/v1/postcodes/' . rawurlencode( $postcode_formatted );
		$url = add_query_arg( array(
			'api_key' => $api_key,
			'page'    => max( 0, (int) $page ),
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

		if ( $code === 404 ) {
			$msg = isset( $data['message'] ) ? $data['message'] : 'Postcode not found';
			return array( 'success' => false, 'message' => $msg, 'data' => array() );
		}

		if ( $code !== 200 ) {
			$msg = isset( $data['message'] ) ? $data['message'] : 'Address lookup failed';
			return array( 'success' => false, 'message' => $msg, 'data' => array() );
		}

		$result = isset( $data['result'] ) ? $data['result'] : array();
		$addresses = array();

		foreach ( $result as $addr ) {
			$line1 = isset( $addr['line_1'] ) ? trim( $addr['line_1'] ) : '';
			$line2 = isset( $addr['line_2'] ) ? trim( $addr['line_2'] ) : '';
			$line3 = isset( $addr['line_3'] ) ? trim( $addr['line_3'] ) : '';
			$town  = isset( $addr['post_town'] ) ? trim( $addr['post_town'] ) : '';
			$pc    = isset( $addr['postcode'] ) ? trim( $addr['postcode'] ) : $postcode_formatted;

			$parts = array_filter( array( $line1, $line2, $line3, $town ) );
			$formatted = implode( ', ', $parts );
			if ( $pc ) {
				$formatted .= ', ' . $pc;
			}

			if ( ! empty( $formatted ) ) {
				$addresses[] = array(
					'id'        => isset( $addr['udprn'] ) ? (string) $addr['udprn'] : '',
					'name'      => '',
					'formatted' => $formatted,
					'lat'       => isset( $addr['latitude'] ) ? (float) $addr['latitude'] : null,
					'lon'       => isset( $addr['longitude'] ) ? (float) $addr['longitude'] : null,
					'postcode'  => $pc,
					'source'    => 'idealpostcodes',
					'raw'       => $addr,
				);
			}
		}

		return array(
			'success' => true,
			'data'    => $addresses
		);
	}
}
