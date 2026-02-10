<?php
/**
 * Dashboard Extensions - Backend logic for new dashboard features
 * 
 * Handles: Job Detail/POD, Saved Locations, Support Tickets, Invoices, Account Management
 *
 * @package OnRoute_Courier_Booking
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Dashboard_Extensions {

	/**
	 * Constructor - register AJAX handlers
	 */
	public function __construct() {
		// Account management
		add_action( 'wp_ajax_ocb_update_account', array( $this, 'ajax_update_account' ) );
		add_action( 'wp_ajax_ocb_change_password', array( $this, 'ajax_change_password' ) );

		// Saved locations
		add_action( 'wp_ajax_ocb_save_location', array( $this, 'ajax_save_location' ) );
		add_action( 'wp_ajax_ocb_delete_location', array( $this, 'ajax_delete_location' ) );
		add_action( 'wp_ajax_ocb_get_locations', array( $this, 'ajax_get_locations' ) );

		// Support tickets
		add_action( 'wp_ajax_ocb_create_ticket', array( $this, 'ajax_create_ticket' ) );
		add_action( 'wp_ajax_ocb_get_tickets', array( $this, 'ajax_get_tickets' ) );

		// Job detail view (customer)
		add_action( 'wp_ajax_ocb_get_job_detail', array( $this, 'ajax_get_job_detail' ) );
		add_action( 'wp_ajax_ocb_download_pod', array( $this, 'ajax_download_pod' ) );

		// Admin: POD management
		add_action( 'wp_ajax_ocb_save_pod', array( $this, 'ajax_save_pod' ) );
		add_action( 'wp_ajax_ocb_update_job_status', array( $this, 'ajax_update_job_status' ) );
		add_action( 'wp_ajax_ocb_resend_confirmation', array( $this, 'ajax_resend_confirmation' ) );

		// Admin: Support ticket reply
		add_action( 'wp_ajax_ocb_reply_ticket', array( $this, 'ajax_reply_ticket' ) );
		add_action( 'wp_ajax_ocb_close_ticket', array( $this, 'ajax_close_ticket' ) );
	}

	/**
	 * Get display status label
	 */
	public static function get_status_label( $status ) {
		$labels = array(
			'booked'     => 'Booked',
			'picked_up'  => 'Picked up',
			'in_transit' => 'In Transit',
			'delivered'  => 'Delivered',
			'completed'  => 'Completed',
			'cancelled'  => 'Cancelled',
		);
		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	// =========================================================================
	// DATABASE TABLE HELPERS
	// =========================================================================

	public static function get_saved_locations_table() {
		global $wpdb;
		return $wpdb->prefix . 'ocb_saved_locations';
	}

	public static function get_support_tickets_table() {
		global $wpdb;
		return $wpdb->prefix . 'ocb_support_tickets';
	}

	public static function get_invoices_table() {
		global $wpdb;
		return $wpdb->prefix . 'ocb_invoices';
	}

	/**
	 * Create extension tables (called from Database::create_tables)
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Saved Locations
		$locations_table = self::get_saved_locations_table();
		$locations_sql = "CREATE TABLE $locations_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			label varchar(100) NOT NULL,
			type varchar(20) DEFAULT 'both',
			address text NOT NULL,
			postcode varchar(20) NOT NULL,
			contact_name varchar(100) DEFAULT NULL,
			contact_phone varchar(20) DEFAULT NULL,
			contact_email varchar(100) DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) $charset_collate;";
		dbDelta( $locations_sql );

		// Support Tickets
		$tickets_table = self::get_support_tickets_table();
		$tickets_sql = "CREATE TABLE $tickets_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			booking_id bigint(20) UNSIGNED DEFAULT NULL,
			booking_reference varchar(50) DEFAULT NULL,
			subject varchar(255) NOT NULL,
			message text NOT NULL,
			attachment_url text DEFAULT NULL,
			status varchar(20) DEFAULT 'open',
			admin_reply text DEFAULT NULL,
			replied_at datetime DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00',
			updated_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY booking_id (booking_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $tickets_sql );

		// Invoices
		$invoices_table = self::get_invoices_table();
		$invoices_sql = "CREATE TABLE $invoices_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			invoice_number varchar(50) NOT NULL,
			booking_id bigint(20) UNSIGNED DEFAULT NULL,
			booking_reference varchar(50) DEFAULT NULL,
			company_name varchar(255) DEFAULT NULL,
			amount decimal(10,2) NOT NULL,
			vat_amount decimal(10,2) DEFAULT 0.00,
			total_amount decimal(10,2) NOT NULL,
			status varchar(20) DEFAULT 'unpaid',
			due_date date DEFAULT NULL,
			paid_at datetime DEFAULT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY invoice_number (invoice_number),
			KEY user_id (user_id),
			KEY booking_id (booking_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $invoices_sql );
	}

	// =========================================================================
	// DATA ACCESS METHODS
	// =========================================================================

	/**
	 * Get a single booking with full details for job view
	 */
	public static function get_job_detail( $booking_id, $user_id ) {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d AND (user_id = %d OR customer_email = (SELECT user_email FROM {$wpdb->users} WHERE ID = %d))",
			$booking_id, $user_id, $user_id
		) );
	}

	/**
	 * Get saved locations for a user
	 */
	public static function get_user_locations( $user_id ) {
		global $wpdb;
		$table = self::get_saved_locations_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE user_id = %d ORDER BY label ASC",
			$user_id
		) );
	}

	/**
	 * Save a location
	 */
	public static function save_location( $data ) {
		global $wpdb;
		$table = self::get_saved_locations_table();

		if ( ! empty( $data['id'] ) ) {
			$id = $data['id'];
			unset( $data['id'] );
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id;
		}

		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	/**
	 * Delete a saved location
	 */
	public static function delete_location( $id, $user_id ) {
		global $wpdb;
		$table = self::get_saved_locations_table();
		return $wpdb->delete( $table, array( 'id' => $id, 'user_id' => $user_id ) );
	}

	/**
	 * Get support tickets for a user
	 */
	public static function get_user_tickets( $user_id, $limit = 20 ) {
		global $wpdb;
		$table = self::get_support_tickets_table();
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
			$user_id, $limit
		) );
	}

	/**
	 * Get all support tickets (admin)
	 */
	public static function get_all_tickets( $status = '', $limit = 50 ) {
		global $wpdb;
		$table = self::get_support_tickets_table();
		$where = '1=1';
		$params = array();
		if ( $status ) {
			$where .= ' AND status = %s';
			$params[] = $status;
		}
		$params[] = $limit;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE $where ORDER BY FIELD(status, 'open', 'in_progress', 'closed'), created_at DESC LIMIT %d",
			$params
		) );
	}

	/**
	 * Create a support ticket
	 */
	public static function create_ticket( $data ) {
		global $wpdb;
		$table = self::get_support_tickets_table();
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	/**
	 * Get invoices for a user (auto-generate missing invoices)
	 */
	public static function get_user_invoices( $user_id, $limit = 50 ) {
		global $wpdb;
		$invoices_table = self::get_invoices_table();
		$bookings_table = OnRoute_Courier_Booking_Database::get_bookings_table();

		// Auto-generate missing invoices for all booked+ bookings (not cancelled)
		$bookings = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM $bookings_table WHERE user_id = %d AND status NOT IN ('cancelled') AND id NOT IN (SELECT booking_id FROM $invoices_table)",
			$user_id
		) );

		if ( ! empty( $bookings ) ) {
			foreach ( $bookings as $booking ) {
				self::generate_invoice_for_booking( $booking->id );
			}
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $invoices_table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
			$user_id, $limit
		) );
	}

	/**
	 * Auto-generate invoice for a booking
	 */
	public static function generate_invoice_for_booking( $booking_id ) {
		global $wpdb;
		$invoices_table = self::get_invoices_table();
		$bookings_table = OnRoute_Courier_Booking_Database::get_bookings_table();

		// Check if invoice already exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $invoices_table WHERE booking_id = %d", $booking_id
		) );
		if ( $existing ) {
			return $existing;
		}

		$booking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $bookings_table WHERE id = %d", $booking_id
		) );
		if ( ! $booking ) {
			return false;
		}

		// Don't generate invoice for cancelled bookings only
		if ( $booking->status === 'cancelled' ) {
			return false;
		}

		$invoice_number = 'INV-' . date( 'Ym' ) . '-' . str_pad( $booking_id, 5, '0', STR_PAD_LEFT );
		$company_name = '';
		if ( $booking->user_id ) {
			$account = OnRoute_Business_Credit::get_account_by_user( $booking->user_id );
			$company_name = $account ? $account->company_name : '';
		}

		$vat_amount = $booking->vat_amount ?: 0;
		$net_amount = $booking->total_price - $vat_amount;

		$wpdb->insert( $invoices_table, array(
			'user_id'           => $booking->user_id ?: 0,
			'invoice_number'    => $invoice_number,
			'booking_id'        => $booking_id,
			'booking_reference' => $booking->booking_reference,
			'company_name'      => $company_name,
			'amount'            => $net_amount,
			'vat_amount'        => $vat_amount,
			'total_amount'      => $booking->total_price,
			'status'            => ( $booking->payment_status === 'paid' ) ? 'paid' : 'unpaid',
			'due_date'          => date( 'Y-m-d', strtotime( '+30 days' ) ),
			'paid_at'           => ( $booking->payment_status === 'paid' ) ? current_time( 'mysql' ) : null,
			'created_at'        => current_time( 'mysql' ),
		) );

		return $wpdb->insert_id;
	}

	// =========================================================================
	// AJAX HANDLERS - CUSTOMER DASHBOARD
	// =========================================================================

	/**
	 * Update account details
	 */
	public function ajax_update_account() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		$user_id = get_current_user_id();
		$user = get_userdata( $user_id );
		$account = OnRoute_Business_Credit::get_account_by_user( $user_id );

		if ( ! $account ) {
			wp_send_json_error( array( 'message' => 'No business account found.' ) );
		}

		$display_name = sanitize_text_field( $_POST['display_name'] ?? '' );
		$phone = sanitize_text_field( $_POST['phone'] ?? '' );
		$billing_address = sanitize_textarea_field( $_POST['billing_address'] ?? '' );
		$company_name = sanitize_text_field( $_POST['company_name'] ?? '' );

		if ( $display_name ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $display_name ) );
		}
		if ( $phone ) {
			update_user_meta( $user_id, 'billing_phone', $phone );
		}
		if ( $billing_address ) {
			update_user_meta( $user_id, 'billing_address_1', $billing_address );
		}
		if ( $company_name && $company_name !== $account->company_name ) {
			OnRoute_Business_Credit::save_account( array(
				'id'           => $account->id,
				'company_name' => $company_name,
			) );
		}

		wp_send_json_success( array( 'message' => 'Account updated successfully.' ) );
	}

	/**
	 * Change password
	 */
	public function ajax_change_password() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		$user_id = get_current_user_id();
		$current = $_POST['current_password'] ?? '';
		$new_pass = $_POST['new_password'] ?? '';
		$confirm = $_POST['confirm_password'] ?? '';

		if ( ! $current || ! $new_pass || ! $confirm ) {
			wp_send_json_error( array( 'message' => 'All password fields are required.' ) );
		}

		$user = get_userdata( $user_id );
		if ( ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			wp_send_json_error( array( 'message' => 'Current password is incorrect.' ) );
		}

		if ( $new_pass !== $confirm ) {
			wp_send_json_error( array( 'message' => 'New passwords do not match.' ) );
		}

		if ( strlen( $new_pass ) < 8 ) {
			wp_send_json_error( array( 'message' => 'Password must be at least 8 characters.' ) );
		}

		wp_set_password( $new_pass, $user_id );

		// Re-login the user immediately
		wp_set_auth_cookie( $user_id, true );

		wp_send_json_success( array( 'message' => 'Password changed successfully.' ) );
	}

	/**
	 * Save a location
	 */
	public function ajax_save_location() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		$user_id = get_current_user_id();
		$data = array(
			'user_id'       => $user_id,
			'label'         => sanitize_text_field( $_POST['label'] ?? '' ),
			'type'          => sanitize_text_field( $_POST['type'] ?? 'both' ),
			'address'       => sanitize_textarea_field( $_POST['address'] ?? '' ),
			'postcode'      => sanitize_text_field( $_POST['postcode'] ?? '' ),
			'contact_name'  => sanitize_text_field( $_POST['contact_name'] ?? '' ),
			'contact_phone' => sanitize_text_field( $_POST['contact_phone'] ?? '' ),
			'contact_email' => sanitize_email( $_POST['contact_email'] ?? '' ),
		);

		if ( empty( $data['label'] ) || empty( $data['address'] ) || empty( $data['postcode'] ) ) {
			wp_send_json_error( array( 'message' => 'Label, address, and postcode are required.' ) );
		}

		if ( ! empty( $_POST['location_id'] ) ) {
			$data['id'] = absint( $_POST['location_id'] );
		}

		$id = self::save_location( $data );
		if ( $id ) {
			wp_send_json_success( array( 'message' => 'Location saved.', 'id' => $id ) );
		}
		wp_send_json_error( array( 'message' => 'Failed to save location.' ) );
	}

	/**
	 * Delete a location
	 */
	public function ajax_delete_location() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		$id = absint( $_POST['location_id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Invalid location.' ) );
		}

		$deleted = self::delete_location( $id, get_current_user_id() );
		if ( $deleted ) {
			wp_send_json_success( array( 'message' => 'Location deleted.' ) );
		}
		wp_send_json_error( array( 'message' => 'Failed to delete.' ) );
	}

	/**
	 * Get saved locations
	 */
	public function ajax_get_locations() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		$locations = self::get_user_locations( get_current_user_id() );
		wp_send_json_success( array( 'locations' => $locations ) );
	}

	/**
	 * Create support ticket
	 */
	public function ajax_create_ticket() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		$user_id = get_current_user_id();
		$subject = sanitize_text_field( $_POST['subject'] ?? '' );
		$message = sanitize_textarea_field( $_POST['message'] ?? '' );
		$booking_ref = sanitize_text_field( $_POST['booking_reference'] ?? '' );

		if ( empty( $subject ) || empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Subject and message are required.' ) );
		}

		$data = array(
			'user_id'           => $user_id,
			'subject'           => $subject,
			'message'           => $message,
			'booking_reference' => $booking_ref,
			'status'            => 'open',
		);

		// Handle file upload
		if ( ! empty( $_FILES['attachment'] ) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$upload = wp_handle_upload( $_FILES['attachment'], array( 'test_form' => false ) );
			if ( ! empty( $upload['url'] ) ) {
				$data['attachment_url'] = $upload['url'];
			}
		}

		// Link to booking if reference provided
		if ( $booking_ref ) {
			global $wpdb;
			$bookings_table = OnRoute_Courier_Booking_Database::get_bookings_table();
			$booking = $wpdb->get_row( $wpdb->prepare(
				"SELECT id FROM $bookings_table WHERE booking_reference = %s AND (user_id = %d OR customer_email = (SELECT user_email FROM {$wpdb->users} WHERE ID = %d))",
				$booking_ref, $user_id, $user_id
			) );
			if ( $booking ) {
				$data['booking_id'] = $booking->id;
			}
		}

		$ticket_id = self::create_ticket( $data );
		if ( $ticket_id ) {
			// Notify admin
			$user = get_userdata( $user_id );
			$admin_email = 'admin@onroutecouriers.com';
			$email_subject = 'New Support Ticket #' . $ticket_id . ': ' . $subject;
			$body = '<h2>New Support Ticket</h2>';
			$body .= '<p><strong>From:</strong> ' . esc_html( $user->display_name ) . ' (' . esc_html( $user->user_email ) . ')</p>';
			if ( $booking_ref ) {
				$body .= '<p><strong>Booking:</strong> ' . esc_html( $booking_ref ) . '</p>';
			}
			$body .= '<p><strong>Subject:</strong> ' . esc_html( $subject ) . '</p>';
			$body .= '<p><strong>Message:</strong></p><p>' . nl2br( esc_html( $message ) ) . '</p>';
			if ( ! empty( $data['attachment_url'] ) ) {
				$body .= '<p><strong>Attachment:</strong> <a href="' . esc_url( $data['attachment_url'] ) . '">View File</a></p>';
			}

			$branded = OnRoute_Courier_Booking_Emails::apply_branding( $body );
			OnRoute_Courier_Booking_Emails::send_html_mail( $admin_email, $email_subject, $branded );

			wp_send_json_success( array( 'message' => 'Support ticket created. We\'ll respond within 24 hours.' ) );
		}
		wp_send_json_error( array( 'message' => 'Failed to create ticket.' ) );
	}

	/**
	 * Get support tickets
	 */
	public function ajax_get_tickets() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		$tickets = self::get_user_tickets( get_current_user_id() );
		wp_send_json_success( array( 'tickets' => $tickets ) );
	}

	// =========================================================================
	// AJAX HANDLERS - CUSTOMER JOB DETAIL VIEW
	// =========================================================================

	/**
	 * Get job detail HTML for customer dashboard
	 */
	public function ajax_get_job_detail() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$user_id = get_current_user_id();

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => 'Invalid booking.' ) );
		}

		$booking = self::get_job_detail( $booking_id, $user_id );
		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => 'Booking not found or access denied.' ) );
		}

		$notes = json_decode( $booking->notes ?? '{}', true ) ?: array();
		$v_name = ucfirst( str_replace( array( 'mwb', 'lwb', '_' ), array( 'Medium Van', 'Large Van', ' ' ), $booking->vehicle_id ) );
		$s_name = ucfirst( str_replace( array( 'direct', 'timed' ), array( 'Dedicated', 'Priority' ), $booking->service_id ) );
		$status_label = self::get_status_label( $booking->status );

		ob_start();
		?>
		<div style="margin-bottom:15px;">
			<a href="#" class="ocb-back-to-bookings" style="color:#e31837; font-weight:600; text-decoration:none; font-size:14px;">&larr; Back to My Bookings</a>
		</div>
		<h2 style="margin:0 0 5px;">Job: <span style="color:#e31837;"><?php echo esc_html( $booking->booking_reference ); ?></span></h2>
		<p style="margin:0 0 25px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
			<span class="status-pill status-<?php echo esc_attr( strtolower( $booking->status ) ); ?>" style="padding:5px 14px; font-size:12px;">
				<?php echo esc_html( $status_label ); ?>
			</span>
			<?php if ( ! empty( $booking->collected_at ) || ! empty( $booking->delivered_at ) ) : ?>
				<button class="ocb-download-pod" data-id="<?php echo esc_attr( $booking->id ); ?>" style="background:#28a745; color:#fff; border:none; padding:7px 14px; border-radius:4px; cursor:pointer; font-size:12px;"><i class="fas fa-download"></i> Download POD</button>
			<?php endif; ?>
		</p>

		<div class="ocb-job-info-grid">
			<!-- Booking Details -->
			<div class="ocb-job-card">
				<h4><i class="fas fa-info-circle" style="color:#e31837;"></i> Booking Details</h4>
				<table style="width:100%; font-size:13px;">
					<tr><td style="padding:6px 0; color:#888; width:120px;">Reference</td><td style="padding:6px 0; font-weight:600;"><?php echo esc_html( $booking->booking_reference ); ?></td></tr>
					<tr><td style="padding:6px 0; color:#888;">Vehicle</td><td style="padding:6px 0;"><?php echo esc_html( $v_name ); ?></td></tr>
					<tr><td style="padding:6px 0; color:#888;">Service</td><td style="padding:6px 0; color:#e31837; font-weight:700;"><?php echo esc_html( $s_name ); ?></td></tr>
					<tr><td style="padding:6px 0; color:#888;">Total</td><td style="padding:6px 0; font-weight:700; font-size:16px;">£<?php echo number_format( (float)$booking->total_price, 2 ); ?></td></tr>
					<tr><td style="padding:6px 0; color:#888;">Payment</td><td style="padding:6px 0;"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $booking->payment_method ?: 'N/A' ) ) ); ?> — <?php echo esc_html( ucfirst( $booking->payment_status ) ); ?></td></tr>
				</table>
			</div>

			<!-- Route Details -->
			<div class="ocb-job-card">
				<h4><i class="fas fa-route" style="color:#e31837;"></i> Route</h4>
				<div style="margin-bottom:15px;">
					<p style="font-size:11px; color:#888; margin:0 0 3px; text-transform:uppercase; font-weight:700;">Pickup</p>
					<p style="font-size:13px; margin:0;"><?php echo esc_html( $booking->pickup_address ); ?></p>
					<p style="font-size:14px; font-weight:700; margin:3px 0;"><?php echo esc_html( $booking->pickup_postcode ); ?></p>
					<p style="font-size:12px; color:#666; margin:0;"><?php echo esc_html( date( 'j M Y', strtotime( $booking->collection_date ) ) . ' at ' . date( 'H:i', strtotime( $booking->collection_time ) ) ); ?></p>
				</div>
				<div style="border-top:1px dashed #ddd; padding-top:15px;">
					<p style="font-size:11px; color:#888; margin:0 0 3px; text-transform:uppercase; font-weight:700;">Delivery</p>
					<p style="font-size:13px; margin:0;"><?php echo esc_html( $booking->delivery_address ); ?></p>
					<p style="font-size:14px; font-weight:700; margin:3px 0;"><?php echo esc_html( $booking->delivery_postcode ); ?></p>
					<?php if ( $booking->delivery_date && $booking->delivery_date !== '0000-00-00' ) : ?>
						<p style="font-size:12px; color:#666; margin:0;"><?php echo esc_html( date( 'j M Y', strtotime( $booking->delivery_date ) ) ); ?><?php echo $booking->delivery_time ? ' at ' . esc_html( date( 'H:i', strtotime( $booking->delivery_time ) ) ) : ''; ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Pickup POD -->
			<div class="ocb-job-card" style="border-left: 4px solid <?php echo ! empty( $booking->collected_at ) ? '#28a745' : '#ffc107'; ?>;">
				<h4><i class="fas fa-box-open" style="color:<?php echo ! empty( $booking->collected_at ) ? '#28a745' : '#ffc107'; ?>;"></i> Pickup Confirmation</h4>
				<?php if ( ! empty( $booking->collected_at ) ) : ?>
					<p class="ocb-pod-confirmed"><i class="fas fa-check-circle"></i> Completed</p>
					<table style="width:100%; font-size:13px;">
						<tr><td style="padding:4px 0; color:#888; width:110px;">Date & Time</td><td style="padding:4px 0;"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $booking->collected_at ) ) ); ?></td></tr>
						<tr><td style="padding:4px 0; color:#888;">Handed by</td><td style="padding:4px 0; font-weight:600;"><?php echo esc_html( $booking->collected_by_name ); ?></td></tr>
					</table>
					<?php if ( ! empty( $booking->collection_signature ) ) : ?>
						<div class="ocb-pod-sig">
							<p style="font-size:12px; color:#888; margin:10px 0 5px;">Signature:</p>
							<img src="<?php echo esc_url( $booking->collection_signature ); ?>" alt="Collection Signature">
						</div>
					<?php endif; ?>
				<?php else : ?>
					<p class="ocb-pod-pending"><i class="fas fa-clock"></i> Pending — awaiting pickup</p>
				<?php endif; ?>
			</div>

			<!-- Delivery POD -->
			<div class="ocb-job-card" style="border-left: 4px solid <?php echo ! empty( $booking->delivered_at ) ? '#28a745' : '#ffc107'; ?>;">
				<h4><i class="fas fa-truck" style="color:<?php echo ! empty( $booking->delivered_at ) ? '#28a745' : '#ffc107'; ?>;"></i> Delivery Confirmation</h4>
				<?php if ( ! empty( $booking->delivered_at ) ) : ?>
					<p class="ocb-pod-confirmed"><i class="fas fa-check-circle"></i> Delivered</p>
					<table style="width:100%; font-size:13px;">
						<tr><td style="padding:4px 0; color:#888; width:110px;">Date & Time</td><td style="padding:4px 0;"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $booking->delivered_at ) ) ); ?></td></tr>
						<tr><td style="padding:4px 0; color:#888;">Received by</td><td style="padding:4px 0; font-weight:600;"><?php echo esc_html( $booking->delivered_to_name ); ?></td></tr>
					</table>
					<?php if ( ! empty( $booking->delivery_signature ) ) : ?>
						<div class="ocb-pod-sig">
							<p style="font-size:12px; color:#888; margin:10px 0 5px;">Signature:</p>
							<img src="<?php echo esc_url( $booking->delivery_signature ); ?>" alt="Delivery Signature">
						</div>
					<?php endif; ?>
				<?php else : ?>
					<p class="ocb-pod-pending"><i class="fas fa-clock"></i> Pending — awaiting delivery</p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty( $notes ) ) : ?>
			<div class="ocb-job-card" style="margin-top:20px;">
				<h4><i class="fas fa-sticky-note" style="color:#e31837;"></i> Additional Notes</h4>
				<table style="width:100%; font-size:13px;">
					<?php if ( ! empty( $notes['collection_contact_name'] ) ) : ?>
						<tr><td style="padding:4px 0; color:#888; width:160px;">Collection Contact</td><td style="padding:4px 0;"><?php echo esc_html( $notes['collection_contact_name'] ); ?><?php echo ! empty( $notes['collection_contact_phone'] ) ? ' — ' . esc_html( $notes['collection_contact_phone'] ) : ''; ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $notes['delivery_contact_name'] ) ) : ?>
						<tr><td style="padding:4px 0; color:#888;">Delivery Contact</td><td style="padding:4px 0;"><?php echo esc_html( $notes['delivery_contact_name'] ); ?><?php echo ! empty( $notes['delivery_contact_phone'] ) ? ' — ' . esc_html( $notes['delivery_contact_phone'] ) : ''; ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $notes['booked_by_company'] ) ) : ?>
						<tr><td style="padding:4px 0; color:#888;">Company</td><td style="padding:4px 0;"><?php echo esc_html( $notes['booked_by_company'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $notes['special_instructions'] ) ) : ?>
						<tr><td style="padding:4px 0; color:#888;">Instructions</td><td style="padding:4px 0;"><?php echo esc_html( $notes['special_instructions'] ); ?></td></tr>
					<?php endif; ?>
				</table>
			</div>
		<?php endif; ?>

		<script>
		jQuery('.ocb-back-to-bookings').on('click', function(e) {
			e.preventDefault();
			jQuery('.nav-item').removeClass('active');
			jQuery('.nav-item[data-target="dash-bookings"]').addClass('active');
			jQuery('.dash-tab-content').removeClass('active');
			jQuery('#dash-bookings').addClass('active');
		});
		</script>
		<?php
		$html = ob_get_clean();
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Download POD as HTML (customer can print or save as PDF)
	 */
	public function ajax_download_pod() {
		check_ajax_referer( 'ocb_dashboard_ext', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated.' ) );
		}

		global $wpdb;
		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$user_id = get_current_user_id();
		$table = $wpdb->prefix . 'ocb_bookings';

		$booking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE id = %d AND user_id = %d",
			$booking_id, $user_id
		) );

		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => 'Booking not found.' ) );
		}

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title>Proof of Delivery - #<?php echo esc_html( $booking->booking_reference ); ?></title>
			<style>
				body { font-family: Arial, sans-serif; margin: 40px; }
				.header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #e31837; padding-bottom: 20px; }
				.header h1 { color: #e31837; margin: 0; font-size: 24px; }
				.header p { margin: 5px 0; color: #666; }
				.section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
				.section h2 { color: #333; font-size: 18px; margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
				table { width: 100%; font-size: 14px; }
				td { padding: 5px 0; }
				td:first-child { color: #888; width: 150px; }
				td:last-child { font-weight: 500; }
				.signature { margin-top: 15px; text-align: center; }
				.signature img { max-width: 300px; border: 1px solid #ddd; padding: 10px; }
				.confirmed { color: #28a745; font-weight: bold; }
				.pending { color: #ffc107; font-weight: bold; }
			</style>
		</head>
		<body>
			<div class="header">
				<h1>OnRoute Couriers - Proof of Delivery</h1>
				<p>Booking Reference: <strong>#<?php echo esc_html( $booking->booking_reference ); ?></strong></p>
				<p>Generated: <?php echo date( 'd/m/Y H:i' ); ?></p>
			</div>

			<div class="section">
				<h2>Booking Details</h2>
				<table>
					<tr><td>Collection Date:</td><td><?php echo date_i18n( 'd/m/Y', strtotime( $booking->collection_date ) ); ?> at <?php echo esc_html( $booking->collection_time ); ?></td></tr>
					<tr><td>Pickup:</td><td><?php echo nl2br( esc_html( $booking->pickup_address ) ); ?>, <?php echo esc_html( $booking->pickup_postcode ); ?></td></tr>
					<tr><td>Delivery To:</td><td><?php echo nl2br( esc_html( $booking->delivery_address ) ); ?>, <?php echo esc_html( $booking->delivery_postcode ); ?></td></tr>
					<tr><td>Status:</td><td><?php echo esc_html( self::get_status_label( $booking->status ) ); ?></td></tr>
				</table>
			</div>

			<?php if ( ! empty( $booking->collected_at ) ) : ?>
				<div class="section">
					<h2>Pickup Confirmation <span class="confirmed">✓ Completed</span></h2>
					<table>
						<tr><td>Date & Time:</td><td><?php echo date( 'd/m/Y H:i', strtotime( $booking->collected_at ) ); ?></td></tr>
						<tr><td>Handed by:</td><td><?php echo esc_html( $booking->collected_by_name ); ?></td></tr>
					</table>
					<?php if ( ! empty( $booking->collection_signature ) ) : ?>
						<div class="signature">
							<p><strong>Signature:</strong></p>
							<img src="<?php echo esc_url( $booking->collection_signature ); ?>" alt="Collection Signature">
						</div>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="section">
					<h2>Pickup Confirmation <span class="pending">⏳ Pending</span></h2>
					<p>Awaiting pickup confirmation.</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $booking->delivered_at ) ) : ?>
				<div class="section">
					<h2>Delivery Confirmation <span class="confirmed">✓ Delivered</span></h2>
					<table>
						<tr><td>Date & Time:</td><td><?php echo date( 'd/m/Y H:i', strtotime( $booking->delivered_at ) ); ?></td></tr>
						<tr><td>Received by:</td><td><?php echo esc_html( $booking->delivered_to_name ); ?></td></tr>
					</table>
					<?php if ( ! empty( $booking->delivery_signature ) ) : ?>
						<div class="signature">
							<p><strong>Signature:</strong></p>
							<img src="<?php echo esc_url( $booking->delivery_signature ); ?>" alt="Delivery Signature">
						</div>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="section">
					<h2>Delivery Confirmation <span class="pending">⏳ Pending</span></h2>
					<p>Awaiting delivery confirmation.</p>
				</div>
			<?php endif; ?>

			<script>
				window.onload = function() {
					window.print();
				};
			</script>
		</body>
		</html>
		<?php
		$html = ob_get_clean();
		wp_send_json_success( array( 'html' => $html ) );
	}

	// =========================================================================
	// AJAX HANDLERS - ADMIN
	// =========================================================================

	/**
	 * Save Proof of Pickup/Delivery (Admin only)
	 */
	public function ajax_save_pod() {
		check_ajax_referer( 'ocb_admin_pod', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$pod_type = sanitize_text_field( $_POST['pod_type'] ?? '' ); // 'pickup' or 'delivery'
		$person_name = sanitize_text_field( $_POST['person_name'] ?? '' );

		if ( ! $booking_id || ! $pod_type || ! $person_name ) {
			wp_send_json_error( array( 'message' => 'Booking ID, type, and person name required.' ) );
		}

		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();

		// Handle signature upload
		$signature_data = '';
		if ( ! empty( $_POST['signature_data'] ) ) {
			// Base64 signature from canvas
			$signature_data = $_POST['signature_data'];
		} elseif ( ! empty( $_FILES['signature_file'] ) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$upload = wp_handle_upload( $_FILES['signature_file'], array( 'test_form' => false ) );
			if ( ! empty( $upload['url'] ) ) {
				$signature_data = $upload['url'];
			}
		}

		$now = current_time( 'mysql' );

		if ( $pod_type === 'pickup' ) {
			$update_data = array(
				'collected_by_name'    => $person_name,
				'collection_signature' => $signature_data,
				'collected_at'         => $now,
				'status'               => 'picked_up',
			);
		} elseif ( $pod_type === 'delivery' ) {
			$update_data = array(
				'delivered_to_name'   => $person_name,
				'delivery_signature'  => $signature_data,
				'delivered_at'        => $now,
				'status'              => 'delivered',
			);
		} else {
			wp_send_json_error( array( 'message' => 'Invalid POD type.' ) );
			return;
		}

		$result = $wpdb->update( $table, $update_data, array( 'id' => $booking_id ) );

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
		}

		// Trigger confirmation email
		if ( $pod_type === 'pickup' ) {
			OnRoute_Courier_Booking_Emails::send_collection_confirmation( $booking_id );
		} else {
			OnRoute_Courier_Booking_Emails::send_delivery_confirmation( $booking_id );
		}

		// Auto-generate invoice on delivery
		if ( $pod_type === 'delivery' ) {
			self::generate_invoice_for_booking( $booking_id );
		}

		wp_send_json_success( array( 'message' => ucfirst( $pod_type ) . ' confirmation saved and email sent.' ) );
	}

	/**
	 * Update job status (Admin only)
	 */
	public function ajax_update_job_status() {
		check_ajax_referer( 'ocb_admin_pod', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$new_status = sanitize_text_field( $_POST['status'] ?? '' );

		$valid_statuses = array( 'booked', 'picked_up', 'in_transit', 'delivered', 'completed', 'cancelled' );
		if ( ! $booking_id || ! in_array( $new_status, $valid_statuses ) ) {
			wp_send_json_error( array( 'message' => 'Invalid booking or status.' ) );
		}

		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		$wpdb->update( $table, array( 'status' => $new_status ), array( 'id' => $booking_id ) );

		// Auto-generate invoice when status changes to booked or higher
		$invoice_statuses = array( 'booked', 'picked_up', 'in_transit', 'delivered', 'completed' );
		if ( in_array( $new_status, $invoice_statuses ) ) {
			self::generate_invoice_for_booking( $booking_id );
		}

		wp_send_json_success( array( 'message' => 'Status updated to: ' . $new_status ) );
	}

	/**
	 * Resend confirmation email (Admin only)
	 */
	public function ajax_resend_confirmation() {
		check_ajax_referer( 'ocb_admin_pod', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		$type = sanitize_text_field( $_POST['type'] ?? '' ); // 'booking', 'pickup', 'delivery'

		if ( ! $booking_id || ! $type ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ) );
		}

		switch ( $type ) {
			case 'booking':
				// Remove duplicate check for resend
				global $wpdb;
				$logs_table = OnRoute_Courier_Booking_Database::get_email_logs_table();
				// Allow resend by not checking duplicates - call the method directly
				$booking_obj = new OnRoute_Courier_Booking_Booking();
				$b = $booking_obj->get( $booking_id );
				if ( $b ) {
					$subject = "Booking Confirmation: " . $b->booking_reference;
					$message = OnRoute_Courier_Booking_Emails::apply_branding(
						'<h2>Booking Confirmation (Resent)</h2><p>Your booking has been confirmed.</p>',
						$b->booking_reference
					);
					OnRoute_Courier_Booking_Emails::send_html_mail( $b->customer_email, $subject, $message, $booking_id );
					OnRoute_Courier_Booking_Emails::send_html_mail( 'admin@onroutecouriers.com', "Resent: " . $subject, $message, $booking_id );
				}
				break;
			case 'pickup':
				OnRoute_Courier_Booking_Emails::send_collection_confirmation( $booking_id );
				break;
			case 'delivery':
				OnRoute_Courier_Booking_Emails::send_delivery_confirmation( $booking_id );
				break;
			default:
				wp_send_json_error( array( 'message' => 'Invalid type.' ) );
				return;
		}

		wp_send_json_success( array( 'message' => ucfirst( $type ) . ' confirmation email resent.' ) );
	}

	/**
	 * Reply to support ticket (Admin only)
	 */
	public function ajax_reply_ticket() {
		check_ajax_referer( 'ocb_admin_pod', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		$reply = sanitize_textarea_field( $_POST['reply'] ?? '' );

		if ( ! $ticket_id || ! $reply ) {
			wp_send_json_error( array( 'message' => 'Ticket ID and reply required.' ) );
		}

		global $wpdb;
		$table = self::get_support_tickets_table();
		$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $ticket_id ) );

		if ( ! $ticket ) {
			wp_send_json_error( array( 'message' => 'Ticket not found.' ) );
		}

		$wpdb->update( $table, array(
			'admin_reply' => $reply,
			'replied_at'  => current_time( 'mysql' ),
			'status'      => 'in_progress',
			'updated_at'  => current_time( 'mysql' ),
		), array( 'id' => $ticket_id ) );

		// Email the customer
		$user = get_userdata( $ticket->user_id );
		if ( $user ) {
			$subject = 'Response to your support ticket #' . $ticket_id;
			$body = '<h2>Support Ticket Response</h2>';
			$body .= '<p><strong>Subject:</strong> ' . esc_html( $ticket->subject ) . '</p>';
			$body .= '<p><strong>Our Response:</strong></p>';
			$body .= '<div style="background:#f8f9fa; padding:15px; border-radius:8px; margin:15px 0;">' . nl2br( esc_html( $reply ) ) . '</div>';

			$branded = OnRoute_Courier_Booking_Emails::apply_branding( $body );
			OnRoute_Courier_Booking_Emails::send_html_mail( $user->user_email, $subject, $branded );
		}

		wp_send_json_success( array( 'message' => 'Reply sent to customer.' ) );
	}

	/**
	 * Close support ticket (Admin only)
	 */
	public function ajax_close_ticket() {
		check_ajax_referer( 'ocb_admin_pod', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		if ( ! $ticket_id ) {
			wp_send_json_error( array( 'message' => 'Invalid ticket.' ) );
		}

		global $wpdb;
		$table = self::get_support_tickets_table();
		$wpdb->update( $table, array(
			'status'     => 'closed',
			'updated_at' => current_time( 'mysql' ),
		), array( 'id' => $ticket_id ) );

		wp_send_json_success( array( 'message' => 'Ticket closed.' ) );
	}
}
