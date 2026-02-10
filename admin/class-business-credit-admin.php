<?php
/**
 * Admin Business Credit management
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Business_Credit_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_item' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'wp_ajax_ocb_reset_balance', array( $this, 'ajax_reset_balance' ) );
	}

	/**
	 * Standalone price formatter (No WooCommerce needed)
	 */
	private function format_price( $amount ) {
		return '£' . number_format( (float) $amount, 2 );
	}

	/**
	 * Add menu item under OnRoute Booking
	 */
	public function add_menu_item() {
		add_submenu_page(
			'ocb-dashboard',
			__( 'Business Credit Accounts', 'onroute-courier-booking' ),
			__( 'Business Credit', 'onroute-courier-booking' ),
			'manage_options',
			'ocb-business-credit',
			array( $this, 'render_admin_page' )
		);
		
		// Add separate menu for Agent Bookings
		add_submenu_page(
			'ocb-dashboard',
			__( 'Agent Bookings', 'onroute-courier-booking' ),
			__( 'Agent Bookings', 'onroute-courier-booking' ),
			'manage_options',
			'ocb-agent-bookings',
			array( $this, 'render_agent_bookings_page' )
		);
	}

	/**
	 * Handle admin actions (approve, reject, update)
	 */
	public function handle_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! $action || ! $id ) {
			return;
		}

		check_admin_referer( 'ocb_credit_action' );

		switch ( $action ) {
			case 'approve':
				OnRoute_Business_Credit::update_status( $id, 'approved', get_current_user_id() );
				break;
			case 'suspend':
				OnRoute_Business_Credit::update_status( $id, 'suspended', get_current_user_id() );
				break;
			case 'activate':
				OnRoute_Business_Credit::update_status( $id, 'approved', get_current_user_id() );
				break;
			case 'update_credit':
				$limit = isset( $_POST['credit_limit'] ) ? floatval( $_POST['credit_limit'] ) : 0;
				$balance = isset( $_POST['current_balance'] ) ? floatval( $_POST['current_balance'] ) : 0;
				OnRoute_Business_Credit::save_account( array(
					'id'              => $id,
					'credit_limit'    => $limit,
					'current_balance' => $balance,
				) );
				break;
		}

		wp_redirect( remove_query_arg( array( 'action', 'id', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : 'list';
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( $view === 'edit' && $id ) {
			$this->render_edit_view( $id );
		} else {
			$this->render_list_view();
		}
	}

	/**
	 * Render list of accounts
	 */
	private function render_list_view() {
		$accounts = OnRoute_Business_Credit::get_all_accounts();
		?>
		<div class="wrap">
			<h1><?php _e( 'Business Credit Accounts', 'onroute-courier-booking' ); ?></h1>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e( 'Company Name', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'User', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Phone', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Credit Limit', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Current Balance', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Status', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Actions', 'onroute-courier-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $accounts ) ) : ?>
						<?php foreach ( $accounts as $account ) : 
							$user = get_userdata( $account->user_id );
							$phone = $user ? get_user_meta( $user->ID, 'billing_phone', true ) : '';
							?>
							<tr>
								<td><strong><?php echo esc_html( $account->company_name ); ?></strong></td>
								<td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : 'Unknown'; ?></td>
								<td><?php echo esc_html( $phone ? $phone : '-' ); ?></td>
								<td><?php echo $this->format_price( $account->credit_limit ); ?></td>
								<td>
									<?php echo $this->format_price( $account->current_balance ); ?>
									<?php if ( $account->current_balance == $account->credit_limit && $account->credit_limit > 0 ) : ?>
										<span style="color: #dc3545; font-size: 11px; display: block; margin-top: 3px;">⚠️ Needs Reset</span>
									<?php endif; ?>
								</td>
								<td>
									<span class="status-badge status-<?php echo esc_attr( $account->account_status ); ?>">
										<?php echo ucfirst( esc_html( $account->account_status ) ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'edit', 'id' => $account->id ) ) ); ?>" class="button"><?php _e( 'Manage', 'onroute-courier-booking' ); ?></a>
									
									<?php if ( $account->account_status === 'pending' ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'approve', 'id' => $account->id ) ), 'ocb_credit_action' ) ); ?>" class="button button-primary"><?php _e( 'Approve', 'onroute-courier-booking' ); ?></a>
									<?php elseif ( $account->account_status === 'approved' ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'suspend', 'id' => $account->id ) ), 'ocb_credit_action' ) ); ?>" class="button"><?php _e( 'Suspend', 'onroute-courier-booking' ); ?></a>
									<?php elseif ( $account->account_status === 'suspended' ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'activate', 'id' => $account->id ) ), 'ocb_credit_action' ) ); ?>" class="button"><?php _e( 'Activate', 'onroute-courier-booking' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="6"><?php _e( 'No business accounts found.', 'onroute-courier-booking' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<style>
				.status-badge { padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; }
				.status-pending { background: #fff8e5; color: #856404; border: 1px solid #ffeeba; }
				.status-approved { background: #e7f4e4; color: #1e4620; border: 1px solid #c3e6cb; }
				.status-suspended { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
			</style>
		</div>
		<?php
	}

	/**
	 * Render edit view
	 */
	private function render_edit_view( $id ) {
		$account = OnRoute_Business_Credit::get_account( $id );
		if ( ! $account ) {
			echo '<div class="notice notice-error"><p>' . __( 'Account not found.', 'onroute-courier-booking' ) . '</p></div>';
			return;
		}
		$user = get_userdata( $account->user_id );
		$phone = $user ? get_user_meta( $user->ID, 'billing_phone', true ) : '';
		$address = $user ? get_user_meta( $user->ID, 'billing_address_1', true ) : '';
		?>
		<div class="wrap">
			<h1><?php _e( 'Manage Business Credit Account', 'onroute-courier-booking' ); ?></h1>
			<p><a href="<?php echo esc_url( remove_query_arg( array( 'view', 'id' ) ) ); ?>">&larr; <?php _e( 'Back to list', 'onroute-courier-booking' ); ?></a></p>

			<div class="card" style="max-width: 600px;">
				<form method="post" action="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'update_credit', 'id' => $id ) ), 'ocb_credit_action' ) ); ?>">
					<table class="form-table">
						<tr>
							<th><?php _e( 'Company Name', 'onroute-courier-booking' ); ?></th>
							<td><strong><?php echo esc_html( $account->company_name ); ?></strong></td>
						</tr>
						<tr>
							<th><?php _e( 'Associated User', 'onroute-courier-booking' ); ?></th>
							<td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : 'Unknown'; ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Phone Number', 'onroute-courier-booking' ); ?></th>
							<td><?php echo esc_html( $phone ? $phone : '-' ); ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Business Address', 'onroute-courier-booking' ); ?></th>
							<td><?php echo nl2br( esc_html( $address ? $address : '-' ) ); ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Credit Limit (£)', 'onroute-courier-booking' ); ?></th>
							<td>
								<input type="number" name="credit_limit" step="0.01" value="<?php echo esc_attr( $account->credit_limit ); ?>" class="regular-text">
								<p class="description"><?php _e( 'Maximum credit allowed for this customer.', 'onroute-courier-booking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php _e( 'Current Balance / Used (£)', 'onroute-courier-booking' ); ?></th>
							<td>
								<input type="number" name="current_balance" step="0.01" value="<?php echo esc_attr( $account->current_balance ); ?>" class="regular-text">
								<p class="description"><?php _e( 'Amount already spent. Set to 0.00 for a fresh account.', 'onroute-courier-booking' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php _e( 'Status', 'onroute-courier-booking' ); ?></th>
							<td>
								<span class="status-badge status-<?php echo esc_attr( $account->account_status ); ?>">
									<?php echo ucfirst( esc_html( $account->account_status ) ); ?>
								</span>
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'onroute-courier-booking' ); ?>">
						
						<?php if ( $account->account_status === 'pending' ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'approve', 'id' => $account->id ) ), 'ocb_credit_action' ) ); ?>" class="button button-secondary" style="margin-left: 10px;"><?php _e( 'Approve Application', 'onroute-courier-booking' ); ?></a>
						<?php endif; ?>
						
						<button type="button" class="button ocb-reset-balance-btn" data-account-id="<?php echo $account->id; ?>" style="margin-left: 10px;" onclick="return confirm('Reset balance to £0.00?');"><?php _e( 'Reset Balance to £0.00', 'onroute-courier-booking' ); ?></button>
					</p>
				</form>

				<script>
				jQuery(document).ready(function($) {
					$('.ocb-reset-balance-btn').on('click', function(e) {
						e.preventDefault();
						var $btn = $(this);
						var accountId = $btn.data('account-id');
						
						$btn.prop('disabled', true).text('Resetting...');
						
						$.ajax({
							type: 'POST',
							url: '<?php echo admin_url('admin-ajax.php'); ?>',
							data: {
								action: 'ocb_reset_balance',
								account_id: accountId,
								nonce: '<?php echo wp_create_nonce("ocb_admin_nonce"); ?>'
							},
							success: function(response) {
								if ( response.success ) {
									$('input[name="current_balance"]').val('0.00');
									alert('Balance reset to £0.00 successfully');
									$btn.prop('disabled', false).text('Reset Balance to £0.00');
								} else {
									alert('Error: ' + response.data.message);
									$btn.prop('disabled', false).text('Reset Balance to £0.00');
								}
							},
							error: function() {
								alert('AJAX error occurred');
								$btn.prop('disabled', false).text('Reset Balance to £0.00');
							}
						});
					});
				});
				</script>
			</div>

			<h2><?php _e( 'Linked Bookings', 'onroute-courier-booking' ); ?></h2>
			<?php $this->render_user_bookings( $account->user_id ); ?>
		</div>
		<?php
	}

	/**
	 * Render user bookings
	 */
	private function render_user_bookings( $user_id ) {
		global $wpdb;
		$user = get_userdata( $user_id );
		if ( ! $user ) return;

		$bookings_table = OnRoute_Courier_Booking_Database::get_bookings_table();
		$bookings = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $bookings_table WHERE customer_email = %s ORDER BY created_at DESC", $user->user_email ) );

		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php _e( 'Reference', 'onroute-courier-booking' ); ?></th>
					<th><?php _e( 'Route', 'onroute-courier-booking' ); ?></th>
					<th><?php _e( 'Vehicle/Service', 'onroute-courier-booking' ); ?></th>
					<th><?php _e( 'Status', 'onroute-courier-booking' ); ?></th>
					<th><?php _e( 'Price', 'onroute-courier-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $bookings ) ) : ?>
					<?php foreach ( $bookings as $booking ) : 
						$v_name = ucfirst( str_replace( array( 'mwb', 'lwb', '_' ), array( 'Medium Van', 'Large Van', ' ' ), $booking->vehicle_id ) );
						$s_name = ucfirst( str_replace( array( 'direct', 'timed' ), array( 'Dedicated', 'Priority' ), $booking->service_id ) );
					?>
						<tr>
							<td>
								<a href="<?php echo admin_url( 'admin.php?page=ocb-bookings&view=' . $booking->id ); ?>">
									<strong>#<?php echo esc_html( $booking->booking_reference ); ?></strong>
								</a>
							</td>
							<td>
								<span title="<?php echo esc_attr($booking->pickup_address); ?>"><?php echo esc_html( $booking->pickup_postcode ); ?></span> → 
								<span title="<?php echo esc_attr($booking->delivery_address); ?>"><?php echo esc_html( $booking->delivery_postcode ); ?></span>
							</td>
							<td>
								<div><?php echo esc_html( $v_name ); ?></div>
								<small style="color:#999;"><?php echo esc_html( $s_name ); ?></small>
							</td>
							<td><?php echo esc_html( $booking->status ); ?></td>
							<td><?php echo $this->format_price( $booking->total_price ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="5"><?php _e( 'No bookings found for this user.', 'onroute-courier-booking' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX: Reset balance to 0.00 for a specific account
	 */
	public function ajax_reset_balance() {
		check_ajax_referer( 'ocb_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;
		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( $_POST['company_name'] ) : '';

		if ( ! $account_id && ! $company_name ) {
			wp_send_json_error( array( 'message' => 'Invalid account ID or Company Name' ) );
		}

		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_business_accounts_table();
		
		$where = $account_id ? array( 'id' => $account_id ) : array( 'company_name' => $company_name );
		$where_format = $account_id ? array( '%d' ) : array( '%s' );

		$result = $wpdb->update(
			$table,
			array( 'current_balance' => 0.00 ),
			$where,
			array( '%f' ),
			$where_format
		);

		if ( $result === false ) {
			wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
		}

		wp_send_json_success( array(
			'message' => 'Balance reset successfully',
		) );
	}

	/**
	 * Render Agent Bookings page
	 */
	public function render_agent_bookings_page() {
		global $wpdb;
		$bookings_table = $wpdb->prefix . 'ocb_bookings';
		$accounts_table = OnRoute_Courier_Booking_Database::get_business_accounts_table();
		
		// Get filter parameters
		$agent_filter = isset( $_GET['agent'] ) ? absint( $_GET['agent'] ) : 0;
		$status_filter = isset( $_GET['booking_status'] ) ? sanitize_text_field( $_GET['booking_status'] ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
		
		// Build query
		$where_clauses = array( "b.payment_method = 'business_credit'" );
		
		if ( $agent_filter ) {
			$where_clauses[] = $wpdb->prepare( "b.user_id = %d", $agent_filter );
		}
		
		if ( $status_filter ) {
			$where_clauses[] = $wpdb->prepare( "b.status = %s", $status_filter );
		}
		
		if ( $date_from ) {
			$where_clauses[] = $wpdb->prepare( "DATE(b.created_at) >= %s", $date_from );
		}
		
		if ( $date_to ) {
			$where_clauses[] = $wpdb->prepare( "DATE(b.created_at) <= %s", $date_to );
		}
		
		$where_sql = implode( ' AND ', $where_clauses );
		
		// Query agent bookings
		$query = "
			SELECT b.*, 
				   a.company_name as agent_company,
				   u.display_name as agent_name,
				   u.user_email as agent_email
			FROM {$bookings_table} b
			LEFT JOIN {$accounts_table} a ON b.user_id = a.user_id
			LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
			WHERE {$where_sql}
			ORDER BY b.created_at DESC
			LIMIT 100
		";
		
		$bookings = $wpdb->get_results( $query );
		
		// Get all agents for filter dropdown
		$agents_query = "
			SELECT DISTINCT u.ID, u.display_name, a.company_name
			FROM {$wpdb->users} u
			INNER JOIN {$accounts_table} a ON u.ID = a.user_id
			WHERE a.account_status = 'approved'
			ORDER BY a.company_name ASC
		";
		$agents = $wpdb->get_results( $agents_query );
		
		?>
		<div class="wrap">
			<h1><?php _e( 'Agent Bookings', 'onroute-courier-booking' ); ?></h1>
			<p><?php _e( 'View all bookings made through Business Credit accounts (Agents)', 'onroute-courier-booking' ); ?></p>
			
			<!-- Filters -->
			<div class="tablenav top">
				<form method="get" action="">
					<input type="hidden" name="page" value="ocb-agent-bookings">
					
					<select name="agent" style="margin-right: 10px;">
						<option value=""><?php _e( 'All Agents', 'onroute-courier-booking' ); ?></option>
						<?php foreach ( $agents as $agent ) : ?>
							<option value="<?php echo esc_attr( $agent->ID ); ?>" <?php selected( $agent_filter, $agent->ID ); ?>>
								<?php echo esc_html( $agent->company_name . ' (' . $agent->display_name . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					
					<select name="booking_status" style="margin-right: 10px;">
						<option value=""><?php _e( 'All Statuses', 'onroute-courier-booking' ); ?></option>
						<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php _e( 'Pending', 'onroute-courier-booking' ); ?></option>
						<option value="paid" <?php selected( $status_filter, 'paid' ); ?>><?php _e( 'Paid', 'onroute-courier-booking' ); ?></option>
						<option value="completed" <?php selected( $status_filter, 'completed' ); ?>><?php _e( 'Completed', 'onroute-courier-booking' ); ?></option>
						<option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php _e( 'Cancelled', 'onroute-courier-booking' ); ?></option>
					</select>
					
					<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" placeholder="From Date" style="margin-right: 10px;">
					<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" placeholder="To Date" style="margin-right: 10px;">
					
					<input type="submit" class="button" value="<?php _e( 'Filter', 'onroute-courier-booking' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocb-agent-bookings' ) ); ?>" class="button"><?php _e( 'Reset', 'onroute-courier-booking' ); ?></a>
				</form>
			</div>
			
			<!-- Bookings Table -->
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e( 'Reference', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Date', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Agent/Company', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Route', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Service', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Vehicle', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Total', 'onroute-courier-booking' ); ?></th>
						<th><?php _e( 'Status', 'onroute-courier-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $bookings ) ) : ?>
						<?php foreach ( $bookings as $booking ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $booking->booking_reference ); ?></strong>
									<div style="font-size: 11px; color: #888; margin-top: 3px;">
										ID: <?php echo esc_html( $booking->id ); ?>
									</div>
								</td>
								<td>
									<?php echo esc_html( date( 'j M Y', strtotime( $booking->created_at ) ) ); ?>
									<div style="font-size: 11px; color: #888;">
										<?php echo esc_html( date( 'g:i A', strtotime( $booking->created_at ) ) ); ?>
									</div>
								</td>
								<td>
									<strong><?php echo esc_html( $booking->agent_company ?: 'N/A' ); ?></strong>
									<div style="font-size: 11px; color: #888;">
										<?php echo esc_html( $booking->agent_name ); ?>
									</div>
								</td>
								<td class="route-column">
									<div style="margin-bottom: 8px;">
										<span style="color: #28a745; font-weight: 600;">📍 Pickup:</span><br>
										<span style="font-size: 12px;"><?php echo esc_html( $booking->pickup_address ); ?></span><br>
										<strong><?php echo esc_html( $booking->pickup_postcode ); ?></strong>
									</div>
									<div style="border-top: 1px dashed #ddd; padding-top: 8px;">
										<span style="color: #dc3545; font-weight: 600;">📍 Delivery:</span><br>
										<span style="font-size: 12px;"><?php echo esc_html( $booking->delivery_address ); ?></span><br>
										<strong><?php echo esc_html( $booking->delivery_postcode ); ?></strong>
									</div>
								</td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $booking->service_id ) ) ); ?></td>
								<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $booking->vehicle_id ) ) ); ?></td>
								<td><strong><?php echo $this->format_price( $booking->total_price ); ?></strong></td>
								<td>
									<span class="status-badge status-<?php echo esc_attr( $booking->status ); ?>">
										<?php echo esc_html( ucfirst( $booking->status ) ); ?>
									</span>
									<?php if ( $booking->payment_method === 'business_credit' ) : ?>
										<div style="margin-top: 3px;">
											<span style="background: #17a2b8; color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 3px; text-transform: uppercase; font-weight: 700;">Business Credit</span>
										</div>
									<?php endif; ?>
									<div style="margin-top: 8px;">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocb-bookings&view=' . $booking->id ) ); ?>" class="button button-small">Manage Status</a>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="8" style="text-align: center; padding: 40px;">
								<?php _e( 'No agent bookings found.', 'onroute-courier-booking' ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			
			<style>
				.route-column { max-width: 300px; font-size: 13px; line-height: 1.5; }
				.status-badge { padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 11px; text-transform: uppercase; }
				.status-pending { background: #fff8e5; color: #856404; border: 1px solid #ffeeba; }
				.status-paid, .status-completed { background: #e7f4e4; color: #1e4620; border: 1px solid #c3e6cb; }
				.status-cancelled, .status-failed { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
				.tablenav.top { margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px; }
				.tablenav.top form { margin: 0; }
			</style>
		</div>
		<?php
	}
}
