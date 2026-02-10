<?php
/**
 * Business Credit management class
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Business_Credit {

	/**
	 * Get business account by user ID
	 *
	 * @param int $user_id User ID.
	 * @return object|null Account object or null if not found.
	 */
	public static function get_account_by_user( $user_id ) {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_business_accounts_table();
		// Order by ID DESC to ensure we get the latest if duplicates exist, though we should avoid them
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT 1", $user_id ) );
	}

	/**
	 * Get business account by ID
	 *
	 * @param int $id Account ID.
	 * @return object|null Account object or null if not found.
	 */
	public static function get_account( $id ) {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_business_accounts_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	/**
	 * Get all business accounts
	 *
	 * @return array List of accounts.
	 */
	public static function get_all_accounts() {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_business_accounts_table();
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
	}

	/**
	 * Create or update business account
	 *
	 * @param array $data Account data.
	 * @return int|false Account ID or false on failure.
	 */
	public static function save_account( $data ) {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_business_accounts_table();

		if ( isset( $data['id'] ) && ! empty( $data['id'] ) ) {
			$id = $data['id'];
			unset( $data['id'] );
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			self::clear_cache( $id );
			return $id;
		} else {
			$wpdb->insert( $table, $data );
			$new_id = $wpdb->insert_id;
			self::clear_cache( $new_id );
			return $new_id;
		}
	}

	/**
	 * Update account status
	 *
	 * @param int    $id Account ID.
	 * @param string $status New status.
	 * @param int    $admin_id Admin user ID.
	 * @return bool True on success, false on failure.
	 */
	public static function update_status( $id, $status, $admin_id ) {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_business_accounts_table();
		
		$data = array(
			'account_status' => $status,
			'updated_at'     => current_time( 'mysql' ),
		);

		if ( $status === 'approved' ) {
			$data['approved_by'] = $admin_id;
			$data['approved_at'] = current_time( 'mysql' );
		}

		$updated = $wpdb->update( $table, $data, array( 'id' => $id ) ) !== false;
		if ( $updated ) {
			self::clear_cache( $id );
		}
		return $updated;
	}

	/**
	 * Adjust account balance
	 *
	 * @param int    $id Account ID.
	 * @param float  $amount Amount to add (positive) or subtract (negative).
	 * @return bool True on success, false on failure.
	 */
	public static function adjust_balance( $id, $amount ) {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_business_accounts_table();
		
		$account = self::get_account( $id );
		if ( ! $account ) {
			return false;
		}

		$new_balance = (float) $account->current_balance + (float) $amount;

		$updated = $wpdb->update( 
			$table, 
			array( 
				'current_balance' => $new_balance,
				'updated_at'      => current_time( 'mysql' ),
			), 
			array( 'id' => $id ) 
		) !== false;

		if ( $updated ) {
			self::clear_cache( $id );
		}
		return $updated;
	}

	/**
	 * Clear cache for a business account
	 * 
	 * @param int $id Account ID or User ID.
	 */
	public static function clear_cache( $id ) {
		// Clear WordPress Object Cache if exists
		wp_cache_delete( 'ocb_account_' . $id, 'onroute_couriers' );
		
		// If LiteSpeed Cache is active, purge the site/dashboard
		if ( function_exists( 'litespeed_purge_all' ) ) {
			// Instead of purging all, we can try to purge just the current user or transients
			// For simplicity and to solve the user's issue, we can trigger a purge
			do_action( 'litespeed_purge_all' );
		}
	}

	/**
	 * Check if account is eligible for credit booking
	 *
	 * @param int $user_id User ID.
	 * @return bool True if eligible.
	 */
	public static function is_eligible_for_credit( $user_id ) {
		$account = self::get_account_by_user( $user_id );
		if ( ! $account ) {
			return false;
		}

		if ( $account->account_status !== 'approved' ) {
			return false;
		}

		// Check if current balance has reached or exceeded credit limit
		if ( (float) $account->current_balance >= (float) $account->credit_limit ) {
			return false;
		}

		return true;
	}
}
