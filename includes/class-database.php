<?php
/**
 * Database management class
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Database {

	/**
	 * Get bookings table name
	 */
	public static function get_bookings_table() {
		global $wpdb;
		return $wpdb->prefix . 'ocb_bookings';
	}

	/**
	 * Get business accounts table name
	 */
	public static function get_business_accounts_table() {
		global $wpdb;
		return $wpdb->prefix . 'onroute_business_accounts';
	}

	/**
	 * Get promo codes table name
	 */
	public static function get_promos_table() {
		global $wpdb;
		return $wpdb->prefix . 'ocb_promo_codes';
	}

	/**
	 * Get email logs table name
	 */
	public static function get_email_logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'ocb_email_logs';
	}

	/**
	 * Create tables
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Bookings table
		$bookings_table = self::get_bookings_table();
		$bookings_sql = "CREATE TABLE $bookings_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			booking_reference varchar(50) NOT NULL,
			customer_name varchar(100) DEFAULT NULL,
			customer_email varchar(100) NOT NULL,
			customer_phone varchar(20) NOT NULL,
			pickup_address text NOT NULL,
			pickup_postcode varchar(20) NOT NULL,
			delivery_address text NOT NULL,
			delivery_postcode varchar(20) NOT NULL,
			collection_date date NOT NULL,
			collection_time time NOT NULL,
			delivery_date date NOT NULL,
			delivery_time time NOT NULL,
			vehicle_id varchar(50) NOT NULL,
			service_id varchar(50) NOT NULL,
			base_price decimal(10,2) NOT NULL,
			vat_amount decimal(10,2) NOT NULL,
			discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			total_price decimal(10,2) NOT NULL,
			promo_code varchar(50) DEFAULT NULL,
			status varchar(50) DEFAULT 'pending',
			payment_status varchar(50) DEFAULT 'unpaid',
			payment_mode varchar(50) DEFAULT 'none',
			amount_paid decimal(10,2) NOT NULL DEFAULT 0.00,
			payment_method varchar(50) DEFAULT NULL,
			stripe_payment_id varchar(100) DEFAULT NULL,
			collected_by_name varchar(100) DEFAULT NULL,
			collection_signature longtext DEFAULT NULL,
			collected_at datetime DEFAULT NULL,
			delivered_to_name varchar(100) DEFAULT NULL,
			delivery_signature longtext DEFAULT NULL,
			delivered_at datetime DEFAULT NULL,
			notes longtext DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00',
			updated_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY booking_reference (booking_reference),
			KEY user_id (user_id),
			KEY customer_email (customer_email),
			KEY collection_date (collection_date),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $bookings_sql );

		// Promo codes table
		$promos_table = self::get_promos_table();
		$promos_sql = "CREATE TABLE $promos_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code varchar(50) NOT NULL,
			type varchar(20) NOT NULL,
			value decimal(10,2) NOT NULL,
			expiry_date date DEFAULT NULL,
			max_uses int(11) DEFAULT NULL,
			times_used int(11) DEFAULT 0,
			active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) $charset_collate;";

		dbDelta( $promos_sql );

		// Business accounts table
		$business_accounts_table = self::get_business_accounts_table();
		$business_accounts_sql = "CREATE TABLE $business_accounts_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			company_name varchar(255) NOT NULL,
			credit_limit decimal(10,2) NOT NULL DEFAULT 0.00,
			current_balance decimal(10,2) NOT NULL DEFAULT 0.00,
			account_status varchar(20) NOT NULL DEFAULT 'pending',
			approved_by bigint(20) UNSIGNED DEFAULT NULL,
			approved_at datetime DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00',
			updated_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY account_status (account_status)
		) $charset_collate;";

		dbDelta( $business_accounts_sql );

		// Email logs table
		$email_logs_table = self::get_email_logs_table();
		$email_logs_sql = "CREATE TABLE $email_logs_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) UNSIGNED DEFAULT NULL,
			recipient_email varchar(100) NOT NULL,
			subject varchar(255) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			error_message text DEFAULT NULL,
			sent_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY recipient_email (recipient_email)
		) $charset_collate;";

		dbDelta( $email_logs_sql );

		// Extension tables (saved locations, support tickets, invoices)
		if ( class_exists( 'OnRoute_Dashboard_Extensions' ) ) {
			OnRoute_Dashboard_Extensions::create_tables();
		}
	}

	/**
	 * Drop tables
	 */
	public static function drop_tables() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS " . self::get_bookings_table() );
		$wpdb->query( "DROP TABLE IF EXISTS " . self::get_promos_table() );
	}
}
