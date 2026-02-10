/**
 * OnRoute Booking - CitySprint Style
 * Production Ready JavaScript
 */
(function() {
    'use strict';

    // Professional SVG Icons for vehicles
    const VEHICLE_ICONS = {
        motorbike: `<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="14" cy="46" r="10" stroke="currentColor" stroke-width="3" fill="none"/>
            <circle cx="50" cy="46" r="10" stroke="currentColor" stroke-width="3" fill="none"/>
            <path d="M24 46H40" stroke="currentColor" stroke-width="3"/>
            <path d="M14 36L24 24H36L44 16" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            <path d="M36 24L40 46" stroke="currentColor" stroke-width="3"/>
            <circle cx="32" cy="20" r="6" fill="currentColor"/>
        </svg>`,
        small_van: `<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="4" y="20" width="40" height="26" rx="3" stroke="currentColor" stroke-width="3" fill="none"/>
            <path d="M44 28H54C56 28 58 30 58 32V46H44" stroke="currentColor" stroke-width="3" fill="none"/>
            <circle cx="16" cy="50" r="6" stroke="currentColor" stroke-width="3" fill="none"/>
            <circle cx="48" cy="50" r="6" stroke="currentColor" stroke-width="3" fill="none"/>
            <rect x="46" y="32" width="8" height="6" rx="1" fill="currentColor" opacity="0.5"/>
        </svg>`,
        mwb: `<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="18" width="44" height="28" rx="3" stroke="currentColor" stroke-width="3" fill="none"/>
            <path d="M46 24H56C58 24 60 26 60 28V46H46" stroke="currentColor" stroke-width="3" fill="none"/>
            <circle cx="14" cy="50" r="6" stroke="currentColor" stroke-width="3" fill="none"/>
            <circle cx="52" cy="50" r="6" stroke="currentColor" stroke-width="3" fill="none"/>
            <rect x="48" y="28" width="8" height="8" rx="1" fill="currentColor" opacity="0.5"/>
            <line x1="24" y1="18" x2="24" y2="46" stroke="currentColor" stroke-width="2" opacity="0.3"/>
        </svg>`,
        lwb: `<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="2" y="16" width="46" height="30" rx="3" stroke="currentColor" stroke-width="3" fill="none"/>
            <path d="M48 22H58C60 22 62 24 62 26V46H48" stroke="currentColor" stroke-width="3" fill="none"/>
            <circle cx="14" cy="50" r="6" stroke="currentColor" stroke-width="3" fill="none"/>
            <circle cx="36" cy="50" r="6" stroke="currentColor" stroke-width="3" fill="none"/>
            <circle cx="54" cy="50" r="6" stroke="currentColor" stroke-width="3" fill="none"/>
            <rect x="50" y="26" width="8" height="8" rx="1" fill="currentColor" opacity="0.5"/>
            <line x1="18" y1="16" x2="18" y2="46" stroke="currentColor" stroke-width="2" opacity="0.3"/>
            <line x1="34" y1="16" x2="34" y2="46" stroke="currentColor" stroke-width="2" opacity="0.3"/>
        </svg>`
    };

    // Aliases for icons if IDs differ
    VEHICLE_ICONS.large_van = VEHICLE_ICONS.mwb;
    VEHICLE_ICONS.xl_van = VEHICLE_ICONS.lwb;

    // State
    let currentStep = 1;
    let selectedVehicle = null;
    let selectedVehicleName = ''; // NEW: Store display name
    let selectedVehicleData = {}; // Store extra data
    let selectedService = null;
    let selectedPrice = 0;
    let selectedNightPrice = 0;
    let currentDistance = 0; // Store distance for submission
    
    // Quotes Cache
    let cachedQuotes = null;

    // Service ID Mapping (Backend -> UI)
    const SERVICE_MAP = {
        'direct': 'dedicated',
        'priority': 'timed',
        'same_day': 'same_day'
    };

    // DOM Ready
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const wrapper = document.querySelector('.ocb-booking-wrapper');
        if (!wrapper) {
            return;
        }

        // NEW: Refresh session data to handle aggressive caching
        refreshSessionData().then(() => {
            // Update labels based on time
            calculateDeliveryTimes();
            
            // Fetch quotes immediately using fresh data
            fetchQuotes();
        });

        // Bind events
        bindEvents();
    }

    function refreshSessionData() {
        const formData = new FormData();
        formData.append('action', 'ocb_get_session_data');
        
        return fetch(ocbData.ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(response => {
            if (response.success && response.data) {
                
                // Update Hidden Inputs
                const collInput = document.querySelector('input[name="collection_postcode"]');
                const delInput = document.querySelector('input[name="delivery_postcode"]');
                const dateInput = document.querySelector('input[name="collection_date"]');
                const timeInput = document.querySelector('input[name="collection_time"]');

                if (collInput && response.data.collection_postcode) {
                    collInput.value = response.data.collection_postcode;
                }
                if (delInput && response.data.delivery_postcode) {
                    delInput.value = response.data.delivery_postcode;
                }
                if (dateInput && response.data.collection_date) {
                    dateInput.value = response.data.collection_date;
                }
                if (timeInput && response.data.collection_time) {
                    timeInput.value = response.data.collection_time;
                }
                
                // Sync internal state from session
                const serviceInput = document.getElementById('selected-service-type');
                const sId = response.data.selected_service || response.data.service_id || response.data.service_type;
                if (serviceInput && sId) {
                    serviceInput.value = sId;
                    selectedService = sId;
                }
                
                const vehicleInput = document.getElementById('selected-vehicle-id');
                const vId = response.data.selected_vehicle || response.data.vehicle_id;
                if (vehicleInput && vId) {
                    vehicleInput.value = vId;
                    selectedVehicle = vId;
                }

                // Update Display Text (Span elements)
                const dispColl = document.getElementById('display-collection');
                const dispDel = document.getElementById('display-delivery');
                
                if (dispColl && response.data.collection_postcode) {
                    dispColl.textContent = response.data.collection_postcode;
                }
                if (dispDel && response.data.delivery_postcode) {
                    dispDel.textContent = response.data.delivery_postcode;
                }

                // Update Distance if available
                const clientDistance = document.getElementById('client-distance');
                if(clientDistance && response.data.distance_text) {
                     // Optionally update distance display if you have a spot for it
                }
            }
        })
        .catch(err => {
        });
    }

    function fetchQuotes() {
        const collPC = document.querySelector('input[name="collection_postcode"]')?.value;
        const delPC = document.querySelector('input[name="delivery_postcode"]')?.value;

        if (!collPC || !delPC) {
            console.warn('❌ Missing postcodes for quote calculation');
            console.warn('Collection:', collPC, 'Delivery:', delPC);
            showErrorMessage('Please enter both collection and delivery postcodes.');
            return;
        }

        // Show loading state in containers
        ['dedicated', 'timed', 'same_day'].forEach(type => {
            const container = document.querySelector(`.ocb-vehicles-row[data-service="${type}"]`);
            if (container) container.innerHTML = '<div class="ocb-loading-spinner">🔄 Loading vehicles...</div>';
        });

        // Define logic to perform server search
        const performDataFetch = (clientDistance = null) => {
            const formData = new FormData();
            formData.append('action', 'ocb_quote_search');
            formData.append('nonce', ocbData.nonces?.api || ocbData.nonce); // Handle structure variance
            formData.append('pickup_code', collPC);
            formData.append('delivery_code', delPC);

            if (clientDistance) {
                 formData.append('client_distance', clientDistance);
            }

            // Logs removed for production

            fetch(ocbData.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => {
                return res.json();
            })
            .then(response => {
                
                if (response.success) {
                    
                    // Check if debugging info is present and if fallback was used
                    if (response.data.debug && response.data.debug.fallback_used) {
                        console.warn('⚠️ FALLBACK USED: The system used a default distance of ' + response.data.distance + ' miles because the API call failed.');
                        console.warn('⚠️ Reason:', response.data.debug.error);
                        
                        if (!response.data.debug.api_key_configured) {
                            showErrorMessage('⚠️ Warning: OpenRoute API Key is missing! Dynamic pricing is disabled. Showing strict fallback prices.');
                        } else {
                            showErrorMessage('⚠️ Warning: API Connection Error: ' + response.data.debug.error + '. Showing fallback prices.');
                        }
                    }
                    
                    cachedQuotes = response.data.quotes;
                    currentDistance = response.data.distance; // Store distance
                    renderAllQuotes(response.data.quotes, response.data.distance);
                } else {
                    // Check if it's an API key issue
                    if (response.data?.message && response.data.message.includes('API')) {
                        showErrorMessage('API Configuration Error: ' + response.data.message + 
                            '\\n\\nPlease contact the administrator to configure the OpenRoute API key in Settings > API Settings.' +
                            '\\nGet a free API key from: https://openrouteservice.org/dev/#/signup');
                    } else {
                        showErrorMessage('Error fetching quotes: ' + (response.data?.message || 'Unknown error'));
                    }
                    
                    // Show fallback message in containers - still allow selection
                    ['dedicated', 'timed', 'same_day'].forEach(type => {
                        const container = document.querySelector(`.ocb-vehicles-row[data-service="${type}"]`);
                        if (container) {
                            container.innerHTML = `<div class="ocb-error-message" style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">
                                ⚠️ Unable to load dynamic pricing. Using fallback prices. <br>
                                <small>Error: ${response.data?.message || 'Unknown'}. Please contact support.</small>
                            </div>`;
                        }
                    });
                }
            })
            .catch(err => {
                // Show error in containers
                ['dedicated', 'timed', 'same_day'].forEach(type => {
                    const container = document.querySelector(`.ocb-vehicles-row[data-service="${type}"]`);
                    if (container) {
                        container.innerHTML = `<div class="ocb-error-message">
                            ❌ Network error. Please try again. <br>Error: ${err.message}
                        </div>`;
                    }
                });
            });
        };

        // Try Google Maps Client Side Distance Matrix first (works with Domain Restricted Keys)
        if (typeof google !== 'undefined' && google.maps && google.maps.DistanceMatrixService) {
             const service = new google.maps.DistanceMatrixService();
             service.getDistanceMatrix({
                    origins: [collPC],
                    destinations: [delPC],
                    travelMode: 'DRIVING',
                    unitSystem: google.maps.UnitSystem.IMPERIAL
                }, 
                (response, status) => {
                    if (status === 'OK' && response.rows[0].elements[0].status === 'OK') {
                        const distVal = response.rows[0].elements[0].distance.value; // meters
                        const distText = response.rows[0].elements[0].distance.text;
                        // Convert meters to miles
                        const miles = (distVal * 0.000621371).toFixed(2);
                        performDataFetch(miles); // Use client-side distance
                    } else {
                        console.warn('⚠️ Client-Side Distance Matrix Failed or Zero Results:', status);
                        performDataFetch(null); // Fallback to server side
                    }
                }
            );
        } else {
            // Google Maps JS not loaded or unavailable
            performDataFetch(null);
        }
    }
    
    function showErrorMessage(message) {
        // Create error notification
        const errorDiv = document.createElement('div');
        errorDiv.className = 'ocb-error-notification';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f44336;
            color: white;
            padding: 15px 20px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 10000;
            max-width: 400px;
            animation: slideIn 0.3s ease-out;
        `;
        errorDiv.textContent = message;
        
        document.body.appendChild(errorDiv);
        
        // Auto-remove after 8 seconds
        setTimeout(() => {
            errorDiv.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => errorDiv.remove(), 300);
        }, 8000);
    }

    function renderAllQuotes(quotes, distance) {
        // Clear containers
        document.querySelectorAll('.ocb-vehicles-row').forEach(el => el.innerHTML = '');

        // Iterate over backend services
        Object.keys(quotes).forEach(serviceId => {
            const uiServiceId = SERVICE_MAP[serviceId] || serviceId;
            const container = document.querySelector(`.ocb-vehicles-row[data-service="${uiServiceId}"]`);
            
            if (container) {
                renderVehiclesForService(container, quotes[serviceId], uiServiceId, distance);
            }
        });
        
        // Handle Empty States
        document.querySelectorAll('.ocb-vehicles-row').forEach(el => {
            if (el.innerHTML === '') {
                el.innerHTML = '<div class="ocb-no-vehicles">No vehicles available for this service.</div>';
            }
        });
    }

    function renderVehiclesForService(container, vehicles, serviceType, distance) {
        let html = '';

        vehicles.forEach(v => {
            // Determine price based on current time (Day vs Night) logic could happen here
            // But for now we use the 'price' field returned (Day Rate)
            // If we want dynamic time based updates, we need to check the time input?
            // But this is Step 1. Time selection is usually Step 2?
            // Wait, UI Step 1 shows collection/delivery times hardcoded in HTML?
            // "We'll collect by 11:41..."
            
            const price = parseFloat(v.price);
            const nightPrice = parseFloat(v.night_price); // For reference
            
            // Icon
            let icon = VEHICLE_ICONS[v.vehicle_id] || VEHICLE_ICONS['small_van'];

            // Prices
            const priceExcl = price;
            const priceIncl = priceExcl * (1 + (v.vat_rate / 100));

            html += `
                <div class="ocb-v-card" 
                     data-vehicle="${v.vehicle_id}" 
                     data-service="${serviceType}" 
                     data-price="${priceExcl.toFixed(2)}"
                     data-night-price="${nightPrice.toFixed(2)}"
                     data-description="${v.description || ''}"
                     data-dimensions="${v.dimensions || ''}"
                     data-weight="${v.max_weight || ''}">
                    <div class="ocb-v-icon">${icon}</div>
                    <div class="ocb-v-name">${v.vehicle_name}</div>
                    ${v.description ? `<div class="ocb-v-desc">${v.description}</div>` : ''}
                    <div class="ocb-v-specs">${v.dimensions || ''}</div>
                    <div class="ocb-v-weight">${v.max_weight || ''}</div>
                    <div class="ocb-v-price">£${priceExcl.toFixed(2)}</div>
                    <div class="ocb-v-price-label"></div>
                    <!-- <div class="ocb-v-price-vat">£${priceIncl.toFixed(2)} incl. VAT</div> -->
                    <button type="button" class="ocb-v-btn">BOOK</button>
                </div>
            `;
        });

        container.innerHTML = html;

        // Bind click events
        container.querySelectorAll('.ocb-v-card').forEach(card => {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                selectVehicle(this);
            });
        });
    }

    function selectVehicle(card) {
        // Remove previous selection
        document.querySelectorAll('.ocb-v-card').forEach(c => c.classList.remove('selected'));

        // Select this one
        card.classList.add('selected');

        // Store selection
        selectedVehicle = card.dataset.vehicle;
        selectedService = card.dataset.service; // This is the UI service name
        selectedPrice = parseFloat(card.dataset.price);
        
        // Capture proper vehicle name from card (ignore internal ID)
        const nameElem = card.querySelector('.ocb-v-name');
        selectedVehicleName = nameElem ? nameElem.textContent : (selectedVehicle.replace('_', ' '));

        // Store extra vehicle data
        selectedVehicleData = {
            description: card.dataset.description || '',
            dimensions: card.dataset.dimensions || '',
            weight: card.dataset.weight || ''
        };

        // Update hidden fields
        const vInput = document.getElementById('selected-vehicle-id');
        if(vInput) vInput.value = selectedVehicle;
        
        const sInput = document.getElementById('selected-service-type');
        if(sInput) sInput.value = selectedService;
        
        const pInput = document.getElementById('selected-price');
        if(pInput) pInput.value = selectedPrice;

        // Go to step 2 after short delay
        setTimeout(() => goToStep(2), 400);
    }


    function bindEvents() {
        // Tab clicks
        document.querySelectorAll('.ocb-nav-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const step = parseInt(this.dataset.step);
                if (step < currentStep || (step === 2 && selectedVehicle)) {
                    goToStep(step);
                }
            });
        });

        // Back buttons
        document.querySelectorAll('[data-action="back"]').forEach(btn => {
            btn.addEventListener('click', () => goToStep(currentStep - 1));
        });

        // Next buttons
        document.querySelectorAll('[data-action="next"]').forEach(btn => {
            btn.addEventListener('click', function() {
                if (validateStep(currentStep)) {
                    goToStep(currentStep + 1);
                }
            });
        });

        // Form submit
        const form = document.getElementById('ocb-booking-form');
        if (form) {
            form.addEventListener('submit', handleSubmit);
        }

        // Date/Time Logic for "Same Day"
        bindDateTimeEvents();
    }

    function bindDateTimeEvents() {
        // Native Date & Time Logic for Step 2
        const dateInput = document.getElementById('ocb_step2_date');
        const timeInput = document.getElementById('ocb_step2_time');
        const hiddenInput = document.getElementById('ocb_step2_datetime');

        if (dateInput && timeInput && hiddenInput) {
            
            // Set min date to collection date from Step 1 (or today if no pre-booking)
            let minDate = new Date().toISOString().split('T')[0];
            
            // Check if there's a pre-booked collection date stored
            const collectionDateElem = document.querySelector('input[name="collection_date"]');
            if (collectionDateElem && collectionDateElem.value) {
                minDate = collectionDateElem.value;
            }
            
            dateInput.min = minDate;
            
            // Fix: Default time to 09:00 to prevent accidental night rate (11:15 PM) triggers during testing
            if (!timeInput.value) {
                timeInput.value = "09:00";
            }
            
            function updateHidden() {
                if (dateInput.value && timeInput.value) {
                    hiddenInput.value = dateInput.value + ' ' + timeInput.value;
                } else if (dateInput.value) {
                    // For services where time is not selected, still need a valid datetime
                    hiddenInput.value = dateInput.value + ' 00:00:00';
                } else {
                    hiddenInput.value = '';
                }
                updateDeliveryTimeUI(); 
                updateReview();
            }

            dateInput.addEventListener('change', updateHidden);
            timeInput.addEventListener('change', updateHidden);
        }

        // Initialize for Booking Details form - Legacy or other forms
        if (typeof flatpickr !== 'undefined') {
            const dateInputs = document.querySelectorAll('[data-flatpickr-date]');
            dateInputs.forEach(input => {
                flatpickr(input, {
                    enableTime: false,
                    dateFormat: "Y-m-d",
                    minDate: "today",
                    disableMobile: false,
                    closeOnSelect: true
                });
            });

            const timeInputs = document.querySelectorAll('[data-flatpickr-time]');
            timeInputs.forEach(input => {
                flatpickr(input, {
                    enableTime: true,
                    noCalendar: true,
                    dateFormat: "H:i",
                    time_24hr: true,
                    disableMobile: false,
                    closeOnSelect: true
                });
            });
        }
    }


    function goToStep(step) {
        if (step < 1 || step > 3) return;

        // Hide all panels
        document.querySelectorAll('.ocb-step-panel').forEach(p => p.classList.remove('active'));

        // Show target panel
        const panel = document.querySelector(`.ocb-step-panel[data-step="${step}"]`);
        if (panel) panel.classList.add('active');

        // Update tabs
        document.querySelectorAll('.ocb-nav-tab').forEach(tab => {
            const tabStep = parseInt(tab.dataset.step);
            tab.classList.remove('active', 'completed');
            if (tabStep < step) {
                tab.classList.add('completed');
            } else if (tabStep === step) {
                tab.classList.add('active');
            }
        });

        currentStep = step;

        // Update date constraints if entering Step 2
        if (step === 2) {
            const dateInput = document.getElementById('ocb_step2_date');
            
            // Check if there's a pre-booked collection date stored for Step 1
            // This element ID/Name might vary depending on your "Quick Quote" form implementation
            // Assuming there is a hidden or visible input named 'collection_date'
            const collectionDateElem = document.querySelector('input[name="collection_date"]');
            
            if (dateInput) {
                let minDate = new Date().toISOString().split('T')[0];
                
                if (collectionDateElem && collectionDateElem.value) {
                    minDate = collectionDateElem.value;
                }
                
                dateInput.min = minDate;
                
                // If current value is before min, reset it
                if (dateInput.value && dateInput.value < minDate) {
                    dateInput.value = minDate;
                }
            }
            
            // Trigger Delivery Window Update
            updateDeliveryTimeUI();
        }

        // Update review if step 3
        if (step === 3) {
            updateReviewWithPricingRecalculation();
        }

        // Scroll to top
        document.querySelector('.ocb-booking-wrapper')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function validateStep(step) {
        if (step === 1) {
            if (!selectedVehicle) {
                alert('Please select a vehicle to continue.');
                return false;
            }
        }

        if (step === 2) {
            const firstName = document.querySelector('input[name="first_name"]')?.value;
            const lastName = document.querySelector('input[name="last_name"]')?.value;
            const email = document.querySelector('input[name="customer_email"]')?.value;
            const phone = document.querySelector('input[name="customer_phone"]')?.value;

            if (!firstName || !lastName || !email || !phone) {
                alert('Please fill in all required fields: First name, Surname, Email, and Mobile number.');
                return false;
            }

            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }

            // Lead time validation for Priority / Timed service
            const serviceType = document.getElementById('selected-service-type')?.value;
            if (serviceType === 'timed' || serviceType === 'priority') {
                const dateVal = document.getElementById('ocb_step2_date')?.value;
                const timeVal = document.getElementById('ocb_step2_time')?.value;
                if (!dateVal || !timeVal) {
                    alert('Please select a delivery date and time');
                    return false;
                }

                const selectedDate = new Date(`${dateVal}T${timeVal}`);
                const serverNow = window.ocbData && window.ocbData.serverTime ? new Date(window.ocbData.serverTime) : new Date();
                const minTime = new Date(serverNow.getTime() + 3 * 3600000);

                if (selectedDate < minTime) {
                    alert('Earliest delivery time is 3 hours from now (' + formatTime(minTime) + ')');
                    return false;
                }
            }
        }

        return true;
    }

    // Store pricing breakdown for Review & Book display
    let pricingBreakdown = null;

    /**
     * Recalculate pricing with delivery time for Step 3 (Review & Book)
     * Ensures night rate is applied correctly based on actual delivery time
     */
    function updateReviewWithPricingRecalculation() {
        // Fallback: If internal state is lost (e.g. page refresh), try to recover from hidden fields
        if (!selectedVehicle) {
            selectedVehicle = document.getElementById('selected-vehicle-id')?.value;
        }
        if (!selectedService) {
            selectedService = document.getElementById('selected-service-type')?.value;
        }

        // Get delivery time from the form
        const deliveryDatetimeInput = document.getElementById('ocb_step2_datetime');
        let deliveryTime = '';
        if (deliveryDatetimeInput && deliveryDatetimeInput.value) {
            const parts = deliveryDatetimeInput.value.split(' ');
            if (parts.length >= 2) {
                deliveryTime = parts[1]; // Extract time portion
            }
        }

        // Get collection time from hidden input
        const collectionTimeInput = document.querySelector('input[name="collection_time"]');
        const collectionTime = collectionTimeInput ? collectionTimeInput.value : '';

        // Get postcodes from inputs
        const collPC = document.querySelector('input[name="collection_postcode"]')?.value;
        const delPC = document.querySelector('input[name="delivery_postcode"]')?.value;

        // If we have all required info, fetch pricing breakdown from server
        if (selectedVehicle && collPC && delPC && currentDistance > 0) {
            // Build AJAX request for pricing breakdown
            const formData = new FormData();
            formData.append('action', 'ocb_calculate_price');
            formData.append('nonce', ocbData.nonce); // Use generic nonce
            formData.append('vehicle_id', selectedVehicle);
            formData.append('service_id', selectedService);
            formData.append('pickup_code', collPC);
            formData.append('delivery_code', delPC);
            formData.append('collection_time', collectionTime);
            formData.append('client_distance', currentDistance); // Send distance to ensure consistency
            if (deliveryTime) {
                formData.append('delivery_time', deliveryTime);
            }

            fetch(ocbData.ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(response => {
                if (response.success && response.data) {
                    // Store breakdown for reference
                    selectedPrice = response.data.total_price || selectedPrice;
                    pricingBreakdown = response.data; // Store all pricing data
                    
                    // Update the hidden price field
                    const pInput = document.getElementById('selected-price');
                    if (pInput) pInput.value = selectedPrice;

                    // Update review with new price
                    updateReview(selectedPrice, collectionTime, deliveryTime);
                } else {
                    // Fallback to regular review update
                    updateReview(selectedPrice, collectionTime, deliveryTime);
                }
            })
            .catch(err => {
             updateReview(selectedPrice, collectionTime, deliveryTime);
            });
        } else {
            // Missing data, just update review with current info
            const collectionTimeInput = document.querySelector('input[name="collection_time"]');
            const collectionTime = collectionTimeInput ? collectionTimeInput.value : '';
            const deliveryDatetimeInput = document.getElementById('ocb_step2_datetime');
            let deliveryTime = '';
            if (deliveryDatetimeInput && deliveryDatetimeInput.value) {
                const parts = deliveryDatetimeInput.value.split(' ');
                if (parts.length >= 2) {
                    deliveryTime = parts[1];
                }
            }
            updateReview(selectedPrice, collectionTime, deliveryTime);
        }
    }

    function updateReview(totalPrice = null, collectionTime = '', deliveryTime = '') {
        // Use passed price or global selectedPrice
        if (totalPrice === null) totalPrice = selectedPrice;

        // Collection and delivery times from inputs if not provided
        // Service & Vehicle
        const serviceNames = { dedicated: 'Dedicated', timed: 'Priority', same_day: 'Same Day' };
        document.getElementById('r-service').textContent = serviceNames[selectedService] || selectedService;
        document.getElementById('r-vehicle').textContent = selectedVehicleName || selectedVehicle?.replace('_', ' ') || '—';

        // Update Vehicle Specs
        if (selectedVehicleData) {
            const rDesc = document.getElementById('r-vehicle-desc');
            const rDims = document.getElementById('r-vehicle-dim');
            const rWeight = document.getElementById('r-vehicle-weight');
            const rSpecsRow = document.getElementById('r-specs-row');

            if (rDesc) rDesc.textContent = selectedVehicleData.description;
            if (rDims) rDims.textContent = selectedVehicleData.dimensions ? `Dims: ${selectedVehicleData.dimensions}` : '';
            if (rWeight) rWeight.textContent = selectedVehicleData.weight ? `Max Load: ${selectedVehicleData.weight}` : '';
            
            if (rSpecsRow) {
                if (selectedVehicleData.dimensions || selectedVehicleData.weight) {
                    rSpecsRow.style.display = 'flex';
                } else {
                    rSpecsRow.style.display = 'none';
                }
            }
        }

        // Addresses & Dates
        const collectionAddress = document.querySelector('textarea[name="collection_address"]')?.value || '';
        const collectionAddress2 = document.querySelector('input[name="collection_address_line2"]')?.value || '';
        const collectionCity = document.querySelector('input[name="collection_city"]')?.value || '';
        
        const deliveryAddress = document.querySelector('textarea[name="delivery_address"]')?.value || '';
        const deliveryAddress2 = document.querySelector('input[name="delivery_address_line2"]')?.value || '';
        const deliveryCity = document.querySelector('input[name="delivery_city"]')?.value || '';

        const dateTimeStr = document.getElementById('ocb_step2_datetime')?.value || '';
        
        let deliveryDate = '';
        if (dateTimeStr) {
            // Split YYYY-MM-DD HH:MM (Flatpickr format)
            const parts = dateTimeStr.split(' ');
            if (parts.length >= 2) {
                deliveryDate = parts[0];
                deliveryTime = parts[1];
            }
        }

        const rCollAddr = document.getElementById('r-collection-address');
        if (rCollAddr) {
            let fullColl = collectionAddress;
            if (collectionAddress2) fullColl += ', ' + collectionAddress2;
            if (collectionCity) fullColl += ', ' + collectionCity;
            rCollAddr.textContent = fullColl;
        }

        const rDelAddr = document.getElementById('r-delivery-address');
        if (rDelAddr) {
            let fullDel = deliveryAddress;
            if (deliveryAddress2) fullDel += ', ' + deliveryAddress2;
            if (deliveryCity) fullDel += ', ' + deliveryCity;
            rDelAddr.textContent = fullDel;
        }

        // Collection & Delivery Contact Details
        const collContact = document.querySelector('input[name="collection_contact"]')?.value || '—';
        const collPhone = document.querySelector('input[name="collection_phone"]')?.value || '—';
        const delContact = document.querySelector('input[name="delivery_contact"]')?.value || '—';
        const delPhone = document.querySelector('input[name="delivery_phone"]')?.value || '—';

        if(document.getElementById('r-collection-contact')) document.getElementById('r-collection-contact').textContent = collContact;
        if(document.getElementById('r-collection-phone')) document.getElementById('r-collection-phone').textContent = collPhone;
        if(document.getElementById('r-delivery-contact')) document.getElementById('r-delivery-contact').textContent = delContact;
        if(document.getElementById('r-delivery-phone')) document.getElementById('r-delivery-phone').textContent = delPhone;
        
        // Show Delivery Date/Time if set
        const rDelDate = document.getElementById('r-delivery-date');
        if(rDelDate && deliveryDate) {
             rDelDate.textContent = `By ${deliveryTime}, ${deliveryDate}`;
        }

        // Show Collection Date/Time
        const rCollDate = document.getElementById('r-collection-date');
        const collDateInput = document.querySelector('input[name="collection_date"]');
        const collTimeInput = document.querySelector('input[name="collection_time"]');
        
        if (rCollDate) {
            const cDate = collDateInput ? collDateInput.value : '';
            const cTime = collTimeInput ? collTimeInput.value : '';
            
            if (cDate) {
                let display = `By ${cTime || 'ASAP'}, ${cDate}`;
                rCollDate.textContent = display;
            } else {
                rCollDate.textContent = '';
            }
        }

        // Contact
        const firstName = document.querySelector('input[name="first_name"]')?.value || '';
        const lastName = document.querySelector('input[name="last_name"]')?.value || '';
        const email = document.querySelector('input[name="customer_email"]')?.value || '';
        const phone = document.querySelector('input[name="customer_phone"]')?.value || '';

        document.getElementById('r-name').textContent = firstName + ' ' + lastName;
        document.getElementById('r-email').textContent = email;
        document.getElementById('r-phone').textContent = phone;

        // ===================================================================
        // PRICING DISPLAY - Non-editable, locked at Review & Book
        // ===================================================================
        // Get correct values from server breakdown
        const hasBreakdown = pricingBreakdown?.breakdown;
        let basePrice = hasBreakdown ? pricingBreakdown.breakdown.base_price : totalPrice;
        const finalPrice = hasBreakdown ? pricingBreakdown.breakdown.final_price : totalPrice;
        
        // Detect Night Mode to adjust Display Logic
        // If night rate is applied, we want to show DAY RATE components in the breakdown
        // so that they match the "Base Delivery Price" header.
        const isNightApplied = hasBreakdown && pricingBreakdown.breakdown.night_applied;

        // Debug logging
        let adminCharge = 0;
        let visibleDistanceCost = 0;
        
        if (pricingBreakdown?.breakdown) {
            // Use actual data from server
            adminCharge = pricingBreakdown.breakdown.admin_fee || 0;
            
            if (isNightApplied && pricingBreakdown.breakdown.distance_cost_day) {
                // If night is applied, use the DAY distance cost for the breakdown list
                visibleDistanceCost = pricingBreakdown.breakdown.distance_cost_day;
            } else {
                visibleDistanceCost = pricingBreakdown.breakdown.distance_cost || 0;
            }
        } else {
            // Fallback: estimate from basePrice (before night multiplier)
            adminCharge = 15; // Default fallback
            visibleDistanceCost = basePrice - adminCharge;
            if (visibleDistanceCost < 0) visibleDistanceCost = basePrice;
        }

        // Update visible elements safely
        const safeUpdate = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        const safeDisplay = (id, display) => {
            const el = document.getElementById(id);
            if (el) el.style.display = display;
        };

        safeUpdate('r-base-price', '£' + basePrice.toFixed(2));
        safeUpdate('r-distance-cost', '£' + visibleDistanceCost.toFixed(2));
        safeUpdate('r-admin-charge', '£' + adminCharge.toFixed(2));

        // Service Multiplier / Surcharge Display
        const serviceRow = document.getElementById('r-service-mult-row');
        if (serviceRow) {
            let showSurcharge = false;
            let surchargeAmount = 0;
            let multVal = 1.0;

            if (hasBreakdown && pricingBreakdown.breakdown.service_multiplier > 1.0) {
                multVal = pricingBreakdown.breakdown.service_multiplier;
                
                // Calculate surcharge
                if (isNightApplied && pricingBreakdown.breakdown.chargeable_cost_day) {
                    const distCost = pricingBreakdown.breakdown.distance_cost_day || 0;
                    const finalChargeable = pricingBreakdown.breakdown.chargeable_cost_day || 0;
                    surchargeAmount = finalChargeable - distCost;
                } else {
                    const distCost = pricingBreakdown.breakdown.distance_cost || 0;
                    const finalChargeable = pricingBreakdown.breakdown.chargeable_cost || 0;
                    surchargeAmount = finalChargeable - distCost;
                }
                
                if (surchargeAmount > 0) showSurcharge = true;
            }

            if (showSurcharge) {
                serviceRow.style.display = 'flex';
                const serviceLabel = serviceNames[selectedService] || 'Service';
                const labelSpan = serviceRow.querySelector('span:first-child');
                if (labelSpan) labelSpan.innerHTML = `&nbsp;&nbsp;• ${serviceLabel} Surcharge (x${multVal})`;
                
                const valSpan = document.getElementById('r-service-mult');
                if (valSpan) valSpan.textContent = '£' + surchargeAmount.toFixed(2);
            } else {
                serviceRow.style.display = 'none';
            }
        }
        
        // Check if night rate applies
        let isNight = false;
        let nightMultiplier = 2;
        let nightSurcharge = 0;
        
        let timeToCheck = deliveryTime || collectionTime;
        if (timeToCheck) {
            const hourPart = parseInt(timeToCheck.split(':')[0]);
            isNight = (hourPart >= 22 || hourPart < 6);
        }
        
        const nightRateRow = document.getElementById('r-night-rate-row');
        const nightInfoRow = document.getElementById('r-night-info-row');
        
        if (nightRateRow && nightInfoRow) {
            const serverSaysNight = pricingBreakdown?.breakdown?.night_applied;
            const useServerNight = (pricingBreakdown?.breakdown !== undefined);
            const showNight = useServerNight ? serverSaysNight : isNight;
            
            if (showNight) {
                nightRateRow.style.display = 'flex';
                nightInfoRow.style.display = 'flex';
                
                const nsElem = document.getElementById('r-night-surcharge');
                if (nsElem) {
                    let surchargeText = '';
                    if (useServerNight && pricingBreakdown?.breakdown?.night_surcharge_formatted) {
                         surchargeText = pricingBreakdown.breakdown.night_surcharge_formatted;
                         nightSurcharge = pricingBreakdown.breakdown.night_surcharge || 0;
                    } else {
                        nightSurcharge = (visibleDistanceCost * (nightMultiplier - 1));
                        surchargeText = '£' + nightSurcharge.toFixed(2);
                    }
                    nsElem.textContent = surchargeText;
                }
                
                const niText = document.getElementById('r-night-info-text');
                if (niText) {
                    const mult = useServerNight ? (pricingBreakdown.breakdown.night_multiplier_value || 2) : (nightMultiplier || 2);
                    niText.textContent = '×' + (mult ? mult.toFixed(1) : '2.0');
                }
            } else {
                nightRateRow.style.display = 'none';
                nightInfoRow.style.display = 'none';
                nightSurcharge = 0;
            }
        }
        
        // Calculate final total price
        let finalTotalPrice = finalPrice;
        if (!hasBreakdown) {
            finalTotalPrice = basePrice + visibleDistanceCost + adminCharge + nightSurcharge;
        } else if (pricingBreakdown?.breakdown?.final_price) {
            finalTotalPrice = pricingBreakdown.breakdown.final_price;
        }
        
        // Display final total
        const totalElem = document.getElementById('r-price-excl');
        if (totalElem) {
            totalElem.textContent = '£' + finalTotalPrice.toFixed(2);
        }
        
        // Update any other total displays if they exist
        const cbTotal = document.querySelector('.cb-price-total #r-price-excl');
        if (cbTotal) cbTotal.textContent = '£' + finalTotalPrice.toFixed(2);

        // Payment info
        const paymentNote = document.querySelector('.ocb-price-box');
        if (paymentNote && !paymentNote.querySelector('.ocb-price-note')) {
            const note = document.createElement('div');
            note.className = 'ocb-price-note';
            note.style.cssText = 'margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0; font-size: 0.85em; color: #666;';
            note.innerHTML = '<strong>💡 Note:</strong> Price is locked at this stage. No changes will be made during payment.';
            paymentNote.appendChild(note);
        }
    }

    // Prevent double submission
    let isSubmitting = false;

    function handleSubmit(e) {
        e.preventDefault();
        e.stopPropagation();

        // Prevent double submission
        if (isSubmitting) {
            return;
        }

        // Check terms
        const terms = document.getElementById('agree-terms');
        if (!terms?.checked) {
            alert('Please agree to the Terms & Conditions.');
            return;
        }

        isSubmitting = true;

        // Show loading
        const loading = document.getElementById('ocb-loading');
        if (loading) loading.style.display = 'flex';

        // Disable submit button and prevent double submission
        const submitBtn = document.querySelector('.ocb-btn-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.style.visibility = 'hidden';
        }

        // Collect form data
        const form = e.target;
        const formData = new FormData(form);
        
        // Add distance to form data
        if (currentDistance > 0) {
            formData.append('distance_miles', currentDistance);
        }

        fetch(ocbData.ajaxurl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (loading) loading.style.display = 'none';

            if (data.success) {
                if (data.data.redirect) {
                    window.location.href = data.data.redirect;
                }
            } else {
                alert(data.data?.message || 'Failed to create booking.');
                isSubmitting = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.visibility = 'visible';
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
                }
            }
        })
        .catch(err => {
            if (loading) loading.style.display = 'none';
            isSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.visibility = 'visible';
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Booking';
            }
        });
    }

    /**
     * Calculate and display dynamic delivery times for service summaries
     */
    function calculateDeliveryTimes() {
        const serverNow = window.ocbData && window.ocbData.serverTime ? new Date(window.ocbData.serverTime) : new Date();
        
        const format = (d) => {
            let hours = d.getHours();
            let minutes = d.getMinutes();
            const ampm = hours >= 12 ? 'pm' : 'am';
            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = ('0' + minutes).slice(-2);
            return hours + ':' + minutes + ampm;
        };

        const collectTime = new Date(serverNow.getTime()); // Assuming immediate collection for estimate
        const collectTimeStr = format(collectTime);

        // 1. Same Day
        // Start: 3 hours after booking
        // End: 5 PM (if before 1 PM) or 8 PM (if >= 1 PM)
        const sameDayStart = new Date(serverNow.getTime() + 3 * 3600000);
        let sameDayEnd = new Date(serverNow);
        if (serverNow.getHours() < 13) {
            sameDayEnd.setHours(17, 0, 0, 0);
        } else {
            sameDayEnd.setHours(20, 0, 0, 0);
        }
        if (sameDayStart > sameDayEnd) sameDayStart.setTime(sameDayEnd.getTime());
        
        const sameDaySection = document.querySelector('.ocb-vehicles-row[data-service="same_day"]')?.closest('.ocb-service-section');
        if (sameDaySection) {
            const c = sameDaySection.querySelector('.ocb-time-collect');
            const d = sameDaySection.querySelector('.ocb-time-deliver');
            if (c) c.textContent = format(serverNow); // Booking time
            if (d) d.textContent = format(sameDayStart) + ' – ' + format(sameDayEnd);
        }

        // 2. Priority (Timed)
        // Customer selects time, earliest +3h
        const priorityTime = new Date(serverNow.getTime() + 3 * 3600000);
        const timedSection = document.querySelector('.ocb-vehicles-row[data-service="timed"]')?.closest('.ocb-service-section');
        if (timedSection) {
            const c = timedSection.querySelector('.ocb-time-collect');
            const d = timedSection.querySelector('.ocb-time-deliver');
            if (c) c.textContent = format(serverNow);
            if (d) d.textContent = 'By ' + format(priorityTime);
        }

        // 3. Dedicated (Dedicated/Direct)
        // Range: 1.5h to 3h after booking
        const dedicatedStart = new Date(serverNow.getTime() + 1.5 * 3600000);
        const dedicatedEnd = new Date(serverNow.getTime() + 3 * 3600000);
        const dedicatedSection = document.querySelector('.ocb-vehicles-row[data-service="dedicated"]')?.closest('.ocb-service-section');
        if (dedicatedSection) {
            const c = dedicatedSection.querySelector('.ocb-time-collect');
            const d = dedicatedSection.querySelector('.ocb-time-deliver');
            if (c) c.textContent = format(serverNow);
            if (d) d.textContent = format(dedicatedStart) + ' – ' + format(dedicatedEnd);
        }
    }

    /**
     * Format Time (12h with am/pm)
     */
    function formatTime(date) {
        let hours = date.getHours();
        let minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'pm' : 'am';
        hours = hours % 12;
        hours = hours ? hours : 12;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        return hours + ':' + minutes + ' ' + ampm;
    }

    /**
     * Format Time 24h (HH:MM)
     */
    function formatTime24(date) {
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${hours}:${minutes}`;
    }

    /**
     * Update Delivery Time UI based on Service Type
     */
    function updateDeliveryTimeUI() {
        const serviceTypeInput = document.getElementById('selected-service-type');
        const serviceType = serviceTypeInput ? serviceTypeInput.value : 'timed';
        
        const windowDisplay = document.getElementById('ocb-delivery-window-display');
        const windowText = document.getElementById('ocb-delivery-window-text');
        
        const timeContainer = document.getElementById('ocb-time-container');
        const dateContainer = document.getElementById('ocb-date-container');
        const labelText = document.getElementById('ocb-datetime-label');
        
        if (!windowText) return;

        const serverNow = window.ocbData && window.ocbData.serverTime ? new Date(window.ocbData.serverTime) : new Date();
        
        // Reset
        if (windowDisplay) windowDisplay.style.display = 'none';
        if (timeContainer) timeContainer.style.display = 'flex';
        if (dateContainer) dateContainer.style.display = 'flex';
        if (labelText) labelText.textContent = 'Collection/Delivery date & time';
        
        const dateInput = document.getElementById('ocb_step2_date');
        const timeInput = document.getElementById('ocb_step2_time');
        const hiddenInput = document.getElementById('ocb_step2_datetime');

        if (serviceType === 'same_day') {
            // SAME DAY Logic
            // Hide selection - it's calculated from "Now"
            if (dateContainer) dateContainer.style.display = 'none';
            if (timeContainer) timeContainer.style.display = 'none';
            if (labelText) labelText.style.display = 'none';

            // Always Today
            const todayStr = serverNow.toISOString().split('T')[0];
            if (dateInput) dateInput.value = todayStr;

            let start = new Date(serverNow.getTime() + 3 * 3600000); // +3h
            let end = new Date(serverNow);
            
            if (serverNow.getHours() < 13) {
                end.setHours(17, 0, 0);
            } else {
                end.setHours(20, 0, 0);
            }

            if (start > end) start = new Date(end.getTime());

            windowText.textContent = `${formatTime(start)} – ${formatTime(end)}`;
            if (windowDisplay) windowDisplay.style.display = 'block';

            if (hiddenInput && dateInput) {
                hiddenInput.value = todayStr + ' ' + formatTime24(end);
            }

        } else if (serviceType === 'dedicated' || serviceType === 'direct' || serviceType === 'dedicated_direct') {
            // DIRECT Logic
            // Hide selection - it's calculated from "Now"
            if (dateContainer) dateContainer.style.display = 'none';
            if (timeContainer) timeContainer.style.display = 'none';
            if (labelText) labelText.style.display = 'none';

            // Always Today
            const todayStr = serverNow.toISOString().split('T')[0];
            if (dateInput) dateInput.value = todayStr;

            const start = new Date(serverNow.getTime() + (1 * 3600000 + 30 * 60000)); // +1.5h
            const end = new Date(serverNow.getTime() + 3 * 3600000); // +3h

            windowText.textContent = `${formatTime(start)} – ${formatTime(end)}`;
            if (windowDisplay) windowDisplay.style.display = 'block';

            if (hiddenInput && dateInput) {
                hiddenInput.value = todayStr + ' ' + formatTime24(end);
            }

        } else if (serviceType === 'priority' || serviceType === 'timed' || serviceType === 'scheduled') {
            // PRIORITY Logic
            if (labelText) labelText.textContent = 'Select delivery date & time';
            
            // Initialize with +3h if empty
            if (dateInput && !dateInput.value) {
                const minTime = new Date(serverNow.getTime() + 3 * 3600000);
                dateInput.value = minTime.toISOString().split('T')[0];
                if (timeInput) timeInput.value = formatTime24(minTime);
                if (hiddenInput) hiddenInput.value = dateInput.value + ' ' + (timeInput ? timeInput.value : '');
            }
        }
    }

})();
