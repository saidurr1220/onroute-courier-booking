<?php
/**
 * Uninstall OnRoute Courier Booking
 * 
 * This file runs when the plugin is deleted from WordPress admin.
 * It removes all plugin data, options, and database tables.
 *
 * @package OnRoute_Courier_Booking
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Check if user wants to delete data
if ( get_option( 'ocb_delete_data_on_uninstall' ) ) {

    // Delete all plugin options
    $options_to_delete = array(
        'ocb_plugin_activated',
        'ocb_version',
        'ocb_vat_rate',
        'ocb_services',
        'ocb_vehicles',
        'ocb_google_maps_api_key',
        'ocb_ideal_postcodes_api_key',
        'ocb_getaddress_io_api_key',
        'ocb_fallback_distance',
        'ocb_booking_page_id',
        'ocb_quote_page_id',
        'ocb_delete_data_on_uninstall'
    );

    foreach ( $options_to_delete as $option ) {
        delete_option( $option );
    }

    // Drop custom database tables
    $tables_to_drop = array(
        $wpdb->prefix . 'ocb_bookings',
        $wpdb->prefix . 'ocb_promo_codes',
    );

    foreach ( $tables_to_drop as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }

    // Clear any transients
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_ocb_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_ocb_%'" );
}

// Clear rewrite rules
flush_rewrite_rules();
