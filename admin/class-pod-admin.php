<?php
/**
 * Admin POD (Proof of Delivery) & Job Management
 *
 * Adds POD entry forms, status management, and support ticket handling to admin.
 *
 * @package OnRoute_Courier_Booking
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_POD_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_items' ), 25 );
	}

	private function format_price( $amount ) {
		return '£' . number_format( (float) $amount, 2 );
	}

	public function add_menu_items() {
		add_submenu_page(
			'ocb-dashboard',
			__( 'Active Jobs & POD', 'onroute-courier-booking' ),
			__( 'Active Jobs', 'onroute-courier-booking' ),
			'manage_options',
			'ocb-job-management',
			array( $this, 'render_job_management_page' )
		);

		add_submenu_page(
			'ocb-dashboard',
			__( 'Support Tickets', 'onroute-courier-booking' ),
			__( 'Support Tickets', 'onroute-courier-booking' ),
			'manage_options',
			'ocb-support-tickets',
			array( $this, 'render_support_tickets_page' )
		);
	}

	/**
	 * Render Job Management / POD Page
	 */
	public function render_job_management_page() {
		$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'list';
		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;

		if ( $view === 'detail' && $booking_id ) {
			$this->render_job_detail( $booking_id );
		} else {
			$this->render_job_list();
		}
	}

	/**
	 * Job List View
	 */
	private function render_job_list() {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$where = "WHERE 1=1";
		$params = array();

		if ( $status_filter ) {
			$where .= " AND status = %s";
			$params[] = $status_filter;
		}

		if ( ! empty( $params ) ) {
			$bookings = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 100", $params
			) );
		} else {
			$bookings = $wpdb->get_results(
				"SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 100"
			);
		}
		?>
		<div class="wrap">
			<h1><?php _e( 'Job Management & POD', 'onroute-courier-booking' ); ?></h1>
			<p><?php _e( 'Manage pickup/delivery confirmations, update job status, and handle proof of delivery.', 'onroute-courier-booking' ); ?></p>

			<!-- Status filter -->
			<div class="tablenav top" style="padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px;">
				<form method="get">
					<input type="hidden" name="page" value="ocb-job-management">
					<select name="status">
						<option value=""><?php _e( 'All Statuses', 'onroute-courier-booking' ); ?></option>
						<?php foreach ( array( 'booked', 'picked_up', 'in_transit', 'delivered', 'completed', 'cancelled' ) as $s ) : ?>
							<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status_filter, $s ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $s ) ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="submit" class="button" value="<?php _e( 'Filter', 'onroute-courier-booking' ); ?>">
					<a href="<?php echo admin_url( 'admin.php?page=ocb-job-management' ); ?>" class="button"><?php _e( 'Reset', 'onroute-courier-booking' ); ?></a>
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:140px;"><?php _e( 'Reference', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Route', 'onroute-courier-booking' ); ?></th>
						<th style="width:100px;"><?php _e( 'Date', 'onroute-courier-booking' ); ?></th>
						<th style="width:100px;"><?php _e( 'Status', 'onroute-courier-booking' ); ?></th>
						<th style="width:70px;"><?php _e( 'Pickup', 'onroute-courier-booking' ); ?></th>
						<th style="width:70px;"><?php _e( 'Delivery', 'onroute-courier-booking' ); ?></th>
						<th style="width:90px;"><?php _e( 'Total', 'onroute-courier-booking' ); ?></th>
						<th style="width:100px;"><?php _e( 'Actions', 'onroute-courier-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $bookings ) ) : foreach ( $bookings as $b ) :
						$has_pickup = ! empty( $b->collected_at );
						$has_delivery = ! empty( $b->delivered_at );
					?>
						<tr>
							<td><strong><?php echo esc_html( $b->booking_reference ); ?></strong></td>
							<td>
								<strong><?php echo esc_html( $b->pickup_postcode ); ?></strong> → <strong><?php echo esc_html( $b->delivery_postcode ); ?></strong>
							</td>
							<td><?php echo esc_html( date( 'j M Y', strtotime( $b->collection_date ) ) ); ?></td>
							<td>
								<span class="ocb-status-badge ocb-status-<?php echo esc_attr( $b->status ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $b->status ) ) ); ?>
								</span>
							</td>
							<td style="text-align:center;">
								<?php if ( $has_pickup ) : ?>
									<span style="color:#28a745; font-weight:700;" title="<?php echo esc_attr( $b->collected_by_name . ' at ' . $b->collected_at ); ?>">✓</span>
								<?php else : ?>
									<span style="color:#dc3545;">✗</span>
								<?php endif; ?>
							</td>
							<td style="text-align:center;">
								<?php if ( $has_delivery ) : ?>
									<span style="color:#28a745; font-weight:700;" title="<?php echo esc_attr( $b->delivered_to_name . ' at ' . $b->delivered_at ); ?>">✓</span>
								<?php else : ?>
									<span style="color:#dc3545;">✗</span>
								<?php endif; ?>
							</td>
							<td><?php echo $this->format_price( $b->total_price ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocb-job-management&view=detail&booking_id=' . $b->id ) ); ?>" class="button button-small">
									<?php _e( 'Manage', 'onroute-courier-booking' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="8" style="text-align:center; padding:30px;"><?php _e( 'No bookings found.', 'onroute-courier-booking' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<style>
			.ocb-status-badge { padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; display: inline-block; }
			.ocb-status-pending { background: #fff8e5; color: #856404; border: 1px solid #ffeeba; }
			.ocb-status-confirmed { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
			.ocb-status-collected { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
			.ocb-status-in_transit { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
			.ocb-status-delivered, .ocb-status-completed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
			.ocb-status-cancelled { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
		</style>
		<?php
	}

	/**
	 * Job Detail View with POD Management
	 */
	private function render_job_detail( $booking_id ) {
		$booking_obj = new OnRoute_Courier_Booking_Booking();
		$b = $booking_obj->get( $booking_id );

		if ( ! $b ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Booking not found.</p></div></div>';
			return;
		}

		$notes = json_decode( $b->notes ?? '{}', true ) ?: array();
		$nonce = wp_create_nonce( 'ocb_admin_pod' );
		?>
		<div class="wrap">
			<h1>
				<?php _e( 'Job Detail', 'onroute-courier-booking' ); ?>: <strong style="color:#e31837;"><?php echo esc_html( $b->booking_reference ); ?></strong>
			</h1>
			<p>
				<a href="<?php echo admin_url( 'admin.php?page=ocb-job-management' ); ?>">&larr; <?php _e( 'Back to Job List', 'onroute-courier-booking' ); ?></a>
			</p>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">

				<!-- Left Column: Booking Info -->
				<div>
					<div class="card" style="padding: 20px;">
						<h3 style="margin-top:0;"><?php _e( 'Booking Information', 'onroute-courier-booking' ); ?></h3>
						<table class="form-table">
							<tr><th>Status</th><td>
								<select id="ocb-job-status" style="min-width: 150px;">
					<?php foreach ( array( 'booked', 'picked_up', 'in_transit', 'delivered', 'completed', 'cancelled' ) as $s ) : ?>
										<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $b->status, $s ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $s ) ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<button class="button" id="ocb-update-status" data-booking="<?php echo $b->id; ?>"><?php _e( 'Update', 'onroute-courier-booking' ); ?></button>
								<span id="ocb-status-msg" style="margin-left:10px;"></span>
							</td></tr>
							<tr><th>Customer</th><td><?php echo esc_html( $b->customer_name ?: $b->customer_email ); ?></td></tr>
							<tr><th>Phone</th><td><?php echo esc_html( $b->customer_phone ); ?></td></tr>
							<tr><th>Pickup</th><td><?php echo esc_html( $b->pickup_address ); ?><br><strong><?php echo esc_html( $b->pickup_postcode ); ?></strong></td></tr>
							<tr><th>Delivery</th><td><?php echo esc_html( $b->delivery_address ); ?><br><strong><?php echo esc_html( $b->delivery_postcode ); ?></strong></td></tr>
							<tr><th>Collection</th><td><?php echo esc_html( date( 'j M Y H:i', strtotime( $b->collection_date . ' ' . $b->collection_time ) ) ); ?></td></tr>
							<tr><th>Total</th><td><strong><?php echo $this->format_price( $b->total_price ); ?></strong></td></tr>
							<tr><th>Payment</th><td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $b->payment_method ?: 'N/A' ) ) ); ?> — <?php echo esc_html( ucfirst( $b->payment_status ) ); ?></td></tr>
						</table>

						<?php if ( ! empty( $notes ) ) : ?>
							<h4>Contact Notes</h4>
							<table class="form-table">
								<?php if ( ! empty( $notes['collection_contact_name'] ) ) : ?>
									<tr><th>Collection Contact</th><td><?php echo esc_html( $notes['collection_contact_name'] ); ?> — <?php echo esc_html( $notes['collection_contact_phone'] ?? '' ); ?></td></tr>
								<?php endif; ?>
								<?php if ( ! empty( $notes['delivery_contact_name'] ) ) : ?>
									<tr><th>Delivery Contact</th><td><?php echo esc_html( $notes['delivery_contact_name'] ); ?> — <?php echo esc_html( $notes['delivery_contact_phone'] ?? '' ); ?></td></tr>
								<?php endif; ?>
								<?php if ( ! empty( $notes['booked_by_company'] ) ) : ?>
									<tr><th>Company</th><td><?php echo esc_html( $notes['booked_by_company'] ); ?></td></tr>
								<?php endif; ?>
							</table>
						<?php endif; ?>

						<!-- Resend Emails -->
						<h4 style="margin-top: 25px;"><?php _e( 'Resend Emails', 'onroute-courier-booking' ); ?></h4>
						<p>
							<button class="button ocb-resend-email" data-booking="<?php echo $b->id; ?>" data-type="booking">📧 Booking Confirmation</button>
							<?php if ( ! empty( $b->collected_at ) ) : ?>
								<button class="button ocb-resend-email" data-booking="<?php echo $b->id; ?>" data-type="pickup">📧 Pickup Confirmation</button>
							<?php endif; ?>
							<?php if ( ! empty( $b->delivered_at ) ) : ?>
								<button class="button ocb-resend-email" data-booking="<?php echo $b->id; ?>" data-type="delivery">📧 Delivery Confirmation</button>
							<?php endif; ?>
						</p>
						<div id="ocb-resend-msg"></div>
					</div>
				</div>

				<!-- Right Column: POD -->
				<div>
					<!-- PICKUP CONFIRMATION -->
					<div class="card" style="padding: 20px; margin-bottom: 20px; border-left: 4px solid <?php echo ! empty( $b->collected_at ) ? '#28a745' : '#ffc107'; ?>;">
						<h3 style="margin-top:0;">📦 <?php _e( 'Pickup Confirmation', 'onroute-courier-booking' ); ?></h3>
						<?php if ( ! empty( $b->collected_at ) ) : ?>
							<p><strong>Completed:</strong> <span style="color:#28a745; font-weight:700;">Yes</span></p>
							<p><strong>Date & Time:</strong> <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $b->collected_at ) ) ); ?></p>
							<p><strong>Handed over by:</strong> <?php echo esc_html( $b->collected_by_name ); ?></p>
							<?php if ( ! empty( $b->collection_signature ) ) : ?>
								<p><strong>Signature:</strong><br>
								<?php if ( strpos( $b->collection_signature, 'data:image' ) === 0 || strpos( $b->collection_signature, 'http' ) === 0 ) : ?>
									<img src="<?php echo esc_url( $b->collection_signature ); ?>" style="max-width:300px; border:1px solid #ddd; background:#fff; padding:5px; border-radius:4px;">
								<?php endif; ?>
								</p>
							<?php endif; ?>
							<hr>
							<p><strong>Replace signature:</strong></p>
						<?php else : ?>
							<p><strong>Completed:</strong> <span style="color:#dc3545; font-weight:700;">No</span></p>
						<?php endif; ?>

						<div class="ocb-pod-form">
							<p>
								<label><strong><?php _e( 'Name of person handing over:', 'onroute-courier-booking' ); ?></strong></label><br>
								<input type="text" id="ocb-pickup-name" value="<?php echo esc_attr( $b->collected_by_name ?? '' ); ?>" class="regular-text">
							</p>
							<p>
								<label><strong><?php _e( 'Signature (upload image):', 'onroute-courier-booking' ); ?></strong></label><br>
								<input type="file" id="ocb-pickup-sig" accept="image/*">
							</p>
							<button class="button button-primary ocb-save-pod" data-booking="<?php echo $b->id; ?>" data-type="pickup">
								<?php echo ! empty( $b->collected_at ) ? __( 'Update Pickup POD', 'onroute-courier-booking' ) : __( 'Confirm Pickup', 'onroute-courier-booking' ); ?>
							</button>
							<span class="ocb-pod-msg" data-type="pickup"></span>
						</div>
					</div>

					<!-- DELIVERY CONFIRMATION -->
					<div class="card" style="padding: 20px; border-left: 4px solid <?php echo ! empty( $b->delivered_at ) ? '#28a745' : '#ffc107'; ?>;">
						<h3 style="margin-top:0;">🚚 <?php _e( 'Delivery Confirmation', 'onroute-courier-booking' ); ?></h3>
						<?php if ( ! empty( $b->delivered_at ) ) : ?>
							<p><strong>Completed:</strong> <span style="color:#28a745; font-weight:700;">Yes</span></p>
							<p><strong>Date & Time:</strong> <?php echo esc_html( date( 'd/m/Y H:i', strtotime( $b->delivered_at ) ) ); ?></p>
							<p><strong>Received by:</strong> <?php echo esc_html( $b->delivered_to_name ); ?></p>
							<?php if ( ! empty( $b->delivery_signature ) ) : ?>
								<p><strong>Signature:</strong><br>
								<?php if ( strpos( $b->delivery_signature, 'data:image' ) === 0 || strpos( $b->delivery_signature, 'http' ) === 0 ) : ?>
									<img src="<?php echo esc_url( $b->delivery_signature ); ?>" style="max-width:300px; border:1px solid #ddd; background:#fff; padding:5px; border-radius:4px;">
								<?php endif; ?>
								</p>
							<?php endif; ?>
							<hr>
							<p><strong>Replace signature:</strong></p>
						<?php else : ?>
							<p><strong>Completed:</strong> <span style="color:#dc3545; font-weight:700;">No</span></p>
						<?php endif; ?>

						<div class="ocb-pod-form">
							<p>
								<label><strong><?php _e( 'Name of receiver:', 'onroute-courier-booking' ); ?></strong></label><br>
								<input type="text" id="ocb-delivery-name" value="<?php echo esc_attr( $b->delivered_to_name ?? '' ); ?>" class="regular-text">
							</p>
							<p>
								<label><strong><?php _e( 'Signature (upload image):', 'onroute-courier-booking' ); ?></strong></label><br>
								<input type="file" id="ocb-delivery-sig" accept="image/*">
							</p>
							<button class="button button-primary ocb-save-pod" data-booking="<?php echo $b->id; ?>" data-type="delivery">
								<?php echo ! empty( $b->delivered_at ) ? __( 'Update Delivery POD', 'onroute-courier-booking' ) : __( 'Confirm Delivery', 'onroute-courier-booking' ); ?>
							</button>
							<span class="ocb-pod-msg" data-type="delivery"></span>
						</div>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var nonce = '<?php echo $nonce; ?>';

			// Update status
			$('#ocb-update-status').on('click', function() {
				var $btn = $(this);
				$btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'ocb_update_job_status',
					nonce: nonce,
					booking_id: $btn.data('booking'),
					status: $('#ocb-job-status').val()
				}, function(res) {
					$('#ocb-status-msg').html(res.success ? '<span style="color:green">✓ ' + res.data.message + '</span>' : '<span style="color:red">✗ ' + res.data.message + '</span>');
					$btn.prop('disabled', false);
				});
			});

			// Save POD
			$('.ocb-save-pod').on('click', function() {
				var $btn = $(this);
				var type = $btn.data('type');
				var bookingId = $btn.data('booking');
				var nameField = type === 'pickup' ? '#ocb-pickup-name' : '#ocb-delivery-name';
				var sigField = type === 'pickup' ? '#ocb-pickup-sig' : '#ocb-delivery-sig';
				var personName = $(nameField).val().trim();

				if (!personName) {
					alert('Please enter the person\'s name.');
					return;
				}

				var formData = new FormData();
				formData.append('action', 'ocb_save_pod');
				formData.append('nonce', nonce);
				formData.append('booking_id', bookingId);
				formData.append('pod_type', type);
				formData.append('person_name', personName);

				var fileInput = $(sigField)[0];
				if (fileInput.files.length > 0) {
					formData.append('signature_file', fileInput.files[0]);
				}

				$btn.prop('disabled', true).text('Saving...');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(res) {
						$('.ocb-pod-msg[data-type="' + type + '"]').html(
							res.success ? '<span style="color:green; margin-left:10px;">✓ ' + res.data.message + '</span>' : '<span style="color:red; margin-left:10px;">✗ ' + res.data.message + '</span>'
						);
						if (res.success) {
							setTimeout(function() { location.reload(); }, 1500);
						}
						$btn.prop('disabled', false).text(type === 'pickup' ? 'Confirm Pickup' : 'Confirm Delivery');
					}
				});
			});

			// Resend emails
			$('.ocb-resend-email').on('click', function() {
				var $btn = $(this);
				$btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'ocb_resend_confirmation',
					nonce: nonce,
					booking_id: $btn.data('booking'),
					type: $btn.data('type')
				}, function(res) {
					$('#ocb-resend-msg').html(res.success ? '<p style="color:green;">✓ ' + res.data.message + '</p>' : '<p style="color:red;">✗ ' + res.data.message + '</p>');
					$btn.prop('disabled', false);
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Support Tickets Admin Page
	 */
	public function render_support_tickets_page() {
		$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'list';
		$ticket_id = isset( $_GET['ticket_id'] ) ? absint( $_GET['ticket_id'] ) : 0;

		if ( $view === 'detail' && $ticket_id ) {
			$this->render_ticket_detail( $ticket_id );
		} else {
			$this->render_tickets_list();
		}
	}

	private function render_tickets_list() {
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$tickets = OnRoute_Dashboard_Extensions::get_all_tickets( $status_filter );
		$nonce = wp_create_nonce( 'ocb_admin_pod' );
		?>
		<div class="wrap">
			<h1><?php _e( 'Support Tickets', 'onroute-courier-booking' ); ?></h1>

			<div class="tablenav top" style="padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 15px;">
				<form method="get">
					<input type="hidden" name="page" value="ocb-support-tickets">
					<select name="status">
						<option value="">All</option>
						<option value="open" <?php selected( $status_filter, 'open' ); ?>>Open</option>
						<option value="in_progress" <?php selected( $status_filter, 'in_progress' ); ?>>In Progress</option>
						<option value="closed" <?php selected( $status_filter, 'closed' ); ?>>Closed</option>
					</select>
					<input type="submit" class="button" value="Filter">
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:40px;">#</th>
						<th><?php _e( 'Subject', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Customer', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Booking', 'onroute-courier-booking' ); ?></th>
						<th style="width:100px;"><?php _e( 'Status', 'onroute-courier-booking' ); ?></th>
						<th style="width:120px;"><?php _e( 'Date', 'onroute-courier-booking' ); ?></th>
						<th style="width:100px;"><?php _e( 'Actions', 'onroute-courier-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $tickets ) ) : foreach ( $tickets as $t ) :
						$user = get_userdata( $t->user_id );
					?>
						<tr>
							<td><?php echo $t->id; ?></td>
							<td><strong><?php echo esc_html( $t->subject ); ?></strong></td>
							<td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : 'Unknown'; ?></td>
							<td><?php echo $t->booking_reference ? esc_html( $t->booking_reference ) : '-'; ?></td>
							<td>
								<span class="ocb-status-badge ocb-status-<?php echo esc_attr( $t->status ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $t->status ) ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( date( 'j M Y H:i', strtotime( $t->created_at ) ) ); ?></td>
							<td>
								<a href="<?php echo admin_url( 'admin.php?page=ocb-support-tickets&view=detail&ticket_id=' . $t->id ); ?>" class="button button-small">View</a>
							</td>
						</tr>
					<?php endforeach; else : ?>
						<tr><td colspan="7" style="text-align:center; padding:30px;">No tickets found.</td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<style>
			.ocb-status-badge { padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; display: inline-block; }
			.ocb-status-open { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
			.ocb-status-in_progress { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
			.ocb-status-closed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		</style>
		<?php
	}

	private function render_ticket_detail( $ticket_id ) {
		global $wpdb;
		$table = OnRoute_Dashboard_Extensions::get_support_tickets_table();
		$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $ticket_id ) );

		if ( ! $ticket ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>Ticket not found.</p></div></div>';
			return;
		}

		$user = get_userdata( $ticket->user_id );
		$nonce = wp_create_nonce( 'ocb_admin_pod' );
		?>
		<div class="wrap">
			<h1><?php _e( 'Support Ticket', 'onroute-courier-booking' ); ?> #<?php echo $ticket->id; ?></h1>
			<p><a href="<?php echo admin_url( 'admin.php?page=ocb-support-tickets' ); ?>">&larr; Back to Tickets</a></p>

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
				<div>
					<div class="card" style="padding: 20px;">
						<h3 style="margin-top:0;"><?php echo esc_html( $ticket->subject ); ?></h3>
						<p style="color:#666; font-size:13px;">
							From: <strong><?php echo $user ? esc_html( $user->display_name ) : 'Unknown'; ?></strong> —
							<?php echo esc_html( date( 'j M Y H:i', strtotime( $ticket->created_at ) ) ); ?>
							<?php if ( $ticket->booking_reference ) : ?>
								— Booking: <strong><?php echo esc_html( $ticket->booking_reference ); ?></strong>
							<?php endif; ?>
						</p>
						<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
							<?php echo nl2br( esc_html( $ticket->message ) ); ?>
						</div>
						<?php if ( $ticket->attachment_url ) : ?>
							<p><strong>Attachment:</strong> <a href="<?php echo esc_url( $ticket->attachment_url ); ?>" target="_blank">View File</a></p>
						<?php endif; ?>

						<?php if ( $ticket->admin_reply ) : ?>
							<hr>
							<h4>Admin Reply <small style="color:#999;">(<?php echo esc_html( date( 'j M Y H:i', strtotime( $ticket->replied_at ) ) ); ?>)</small></h4>
							<div style="background: #e7f4e4; padding: 15px; border-radius: 8px;">
								<?php echo nl2br( esc_html( $ticket->admin_reply ) ); ?>
							</div>
						<?php endif; ?>

						<?php if ( $ticket->status !== 'closed' ) : ?>
							<hr>
							<h4><?php _e( 'Reply to Customer', 'onroute-courier-booking' ); ?></h4>
							<textarea id="ocb-ticket-reply" style="width:100%; min-height:120px; padding:10px; border-radius:8px; border:1px solid #ddd;"></textarea>
							<p style="margin-top:10px;">
								<button class="button button-primary" id="ocb-send-reply" data-ticket="<?php echo $ticket->id; ?>">Send Reply</button>
								<button class="button" id="ocb-close-ticket" data-ticket="<?php echo $ticket->id; ?>" style="margin-left:10px;">Close Ticket</button>
							</p>
							<div id="ocb-ticket-msg"></div>
						<?php endif; ?>
					</div>
				</div>

				<div>
					<div class="card" style="padding: 20px;">
						<h4 style="margin-top:0;">Ticket Info</h4>
						<p><strong>Status:</strong>
							<span class="ocb-status-badge ocb-status-<?php echo esc_attr( $ticket->status ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $ticket->status ) ) ); ?>
							</span>
						</p>
						<p><strong>Created:</strong> <?php echo esc_html( date( 'j M Y H:i', strtotime( $ticket->created_at ) ) ); ?></p>
						<?php if ( $ticket->replied_at ) : ?>
							<p><strong>Last Reply:</strong> <?php echo esc_html( date( 'j M Y H:i', strtotime( $ticket->replied_at ) ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var nonce = '<?php echo $nonce; ?>';

			$('#ocb-send-reply').on('click', function() {
				var reply = $('#ocb-ticket-reply').val().trim();
				if (!reply) { alert('Please enter a reply.'); return; }
				var $btn = $(this);
				$btn.prop('disabled', true).text('Sending...');
				$.post(ajaxurl, {
					action: 'ocb_reply_ticket',
					nonce: nonce,
					ticket_id: $btn.data('ticket'),
					reply: reply
				}, function(res) {
					$('#ocb-ticket-msg').html(res.success ? '<p style="color:green;">✓ ' + res.data.message + '</p>' : '<p style="color:red;">✗ ' + res.data.message + '</p>');
					if (res.success) setTimeout(function() { location.reload(); }, 1500);
					$btn.prop('disabled', false).text('Send Reply');
				});
			});

			$('#ocb-close-ticket').on('click', function() {
				if (!confirm('Close this ticket?')) return;
				var $btn = $(this);
				$btn.prop('disabled', true);
				$.post(ajaxurl, {
					action: 'ocb_close_ticket',
					nonce: nonce,
					ticket_id: $btn.data('ticket')
				}, function(res) {
					if (res.success) location.reload();
					$btn.prop('disabled', false);
				});
			});
		});
		</script>

		<style>
			.ocb-status-badge { padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; display: inline-block; }
			.ocb-status-open { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
			.ocb-status-in_progress { background: #cce5ff; color: #004085; border: 1px solid #b8daff; }
			.ocb-status-closed { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		</style>
		<?php
	}
}
