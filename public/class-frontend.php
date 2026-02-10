<?php
/**
 * Frontend booking forms class
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Frontend {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'onroute_booking', array( $this, 'render_booking_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Render booking shortcode
	 */
	public function render_booking_shortcode( $atts ) {
		// Start session if needed
		if ( ! session_id() ) {
			session_start();
		}

		ob_start();
		?>
		<div class="ocb-booking-container">
			<!-- Step Indicator -->
			<div class="ocb-steps">
				<div class="ocb-step ocb-step-active" data-step="1">
					<span class="ocb-step-number">1</span>
					<span class="ocb-step-label">Quote Request</span>
				</div>
				<div class="ocb-step" data-step="2">
					<span class="ocb-step-number">2</span>
					<span class="ocb-step-label">Results</span>
				</div>
				<div class="ocb-step" data-step="3">
					<span class="ocb-step-number">3</span>
					<span class="ocb-step-label">Details</span>
				</div>
				<div class="ocb-step" data-step="4">
					<span class="ocb-step-number">4</span>
					<span class="ocb-step-label">Review & Book</span>
				</div>
			</div>

			<!-- STEP 1: Quote Request -->
			<div class="ocb-step-form ocb-step-1-form ocb-step-active">
				<?php $this->render_step_1(); ?>
			</div>

			<!-- STEP 2: Results -->
			<div class="ocb-step-form ocb-step-2-form" style="display:none;">
				<?php $this->render_step_2(); ?>
			</div>

			<!-- STEP 3: Booking Details -->
			<div class="ocb-step-form ocb-step-3-form" style="display:none;">
				<?php $this->render_step_3(); ?>
			</div>

			<!-- STEP 4: Review & Book -->
			<div class="ocb-step-form ocb-step-4-form" style="display:none;">
				<?php $this->render_step_4(); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render step 1: Quote request
	 */
	private function render_step_1() {
		?>
		<form class="ocb-form ocb-quote-form">
			<h2>Get Your Quote</h2>

			<div class="ocb-form-group">
				<label for="pickup_code">Pickup Postcode *</label>
				<input type="text" id="pickup_code" name="pickup_code" placeholder="e.g., SW1A 1AA" required />
			</div>

			<div class="ocb-form-group">
				<label for="delivery_code">Delivery Postcode *</label>
				<input type="text" id="delivery_code" name="delivery_code" placeholder="e.g., M1 1AE" required />
			</div>

			<button type="button" class="ocb-btn ocb-btn-primary ocb-search-quote">Get Quote</button>
		</form>
		<?php
	}

	/**
	 * Render step 2: Results
	 */
	private function render_step_2() {
		$pricing = new OnRoute_Courier_Booking_Pricing();
		?>
		<div class="ocb-results">
			<h2>Select Service & Vehicle</h2>

			<div class="ocb-quote-summary" id="ocb-quote-summary"></div>

			<div class="ocb-services">
				<h3>Delivery Services</h3>
				<div class="ocb-service-cards" id="ocb-services"></div>
			</div>

			<div class="ocb-vehicles">
				<h3>Vehicle Type</h3>
				<div class="ocb-vehicle-cards" id="ocb-vehicles"></div>
			</div>

			<div class="ocb-price-display">
				<div class="ocb-price-item">
					<span>Base Price:</span>
					<strong id="ocb-base-price">£0.00</strong>
				</div>
				<div class="ocb-price-item">
					<span>VAT (<?php echo esc_html( $pricing->get_vat_rate() ); ?>%):</span>
					<strong id="ocb-vat-amount">£0.00</strong>
				</div>
				<div class="ocb-price-item ocb-total">
					<span>Total:</span>
					<strong id="ocb-total-price">£0.00</strong>
				</div>
			</div>

			<div class="ocb-form-actions">
				<button type="button" class="ocb-btn ocb-btn-secondary ocb-back-quote">Back</button>
				<button type="button" class="ocb-btn ocb-btn-primary ocb-next-booking" disabled id="ocb-next-booking-btn">Continue to Booking</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render step 3: Booking details
	 */
	private function render_step_3() {
		?>
		<form class="ocb-form ocb-booking-details-form">
			<h2>Booking Details</h2>

			<div class="ocb-form-row">
				<div class="ocb-form-group">
					<label for="customer_email">Email Address *</label>
					<input type="email" id="customer_email" name="customer_email" required />
				</div>

				<div class="ocb-form-group">
					<label for="customer_phone">Phone Number *</label>
					<input type="tel" id="customer_phone" name="customer_phone" required />
				</div>
			</div>

			<div class="ocb-form-group">
				<label for="pickup_address">Pickup Address *</label>
				<textarea id="pickup_address" name="pickup_address" required placeholder="Street address, building, etc."></textarea>
			</div>

			<div class="ocb-form-group">
				<label for="delivery_address">Delivery Address *</label>
				<textarea id="delivery_address" name="delivery_address" required placeholder="Street address, building, etc."></textarea>
			</div>

			<div class="ocb-form-row">
				<div class="ocb-form-group">
					<label for="collection_date">Collection Date *</label>
					<input type="date" id="collection_date" name="collection_date" required />
				</div>

				<div class="ocb-form-group">
					<label for="collection_time">Collection Time *</label>
					<input type="time" id="collection_time" name="collection_time" required />
				</div>
			</div>

			<div class="ocb-form-row">
				<div class="ocb-form-group">
					<label for="delivery_date">Delivery Date *</label>
					<input type="date" id="delivery_date" name="delivery_date" required />
				</div>

				<div class="ocb-form-group">
					<label for="delivery_time">Delivery Time (Optional)</label>
					<input type="time" id="delivery_time" name="delivery_time" />
				</div>
			</div>

			<div class="ocb-form-actions">
				<button type="button" class="ocb-btn ocb-btn-secondary ocb-back-results">Back</button>
				<button type="button" class="ocb-btn ocb-btn-primary ocb-next-review">Review & Book</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Render step 4: Review & Book
	 */
	private function render_step_4() {
		?>
		<form class="ocb-form ocb-review-form">
			<h2>Review & Confirm Booking</h2>

			<!-- Booking Summary -->
			<div class="ocb-section ocb-booking-summary">
				<h3>Booking Summary</h3>
				<div class="ocb-summary-grid">
					<div class="ocb-summary-item">
						<span class="ocb-label">Email</span>
						<span class="ocb-value" id="review-email"></span>
					</div>
					<div class="ocb-summary-item">
						<span class="ocb-label">Phone</span>
						<span class="ocb-value" id="review-phone"></span>
					</div>
					<div class="ocb-summary-item">
						<span class="ocb-label">Pickup Address</span>
						<span class="ocb-value" id="review-pickup-address"></span>
					</div>
					<div class="ocb-summary-item">
						<span class="ocb-label">Delivery Address</span>
						<span class="ocb-value" id="review-delivery-address"></span>
					</div>
					<div class="ocb-summary-item">
						<span class="ocb-label">Collection</span>
						<span class="ocb-value" id="review-collection"></span>
					</div>
					<div class="ocb-summary-item">
						<span class="ocb-label">Delivery</span>
						<span class="ocb-value" id="review-delivery"></span>
					</div>
					<div class="ocb-summary-item">
						<span class="ocb-label">Service</span>
						<span class="ocb-value" id="review-service"></span>
					</div>
					<div class="ocb-summary-item">
						<span class="ocb-label">Vehicle</span>
						<span class="ocb-value" id="review-vehicle"></span>
					</div>
				</div>
			</div>

			<!-- Price Breakdown -->
			<div class="ocb-section ocb-price-breakdown">
				<h3>Price Breakdown</h3>
				<div class="ocb-breakdown-items">
					<div class="ocb-breakdown-item">
						<span>Base Price</span>
						<strong id="review-base-price">£0.00</strong>
					</div>
					<div class="ocb-breakdown-item">
						<span>VAT</span>
						<strong id="review-vat">£0.00</strong>
					</div>
					<div class="ocb-breakdown-item" id="ocb-discount-item" style="display:none;">
						<span>Discount</span>
						<strong id="review-discount" class="ocb-discount-negative">-£0.00</strong>
					</div>
					<div class="ocb-breakdown-item ocb-total-item">
						<span>Total Price</span>
						<strong id="review-total-price">£0.00</strong>
					</div>
				</div>
			</div>

			<!-- Promo Code -->
			<div class="ocb-section ocb-promo-section">
				<h3>Promo Code</h3>
				<div class="ocb-promo-input">
					<input type="text" id="promo_code" name="promo_code" placeholder="Enter promo code" />
					<button type="button" class="ocb-btn ocb-btn-secondary ocb-apply-promo">Apply</button>
				</div>
				<div id="ocb-promo-message"></div>
			</div>

			<!-- Terms & Conditions -->
			<div class="ocb-section ocb-terms-section">
				<label class="ocb-checkbox-label">
					<input type="checkbox" id="terms_accepted" name="terms_accepted" required />
					<span>I agree to the <a href="#" target="_blank">Terms & Conditions</a> *</span>
				</label>
			</div>

			<!-- Form Hidden Fields -->
			<input type="hidden" id="review-vehicle-id" name="vehicle_id" />
			<input type="hidden" id="review-service-id" name="service_id" />
			<input type="hidden" id="review-pickup-code" name="pickup_code" />
			<input type="hidden" id="review-delivery-code" name="delivery_code" />

			<div class="ocb-form-actions">
				<button type="button" class="ocb-btn ocb-btn-secondary ocb-back-details">Back</button>
				<button type="button" class="ocb-btn ocb-btn-success ocb-confirm-booking" disabled id="ocb-confirm-btn">Confirm & Book</button>
			</div>

			<p class="ocb-loading" id="ocb-loading" style="display:none;">Processing your booking...</p>
		</form>
		<?php
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'ocb-frontend-css', ONROUTE_COURIER_BOOKING_URL . 'assets/frontend.css' );
		wp_enqueue_style( 'ocb-forms-css', ONROUTE_COURIER_BOOKING_URL . 'assets/forms.css' );
		wp_enqueue_script( 'ocb-frontend-js', ONROUTE_COURIER_BOOKING_URL . 'assets/frontend.js', array( 'jquery' ), ONROUTE_COURIER_BOOKING_VERSION, true );

		wp_localize_script( 'ocb-frontend-js', 'ocbData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ocb_nonce' ),
		) );
	}
}
