<?php
/**
 * Payment Dashboard
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Payment_Dashboard {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		// Action for sending payment request
		add_action( 'admin_post_ocb_send_payment_link', array( $this, 'send_payment_link' ) );
		// Action for deleting booking
		add_action( 'admin_post_ocb_delete_booking', array( $this, 'delete_booking' ) );
		// Action for exporting bookings
		add_action( 'admin_post_ocb_export_bookings', array( $this, 'export_bookings' ) );
	}

	public function add_menu_page() {
		add_submenu_page(
			'ocb-dashboard',
			'Payments',
			'Payments',
			'manage_options',
			'ocb-payments',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		
		// Pagination
		$per_page = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * $per_page;

		// Get total items
		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table" );
		$total_pages = ceil( $total_items / $per_page );

		// Get bookings
		$bookings = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
		
		// Base URL for pagination
		$base_url = admin_url( 'admin.php?page=ocb-payments' );

		?>
		<div class="wrap ocb-admin">
			<h1 class="wp-heading-inline">Payment Dashboard</h1>
			
			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block; margin-left: 10px;">
				<input type="hidden" name="action" value="ocb_export_bookings">
				<?php wp_nonce_field('ocb_export_bookings'); ?>
				<button type="submit" class="page-title-action">Export to CSV</button>
			</form>
			
			<hr class="wp-header-end">

			<?php if ( isset( $_GET['message'] ) ): ?>
				<div class="notice notice-success is-dismissible">
					<?php if ( $_GET['message'] === 'link_sent' ): ?>
						<p>Payment link sent successfully via email.</p>
						<?php 
						$manual_link = get_transient( 'ocb_payment_link_' . get_current_user_id() );
						if ( $manual_link ) : 
							delete_transient( 'ocb_payment_link_' . get_current_user_id() ); 
						?>
							<div style="background: #fff; padding: 10px; border: 1px solid #ddd; margin-top: 5px;">
								<strong>Manual Link:</strong> (Copy if needed)<br>
								<input type="text" value="<?php echo esc_url( $manual_link ); ?>" class="large-text" readonly onclick="this.select()" style="margin-top:5px;">
							</div>
						<?php endif; ?>
					<?php elseif ( $_GET['message'] === 'deleted' ): ?>
						<p>Booking deleted successfully.</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th>Ref</th>
						<th>Customer</th>
						<th>Total</th>
						<th>Paid</th>
						<th>Balance</th>
						<th>Status</th>
						<th>Method</th>
						<th>Date</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty($bookings) ): ?>
						<tr><td colspan="9">No bookings found.</td></tr>
					<?php else: ?>
						<?php foreach ( $bookings as $b ) : ?>
							<?php 
							$paid = isset( $b->amount_paid ) ? floatval( $b->amount_paid ) : 0;
							$total = floatval( $b->total_price );
							$balance = $total - $paid;
							$payment_status = $b->payment_status ?? 'unpaid';
							?>
							<tr>
								<td><strong><?php echo esc_html( $b->booking_reference ); ?></strong></td>
								<td>
									<?php echo esc_html( $b->customer_name ?? 'N/A' ); ?><br>
									<a href="mailto:<?php echo esc_attr( $b->customer_email ); ?>"><?php echo esc_html( $b->customer_email ); ?></a>
								</td>
								<td>£<?php echo number_format( $total, 2 ); ?></td>
								<td style="color: green;">£<?php echo number_format( $paid, 2 ); ?></td>
								<td style="color: red;">
									<?php echo $balance > 0 ? '£' . number_format( $balance, 2 ) : '-'; ?>
								</td>
								<td>
									<span class="ocb-payment-badge ocb-payment-<?php echo esc_attr( $payment_status ); ?>">
										<?php echo ucfirst( $payment_status ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $b->payment_method ?? $b->payment_mode ?? '-' ); ?></td>
								<td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $b->created_at ) ); ?></td>
								<td>
									<div style="display: flex; gap: 5px;">
										<?php if ( $balance > 0.01 ) : ?>
											<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
												<input type="hidden" name="action" value="ocb_send_payment_link">
												<input type="hidden" name="booking_id" value="<?php echo $b->id; ?>">
												<?php wp_nonce_field( 'ocb_send_payment_link_' . $b->id ); ?>
												<button type="submit" class="button button-small" onclick="return confirm('Send payment link?')">Pay Link</button>
											</form>
										<?php endif; ?>

										<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
											<input type="hidden" name="action" value="ocb_delete_booking">
											<input type="hidden" name="booking_id" value="<?php echo $b->id; ?>">
											<?php wp_nonce_field( 'ocb_delete_booking_' . $b->id ); ?>
											<button type="submit" class="button button-small button-link-delete" onclick="return confirm('Permanently delete this booking? This cannot be undone.')">
												<span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
											</button>
										</form>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num"><?php echo $total_items; ?> items</span>
						<?php
						$prev_page = $current_page - 1;
						$next_page = $current_page + 1;
						
						if ( $current_page > 1 ) {
							echo '<a class="prev-page button" href="' . add_query_arg( 'paged', $prev_page, $base_url ) . '">&lsaquo;</a>';
						} else {
							echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
						}

						echo '<span class="paging-input"><span class="current-page">' . $current_page . '</span> of <span class="total-pages">' . $total_pages . '</span></span>';

						if ( $current_page < $total_pages ) {
							echo '<a class="next-page button" href="' . add_query_arg( 'paged', $next_page, $base_url ) . '">&rsaquo;</a>';
						} else {
							echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
						}
						?>
					</div>
				</div>
			<?php endif; ?>

		</div>
		<style>
			.ocb-payment-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; }
			.ocb-payment-paid { background: #d4edda; color: #155724; }
			.ocb-payment-unpaid { background: #f8d7da; color: #721c24; }
			.ocb-payment-pending { background: #fff3cd; color: #856404; }
			.ocb-payment-partially-paid { background: #cce5ff; color: #004085; }
			.button-link-delete { color: #a00; }
			.button-link-delete:hover { color: #dc3232; background: #fff; border-color: #a00; }
		</style>
		<?php
	}

	/**
	 * Delete Booking
	 */
	public function delete_booking() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		
		$booking_id = intval( $_POST['booking_id'] );
		check_admin_referer( 'ocb_delete_booking_' . $booking_id );

		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		$wpdb->delete( $table, array( 'id' => $booking_id ) );

		wp_redirect( admin_url( 'admin.php?page=ocb-payments&message=deleted' ) );
		exit;
	}

	/**
	 * Export Bookings to CSV
	 */
	public function export_bookings() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'ocb_export_bookings' );

		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		$bookings = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

		if ( empty( $bookings ) ) {
			wp_die( 'No bookings to export.' );
		}

		// Clean up output buffer
		if ( ob_get_level() ) ob_end_clean();

		$filename = 'onroute_bookings_' . date('Y-m-d') . '.csv';
		
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		// Output Header
		fputcsv( $output, array_keys( $bookings[0] ) );

		// Output Rows
		foreach ( $bookings as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle sending payment link
	 */
	public function send_payment_link() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		
		$booking_id = intval( $_POST['booking_id'] );
		check_admin_referer( 'ocb_send_payment_link_' . $booking_id );

		$b = $booking->get( $booking_id );

		if ( ! $b ) wp_die( 'Booking not found' );

		// Generate Payment Link (We'll use Stripe Checkout again)
		// We'll calculate outstanding balance
		$paid = isset( $b->amount_paid ) ? floatval( $b->amount_paid ) : 0;
		$total = floatval( $b->total_price );
		$outstanding = $total - $paid;

		if ( $outstanding <= 0 ) {
			wp_die( 'No outstanding balance' );
		}

		$stripe = new OnRoute_Courier_Booking_Stripe_API();
		
		// URLs
		$success_url = home_url( '/booking-confirmation/?ref=' . $b->booking_reference . '&session_id={CHECKOUT_SESSION_ID}' );
		$cancel_url = home_url( '/booking-confirmation/?ref=' . $b->booking_reference ); // Back to confirmation

		$session = $stripe->create_checkout_session( 
			$b, 
			$outstanding, 
			'balance', // Mode is balance payment
			$success_url, 
			$cancel_url 
		);

		if ( is_wp_error( $session ) ) {
			wp_die( 'Stripe Error: ' . $session->get_error_message() );
		}

		$payment_url = $session['url'];

		// Send Email
		$to = $b->customer_email;
		$subject = 'Payment Request for Booking ' . $b->booking_reference;
		$message = "Dear " . ($b->customer_name ?? 'Customer') . ",\n\n";
		$message .= "Please click the link below to pay the outstanding balance of £" . number_format( $outstanding, 2 ) . " for your booking.\n\n";
		$message .= $payment_url . "\n\n";
		$message .= "Thank you,\nOnRoute Couriers";
		$headers = array('Content-Type: text/plain; charset=UTF-8');

		$sent = wp_mail( $to, $subject, $message, $headers );

		// Save link to session/transient so admin can copy it manually if email fails
		set_transient( 'ocb_payment_link_' . get_current_user_id(), $payment_url, 300 ); // Keep for 5 mins

		// Using query arg to display manual link message
		wp_redirect( admin_url( 'admin.php?page=ocb-payments&message=link_sent' ) );
		exit;

	}
}

