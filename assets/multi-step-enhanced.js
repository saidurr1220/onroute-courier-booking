/**
 * Multi-Step Booking Form JavaScript
 * Handles form navigation, validation, and Maps API
 */

(function($) {
	'use strict';

	// Configuration
	const OCB_CONFIG = {
		GOOGLE_MAPS_API_KEY: null, // Will be fetched securely from server
		QUOTE_VALID_MINUTES: 15,
		BASE_PRICING: {
			motorbike: { same_day: 39.97, timed: 45.00, dedicated: 55.00 },
			small_van: { same_day: 56.63, timed: 65.00, dedicated: 75.00 },
			large_van: { same_day: 72.77, timed: 85.00, dedicated: 95.00 },
			xl_van: { same_day: 80.77, timed: 95.00, dedicated: 110.00 }
		},
		POSTCODE_BASE_PRICES: {
			'SW': 5.00,
			'E1': 3.00,
			'W1': 6.00,
			'EC': 7.00,
			'WC': 6.50,
			'N': 4.00,
			'NW': 4.50,
			'SE': 3.50,
			'DEFAULT': 5.00
		},
		VAT_RATE: 0.20
	};

	class OnRouteBookingForm {
		constructor() {
			this.currentStep = 1;
			this.totalSteps = 4;
			this.formData = {};
			this.quoteTimer = null;
			this.quoteExpiry = null;
			this.apiKeyLoaded = false;

			this.init();
		}

		init() {
		
		
		// Check Font Awesome
		console.log('🎨 Checking Font Awesome...', {
			'FA CSS loaded': !!document.querySelector('link[href*="font-awesome"]'),
			'Can render test icon': this.testFontAwesome()
		});

		this.loadAPIKey(); // Load API key securely first
		this.bindEvents();
		this.loadSessionData();
		this.startQuoteTimer();
		this.updateStepIndicator();
		
		
		}

		/**
		 * Test if Font Awesome is working
		 */
		testFontAwesome() {
			const test = $('<i class="fa-solid fa-truck"></i>');
			$('body').append(test);
			const computed = window.getComputedStyle(test[0], ':before');
			const content = computed.getPropertyValue('content');
			test.remove();
			return content && content !== 'none' && content !== '';
		}

		/**
		 * Calculate Distance from Postcodes using Google Maps Distance Matrix API
		 */
		async calculateDistanceFromPostcodes() {
			const collectionPostcode = $('input[name="collection_postcode"]').val();
			const deliveryPostcode = $('input[name="delivery_postcode"]').val();

			
			

			if (!collectionPostcode || !deliveryPostcode) {
				
				return;
			}

			// Show loading state
			$('#ocb-distance-info').show().find('#ocb-distance-text').text('Calculating distance from postcodes...');
			$('.ocb-loading-vehicles').show();

			try {
				// Wait for Google Maps API to load
				
				await this.waitForGoogleMaps();
				

				const service = new google.maps.DistanceMatrixService();
				
				const response = await new Promise((resolve, reject) => {
					service.getDistanceMatrix({
						origins: [collectionPostcode + ', UK'],
						destinations: [deliveryPostcode + ', UK'],
						travelMode: google.maps.TravelMode.DRIVING,
						unitSystem: google.maps.UnitSystem.IMPERIAL
					}, (response, status) => {
						
						if (status === 'OK') {
							resolve(response);
						} else {
							reject(status);
						}
					});
				});

				if (response.rows[0].elements[0].status === 'OK') {
					const distanceInMeters = response.rows[0].elements[0].distance.value;
					const distanceInMiles = (distanceInMeters * 0.000621371).toFixed(1);
					const durationInSeconds = response.rows[0].elements[0].duration.value;
					const durationInMinutes = Math.ceil(durationInSeconds / 60);

					

					// Store distance data
					this.formData.distance_miles = parseFloat(distanceInMiles);
					this.formData.duration_minutes = durationInMinutes;

					// Update UI
					$('#ocb-distance-text').html(`<strong>${distanceInMiles} miles</strong> - Estimated ${durationInMinutes} minutes drive time`);

					// Calculate delivery times based on service type
					this.calculateDeliveryTimes();

					// Load vehicles with real pricing
					
					this.loadVehiclesCitySprint();

				} else {
					throw new Error('No route found');
				}

			} catch (error) {
				
				
				// Check if it's a billing issue
				if (error === 'REQUEST_DENIED') {
					
					
					$('#ocb-distance-text').html('<strong>Distance calculation unavailable</strong> - Using estimated pricing. <a href="https://console.cloud.google.com/billing" target="_blank" style="color: white; text-decoration: underline;">Enable billing on Google Cloud</a>');
				} else {
					$('#ocb-distance-text').text('Unable to calculate distance. Using estimated pricing.');
				}
				
				// Use fallback distance
				this.formData.distance_miles = 10;
				this.formData.duration_minutes = 30;
				
				
				
				this.calculateDeliveryTimes();
				this.loadVehiclesCitySprint();
			}
		}

		/**
		 * Wait for Google Maps API to load
		 */
		waitForGoogleMaps() {
			return new Promise((resolve, reject) => {
				// Check if Google Maps is already loaded
				if (typeof google !== 'undefined' && google.maps && google.maps.DistanceMatrixService) {
					
					resolve();
					return;
				}

				
				let attempts = 0;
				const maxAttempts = 100; // 10 seconds
				
				const checkGoogleMaps = () => {
					if (typeof google !== 'undefined' && google.maps && google.maps.DistanceMatrixService) {
						
						resolve();
					} else if (attempts < maxAttempts) {
						attempts++;
						setTimeout(checkGoogleMaps, 100);
					} else {
						
						console.log('🔍 Check:', {
							'google defined': typeof google !== 'undefined',
							'google.maps': typeof google !== 'undefined' && !!google.maps,
							'API Key in ocbData': window.ocbData && !!window.ocbData.googleMapsKey
						});
						reject('Google Maps API failed to load - check API key and network connection');
					}
				};
				
				checkGoogleMaps();
			});
		}

		/**
		 * Calculate Delivery Times based on Service Type
		 */
		calculateDeliveryTimes() {
			const serverNow = window.ocbData && window.ocbData.serverTime ? new Date(window.ocbData.serverTime) : new Date();
			
			// Collection Estimation (Standard 60 mins)
			const collectionTime = new Date(serverNow.getTime() + 60 * 60000);
			const collectionTimeStr = this.formatTime(collectionTime);

			// 1. Same Day Logic
			const sameDayStart = new Date(serverNow.getTime() + 3 * 3600000);
			let sameDayEnd = new Date(serverNow);
			if (serverNow.getHours() < 13) {
				sameDayEnd.setHours(17, 0, 0, 0);
			} else {
				sameDayEnd.setHours(20, 0, 0, 0);
			}
			if (sameDayStart > sameDayEnd) sameDayStart.setTime(sameDayEnd.getTime());
			const sameDayText = `${this.formatTime(sameDayStart)} - ${this.formatTime(sameDayEnd)}`;

			// 2. Direct (Dedicated) Logic: +1h 30m
			const directTime = new Date(serverNow.getTime() + 1.5 * 3600000);
			const directTimeText = `By ${this.formatTime(directTime)}`;

			// 3. Priority (Timed) Logic: +3h
			const priorityTime = new Date(serverNow.getTime() + 3 * 3600000);
			const priorityTimeText = `By ${this.formatTime(priorityTime)}`;

			// Update service tab descriptions
			$('.ocb-service-tab[data-service="dedicated"] .ocb-collect-time').text(collectionTimeStr);
			$('.ocb-service-tab[data-service="dedicated"] .ocb-deliver-time').text(directTimeText);

			$('.ocb-service-tab[data-service="same_day"] .ocb-collect-time').text(collectionTimeStr);
			$('.ocb-service-tab[data-service="same_day"] .ocb-deliver-time').text(sameDayText);

			$('.ocb-service-tab[data-service="timed"] .ocb-collect-time').text(collectionTimeStr);
			$('.ocb-service-tab[data-service="timed"] .ocb-deliver-time').text(priorityTimeText);

			// Store for later use
			this.formData.collection_time = collectionTimeStr;
		}

		/**
		 * Format Time (HH:MM AM/PM)
		 */
		formatTime(date) {
			let hours = date.getHours();
			const minutes = String(date.getMinutes()).padStart(2, '0');
			const ampm = hours >= 12 ? 'PM' : 'AM';
			hours = hours % 12;
			hours = hours ? hours : 12; // the hour '0' should be '12'
			return `${hours}:${minutes} ${ampm}`;
		}

		/**
		 * Format Time 24h (HH:MM)
		 */
		formatTime24(date) {
			const hours = String(date.getHours()).padStart(2, '0');
			const minutes = String(date.getMinutes()).padStart(2, '0');
			return `${hours}:${minutes}`;
		}

		/**
		 * Update Delivery Time UI based on Service Type
		 */
		updateDeliveryTimeUI() {
			const serviceType = $('#selected-service-type').val();
			

			const $windowDisplay = $('#ocb-delivery-window-display');
			const $windowText = $('#ocb-delivery-window-text');
			const $timePicker = $('#ocb-priority-time-picker');
			
			const serverNow = window.ocbData && window.ocbData.serverTime ? new Date(window.ocbData.serverTime) : new Date();
			
			// Reset
			$windowDisplay.hide();
			$timePicker.hide();
			$('#ocb_step2_date, #ocb_step2_time').prop('required', false);

			if (serviceType === 'same_day') {
				// SAME DAY Logic
				let start = new Date(serverNow.getTime() + 3 * 3600000);
				let end = new Date(serverNow.getTime());
				
				if (serverNow.getHours() < 13) {
					end.setHours(17, 0, 0);
				} else {
					end.setHours(20, 0, 0);
				}

				if (start > end) start = new Date(end.getTime());

				$windowText.text(`${this.formatTime(start)} – ${this.formatTime(end)}`);
				$windowDisplay.show();

			} else if (serviceType === 'dedicated' || serviceType === 'direct') {
				// DIRECT Logic
				const start = new Date(serverNow.getTime() + (1 * 3600000 + 30 * 60000)); // +1.5h
				const end = new Date(serverNow.getTime() + 3 * 3600000); // +3h

				$windowText.text(`${this.formatTime(start)} – ${this.formatTime(end)}`);
				$windowDisplay.show();

			} else if (serviceType === 'priority' || serviceType === 'timed') {
				// PRIORITY Logic
				$timePicker.show();
				$('#ocb_step2_date, #ocb_step2_time').prop('required', true);

				// Initialize with +3h
				const minTime = new Date(serverNow.getTime() + 3 * 3600000);
				$('#ocb_step2_date').val(minTime.toISOString().split('T')[0]);
				$('#ocb_step2_time').val(this.formatTime24(minTime));
			}
		}

		/**
		 * Load Google Maps API Key Securely from Server
		 */
		loadAPIKey() {
			if (!window.ocbData) {
				
				return;
			}

			// Check if API key is available in localized data
			if (window.ocbData.googleMapsKey) {
				OCB_CONFIG.GOOGLE_MAPS_API_KEY = window.ocbData.googleMapsKey;
				this.apiKeyLoaded = true;
				
			} else {
				
			}
		}

		/**
		 * Bind Event Listeners
		 */
		bindEvents() {
			const self = this;
			
			// Navigation buttons
			$('#ocb-btn-next').on('click', () => this.nextStep());
			$('#ocb-btn-back').on('click', () => this.prevStep());
			$('#ocb-btn-submit').on('click', (e) => this.submitForm(e));

			// Vehicle Card Click
			$(document).on('click', '.ocb-vehicle-citysprint-card', function() {
				$('.ocb-vehicle-citysprint-card').removeClass('selected');
				$(this).addClass('selected');
				
				// Store selected service type and vehicle
				const serviceType = $(this).data('service-type');
				const vehicleId = $(this).data('vehicle-id');
				$('#selected-service-type').val(serviceType);
				$('#selected-vehicle-id').val(vehicleId);
			});

			// Book Button Click
			$(document).on('click', '.ocb-vehicle-book-btn', function(e) {
				e.stopPropagation();
				const $card = $(this).closest('.ocb-vehicle-citysprint-card');
				$('.ocb-vehicle-citysprint-card').removeClass('selected');
				$card.addClass('selected');
				
				const vehicleId = $card.data('vehicle-id');
				const vehicleName = $card.data('vehicle-name');
				const serviceType = $card.data('service-type');
				const priceExcl = $card.data('price-excl');
				const priceIncl = $card.data('price-incl');
				
				console.log('🚗 Vehicle selected:', {
					id: vehicleId,
					name: vehicleName,
					service: serviceType,
					price: '£' + priceExcl
				});
				
				// Store selected vehicle data
				self.formData.selected_vehicle = {
					id: vehicleId,
					name: vehicleName,
					service_type: serviceType,
					price_excl: priceExcl,
					price_incl: priceIncl
				};
				self.formData.vehicle_type = vehicleId;
				self.formData.service_type = serviceType;
				self.formData.base_price = priceExcl;
				self.formData.total_price = priceIncl;
				
				// Store in hidden fields
				$('#selected-service-type').val(serviceType);
				$('#selected-vehicle-id').val(vehicleId);
				
				console.log('✅ Vehicle data stored:', {
					hiddenServiceType: $('#selected-service-type').val(),
					hiddenVehicleId: $('#selected-vehicle-id').val()
				});
				
				// Go to next step
				self.nextStep();
			});

			// Address lookup
			$('.ocb-btn-find-address').on('click', (e) => this.findAddress(e));

			// Edit buttons in review
			$('.ocb-edit-btn').on('click', (e) => {
				const step = $(e.currentTarget).data('edit-step');
				this.goToStep(step);
			});

			// Postcode formatting
			$('input[name="delivery_postcode"]').on('blur', (e) => {
				const val = $(e.target).val().trim().toUpperCase();
				$(e.target).val(val);
			});
		}

		/**
		 * Load Session Data
		 */
		loadSessionData() {
			// Load initial postcode from session (from home page)
			const initialPostcode = $('#summary-collection-postcode').first().text();
			if (initialPostcode && initialPostcode !== '—') {
				this.formData.collection_postcode = initialPostcode;
			}
		}

		/**
		 * Start Quote Timer
		 */
		startQuoteTimer() {
			if (this.quoteExpiry) return;

			this.quoteExpiry = new Date();
			this.quoteExpiry.setMinutes(this.quoteExpiry.getMinutes() + OCB_CONFIG.QUOTE_VALID_MINUTES);

			this.updateQuoteTimer();
			this.quoteTimer = setInterval(() => this.updateQuoteTimer(), 1000);
		}

		updateQuoteTimer() {
			const now = new Date();
			const diff = this.quoteExpiry - now;

			if (diff <= 0) {
				clearInterval(this.quoteTimer);
				$('#ocb-timer-minutes').text('EXPIRED');
				this.showError('Your quote has expired. Please refresh the page to get a new quote.');
				return;
			}

			const minutes = Math.floor(diff / 60000);
			const seconds = Math.floor((diff % 60000) / 1000);
			$('#ocb-timer-minutes').text(`${minutes}:${seconds.toString().padStart(2, '0')}`);
		}

		/**
		 * Update Step Indicator
		 */
		updateStepIndicator() {
			$('.ocb-multi-step-item').each((index, el) => {
				const stepNum = $(el).data('step');
				$(el).removeClass('ocb-multi-step-active ocb-multi-step-completed');

				if (stepNum < this.currentStep) {
					$(el).addClass('ocb-multi-step-completed');
				} else if (stepNum === this.currentStep) {
					$(el).addClass('ocb-multi-step-active');
				}
			});

			// Update button visibility
			if (this.currentStep === 1) {
				$('#ocb-btn-back').hide();
			} else {
				$('#ocb-btn-back').show();
			}

			if (this.currentStep === this.totalSteps) {
				$('#ocb-btn-next').hide();
				$('#ocb-btn-submit').show();
			} else {
				$('#ocb-btn-next').show();
				$('#ocb-btn-submit').hide();
			}
		}

		/**
		 * Go to Specific Step
		 */
		goToStep(stepNumber) {
			if (stepNumber < 1 || stepNumber > this.totalSteps) return;

			// Hide all steps
			$('.ocb-multi-step-content').hide();

			// Show target step
			$(`.ocb-multi-step-content[data-step="${stepNumber}"]`).show().css('animation', 'ocb-fadeIn 0.5s ease-out');

			this.currentStep = stepNumber;
			this.updateStepIndicator();

			// Scroll to top
			$('html, body').animate({ scrollTop: $('.ocb-multi-step-container').offset().top - 50 }, 500);
		}

		/**
		 * Next Step
		 */
		async nextStep() {
			if (!this.validateCurrentStep()) {
				return;
			}

			// Collect current step data
			this.collectStepData(this.currentStep);

			// Special handling for each step
			switch (this.currentStep) {
				case 1:
					// Step 1 is now Vehicle Selection - save selected vehicle
					this.saveSelectedVehicle();
					break;
				case 2:
					// Step 2 is Delivery Time - save delivery time
					break;
				case 3:
					// Step 3 is Contact Details - prepare review
					this.prepareReview();
					break;
			}

			// Move to next step
			this.goToStep(this.currentStep + 1);

			// Update Delivery UI if moving to Step 2
			if (this.currentStep === 2) {
				this.updateDeliveryTimeUI();
			}
		}

		/**
		 * Previous Step
		 */
		prevStep() {
			if (this.currentStep > 1) {
				this.goToStep(this.currentStep - 1);
			}
		}

		/**
		 * Validate Current Step
		 */
		validateCurrentStep() {
			const $currentStep = $(`.ocb-multi-step-content[data-step="${this.currentStep}"]`);
			const $requiredFields = $currentStep.find('[required]');
			let isValid = true;

			$requiredFields.each(function() {
				const $field = $(this);
				const val = $field.val();

				if (!val || val === '') {
					isValid = false;
					$field.addClass('ocb-field-error');
					$field.focus();
				} else {
					$field.removeClass('ocb-field-error');
				}
			});

			// Step-specific validation
			switch (this.currentStep) {
				case 1: {
					// Step 1 is Vehicle Selection - check if a vehicle is selected
					const selectedVehicleId = $('#selected-vehicle-id').val();
					const selectedServiceType = $('#selected-service-type').val();
					
					if (!selectedVehicleId || !selectedServiceType) {
						this.showError('Please select a vehicle');
						return false;
					}
					break;
				}

				case 3: {
					const email = $('input[name="customer_email"]').val();
					if (email && !this.isValidEmail(email)) {
						this.showError('Please enter a valid email address');
						return false;
					}
					break;
				}

				case 2: {
					const serviceType = $('#selected-service-type').val();
					if (serviceType === 'timed' || serviceType === 'priority') {
						const dateVal = $('#ocb_step2_date').val();
						const timeVal = $('#ocb_step2_time').val();
						if (!dateVal || !timeVal) {
							this.showError('Please select a delivery date and time');
							return false;
						}

						// MIN +3H VALIDATION
						const selectedDate = new Date(`${dateVal}T${timeVal}`);
						const serverNow = window.ocbData && window.ocbData.serverTime ? new Date(window.ocbData.serverTime) : new Date();
						const minTime = new Date(serverNow.getTime() + 3 * 3600000);

						if (selectedDate < minTime) {
							this.showError('Earliest delivery time is 3 hours from now (' + this.formatTime(minTime) + ')');
							return false;
						}
					}
					break;
				}
			}

			if (!isValid) {
				this.showError('Please fill in all required fields');
			}

			return isValid;
		}

		/**
		 * Collect Step Data
		 */
		collectStepData(step) {
			const $step = $(`.ocb-multi-step-content[data-step="${step}"]`);
			const fields = $step.find('input, select, textarea').serializeArray();

			fields.forEach(field => {
				this.formData[field.name] = field.value;
			});
		}

		/**
		 * Calculate Pricing
		 */
		async calculatePricing() {
			const collectionPostcode = this.formData.collection_postcode || '';
			const deliveryPostcode = this.formData.delivery_postcode || '';

			// Extract postcode area for pricing
			const collectionArea = collectionPostcode.match(/^[A-Z]{1,2}/)?.[0] || 'DEFAULT';
			const deliveryArea = deliveryPostcode.match(/^[A-Z]{1,2}/)?.[0] || 'DEFAULT';

			// Base distance charge
			const distanceCharge = 
				(OCB_CONFIG.POSTCODE_BASE_PRICES[collectionArea] || OCB_CONFIG.POSTCODE_BASE_PRICES.DEFAULT) +
				(OCB_CONFIG.POSTCODE_BASE_PRICES[deliveryArea] || OCB_CONFIG.POSTCODE_BASE_PRICES.DEFAULT);

			this.formData.distance_charge = distanceCharge;

			// Update summary display
			$('#summary-delivery-postcode').text(deliveryPostcode);
		}

		/**
		 * Load Vehicles - CitySprint Style with Real Distance-Based Pricing
		 * Loads vehicles for ALL service types at once
		 */
		loadVehiclesCitySprint() {
			const distanceMiles = this.formData.distance_miles || 10;

			

		// Check if grids exist
		const $grids = $('.ocb-vehicles-citysprint-grid');
		
		if ($grids.length === 0) {
			
			return;
		}

			// Vehicle data - IDs MUST match PHP (small_van, mwb, lwb)
			const vehicles = [
				{
					id: 'motorbike',
					name: 'Motorbike',
					icon: 'fa-solid fa-motorcycle',
					dimensions: '0.5 x 0.3 x 0.5m',
					capacity: '(LxWxH)\nMax 5kg',
					baseRate: 0.85,
					minPrice: 35.00
				},
				{
					id: 'small_van',
					name: 'Small Van',
					icon: 'fa-solid fa-truck-pickup',
					dimensions: '1 x 1.2 x 1m',
					capacity: '(LxWxH)\nMax 400kg',
					baseRate: 1.35,
					minPrice: 45.00
				},
				{
					id: 'mwb',
					name: 'Medium Van',
					icon: 'fa-solid fa-truck',
					dimensions: '2 x 1.2 x 1m',
					capacity: '(LxWxH)\nMax 800kg',
					baseRate: 1.55,
					minPrice: 55.00
				},
				{
					id: 'lwb',
					name: 'Large Van',
					icon: 'fa-solid fa-truck-moving',
					dimensions: '3 x 1.2 x 1.7m',
					capacity: '(LxWxH)\nMax 1100kg',
					baseRate: 1.75,
					minPrice: 65.00
				}
			];

			// Service type multipliers (CitySprint pricing)
			const serviceTypes = {
				dedicated: { multiplier: 2.00, name: 'Direct / Dedicated' },
				same_day: { multiplier: 1.00, name: 'Same Day' },
				timed: { multiplier: 1.50, name: 'Priority / Timed' }
			};

			// Load vehicles for each service type
			Object.keys(serviceTypes).forEach(serviceType => {
				const $grid = $(`.ocb-vehicles-citysprint-grid[data-service="${serviceType}"]`);
				$grid.empty();

				const multiplier = serviceTypes[serviceType].multiplier;

				vehicles.forEach(vehicle => {
					// Calculate price based on distance
					// Formula: (Distance * Rate) + Admin Fee
					// Night Rate: (Distance * Rate * 2) + Admin Fee

					let rate = vehicle.baseRate;
					let adminFee = 0;
					let minCharge = 0;

					// Override rates per client request - IDs match PHP
					if (vehicle.id === 'small_van') { rate = 1.35; adminFee = 15; minCharge = 45; }
					else if (vehicle.id === 'mwb') { rate = 1.55; adminFee = 20; minCharge = 55; }
					else if (vehicle.id === 'lwb') { rate = 1.75; adminFee = 25; minCharge = 65; }
					else {
						// Fallback or other vehicles (e.g. motorbike)
						// Use existing properties if available, or defaults
						adminFee = 0; 
						minCharge = vehicle.minPrice || 0;
					}

					// Check Night Rate (Client side check)
					// Try to get time from form data
					let isNight = false;
					// Helper to check time
					const checkTime = (timeStr) => {
						if (!timeStr) return false;
						const h = parseInt(timeStr.split(':')[0]);
						return (h >= 22 || h < 6);
					};
					
					if (this.formData && this.formData.collection_time) {
						isNight = checkTime(this.formData.collection_time);
					} else {
						// Try DOM if formData not updated
						const timeVal = $('input[name="collection_time"]').val();
						isNight = checkTime(timeVal);
					}

					// AUTHORITATIVE FORMULA (matches PHP):
					// 1. per_mile_rate = base_rate × (night ? 2 : 1)
					// 2. distance_cost = distance × per_mile_rate
					// 3. service_cost = distance_cost × service_multiplier
					// 4. chargeable_cost = max(service_cost, min_charge)
					// 5. final_price = chargeable_cost + admin_fee

					// Step 1: Apply night multiplier to per-mile rate
					let perMileRate = rate;
					if (isNight) {
						perMileRate = rate * 2;
					}

					// Step 2: Calculate distance cost
					let distanceCost = distanceMiles * perMileRate;

					// Step 3: Apply service multiplier
					let serviceCost = distanceCost;
					if (multiplier !== 1) {
						serviceCost = distanceCost * multiplier;
					}

					// Step 4: Apply minimum charge
					let chargeableCost = Math.max(serviceCost, minCharge);

					// Step 5: Add admin fee (NEVER multiplied)
					let priceExcl = chargeableCost + adminFee;

					priceExcl = Math.round(priceExcl * 100) / 100;
					const priceIncl = priceExcl; // VAT Removed

					

					const $card = $(`
						<div class="ocb-vehicle-citysprint-card" 
							 data-vehicle-id="${vehicle.id}" 
							 data-vehicle-name="${vehicle.name}"
							 data-service-type="${serviceType}"
							 data-price-excl="${priceExcl}"
							 data-price-incl="${priceIncl}">
							<div class="ocb-vehicle-icon-citysprint">
								<i class="${vehicle.icon}"></i>
							</div>
							<div class="ocb-vehicle-name-citysprint">${vehicle.name}</div>
							<div class="ocb-vehicle-dimensions">${vehicle.dimensions}</div>
							<div class="ocb-vehicle-capacity">${vehicle.capacity}</div>
							<div class="ocb-vehicle-price-citysprint">
								<span class="ocb-price-excl">£${priceExcl.toFixed(2)}</span>
								<!-- <span class="ocb-price-vat-label"></span> -->
							</div>
							<button type="button" class="ocb-vehicle-book-btn">Book</button>
						</div>
					`);

					$grid.append($card);
				});
			});

			
		}

		/**
		 * Load Vehicles
		 */
		loadVehicles() {
			const serviceType = $('input[name="service_type"]:checked').val() || 'same_day';
			const $vehiclesGrid = $('#ocb-vehicles-grid');

			$vehiclesGrid.empty();

			// Vehicle data
			const vehicles = [
				{
					id: 'motorbike',
					name: 'Motorbike',
					icon: '🏍️',
					description: 'Perfect for small documents and parcels up to 5kg',
					capacity: 'Up to 5kg'
				},
				{
					id: 'small_van',
					name: 'Small Van',
					icon: '🚐',
					description: 'Ideal for medium packages and multiple items',
					capacity: 'Up to 250kg'
				},
				{
					id: 'large_van',
					name: 'Large Van (SWB)',
					icon: '🚚',
					description: 'Great for larger items and furniture',
					capacity: 'Up to 500kg'
				},
				{
					id: 'xl_van',
					name: 'XL Van (3.2m)',
					icon: '🚛',
					description: 'Perfect for bulky items and house moves',
					capacity: 'Up to 800kg'
				}
			];

			vehicles.forEach(vehicle => {
				const basePrice = OCB_CONFIG.BASE_PRICING[vehicle.id][serviceType];
				const distanceCharge = this.formData.distance_charge || 0;
				const subtotal = basePrice + distanceCharge;
				const vat = subtotal * OCB_CONFIG.VAT_RATE;
				const total = subtotal + vat;

				const $card = $(`
					<div class="ocb-vehicle-card" data-vehicle="${vehicle.id}" data-price-base="${basePrice}" data-price-total="${total.toFixed(2)}">
						<div class="ocb-vehicle-icon">${vehicle.icon}</div>
						<div class="ocb-vehicle-name">${vehicle.name}</div>
						<div class="ocb-vehicle-desc">${vehicle.description}</div>
						<div class="ocb-vehicle-price">
							<span class="ocb-price-label">Total Price</span>
							<span class="ocb-price-amount">£${total.toFixed(2)}</span>
							<span class="ocb-price-vat">inc. VAT</span>
						</div>
					</div>
				`);

				$vehiclesGrid.append($card);
			});

			// Re-bind click event
			$('.ocb-vehicle-card').on('click', function() {
				$('.ocb-vehicle-card').removeClass('selected');
				$(this).addClass('selected');
			});
		}

		/**
		 * Save Selected Vehicle
		 */
		saveSelectedVehicle() {
			const $selected = $('.ocb-vehicle-card.selected');
			if ($selected.length) {
				this.formData.selected_vehicle = $selected.data('vehicle');
				this.formData.vehicle_name = $selected.find('.ocb-vehicle-name').text();
				this.formData.base_price = parseFloat($selected.data('price-base'));
				this.formData.total_price = parseFloat($selected.data('price-total'));
			}
		}

		/**
		 * Prepare Review Data
		 */
		prepareReview() {
			// Route details
			$('#review-collection-address').text(this.formData.collection_address || 'Address from postcode');
			$('#review-collection-postcode').text(this.formData.collection_postcode || '—');
			$('#review-delivery-address').text(this.formData.delivery_address || 'Address from postcode');
			$('#review-delivery-postcode').text(this.formData.delivery_postcode || '—');

			// Vehicle
			$('#review-vehicle-name').text(this.formData.vehicle_name || '—');
			$('#review-service-type').text(this.formData.service_type?.replace('_', ' ').toUpperCase() || '—');

			// Time
			const collectionDate = this.formData.collection_date || '—';
			const collectionTime = this.formData.collection_time || '—';
			const deliveryDate = this.formData.delivery_date || '—';
			const deliveryTime = this.formData.delivery_time || '—';
			$('#review-collection-datetime').text(`${collectionDate} at ${collectionTime}`);
			$('#review-delivery-datetime').text(`${deliveryDate} at ${deliveryTime}`);

			// Contact
			$('#review-customer-name').text(`${this.formData.first_name || ''} ${this.formData.last_name || ''}`);
			$('#review-customer-email').text(this.formData.email || '—');
			$('#review-customer-phone').text(this.formData.phone || '—');

			// Pricing
			const basePrice = this.formData.base_price || 0;
			const distancePrice = this.formData.distance_charge || 0;
			const serviceFee = 0;
			const subtotal = basePrice + distancePrice + serviceFee;
			const vat = subtotal * OCB_CONFIG.VAT_RATE;
			const total = subtotal + vat;

			$('#review-base-price').text(`£${basePrice.toFixed(2)}`);
			$('#review-distance-price').text(`£${distancePrice.toFixed(2)}`);
			$('#review-service-fee').text(`£${serviceFee.toFixed(2)}`);
			$('#review-subtotal').text(`£${subtotal.toFixed(2)}`);
			$('#review-vat').text(`£${vat.toFixed(2)}`);
			$('#review-total-price').text(`£${total.toFixed(2)}`);
		}

		/**
		 * Find Address via Google Maps API
		 */
		async findAddress(e) {
			const $btn = $(e.currentTarget);
			const location = $btn.data('location');
			const $input = $(`input[name="${location}_postcode"]`);
			const postcode = $input.val().trim().toUpperCase();

			if (!postcode) {
				this.showError('Please enter a postcode');
				return;
			}

			const $resultsDiv = $(`#ocb-${location}-addresses`);
			$resultsDiv.show().html(`
				<div class="ocb-address-loading">
					<div class="ocb-spinner"></div>
					<p>Searching for addresses...</p>
				</div>
			`);

			// If Google Maps API key is not set, show manual entry
			if (!OCB_CONFIG.GOOGLE_MAPS_API_KEY) {
				setTimeout(() => {
					$resultsDiv.html(`
						<div class="ocb-address-item">
							<p style="color: #718096; text-align: center; padding: 20px;">
								Google Maps API not configured. Please enter your address manually below.
							</p>
						</div>
					`);
				}, 1000);
				return;
			}

			// Call Google Maps Geocoding API
			try {
				const response = await fetch(
					`https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(postcode)}&region=uk&key=${OCB_CONFIG.GOOGLE_MAPS_API_KEY}`
				);
				const data = await response.json();

				if (data.status === 'OK' && data.results.length > 0) {
					const addresses = data.results.slice(0, 5);
					let html = '';

					addresses.forEach((result, index) => {
						// Ensure single line display
						const addressText = result.formatted_address || '';
						html += `
							<div class="ocb-address-item" data-address="${addressText}">
								<div class="ocb-address-inline">${addressText}</div>
							</div>
						`;
					});

					$resultsDiv.html(html);

					// Bind click event
					$resultsDiv.find('.ocb-address-item').on('click', function() {
						const address = $(this).data('address');
						$(`textarea[name="${location}_address"]`).val(address);
						$resultsDiv.hide();
						$(this).siblings().removeClass('selected');
						$(this).addClass('selected');
					});
				} else {
					$resultsDiv.html(`
						<div class="ocb-address-item">
							<p style="color: #e53e3e; text-align: center; padding: 20px;">
								No addresses found. Please enter manually below.
							</p>
						</div>
					`);
				}
			} catch (error) {
				
				$resultsDiv.html(`
					<div class="ocb-address-item">
						<p style="color: #e53e3e; text-align: center; padding: 20px;">
							Error finding addresses. Please enter manually below.
						</p>
					</div>
				`);
			}
		}

		/**
		 * Submit Form
		 */
		async submitForm(e) {
			e.preventDefault();

			if (!this.validateCurrentStep()) {
				return;
			}

			// Check terms agreement
			if (!$('#ocb-agree-terms').is(':checked')) {
				this.showError('Please agree to the Terms & Conditions');
				return;
			}

			// Collect final step data
			this.collectStepData(this.currentStep);

			// Disable submit button
			const $submitBtn = $('#ocb-btn-submit');
			$submitBtn.prop('disabled', true).html('<span class="ocb-spinner"></span> Processing...');

			// Prepare data for submission
			const bookingData = {
				action: 'ocb_multi_submit_step',
				ocb_nonce: $('input[name="ocb_nonce"]').val(),
				...this.formData
			};

			try {
				const response = await $.ajax({
					url: ajaxurl || '/wp-admin/admin-ajax.php',
					type: 'POST',
					data: bookingData
				});

				if (response.success) {
					this.showSuccess('Booking created successfully! Redirecting...');
					
					setTimeout(() => {
						if (response.data.redirect) {
							window.location.href = response.data.redirect;
						} else {
							location.reload();
						}
					}, 2000);
				} else {
					this.showError(response.data.message || 'Failed to create booking');
					$submitBtn.prop('disabled', false).html('✓ Complete Booking');
				}
			} catch (error) {
				
				this.showError('Connection error. Please try again.');
				$submitBtn.prop('disabled', false).html('✓ Complete Booking');
			}
		}

		/**
		 * Utility: Show Error Message
		 */
		showError(message) {
			const $error = $('#ocb-multi-error');
			$error.text(message).fadeIn();
			
			setTimeout(() => {
				$error.fadeOut();
			}, 5000);

			$('html, body').animate({
				scrollTop: $error.offset().top - 100
			}, 500);
		}

		/**
		 * Utility: Show Success Message
		 */
		showSuccess(message) {
			const $success = $('#ocb-multi-success');
			$success.text(message).fadeIn();
		}

		/**
		 * Utility: Email Validation
		 */
		isValidEmail(email) {
			const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return re.test(email);
		}
	}

	// Initialize when document is ready
	$(document).ready(function() {
		if ($('#ocb-multi-form').length) {
			new OnRouteBookingForm();
		}
	});

})(jQuery);
