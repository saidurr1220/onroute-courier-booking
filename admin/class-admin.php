<?php
/**
 * Admin settings class
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'api_setup_notice' ) );

		// Load form settings page
		new OnRoute_Courier_Booking_Form_Admin_Settings();

		// Load data manager (backup/restore)
		new OnRoute_Courier_Booking_Data_Manager();
	}

	/**
	 * Show admin notice if API is not configured
	 */
	public function api_setup_notice() {
		// Only show on Courier Booking pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'ocb-' ) === false ) {
			return;
		}

		$provider = get_option( 'ocb_distance_provider', 'openroute' );
		$ors_key = get_option( 'ocb_openroute_api_key', '' );
		$google_key = get_option( 'ocb_google_maps_api_key', '' );

		$api_configured = false;
		if ( $provider === 'openroute' && ! empty( $ors_key ) ) {
			$api_configured = true;
		} elseif ( $provider === 'google' && ! empty( $google_key ) ) {
			$api_configured = true;
		}

		if ( ! $api_configured ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<h3>⚠️ OnRoute Courier Booking: API Configuration Required</h3>
				<p><strong>Your booking form won't show dynamic pricing without an API key configured.</strong></p>
				<p>
					To fix this issue:
				</p>
				<ol>
					<li>Get a <strong>free API key</strong> from <a href="https://openrouteservice.org/dev/#/signup" target="_blank">OpenRouteService</a> (2,000 requests/day free)</li>
					<li>Go to <a href="<?php echo admin_url( 'admin.php?page=ocb-settings' ); ?>">Courier Booking → Settings</a></li>
					<li>Paste your API key in the <strong>"OpenRouteService API Key"</strong> field</li>
					<li>Click <strong>"Save All Settings"</strong></li>
				</ol>
				<p>
					<a href="<?php echo admin_url( 'admin.php?page=ocb-settings' ); ?>" class="button button-primary">Configure API Settings Now</a>
					<a href="<?php echo plugins_url( 'API-SETUP-INSTRUCTIONS.md', dirname( __FILE__ ) ); ?>" class="button" target="_blank">View Setup Instructions</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Add admin menu items
	 */
	public function add_admin_menu() {
		add_menu_page(
			'OnRoute Courier Booking',
			'Courier Booking',
			'manage_options',
			'ocb-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-location'
		);

		add_submenu_page(
			'ocb-dashboard',
			'Booking History',
			'Booking History',
			'manage_options',
			'ocb-bookings',
			array( $this, 'render_bookings' )
		);

		add_submenu_page(
			'ocb-dashboard',
			'Enquiries',
			'Enquiries',
			'manage_options',
			'ocb-enquiries',
			array( $this, 'render_enquiries' )
		);

		add_submenu_page(
			'ocb-dashboard',
			'Promo Codes',
			'Promo Codes',
			'manage_options',
			'ocb-promos',
			array( $this, 'render_promos' )
		);

		add_submenu_page(
			'ocb-dashboard',
			'Settings',
			'Settings',
			'manage_options',
			'ocb-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'ocb-dashboard',
			'Tools & Logs',
			'Tools',
			'manage_options',
			'ocb-tools',
			array( $this, 'render_tools_page' )
		);
	}

	/**
	 * Render Tools Page (Consolidated Logs, Backup, etc)
	 */
	public function render_tools_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'logs';
		?>
		<div class="wrap ocb-admin">
			<h1 class="wp-heading-inline">OnRoute Courier Booking - Tools</h1>
			<hr class="wp-header-end">

			<h2 class="nav-tab-wrapper" style="margin-top: 20px;">
				<a href="?page=ocb-tools&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">Email Logs</a>
				<a href="?page=ocb-tools&tab=backup" class="nav-tab <?php echo $active_tab === 'backup' ? 'nav-tab-active' : ''; ?>">Backup & Restore</a>
				<a href="?page=ocb-tools&tab=status" class="nav-tab <?php echo $active_tab === 'status' ? 'nav-tab-active' : ''; ?>">System Status</a>
			</h2>

			<div class="ocb-tools-content" style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
				<?php 
				if ( $active_tab === 'logs' ) {
					$this->render_email_logs_content(); // Use content-only method
				} elseif ( $active_tab === 'backup' ) {
					if ( class_exists( 'OnRoute_Courier_Booking_Data_Manager' ) ) {
						$data_manager = new OnRoute_Courier_Booking_Data_Manager();
						$data_manager->render_data_manager_only();
					} else {
						echo '<p>Data Manager class not found.</p>';
					}
				} elseif ( $active_tab === 'status' ) {
					$this->render_system_status();
				}
				?>
			</div>
		</div>
		<style>
			.nav-tab-active { background: #fff; border-bottom: 1px solid #fff; }
		</style>
		<?php
	}

	/**
	 * Render system status
	 */
	private function render_system_status() {
		global $wpdb;
		?>
		<h3>System Diagnostics</h3>
		<table class="widefat striped">
			<tbody>
				<tr>
					<td><strong>Plugin Version</strong></td>
					<td><?php echo defined( 'ONROUTE_COURIER_BOOKING_VERSION' ) ? ONROUTE_COURIER_BOOKING_VERSION : 'Unknown'; ?></td>
				</tr>
				<tr>
					<td><strong>PHP Version</strong></td>
					<td><?php echo phpversion(); ?></td>
				</tr>
				<tr>
					<td><strong>Database Tables</strong></td>
					<td>
						<?php
						$tables = array( 'ocb_bookings', 'ocb_promo_codes', 'ocb_email_logs', 'ocb_saved_locations', 'ocb_support_tickets', 'ocb_invoices' );
						foreach ( $tables as $t ) {
							$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->prefix . $t ) );
							echo '<span style="color:' . ( $exists ? 'green' : 'red' ) . ';">' . ( $exists ? '✓ ' : '✗ ' ) . esc_html( $t ) . '</span><br>';
						}
						?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render dashboard
	 */
	public function render_dashboard() {
		$booking = new OnRoute_Courier_Booking_Booking();
		$total_bookings = $booking->count_all();

		global $wpdb;
		$paid_bookings = $wpdb->get_var(
			"SELECT COUNT(*) FROM " . OnRoute_Courier_Booking_Database::get_bookings_table() . " WHERE payment_status = 'paid'"
		);

		$total_revenue = $wpdb->get_var(
			"SELECT SUM(total_price) FROM " . OnRoute_Courier_Booking_Database::get_bookings_table() . " WHERE payment_status = 'paid'"
		);

		?>
		<div class="wrap ocb-admin">
			<h1>OnRoute Courier Booking - Dashboard</h1>

			<div class="ocb-stats">
				<div class="stat-card">
					<h3>Total Bookings</h3>
					<p class="stat-value"><?php echo esc_html( $total_bookings ); ?></p>
				</div>
				<div class="stat-card">
					<h3>Paid Bookings</h3>
					<p class="stat-value"><?php echo esc_html( $paid_bookings ); ?></p>
				</div>
				<div class="stat-card">
					<h3>Total Revenue</h3>
					<p class="stat-value">£<?php echo esc_html( number_format( $total_revenue, 2 ) ); ?></p>
				</div>
			</div>

			<div class="ocb-quick-links">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocb-bookings' ) ); ?>" class="button button-primary">View All Bookings</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocb-promos' ) ); ?>" class="button button-primary">Manage Promo Codes</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocb-settings' ) ); ?>" class="button button-primary">Settings</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocb-business-credit' ) ); ?>" class="button button-primary" style="background-color: #28a745; border-color: #28a745;">Business Credit</a>
			</div>

			<div style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa; border-radius: 4px;">
				<p style="margin: 0; color: #666; font-size: 13px;">
					<strong>OnRoute Courier Booking</strong> • Developed by <a href="https://saidur-it.vercel.app" target="_blank" style="color: #0073aa; text-decoration: none;">👨‍💻 Md. Saidur Rahman</a>
					<br><span style="font-size: 12px; color: #999;">A professional courier booking solution for WordPress</span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render bookings page
	 */
	public function render_bookings() {
		// Handle status & delivery time update
		if ( isset( $_POST['ocb_update_status'] ) && check_admin_referer( 'ocb_status_nonce' ) ) {
			$booking_id = intval( $_POST['booking_id'] );
			$new_status = sanitize_text_field( $_POST['new_status'] );
			$payment_status = sanitize_text_field( $_POST['payment_status'] );
			
			// Admin Override Fields
			$new_delivery_time = sanitize_text_field( $_POST['delivery_time'] ?? '' );
			$new_window_start = sanitize_text_field( $_POST['delivery_window_start'] ?? '' );
			$new_window_end = sanitize_text_field( $_POST['delivery_window_end'] ?? '' );

			// Collection Tracking
			$collected_by_name = sanitize_text_field( $_POST['collected_by_name'] ?? '' );
			$collection_signature = $_POST['collection_signature'] ?? '';
			
			// Delivery Tracking
			$delivered_to_name = sanitize_text_field( $_POST['delivered_to_name'] ?? '' );
			$delivery_signature = $_POST['delivery_signature'] ?? '';

			global $wpdb;
			$table = $wpdb->prefix . 'ocb_bookings';
			$old_booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );
			$old_notes = json_decode( $old_booking->notes, true );
			if ( ! is_array( $old_notes ) ) {
				$old_notes = array();
			}
			
			$update_data = array( 
				'status' => $new_status, 
				'payment_status' => $payment_status 
			);

			// Handle Collection Save
			if ( ! empty( $collected_by_name ) && empty( $old_booking->collected_by_name ) ) {
				$update_data['collected_by_name'] = $collected_by_name;
				$update_data['collection_signature'] = $collection_signature;
				$update_data['collected_at'] = current_time( 'mysql' );
				$update_data['status'] = 'collected';
			}

			// Handle Delivery Save
			if ( ! empty( $delivered_to_name ) && empty( $old_booking->delivered_to_name ) ) {
				$update_data['delivered_to_name'] = $delivered_to_name;
				$update_data['delivery_signature'] = $delivery_signature;
				$update_data['delivered_at'] = current_time( 'mysql' );
				$update_data['status'] = 'completed';
			}

			$time_changed = false;
			$change_details = array();

			// Handle Exactly Delivery Time (Priority)
			if ( ! empty( $new_delivery_time ) && $new_delivery_time !== date( 'H:i', strtotime( $old_booking->delivery_time ) ) ) {
				$update_data['delivery_time'] = $new_delivery_time;
				$change_details['Delivery Time'] = array(
					'old' => date( 'H:i', strtotime( $old_booking->delivery_time ) ),
					'new' => $new_delivery_time
				);
				$time_changed = true;
			}

			// Handle Windows (Same Day / Direct)
			$new_notes = $old_notes;
			if ( $new_window_start !== ( $old_notes['delivery_window_start'] ?? '' ) ) {
				$new_notes['delivery_window_start'] = $new_window_start;
				$change_details['Delivery Window Start'] = array(
					'old' => $old_notes['delivery_window_start'] ?? 'Not set',
					'new' => $new_window_start
				);
				$time_changed = true;
			}
			if ( $new_window_end !== ( $old_notes['delivery_window_end'] ?? '' ) ) {
				$new_notes['delivery_window_end'] = $new_window_end;
				$change_details['Delivery Window End'] = array(
					'old' => $old_notes['delivery_window_end'] ?? 'Not set',
					'new' => $new_window_end
				);
				$time_changed = true;
			}

			if ( $time_changed ) {
				$update_data['notes'] = json_encode( $new_notes );
			}

			$wpdb->update( $table, $update_data, array( 'id' => $booking_id ) );

			// Trigger Emails after update
			if ( ! empty( $collected_by_name ) && empty( $old_booking->collected_by_name ) ) {
				OnRoute_Courier_Booking_Emails::send_collection_confirmation( $booking_id );
			}
			if ( ! empty( $delivered_to_name ) && empty( $old_booking->delivered_to_name ) ) {
				OnRoute_Courier_Booking_Emails::send_delivery_confirmation( $booking_id );
			}

			// Trigger Email if time changed
			if ( $time_changed ) {
				$this->send_delivery_update_email( $old_booking, $change_details );
			}

			echo '<div class="notice notice-success is-dismissible"><p>Booking updated successfully!' . ( $time_changed ? ' Customer has been notified of time changes.' : '' ) . '</p></div>';
		}

		// Handle delete
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			check_admin_referer( 'ocb_delete_booking_' . $_GET['id'] );
			global $wpdb;
			$table = $wpdb->prefix . 'ocb_bookings';
			$wpdb->delete( $table, array( 'id' => intval( $_GET['id'] ) ) );
			echo '<div class="notice notice-success is-dismissible"><p>Booking deleted!</p></div>';
		}

		$booking = new OnRoute_Courier_Booking_Booking();
		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$limit = 20;
		$offset = ( $page - 1 ) * $limit;

		// Filter by status
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

		// Exclude VIP and Quote services from main bookings list
		$excluded_services = array( 'vip_secure', 'quote_same_day', 'quote_priority', 'quote_direct' );
		
		$bookings = $booking->get_all( $limit, $offset, $status_filter, '', $excluded_services, true );
		$total = $booking->count_all( $status_filter, '', $excluded_services, true );
		$pages = ceil( $total / $limit );

		// Get counts for filters (excluding VIP & Quotes)
		$all_count = $booking->count_all( '', '', $excluded_services, true );
		$pending_count = $booking->count_all( 'pending', '', $excluded_services, true );
		$confirmed_count = $booking->count_all( 'confirmed', '', $excluded_services, true );
		$completed_count = $booking->count_all( 'completed', '', $excluded_services, true );

		// Check if viewing single booking
		if ( isset( $_GET['view'] ) ) {
			$this->render_single_booking( intval( $_GET['view'] ) );
			return;
		}

		?>
		<div class="wrap ocb-admin">
			<h1>Bookings</h1>

			<!-- Status Filters -->
			<ul class="subsubsub">
				<li><a href="<?php echo admin_url( 'admin.php?page=ocb-bookings' ); ?>" class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">All <span class="count">(<?php echo $all_count; ?>)</span></a> |</li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ocb-bookings&status=pending' ); ?>" class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">Pending <span class="count">(<?php echo $pending_count; ?>)</span></a> |</li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ocb-bookings&status=confirmed' ); ?>" class="<?php echo $status_filter === 'confirmed' ? 'current' : ''; ?>">Confirmed <span class="count">(<?php echo $confirmed_count; ?>)</span></a> |</li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ocb-bookings&status=completed' ); ?>" class="<?php echo $status_filter === 'completed' ? 'current' : ''; ?>">Completed <span class="count">(<?php echo $completed_count; ?>)</span></a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
				<thead>
					<tr>
						<th style="width: 120px;">Reference</th>
						<th>Customer</th>
						<th>Route</th>
						<th>Service</th>
						<th>Status</th>
						<th>Total</th>
						<th>Payment</th>
						<th>Date</th>
						<th style="width: 150px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $bookings ) ) : ?>
						<tr><td colspan="9" style="text-align: center;">No bookings found.</td></tr>
					<?php else : ?>
						<?php foreach ( $bookings as $b ) : ?>
							<tr>
								<td><strong><a href="<?php echo admin_url( 'admin.php?page=ocb-bookings&view=' . $b->id ); ?>"><?php echo esc_html( $b->booking_reference ); ?></a></strong></td>
								<td>
									<?php echo esc_html( $b->customer_email ); ?><br>
									<small><?php echo esc_html( $b->customer_phone ); ?></small>
								</td>
								<td>
									<span style="color: #0066b3; font-weight: 500;"><?php echo esc_html( $b->pickup_postcode ); ?></span>
									<span style="color: #666;"> → </span>
									<span style="color: #28a745; font-weight: 500;"><?php echo esc_html( $b->delivery_postcode ); ?></span>
								</td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $b->service_id ) ) ); ?></td>
								<td>
									<span class="ocb-status-badge ocb-status-<?php echo esc_attr( $b->status ); ?>">
										<?php echo esc_html( ucfirst( $b->status ) ); ?>
									</span>
								</td>
								<td><strong>£<?php echo esc_html( number_format( $b->total_price, 2 ) ); ?></strong></td>
								<td>
									<span class="ocb-payment-badge ocb-payment-<?php echo esc_attr( $b->payment_status ); ?>">
										<?php echo esc_html( ucfirst( $b->payment_status ) ); ?>
									</span>
								</td>
								<td>
									<?php 
									if ( empty( $b->created_at ) || $b->created_at === '0000-00-00 00:00:00' || strtotime( $b->created_at ) < 0 ) {
										echo 'N/A';
									} else {
										echo esc_html( date( 'd/m/Y H:i', strtotime( $b->created_at ) ) ); 
									}
									?>
								</td>
								<td>
									<a href="<?php echo admin_url( 'admin.php?page=ocb-bookings&view=' . $b->id ); ?>" class="button button-small">View</a>
									<a href="<?php echo home_url( '/booking-confirmation/?ref=' . $b->booking_reference . '&print=true' ); ?>" target="_blank" class="button button-small" style="margin: 0 5px;">Print</a>
									<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=ocb-bookings&action=delete&id=' . $b->id ), 'ocb_delete_booking_' . $b->id ); ?>" class="button button-small" style="color: #dc3545;" onclick="return confirm('Delete this booking?')">Delete</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					$page_links = paginate_links( array(
						'base' => admin_url( 'admin.php?page=ocb-bookings' . ( $status_filter ? '&status=' . $status_filter : '' ) ) . '&paged=%#%',
						'format' => '%#%',
						'prev_text' => __( '&laquo; Previous' ),
						'next_text' => __( 'Next &raquo;' ),
						'total' => $pages,
						'current' => $page,
						'echo' => false,
					) );
					echo wp_kses_post( $page_links );
					?>
				</div>
			</div>
		</div>

		<style>
			.ocb-status-badge, .ocb-payment-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 12px;
				font-size: 12px;
				font-weight: 500;
			}
			.ocb-status-pending { background: #fff3cd; color: #856404; }
			.ocb-status-confirmed { background: #cce5ff; color: #004085; }
			.ocb-status-completed { background: #d4edda; color: #155724; }
			.ocb-status-cancelled { background: #f8d7da; color: #721c24; }
			.ocb-payment-unpaid { background: #f8d7da; color: #721c24; }
			.ocb-payment-paid { background: #d4edda; color: #155724; }
			.ocb-payment-refunded { background: #e2e3e5; color: #383d41; }
		</style>
		<?php
	}

	/**
	 * Render Enquiries page (VIP & Instant)
	 */
	public function render_enquiries() {
		// Handle status update
		if ( isset( $_POST['ocb_update_status'] ) && check_admin_referer( 'ocb_status_nonce' ) ) {
			$booking_id = intval( $_POST['booking_id'] );
			$new_status = sanitize_text_field( $_POST['new_status'] );
			
			global $wpdb;
			$table = $wpdb->prefix . 'ocb_bookings';
			$wpdb->update( $table, 
				array( 'status' => $new_status ),
				array( 'id' => $booking_id )
			);
			echo '<div class="notice notice-success is-dismissible"><p>Enquiry updated!</p></div>';
		}

		// Handle delete
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			check_admin_referer( 'ocb_delete_booking_' . $_GET['id'] );
			global $wpdb;
			$table = $wpdb->prefix . 'ocb_bookings';
			$wpdb->delete( $table, array( 'id' => intval( $_GET['id'] ) ) );
			echo '<div class="notice notice-success is-dismissible"><p>Enquiry deleted!</p></div>';
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'vip';
		$booking = new OnRoute_Courier_Booking_Booking();
		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$limit = 20;
		$offset = ( $page - 1 ) * $limit;
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

		// Define service IDs for tabs
		$vip_services = 'vip_secure';
		$quote_services = array( 'quote_same_day', 'quote_priority', 'quote_direct' );

		$target_services = ( $current_tab === 'instant' ) ? $quote_services : $vip_services;

		// Fetch Data
		$bookings = $booking->get_all( $limit, $offset, $status_filter, $target_services );
		$total = $booking->count_all( $status_filter, $target_services );
		$pages = ceil( $total / $limit );

		// Get Counts
		$all_count = $booking->count_all( '', $target_services );
		$pending_count = $booking->count_all( 'pending', $target_services );
		$confirmed_count = $booking->count_all( 'confirmed', $target_services );
		$completed_count = $booking->count_all( 'completed', $target_services );

		// Check if viewing single booking
		if ( isset( $_GET['view'] ) ) {
			$this->render_single_booking( intval( $_GET['view'] ) );
			return;
		}

		?>
		<div class="wrap ocb-admin">
			<h1 style="border-left: 4px solid #D4AF37; padding-left: 15px;">
				<?php echo ( $current_tab === 'instant' ) ? '⚡ Instant Enquiries' : '⚜️ VIP Enquiries'; ?>
			</h1>
			
			<p>Manage your incoming quote requests and high-value bookings.</p>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo admin_url( 'admin.php?page=ocb-enquiries&tab=vip' ); ?>" class="nav-tab <?php echo ( $current_tab === 'vip' ) ? 'nav-tab-active' : ''; ?>">VIP Requests</a>
				<a href="<?php echo admin_url( 'admin.php?page=ocb-enquiries&tab=instant' ); ?>" class="nav-tab <?php echo ( $current_tab === 'instant' ) ? 'nav-tab-active' : ''; ?>">Instant Enquiries</a>
			</nav>

			<!-- Status Filters -->
			<ul class="subsubsub" style="margin-top: 15px;">
				<li><a href="<?php echo admin_url( 'admin.php?page=ocb-enquiries&tab=' . $current_tab ); ?>" class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">All <span class="count">(<?php echo $all_count; ?>)</span></a> |</li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ocb-enquiries&tab=' . $current_tab . '&status=pending' ); ?>" class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">Pending <span class="count">(<?php echo $pending_count; ?>)</span></a> |</li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ocb-enquiries&tab=' . $current_tab . '&status=confirmed' ); ?>" class="<?php echo $status_filter === 'confirmed' ? 'current' : ''; ?>">Confirmed <span class="count">(<?php echo $confirmed_count; ?>)</span></a> |</li>
				<li><a href="<?php echo admin_url( 'admin.php?page=ocb-enquiries&tab=' . $current_tab . '&status=completed' ); ?>" class="<?php echo $status_filter === 'completed' ? 'current' : ''; ?>">Completed <span class="count">(<?php echo $completed_count; ?>)</span></a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
				<thead>
					<tr>
						<th style="width: 140px;">Ref</th>
						<th>Client</th>
						<th>Route</th>
						<th>Type</th>
						<th>Status</th>
						<th>Date</th>
						<th style="width: 150px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $bookings ) ) : ?>
						<tr><td colspan="7" style="text-align: center;">No enquiries found in this category.</td></tr>
					<?php else : ?>
						<?php foreach ( $bookings as $b ) : ?>
							<tr style="<?php echo ( $current_tab === 'vip' ) ? 'background: #fffdf5;' : ''; ?>">
								<td>
									<strong><a href="<?php echo admin_url( 'admin.php?page=ocb-bookings&view=' . $b->id ); ?>" style="<?php echo ( $current_tab === 'vip' ) ? 'color: #D4AF37;' : 'color: #d32f2f;'; ?>"><?php echo esc_html( $b->booking_reference ); ?></a></strong>
								</td>
								<td>
									<?php echo esc_html( $b->customer_email ); ?><br>
									<a href="tel:<?php echo esc_attr( $b->customer_phone ); ?>"><?php echo esc_html( $b->customer_phone ); ?></a>
								</td>
								<td>
									<span style="font-weight: 500;"><?php echo esc_html( $b->pickup_postcode ); ?></span>
									<span style="color: #666;"> → </span>
									<span style="font-weight: 500;"><?php echo esc_html( $b->delivery_postcode ); ?></span>
								</td>
								<td>
									<?php 
										$type_label = str_replace( array( 'vip_', 'quote_' ), '', $b->service_id );
										echo esc_html( ucfirst( str_replace( '_', ' ', $type_label ) ) ); 
									?>
								</td>
								<td>
									<span class="ocb-status-badge ocb-status-<?php echo esc_attr( $b->status ); ?>">
										<?php echo esc_html( ucfirst( $b->status ) ); ?>
									</span>
								</td>
								<td>
									<?php 
									if ( empty( $b->created_at ) || $b->created_at === '0000-00-00 00:00:00' || strtotime( $b->created_at ) < 0 ) {
										echo 'N/A';
									} else {
										echo esc_html( date( 'd/m/Y', strtotime( $b->created_at ) ) ); 
									}
									?>
								</td>
								<td>
									<a href="<?php echo admin_url( 'admin.php?page=ocb-bookings&view=' . $b->id ); ?>" class="button button-small">View Details</a>
									<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=ocb-enquiries&tab=' . $current_tab . '&action=delete&id=' . $b->id ), 'ocb_delete_booking_' . $b->id ); ?>" class="button button-small" style="color: #dc3545; margin-left: 5px;" onclick="return confirm('Delete this enquiry?')">Delete</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					$page_links = paginate_links( array(
						'base' => admin_url( 'admin.php?page=ocb-enquiries&tab=' . $current_tab . ( $status_filter ? '&status=' . $status_filter : '' ) ) . '&paged=%#%',
						'format' => '%#%',
						'prev_text' => __( '&laquo; Previous' ),
						'next_text' => __( 'Next &raquo;' ),
						'total' => $pages,
						'current' => $page,
						'echo' => false,
					) );
					echo wp_kses_post( $page_links );
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render single booking details
	 */
	private function render_single_booking( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ocb_bookings';
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

		if ( ! $b ) {
			echo '<div class="wrap"><h1>Booking Not Found</h1><p>The requested booking does not exist.</p></div>';
			return;
		}

		$service_names = array( 'dedicated' => 'Dedicated', 'timed' => 'Timed', 'same_day' => 'Same Day' );
		$vehicle_names = array(
			'pushbike' => 'Pushbike', 'motorbike' => 'Motorbike', 'cargo_bike' => 'Cargo Bike',
			'small_van' => 'Small Van', 'mwb' => 'Medium Van', 'lwb' => 'Large Van'
		);
		$is_enquiry = false;
		$back_link = 'admin.php?page=ocb-bookings';
		$back_text = 'Back to Bookings';

		if ( $b->service_id === 'vip_secure' ) {
			$back_link = 'admin.php?page=ocb-enquiries&tab=vip';
			$back_text = 'Back to VIP Enquiries';
			$is_enquiry = true;
		} elseif ( in_array( $b->service_id, array( 'quote_same_day', 'quote_priority', 'quote_direct' ) ) ) {
			$back_link = 'admin.php?page=ocb-enquiries&tab=instant';
			$back_text = 'Back to Instant Enquiries';
			$is_enquiry = true;
		}
		?>
		<div class="wrap ocb-admin">
			<h1>
				<a href="<?php echo admin_url( $back_link ); ?>" class="page-title-action" style="margin-right: 10px;">← <?php echo esc_html( $back_text ); ?></a>
				Booking: <?php echo esc_html( $b->booking_reference ); ?>
				
				<?php if ( ! $is_enquiry ) : ?>
				<a href="<?php echo home_url( '/booking-confirmation/?ref=' . $b->booking_reference . '&print=true' ); ?>" target="_blank" class="page-title-action" style="background: #0073aa; color: #fff; margin-left: 10px;">
					🖨️ Print Invoice
				</a>
				<?php endif; ?>
			</h1>

			<div class="ocb-booking-detail-grid">
				<!-- Left Column -->
				<div class="ocb-detail-column">
					
					<!-- Booking Info Card -->
					<div class="ocb-card">
						<h2>📦 Booking Details</h2>
						<table class="form-table">
							<tr><th>Reference:</th><td><strong><?php echo esc_html( $b->booking_reference ); ?></strong></td></tr>
							<tr><th>Service:</th><td><?php echo esc_html( $service_names[ $b->service_id ] ?? ucfirst( str_replace( array('quote_', '_'), array('', ' '), $b->service_id ) ) ); ?></td></tr>
							
							<?php if ( ! $is_enquiry ) : ?>
							<tr><th>Vehicle:</th><td><?php echo esc_html( $vehicle_names[ $b->vehicle_id ] ?? $b->vehicle_id ); ?></td></tr>
							<?php endif; ?>
							
							<tr><th>Collection Date:</th><td><?php echo esc_html( date( 'l, j F Y', strtotime( $b->collection_date ) ) ); ?></td></tr>
							
							<?php if ( ! $is_enquiry ) : ?>
							<tr>
								<th>Delivery Time:</th>
								<td>
									<?php 
									$notes = json_decode($b->notes, true);
									if ( ! empty( $notes['delivery_window_start'] ) && ! empty( $notes['delivery_window_end'] ) ) {
										echo esc_html( $notes['delivery_window_start'] . ' - ' . $notes['delivery_window_end'] );
									} else {
										echo 'By ' . esc_html( date( 'H:i', strtotime( $b->delivery_time ) ) ); 
									}
									?>
								</td>
							</tr>
							<?php endif; ?>
							
							<tr>
								<th>Created:</th>
								<td>
									<?php 
									if ( empty( $b->created_at ) || $b->created_at === '0000-00-00 00:00:00' || strtotime( $b->created_at ) < 0 ) {
										echo 'N/A';
									} else {
										echo esc_html( date( 'd/m/Y H:i:s', strtotime( $b->created_at ) ) ); 
									}
									?>
								</td>
							</tr>
						</table>
					</div>

					<!-- Route Card -->
					<div class="ocb-card">
						<h2>📍 Route</h2>
						<div class="ocb-route-display">
							<div class="ocb-route-point ocb-collection">
								<span class="ocb-point-label">Collection</span>
								<span class="ocb-point-postcode"><?php echo esc_html( $b->pickup_postcode ); ?></span>
								<?php if ( $b->pickup_address && $b->pickup_address !== $b->pickup_postcode ) : ?>
									<span class="ocb-point-address"><?php echo esc_html( $b->pickup_address ); ?></span>
								<?php endif; ?>
							</div>
							<div class="ocb-route-arrow">→</div>
							<div class="ocb-route-point ocb-delivery">
								<span class="ocb-point-label">Delivery</span>
								<span class="ocb-point-postcode"><?php echo esc_html( $b->delivery_postcode ); ?></span>
								<?php if ( $b->delivery_address && $b->delivery_address !== $b->delivery_postcode ) : ?>
									<span class="ocb-point-address"><?php echo esc_html( $b->delivery_address ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Customer Card -->
					<div class="ocb-card">
						<h2>👤 Customer</h2>
						<table class="form-table">
							<?php if ( $b->customer_name ) : ?>
							<tr><th>Name:</th><td><?php echo esc_html( $b->customer_name ); ?></td></tr>
							<?php endif; ?>
							<tr><th>Email:</th><td><a href="mailto:<?php echo esc_attr( $b->customer_email ); ?>"><?php echo esc_html( $b->customer_email ); ?></a></td></tr>
							<tr><th>Phone:</th><td><a href="tel:<?php echo esc_attr( $b->customer_phone ); ?>"><?php echo esc_html( $b->customer_phone ); ?></a></td></tr>
						</table>
					</div>
				</div>

				<!-- Right Column -->
				<div class="ocb-detail-column">
					
					<?php if ( ! $is_enquiry ) : ?>
					<!-- Payment Card -->
					<div class="ocb-card ocb-payment-card">
						<h2>💳 Payment</h2>
						<?php
						$notes = ! empty( $b->notes ) ? json_decode( $b->notes, true ) : array();
						
						// Calculate breakdown
						$admin_fee = 0;
						if ( $b->vehicle_id === 'small_van' ) { 
							$admin_fee = 15; 
						} elseif ( $b->vehicle_id === 'mwb' ) { 
							$admin_fee = 20; 
						} elseif ( $b->vehicle_id === 'lwb' ) { 
							$admin_fee = 25; 
						}
						
						$distance_cost = $b->base_price - $admin_fee;
						$distance_miles = $notes['distance_miles'] ?? 0;
						
						// Check night rate
						$delivery_hour = (int) date( 'H', strtotime( $b->delivery_time ) );
						$is_night = ( $delivery_hour >= 22 || $delivery_hour < 6 );
						?>
						<table class="ocb-price-table">
							<?php if ( $distance_miles > 0 ) : ?>
								<tr><td>Distance:</td><td><?php echo number_format( $distance_miles, 1 ); ?> miles</td></tr>
							<?php endif; ?>
							<tr>
								<td>Distance Cost<?php echo $is_night ? ' (Night Rate x2)' : ''; ?>:</td>
								<td>£<?php echo esc_html( number_format( $distance_cost, 2 ) ); ?></td>
							</tr>
							<tr><td>Admin Charge:</td><td>£<?php echo esc_html( number_format( $admin_fee, 2 ) ); ?></td></tr>
							<?php if ( $b->discount_amount > 0 ) : ?>
								<tr class="ocb-discount"><td>Discount:</td><td>-£<?php echo esc_html( number_format( $b->discount_amount, 2 ) ); ?></td></tr>
							<?php endif; ?>
							<?php if ( $b->promo_code ) : ?>
								<tr><td>Promo Code:</td><td><code><?php echo esc_html( $b->promo_code ); ?></code></td></tr>
							<?php endif; ?>
							<tr class="ocb-total"><td><strong>Total:</strong></td><td><strong>£<?php echo esc_html( number_format( $b->total_price, 2 ) ); ?></strong></td></tr>
						</table>
					</div>
					<?php endif; ?>
					
					<!-- Quote / VIP Notes Card -->
					<?php 
					if ( $is_enquiry && ! empty( $b->notes ) ) {
						// Check if json
						$json_notes = json_decode( $b->notes, true );
						$display_notes = $b->notes;
						
						if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_notes ) ) {
							// It's JSON, ignore for now in this block as it's handled below, or format it
						} else {
							// It's a string (like in VIP or Instant Quote if we saved string)
							?>
							<div class="ocb-card">
								<h2>📝 Enquiry Details</h2>
								<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; white-space: pre-wrap;"><?php echo esc_html( $display_notes ); ?></div>
							</div>
							<?php
						}
					}
					?>

					<!-- Status Update Card -->
					<div class="ocb-card">
						<h2>⚙️ Update Enquiry Status</h2>
						<form method="POST">
							<?php wp_nonce_field( 'ocb_status_nonce' ); ?>
							<input type="hidden" name="booking_id" value="<?php echo esc_attr( $b->id ); ?>" />
							
							<table class="form-table">
								<tr>
									<th>Status:</th>
									<td>
										<select name="new_status" class="regular-text">
											<option value="pending" <?php selected( $b->status, 'pending' ); ?>>Pending</option>
											<option value="connected" <?php selected( $b->status, 'connected' ); ?>>Customer Contacted</option>
											<option value="followup" <?php selected( $b->status, 'followup' ); ?>>Follow Up Required</option>
											<option value="quote_sent" <?php selected( $b->status, 'quote_sent' ); ?>>Quote Sent</option>
											<option value="confirmed" <?php selected( $b->status, 'confirmed' ); ?>>Confirmed</option>
											<option value="completed" <?php selected( $b->status, 'completed' ); ?>>Completed</option>
											<option value="cancelled" <?php selected( $b->status, 'cancelled' ); ?>>Cancelled</option>
										</select>
									</td>
								</tr>
								
								<?php if ( ! $is_enquiry ) : ?>
								<tr>
									<th>Payment Status:</th>
									<td>
										<select name="payment_status" class="regular-text">
											<option value="unpaid" <?php selected( $b->payment_status, 'unpaid' ); ?>>Unpaid</option>
											<option value="paid" <?php selected( $b->payment_status, 'paid' ); ?>>Paid</option>
											<option value="refunded" <?php selected( $b->payment_status, 'refunded' ); ?>>Refunded</option>
										</select>
									</td>
								</tr>

								<?php 
								$notes = json_decode($b->notes, true);
								$is_priority = in_array($b->service_id, ['timed', 'priority']);
								?>

								<?php if ($is_priority): ?>
								<tr>
									<th>Delivery Time:</th>
									<td>
										<input type="time" name="delivery_time" value="<?php echo date('H:i', strtotime($b->delivery_time)); ?>" class="regular-text">
										<p class="description">Exact delivery time for Priority service.</p>
									</td>
								</tr>
								<?php else: ?>
								<tr>
									<th>Delivery Window:</th>
									<td>
										<div style="display: flex; gap: 10px; align-items: center;">
											<input type="time" name="delivery_window_start" value="<?php echo esc_attr($notes['delivery_window_start'] ?? ''); ?>" style="width: 120px;">
											<span>to</span>
											<input type="time" name="delivery_window_end" value="<?php echo esc_attr($notes['delivery_window_end'] ?? ''); ?>" style="width: 120px;">
										</div>
										<p class="description">Delivery window range for Same Day / Direct services.</p>
									</td>
								</tr>
								<?php endif; ?>

								<?php endif; ?>
							</table>
							
							<p><button type="submit" name="ocb_update_status" class="button button-primary">Update Status</button></p>
						</form>
					</div>

					<!-- Collection Tracking Card -->
					<div class="ocb-card">
						<h2>🚚 Collection Stage</h2>
						<?php if ( ! empty( $b->collected_by_name ) ) : ?>
							<div class="ocb-tracking-info">
								<p><strong>Collected by:</strong> <?php echo esc_html( $b->collected_by_name ); ?></p>
								<p><strong>Date/Time:</strong> <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $b->collected_at ) ) ); ?></p>
								<?php if ( ! empty( $b->collection_signature ) ) : ?>
									<div class="ocb-signature-display">
										<p><strong>Signature:</strong></p>
										<img src="<?php echo $b->collection_signature; ?>" style="max-width: 100%; background: #fff; border: 1px solid #ddd;">
									</div>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<form method="POST" id="collection-form">
								<?php wp_nonce_field( 'ocb_status_nonce' ); ?>
								<input type="hidden" name="booking_id" value="<?php echo esc_attr( $b->id ); ?>" />
								<input type="hidden" name="ocb_update_status" value="1" />
								<input type="hidden" name="new_status" value="collected" />
								<input type="hidden" name="payment_status" value="<?php echo esc_attr( $b->payment_status ); ?>" />
								
								<p>
									<label><strong>Name of person item collected from:</strong></label><br>
									<input type="text" name="collected_by_name" class="widefat" required placeholder="Enter name...">
								</p>
								<div class="ocb-signature-pad-wrapper">
									<label><strong>Capture Signature:</strong></label>
									<canvas id="collection-pad" class="ocb-signature-pad" style="border: 1px solid #ccc; background: #fff; width: 100%; height: 150px;"></canvas>
									<input type="hidden" name="collection_signature" id="collection-sig-data">
									<button type="button" class="button ocb-clear-pad" data-target="collection-pad">Clear</button>
								</div>
								<p>
									<button type="submit" class="button button-primary ocb-save-tracking" data-pad="collection-pad" data-input="collection-sig-data">Confirm Collection</button>
								</p>
							</form>
						<?php endif; ?>
					</div>

					<!-- Delivery Tracking Card -->
					<div class="ocb-card">
						<h2>✅ Delivery Stage</h2>
						<?php if ( ! empty( $b->delivered_to_name ) ) : ?>
							<div class="ocb-tracking-info">
								<p><strong>Received by:</strong> <?php echo esc_html( $b->delivered_to_name ); ?></p>
								<p><strong>Date/Time:</strong> <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $b->delivered_at ) ) ); ?></p>
								<?php if ( ! empty( $b->delivery_signature ) ) : ?>
									<div class="ocb-signature-display">
										<p><strong>Signature:</strong></p>
										<img src="<?php echo $b->delivery_signature; ?>" style="max-width: 100%; background: #fff; border: 1px solid #ddd;">
									</div>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<form method="POST" id="delivery-form">
								<?php wp_nonce_field( 'ocb_status_nonce' ); ?>
								<input type="hidden" name="booking_id" value="<?php echo esc_attr( $b->id ); ?>" />
								<input type="hidden" name="ocb_update_status" value="1" />
								<input type="hidden" name="new_status" value="completed" />
								<input type="hidden" name="payment_status" value="<?php echo esc_attr( $b->payment_status ); ?>" />
								
								<p>
									<label><strong>Name of person receiving item:</strong></label><br>
									<input type="text" name="delivered_to_name" class="widefat" required placeholder="Enter name...">
								</p>
								<div class="ocb-signature-pad-wrapper">
									<label><strong>Capture Signature:</strong></label>
									<canvas id="delivery-pad" class="ocb-signature-pad" style="border: 1px solid #ccc; background: #fff; width: 100%; height: 150px;"></canvas>
									<input type="hidden" name="delivery_signature" id="delivery-sig-data">
									<button type="button" class="button ocb-clear-pad" data-target="delivery-pad">Clear</button>
								</div>
								<p>
									<button type="submit" class="button button-primary ocb-save-tracking" data-pad="delivery-pad" data-input="delivery-sig-data">Confirm Delivery</button>
								</p>
							</form>
						<?php endif; ?>
					</div>

					<script>
					jQuery(document).ready(function($) {
						var pads = {};
						
						function initPads() {
							$('.ocb-signature-pad').each(function() {
								var id = $(this).attr('id');
								var canvas = document.getElementById(id);
								if (canvas && !pads[id]) {
									pads[id] = new SignaturePad(canvas, {
										backgroundColor: 'rgb(255, 255, 255)'
									});
									
									// Handle resizing
									function resizeCanvas() {
										var ratio =  Math.max(window.devicePixelRatio || 1, 1);
										canvas.width = canvas.offsetWidth * ratio;
										canvas.height = canvas.offsetHeight * ratio;
										canvas.getContext("2d").scale(ratio, ratio);
										pads[id].clear(); 
									}
									window.addEventListener("resize", resizeCanvas);
									resizeCanvas();
								}
							});
						}

						// Small delay to ensure canvas is rendered
						setTimeout(initPads, 500);

						$('.ocb-clear-pad').on('click', function() {
							var target = $(this).data('target');
							if (pads[target]) {
								pads[target].clear();
							}
						});

						$('.ocb-save-tracking').on('click', function(e) {
							var padId = $(this).data('pad');
							var inputId = $(this).data('input');
							if (pads[padId]) {
								if (pads[padId].isEmpty()) {
									alert('Please provide a signature.');
									e.preventDefault();
									return false;
								}
								$('#' + inputId).val(pads[padId].toDataURL());
							}
						});
					});
					</script>
					<style>
					.ocb-signature-pad-wrapper { margin: 15px 0; }
					.ocb-signature-pad { border: 1px solid #ccc; border-radius: 4px; cursor: crosshair; touch-action: none; display: block; width: 100%; }
					.ocb-clear-pad { margin-top: 5px !important; }
					.ocb-tracking-info { background: #f0f6fb; padding: 15px; border-radius: 4px; border-left: 4px solid #0073aa; }
					.ocb-tracking-info p { margin: 0 0 10px 0; }
					.ocb-signature-display img { border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
					</style>

					<!-- Notes Card (Standard) -->
					<?php if ( ! $is_enquiry && $b->notes ) : ?>
					<div class="ocb-card">
						<h2>📝 Notes</h2>
						<?php 
						$notes_data = json_decode( $b->notes, true );
						if ( json_last_error() === JSON_ERROR_NONE && is_array( $notes_data ) ) {
							echo '<table class="form-table">';
							foreach ( $notes_data as $key => $value ) {
								// Skip empty values or internal fields if needed
								if ( empty( $value ) ) continue;
								
								// Make labels readable
								$label = ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
								
								// Format value if needed
								if ( $key === 'distance_miles' ) {
									$value .= ' miles';

								}
								
								echo '<tr><th>' . esc_html( $label ) . ':</th><td>' . esc_html( $value ) . '</td></tr>';
							}
							echo '</table>';
						} else {
							echo '<p>' . nl2br( esc_html( $b->notes ) ) . '</p>';
						}
						?>
					</div>
					<?php endif; ?>

				</div>
			</div>
		</div>

		<style>
			.ocb-booking-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
			.ocb-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
			.ocb-card h2 { margin: 0 0 15px; padding-bottom: 10px; border-bottom: 2px solid #0066b3; font-size: 16px; }
			.ocb-card .form-table th { width: 120px; padding: 8px 10px 8px 0; }
			.ocb-card .form-table td { padding: 8px 0; }
			.ocb-route-display { display: flex; align-items: center; gap: 20px; justify-content: center; padding: 20px; }
			.ocb-route-point { text-align: center; padding: 15px 25px; border-radius: 8px; }
			.ocb-collection { background: #e3f2fd; }
			.ocb-delivery { background: #e8f5e9; }
			.ocb-point-label { display: block; font-size: 12px; color: #666; margin-bottom: 5px; }
			.ocb-point-postcode { display: block; font-size: 24px; font-weight: 700; font-family: monospace; }
			.ocb-point-address { display: block; font-size: 12px; color: #666; margin-top: 5px; }
			.ocb-route-arrow { font-size: 24px; color: #999; }
			.ocb-price-table { width: 100%; }
			.ocb-price-table td { padding: 10px 0; border-bottom: 1px solid #eee; }
			.ocb-price-table .ocb-total td { border-bottom: none; font-size: 18px; background: #0066b3; color: #fff; padding: 15px; margin: 10px -20px -20px; }
			.ocb-price-table .ocb-discount td { color: #28a745; }
			@media (max-width: 1200px) { .ocb-booking-detail-grid { grid-template-columns: 1fr; } }
		</style>
		<?php
	}

	/**
	 * Render promo codes page
	 */
	public function render_promos() {
		$promo = new OnRoute_Courier_Booking_Promo();

		// Handle form submission
		if ( isset( $_POST['ocb_create_promo'] ) && check_admin_referer( 'ocb_promo_nonce' ) ) {
			$code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
			$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
			$value = isset( $_POST['value'] ) ? floatval( $_POST['value'] ) : 0;
			$expiry = isset( $_POST['expiry_date'] ) ? sanitize_text_field( $_POST['expiry_date'] ) : null;
			$max_uses = isset( $_POST['max_uses'] ) ? intval( $_POST['max_uses'] ) : null;

			if ( $code && $type && $value > 0 ) {
				$result = $promo->create( $code, $type, $value, $expiry, $max_uses );
				if ( $result ) {
					echo '<div class="notice notice-success is-dismissible"><p>Promo code created successfully!</p></div>';
				}
			}
		}

		// Handle deletion
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
			check_admin_referer( 'ocb_delete_promo_' . $_GET['id'] );
			$promo->delete( intval( $_GET['id'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>Promo code deleted!</p></div>';
		}

		$promos = $promo->get_all();

		?>
		<div class="wrap ocb-admin">
			<h1>Promo Codes</h1>

			<div class="ocb-form-section">
				<h2>Create New Promo Code</h2>
				<form method="POST" class="ocb-form">
					<?php wp_nonce_field( 'ocb_promo_nonce' ); ?>

					<div class="form-group">
						<label for="code">Code:</label>
						<input type="text" id="code" name="code" required placeholder="e.g., SUMMER20" />
					</div>

					<div class="form-group">
						<label for="type">Type:</label>
						<select id="type" name="type" required>
							<option value="">Select Type</option>
							<option value="fixed">Fixed Discount (£)</option>
							<option value="percentage">Percentage Discount (%)</option>
						</select>
					</div>

					<div class="form-group">
						<label for="value">Value:</label>
						<input type="number" id="value" name="value" required step="0.01" placeholder="e.g., 5.00" />
					</div>

					<div class="form-group">
						<label for="expiry_date">Expiry Date (Optional):</label>
						<input type="date" id="expiry_date" name="expiry_date" />
					</div>

					<div class="form-group">
						<label for="max_uses">Max Uses (Optional):</label>
						<input type="number" id="max_uses" name="max_uses" min="1" />
					</div>

					<button type="submit" name="ocb_create_promo" class="button button-primary">Create Promo Code</button>
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Code</th>
						<th>Type</th>
						<th>Value</th>
						<th>Expiry</th>
						<th>Times Used</th>
						<th>Max Uses</th>
						<th>Active</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $promos as $p ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $p->code ); ?></strong></td>
							<td><?php echo esc_html( ucfirst( $p->type ) ); ?></td>
							<td>
								<?php
								if ( 'fixed' === $p->type ) {
									echo '£' . esc_html( number_format( $p->value, 2 ) );
								} else {
									echo esc_html( $p->value ) . '%';
								}
								?>
							</td>
							<td>
								<?php
								echo $p->expiry_date ? esc_html( date( 'd/m/Y', strtotime( $p->expiry_date ) ) ) : 'Never';
								?>
							</td>
							<td><?php echo esc_html( $p->times_used ); ?></td>
							<td><?php echo esc_html( $p->max_uses ?? 'Unlimited' ); ?></td>
							<td><?php echo $p->active ? '✓' : '✗'; ?></td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=ocb-promos&action=delete&id=' . $p->id ), 'ocb_delete_promo_' . $p->id ) ); ?>" onclick="return confirm('Delete this promo code?')">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render Data Manager Page
	 */
	public function render_data_manager_page() {
		?>
		<div class="wrap ocb-admin">
			<h1 class="wp-heading-inline">OnRoute Courier Booking - Backup & Restore</h1>
			<hr class="wp-header-end">
			<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px;">
				<?php
				$data_manager = new OnRoute_Courier_Booking_Data_Manager();
				$data_manager->render_data_manager_only();
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page
	 */
	public function render_settings() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap ocb-admin">
			<h1 class="wp-heading-inline">OnRoute Courier Booking - Settings</h1>
			<hr class="wp-header-end">

			<h2 class="nav-tab-wrapper" style="margin-top: 20px;">
				<a href="?page=ocb-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
				<a href="?page=ocb-settings&tab=pricing" class="nav-tab <?php echo $active_tab === 'pricing' ? 'nav-tab-active' : ''; ?>">Services & Vehicles</a>
				<a href="?page=ocb-settings&tab=form_fields" class="nav-tab <?php echo $active_tab === 'form_fields' ? 'nav-tab-active' : ''; ?>">Booking Form</a>
				<a href="?page=ocb-settings&tab=payments" class="nav-tab <?php echo $active_tab === 'payments' ? 'nav-tab-active' : ''; ?>">Payments</a>
				<a href="?page=ocb-settings&tab=notifications" class="nav-tab <?php echo $active_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">Notifications</a>
			</h2>

			<div class="ocb-settings-content" style="margin-top: 20px;">
				<form method="POST" action="options.php" class="ocb-settings-form">
					<?php 
					if ( $active_tab === 'form_fields' ) {
						settings_fields( 'ocb-form-settings' );
						$form_settings = new OnRoute_Courier_Booking_Form_Admin_Settings();
						$form_settings->render_field_settings_only();
					} else {
						settings_fields( 'ocb_settings_group' );
						
						if ( $active_tab === 'general' ) {
							$this->render_api_settings();
						} elseif ( $active_tab === 'pricing' ) {
							$this->render_services_settings();
							echo '<hr style="margin: 40px 0;">';
							$this->render_vehicles_settings();
						} elseif ( $active_tab === 'payments' ) {
							if ( class_exists( 'OnRoute_Courier_Booking_Payment_Settings' ) ) {
								OnRoute_Courier_Booking_Payment_Settings::render_settings_section();
							}
						} elseif ( $active_tab === 'notifications' ) {
							$this->render_email_settings();
						}
					}
					
					$button_text = ( $active_tab === 'form_fields' ) ? 'Save Form Fields Configuration' : 'Save ' . ucfirst( str_replace('_', ' ', $active_tab) ) . ' Settings';
					submit_button( $button_text ); 
					?>
				</form>
			</div>
		</div>
		<style>
			.ocb-settings-form .ocb-settings-section {
				margin-top: 0;
				border: 1px solid #ccd0d4;
				border-radius: 0;
				box-shadow: none;
			}
			.nav-tab-active { background: #fff; border-bottom: 1px solid #fff; }
			.ocb-settings-content { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; }
			/* Professional Enhancements */
			.ocb-settings-content h2 { font-size: 1.2em; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px; color: #DC3545; }
			.form-table th { font-weight: 600; color: #444; }
			.ocb-settings-content .button-primary { background: #DC3545; border-color: #DC3545; text-shadow: none; box-shadow: none; border-radius: 4px; padding: 5px 20px; font-weight: 600; transition: 0.2s; }
			.ocb-settings-content .button-primary:hover { background: #c82333; border-color: #bd2130; }
		</style>
		<?php
	}

	/**
	 * Render Google Maps API settings
	 */
	private function render_api_settings() {
		$provider = get_option( 'ocb_distance_provider', 'google' );
		$google_key = get_option( 'ocb_google_maps_api_key', '' );
		$openroute_key = get_option( 'ocb_openroute_api_key', '' );
		$fallback_miles = get_option( 'ocb_fallback_distance', 10 );
		?>
		<div class="notice notice-info" style="margin-left: 0; padding: 15px;">
			<h3 style="margin-top: 0;">🚀 API Configuration</h3>
			<p><strong>Note for Client:</strong> This system is configured to use the <strong>Google Distance Matrix API</strong> exclusively for accurate pricing.</p>
			<p>Please ensure your Google API Key has the <strong>Distance Matrix API</strong> enabled.</p>
		</div>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ocb_distance_provider">Distance Calculation Provider:</label></th>
				<td>
					<select id="ocb_distance_provider" name="ocb_distance_provider" class="regular-text">
						<option value="google" <?php selected( $provider, 'google' ); ?>>Google Distance Matrix API (Standard)</option>
						<!-- <option value="openroute" <?php selected( $provider, 'openroute' ); ?>>OpenRouteService (Legacy)</option> -->
					</select>
					<p class="description">
						Using <strong>Google Distance Matrix API</strong> for strictly distance-based pricing.
					</p>
				</td>
			</tr>
			<!-- OpenRoute Settings Hidden/Deprecated -->
			<tr class="ocb-openroute-settings" style="display:none;">
				<th scope="row"><label for="ocb_openroute_api_key">OpenRouteService API Key:</label></th>
				<td>
					<input type="text" id="ocb_openroute_api_key" name="ocb_openroute_api_key" value="<?php echo esc_attr( $openroute_key ); ?>" class="regular-text" />
				</td>
			</tr>
			
			<tr class="ocb-google-settings">
				<th scope="row"><label for="ocb_google_maps_api_key">Google Maps API Key:</label></th>
				<td>
					<input type="text" id="ocb_google_maps_api_key" name="ocb_google_maps_api_key" value="<?php echo esc_attr( $google_key ); ?>" class="regular-text" placeholder="AIzaSy..." />
					<p class="description">
						Enter your Google Maps API key.<br>
						<strong>Required API:</strong> Distance Matrix API (Only).<br>
						<a href="https://console.cloud.google.com/apis/credentials" target="_blank">Get API Key from Google Cloud Console →</a>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_ideal_postcodes_api_key">Ideal Postcodes API Key (Address Lookup):</label></th>
				<td>
					<?php $ideal_key = get_option( 'ocb_ideal_postcodes_api_key', '' ); ?>
					<input type="text" id="ocb_ideal_postcodes_api_key" name="ocb_ideal_postcodes_api_key" value="<?php echo esc_attr( $ideal_key ); ?>" class="regular-text" placeholder="ak_..." />
					<p class="description">
						<strong>Recommended.</strong> Returns UK street addresses (e.g. 21a Quarry Road, Headington, Oxford, OX3 8NT).<br>
						<a href="https://ideal-postcodes.co.uk" target="_blank">Get API Key →</a>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_getaddress_io_api_key">GetAddress.io API Key (Alternative):</label></th>
				<td>
					<?php $getaddress_key = get_option( 'ocb_getaddress_io_api_key', '' ); ?>
					<input type="text" id="ocb_getaddress_io_api_key" name="ocb_getaddress_io_api_key" value="<?php echo esc_attr( $getaddress_key ); ?>" class="regular-text" placeholder="Optional" />
					<p class="description">
						Alternative to Ideal Postcodes. Free tier: 20 lookups/day. <a href="https://getaddress.io" target="_blank">Get API Key →</a>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_fallback_distance">Fallback Distance (miles):</label></th>
				<td>
					<input type="number" id="ocb_fallback_distance" name="ocb_fallback_distance" value="<?php echo esc_attr( $fallback_miles ); ?>" step="0.1" min="1" max="100" />
					<p class="description">Default distance used when API is unavailable or postcodes invalid</p>
				</td>
			</tr>
			<tr>
				<th scope="row">API Status:</th>
				<td>
					<div id="ocb-api-status" class="ocb-api-status">
						<?php 
						$current_key = $provider === 'google' ? $google_key : $openroute_key;
						if ( ! empty( $current_key ) ) : ?>
							<span class="ocb-status-dot active"></span> API Key configured (<?php echo ucfirst($provider); ?>)
						<?php else : ?>
							<span class="ocb-status-dot inactive"></span> No API Key - using fallback distance
						<?php endif; ?>
					</div>
				</td>
			</tr>
		</table>
		<script>
		jQuery(document).ready(function($) {
			$('#ocb_distance_provider').on('change', function() {
				var provider = $(this).val();
				if (provider === 'google') {
					$('.ocb-google-settings').show();
					$('.ocb-openroute-settings').hide();
				} else {
					$('.ocb-google-settings').hide();
					$('.ocb-openroute-settings').show();
				}
			});
		});
		</script>
		<style>
			.ocb-api-status { margin-bottom: 10px; }
			.ocb-status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 5px; }
			.ocb-status-dot.active { background: #46b450; }
			.ocb-status-dot.inactive { background: #dc3232; }
			#ocb-api-test-result { margin-left: 10px; font-weight: 500; }
		</style>
		<?php
	}

	/**
	 * Render Email & SMTP settings
	 */
	private function render_email_settings() {
		$smtp_enabled = get_option( 'ocb_smtp_enabled', 1 );
		$host = get_option( 'ocb_smtp_host', 'smtp.gmail.com' );
		$port = get_option( 'ocb_smtp_port', '587' );
		$encryption = get_option( 'ocb_smtp_encryption', 'tls' );
		$username = get_option( 'ocb_smtp_username', 'ops@onroutecouriers.com' );
		$password = get_option( 'ocb_smtp_password', 'qyap zhxa gmiw rrac' );
		$from_email = get_option( 'ocb_smtp_from_email', 'ops@onroutecouriers.com' );
		$from_name = get_option( 'ocb_smtp_from_name', 'OnRoute Couriers' );
		
		$logo_url = get_option( 'ocb_email_logo_url', '' );
		$footer_text = get_option( 'ocb_email_footer_text', '© ' . date('Y') . ' OnRoute Couriers. All rights reserved.' );
		$primary_color = get_option( 'ocb_email_primary_color', '#D4AF37' );
		?>
		<div class="notice notice-warning" style="margin-left: 0; padding: 15px;">
			<h3 style="margin-top: 0;">📧 SMTP Configuration</h3>
			<p>Configure how emails are sent from the system. If you use Gmail, you must use an <strong>App Password</strong> if 2-Step Verification is enabled.</p>
		</div>
		<table class="form-table">
			<tr>
				<th scope="row"><h3>Custom Email Branding</h3></th>
				<td><p class="description">Customize how your automated emails look for customers.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_email_logo_url">Email Logo URL:</label></th>
				<td>
					<input type="text" id="ocb_email_logo_url" name="ocb_email_logo_url" value="<?php echo esc_attr( $logo_url ); ?>" class="regular-text" placeholder="https://your-site.com/logo.png" />
					<p class="description">Upload your logo to the Media Library and paste the URL here. Recommended width: 200px.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_email_primary_color">Primary Branding Color:</label></th>
				<td>
					<input type="color" id="ocb_email_primary_color" name="ocb_email_primary_color" value="<?php echo esc_attr( $primary_color ); ?>" />
					<p class="description">Color used for headings and accents in the email.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_email_footer_text">Email Footer Signature:</label></th>
				<td>
					<textarea id="ocb_email_footer_text" name="ocb_email_footer_text" rows="3" class="large-text"><?php echo esc_textarea( $footer_text ); ?></textarea>
					<p class="description">This text will appear at the bottom of every email (Booking, Collection, Delivery).</p>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><h3>SMTP Server Configuration</h3></th>
				<td></td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_smtp_enabled">Enable Custom SMTP:</label></th>
				<td>
					<input type="checkbox" id="ocb_smtp_enabled" name="ocb_smtp_enabled" value="1" <?php checked( $smtp_enabled, 1 ); ?> />
					<p class="description">If unchecked, WordPress will use the default server mail (or other SMTP plugins if installed).</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_smtp_host">SMTP Host:</label></th>
				<td>
					<input type="text" id="ocb_smtp_host" name="ocb_smtp_host" value="<?php echo esc_attr( $host ); ?>" class="regular-text" placeholder="smtp.gmail.com" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_smtp_port">SMTP Port:</label></th>
				<td>
					<input type="number" id="ocb_smtp_port" name="ocb_smtp_port" value="<?php echo esc_attr( $port ); ?>" class="small-text" />
					<p class="description">Standard: 587 (TLS) or 465 (SSL)</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_smtp_encryption">Encryption:</label></th>
				<td>
					<select id="ocb_smtp_encryption" name="ocb_smtp_encryption">
						<option value="tls" <?php selected( $encryption, 'tls' ); ?>>TLS</option>
						<option value="ssl" <?php selected( $encryption, 'ssl' ); ?>>SSL</option>
						<option value="none" <?php selected( $encryption, 'none' ); ?>>None</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_smtp_username">Username (Email):</label></th>
				<td>
					<input type="text" id="ocb_smtp_username" name="ocb_smtp_username" value="<?php echo esc_attr( $username ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_smtp_password">Password / App Password:</label></th>
				<td>
					<input type="password" id="ocb_smtp_password" name="ocb_smtp_password" value="<?php echo esc_attr( $password ); ?>" class="regular-text" />
					<p class="description">For Gmail, use a 16-character App Password, not your regular password.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_smtp_from_email">From Email Address:</label></th>
				<td>
					<input type="email" id="ocb_smtp_from_email" name="ocb_smtp_from_email" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ocb_smtp_from_name">From Name:</label></th>
				<td>
					<input type="text" id="ocb_smtp_from_name" name="ocb_smtp_from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text" />
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render VAT settings
	 */
	private function render_vat_settings() {
		$vat_rate = get_option( 'ocb_vat_rate', 20 );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="ocb_vat_rate">VAT Rate (%):</label></th>
				<td>
					<input type="number" id="ocb_vat_rate" name="ocb_vat_rate" value="<?php echo esc_attr( $vat_rate ); ?>" step="0.01" min="0" max="100" />
					<p class="description">Default VAT percentage applied to all bookings</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render services settings
	 */
	private function render_services_settings() {
		$services = get_option( 'ocb_services', array() );
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Service Name</th>
					<th>Service Multiplier</th>
					<th>Active</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $services as $index => $service ) : ?>
					<tr>
						<td>
							<input type="text" name="ocb_services[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $service['name'] ); ?>" />
						</td>
						<td>
							<input type="number" name="ocb_services[<?php echo esc_attr( $index ); ?>][multiplier]" value="<?php echo esc_attr( $service['multiplier'] ); ?>" step="0.1" min="0" />
						</td>
						<td>
							<input type="checkbox" name="ocb_services[<?php echo esc_attr( $index ); ?>][active]" value="1" <?php checked( $service['active'], 1 ); ?> />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render vehicles settings
	 */
	private function render_vehicles_settings() {
		// Load vehicles through Pricing class to ensure defaults and merges are handled correctly
		// This ensures 0 values in DB don't show as 0 in the form, but show the fallback default
		$vehicles = array();
		if ( class_exists( 'OnRoute_Courier_Booking_Pricing' ) ) {
			$pricing = new OnRoute_Courier_Booking_Pricing();
			$vehicles = $pricing->get_vehicles();
		}

		// Fallback for safety
		if ( empty( $vehicles ) ) {
			$vehicles = get_option( 'ocb_vehicles', array() );
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th>Vehicle Name</th>
					<th>Rate per Mile (£)</th>
					<th>Admin Fee (£)</th>
					<th>Min Charge (£)</th>
					<th>Active</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $vehicles as $index => $vehicle ) : ?>
					<tr>
						<td>
							<input type="text" name="ocb_vehicles[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $vehicle['name'] ); ?>" />
						</td>
						<td>
							<input type="number" name="ocb_vehicles[<?php echo esc_attr( $index ); ?>][rate_per_mile]" value="<?php echo esc_attr( $vehicle['rate_per_mile'] ?? 0 ); ?>" step="0.01" min="0" placeholder="1.35" />
						</td>
						<td>
							<input type="number" name="ocb_vehicles[<?php echo esc_attr( $index ); ?>][admin_fee]" value="<?php echo esc_attr( $vehicle['admin_fee'] ?? 0 ); ?>" step="0.01" min="0" placeholder="15.00" />
						</td>
						<td>
							<input type="number" name="ocb_vehicles[<?php echo esc_attr( $index ); ?>][min_charge]" value="<?php echo esc_attr( $vehicle['min_charge'] ?? 0 ); ?>" step="0.01" min="0" placeholder="45.00" />
						</td>
						<td>
							<input type="checkbox" name="ocb_vehicles[<?php echo esc_attr( $index ); ?>][active]" value="1" <?php checked( $vehicle['active'], 1 ); ?> />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// Distance Provider
		register_setting( 'ocb_settings_group', 'ocb_distance_provider', array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'openroute',
		) );

		// Google Maps API Key
		register_setting( 'ocb_settings_group', 'ocb_google_maps_api_key', array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		// OpenRoute API Key
		register_setting( 'ocb_settings_group', 'ocb_openroute_api_key', array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		) );

		// Ideal Postcodes API Key (UK address lookup - preferred)
		register_setting( 'ocb_settings_group', 'ocb_ideal_postcodes_api_key', array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		// GetAddress.io API Key (UK address lookup - alternative)
		register_setting( 'ocb_settings_group', 'ocb_getaddress_io_api_key', array(
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		// SMTP Settings
		register_setting( 'ocb_settings_group', 'ocb_smtp_enabled', array( 'type' => 'number', 'default' => 1 ) );
		register_setting( 'ocb_settings_group', 'ocb_smtp_host', array( 'type' => 'string', 'default' => 'smtp.gmail.com' ) );
		register_setting( 'ocb_settings_group', 'ocb_smtp_port', array( 'type' => 'string', 'default' => '587' ) );
		register_setting( 'ocb_settings_group', 'ocb_smtp_encryption', array( 'type' => 'string', 'default' => 'tls' ) );
		register_setting( 'ocb_settings_group', 'ocb_smtp_username', array( 'type' => 'string', 'default' => 'ops@onroutecouriers.com' ) );
		register_setting( 'ocb_settings_group', 'ocb_smtp_password', array( 'type' => 'string', 'default' => 'qyap zhxa gmiw rrac' ) );
		register_setting( 'ocb_settings_group', 'ocb_smtp_from_email', array( 'type' => 'string', 'default' => 'ops@onroutecouriers.com' ) );
		register_setting( 'ocb_settings_group', 'ocb_smtp_from_name', array( 'type' => 'string', 'default' => 'OnRoute Couriers' ) );
		
		// Set default OpenRoute API key if not already set
		// Admin: Add your OpenRoute API key in Settings or get one from https://openrouteservice.org/dev/#/signup
		if ( empty( get_option( 'ocb_openroute_api_key' ) ) ) {
			update_option( 'ocb_openroute_api_key', '' );
		}
		
		// Fallback distance

		register_setting( 'ocb_settings_group', 'ocb_fallback_distance', array(
			'type' => 'number',
			'sanitize_callback' => 'floatval',
			'default' => 10,
		) );

		register_setting( 'ocb_settings_group', 'ocb_vat_rate', array(
			'type' => 'number',
			'sanitize_callback' => 'floatval',
		) );

		// Email Branding Settings
		register_setting( 'ocb_settings_group', 'ocb_email_logo_url', array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'ocb_settings_group', 'ocb_email_footer_text', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'ocb_settings_group', 'ocb_email_primary_color', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '#D4AF37' ) );

		register_setting( 'ocb_settings_group', 'ocb_services', array(
			'type' => 'array',
			'sanitize_callback' => array( $this, 'sanitize_services' ),
		) );

		register_setting( 'ocb_settings_group', 'ocb_vehicles', array(
			'type' => 'array',
			'sanitize_callback' => array( $this, 'sanitize_vehicles' ),
		) );
	}

	/**
	 * Sanitize services
	 */
	public function sanitize_services( $services ) {
		if ( ! is_array( $services ) ) {
			return get_option( 'ocb_services' );
		}

		$sanitized = array();
		$original = get_option( 'ocb_services', array() );

		foreach ( $services as $index => $service ) {
			$sanitized[] = array(
				'id' => $original[ $index ]['id'] ?? 'service_' . $index,
				'name' => sanitize_text_field( $service['name'] ?? '' ),
				'multiplier' => floatval( $service['multiplier'] ?? 1 ),
				'active' => isset( $service['active'] ) ? 1 : 0,
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize vehicles
	 */
	public function sanitize_vehicles( $vehicles ) {
		if ( ! is_array( $vehicles ) ) {
			return get_option( 'ocb_vehicles' );
		}

		$sanitized = array();
		$pricing = new OnRoute_Courier_Booking_Pricing();
		$defaults = $pricing->get_vehicles();

		foreach ( $vehicles as $index => $vehicle ) {
			$vehicle_name = sanitize_text_field( $vehicle['name'] ?? '' );
			
			// Find the ID by matching the name with defaults
			$vehicle_id = 'vehicle_' . $index;
			foreach ( $defaults as $default ) {
				if ( strtolower( $default['name'] ) === strtolower( $vehicle_name ) ) {
					$vehicle_id = $default['id'];
					break;
				}
			}

			$sanitized[] = array(
				'id' => $vehicle_id,
				'name' => $vehicle_name,
				'rate_per_mile' => floatval( $vehicle['rate_per_mile'] ?? 0 ),
				'admin_fee' => floatval( $vehicle['admin_fee'] ?? 0 ),
				'min_charge' => floatval( $vehicle['min_charge'] ?? 0 ),
				'active' => isset( $vehicle['active'] ) ? 1 : 0,
			);
		}

		return $sanitized;
	}

	/**
	 * Render Data Manager page
	 */
	public function render_data_manager() {
		if ( class_exists( 'OnRoute_Courier_Booking_Data_Manager' ) ) {
			$data_manager = new OnRoute_Courier_Booking_Data_Manager();
			$data_manager->render_page();
		}
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'ocb' ) === false ) {
			return;
		}

		wp_enqueue_style( 'ocb-admin-css', ONROUTE_COURIER_BOOKING_URL . 'assets/admin.css' );
		
		// Add Signature Pad for Collection/Delivery tracking
		wp_enqueue_script( 'signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js', array(), '4.1.7', true );
	}

	/**
	 * Send automated email to customer when delivery time is updated
	 */
	private function send_delivery_update_email( $booking, $changes ) {
		$to = $booking->customer_email;
		$subject = "Updated delivery time for your order #{$booking->booking_reference}";
		
		$primary_color = get_option( 'ocb_email_primary_color', '#D4AF37' );
		
		$content = "<h2 style='color: " . esc_attr( $primary_color ) . ";'>Delivery Update</h2>";
		$content .= "<p>Hello,</p>";
		$content .= "<p>The delivery information for your booking <strong>#{$booking->booking_reference}</strong> has been updated by our administration team.</p>";
		
		$content .= "<h3>Changes:</h3>";
		$content .= "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; border: 1px solid #eee;'>";
		$content .= "<tr style='background: #f8f9fa;'><th>Feature</th><th>Old Value</th><th>New Value</th></tr>";
		
		foreach ( $changes as $label => $values ) {
			$content .= "<tr>";
			$content .= "<td><strong>{$label}</strong></td>";
			$content .= "<td><s>{$values['old']}</s></td>";
			$content .= "<td style='color: #d32f2f; font-weight: bold;'>{$values['new']}</td>";
			$content .= "</tr>";
		}
		
		$content .= "</table>";
		
		$content .= "<p style='margin-top: 20px;'>Our team is working hard to ensure your delivery arrives within the newly specified timeframe. If you have any questions, please contact us at 0207 786 1000.</p>";
		$content .= "<p>Best regards,<br>OnRoute Couriers Team</p>";

		$message = OnRoute_Courier_Booking_Emails::apply_branding( $content, $booking->booking_reference );

		OnRoute_Courier_Booking_Emails::send_html_mail( $to, $subject, $message, $booking->id );
	}

	/**
	 * Render email logs content
	 */
	public function render_email_logs_content() {
		global $wpdb;
		$table_name = OnRoute_Courier_Booking_Database::get_email_logs_table();
		$bookings_table = OnRoute_Courier_Booking_Database::get_bookings_table();

		// Pagination
		$per_page = 20;
		$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $page - 1 ) * $per_page;

		$logs = $wpdb->get_results( $wpdb->prepare(
			"SELECT l.*, b.booking_reference 
			 FROM $table_name l
			 LEFT JOIN $bookings_table b ON l.booking_id = b.id
			 ORDER BY l.sent_at DESC 
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		$total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
		$total_pages = ceil( $total_logs / $per_page );

		?>
		<div>
			<h3>Email Delivery Logs</h3>
			<p>View the status of automated emails sent by the system.</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th width="150">Sent At</th>
						<th width="150">Booking Ref</th>
						<th>Recipient</th>
						<th>Subject</th>
						<th width="100">Status</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( $logs ) : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->sent_at ); ?></td>
								<td>
									<?php if ( $log->booking_reference ) : ?>
										<strong><?php echo esc_html( $log->booking_reference ); ?></strong>
									<?php else : ?>
										<span style="color: #999;">System</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $log->recipient_email ); ?></td>
								<td><?php echo esc_html( $log->subject ); ?></td>
								<td>
									<?php if ( $log->status === 'sent' ) : ?>
										<span class="status-badge" style="background: #e7f4e9; color: #1e7e34; padding: 3px 8px; border-radius: 3px; font-size: 11px;">SUCCESS</span>
									<?php else : ?>
										<span class="status-badge" style="background: #fdf2f2; color: #dc3545; padding: 3px 8px; border-radius: 3px; font-size: 11px;">FAILED</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5">No logs found.</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => __( '&laquo;' ),
							'next_text' => __( '&raquo;' ),
							'total'     => $total_pages,
							'current'   => $page,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
