<?php
/**
 * Dashboard Widget Setup Notification
 *
 * @package OnRoute_Courier_Booking
 */

/**
 * Show setup notice on first activation
 */
function ocb_dashboard_widget_setup_notice() {
	if ( ! get_option( 'ocb_dashboard_widget_setup_notice' ) ) {
		echo '<div class="notice notice-success is-dismissible">';
		echo '<h3>✨ Dashboard Widget Activated!</h3>';
		echo '<p><strong>Good news:</strong> Your booking statistics widget has been added to the WordPress Dashboard.</p>';
		echo '<p><strong>Where to find it:</strong></p>';
		echo '<ul style="margin-left: 20px;">';
		echo '<li>📊 Go to <strong>Dashboard → Home</strong> and scroll down</li>';
		echo '<li>📦 Version info available at <strong>Courier Booking → Settings</strong></li>';
		echo '</ul>';
		echo '<p>';
		echo '<a href="' . esc_url( admin_url( 'index.php' ) ) . '" class="button button-primary">Go to Dashboard</a> ';
		echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'ocb_dismiss_setup', '1' ), 'ocb_dismiss_setup' ) ) . '" class="button">Dismiss</a>';
		echo '</p>';
		echo '</div>';

		// Mark notice as shown
		if ( isset( $_GET['ocb_dismiss_setup'] ) && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ocb_dismiss_setup' ) ) {
			update_option( 'ocb_dashboard_widget_setup_notice', 1 );
		}
	}
}

// Add notice only once after first installation
if ( is_admin() ) {
	add_action( 'admin_notices', 'ocb_dashboard_widget_setup_notice', 5 );
}
