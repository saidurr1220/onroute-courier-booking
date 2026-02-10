<?php
/**
 * Plugin activation/deactivation class
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Activator {

	/**
	 * Activate plugin
	 */
	public static function activate() {
		// Create database tables
		if ( class_exists( 'OnRoute_Courier_Booking_Database' ) ) {
			OnRoute_Courier_Booking_Database::create_tables();
		}

		// Set default options
		update_option( 'ocb_plugin_activated', true );
		update_option( 'ocb_version', ONROUTE_COURIER_BOOKING_VERSION );

		if ( ! get_option( 'ocb_vat_rate' ) ) {
			update_option( 'ocb_vat_rate', 20 );
		}

		if ( ! get_option( 'ocb_services' ) || self::has_legacy_services() ) {
			$default_services = array(
				array(
					'id' => 'same_day',
					'name' => 'Same Day',
					'multiplier' => 1.0,
					'active' => 1,
				),
				array(
					'id' => 'priority',
					'name' => 'Priority',
					'multiplier' => 1.5,
					'active' => 1,
				),
				array(
					'id' => 'direct',
					'name' => 'Direct',
					'multiplier' => 2.0,
					'active' => 1,
				),
			);
			update_option( 'ocb_services', $default_services );
		}

		if ( ! get_option( 'ocb_vehicles' ) ) {
			$default_vehicles = array(
				array(
					'id' => 'motorbike',
					'name' => 'Motorbike',
					'base_price' => 24.67,
					'max_weight' => 5,
					'active' => 1,
				),
				array(
					'id' => 'small_van',
					'name' => 'Small Van',
					'base_price' => 49.06,
					'max_weight' => 400,
					'active' => 1,
				),
				array(
					'id' => 'mwb',
					'name' => 'Medium Van',
					'base_price' => 78.53,
					'max_weight' => 800,
					'active' => 1,
				),
				array(
					'id' => 'lwb',
					'name' => 'Large Van',
					'base_price' => 92.76,
					'max_weight' => 1100,
					'active' => 1,
				),
			);
			update_option( 'ocb_vehicles', $default_vehicles );
		}

		flush_rewrite_rules();
	}

	/**
	 * Check for legacy services
	 */
	private static function has_legacy_services() {
		$services = get_option( 'ocb_services', array() );
		foreach ( $services as $service ) {
			if ( $service['id'] === 'next_day' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Deactivate plugin
	 */
	public static function deactivate() {
		delete_option( 'ocb_plugin_activated' );
		flush_rewrite_rules();
	}
}
