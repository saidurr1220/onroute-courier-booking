<?php
/**
 * Dashboard Widget and Version Info
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Dashboard_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_init', array( $this, 'add_version_info_section' ) );
	}

	/**
	 * Register dashboard widget
	 */
	public function register_dashboard_widget() {
		wp_add_dashboard_widget(
			'ocb_booking_stats',
			'📍 OnRoute Courier Booking Stats',
			array( $this, 'render_booking_widget' )
		);
	}

	/**
	 * Render booking widget on dashboard
	 */
	public function render_booking_widget() {
		global $wpdb;

		// Get booking statistics
		$table_name = $wpdb->prefix . 'ocb_bookings';
		$total_bookings = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$today_bookings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = %s",
				date( 'Y-m-d' )
			)
		);

		// Get pending bookings
		$pending_bookings = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending'"
		);

		// Get completed bookings
		$completed_bookings = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE status = 'completed'"
		);

		// Get total revenue
		$total_revenue = $wpdb->get_var(
			"SELECT SUM(total_price) FROM {$table_name} WHERE status = 'completed'"
		);

		echo '<div style="padding: 20px;">';
		echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';

		// Total Bookings
		echo '<div style="background: #f0f6fc; padding: 15px; border-radius: 8px; border-left: 4px solid #0073aa;">';
		echo '<p style="margin: 0; color: #666; font-size: 12px; text-transform: uppercase;">Total Bookings</p>';
		echo '<h3 style="margin: 5px 0 0 0; color: #0073aa; font-size: 28px;">' . intval( $total_bookings ) . '</h3>';
		echo '</div>';

		// Today's Bookings
		echo '<div style="background: #fff8e5; padding: 15px; border-radius: 8px; border-left: 4px solid #ffb900;">';
		echo '<p style="margin: 0; color: #666; font-size: 12px; text-transform: uppercase;">Today</p>';
		echo '<h3 style="margin: 5px 0 0 0; color: #ffb900; font-size: 28px;">' . intval( $today_bookings ) . '</h3>';
		echo '</div>';

		// Pending Bookings
		echo '<div style="background: #fff5f5; padding: 15px; border-radius: 8px; border-left: 4px solid #ff6b6b;">';
		echo '<p style="margin: 0; color: #666; font-size: 12px; text-transform: uppercase;">Pending</p>';
		echo '<h3 style="margin: 5px 0 0 0; color: #ff6b6b; font-size: 28px;">' . intval( $pending_bookings ) . '</h3>';
		echo '</div>';

		// Completed Bookings
		echo '<div style="background: #f0fdf4; padding: 15px; border-radius: 8px; border-left: 4px solid #22c55e;">';
		echo '<p style="margin: 0; color: #666; font-size: 12px; text-transform: uppercase;">Completed</p>';
		echo '<h3 style="margin: 5px 0 0 0; color: #22c55e; font-size: 28px;">' . intval( $completed_bookings ) . '</h3>';
		echo '</div>';

		echo '</div>';

		// Revenue Section
		if ( $total_revenue ) {
			echo '<div style="background: #f0fdf4; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
			echo '<p style="margin: 0; color: #666; font-size: 12px; text-transform: uppercase;">Total Revenue (Completed)</p>';
			echo '<h3 style="margin: 5px 0 0 0; color: #22c55e; font-size: 28px;">£' . number_format( (float) $total_revenue, 2 ) . '</h3>';
			echo '</div>';
		}

		// Quick Links
		echo '<div style="border-top: 1px solid #ddd; padding-top: 15px;">';
		echo '<p style="margin: 0 0 10px 0;"><strong>Quick Links:</strong></p>';
		echo '<a href="' . admin_url( 'admin.php?page=ocb-bookings' ) . '" class="button button-primary" style="margin-right: 10px;">📋 View All Bookings</a>';
		echo '<a href="' . admin_url( 'admin.php?page=ocb-dashboard' ) . '" class="button">📊 Dashboard</a>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Add version info section to settings
	 */
	public function add_version_info_section() {
		add_settings_section(
			'ocb_version_info',
			'📦 Plugin Version Information',
			array( $this, 'render_version_info' ),
			'ocb_settings_page'
		);
	}

	/**
	 * Render version information
	 */
	public function render_version_info() {
		$version = defined( 'ONROUTE_COURIER_BOOKING_VERSION' ) ? ONROUTE_COURIER_BOOKING_VERSION : 'Unknown';

		echo '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0;">';
		echo '<h3>Current Version: <span style="color: #0073aa;">v' . esc_html( $version ) . '</span></h3>';

		echo '<h4>✅ Included Features in v' . esc_html( $version ) . ':</h4>';
		echo '<ul style="list-style: none; padding: 0;">';

		$features = array(
			'Multi-step booking form with real-time quotes',
			'Quick quote functionality',
			'Distance Matrix API integration (OpenRoute & Google Maps)',
			'Dynamic pricing based on distance and vehicle type',
			'Promo code system',
			'Booking confirmation and email notifications',
			'Admin dashboard with booking management',
			'Elementor block support',
			'Gutenberg block support',
			'Advanced form builder',
			'Session management',
			'Database logging for all bookings',
			'API status checking',
			'Responsive design',
			'Performance optimization',
		);

		foreach ( $features as $feature ) {
			echo '<li style="padding: 8px 0; color: #27ae60; font-weight: 500;">✓ ' . esc_html( $feature ) . '</li>';
		}

		echo '</ul>';

		echo '<h4>📝 What\'s New in Recent Updates:</h4>';
		echo '<ul style="list-style: disc; padding-left: 20px; color: #666;">';
		echo '<li>Enhanced error handling with better logging</li>';
		echo '<li>Improved AJAX error responses</li>';
		echo '<li>Dashboard widget for booking statistics</li>';
		echo '<li>Better API configuration guidance</li>';
		echo '<li>Performance improvements</li>';
		echo '</ul>';

		echo '<h4>ℹ️ System Information:</h4>';
		echo '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
		echo '<tr style="background: #f5f5f5;">';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>Plugin Version</strong></td>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html( $version ) . '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>WordPress Version</strong></td>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html( get_bloginfo( 'version' ) ) . '</td>';
		echo '</tr>';
		echo '<tr style="background: #f5f5f5;">';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>PHP Version</strong></td>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html( phpversion() ) . '</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>Plugin Status</strong></td>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><span style="color: #22c55e; font-weight: bold;">✓ Active</span></td>';
		echo '</tr>';
		echo '<tr style="background: #f5f5f5;">';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><strong>Author</strong></td>';
		echo '<td style="padding: 10px; border: 1px solid #ddd;"><a href="https://saidur-it.vercel.app" target="_blank" style="color: #0073aa; text-decoration: none;">👨‍💻 Md. Saidur Rahman</a></td>';
		echo '</tr>';
		echo '</table>';

		echo '</div>';
	}
}
