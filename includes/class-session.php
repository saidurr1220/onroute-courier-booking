<?php
/**
 * Session Manager - Handles booking session data
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Session {

	const SESSION_KEY = 'ocb_booking_session';

	/**
	 * Initialize session
	 */
	public static function init() {
		if ( ! session_id() ) {
			session_start();
		}
	}

	/**
	 * Get all session data
	 */
	public static function get_all() {
		self::init();
		if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			$_SESSION[ self::SESSION_KEY ] = array();
		}
		return $_SESSION[ self::SESSION_KEY ];
	}

	/**
	 * Get session value
	 */
	public static function get( $key, $default = null ) {
		self::init();
		$data = self::get_all();
		return isset( $data[ $key ] ) ? $data[ $key ] : $default;
	}

	/**
	 * Set session value
	 */
	public static function set( $key, $value ) {
		self::init();
		if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			$_SESSION[ self::SESSION_KEY ] = array();
		}
		$_SESSION[ self::SESSION_KEY ][ $key ] = $value;
	}

	/**
	 * Set step data
	 */
	public static function set_step( $step, $data ) {
		self::init();
		if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			$_SESSION[ self::SESSION_KEY ] = array();
		}
		if ( ! isset( $_SESSION[ self::SESSION_KEY ]['steps'] ) ) {
			$_SESSION[ self::SESSION_KEY ]['steps'] = array();
		}
		$_SESSION[ self::SESSION_KEY ]['steps'][ $step ] = $data;
	}

	/**
	 * Get step data
	 */
	public static function get_step( $step ) {
		self::init();
		$data = self::get_all();
		return isset( $data['steps'][ $step ] ) ? $data['steps'][ $step ] : array();
	}

	/**
	 * Clear session
	 */
	public static function clear() {
		self::init();
		unset( $_SESSION[ self::SESSION_KEY ] );
	}

	/**
	 * Get booking page URL (page with [onroute_booking_form] shortcode)
	 */
	public static function get_booking_page_url() {
		$booking_page_id = get_option( 'ocb_booking_page_id' );
		if ( $booking_page_id ) {
			return get_permalink( $booking_page_id );
		}
		// Fallback: search for page with shortcode
		$pages = get_posts( array(
			'post_type' => 'page',
			'posts_per_page' => 1,
			's' => 'onroute_booking_form',
		) );

		if ( ! empty( $pages ) ) {
			return get_permalink( $pages[0]->ID );
		}

		return home_url();
	}

	/**
	 * Get quote page URL (page with [onroute_quote_form] shortcode)
	 */
	public static function get_quote_page_url() {
		$quote_page_id = get_option( 'ocb_quote_page_id' );
		if ( $quote_page_id ) {
			return get_permalink( $quote_page_id );
		}
		// Fallback: search for page with shortcode
		$pages = get_posts( array(
			'post_type' => 'page',
			'posts_per_page' => 1,
			's' => 'onroute_quote_form',
		) );

		if ( ! empty( $pages ) ) {
			return get_permalink( $pages[0]->ID );
		}

		return home_url();
	}
}
