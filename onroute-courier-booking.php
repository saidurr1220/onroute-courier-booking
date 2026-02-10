<?php
/**
 * Plugin Name: OnRoute Courier Booking
 * Description: Full-featured courier booking system with multi-step quote and booking flow
 * Version: 1.8.1
 * Author: <a href="https://saidur-it.vercel.app" target="_blank">Md. Saidur Rahman</a>
 * Author URI: https://saidur-it.vercel.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: onroute-courier-booking
 * Domain Path: /languages
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'ONROUTE_COURIER_BOOKING_VERSION', '1.8.1' );
define( 'ONROUTE_COURIER_BOOKING_FILE', __FILE__ );
define( 'ONROUTE_COURIER_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'ONROUTE_COURIER_BOOKING_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// INCLUDES
// -------------------------------------------------------------------------

// Core Includes
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-loader.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-session.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-database.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-activator.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-booking.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-pricing.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-distance-matrix.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-promo.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-places-api.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-getaddress-api.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-ideal-postcodes-api.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-business-credit.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-emails.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-dashboard-extensions.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-github-updater.php';

require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-ajax.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-blocks.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-form-builder.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-form-settings.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-payment-settings.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-stripe-api.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-stripe-webhook.php';
// REMOVED: Instant Quote class (disabled 2026-02)
require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-vip-courier.php';
// require_once ONROUTE_COURIER_BOOKING_PATH . 'includes/class-instant-quote.php';

// Admin Includes
if ( is_admin() ) {
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/class-admin.php';
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/class-form-admin.php';
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/class-form-settings-admin.php';
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/class-payment-dashboard.php';
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/class-dashboard-widget.php';
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/dashboard-widget-notice.php';
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/class-data-manager.php';
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/class-business-credit-admin.php';
	require_once ONROUTE_COURIER_BOOKING_PATH . 'admin/class-pod-admin.php';
}

// Public Includes
require_once ONROUTE_COURIER_BOOKING_PATH . 'public/class-frontend.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'public/class-forms.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'public/class-quick-quote.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'public/class-multi-step-form.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'public/class-booking-confirmation.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'public/class-quote-summary.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'public/class-business-credit-public.php';
require_once ONROUTE_COURIER_BOOKING_PATH . 'public/class-vip-landing-page.php';

// -------------------------------------------------------------------------
// HOOKS
// -------------------------------------------------------------------------

// Activation hook
register_activation_hook( __FILE__, array( 'OnRoute_Courier_Booking_Activator', 'activate' ) );

// Deactivation hook
register_deactivation_hook( __FILE__, array( 'OnRoute_Courier_Booking_Activator', 'deactivate' ) );

// Initialize on plugins_loaded
add_action( 'plugins_loaded', 'ocb_init_plugin' );
function ocb_init_plugin() {
	
	// Initialize GitHub Updater
	if ( is_admin() && class_exists( 'OnRoute_Courier_Booking_GitHub_Updater' ) ) {
		new OnRoute_Courier_Booking_GitHub_Updater( 
			__FILE__, 
			'saidurr1220', 
			'onroute-courier-booking' 
		);
	}

	// Check if we need to reset fields cache (after updates)
	$saved_version = get_option( 'ocb_plugin_version', '0.0.0' );
	if ( version_compare( $saved_version, ONROUTE_COURIER_BOOKING_VERSION, '<' ) ) {
		// Plugin was updated, clear fields cache to ensure new fields appear
		if ( class_exists( 'OnRoute_Courier_Booking_Form_Builder' ) ) {
			OnRoute_Courier_Booking_Form_Builder::reset_fields_cache();
		}
		
		// Run database updates
		if ( class_exists( 'OnRoute_Courier_Booking_Database' ) ) {
			OnRoute_Courier_Booking_Database::create_tables();
		}

		update_option( 'ocb_plugin_version', ONROUTE_COURIER_BOOKING_VERSION );
		flush_rewrite_rules();
	}
	
	// Initialize Main Loader (Handles Admin, Assets, etc.)
	if ( class_exists( 'OnRoute_Courier_Booking_Loader' ) ) {
		$loader = new OnRoute_Courier_Booking_Loader();
		$loader->run();
	}

	// Initialize Specific Shortcode Classes
	// These handle [onroute_quick_quote] and [onroute_multi_booking]
	new OnRoute_Courier_Booking_Quick_Quote();
	new OnRoute_Courier_Booking_Multi_Step_Form();
	
	// VIP Courier Form
	if ( class_exists( 'OnRoute_VIP_Courier' ) ) {
		new OnRoute_VIP_Courier();
	}

	// VIP Landing Page
	if ( class_exists( 'OnRoute_VIP_Landing_Page' ) ) {
		new OnRoute_VIP_Landing_Page();
	}
}

/**
 * NOTE: Asset enqueueing is now handled by OnRoute_Courier_Booking_Loader::enqueue_assets
 * This prevents duplicate loading and ensures settings (like API keys) are respected.
 */
