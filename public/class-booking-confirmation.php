<?php
/**
 * Booking Confirmation Page
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Confirmation {

	public function __construct() {
		add_shortcode( 'onroute_booking_confirmation', array( $this, 'render_confirmation' ) );
	}

	/**
	 * Render booking confirmation
	 */
	public function render_confirmation() {
		$booking_ref = isset( $_GET['ref'] ) ? sanitize_text_field( $_GET['ref'] ) : '';

		if ( empty( $booking_ref ) ) {
			return $this->render_no_booking();
		}

		// Get booking from database
		global $wpdb;
		$table = $wpdb->prefix . 'ocb_bookings';
		$booking = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE booking_reference = %s",
			$booking_ref
		) );

		if ( ! $booking ) {
			return $this->render_not_found( $booking_ref );
		}

		// Check for Stripe Session ID to verify payment
		if ( isset( $_GET['session_id'] ) && $booking->payment_status !== 'paid' ) {
			$session_id = sanitize_text_field( $_GET['session_id'] );
			$stripe = new OnRoute_Courier_Booking_Stripe_API();
			$session = $stripe->retrieve_session( $session_id );

			if ( ! is_wp_error( $session ) && isset( $session['payment_status'] ) && $session['payment_status'] === 'paid' ) {
				// Update booking status
				$wpdb->update(
					$table,
					array(
						'payment_status' => 'paid',
						'stripe_payment_id' => $session['payment_intent'] ?? $session_id,
						'amount_paid' => $session['amount_total'] / 100
					),
					array( 'id' => $booking->id )
				);
				$booking->payment_status = 'paid'; // Update local object

				// Trigger Booking Confirmation Email after successful payment verification
				OnRoute_Courier_Booking_Emails::send_booking_confirmation( $booking->id );
			}
		}

		return $this->render_success( $booking );
	}

	/**
	 * No booking reference provided
	 */
	private function render_no_booking() {
		ob_start();
		?>
		<div class="ocb-confirmation-wrapper">
			<div class="ocb-confirmation-card ocb-error">
				<div class="ocb-confirm-icon">⚠️</div>
				<h1>No Booking Reference</h1>
				<p>No booking reference was provided. Please check your email for your booking confirmation.</p>
				<a href="<?php echo home_url(); ?>" class="ocb-btn ocb-btn-primary">Return to Home</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Booking not found
	 */
	private function render_not_found( $ref ) {
		ob_start();
		?>
		<div class="ocb-confirmation-wrapper">
			<div class="ocb-confirmation-card ocb-error">
				<div class="ocb-confirm-icon">❌</div>
				<h1>Booking Not Found</h1>
				<p>We couldn't find a booking with reference: <strong><?php echo esc_html( $ref ); ?></strong></p>
				<p>Please check the reference number or contact us for assistance.</p>
				<div class="ocb-confirm-actions">
					<a href="<?php echo home_url(); ?>" class="ocb-btn ocb-btn-secondary">Return to Home</a>
					<a href="tel:02077861000" class="ocb-btn ocb-btn-primary">Call Us</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Successful booking confirmation
	 */
	private function render_success( $booking ) {
		$service_names = array(
			'dedicated' => 'Dedicated',
			'timed' => 'Timed',
			'same_day' => 'Same Day'
		);
		
		$vehicle_names = array(
			'pushbike' => 'Pushbike',
			'motorbike' => 'Motorbike',
			'cargo_bike' => 'Cargo Bike',
			'small_van' => 'Small Van',
			'mwb' => 'Medium Van',
			'lwb' => 'Large Van'
		);

		$service_name = $service_names[ $booking->service_id ] ?? ucfirst( $booking->service_id );
		$vehicle_name = $vehicle_names[ $booking->vehicle_id ] ?? ucfirst( str_replace( '_', ' ', $booking->vehicle_id ) );

		ob_start();
		?>
		<!-- FontAwesome for Icons -->
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
		
		<div class="ocb-confirmation-wrapper">
			<div class="ocb-confirmation-card ocb-success">
				
				<!-- Print Only Header -->
				<div class="ocb-print-header">
					<div class="ocb-print-logo">
						<img src="<?php echo ONROUTE_COURIER_BOOKING_URL . 'assets/images/onroute-logo.png'; ?>" alt="OnRoute Couriers" onerror="this.style.display='none'">
						<h2 class="ocb-print-text-logo">OnRoute Couriers</h2>
					</div>
					<div class="ocb-print-info">
						<p>0207 786 1000</p>
						<p>www.onroutecouriers.co.uk</p>
					</div>
				</div>

				<!-- Success Header -->
				<div class="ocb-confirm-header">
					<div class="ocb-confirm-icon"><i class="fas fa-check-circle"></i></div>
					<h1>Booking Confirmed!</h1>
					<p class="ocb-confirm-subtitle">Thank you for booking with OnRoute Couriers</p>
				</div>

				<!-- Booking Reference -->
				<div class="ocb-confirm-ref">
					<span class="ocb-ref-label">Your Booking Reference</span>
					<span class="ocb-ref-value"><?php echo esc_html( $booking->booking_reference ); ?></span>
				</div>

				<!-- Booking Details Grid -->
				<div class="ocb-confirm-details">
					
					<!-- Service Info -->
					<div class="ocb-detail-card">
						<h3><i class="fas fa-truck-fast"></i> Service Details</h3>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Service Type:</span>
							<span class="ocb-d-value"><?php echo esc_html( $service_name ); ?></span>
						</div>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Vehicle:</span>
							<span class="ocb-d-value"><?php echo esc_html( $vehicle_name ); ?></span>
						</div>
						<?php 
						$pricing = new OnRoute_Courier_Booking_Pricing();
						$vehicle = $pricing->get_vehicle( $booking->vehicle_id );
						if ( $vehicle ) {
							if ( ! empty( $vehicle['description'] ) ) {
								?>
								<div class="ocb-detail-row">
									<span class="ocb-d-label">Type:</span>
									<span class="ocb-d-value"><?php echo esc_html( $vehicle['description'] ); ?></span>
								</div>
								<?php
							}
						}
						?>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Collection Date:</span>
							<span class="ocb-d-value"><?php echo esc_html( date( 'l, j F Y', strtotime( $booking->collection_date ) ) ); ?></span>
						</div>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Delivery Time:</span>
							<span class="ocb-d-value">By <?php echo esc_html( date( 'H:i', strtotime( $booking->delivery_time ) ) ); ?></span>
						</div>
					</div>

					<!-- Sender Info -->
					<div class="ocb-detail-card">
						<h3><i class="fas fa-location-dot"></i> Sender Details</h3>
						<?php 
						$notes = !empty($booking->notes) ? json_decode($booking->notes, true) : array();
						$collection_contact = $notes['collection_contact'] ?? '';
						$collection_phone = $notes['collection_phone'] ?? '';
						$collection_company = $notes['collection_company'] ?? '';
						?>
						<?php if ($collection_contact): ?>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Contact:</span>
							<span class="ocb-d-value"><?php echo esc_html($collection_contact); ?></span>
						</div>
						<?php endif; ?>
						<?php if ($collection_phone): ?>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Phone:</span>
							<span class="ocb-d-value"><?php echo esc_html($collection_phone); ?></span>
						</div>
						<?php endif; ?>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Address:</span>
							<div class="ocb-d-value">
								<span class="ocb-postcode-badge"><?php echo esc_html( $booking->pickup_postcode ); ?></span>
								<?php if ( ! empty( $booking->pickup_address ) ) : ?>
									<div class="ocb-address-text"><?php echo esc_html( $booking->pickup_address ); ?></div>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Receiver Info -->
					<div class="ocb-detail-card">
						<h3><i class="fas fa-box-check"></i> Receiver Details</h3>
						<?php 
						$delivery_contact = $notes['delivery_contact'] ?? '';
						$delivery_phone = $notes['delivery_phone'] ?? '';
						$delivery_company = $notes['delivery_company'] ?? '';
						?>
						<?php if ($delivery_contact): ?>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Contact:</span>
							<span class="ocb-d-value"><?php echo esc_html($delivery_contact); ?></span>
						</div>
						<?php endif; ?>
						<?php if ($delivery_phone): ?>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Phone:</span>
							<span class="ocb-d-value"><?php echo esc_html($delivery_phone); ?></span>
						</div>
						<?php endif; ?>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Address:</span>
							<div class="ocb-d-value">
								<span class="ocb-postcode-badge"><?php echo esc_html( $booking->delivery_postcode ); ?></span>
								<?php if ( ! empty( $booking->delivery_address ) ) : ?>
									<div class="ocb-address-text"><?php echo esc_html( $booking->delivery_address ); ?></div>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- Payment Info -->
					<div class="ocb-detail-card ocb-payment-card">
						<h3><i class="fas fa-receipt"></i> Payment Summary</h3>
						<?php 
						$admin_fee = 0;
						if ($booking->vehicle_id === 'small_van') { $admin_fee = 15; }
						elseif ($booking->vehicle_id === 'mwb') { $admin_fee = 20; }
						elseif ($booking->vehicle_id === 'lwb') { $admin_fee = 25; }
						$distance_cost = $booking->base_price - $admin_fee;
						$delivery_hour = (int) date('H', strtotime($booking->delivery_time));
						$is_night = ($delivery_hour >= 22 || $delivery_hour < 6);
						?>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Distance Cost<?php echo $is_night ? ' (Night)' : ''; ?>:</span>
							<span class="ocb-d-value">£<?php echo esc_html( number_format( $distance_cost, 2 ) ); ?></span>
						</div>
						<div class="ocb-detail-row">
							<span class="ocb-d-label">Admin Charge:</span>
							<span class="ocb-d-value">£<?php echo esc_html( number_format( $admin_fee, 2 ) ); ?></span>
						</div>
						<div class="ocb-detail-row ocb-total-row">
							<span class="ocb-d-label">Total Amount:</span>
							<span class="ocb-d-value">£<?php echo esc_html( number_format( $booking->total_price, 2 ) ); ?></span>
						</div>
						<div class="ocb-payment-status-wrapper">
							<span class="ocb-status-badge <?php echo esc_attr( $booking->payment_status ); ?>">
								<?php echo $booking->payment_status === 'paid' ? '<i class="fas fa-check"></i> Paid' : '<i class="fas fa-clock"></i> Pending'; ?>
							</span>
						</div>
					</div>

				</div>

				<!-- What's Next -->
				<div class="ocb-whats-next">
					<h3><i class="fas fa-circle-info"></i> What happens next?</h3>
					<div class="ocb-next-steps">
						<div class="ocb-step-item">
							<div class="ocb-step-icon">1</div>
							<div class="ocb-step-content">
								<strong>Order Confirmation</strong>
								<p>A confirmation email has been sent to <?php echo esc_html($booking->customer_email); ?></p>
							</div>
						</div>
						<div class="ocb-step-item">
							<div class="ocb-step-icon">2</div>
							<div class="ocb-step-content">
								<strong>Driver Dispatch</strong>
								<p>Our nearest driver will be assigned to your collection shortly.</p>
							</div>
						</div>
						<div class="ocb-step-item">
							<div class="ocb-step-icon">3</div>
							<div class="ocb-step-content">
								<strong>Real-time Tracking</strong>
								<p>You'll receive SMS notifications when the driver is on the way.</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Actions -->
				<div class="ocb-confirm-actions">
					<button onclick="printBookingConfirmation()" class="ocb-btn ocb-btn-print">
						<i class="fas fa-print"></i> Print Details
					</button>
					<a href="<?php echo home_url(); ?>" class="ocb-btn ocb-btn-home">
						<i class="fas fa-house"></i> Return Home
					</a>
				</div>

				<!-- Footer Contact -->
				<div class="ocb-confirm-footer">
					<p>Need help? Call us 24/7 at <a href="tel:02077861000">0207 786 1000</a></p>
				</div>

			</div>
		</div>

		<style>
		.ocb-confirmation-wrapper {
			max-width: 900px;
			margin: 40px auto;
			padding: 0 20px;
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
			color: #2D3748;
		}

		.ocb-confirmation-card {
			background: #ffffff;
			border-radius: 20px;
			box-shadow: 0 10px 40px rgba(0,0,0,0.08);
			padding: 50px;
			border: 1px solid #EDF2F7;
			overflow: hidden;
		}

		@media (max-width: 768px) {
			.ocb-confirmation-card { padding: 25px; }
		}

		.ocb-confirm-header {
			text-align: center;
			margin-bottom: 40px;
		}

		.ocb-confirm-icon {
			font-size: 60px;
			color: #d63031; /* Softened Red */
			margin-bottom: 15px;
		}

		.ocb-confirm-header h1 {
			font-size: 32px;
			font-weight: 800;
			margin: 0;
			color: #1A202C;
		}

		.ocb-confirm-subtitle {
			color: #718096;
			font-size: 16px;
			margin-top: 5px;
		}

		.ocb-confirm-ref {
			background: #F7FAFC;
			border: 2px dashed #CBD5E0;
			border-radius: 12px;
			padding: 20px;
			text-align: center;
			margin-bottom: 40px;
		}

		.ocb-ref-label {
			display: block;
			font-size: 13px;
			color: #718096;
			text-transform: uppercase;
			letter-spacing: 1px;
			font-weight: 600;
			margin-bottom: 5px;
		}

		.ocb-ref-value {
			font-size: 30px;
			font-weight: 800;
			color: #d63031; /* Softened Red */
			font-family: 'Monaco', 'Consolas', monospace;
		}

		.ocb-confirm-details {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 25px;
			margin-bottom: 40px;
		}

		@media (max-width: 850px) {
			.ocb-confirm-details { grid-template-columns: 1fr; }
		}

		.ocb-detail-card {
			background: #ffffff;
			border: 1px solid #E2E8F0;
			border-radius: 16px;
			padding: 0;
			overflow: hidden;
			display: flex;
			flex-direction: column;
		}

		.ocb-detail-card h3 {
			background: linear-gradient(135deg, #d63031 0%, #b33939 100%); /* Softened Red Gradient */
			color: #ffffff;
			margin: 0;
			padding: 15px 20px;
			font-size: 15px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			display: flex;
			align-items: center;
			gap: 10px;
		}

		.ocb-detail-row {
			display: flex;
			justify-content: space-between;
			padding: 12px 20px;
			border-bottom: 1px solid #F7FAFC;
			font-size: 14px;
		}

		.ocb-detail-row:last-of-type { border-bottom: none; }

		.ocb-d-label {
			color: #718096;
			font-weight: 500;
		}

		.ocb-d-value {
			font-weight: 700;
			color: #1A202C;
			text-align: right;
		}

		.ocb-postcode-badge {
			background: #E2E8F0;
			padding: 2px 8px;
			border-radius: 6px;
			font-family: monospace;
			font-weight: bold;
			display: inline-block;
			margin-bottom: 4px;
		}

		.ocb-address-text {
			font-size: 13px;
			color: #4A5568;
			font-weight: 400;
			line-height: 1.4;
		}

		.ocb-total-row {
			background: #FFF5F5;
			border-top: 1px solid #FED7D7;
			margin-top: auto;
		}

		.ocb-total-row .ocb-d-label { color: #d63031; font-weight: 700; }
		.ocb-total-row .ocb-d-value { color: #d63031; font-size: 18px; }

		.ocb-payment-status-wrapper {
			padding: 15px;
			text-align: center;
			background: #F8FAFC;
		}

		.ocb-status-badge {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 16px;
			border-radius: 20px;
			font-size: 13px;
			font-weight: 700;
		}

		.ocb-status-badge.paid { background: #C6F6D5; color: #22543D; }
		.ocb-status-badge.pending, .ocb-status-badge.unpaid { background: #FEEBC8; color: #744210; }

		.ocb-whats-next {
			background: #F7FAFC;
			border-radius: 16px;
			padding: 30px;
			margin-bottom: 40px;
		}

		.ocb-whats-next h3 {
			margin: 0 0 20px;
			font-size: 18px;
			color: #1A202C;
		}

		.ocb-next-steps {
			display: grid;
			gap: 20px;
		}

		.ocb-step-item {
			display: flex;
			gap: 15px;
			align-items: flex-start;
		}

		.ocb-step-icon {
			width: 32px;
			height: 32px;
			background: #d63031; /* Softened Red */
			color: white;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: bold;
			flex-shrink: 0;
		}

		.ocb-step-content strong { display: block; margin-bottom: 2px; }
		.ocb-step-content p { margin: 0; font-size: 14px; color: #718096; }

		.ocb-confirm-actions {
			display: flex;
			gap: 15px;
			justify-content: center;
			margin-bottom: 30px;
		}

		.ocb-btn {
			padding: 12px 25px;
			border-radius: 10px;
			font-weight: 700;
			text-decoration: none;
			display: flex;
			align-items: center;
			gap: 8px;
			transition: all 0.2s;
			cursor: pointer;
			border: none;
		}

		.ocb-btn-print { background: #d63031; color: white; }
		.ocb-btn-print:hover { background: #b33939; transform: translateY(-2px); }

		.ocb-btn-home { background: #EDF2F7; color: #4A5568; }
		.ocb-btn-home:hover { background: #E2E8F0; transform: translateY(-2px); }

		.ocb-confirm-footer {
			text-align: center;
			font-size: 14px;
			color: #718096;
			border-top: 1px solid #EDF2F7;
			padding-top: 25px;
		}

		.ocb-confirm-footer a { color: #d63031; font-weight: bold; text-decoration: none; }

		/* Print Header (Hidden on web) */
		.ocb-print-header {
			display: none;
			justify-content: space-between;
			align-items: center;
			border-bottom: 3px solid #d63031;
			padding-bottom: 20px;
			margin-bottom: 30px;
		}

		.ocb-print-logo img {
			max-height: 60px;
		}

		.ocb-print-text-logo {
			margin: 0;
			color: #d63031;
			font-size: 24px;
			font-weight: 800;
		}

		.ocb-print-info {
			text-align: right;
			font-size: 14px;
			color: #4A5568;
		}

		.ocb-print-info p {
			margin: 0;
			line-height: 1.4;
		}

		/* Print Optimizations */
		@media print {
			@page {
				size: auto;
				margin: 15mm;
			}

			body {
				background: white !important;
				color: black !important;
			}

			/* Hide everything except our card */
			header, footer, nav, .sidebar, .elementor-header, .elementor-footer, .ocb-confirm-actions, .ocb-confirm-footer, .ocb-confirm-icon, .ocb-whats-next {
				display: none !important;
			}

			/* Ensure links don't show URLs */
			a[href]:after { content: none !important; }

			.ocb-confirmation-wrapper {
				max-width: 100% !important;
				margin: 0 !important;
				padding: 0 !important;
			}

			.ocb-confirmation-card {
				box-shadow: none !important;
				border: none !important;
				padding: 0 !important;
				width: 100% !important;
			}

			.ocb-print-header {
				display: flex !important;
			}

			.ocb-confirm-header {
				margin-bottom: 20px !important;
			}

			.ocb-confirm-header h1 {
				font-size: 24px !important;
				margin-bottom: 5px !important;
			}

			.ocb-confirm-ref {
				margin: 0 0 25px 0 !important;
				background: #f8f9fa !important;
				border: 1px solid #ddd !important;
				color: #000 !important;
				padding: 15px !important;
			}

			.ocb-ref-value {
				color: #C62828 !important;
				font-size: 24px !important;
			}

			.ocb-confirm-details {
				grid-template-columns: 1fr 1fr !important;
				gap: 15px !important;
			}

			.ocb-detail-card {
				border: 1px solid #e2e8f0 !important;
				break-inside: avoid;
				page-break-inside: avoid;
			}

			.ocb-detail-card h3 {
				background: #f1f5f9 !important;
				color: #C62828 !important;
				padding: 10px 15px !important;
				font-size: 13px !important;
				border-bottom: 1px solid #e2e8f0 !important;
			}

			.ocb-detail-row {
				padding: 8px 15px !important;
			}

			.ocb-d-value {
				font-size: 13px !important;
			}

			.ocb-total-row {
				background: #fff5f5 !important;
				-webkit-print-color-adjust: exact;
			}
		}
		</style>

		<script>
		function printBookingConfirmation() {
			window.print();
		}
		</script>
		<?php
		return ob_get_clean();
	}
}

// Initialize
new OnRoute_Courier_Booking_Confirmation();
