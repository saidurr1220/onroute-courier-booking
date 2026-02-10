<?php
/**
 * Simple IP-based Rate Limiter
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Courier_Booking_Rate_Limiter {

	/**
	 * Check if action is allowed for IP and log usage
	 * Returns true if allowed, false if limit exceeded
	 *
	 * @param string $action  Action name
	 * @param int    $limit   Max allows requests
	 * @param int    $seconds Time window in seconds
	 * @return bool
	 */
	public static function check_and_log( $action, $limit = 20, $seconds = 60 ) {
		// Bypass for logged in admins
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$ip = $_SERVER['REMOTE_ADDR'];
		
		// Use fixed time windows for performance
		$window = floor( time() / $seconds );
		$key = 'ocb_rl_' . $action . '_' . md5( $ip ) . '_' . $window;

		$count = get_transient( $key );

		if ( $count === false ) {
			$count = 0;
			set_transient( $key, 1, $seconds ); // Start count at 1
			return true;
		}

		if ( $count >= $limit ) {
			return false; // Limit exceeded
		}

		// Increment
		set_transient( $key, $count + 1, $seconds );
		return true;
	}
}
