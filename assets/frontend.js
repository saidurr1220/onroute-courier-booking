/**
 * OnRoute Courier Booking - Frontend JavaScript
 */

jQuery(document).ready(function($) {
	// State management
	const bookingState = {
		currentStep: 1,
		pickupCode: '',
		deliveryCode: '',
		vehicleId: '',
		serviceId: '',
		basePrice: 0,
		vatRate: 0,
		vatAmount: 0,
		discountAmount: 0,
		totalPrice: 0,
		promoCode: '',
		termsAccepted: false,
	};

	// ======== STEP 1: QUOTE REQUEST ========

	$('.ocb-search-quote').on('click', function() {
		const pickupCode = $('#pickup_code').val().trim().toUpperCase();
		const deliveryCode = $('#delivery_code').val().trim().toUpperCase();

		if (!pickupCode || !deliveryCode) {
			alert('Please enter both postcodes');
			return;
		}

		// Validate that postcodes are different
		if (pickupCode === deliveryCode) {
			alert('Collection and delivery postcodes must be different');
			return;
		}

		bookingState.pickupCode = pickupCode;
		bookingState.deliveryCode = deliveryCode;

		$.ajax({
			type: 'POST',
			url: ocbData.ajaxUrl,
			data: {
				action: 'ocb_quote_search',
				nonce: ocbData.nonce,
				pickup_code: pickupCode,
				delivery_code: deliveryCode,
			},
			success: function(response) {
				if (response.success) {
					goToStep(2);
					displayResults(response.data);
				} else {
					alert('Error: ' + response.data.message);
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
			}
		});
	});

	// ======== STEP 2: RESULTS ========

	function displayResults(quoteData) {
		// Display quote summary
		$('#ocb-quote-summary').html(`
			<strong>Shipping from ${quoteData.pickup.city} (${quoteData.pickup.code}) to ${quoteData.delivery.city} (${quoteData.delivery.code})</strong>
		`);

		// Get pricing data
		const pricing = new PricingEngine();
		const services = pricing.getServices();
		const vehicles = pricing.getVehicles();

		// Display services
		let servicesHtml = '';
		services.forEach(service => {
			servicesHtml += `
				<div class="ocb-card" data-service-id="${service.id}">
					<div class="ocb-card-title">${service.name}</div>
					<div class="ocb-card-meta">Service Multiplier: ${service.multiplier.toFixed(2)}x</div>
				</div>
			`;
		});
		$('#ocb-services').html(servicesHtml);

		// Display vehicles
		let vehiclesHtml = '';
		vehicles.forEach(vehicle => {
			vehiclesHtml += `
				<div class="ocb-card" data-vehicle-id="${vehicle.id}">
					<div class="ocb-card-title">${vehicle.name}</div>
					<div class="ocb-card-price">£${vehicle.base_price.toFixed(2)}</div>
					<div class="ocb-card-meta">Max: ${vehicle.max_weight}kg</div>
				</div>
			`;
		});
		$('#ocb-vehicles').html(vehiclesHtml);

		// Disable next button until selection is made
		$('#ocb-next-booking-btn').prop('disabled', true);
	}

	// Handle service selection
	$(document).on('click', '.ocb-services .ocb-card', function() {
		$('.ocb-services .ocb-card').removeClass('ocb-card-selected');
		$(this).addClass('ocb-card-selected');
		bookingState.serviceId = $(this).data('service-id');
		calculatePrice();
	});

	// Handle vehicle selection
	$(document).on('click', '.ocb-vehicles .ocb-card', function() {
		$('.ocb-vehicles .ocb-card').removeClass('ocb-card-selected');
		$(this).addClass('ocb-card-selected');
		bookingState.vehicleId = $(this).data('vehicle-id');
		calculatePrice();
	});

	function calculatePrice() {
		if (!bookingState.serviceId || !bookingState.vehicleId) {
			$('#ocb-next-booking-btn').prop('disabled', true);
			return;
		}

		$.ajax({
			type: 'POST',
			url: ocbData.ajaxUrl,
			data: {
				action: 'ocb_calculate_price',
				nonce: ocbData.nonce,
				vehicle_id: bookingState.vehicleId,
				service_id: bookingState.serviceId,
				weight: 0,
			},
			success: function(response) {
				if (response.success) {
					bookingState.basePrice = response.data.base_price;
					bookingState.vatRate = response.data.vat_rate;
					bookingState.vatAmount = bookingState.basePrice * (bookingState.vatRate / 100);
					bookingState.totalPrice = bookingState.basePrice + bookingState.vatAmount;

					updatePriceDisplay();
					$('#ocb-next-booking-btn').prop('disabled', false);
				}
			}
		});
	}

	function updatePriceDisplay() {
		$('#ocb-base-price').text('£' + bookingState.basePrice.toFixed(2));
		$('#ocb-vat-amount').text('£' + bookingState.vatAmount.toFixed(2));
		const displayTotal = bookingState.basePrice + bookingState.vatAmount - bookingState.discountAmount;
		$('#ocb-total-price').text('£' + displayTotal.toFixed(2));
	}

	// ======== STEP 3: BOOKING DETAILS ========

	$('.ocb-next-booking').on('click', function() {
		goToStep(3);
	});

	$('.ocb-back-results').on('click', function() {
		goToStep(2);
	});

	$('.ocb-next-review').on('click', function() {
		// Validate form
		const form = $('.ocb-booking-details-form')[0];
		if (!form.checkValidity()) {
			form.reportValidity();
			return;
		}

		// Store form data
		bookingState.email = $('#customer_email').val();
		bookingState.phone = $('#customer_phone').val();
		bookingState.pickupAddress = $('#pickup_address').val();
		bookingState.deliveryAddress = $('#delivery_address').val();
		bookingState.collectionDate = $('#collection_date').val();
		bookingState.collectionTime = $('#collection_time').val();
		bookingState.deliveryDate = $('#delivery_date').val();
		bookingState.deliveryTime = $('#delivery_time').val();

		goToStep(4);
		populateReview();
	});

	// ======== STEP 4: REVIEW & BOOK ========

	function populateReview() {
		// Booking summary
		$('#review-email').text(bookingState.email);
		$('#review-phone').text(bookingState.phone);
		$('#review-pickup-address').text(bookingState.pickupAddress);
		$('#review-delivery-address').text(bookingState.deliveryAddress);
		$('#review-collection').text(formatDateTime(bookingState.collectionDate, bookingState.collectionTime));
		$('#review-delivery').text(formatDateTime(bookingState.deliveryDate, bookingState.deliveryTime));

		// Get service and vehicle names
		const pricing = new PricingEngine();
		const service = pricing.getService(bookingState.serviceId);
		const vehicle = pricing.getVehicle(bookingState.vehicleId);

		$('#review-service').text(service ? service.name : '');
		$('#review-vehicle').text(vehicle ? vehicle.name : '');

		// Price breakdown
		$('#review-base-price').text('£' + bookingState.basePrice.toFixed(2));
		$('#review-vat').text('£' + bookingState.vatAmount.toFixed(2));

		// Update hidden fields for form submission
		$('#review-vehicle-id').val(bookingState.vehicleId);
		$('#review-service-id').val(bookingState.serviceId);
		$('#review-pickup-code').val(bookingState.pickupCode);
		$('#review-delivery-code').val(bookingState.deliveryCode);

		updateReviewTotals();
	}

	function updateReviewTotals() {
		if (bookingState.discountAmount > 0) {
			$('#ocb-discount-item').show();
			$('#review-discount').text('-£' + bookingState.discountAmount.toFixed(2));
		} else {
			$('#ocb-discount-item').hide();
		}

		const total = bookingState.basePrice + bookingState.vatAmount - bookingState.discountAmount;
		$('#review-total-price').text('£' + total.toFixed(2));
		bookingState.totalPrice = total;
	}

	// Handle promo code application
	$('.ocb-apply-promo').on('click', function() {
		const promoCode = $('#promo_code').val().trim();
		if (!promoCode) {
			alert('Please enter a promo code');
			return;
		}

		$.ajax({
			type: 'POST',
			url: ocbData.ajaxUrl,
			data: {
				action: 'ocb_apply_promo',
				nonce: ocbData.nonce,
				code: promoCode,
				base_price: bookingState.basePrice,
			},
			success: function(response) {
				if (response.success) {
					bookingState.discountAmount = response.data.discount_amount;
					bookingState.promoCode = promoCode;

					const messageDiv = $('#ocb-promo-message');
					messageDiv.removeClass('error').addClass('success');
					messageDiv.html('✓ Promo code applied: ');
					if (response.data.discount_type === 'fixed') {
						messageDiv.append('£' + response.data.discount_amount.toFixed(2) + ' discount');
					} else {
						messageDiv.append(response.data.discount_value + '% discount');
					}

					updateReviewTotals();
				} else {
					const messageDiv = $('#ocb-promo-message');
					messageDiv.removeClass('success').addClass('error');
					messageDiv.text('✗ ' + response.data.message);
					bookingState.discountAmount = 0;
					bookingState.promoCode = '';
				}
			},
			error: function() {
				const messageDiv = $('#ocb-promo-message');
				messageDiv.removeClass('success').addClass('error');
				messageDiv.text('✗ Error applying promo code');
			}
		});
	});

	// Handle terms checkbox
	$('#terms_accepted').on('change', function() {
		bookingState.termsAccepted = this.checked;
		$('#ocb-confirm-btn').prop('disabled', !this.checked);
	});

	// Confirm booking
	$('.ocb-confirm-booking').on('click', function(e) {
		e.preventDefault();

		if (!bookingState.termsAccepted) {
			alert('Please accept terms and conditions');
			return;
		}

		$('#ocb-loading').show();
		$('.ocb-confirm-booking').prop('disabled', true);

		$.ajax({
			type: 'POST',
			url: ocbData.ajaxUrl,
			data: {
				action: 'ocb_create_booking',
				nonce: ocbData.nonce,
				email: bookingState.email,
				phone: bookingState.phone,
				pickup_address: bookingState.pickupAddress,
				pickup_code: bookingState.pickupCode,
				delivery_address: bookingState.deliveryAddress,
				delivery_code: bookingState.deliveryCode,
				collection_date: bookingState.collectionDate,
				collection_time: bookingState.collectionTime,
				delivery_date: bookingState.deliveryDate,
				delivery_time: bookingState.deliveryTime,
				vehicle_id: bookingState.vehicleId,
				service_id: bookingState.serviceId,
				base_price: bookingState.basePrice,
				vat_amount: bookingState.vatAmount,
				discount_amount: bookingState.discountAmount,
				total_price: bookingState.totalPrice,
				promo_code: bookingState.promoCode,
			},
			success: function(response) {
				if (response.success) {
					// Booking created successfully
					alert('Booking confirmed! Booking ID: ' + response.data.booking_id + '\n\nA confirmation email has been sent to ' + bookingState.email);
					window.location.href = response.data.redirect;
				} else {
					alert('Error: ' + response.data.message);
					$('#ocb-loading').hide();
					$('.ocb-confirm-booking').prop('disabled', false);
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$('#ocb-loading').hide();
				$('.ocb-confirm-booking').prop('disabled', false);
			}
		});
	});

	// Back buttons
	$('.ocb-back-quote').on('click', function() {
		goToStep(1);
	});

	$('.ocb-back-details').on('click', function() {
		goToStep(3);
	});

	// ======== UTILITIES ========

	function goToStep(step) {
		bookingState.currentStep = step;

		// Hide all forms
		$('.ocb-step-form').hide();
		$('.ocb-step').removeClass('ocb-step-active ocb-step-completed');

		// Show current step
		$('.ocb-step-' + step + '-form').show();
		$('.ocb-step[data-step="' + step + '"]').addClass('ocb-step-active');

		// Mark previous steps as completed
		for (let i = 1; i < step; i++) {
			$('.ocb-step[data-step="' + i + '"]').addClass('ocb-step-completed');
		}

		// Scroll to top
		$('html, body').animate({ scrollTop: 0 }, 300);
	}

	function formatDateTime(date, time) {
		if (!date) return '';
		const dateObj = new Date(date);
		const formatted = dateObj.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
		return time ? formatted + ' at ' + time : formatted;
	}

	// ======== PRICING ENGINE ========

	class PricingEngine {
		constructor() {
			// This would be loaded from your WordPress options
			this.vehicles = [
				{ id: 'small_parcel', name: 'Small Parcel', base_price: 5.00, max_weight: 2, active: 1 },
				{ id: 'large_parcel', name: 'Large Parcel', base_price: 8.00, max_weight: 10, active: 1 },
				{ id: 'pallet', name: 'Pallet', base_price: 15.00, max_weight: 100, active: 1 },
			];

			this.services = [
				{ id: 'next_day', name: 'Next Day', multiplier: 1.0, active: 1 },
				{ id: 'same_day', name: 'Same Day', multiplier: 1.5, active: 1 },
				{ id: 'economy', name: 'Economy', multiplier: 0.8, active: 1 },
			];
		}

		getVehicles() {
			return this.vehicles.filter(v => v.active);
		}

		getVehicle(id) {
			return this.vehicles.find(v => v.id === id);
		}

		getServices() {
			return this.services.filter(s => s.active);
		}

		getService(id) {
			return this.services.find(s => s.id === id);
		}
	}
});
