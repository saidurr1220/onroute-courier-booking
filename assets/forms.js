(function($) {
	'use strict';

	// Service Tab Handler - Quote Summary Page
	$(document).on('click', '.ocb-service-tab', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var serviceType = $btn.data('service');
		var $container = $('#ocb-vehicles-container');

		// Update active tab
		$('.ocb-service-tab').removeClass('ocb-service-active');
		$btn.addClass('ocb-service-active');

		// Reload vehicles for selected service
		$.ajax({
			url: ocbForms.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ocb_get_vehicles',
				service_type: serviceType
			},
			dataType: 'html',
			success: function(html) {
				$container.html(html);
			}
		});
	});

	// Vehicle Selection Handler
	$(document).on('click', '.ocb-select-vehicle', function(e) {
		e.preventDefault();

		var $btn = $(this);
		var vehicleId = $btn.data('vehicle');
		var serviceType = $('.ocb-service-tab.ocb-service-active').data('service') || 'same_day';

		$btn.prop('disabled', true).text('Selecting...');

		// Submit vehicle selection via AJAX
		$.ajax({
			url: ocbForms.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ocb_select_vehicle',
				nonce: $('input[name="nonce"]').val(),
				vehicle_id: vehicleId,
				service_type: serviceType
			},
			dataType: 'json',
			success: function(response) {
				if (response.success) {
					// Redirect to booking form
					window.location.href = response.data.redirect;
				} else {
					alert(response.data.message);
					$btn.prop('disabled', false).text('Select');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$btn.prop('disabled', false).text('Select');
			}
		});
	});

	// Quote Form Handler
	$(document).on('submit', '#ocb-quote-form', function(e) {
		e.preventDefault();

		var $form = $(this);
		var $error = $('#ocb-quote-error');
		var $loading = $('#ocb-quote-loading');

		// Clear previous errors
		$error.hide().html('');
		$loading.show();

		// Get form data
		var formData = $form.serialize();

		// Submit via AJAX
		$.ajax({
			url: ocbForms.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ocb_submit_quote',
				nonce: $form.find('input[name="nonce"]').val(),
				...parseFormData($form)
			},
			dataType: 'json',
			success: function(response) {
				$loading.hide();

				if (response.success) {
					// Redirect to quote summary page
					window.location.href = response.data.redirect;
				} else {
					$error.html(response.data.message).show();
				}
			},
			error: function(xhr, status, error) {
				$loading.hide();
				$error.html('An error occurred. Please try again.').show();
			}
		});
	});

	// Booking Form Handler
	$(document).on('submit', '#ocb-booking-form', function(e) {
		e.preventDefault();

		var $form = $(this);
		var $error = $('#ocb-booking-error');
		var $success = $('#ocb-booking-success');
		var $loading = $('#ocb-booking-loading');

		// Clear previous messages
		$error.hide().html('');
		$success.hide().html('');
		$loading.show();

		// Submit via AJAX
		$.ajax({
			url: ocbForms.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ocb_submit_booking',
				nonce: $form.find('input[name="nonce"]').val(),
				...parseFormData($form)
			},
			dataType: 'json',
			success: function(response) {
				$loading.hide();

				if (response.success) {
					$success.html(response.data.message).show();
					setTimeout(function() {
						window.location.href = response.data.redirect;
					}, 2000);
				} else {
					$error.html(response.data.message).show();
				}
			},
			error: function(xhr, status, error) {
				$loading.hide();
				$error.html('An error occurred. Please try again.').show();
			}
		});
	});

	// Helper function to parse form data
	function parseFormData($form) {
		var data = {};
		$form.find('input, textarea, select').each(function() {
			var $field = $(this);
			var name = $field.attr('name');
			var value = $field.val();

			if (name && value !== undefined) {
				data[name] = value;
			}
		});
		return data;
	}

	// Address Selection Modal Handler
	var currentAddressType = null;
	var selectedAddress = null;

	$(document).on('click', '.ocb-btn-select-address', function(e) {
		e.preventDefault();
		currentAddressType = $(this).data('postcode-type'); // 'collection' or 'delivery'
		
		var postcode = $('.ocb-postcode-display').val();
		
		// Show modal
		$('#ocb-address-modal').show();
		$('.ocb-address-loading').show();
		$('.ocb-address-items').empty().hide();
		$('.ocb-address-form').hide();
		
		// Fetch addresses from AJAX
		$.ajax({
			url: ocbForms.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ocb_fetch_addresses',
				nonce: $('input[name="nonce"]').val(),
				postcode: postcode
			},
			success: function(response) {
				if (response.success && response.data.addresses) {
					renderAddressList(response.data.addresses);
				} else {
					$('.ocb-address-loading').html('<p style="color:red;">Error loading addresses</p>');
				}
			}
		});
	});

	function renderAddressList(addresses) {
		var html = '';
		addresses.forEach(function(addr, index) {
			html += '<div class="ocb-address-item" style="padding:12px; border:1px solid #e0e0e0; border-radius:4px; margin-bottom:10px; cursor:pointer;" data-address="' + esc(addr.address) + '">';
			html += '<input type="radio" name="ocb-address-selection" value="' + index + '" class="ocb-address-radio">';
			html += '<span style="margin-left:8px;">' + esc(addr.address) + '</span>';
			html += '</div>';
		});
		
		$('.ocb-address-items').html(html).show();
		$('.ocb-address-loading').hide();
		$('.ocb-address-form').show();
	}

	function esc(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	// Address Selection Confirm
	$(document).on('click', '#ocb-address-confirm', function(e) {
		e.preventDefault();
		
		var $selected = $('input[name="ocb-address-selection"]:checked');
		var manualAddress = $('#ocb-manual-address').val();
		
		if ($selected.length > 0) {
			selectedAddress = $selected.closest('.ocb-address-item').data('address');
		} else if (manualAddress) {
			selectedAddress = manualAddress;
		} else {
			alert('Please select an address or enter one manually');
			return;
		}
		
		// Set the address field
		var fieldName = 'step3_' + currentAddressType + '_address';
		$('textarea[name="' + fieldName + '"]').val(selectedAddress);
		
		// Close modal
		$('#ocb-address-modal').hide();
	});

	// Modal Close Handler
	$(document).on('click', '.ocb-modal-close, .ocb-modal-close-btn', function(e) {
		e.preventDefault();
		$('#ocb-address-modal').hide();
	});

	$(document).on('click', '#ocb-address-modal', function(e) {
		if (e.target === this) {
			$(this).hide();
		}
	});

	// Session Storage Helper
	window.ocbSession = {
		set: function(key, value) {
			sessionStorage.setItem('ocb_' + key, JSON.stringify(value));
		},
		get: function(key) {
			var value = sessionStorage.getItem('ocb_' + key);
			return value ? JSON.parse(value) : null;
		},
		clear: function() {
			sessionStorage.clear();
		}
	};

})(jQuery);
