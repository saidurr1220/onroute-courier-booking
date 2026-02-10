<?php
/**
 * Frontend Forms Handler - Renders shortcodes for booking forms
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Forms {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'onroute_quote_form', array( $this, 'render_quote_form' ) );
		add_shortcode( 'onroute_booking_form', array( $this, 'render_booking_form' ) );
		add_shortcode( 'onroute_quote_summary', array( $this, 'render_quote_summary' ) );
		add_shortcode( 'onroute_review_booking', array( $this, 'render_review_form' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_nopriv_ocb_submit_quote', array( $this, 'ajax_submit_quote' ) );
		add_action( 'wp_ajax_ocb_submit_quote', array( $this, 'ajax_submit_quote' ) );
		add_action( 'wp_ajax_nopriv_ocb_select_vehicle', array( $this, 'ajax_select_vehicle' ) );
		add_action( 'wp_ajax_ocb_select_vehicle', array( $this, 'ajax_select_vehicle' ) );
		add_action( 'wp_ajax_nopriv_ocb_submit_booking', array( $this, 'ajax_submit_booking' ) );
		add_action( 'wp_ajax_ocb_submit_booking', array( $this, 'ajax_submit_booking' ) );
		add_action( 'wp_ajax_nopriv_ocb_fetch_addresses', array( $this, 'ajax_fetch_addresses' ) );
		add_action( 'wp_ajax_ocb_fetch_addresses', array( $this, 'ajax_fetch_addresses' ) );
		add_action( 'wp_ajax_nopriv_ocb_get_vehicles', array( $this, 'ajax_get_vehicles' ) );
		add_action( 'wp_ajax_ocb_get_vehicles', array( $this, 'ajax_get_vehicles' ) );
	}

	/**
	 * Render quote form shortcode
	 */
	public function render_quote_form() {
		OnRoute_Courier_Booking_Session::init();
		ob_start();
		?>
		<div class="ocb-quote-form-container">
			<div class="ocb-form-header">
				<h2>Get Your Quote</h2>
				<p>Enter your collection and delivery postcodes to see available services</p>
			</div>

			<form id="ocb-quote-form" class="ocb-quote-form" method="POST">
				<?php wp_nonce_field( 'ocb_quote_nonce', 'nonce' ); ?>

				<div class="ocb-form-section">
					<?php
					$fields = OnRoute_Courier_Booking_Form_Builder::get_step_fields( 1 );
					foreach ( $fields as $field_name => $field_config ) {
						$value = OnRoute_Courier_Booking_Session::get( $field_name, '' );
						OnRoute_Courier_Booking_Form_Builder::render_field( $field_name, $field_config, $value );
					}
					?>
				</div>

				<button type="submit" class="ocb-btn ocb-btn-primary">Get Quote</button>
				
				<p style="margin-top: 12px; padding: 8px 12px; background: #fff4e6; border-left: 3px solid #ff9800; font-size: 12px; color: #e65100; line-height: 1.4;">
					<strong>🌙 Night Rate:</strong> Deliveries between 10 PM - 6 AM are charged at 2× the standard rate.
				</p>
			</form>

			<div id="ocb-quote-error" class="ocb-message ocb-message-error" style="display:none;"></div>
			<div id="ocb-quote-loading" class="ocb-loading" style="display:none;">Loading...</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render booking form shortcode
	 */
	public function render_booking_form() {
		OnRoute_Courier_Booking_Session::init();
		$session_data = OnRoute_Courier_Booking_Session::get_step( 1 );

		// Check if user has completed Step 1
		if ( empty( $session_data ) ) {
			return '<div class="ocb-message ocb-message-info">
				<p>Please start with our <a href="' . esc_url( OnRoute_Courier_Booking_Session::get_quote_page_url() ) . '">quote form</a> to begin your booking.</p>
			</div>';
		}

		// Pricing & Vehicle Data for JS
		$distance = isset( $session_data['step1_distance_miles'] ) ?  floatval( $session_data['step1_distance_miles'] ) : 1;
		$selected_service = isset( $session_data['step1_service_type'] ) ? $session_data['step1_service_type'] : 'same_day';
		
		$pricing_calculator = new OnRoute_Courier_Booking_Pricing();
		$vehicles = $pricing_calculator->get_vehicles();
		$services = get_option( 'ocb_services', array() );
		
		$pricing_config = array(
			'vehicles' => $vehicles,
			'services' => $services,
			'selectedService' => $selected_service,
			'distance' => $distance,
			'night_start' => (int) get_option( 'ocb_night_start', 22 ),
			'night_end' => (int) get_option( 'ocb_night_end', 6 ),
			'vat_rate' => (float) get_option( 'ocb_vat_rate', 20 ),
		);

		ob_start();
		?>
		<script>
			window.ocbPricing = <?php echo wp_json_encode( $pricing_config ); ?>;
		</script>

		<div class="ocb-booking-form-container">
			<?php 
				$service_name = 'Same Day';
				foreach($services as $s) {
					if($s['id'] === $selected_service) {
						$service_name = $s['name'];
						break;
					}
				}
			?>
			<div class="ocb-form-header">
				<h2>Booking Details</h2>
				<p>Complete your booking information. <strong>Distance: <?php echo esc_html( $distance ); ?> miles</strong> | <strong>Service: <?php echo esc_html( $service_name ); ?></strong></p>
			</div>

			<form id="ocb-booking-form" class="ocb-booking-form" method="POST">
				<?php wp_nonce_field( 'ocb_booking_nonce', 'nonce' ); ?>

				<!-- Hidden fields for Step 1 data -->
				<input type="hidden" name="collection_postcode" value="<?php echo esc_attr( $session_data['step1_collection_postcode'] ?? '' ); ?>" />
				<input type="hidden" name="delivery_postcode" value="<?php echo esc_attr( $session_data['step1_delivery_postcode'] ?? '' ); ?>" />
				<input type="hidden" name="ready_now" value="<?php echo esc_attr( $session_data['step1_ready_now'] ?? '' ); ?>" />

				<div class="ocb-form-section ocb-vehicle-section">
					<h3>Select Vehicle</h3>
					<div class="ocb-vehicle-grid">
						<?php foreach ( $vehicles as $v ) : ?>
							<?php if ( empty( $v['active'] ) ) continue; ?>
							<label class="ocb-vehicle-card">
								<input type="radio" name="vehicle_id" value="<?php echo esc_attr( $v['id'] ); ?>" required />
								<span class="ocb-vehicle-name"><?php echo esc_html( $v['name'] ); ?></span>
								<?php if ( isset( $v['description'] ) && ! empty( $v['description'] ) ) : ?>
									<span class="ocb-vehicle-description"><?php echo esc_html( $v['description'] ); ?></span>
								<?php endif; ?>
								<span class="ocb-vehicle-price" data-vehicle="<?php echo esc_attr( $v['id'] ); ?>">Calculating...</span>
								<span class="ocb-vehicle-details">
									Max <?php echo esc_html( $v['max_weight'] ); ?>kg<br>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
					<div id="ocb-total-display" style="margin-top:20px; font-weight:bold; font-size:1.2em; text-align:right;">
						Total (inc VAT): <span id="ocb-final-price">--</span>
					</div>
				</div>

				<div class="ocb-form-section">
					<h3>Your Contact Information</h3>
					<?php
					$contact_fields = array(
						'step3_first_name',
						'step3_last_name',
						'step3_email',
						'step3_phone',
					);

					// Get all fields (now automatically includes defaults + saved customizations)
					$all_fields = OnRoute_Courier_Booking_Form_Builder::get_form_fields();

					foreach ( $contact_fields as $field_name ) {
						if ( isset( $all_fields[ $field_name ] ) ) {
							$field_config = $all_fields[ $field_name ];
							$value = OnRoute_Courier_Booking_Session::get( $field_name, '' );
							OnRoute_Courier_Booking_Form_Builder::render_field( $field_name, $field_config, $value );
						}
					}
					?>
				</div>

				<div class="ocb-form-section">
					<h3>Collection Details</h3>
					<div class="ocb-form-group">
						<label>Collection Postcode</label>
						<div class="ocb-postcode-container">
							<input type="text" class="ocb-field-locked ocb-postcode-display" value="<?php echo esc_attr( $session_data['step1_collection_postcode'] ?? '' ); ?>" disabled />
							<button type="button" class="ocb-btn-select-address" data-postcode-type="collection">
								Select Address
							</button>
						</div>
						<small>From your quote request. Click "Select Address" to choose from list or enter manually.</small>
					</div>

					<?php
					// Get field configs with fallback
					$defaults = OnRoute_Courier_Booking_Form_Builder::get_default_fields();
					
					// 1. Address
					$field_name = 'step3_collection_address';
					$config = isset($all_fields[$field_name]) ? $all_fields[$field_name] : (isset($defaults[$field_name]) ? $defaults[$field_name] : array('label' => 'Collection Address', 'type' => 'textarea', 'required' => true));
					OnRoute_Courier_Booking_Form_Builder::render_field($field_name, $config, OnRoute_Courier_Booking_Session::get($field_name, ''));

					// 2. Company (Force render)
					$field_name = 'step3_collection_company';
					$config = isset($all_fields[$field_name]) ? $all_fields[$field_name] : (isset($defaults[$field_name]) ? $defaults[$field_name] : array('label' => 'Company', 'type' => 'text', 'required' => false));
					OnRoute_Courier_Booking_Form_Builder::render_field($field_name, $config, OnRoute_Courier_Booking_Session::get($field_name, ''));

					// 3. City (Force render)
					$field_name = 'step3_collection_city';
					$config = isset($all_fields[$field_name]) ? $all_fields[$field_name] : (isset($defaults[$field_name]) ? $defaults[$field_name] : array('label' => 'City', 'type' => 'text', 'required' => false));
					OnRoute_Courier_Booking_Form_Builder::render_field($field_name, $config, OnRoute_Courier_Booking_Session::get($field_name, ''));
					?>
				</div>

				<div class="ocb-form-section">
					<h3>Delivery Details</h3>
					<div class="ocb-form-group">
						<label>Delivery Postcode</label>
						<div class="ocb-postcode-container">
							<input type="text" class="ocb-field-locked ocb-postcode-display" value="<?php echo esc_attr( $session_data['step1_delivery_postcode'] ?? '' ); ?>" disabled />
							<button type="button" class="ocb-btn-select-address" data-postcode-type="delivery">
								Select Address
							</button>
						</div>
						<small>From your quote request. Click "Select Address" to choose from list or enter manually.</small>
					</div>

					<?php
					// Get field configs with fallback
					
					// 1. Address
					$field_name = 'step3_delivery_address';
					$config = isset($all_fields[$field_name]) ? $all_fields[$field_name] : (isset($defaults[$field_name]) ? $defaults[$field_name] : array('label' => 'Delivery Address', 'type' => 'textarea', 'required' => true));
					OnRoute_Courier_Booking_Form_Builder::render_field($field_name, $config, OnRoute_Courier_Booking_Session::get($field_name, ''));

					// 2. Company (Force render)
					$field_name = 'step3_delivery_company';
					$config = isset($all_fields[$field_name]) ? $all_fields[$field_name] : (isset($defaults[$field_name]) ? $defaults[$field_name] : array('label' => 'Company', 'type' => 'text', 'required' => false));
					OnRoute_Courier_Booking_Form_Builder::render_field($field_name, $config, OnRoute_Courier_Booking_Session::get($field_name, ''));

					// 3. City (Force render)
					$field_name = 'step3_delivery_city';
					$config = isset($all_fields[$field_name]) ? $all_fields[$field_name] : (isset($defaults[$field_name]) ? $defaults[$field_name] : array('label' => 'City', 'type' => 'text', 'required' => false));
					OnRoute_Courier_Booking_Form_Builder::render_field($field_name, $config, OnRoute_Courier_Booking_Session::get($field_name, ''));
					?>
				</div>

				<div class="ocb-form-section">
					<h3>Collection & Delivery Times</h3>
					<div class="ocb-form-row">
						<?php
						$time_fields = array(
							'step3_collection_date',
							'step3_collection_time',
							'step3_delivery_date',
							'step3_delivery_time',
						);
						foreach ( $time_fields as $field_name ) {
							if ( isset( $all_fields[ $field_name ] ) ) {
								$field_config = $all_fields[ $field_name ];
								$value = OnRoute_Courier_Booking_Session::get( $field_name, '' );
								OnRoute_Courier_Booking_Form_Builder::render_field( $field_name, $field_config, $value );
							}
						}
						?>
					</div>
				</div>

				<p style="margin: 15px 0; padding: 8px 12px; background: #fff4e6; border-left: 3px solid #ff9800; font-size: 12px; color: #e65100; line-height: 1.4;">
					<strong>🌙 Night Rate:</strong> Deliveries between 10 PM - 6 AM are charged at 2× the standard rate.
				</p>

				<div class="ocb-form-actions">
					<a href="<?php echo esc_url( OnRoute_Courier_Booking_Session::get_quote_page_url() ); ?>" class="ocb-btn ocb-btn-secondary">Back to Quote</a>
					<button type="submit" class="ocb-btn ocb-btn-primary">Book Now</button>
				</div>
			</form>

			<script>
			jQuery(document).ready(function($) {
				const config = window.ocbPricing;
				
				function calculatePrice() {
					const vehicleId = $('input[name="vehicle_id"]:checked').val();
					if (!vehicleId) return;

					const timeStr = $('input[name="step3_collection_time"]').val(); 
					// Find vehicle config
					const vehicle = config.vehicles.find(v => v.id === vehicleId);
					if (!vehicle) return;

					// Find service config
					const service = config.services.find(s => s.id === config.selectedService);
					const serviceMultiplier = service ? parseFloat(service.multiplier) : 1.0;

					// Base calculation
					const rate = parseFloat(vehicle.rate_per_mile) || 1.50;
					const admin = parseFloat(vehicle.admin_fee) || 15.00;
					const min = parseFloat(vehicle.min_charge) || 45.00;
					const distance = parseFloat(config.distance);

					let price = (distance * rate) + admin;

					// Night logic
					if (timeStr) {
						const hour = parseInt(timeStr.split(':')[0]);
						const start = config.night_start;
						const end = config.night_end;
						let isNight = false;
						
						if (start > end) { // e.g. 22 to 6
							if (hour >= start || hour < end) isNight = true;
						} else { // e.g. 0 to 6
							if (hour >= start && hour < end) isNight = true;
						}

						if (isNight) {
							price = (distance * rate * 2) + admin; // Double the rate part only
						}
					}

					// Apply Service Multiplier
					price = price * serviceMultiplier;

					// Min charge
					if (price < min) price = min;

					// VAT
					const vat = price * (config.vat_rate / 100);
					const total = price + vat;

					$('#ocb-final-price').text('£' + total.toFixed(2));
				}

				// Listeners
				$('input[name="vehicle_id"]').on('change', calculatePrice);
				$('input[name="step3_collection_time"]').on('change', calculatePrice);
				
				// Initial calc
				calculatePrice();
			});
			</script>

			<div id="ocb-booking-error" class="ocb-message ocb-message-error" style="display:none;"></div>
			<div id="ocb-booking-success" class="ocb-message ocb-message-success" style="display:none;"></div>
			<div id="ocb-booking-loading" class="ocb-loading" style="display:none;">Processing...</div>
		</div>

		<!-- Address Selection Modal -->
		<div id="ocb-address-modal" class="ocb-modal" style="display:none;">
			<div class="ocb-modal-content">
				<div class="ocb-modal-header">
					<h3>Select Address</h3>
					<button type="button" class="ocb-modal-close">&times;</button>
				</div>
				<div class="ocb-modal-body">
					<div class="ocb-address-list">
						<div class="ocb-address-loading" style="text-align:center; padding:20px;">
							<p>Loading addresses...</p>
						</div>
						<div class="ocb-address-items" style="display:none;"></div>
					</div>
					<div class="ocb-address-form" style="display:none; margin-top:20px; padding-top:20px; border-top:1px solid #e0e0e0;">
						<p><strong>Or enter address manually:</strong></p>
						<textarea id="ocb-manual-address" class="ocb-field" placeholder="Enter full address..." rows="4" style="width:100%;"></textarea>
					</div>
				</div>
				<div class="ocb-modal-footer">
					<button type="button" class="ocb-btn ocb-btn-secondary ocb-modal-close-btn">Close</button>
					<button type="button" class="ocb-btn ocb-btn-primary" id="ocb-address-confirm">Select Address</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render quote summary shortcode - Step 2
	 */
	public function render_quote_summary() {
		OnRoute_Courier_Booking_Session::init();
		$session_data = OnRoute_Courier_Booking_Session::get_step( 1 );

		// Check if user has completed Step 1
		if ( empty( $session_data ) ) {
			return '<div class="ocb-message ocb-message-info">
				<p>Please start with our <a href="' . esc_url( OnRoute_Courier_Booking_Session::get_quote_page_url() ) . '">quote form</a> to begin your booking.</p>
			</div>';
		}

		$distance = isset( $session_data['step1_distance_miles'] ) ?  floatval( $session_data['step1_distance_miles'] ) : 1;
		$selected_service = isset( $session_data['step1_service_type'] ) ? $session_data['step1_service_type'] : 'same_day';
		
		ob_start();
		?>
		<div class="ocb-quote-summary-container">
			<div class="ocb-step-indicator">
				<span class="ocb-step-item ocb-step-completed">QUOTE REQUEST</span>
				<span class="ocb-step-arrow">→</span>
				<span class="ocb-step-item ocb-step-active">VEHICLE SELECTION</span>
				<span class="ocb-step-arrow">→</span>
				<span class="ocb-step-item">BOOKING DETAILS</span>
				<span class="ocb-step-arrow">→</span>
				<span class="ocb-step-item">REVIEW & BOOK</span>
			</div>

			<div class="ocb-quote-summary">
				<h2>Your Quote Summary</h2>
				<p>Your quote is valid for 15 minutes from 04:00 on 2 February</p>
				
				<div class="ocb-quote-info">
					<p>
						<strong>Collection postcode:</strong> 
						<span><?php echo esc_html( $session_data['step1_collection_postcode'] ?? '' ); ?></span>
						<a href="<?php echo esc_url( OnRoute_Courier_Booking_Session::get_quote_page_url() ); ?>" class="ocb-change-link">Change</a>
					</p>
					<p>
						<strong>Delivery postcode:</strong> 
						<span><?php echo esc_html( $session_data['step1_delivery_postcode'] ?? '' ); ?></span>
						<a href="<?php echo esc_url( OnRoute_Courier_Booking_Session::get_quote_page_url() ); ?>" class="ocb-change-link">Change</a>
					</p>
				</div>

				<div class="ocb-service-tabs">
					<button class="ocb-service-tab <?php echo $selected_service === 'same_day' ? 'ocb-service-active' : ''; ?>" data-service="same_day">
						Same Day
					</button>
					<button class="ocb-service-tab <?php echo $selected_service === 'timed' ? 'ocb-service-active' : ''; ?>" data-service="timed">
						Timed
					</button>
					<button class="ocb-service-tab <?php echo $selected_service === 'dedicated' ? 'ocb-service-active' : ''; ?>" data-service="dedicated">
						Dedicated
					</button>
				</div>

				<div class="ocb-vehicles-container" id="ocb-vehicles-container">
					<?php echo OnRoute_Courier_Booking_Quote_Summary::render_vehicle_options( $selected_service ); ?>
				</div>
			</div>

			<div id="ocb-vehicle-error" class="ocb-message ocb-message-error" style="display:none;"></div>
			<div id="ocb-vehicle-loading" class="ocb-loading" style="display:none;">Loading...</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render review form shortcode (future use)
	 */
	public function render_review_form() {
		OnRoute_Courier_Booking_Session::init();
		$session_data = OnRoute_Courier_Booking_Session::get_all();

		if ( empty( $session_data ) ) {
			return '<div class="ocb-message ocb-message-info">No booking data found.</div>';
		}

		ob_start();
		?>
		<div class="ocb-review-form-container">
			<h2>Review Your Booking</h2>
			<pre><?php echo esc_html( wp_json_encode( $session_data, JSON_PRETTY_PRINT ) ); ?></pre>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Submit quote form
	 */
	public function ajax_submit_quote() {
		check_ajax_referer( 'ocb_quote_nonce', 'nonce' );

		OnRoute_Courier_Booking_Session::init();

		// Get fields configuration
		$fields = OnRoute_Courier_Booking_Form_Builder::get_step_fields( 1 );
		$step_data = array();
		$errors = array();

		// Process and validate each field
		foreach ( $fields as $field_name => $field_config ) {
			$raw_value = isset( $_POST[ $field_name ] ) ? $_POST[ $field_name ] : '';

			// Validate
			$validation = OnRoute_Courier_Booking_Form_Builder::validate_field_data( $field_name, $raw_value, $field_config );
			if ( is_wp_error( $validation ) ) {
				$errors[] = $validation->get_error_message();
				continue;
			}

			// Sanitize
			$value = OnRoute_Courier_Booking_Form_Builder::sanitize_field_data( $field_name, $raw_value, $field_config );
			$step_data[ $field_name ] = $value;
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => 'Please fix the following errors: ' . implode( ', ', $errors ),
			) );
		}

		// EXTRACT & SAVE SERVICE TYPE (If not handled by dynamic fields)
		if ( isset( $_POST['service_type'] ) ) {
			$step_data['service_type'] = sanitize_text_field( $_POST['service_type'] );
		} elseif ( isset( $_POST['step1_service_type'] ) ) {
			$step_data['service_type'] = sanitize_text_field( $_POST['step1_service_type'] );
		}

		// Calculate valid distance using Distance Matrix API
		$origin = $step_data['step1_collection_postcode'];
		$destination = $step_data['step1_delivery_postcode'];
		
		$distance = OnRoute_Courier_Booking_Distance_Matrix::get_distance( $origin, $destination );

		if ( is_wp_error( $distance ) ) {
			wp_send_json_error( array( 'message' => $distance->get_error_message() ) );
		}

		// Add distance to step data
		$step_data['distance_miles'] = $distance;

		// Save to session with step prefix
		$session_step1 = array();
		foreach ( $step_data as $key => $value ) {
			$session_step1[ 'step1_' . $key ] = $value;
		}

		OnRoute_Courier_Booking_Session::set_step( 1, $session_step1 );

		// Get booking page URL for redirect
		$booking_url = OnRoute_Courier_Booking_Session::get_booking_page_url();

		if ( ! $booking_url ) {
			// Fallback: get admin URL or home URL
			$booking_url = admin_url( 'admin.php?page=ocb-dashboard' );
		}

		wp_send_json_success( array(
			'message' => 'Quote submitted successfully',
			'redirect' => $booking_url,
			'data' => array(
				'distance' => $distance,
			),
		) );
	}


	/**
	 * AJAX: Submit booking form
	 */
	public function ajax_submit_booking() {
		check_ajax_referer( 'ocb_booking_nonce', 'nonce' );

		OnRoute_Courier_Booking_Session::init();

		// Get step 1 data
		$step1_data = OnRoute_Courier_Booking_Session::get_step( 1 );
		if ( empty( $step1_data ) ) {
			wp_send_json_error( array(
				'message' => 'Session expired. Please start over.',
			) );
		}

		// Get Step 3 fields
		$fields = OnRoute_Courier_Booking_Form_Builder::get_step_fields( 3 );
		$step3_data = array();
		$errors = array();

		// Process and validate each field
		foreach ( $fields as $field_name => $field_config ) {
			$raw_value = isset( $_POST[ $field_name ] ) ? $_POST[ $field_name ] : '';

			// Validate
			$validation = OnRoute_Courier_Booking_Form_Builder::validate_field_data( $field_name, $raw_value, $field_config );
			if ( is_wp_error( $validation ) ) {
				$errors[] = $validation->get_error_message();
				continue;
			}

			// Sanitize
			$value = OnRoute_Courier_Booking_Form_Builder::sanitize_field_data( $field_name, $raw_value, $field_config );
			$step3_data[ $field_name ] = $value;
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => 'Please fix the following errors: ' . implode( ', ', $errors ),
			) );
		}

		// Combine all data
		$all_data = array_merge( $step1_data, $step3_data );

		// VALIDATE & CALCULATE PRICING (SERVER-SIDE)
		$distance = floatval( $step1_data['step1_distance_miles'] ?? 10 );
		$vehicle_id = sanitize_text_field( $_POST['vehicle_id'] ?? '' );
		$collection_time = $step3_data['step3_collection_time'] ?? '';
		$service_id = isset( $step1_data['step1_service_type'] ) ? $step1_data['step1_service_type'] : 'same-day';

		if ( empty( $vehicle_id ) ) {
			wp_send_json_error( array( 'message' => 'Please select a vehicle' ) );
		}

		// Calculate exact price purely in code
		$pricing_calculator = new OnRoute_Courier_Booking_Pricing();
		// Passing service_id as the 4th argument
		$base_price = $pricing_calculator->calculate_price( $distance, $vehicle_id, $collection_time, $service_id );
		
		// Calculate totals
		$calculated_totals = $pricing_calculator->calculate_total( $base_price );
		
		$vat_amount = $calculated_totals['vat_amount'];
		$total_price = $calculated_totals['total'];

		// Create booking with validated pricing
		$booking = new OnRoute_Courier_Booking_Booking();
		$booking_id = $booking->create(
			$all_data['step3_email'] ?? '',
			$all_data['step3_phone'] ?? '',
			$all_data['step3_collection_address'] ?? '',
			$all_data['step1_collection_postcode'] ?? '',
			$all_data['step3_delivery_address'] ?? '',
			$all_data['step1_delivery_postcode'] ?? '',
			$all_data['step3_collection_date'] ?? '',
			$all_data['step3_collection_time'] ?? '00:00',
			$all_data['step3_delivery_date'] ?? '',
			$all_data['step3_delivery_time'] ?? '00:00',
			$vehicle_id,
			$service_id,
			$base_price,
			$vat_amount,
			0, // discount_amount
			$total_price,
			'' // promo_code
		);

		if ( ! $booking_id ) {
			wp_send_json_error( array(
				'message' => 'Failed to create booking. Please try again.',
			) );
		}

		// Save step 3 data to session
		OnRoute_Courier_Booking_Session::set_step( 3, $step3_data );

		// Clear session after successful booking
		OnRoute_Courier_Booking_Session::clear();

		wp_send_json_success( array(
			'message' => 'Booking created successfully!',
			'booking_id' => $booking_id,
			'redirect' => home_url( '/bookings/' ), // Redirect to booking confirmation page
		) );
	}

	/**
	 * AJAX: Get vehicles for service type
	 */
	public function ajax_get_vehicles() {
		$service_type = isset( $_REQUEST['service_type'] ) ? sanitize_text_field( $_REQUEST['service_type'] ) : 'same_day';
		
		echo OnRoute_Courier_Booking_Quote_Summary::render_vehicle_options( $service_type );
		wp_die();
	}

	/**
	 * AJAX: Select vehicle and update pricing
	 */
	public function ajax_select_vehicle() {
		check_ajax_referer( 'ocb_quote_nonce', 'nonce' );

		OnRoute_Courier_Booking_Session::init();

		// Get vehicle and pricing data
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? sanitize_text_field( $_POST['vehicle_id'] ) : '';
		$service_type = isset( $_POST['service_type'] ) ? sanitize_text_field( $_POST['service_type'] ) : 'same_day';

		if ( empty( $vehicle_id ) ) {
			wp_send_json_error( array(
				'message' => 'Please select a vehicle.',
			) );
		}

		// Get Step 1 for distance
		$step1_data = OnRoute_Courier_Booking_Session::get_step( 1 );
		$distance = isset( $step1_data['step1_distance_miles'] ) ? floatval( $step1_data['step1_distance_miles'] ) : 10;
		// Default time if not set yet
		$time = isset( $step1_data['step3_collection_time'] ) ? $step1_data['step3_collection_time'] : '09:00';

		// Calculate dynamic pricing
		$pricing_calculator = new OnRoute_Courier_Booking_Pricing();
		$base_price = $pricing_calculator->calculate_price( $distance, $vehicle_id, $time, $service_type );
		$totals = $pricing_calculator->calculate_total( $base_price );

		// Get vehicle info for name from DB
		$vehicle_data = $pricing_calculator->get_vehicle( $vehicle_id );
		$vehicle_name = $vehicle_data ? $vehicle_data['name'] : $vehicle_id;

		// Store selected vehicle and pricing in session (Step 2)
		// ALSO update step1_service_type to ensure downstream logic works
		$step1_data['step1_service_type'] = $service_type;
		OnRoute_Courier_Booking_Session::set_step( 1, $step1_data );

		$pricing_total_key = isset($totals['total']) ? 'total' : 'total_price';
		$total_val = isset($totals[$pricing_total_key]) ? $totals[$pricing_total_key] : 0;

		$step2_data = array(
			'step2_vehicle_id' => $vehicle_id,
			'step2_vehicle_name' => $vehicle_name,
			'step2_service_type' => $service_type,
			'step2_base_price' => $base_price,
			'step2_vat_amount' => $totals['vat_amount'],
			'step2_total_price' => $total_val,
		);

		OnRoute_Courier_Booking_Session::set_step( 2, $step2_data );

		// Get booking page URL
		$booking_url = OnRoute_Courier_Booking_Session::get_booking_page_url();

		if ( ! $booking_url ) {
			$booking_url = admin_url( 'admin.php?page=ocb-dashboard' );
		}

		wp_send_json_success( array(
			'message' => 'Vehicle selected successfully',
			'vehicle' => $vehicle_name,
			'total_price' => '£' . number_format( $totals['total'], 2 ),
			'redirect' => $booking_url,
		) );
	}

	/**
	 * Fetch addresses by postcode via AJAX
	 */
	public function ajax_fetch_addresses() {
		check_ajax_referer( 'ocb_booking_nonce', 'nonce' );

		$postcode = sanitize_text_field( $_POST['postcode'] ?? '' );

		if ( empty( $postcode ) ) {
			wp_send_json_error( array( 'message' => 'Postcode is required' ) );
		}

		// TODO: Integrate with real address API (Google Maps, PostcodeAnywhere, etc.)
		// For now, return mock addresses
		$mock_addresses = array(
			array(
				'id' => 1,
				'address' => '123 Main Street, London, SW1A 1AA',
				'formatted' => '123 Main Street',
				'city' => 'London',
				'postcode' => $postcode,
			),
			array(
				'id' => 2,
				'address' => '456 High Road, London, SW1A 1AA',
				'formatted' => '456 High Road',
				'city' => 'London',
				'postcode' => $postcode,
			),
			array(
				'id' => 3,
				'address' => '789 Park Lane, London, SW1A 1AA',
				'formatted' => '789 Park Lane',
				'city' => 'London',
				'postcode' => $postcode,
			),
			array(
				'id' => 4,
				'address' => '321 Queen Street, London, SW1A 1AA',
				'formatted' => '321 Queen Street',
				'city' => 'London',
				'postcode' => $postcode,
			),
		);

		wp_send_json_success( array(
			'addresses' => $mock_addresses,
			'postcode' => $postcode,
		) );
	}

	/**
	 * Enqueue scripts
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'ocb-forms-js', ONROUTE_COURIER_BOOKING_URL . 'assets/forms.js', array( 'jquery' ), ONROUTE_COURIER_BOOKING_VERSION, true );
		wp_enqueue_script( 'ocb-multi-form-js', ONROUTE_COURIER_BOOKING_URL . 'assets/multi-step-form.js', array( 'jquery' ), ONROUTE_COURIER_BOOKING_VERSION, true );
		wp_enqueue_style( 'ocb-multi-form-css', ONROUTE_COURIER_BOOKING_URL . 'assets/multi-step-form.css', array(), ONROUTE_COURIER_BOOKING_VERSION );
		wp_localize_script( 'ocb-forms-js', 'ocbForms', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		) );
	}
}
