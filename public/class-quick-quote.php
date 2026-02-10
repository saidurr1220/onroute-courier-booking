<?php
/**
 * Quick Quote Shortcode for Home Page
 * Collects postcode and booking type, redirects to booking page
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Quick_Quote {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'onroute_quick_quote', array( $this, 'render_quick_quote' ) );
		add_action( 'wp_ajax_nopriv_ocb_start_booking', array( $this, 'ajax_start_booking' ) );
		add_action( 'wp_ajax_ocb_start_booking', array( $this, 'ajax_start_booking' ) );
	}

	/**
	 * Render quick quote form for home page
	 */
	public function render_quick_quote( $atts ) {
		$atts = shortcode_atts( array(
			'booking_page_url' => home_url( '/complete-your-booking' ),
		), $atts );

		OnRoute_Courier_Booking_Session::init();

		// Handle Edit Mode (Pre-fill)
		$pre_coll = isset($_GET['coll']) ? sanitize_text_field(urldecode($_GET['coll'])) : '';
		$pre_del = isset($_GET['del']) ? sanitize_text_field(urldecode($_GET['del'])) : '';
		$pre_dist = isset($_GET['dist']) ? floatval($_GET['dist']) : '';

		ob_start();
		?>
		<div class="ocb-quick-quote-wrapper">
			<div class="ocb-quick-quote-container">
				
				<!-- Animated Overlay Loader -->
				<div class="ocb-overlay-loader" style="display:none;">
					<div class="ocb-loader-content">
						<div class="ocb-spinner-large"></div>
						<div class="ocb-loader-text">Checking Availability<span class="ocb-dots">...</span></div>
					</div>
				</div>

				<h2 class="ocb-quick-title">Send a parcel</h2>

				<form id="ocb-quick-quote-form" class="ocb-quick-form">
					<?php wp_nonce_field( 'ocb_quick_quote', 'ocb_quick_nonce' ); ?>
					<input type="hidden" id="ocb_pre_distance" value="<?php echo esc_attr( $pre_dist ); ?>" />
					<input type="hidden" id="ocb_pre_coll" value="<?php echo esc_attr( strtoupper($pre_coll) ); ?>" />
					<input type="hidden" id="ocb_pre_del" value="<?php echo esc_attr( strtoupper($pre_del) ); ?>" />
					
					<div class="ocb-form-group">
						<label class="ocb-field-label">
							Collection postcode: <span class="ocb-required">*</span>
						</label>
						<input 
							type="text" 
							id="ocb_collection_postcode" 
							name="collection_postcode" 
							class="ocb-input" 
							placeholder="Pickup postcode" 
							value="<?php echo esc_attr( $pre_coll ); ?>"
							required 
						/>
					</div>

					<div class="ocb-form-group">
						<label class="ocb-field-label">
							Delivery postcode: <span class="ocb-required">*</span>
						</label>
						<input 
							type="text" 
							id="ocb_delivery_postcode" 
							name="delivery_postcode" 
							class="ocb-input" 
							placeholder="Delivery postcode" 
							value="<?php echo esc_attr( $pre_del ); ?>"
							required 
						/>
					</div>

					<div class="ocb-form-group">
						<label class="ocb-field-label">Are your items ready or are you pre-booking?</label>
						<div class="ocb-service-type-options">
							<label class="ocb-radio-card">
								<input type="radio" name="booking_type" value="ready_now" checked />
								<span class="ocb-radio-indicator"></span>
								<span class="ocb-radio-text">ASAP – Book Now</span>
							</label>
							<label class="ocb-radio-card">
								<input type="radio" name="booking_type" value="pre_book" />
								<span class="ocb-radio-indicator"></span>
								<span class="ocb-radio-text">Pre-Book – Schedule</span>
							</label>
						</div>
					</div>

					<div id="ocb-datetime-picker-group" class="ocb-form-group" style="display:none;">
						<label class="ocb-field-label">Select pickup date & time</label>
						<div class="ocb-date-time-row" style="display: flex; gap: 10px;">
							<div style="flex: 1;">
								<input 
									type="date" 
									id="ocb_pickup_date" 
									class="ocb-input" 
									placeholder="Date"
								/>
							</div>
							<div style="flex: 1;">
								<input 
									type="time" 
									id="ocb_pickup_time" 
									class="ocb-input" 
									placeholder="Time"
								/>
							</div>
						</div>
						<input type="hidden" id="ocb_pickup_datetime" name="pickup_datetime" />
					</div>

					<button type="submit" class="ocb-btn ocb-btn-primary ocb-btn-book">
						<span class="ocb-btn-text">BOOK NOW</span>
						<span class="ocb-btn-loader" style="display:none;"></span>
					</button>
				</form>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			
			// Native Date/Time Logic
			var $dateInput = $('#ocb_pickup_date');
			var $timeInput = $('#ocb_pickup_time');
			var $hiddenInput = $('#ocb_pickup_datetime');

			// Set min date to today
			var today = new Date().toISOString().split('T')[0];
			$dateInput.attr('min', today);

			function updateHiddenDatetime() {
				var date = $dateInput.val();
				var time = $timeInput.val();
				if(date && time) {
					$hiddenInput.val(date + ' ' + time);
				} else {
					$hiddenInput.val('');
				}
			}

			$dateInput.on('change', updateHiddenDatetime);
			$timeInput.on('change', updateHiddenDatetime);

			// Pre-book Visibility Logic
			$('input[name="booking_type"]').on('change', function() {
				if ($(this).val() === 'pre_book') {
					$('#ocb-datetime-picker-group').stop(true,true).slideDown();
					// Required prop separate from HTML5 required to avoid conflict with hidden input
				} else {
					$('#ocb-datetime-picker-group').stop(true,true).slideUp();
					$dateInput.val('');
					$timeInput.val('');
					$hiddenInput.val('');
				}
			});

			// Form submission
			$('#ocb-quick-quote-form').on('submit', function(e) {
				e.preventDefault();

				var $form = $(this);
				var $btn = $form.find('.ocb-btn-primary');
				var $loader = $btn.find('.ocb-btn-loader');

				// Validate postcodes
				var collectionPostcode = $('#ocb_collection_postcode').val().trim().toUpperCase();
				var deliveryPostcode = $('#ocb_delivery_postcode').val().trim().toUpperCase();
				var pickupDatetime = $('#ocb_pickup_datetime').val();
				
				if (!collectionPostcode || !deliveryPostcode) {
					alert('Please enter both pickup and delivery postcodes');
					return;
				}

				if (collectionPostcode.replace(/\s+/g, '') === deliveryPostcode.replace(/\s+/g, '')) {
					alert('Collection and delivery postcodes must be different');
					return;
				}

				// Get booking type logic
				var mainBookingType = $('input[name="booking_type"]:checked').val();
				
				// Validate Pre-Book Date
				if (mainBookingType === 'pre_book' && !pickupDatetime) {
					alert('Please select a pickup date and time');
					return;
				}

				// Show loading
				$btn.prop('disabled', true).addClass('ocb-loading');
				$btn.find('.ocb-btn-text').css('opacity', '0'); // Hide text
				$loader.show();
				
				// Show Overlay Loader
				var $overlay = $('.ocb-overlay-loader');
				$overlay.addClass('ocb-active');

				// Delay execution to allow smooth animation
				setTimeout(function() {
					processBooking();
				}, 150);

				function processBooking() {
					// Check for Pre-calculated Distance (Edit/Change Date Scenario)
					var preDist = parseFloat($('#ocb_pre_distance').val());
					var preColl = $('#ocb_pre_coll').val();
					var preDel = $('#ocb_pre_del').val();

					// If we have a cached distance and postcodes haven't changed, skip API
					if (preDist > 0 && collectionPostcode === preColl && deliveryPostcode === preDel) {
						// Use cached distance
						submitBooking(collectionPostcode, deliveryPostcode, mainBookingType, preDist, pickupDatetime);
						return; 
					}

					// CLIENT-SIDE DISTANCE CALCULATION (Bypasses Server API Restriction)
					// requires Google Maps JS API to be loaded on the page
					if (typeof google !== 'undefined' && google.maps && google.maps.DistanceMatrixService) {
						var service = new google.maps.DistanceMatrixService();
						service.getDistanceMatrix(
							{
								origins: [collectionPostcode],
								destinations: [deliveryPostcode],
								travelMode: 'DRIVING',
								unitSystem: google.maps.UnitSystem.IMPERIAL
							}, 
							function(response, status) {
								var calculatedDistance = 0;
								if (status == 'OK' && response.rows[0].elements[0].status == 'OK') {
									// Get distance in miles (text usually contains "mi")
									// Value is in meters
									var distanceMeters = response.rows[0].elements[0].distance.value;
									calculatedDistance = (distanceMeters * 0.000621371).toFixed(1);
								}
								
								// Proceed to server with calculated distance
								submitBooking(collectionPostcode, deliveryPostcode, mainBookingType, calculatedDistance, pickupDatetime);
							}
						);
					} else {
						// Fallback if Google Maps JS not loaded (should not happen if key is correct)
						submitBooking(collectionPostcode, deliveryPostcode, mainBookingType, 0, pickupDatetime);
					}
				}

				function submitBooking(coll, del, type, dist, datetime) {
					$.ajax({
						url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
						type: 'POST',
						data: {
							action: 'ocb_start_booking',
							nonce: $('#ocb_quick_nonce').val(),
							collection_postcode: coll,
							delivery_postcode: del,
							booking_type: type,
							client_distance: dist, // Send client-calcuated distance
							pickup_datetime: datetime
						},
						success: function(response) {
							if (response.success) {
								window.location.href = '<?php echo esc_url( $atts['booking_page_url'] ); ?>';
							} else {
								alert(response.data.message || 'An error occurred');
								$btn.prop('disabled', false).removeClass('ocb-loading');
								$btn.find('.ocb-btn-text').css('opacity', '1');
								$loader.hide();
								$('.ocb-overlay-loader').animate({opacity: 0}, 200, function(){ $(this).css('display', 'none'); });
							}
						},
						error: function() {
							alert('Connection error. Please try again.');
							$btn.prop('disabled', false).removeClass('ocb-loading');
							$btn.find('.ocb-btn-text').css('opacity', '1');
							$loader.hide();
							$('.ocb-overlay-loader').animate({opacity: 0}, 200, function(){ $(this).css('display', 'none'); });
						}
					});
				}
			});


			// Postcode formatting
			$('#ocb_collection_postcode, #ocb_delivery_postcode').on('blur', function() {
				var val = $(this).val().trim().toUpperCase();
				if (val) {
					$(this).val(val);
				}
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX handler to start booking
	 */
	public function ajax_start_booking() {
		check_ajax_referer( 'ocb_quick_quote', 'nonce' );

		$collection_postcode = isset( $_POST['collection_postcode'] ) ? sanitize_text_field( $_POST['collection_postcode'] ) : '';
		$delivery_postcode = isset( $_POST['delivery_postcode'] ) ? sanitize_text_field( $_POST['delivery_postcode'] ) : '';
		$booking_type = isset( $_POST['booking_type'] ) ? sanitize_text_field( $_POST['booking_type'] ) : 'ready';
		
		// Map service types
		$raw_service_type = isset( $_POST['service_type'] ) ? sanitize_text_field( $_POST['service_type'] ) : 'same_day';
		$service_map = array(
			'same_day' => 'same_day',
			'priority' => 'timed',
			'direct'   => 'dedicated',
		);
		$service_type = isset( $service_map[ $raw_service_type ] ) ? $service_map[ $raw_service_type ] : 'same_day';

		if ( empty( $collection_postcode ) || empty( $delivery_postcode ) ) {
			wp_send_json_error( array( 'message' => 'Both postcodes are required' ) );
		}

		// Normalize postcodes for comparison (remove spaces, convert to uppercase)
		$normalized_collection = strtoupper( str_replace( ' ', '', $collection_postcode ) );
		$normalized_delivery = strtoupper( str_replace( ' ', '', $delivery_postcode ) );

		// Validate that postcodes are different
		if ( $normalized_collection === $normalized_delivery ) {
			wp_send_json_error( array( 'message' => 'Collection and delivery postcodes must be different' ) );
		}

		// Validate UK postcode formats
		if ( ! preg_match( '/^[A-Z]{1,2}[0-9R][0-9A-Z]?\s?[0-9][ABD-HJLNP-UW-Z]{2}$/i', $collection_postcode ) ) {
			wp_send_json_error( array( 'message' => 'Invalid pickup postcode format. Please enter a valid UK postcode.' ) );
		}
		if ( ! preg_match( '/^[A-Z]{1,2}[0-9R][0-9A-Z]?\s?[0-9][ABD-HJLNP-UW-Z]{2}$/i', $delivery_postcode ) ) {
			wp_send_json_error( array( 'message' => 'Invalid delivery postcode format. Please enter a valid UK postcode.' ) );
		}

		// Initialize session
		OnRoute_Courier_Booking_Session::init();

		// Calculate distance
		$distance = 0;
		if ( isset( $_POST['client_distance'] ) && floatval( $_POST['client_distance'] ) > 0 ) {
			// Trust client distance if provided (bypasses server API restriction)
			$distance = floatval( $_POST['client_distance'] );
			
			// Cache this distance for future backend use without API calls
			OnRoute_Courier_Booking_Distance_Matrix::cache_distance( $collection_postcode, $delivery_postcode, $distance );
		} else {
			// Fallback to server calculation (might fail if key is restricted)
			$distance = OnRoute_Courier_Booking_Distance_Matrix::get_distance( $collection_postcode, $delivery_postcode );
			if ( is_wp_error( $distance ) ) {
				$distance = 10; // Fallback
			}
		}

		// Store initial data in session (Legacy support)
		OnRoute_Courier_Booking_Session::set( 'collection_postcode', $collection_postcode );
		OnRoute_Courier_Booking_Session::set( 'delivery_postcode', $delivery_postcode );
		OnRoute_Courier_Booking_Session::set( 'booking_type', $booking_type );
		
		// Handle Pickup Date & Time
		$pickup_datetime = isset( $_POST['pickup_datetime'] ) ? sanitize_text_field( $_POST['pickup_datetime'] ) : '';
		
		if ( ! empty( $pickup_datetime ) ) {
			// Handle both T separator (HTML5) and space separator (Flatpickr)
			// Normalize to ensure strtotime works correctly
			$normalized_datetime = str_replace( 'T', ' ', $pickup_datetime );
			$ts = strtotime( $normalized_datetime );
			
			if ( $ts ) {
				$date_part = date( 'Y-m-d', $ts );
				$time_part = date( 'H:i', $ts ); // Ensure H:i format (24h)
			} else {
				// Fallback if parsing fails
				$date_part = date( 'Y-m-d' );
				$time_part = date( 'H:i' );
			}
			
			// Set session variables for Pricing Logic
			OnRoute_Courier_Booking_Session::set( 'pickup_datetime', $pickup_datetime );
			OnRoute_Courier_Booking_Session::set( 'collection_date', $date_part );
			OnRoute_Courier_Booking_Session::set( 'collection_time', $time_part );
		} else {
			// Default to now if not provided (Ready Now)
			OnRoute_Courier_Booking_Session::set( 'pickup_datetime', date('Y-m-d H:i') );
			OnRoute_Courier_Booking_Session::set( 'collection_date', date('Y-m-d') );
			OnRoute_Courier_Booking_Session::set( 'collection_time', date('H:i') );
		}

		OnRoute_Courier_Booking_Session::set( 'service_types', array( $service_type ) );
		OnRoute_Courier_Booking_Session::set( 'quote_time', time() );

		// Populate Step 1 Data for Forms Class compatibility
		$step1_data = array(
			'step1_collection_postcode' => $collection_postcode,
			'step1_delivery_postcode' => $delivery_postcode,
			'step1_service_type' => $service_type,
			'step1_distance_miles' => $distance,
			'step1_collection_date' => OnRoute_Courier_Booking_Session::get('collection_date'),
			'step1_collection_time' => OnRoute_Courier_Booking_Session::get('collection_time'),
		);
		OnRoute_Courier_Booking_Session::set_step( 1, $step1_data );

		wp_send_json_success( array(
			'message' => 'Booking started',
			'redirect' => true,
		) );
	}
}
