(function($) {
	'use strict';

	var MultiStepForm = {
		currentStep: 1,
		maxSteps: 4,
		selectedService: 'same_day',
		selectedVehicle: null,

		init: function() {
			this.bindEvents();
			this.updateUI();
		},

		bindEvents: function() {
			// Navigation buttons
			$(document).on('click', '#ocb-btn-next', $.proxy(this.nextStep, this));
			$(document).on('click', '#ocb-btn-back', $.proxy(this.prevStep, this));
			$(document).on('submit', '#ocb-multi-form', $.proxy(this.submitForm, this));

			// Service selection (Step 2)
			$(document).on('click', '.ocb-service-btn', $.proxy(this.selectService, this));

			// Vehicle selection (Step 2)
			$(document).on('click', '.ocb-vehicle-card', $.proxy(this.selectVehicle, this));

			// Address lookup
			$(document).on('click', '.ocb-btn-lookup-addresses', $.proxy(this.lookupAddresses, this));

			// Address modal
			$(document).on('click', '.ocb-modal-close, #ocb-modal-close', function() {
				$('#ocb-multi-address-modal').hide();
			});
			$(document).on('click', '#ocb-modal-select', $.proxy(this.selectAddressFromModal, this));
		},

		nextStep: function(e) {
			e.preventDefault();

			// Collect form data
			var formData = this.collectFormData();

			// Submit current step
			$.ajax({
				url: ocbForms.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ocb_multi_next_step',
					nonce: $('input[name="nonce"]').val(),
					current_step: this.currentStep,
					form_data: formData
				},
				success: $.proxy(function(response) {
					if (response.success) {
						this.currentStep = response.data.next_step;
						
						// Prepare Step 2 vehicles if moving to Step 2
						if (2 === this.currentStep && response.data.data.vehicles) {
							this.renderVehicles(response.data.data.vehicles);
							this.displayQuoteSummary();
							this.startQuoteTimer();
						}
						
						this.updateUI();
					} else {
						alert('Error: ' + (response.data.message || 'Failed to proceed'));
					}
				}, this),
				error: function() {
					alert('Error: Could not connect to server');
				}
			});
		},

		prevStep: function(e) {
			e.preventDefault();

			$.ajax({
				url: ocbForms.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ocb_multi_prev_step',
					nonce: $('input[name="nonce"]').val(),
					current_step: this.currentStep
				},
				success: $.proxy(function(response) {
					if (response.success) {
						this.currentStep = response.data.prev_step;
						this.restoreFormData(response.data.data);
						this.updateUI();
					}
				}, this)
			});
		},

		submitForm: function(e) {
			e.preventDefault();

			// Validate Step 4
			if (!$('input[name="agree_terms"]').is(':checked')) {
				alert('Please accept Terms & Conditions');
				return;
			}

			// Show loading
			$('#ocb-multi-loading').show();

			// Submit final form
			$.ajax({
				url: ocbForms.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ocb_multi_submit_step',
					nonce: $('input[name="nonce"]').val()
				},
				success: $.proxy(function(response) {
					$('#ocb-multi-loading').hide();
					if (response.success) {
						alert('Booking confirmed! Redirecting...');
						window.location.href = response.data.redirect;
					} else {
						$('#ocb-multi-error').text(response.data.message || 'Booking failed').show();
					}
				}, this),
				error: function() {
					$('#ocb-multi-loading').hide();
					alert('Error submitting booking');
				}
			});
		},

		collectFormData: function() {
			var formData = {};

			if (1 === this.currentStep) {
				formData = {
					collection_postcode: $('input[name="collection_postcode"]').val(),
					collection_address: $('textarea[name="collection_address"]').val(),
					delivery_postcode: $('input[name="delivery_postcode"]').val(),
					delivery_address: $('textarea[name="delivery_address"]').val()
				};
			} else if (2 === this.currentStep) {
				formData = {
					selected_vehicle: this.selectedVehicle,
					selected_service: this.selectedService
				};
			} else if (3 === this.currentStep) {
				formData = {
					first_name: $('input[name="first_name"]').val(),
					last_name: $('input[name="last_name"]').val(),
					email: $('input[name="email"]').val(),
					phone: $('input[name="phone"]').val(),
					pickup_date: $('input[name="pickup_date"]').val(),
					pickup_time: $('input[name="pickup_time"]').val(),
					delivery_date: $('input[name="delivery_date"]').val(),
					delivery_time: $('input[name="delivery_time"]').val(),
					instructions: $('textarea[name="instructions"]').val()
				};
			}

			return formData;
		},

		restoreFormData: function(data) {
			if (1 === this.currentStep && data) {
				$('input[name="collection_postcode"]').val(data.collection_postcode || '');
				$('textarea[name="collection_address"]').val(data.collection_address || '');
				$('input[name="delivery_postcode"]').val(data.delivery_postcode || '');
				$('textarea[name="delivery_address"]').val(data.delivery_address || '');
			} else if (3 === this.currentStep && data) {
				$('input[name="first_name"]').val(data.first_name || '');
				$('input[name="last_name"]').val(data.last_name || '');
				$('input[name="email"]').val(data.email || '');
				$('input[name="phone"]').val(data.phone || '');
				$('input[name="pickup_date"]').val(data.pickup_date || '');
				$('input[name="pickup_time"]').val(data.pickup_time || '');
				$('input[name="delivery_date"]').val(data.delivery_date || '');
				$('input[name="delivery_time"]').val(data.delivery_time || '');
				$('textarea[name="instructions"]').val(data.instructions || '');
			}
		},

		selectService: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var service = $btn.data('service');

			$('.ocb-service-btn').removeClass('ocb-service-active');
			$btn.addClass('ocb-service-active');

			this.selectedService = service;

			// Reload vehicles for new service
			var vehicles = window.ocbVehicles || {};
			this.renderVehicles(vehicles);
		},

		selectVehicle: function(e) {
			e.preventDefault();
			var $card = $(e.currentTarget);
			var vehicleId = $card.data('vehicle');

			$('.ocb-vehicle-card').removeClass('ocb-vehicle-selected');
			$card.addClass('ocb-vehicle-selected');

			this.selectedVehicle = vehicleId;
		},

		renderVehicles: function(vehicles) {
			var html = '<div class="ocb-vehicles-grid">';
			var service = this.selectedService;

			if (!vehicles || Object.keys(vehicles).length === 0) {
				html += '<p>Loading vehicles...</p>';
			} else {
				$.each(vehicles, function(vehicleId, vehicle) {
					var pricing = vehicle[service] || vehicle.same_day;
					var isSelected = (vehicleId === MultiStepForm.selectedVehicle) ? ' ocb-vehicle-selected' : '';

					html += '<div class="ocb-vehicle-card' + isSelected + '" data-vehicle="' + vehicleId + '">';
					html += '<h4>' + vehicle.name + '</h4>';
					html += '<p class="ocb-vehicle-dimensions">' + (vehicle.dimensions || '') + '</p>';
					html += '<p class="ocb-vehicle-capacity">' + (vehicle.capacity || '') + '</p>';
					html += '<div class="ocb-vehicle-pricing">';
					html += '<span class="ocb-price">£' + parseFloat(pricing.total).toFixed(2) + '</span>';
					html += '<span class="ocb-vat">inc. VAT</span>';
					html += '</div>';
					html += '</div>';
				});
			}

			html += '</div>';
			$('#ocb-vehicles-for-step-2').html(html);
			window.ocbVehicles = vehicles;
		},

		lookupAddresses: function(e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var locationType = $btn.data('location');
			var postcode = $btn.prev().prev('.ocb-postcode-input').val();

			if (!postcode) {
				alert('Please enter a postcode');
				return;
			}

			// Store for later use
			window.currentLocationLookup = locationType;

			// Show modal
			$('#ocb-multi-address-modal').show();
			$('#ocb-modal-addresses').html('<p style="text-align:center; padding:20px;">Loading addresses for ' + postcode + '...</p>');

			// In production, call real API here
			// For now, show mock addresses
			this.loadMockAddresses(postcode, locationType);
		},

		loadMockAddresses: function(postcode, locationType) {
			// Mock addresses - replace with real API call
			var addresses = [
				{
					id: 1,
					address: '123 Main Street, Oxfordshire, ' + postcode,
					formatted: '123 Main Street'
				},
				{
					id: 2,
					address: '456 High Road, Oxfordshire, ' + postcode,
					formatted: '456 High Road'
				},
				{
					id: 3,
					address: '789 Park Lane, Oxfordshire, ' + postcode,
					formatted: '789 Park Lane'
				},
				{
					id: 4,
					address: '321 Queen Street, Oxfordshire, ' + postcode,
					formatted: '321 Queen Street'
				}
			];

			this.renderAddressList(addresses);
		},

		renderAddressList: function(addresses) {
			var html = '';
			$.each(addresses, function(index, addr) {
				html += '<div style="padding:12px; border:1px solid #e0e0e0; border-radius:4px; margin-bottom:10px; cursor:pointer;" data-address="' + addr.address + '">';
				html += '<input type="radio" name="address-selection" value="' + index + '">';
				html += '<span style="margin-left:8px;">' + addr.address + '</span>';
				html += '</div>';
			});

			$('#ocb-modal-addresses').html(html);
		},

		selectAddressFromModal: function(e) {
			e.preventDefault();
			var $selected = $('input[name="address-selection"]:checked').closest('div');
			var address = $selected.data('address');
			var locationType = window.currentLocationLookup;

			if (address && locationType) {
				var fieldName = locationType === 'collection' ? 'collection_address' : 'delivery_address';
				$('textarea[name="' + fieldName + '"]').val(address);
				$('#ocb-multi-address-modal').hide();
			}
		},

		updateUI: function() {
			// Update step indicator
			$('.ocb-multi-step-item').removeClass('ocb-multi-step-active ocb-multi-step-completed');
			$('.ocb-multi-step-item').each(function() {
				var step = parseInt($(this).data('step'));
				if (step < MultiStepForm.currentStep) {
					$(this).addClass('ocb-multi-step-completed');
				} else if (step === MultiStepForm.currentStep) {
					$(this).addClass('ocb-multi-step-active');
				}
			});

			// Show/hide step content
			$('.ocb-multi-step-content').hide();
			$('[data-step="' + this.currentStep + '"]').show();

			// Update buttons
			$('#ocb-btn-back').toggle(this.currentStep > 1);
			$('#ocb-btn-next').toggle(this.currentStep < this.maxSteps);
			$('#ocb-btn-submit').toggle(this.currentStep === this.maxSteps);

			// Update form attribute
			$('#ocb-multi-form').attr('data-current-step', this.currentStep);
		},

		displayQuoteSummary: function() {
			var collectionPostcode = $('input[name="collection_postcode"]').val() || '';
			var deliveryPostcode = $('input[name="delivery_postcode"]').val() || '';

			$('#summary-collection-postcode').text(collectionPostcode);
			$('#summary-delivery-postcode').text(deliveryPostcode);
		},

		startQuoteTimer: function() {
			var now = new Date();
			var validUntil = new Date(now.getTime() + 15 * 60000); // 15 minutes from now

			// Format time display
			var hours = String(validUntil.getHours()).padStart(2, '0');
			var mins = String(validUntil.getMinutes()).padStart(2, '0');
			var dateStr = validUntil.toLocaleDateString('en-GB', { 
				month: 'long', 
				day: 'numeric',
				year: 'numeric'
			});

			$('#quote-timer').text('From ' + hours + ':' + mins + ' on ' + dateStr);

			// Update countdown every second
			var timerInterval = setInterval(function() {
				var remaining = validUntil - new Date();
				if (remaining <= 0) {
					$('#quote-timer').text('Quote expired!').css('color', '#d32f2f');
					clearInterval(timerInterval);
				} else {
					var mins = Math.floor((remaining / 1000) / 60);
					var secs = Math.floor((remaining / 1000) % 60);
					$('#quote-timer').text('Valid for ' + mins + 'm ' + secs + 's');
				}
			}, 1000);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		MultiStepForm.init();
	});

})(jQuery);
