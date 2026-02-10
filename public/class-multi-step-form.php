<?php
/**
 * Multi-Step Booking Form - CitySprint Style
 *
 * Step 1: Quote Request (Vehicle Selection)
 * Step 2: Booking Details (Contact, Collection, Delivery)
 * Step 3: Review & Book
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Multi_Step_Form {

	public function __construct() {
		add_shortcode( 'onroute_multi_booking', array( $this, 'render_form' ) );
		add_action( 'wp_ajax_nopriv_ocb_multi_submit_step', array( $this, 'ajax_submit_step' ) );
		add_action( 'wp_ajax_ocb_multi_submit_step', array( $this, 'ajax_submit_step' ) );
		
		// New AJAX endpoint to fetch session data bypassing Page Cache
		add_action( 'wp_ajax_nopriv_ocb_get_session_data', array( $this, 'ajax_get_session_data' ) );
		add_action( 'wp_ajax_ocb_get_session_data', array( $this, 'ajax_get_session_data' ) );
		
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function enqueue_scripts() {
		if ( ! is_singular() ) return;
		
		global $post;
		if ( ! has_shortcode( $post->post_content, 'onroute_multi_booking' ) ) return;

		// Get API key from settings
		$api_key = get_option( 'ocb_google_maps_api_key', '' );
		$fallback_distance = get_option( 'ocb_fallback_distance', 10 );

		// Load Google Maps API - DISABLED (Using ORS Server Side)
		/*
		if ( ! empty( $api_key ) ) {
			wp_enqueue_script(
				'google-maps-api',
				'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&libraries=places',
				array(),
				null,
				true
			);
		}
		*/

	}

	/**
	 * Validate UK postcode format
	 */
	public static function is_valid_uk_postcode( $postcode ) {
		$postcode = strtoupper( str_replace( ' ', '', $postcode ) );
		$pattern = '/^[A-Z]{1,2}[0-9][0-9A-Z]?[0-9][A-Z]{2}$/';
		return preg_match( $pattern, $postcode );
	}

	public function render_form() {
		OnRoute_Courier_Booking_Session::init();
		
		$collection_postcode = OnRoute_Courier_Booking_Session::get( 'collection_postcode' ) ?? '';
		$delivery_postcode = OnRoute_Courier_Booking_Session::get( 'delivery_postcode' ) ?? '';
		$collection_date = OnRoute_Courier_Booking_Session::get( 'collection_date' ) ?? '';
		$collection_time = OnRoute_Courier_Booking_Session::get( 'collection_time' ) ?? '';
		$booking_type = OnRoute_Courier_Booking_Session::get( 'booking_type' ) ?? 'ready';
		$service_types = OnRoute_Courier_Booking_Session::get( 'service_types' ) ?? array( 'same_day', 'priority', 'direct' );
		$distance = OnRoute_Courier_Booking_Session::get( 'step1_distance_miles' ) ?? 0;

		$current_time = date( 'H:i' );
		$current_date = date( 'j F' );
		
		// Force display of all columns by default for now to prevent blank states
		// This overrides any session issues where service_types might be empty
		$show_dedicated = true; 
		$show_timed = true; 
		$show_same_day = true;
		
		ob_start();
		?>
		<div class="ocb-booking-wrapper">
			
			<!-- Top Navigation Tabs -->
			<div class="ocb-nav-tabs">
				<div class="ocb-nav-tab active" data-step="1">
					<span class="ocb-tab-text">QUOTE REQUEST</span>
				</div>
				<div class="ocb-nav-tab" data-step="2">
					<span class="ocb-tab-text">BOOKING DETAILS</span>
				</div>
				<div class="ocb-nav-tab" data-step="3">
					<span class="ocb-tab-text">REVIEW & BOOK</span>
				</div>
			</div>

			<form id="ocb-booking-form" method="POST">
				<?php wp_nonce_field( 'ocb_multi_nonce', 'ocb_nonce' ); ?>
				<?php wp_nonce_field( 'ocb_api_nonce', 'ocb_api_nonce_field' ); ?>
				<input type="hidden" name="action" value="ocb_multi_submit_step" />
				<input type="hidden" name="booking_type" value="<?php echo esc_attr( $booking_type ); ?>" />
				<input type="hidden" name="collection_postcode" value="<?php echo esc_attr( $collection_postcode ); ?>" />
				<input type="hidden" name="delivery_postcode" value="<?php echo esc_attr( $delivery_postcode ); ?>" />
				<input type="hidden" name="collection_date" value="<?php echo esc_attr( $collection_date ); ?>" />
				<input type="hidden" name="collection_time" value="<?php echo esc_attr( $collection_time ); ?>" />
				<input type="hidden" name="service_type" id="selected-service-type" value="<?php echo esc_attr( OnRoute_Courier_Booking_Session::get( 'selected_service' ) ?? 'same_day' ); ?>" />
				<input type="hidden" name="selected_vehicle_id" id="selected-vehicle-id" value="<?php echo esc_attr( OnRoute_Courier_Booking_Session::get( 'selected_vehicle' ) ?? '' ); ?>" />
				<input type="hidden" name="selected_price" id="selected-price" value="0" />

				<!-- ============================================
					 STEP 1: QUOTE REQUEST
					 ============================================ -->
				<div class="ocb-step-panel active" data-step="1">
					
					<div class="ocb-quote-header">
						<h1>Your quote summary</h1>
						<p class="ocb-quote-validity">
							Your quote is valid for <strong>15 minutes</strong> from <strong><?php echo $current_time; ?></strong> on <strong><?php echo $current_date; ?></strong>.
						</p>
					</div>

					<!-- Postcode Display -->
					<div class="ocb-postcodes-row">
						<div class="ocb-postcode-item">
							<span class="ocb-pc-label">Collection postcode:</span>
							<span class="ocb-pc-value" id="display-collection"><?php echo esc_html( $collection_postcode ); ?></span>
						</div>
						<div class="ocb-postcode-item">
							<span class="ocb-pc-label">Delivery postcode:</span>
							<span class="ocb-pc-value" id="display-delivery"><?php echo esc_html( $delivery_postcode ); ?></span>
						</div>				</div>

				<?php 
				$now = time();
				$collect_initial = date('H:i', $now);
				$same_day_deliver = (date('H', $now) < 13) ? '17:00' : '20:00';
				$priority_deliver = date('H:i', $now + 3 * 3600);
				$dedicated_deliver = date('H:i', $now + 1.5 * 3600);
				?>

				<?php if ( $show_same_day ) : ?>
				<!-- SAME DAY SERVICE -->
				<div class="ocb-service-section">
					<h2 class="ocb-service-title">Same Day</h2>
					<p class="ocb-service-desc">
						Our most cost-effective service, we'll collect by <strong class="ocb-time-collect"><?php echo $collect_initial; ?></strong> and deliver by <strong class="ocb-time-deliver"><?php echo $same_day_deliver; ?></strong>
					</p>
					<div class="ocb-vehicles-row" data-service="same_day"></div>
				</div>
				<?php endif; ?>

				<?php if ( $show_timed ) : ?>
				<!-- PRIORITY SERVICE (Previously Timed) -->
				<div class="ocb-service-section">
					<h2 class="ocb-service-title">Priority</h2>
					<p class="ocb-service-desc">
						The most popular option, we'll collect by <strong class="ocb-time-collect"><?php echo $collect_initial; ?></strong> and deliver by <strong class="ocb-time-deliver"><?php echo $priority_deliver; ?></strong> or another time after that suits you.
					</p>
					<div class="ocb-vehicles-row" data-service="timed"></div>
				</div>
				<?php endif; ?>

				<?php if ( $show_dedicated ) : ?>
				<!-- DEDICATED SERVICE (OnRoute Direct) -->
				<div class="ocb-service-section">
					<h2 class="ocb-service-title">Dedicated</h2>
					<p class="ocb-service-desc">
						Our most secure service, we'll collect by <strong class="ocb-time-collect"><?php echo $collect_initial; ?></strong> and deliver by <strong class="ocb-time-deliver"><?php echo $dedicated_deliver; ?></strong>, with no other items on board.
					</p>
					<div class="ocb-vehicles-row" data-service="dedicated"></div>
				</div>
				<?php endif; ?>

				</div>

				<!-- ============================================
					 STEP 2: BOOKING DETAILS (CitySprint Style)
					 ============================================ -->
				<div class="ocb-step-panel" data-step="2">
					
					<div class="ocb-booking-layout">
						<div class="ocb-booking-main">
							
							<!-- Change Date Button -->
							<?php
							$edit_url = add_query_arg( array(
								'ocb_action' => 'edit_quote',
								'coll' => urlencode( $collection_postcode ),
								'del' => urlencode( $delivery_postcode ),
								'dist' => $distance,
							), home_url( '/' ) );
							?>
							<div class="ocb-change-date-section">
								<p>Need to change the date or postcodes?</p>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="ocb-change-link">← Change date & postcodes</a>
							</div>
							
							<!-- My Details Section -->
							<div class="ocb-form-card">
								<?php 
								$current_user = wp_get_current_user();
								$user_first = $current_user->exists() ? $current_user->user_firstname : '';
								$user_last = $current_user->exists() ? $current_user->user_lastname : '';
								if (empty($user_first) && $current_user->exists()) {
									$parts = explode(' ', $current_user->display_name);
									$user_first = $parts[0];
									$user_last = isset($parts[1]) ? $parts[1] : '';
								}
								$user_email = $current_user->exists() ? $current_user->user_email : '';
								?>
								<h3 class="ocb-card-title">My details</h3>
								<div class="ocb-form-grid-2">
									<div class="ocb-field-group">
										<label>First name <span class="required">*</span></label>
										<input type="text" name="first_name" class="ocb-field" value="<?php echo esc_attr($user_first); ?>" required />
									</div>
									<div class="ocb-field-group">
										<label>Surname <span class="required">*</span></label>
										<input type="text" name="last_name" class="ocb-field" value="<?php echo esc_attr($user_last); ?>" required />
									</div>
									<div class="ocb-field-group">
										<label>Email <span class="required">*</span></label>
										<input type="email" name="customer_email" class="ocb-field" value="<?php echo esc_attr($user_email); ?>" required />
									</div>
									<div class="ocb-field-group">
										<label>Mobile no. <span class="required">*</span></label>
										<input type="tel" name="customer_phone" class="ocb-field" required />
									</div>
									<div class="ocb-field-group">
										<label>Booking ref.</label>
										<input type="text" name="booking_ref" class="ocb-field" />
									</div>
								</div>
							</div>

							<!-- Collection Details Section -->
							<div class="ocb-form-card">
								<h3 class="ocb-card-title">Collection details</h3>
								
								<!-- Row 1: Name + Postcode -->
								<div class="ocb-cs-grid">
									<label>Contact name</label>
									<div class="ocb-cs-grid-postcode">
										<input type="text" name="collection_contact" class="ocb-field" />
										<label>Postcode <span class="required">*</span></label>
										<input type="text" name="collection_postcode_display" class="ocb-field ocb-postcode-input" value="<?php echo esc_attr( $collection_postcode ); ?>" readonly style="width: 100px;" data-location="collection" />
										<button type="button" class="ocb-select-address-btn ocb-address-lookup-btn" data-location="collection" data-postcode-field="collection_postcode_display" data-address-field="collection_address">Select address</button>
									</div>
								</div>

								<!-- Address Field -->
								<div class="ocb-cs-grid">
									<label>Address <span class="required">*</span></label>
									<textarea name="collection_address" class="ocb-field ocb-textarea" rows="2" placeholder="Street, Building, Flat..." required></textarea>
								</div>

								<!-- Address Line 2 -->
								<div class="ocb-cs-grid">
									<label>Address Line 2</label>
									<input type="text" name="collection_address_line2" class="ocb-field" placeholder="Apartment, Suite, Floor, etc. (optional)" />
								</div>

								<!-- Row: City -->
								<div class="ocb-cs-grid">
									<label>Town / City <span class="required">*</span></label>
									<input type="text" name="collection_city" class="ocb-field" placeholder="Town or City" required style="max-width: 300px;" />
								</div>

								<!-- Row 2: Phone -->
								<div class="ocb-cs-grid">
									<label>Contact phone no.</label>
									<input type="tel" name="collection_phone" class="ocb-field" style="max-width: 300px;" />
								</div>

								<!-- Row 3: Company -->
								<div class="ocb-cs-grid">
									<label>Company</label>
									<input type="text" name="collection_company" class="ocb-field" />
								</div>

								<!-- Row 4: Instructions -->
								<div class="ocb-cs-grid" style="align-items: flex-start;">
									<label style="margin-top: 10px;">Further instructions</label>
									<textarea name="collection_instructions" class="ocb-field ocb-textarea" maxlength="100" rows="3"></textarea>
								</div>
							</div>

							<!-- Delivery Details Section -->
							<div class="ocb-form-card">
								<h3 class="ocb-card-title">Delivery details</h3>
								
								<!-- Row 1: Name + Postcode -->
								<div class="ocb-cs-grid">
									<label>Contact name <i class="fas fa-info-circle" style="color:var(--ocb-primary);"></i></label>
									<div class="ocb-cs-grid-postcode">
										<input type="text" name="delivery_contact" class="ocb-field" />
										<label>Postcode <span class="required">*</span></label>
										<input type="text" name="delivery_postcode_display" class="ocb-field ocb-postcode-input" value="<?php echo esc_attr( $delivery_postcode ); ?>" readonly style="width: 100px;" data-location="delivery" />
										<button type="button" class="ocb-select-address-btn ocb-address-lookup-btn" data-location="delivery" data-postcode-field="delivery_postcode_display" data-address-field="delivery_address">Select address</button>
									</div>
								</div>

								<!-- Address Field -->
								<div class="ocb-cs-grid">
									<label>Address <span class="required">*</span></label>
									<textarea name="delivery_address" class="ocb-field ocb-textarea" rows="2" placeholder="Street, Building, Flat..." required></textarea>
								</div>

								<!-- Address Line 2 -->
								<div class="ocb-cs-grid">
									<label>Address Line 2</label>
									<input type="text" name="delivery_address_line2" class="ocb-field" placeholder="Apartment, Suite, Floor, etc. (optional)" />
								</div>

								<!-- Row: City -->
								<div class="ocb-cs-grid">
									<label>Town / City <span class="required">*</span></label>
									<input type="text" name="delivery_city" class="ocb-field" placeholder="Town or City" required style="max-width: 300px;" />
								</div>

								<!-- Row 2: Phone -->
								<div class="ocb-cs-grid">
									<label>Contact phone no. <i class="fas fa-info-circle" style="color:var(--ocb-primary);"></i></label>
									<input type="tel" name="delivery_phone" class="ocb-field" style="max-width: 300px;" />
								</div>
								
								<!-- Row 3: Company -->
								<div class="ocb-cs-grid">
									<label>Company</label>
									<input type="text" name="delivery_company" class="ocb-field" />
								</div>

								<!-- Row 4: Date Time Selector -->
								<div class="ocb-cs-grid">
									<div id="ocb-delivery-window-display" style="display:none; padding: 15px; background: #f8fafc; border-left: 4px solid #3182ce; border-radius: 4px; margin-bottom: 15px; width: 100%;">
										<div style="font-weight: 700; color: #2d3748; margin-bottom: 5px; display: flex; align-items: center;">
											<i class="fas fa-truck-clock" style="margin-right: 10px; color: #3182ce;"></i> 
											Estimated delivery window:
										</div>
										<div id="ocb-delivery-window-text" style="font-size: 1.1em; color: #2c5282; font-weight: 600;"></div>
									</div>

									<div id="ocb-priority-time-picker" style="display: flex; flex-direction: column; gap: 15px;">
											<div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
												<div class="ocb-datetime-wrapper" id="ocb-date-container">
													<div class="ocb-dt-group">
														<i class="fas fa-calendar-alt"></i>
														<input type="date" id="ocb_step2_date" class="ocb-dt-input" required />
													</div>
												</div>
												<div class="ocb-datetime-wrapper" id="ocb-time-container">
													<div class="ocb-dt-group">
														<i class="fas fa-clock"></i>
														<input type="time" id="ocb_step2_time" class="ocb-dt-input" required />
													</div>
												</div>
												
												<div style="font-size: 13px; color: var(--ocb-dark); display: flex; align-items: center;">
													<span id="ocb-datetime-label">Collection/Delivery date & time</span>
													<div class="ocb-dt-tooltip">
														<i class="fas fa-info-circle"></i>
														<span class="tooltip-text">You can adjust the schedule as needed, subject to minimum lead times.</span>
													</div>
												</div>
											</div>
											<input type="hidden" id="ocb_step2_datetime" name="delivery_datetime" />
										</div>
								</div>

								<div class="ocb-cs-grid">
									<label>If recipient unavailable</label>
									<select name="recipient_unavailable" class="ocb-field" style="max-width: 300px;">
										<option value="">----- Select -------</option>
										<option value="leave_safe">Leave in a safe place</option>
										<option value="neighbour">Leave with neighbour</option>
										<option value="return">Return to sender</option>
									</select>
								</div>

								<div class="ocb-cs-grid" style="align-items: flex-start;">
									<label style="margin-top: 10px;">Further instructions</label>
									<textarea name="delivery_instructions" class="ocb-field ocb-textarea" maxlength="100" rows="3"></textarea>
								</div>
							</div>

							<!-- Navigation -->
							<div class="ocb-step-nav">
								<button type="button" class="ocb-btn ocb-btn-back" data-action="back">Back</button>
								<button type="button" class="ocb-btn ocb-btn-next" data-action="next">Next</button>
							</div>

						</div>

						<!-- Sidebar -->
						<div class="ocb-booking-sidebar">
							<div class="ocb-help-box">
								<div class="ocb-help-icon">📞</div>
								<div class="ocb-help-text">
									<strong>Need a helping hand?</strong>
									<p>Call us on <a href="tel:02077861000">0207 786 1000</a></p>
								</div>
							</div>

							<div class="ocb-why-choose">
								<h4>Why choose OnRoute?</h4>
								<ul>
									<li><i class="fas fa-check"></i> ⚡ <strong>Rapid Pickups.</strong> Collection within 60 minutes for all Same Day bookings.</li>
									<li><i class="fas fa-check"></i> 💰 <strong>Transparent Pricing.</strong> No hidden fees, fuel surcharges, or surprise booking costs.</li>
									<li><i class="fas fa-check"></i> 🇬🇧 <strong>National Network.</strong> Local expertise with nationwide coverage for every delivery.</li>
								</ul>
							</div>
						</div>
					</div>

				</div>

				<!-- ============================================
					 STEP 3: REVIEW & BOOK
					 ============================================ -->
				<div class="ocb-step-panel" data-step="3">
					
					<div class="ocb-review-header">
						<div class="ocb-review-header-content">
							<div>
								<h1>Review & Book</h1>
								<p>Please review your booking details before confirming</p>
							</div>
							<button type="button" class="ocb-btn ocb-btn-edit-details" data-action="back" title="Edit Details">
								<i class="fas fa-edit"></i> <span class="ocb-btn-text">Edit Details</span>
							</button>
						</div>
					</div>

					<div class="ocb-review-grid">
						<!-- Booking Summary Card -->
						<div class="ocb-review-card">
							<h3><i class="fas fa-clipboard-list"></i> Booking Summary</h3>
							<div class="ocb-review-item">
								<span class="ocb-r-label">Service Type:</span>
								<span class="ocb-r-value" id="r-service">—</span>
							</div>
							<div class="ocb-review-item">
								<span class="ocb-r-label">Vehicle:</span>
								<div class="ocb-r-value-group">
									<span class="ocb-r-value" id="r-vehicle">—</span>
									<div class="ocb-r-specs" id="r-specs-row" style="display:none; flex-direction:column; margin-top:5px; font-size:0.9em; color:#666;">
										<span id="r-vehicle-desc" style="font-weight:600; color:#444;"></span>
										<span id="r-vehicle-dim"></span>
										<span id="r-vehicle-weight"></span>
									</div>
								</div>
							</div>
							<div class="ocb-review-item">
								<span class="ocb-r-label">Collection:</span>
								<div class="ocb-r-value-group">
									<span class="ocb-r-value" id="r-collection"><?php echo esc_html( $collection_postcode ); ?></span>
									<span class="ocb-r-address" id="r-collection-address" style="display:block; font-size:0.9em; color:#666; margin-top:4px;"></span>
									<div class="ocb-r-contact" style="margin-top: 5px; font-size: 0.85em; color: #555;">
										<i class="fas fa-user-circle"></i> <span id="r-collection-contact">—</span><br>
										<i class="fas fa-phone"></i> <span id="r-collection-phone">—</span>
									</div>
									<span class="ocb-r-date" id="r-collection-date" style="display:block; font-size:0.9em; color:#666;"></span>
								</div>
							</div>
							<div class="ocb-review-item">
								<span class="ocb-r-label">Delivery:</span>
								<div class="ocb-r-value-group">
									<span class="ocb-r-value" id="r-delivery"><?php echo esc_html( $delivery_postcode ); ?></span>
									<span class="ocb-r-address" id="r-delivery-address" style="display:block; font-size:0.9em; color:#666; margin-top:4px;"></span>
									<div class="ocb-r-contact" style="margin-top: 5px; font-size: 0.85em; color: #555;">
										<i class="fas fa-user-circle"></i> <span id="r-delivery-contact">—</span><br>
										<i class="fas fa-phone"></i> <span id="r-delivery-phone">—</span>
									</div>
									<span class="ocb-r-date" id="r-delivery-date" style="display:block; font-size:0.9em; color:#666;"></span>
								</div>
							</div>
						</div>

						<!-- Contact Summary Card -->
						<div class="ocb-review-card">
							<h3><i class="fas fa-user"></i> Contact Details</h3>
							<div class="ocb-review-item">
								<span class="ocb-r-label">Name:</span>
								<span class="ocb-r-value" id="r-name">—</span>
							</div>
							<div class="ocb-review-item">
								<span class="ocb-r-label">Email:</span>
								<span class="ocb-r-value" id="r-email">—</span>
							</div>
							<div class="ocb-review-item">
								<span class="ocb-r-label">Phone:</span>
								<span class="ocb-r-value" id="r-phone">—</span>
							</div>
						</div>
					</div>

					<!-- Price Box -->
					<div class="ocb-price-box">
					<div class="ocb-price-box">
						<div class="ocb-price-row ocb-price-total">
							<span>Total Price</span>
							<span id="r-price-excl">£0.00</span>
						</div>
					</div>
					</div>

					<!-- Terms -->
					<div class="ocb-terms">
						<?php if ( is_user_logged_in() && class_exists( 'OnRoute_Business_Credit' ) && OnRoute_Business_Credit::is_eligible_for_credit( get_current_user_id() ) ) : ?>
							<div class="ocb-payment-method-choice" style="margin-bottom: 20px; padding: 15px; background: #fdf2f2; border: 1px solid #fee2e2; border-radius: 8px;">
								<label style="font-weight: 600; display: block; margin-bottom: 10px;">Payment Method</label>
								<div style="display: flex; gap: 20px;">
									<label class="ocb-radio">
										<input type="radio" name="payment_method_choice" value="stripe" checked />
										<span>Card Payment (Stripe)</span>
									</label>
									<label class="ocb-radio">
										<input type="radio" name="payment_method_choice" value="business_credit" />
										<span>Business Credit Account</span>
									</label>
								</div>
							</div>
						<?php endif; ?>
						<label class="ocb-checkbox">
							<input type="checkbox" name="agree_terms" id="agree-terms" required />
							<span>I agree to the <a href="https://onroutecouriers.com/terms" target="_blank">Terms & Conditions</a> and <a href="https://onroutecouriers.com/privacy" target="_blank">Privacy Policy</a></span>
						</label>
					</div>

					<!-- Navigation -->
					<div class="ocb-step-nav">
						<?php
						$payment_settings = class_exists('OnRoute_Courier_Booking_Payment_Settings') ? 
							OnRoute_Courier_Booking_Payment_Settings::get_settings() : array('enabled' => false, 'mode' => 'none');
						$btn_text = 'Confirm Booking';
						$btn_icon = 'check';
						if ( $payment_settings['enabled'] && $payment_settings['mode'] !== 'none' ) {
							$btn_text = 'Pay Securely & Book';
							$btn_icon = 'credit-card';
							if ( $payment_settings['mode'] === 'deposit' ) {
								$btn_text = 'Pay Deposit & Book';
							}
						}
						?>
						<button type="button" class="ocb-btn ocb-btn-back" data-action="back">Back</button>
						<button type="submit" class="ocb-btn ocb-btn-submit">
							<i class="fas fa-<?php echo $btn_icon; ?>"></i> <?php echo esc_html( $btn_text ); ?>
						</button>
					</div>

				</div>

				<!-- Address Selection Modal -->
				<div id="ocb-address-modal" class="ocb-address-modal hidden">
					<div class="ocb-address-modal-content">
						<div class="ocb-address-modal-header">
							<h3>Select Address</h3>
							<button type="button" class="ocb-address-modal-close">&times;</button>
						</div>
						<div class="ocb-address-modal-body">
							<div id="ocb-address-loading" class="ocb-address-loading" style="text-align:center; padding:30px;">
								<div class="ocb-spinner-small"></div>
								<p>Loading addresses...</p>
							</div>
							<div id="ocb-address-list" class="ocb-address-list">
								<table class="ocb-address-table">
									<thead>
										<tr>
											<th class="ocb-address-col-select">Select</th>
											<th class="ocb-address-col-address">Addresses</th>
										</tr>
									</thead>
									<tbody id="ocb-address-tbody">
									</tbody>
								</table>
								<div id="ocb-address-pagination" class="ocb-address-pagination"></div>
							</div>
							<div id="ocb-address-error" class="ocb-address-error" style="display:none; color:#d32f2f; padding:15px; text-align:center;"></div>
						</div>
						<div class="ocb-address-modal-footer">
							<button type="button" class="ocb-btn ocb-btn-secondary ocb-address-modal-close"><i class="fas fa-times"></i> Close</button>
							<button type="button" class="ocb-btn ocb-btn-secondary" id="ocb-address-manual"><i class="fas fa-edit"></i> Manual Entry</button>
							<button type="button" class="ocb-btn ocb-btn-primary" id="ocb-address-select"><i class="fas fa-check"></i> Select Address</button>
						</div>
					</div>
				</div>

				<!-- Loading -->
				<div id="ocb-loading" class="ocb-loading-overlay" style="display:none;">
					<div class="ocb-spinner"></div>
					<span>Processing your booking...</span>
				</div>

			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Get current session data (bypass cache)
	 */
	public function ajax_get_session_data() {
		OnRoute_Courier_Booking_Session::init();
		
		$data = array(
			'collection_postcode' => OnRoute_Courier_Booking_Session::get( 'collection_postcode' ),
			'delivery_postcode' => OnRoute_Courier_Booking_Session::get( 'delivery_postcode' ),
			'collection_date' => OnRoute_Courier_Booking_Session::get( 'collection_date' ),
			'collection_time' => OnRoute_Courier_Booking_Session::get( 'collection_time' ),
			'booking_type' => OnRoute_Courier_Booking_Session::get( 'booking_type' ),
			'service_types' => OnRoute_Courier_Booking_Session::get( 'service_types' ),
			'selected_service' => OnRoute_Courier_Booking_Session::get( 'selected_service' ),
			'selected_vehicle' => OnRoute_Courier_Booking_Session::get( 'selected_vehicle' )
		);
		
		wp_send_json_success( $data );
	}

	public function ajax_submit_step() {
		// Check nonce
		if ( ! isset( $_POST['ocb_nonce'] ) || ! wp_verify_nonce( $_POST['ocb_nonce'], 'ocb_multi_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Please refresh and try again.' ) );
			return;
		}

		// Ensure database table exists
		$this->ensure_tables_exist();

		// Save selection data to session if provided
		OnRoute_Courier_Booking_Session::init();
		if ( isset( $_POST['service_type'] ) ) {
			OnRoute_Courier_Booking_Session::set( 'selected_service', sanitize_text_field( $_POST['service_type'] ) );
		}
		if ( isset( $_POST['selected_vehicle_id'] ) ) {
			OnRoute_Courier_Booking_Session::set( 'selected_vehicle', sanitize_text_field( $_POST['selected_vehicle_id'] ) );
		}

		// Generate booking reference
		$booking_ref = 'ONR-' . strtoupper( substr( md5( uniqid() ), 0, 8 ) );

		$first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
		$last_name = sanitize_text_field( $_POST['last_name'] ?? '' );
		$email = sanitize_email( $_POST['customer_email'] ?? '' );
		$phone = sanitize_text_field( $_POST['customer_phone'] ?? '' );
		$collection_pc = strtoupper( sanitize_text_field( $_POST['collection_postcode'] ?? '' ) );
		$delivery_pc = strtoupper( sanitize_text_field( $_POST['delivery_postcode'] ?? '' ) );
		$collection_address = sanitize_textarea_field( $_POST['collection_address'] ?? '' );
		$collection_address_line2 = sanitize_text_field( $_POST['collection_address_line2'] ?? '' );
		$collection_city = sanitize_text_field( $_POST['collection_city'] ?? '' );
		$delivery_address = sanitize_textarea_field( $_POST['delivery_address'] ?? '' );
		$delivery_address_line2 = sanitize_text_field( $_POST['delivery_address_line2'] ?? '' );
		$delivery_city = sanitize_text_field( $_POST['delivery_city'] ?? '' );
		$collection_contact = sanitize_text_field( $_POST['collection_contact'] ?? '' );
		$collection_phone = sanitize_text_field( $_POST['collection_phone'] ?? '' );
		$collection_company = sanitize_text_field( $_POST['collection_company'] ?? '' );
		$collection_instructions = sanitize_textarea_field( $_POST['collection_instructions'] ?? '' );
		$delivery_contact = sanitize_text_field( $_POST['delivery_contact'] ?? '' );
		$delivery_phone = sanitize_text_field( $_POST['delivery_phone'] ?? '' );
		$delivery_company = sanitize_text_field( $_POST['delivery_company'] ?? '' );
		
		// Handle Unified Datetime & Validation
		$delivery_date = date('Y-m-d');
		$delivery_time = '12:00';
		$delivery_window_start = '';
		$delivery_window_end = '';
		
		$server_now = current_time('timestamp');
		$service_type = sanitize_text_field( $_POST['service_type'] ?? 'timed' );

		if ( $service_type === 'timed' || $service_type === 'priority' ) {
			if ( ! empty( $_POST['delivery_datetime'] ) ) {
				$dt_str = sanitize_text_field( $_POST['delivery_datetime'] );
				$normalized_dt = str_replace( 'T', ' ', $dt_str );
				$ts = strtotime( $normalized_dt );
				if ( $ts ) {
					// Backend validation: Min +3 hours for Priority
					if ( $ts < ($server_now + 3 * 3600) ) {
						wp_send_json_error( array( 'message' => 'For Priority service, delivery must be at least 3 hours from now.' ) );
						return;
					}
					$delivery_date = date( 'Y-m-d', $ts );
					$delivery_time = date( 'H:i', $ts );
				}
			} else {
				wp_send_json_error( array( 'message' => 'Please select a delivery date and time for Priority service.' ) );
				return;
			}
		} else {
			// Same Day or Direct - Calculate Read-only Window
			$delivery_date = date( 'Y-m-d', $server_now );
			
			if ( $service_type === 'same_day' ) {
				$start_ts = $server_now + 3 * 3600;
				$end_ts = $server_now;
				if ( (int)date('H', $server_now) < 13 ) {
					$end_ts = strtotime( date('Y-m-d 17:00:00', $server_now) );
				} else {
					$end_ts = strtotime( date('Y-m-d 20:00:00', $server_now) );
				}
				if ($start_ts > $end_ts) $start_ts = $end_ts;
				
				$delivery_window_start = date('H:i', $start_ts);
				$delivery_window_end = date('H:i', $end_ts);
				$delivery_time = $delivery_window_start; // Use start as single time fallback
			} elseif ( $service_type === 'dedicated' || $service_type === 'direct' ) {
				$start_ts = $server_now + (1.5 * 3600);
				$end_ts = $server_now + 3 * 3600;
				
				$delivery_window_start = date('H:i', $start_ts);
				$delivery_window_end = date('H:i', $end_ts);
				$delivery_time = $delivery_window_start;
			}
		}

		$vehicle_id = sanitize_text_field( $_POST['selected_vehicle_id'] ?? '' );
		$service_type = sanitize_text_field( $_POST['service_type'] ?? 'timed' );
		$price = floatval( $_POST['selected_price'] ?? 0 );
		$distance = floatval( $_POST['distance_miles'] ?? 0 );

		// Validate required fields
		if ( empty( $first_name ) || empty( $email ) || empty( $phone ) ) {
			wp_send_json_error( array( 'message' => 'Please fill in all required fields.' ) );
			return;
		}

		// Validate UK postcodes
		if ( ! self::is_valid_uk_postcode( $collection_pc ) ) {
			wp_send_json_error( array( 'message' => 'Invalid UK collection postcode. Please enter a valid UK postcode (e.g., SW1A 1AA).' ) );
			return;
		}
		if ( ! self::is_valid_uk_postcode( $delivery_pc ) ) {
			wp_send_json_error( array( 'message' => 'Invalid UK delivery postcode. Please enter a valid UK postcode (e.g., E1 6AN).' ) );
			return;
		}

		// Validate email
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
			return;
		}

		if ( empty( $vehicle_id ) ) {
			wp_send_json_error( array( 'message' => 'Please select a vehicle.' ) );
			return;
		}

		// Calculate prices - VAT removed per client request
		$base_price = $price;
		$vat_amount = 0; // VAT disabled
		$total_price = $base_price;

		// ---------------------------------------------------------------------
		// PAYMENT LOGIC
		// ---------------------------------------------------------------------
		$payment_settings = OnRoute_Courier_Booking_Payment_Settings::get_settings();
		$payment_enabled = $payment_settings['enabled'];
		$payment_mode = $payment_settings['mode'];

		$amount_to_pay = 0;
		if ( $payment_enabled ) {
			if ( $payment_mode === 'full' ) {
				$amount_to_pay = $total_price;
			} elseif ( $payment_mode === 'deposit' ) {
				if ( $payment_settings['deposit_type'] === 'fixed' ) {
					$amount_to_pay = floatval( $payment_settings['deposit_amount'] );
				} else {
					$amount_to_pay = $total_price * ( floatval( $payment_settings['deposit_amount'] ) / 100 );
				}
				// Ensure deposit doesn't exceed total
				if ( $amount_to_pay > $total_price ) {
					$amount_to_pay = $total_price;
				}
			}
		}
		
		$use_business_credit = false;
		if ( isset( $_POST['payment_method_choice'] ) && $_POST['payment_method_choice'] === 'business_credit' ) {
			if ( is_user_logged_in() && OnRoute_Business_Credit::is_eligible_for_credit( get_current_user_id() ) ) {
				$use_business_credit = true;
				$amount_to_pay = 0; // Don't pay via Stripe
				$payment_enabled = false; 
			}
		}

		// Insert into database
		global $wpdb;
		$table = $wpdb->prefix . 'ocb_bookings';
		
		// Build notes JSON with all details
		$full_customer_name = $first_name . ' ' . $last_name;
		$notes_data = array(
			'customer_name_data' => $full_customer_name,
			'collection_contact' => $collection_contact,
			'collection_phone' => $collection_phone,
			'collection_company' => $collection_company,
			'collection_instructions' => $collection_instructions,
			'delivery_contact' => $delivery_contact,
			'delivery_phone' => $delivery_phone,
			'delivery_company' => $delivery_company,
			'distance_miles' => $distance,
			'delivery_window_start' => $delivery_window_start,
			'delivery_window_end' => $delivery_window_end,
			// Pricing transparency - show why price is what it is
			'pricing_info' => array(
				'locked_at_review' => true,
				'collection_time' => $collection_datetime_raw ?? $delivery_time,
				'delivery_time' => $delivery_time,
				'locked_price' => $price,
			),
		);

		$full_collection_address = $collection_address;
		if ( ! empty( $collection_address_line2 ) ) {
			$full_collection_address .= "\n" . $collection_address_line2;
		}
		if ( ! empty( $collection_city ) ) {
			$full_collection_address .= "\n" . $collection_city;
		}

		$full_delivery_address = $delivery_address;
		if ( ! empty( $delivery_address_line2 ) ) {
			$full_delivery_address .= "\n" . $delivery_address_line2;
		}
		if ( ! empty( $delivery_city ) ) {
			$full_delivery_address .= "\n" . $delivery_city;
		}

		// Prepare insert data
		$insert_data = array(
			'user_id' => is_user_logged_in() ? get_current_user_id() : null,
			'booking_reference' => $booking_ref,
			'customer_email' => $email,
			'customer_phone' => $phone,
			'pickup_address' => $full_collection_address,
			'pickup_postcode' => $collection_pc,
			'delivery_address' => $full_delivery_address,
			'delivery_postcode' => $delivery_pc,
			'collection_date' => $delivery_date,
			'collection_time' => $delivery_time,
			'delivery_date' => $delivery_date,
			'delivery_time' => $delivery_time,
			'vehicle_id' => $vehicle_id,
			'service_id' => $service_type,
			'base_price' => $base_price,
			'vat_amount' => $vat_amount,
			'discount_amount' => 0,
			'total_price' => $total_price,
			'notes' => json_encode( $notes_data ),
			'status' => 'pending',
			'payment_status' => 'unpaid',
			'payment_mode' => $payment_enabled ? $payment_mode : 'none',
			'amount_paid' => 0,
			'created_at' => current_time( 'mysql' ),
		);

		// Format string for wpdb->insert
		$format_data = array(
			'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%f', '%s'
		);

		// Conditionally add customer_name if column exists
		$check_col = $wpdb->get_results( "SHOW COLUMNS FROM $table LIKE 'customer_name'" );
		if ( ! empty( $check_col ) ) {
			// Add to the end of the array and the format
			$insert_data['customer_name'] = $full_customer_name;
			$format_data[] = '%s';
		}

			$result = $wpdb->insert( $table, $insert_data, $format_data );

		if ( $result ) {
			$booking_id = $wpdb->insert_id;
			OnRoute_Courier_Booking_Session::clear();

			// Handle Business Credit Adjustment
			if ( $use_business_credit ) {
				$account = OnRoute_Business_Credit::get_account_by_user( get_current_user_id() );
				if ( $account ) {
					OnRoute_Business_Credit::adjust_balance( $account->id, $total_price );
					$wpdb->update( $table, array( 'payment_status' => 'paid', 'payment_method' => 'business_credit' ), array( 'id' => $booking_id ) );
				}
				// Trigger email immediately for Business Credit
				OnRoute_Courier_Booking_Emails::send_booking_confirmation( $booking_id );
			}
			
			// Non-payment bookings or if payment is disabled
			if ( ! $payment_enabled || $amount_to_pay <= 0 ) {
				OnRoute_Courier_Booking_Emails::send_booking_confirmation( $booking_id );
			}
			
			$redirect_url = home_url( '/booking-confirmation/?ref=' . $booking_ref );

			// -----------------------------------------------------------------
			// STRIPE REDIRECT
			// -----------------------------------------------------------------
			if ( $payment_enabled && $amount_to_pay > 0 && $payment_mode !== 'none' ) {
				$stripe = new OnRoute_Courier_Booking_Stripe_API();
				
				// URLs
				$confirm_base = home_url( '/booking-confirmation/' );
				$success_url = $confirm_base . '?ref=' . $booking_ref . '&session_id={CHECKOUT_SESSION_ID}';
				$cancel_url = home_url( '/?booking_ref=' . $booking_ref . '&cancelled=true' );

				// Create Session
				$session_data = (object)array_merge(
					['id' => $booking_id, 'booking_reference' => $booking_ref], 
					$insert_data
				);

				$session = $stripe->create_checkout_session( 
					$session_data,
					$amount_to_pay,
					$payment_mode,
					$success_url,
					$cancel_url
				);

				if ( ! is_wp_error( $session ) && isset( $session['url'] ) ) {
					$redirect_url = $session['url'];
				} else {
					// Fallback to confirmation page but log error
					error_log( 'Stripe Session Error: ' . ( is_wp_error( $session ) ? $session->get_error_message() : json_encode($session) ) );
					// We redirect to confirmation, they will see "Unpaid" status
				}
			}
			
			wp_send_json_success( array(
				'message' => 'Booking created successfully!',
				'booking_id' => $booking_id,
				'booking_ref' => $booking_ref,
				'redirect' => $redirect_url,
			) );
		} else {
			error_log( 'OCB Booking Error: ' . $wpdb->last_error );
			wp_send_json_error( array( 'message' => 'Database error: ' . $wpdb->last_error ) );
		}
	}

	/**
	 * Ensure database tables exist
	 * Auto-creates tables if they don't exist
	 */
	private function ensure_tables_exist() {
		if ( class_exists( 'OnRoute_Courier_Booking_Database' ) ) {
			OnRoute_Courier_Booking_Database::create_tables();
		}
	}

	/**
	 * Create database tables (Deprecated - used OnRoute_Courier_Booking_Database instead)
	 */
	private function create_tables() {
		$this->ensure_tables_exist();
	}
}
