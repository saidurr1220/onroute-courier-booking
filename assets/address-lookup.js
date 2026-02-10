/**
 * OnRoute Address Lookup - UK Postcode Address Selection
 * Uses Postcodes.io API for free UK postcode lookups
 * 
 * @package OnRoute_Courier_Booking
 */

(function($) {
	'use strict';

	var AddressLookup = {
		currentPostcode: '',
		fullPostcode: '', // Store full postcode with space for validation
		postcodeOutcode: '', // Extract outcode for filtering (e.g., "M1" from "M1 1AE")
		currentLocation: '', // 'collection' or 'delivery'
		currentPostcodeField: '',
		currentAddressField: '',
		selectedAddressIndex: -1,
		addressesData: [],
		isModalOpen: false,
		currentPage: 1,
		addressesPerPage: 10,
		totalPages: 1,
		addressCache: {}, // Cache keyed by full postcode md5
		postcodeCityMap: {
			'EH': 'Edinburgh',
			'M': 'Manchester',
			'B': 'Birmingham',
			'L': 'Liverpool',
			'G': 'Glasgow',
			'LS': 'Leeds',
			'SH': 'Sheffield',
			'N': 'Newcastle',
			'C': 'Coventry',
			'N': 'Nottingham',
			'B': 'Bradford',
			'W': 'Wolverhampton',
			'S': 'Stoke'
		},

		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;
			
			// Address lookup button click
			$(document).on('click', '.ocb-address-lookup-btn', function(e) {
				self.openAddressModal.call(self, e);
			});

			// Modal close buttons
			$(document).on('click', '.ocb-address-modal-close', function(e) {
				self.closeModal.call(self, e);
			});

			// Address link click (table cell)
			$(document).on('click', '.ocb-address-link', function(e) {
				self.selectAddressItem.call(self, e);
			});

			// Address row click
			$(document).on('click', '.ocb-address-row', function(e) {
				// Only select if not clicking the link directly
				if (!$(e.target).closest('.ocb-address-link').length) {
					self.selectAddressItem.call(self, { currentTarget: this, preventDefault: function() {} });
				}
			});

			// Radio button change
			$(document).on('change', 'input[name="address-selection"]', function(e) {
				self.selectAddressItem.call(self, { currentTarget: this, preventDefault: function() {} });
			});

			// Pagination link click
			$(document).on('click', '.ocb-page-link', function(e) {
				e.preventDefault();
				var page = $(this).data('page');
				self.currentPage = page;
				self.renderAddressPage();
			});

			// Select button in modal
			$(document).on('click', '#ocb-address-select', function(e) {
				self.confirmAddressSelection.call(self, e);
			});

			// Manual address entry
			$(document).on('click', '#ocb-address-manual', function(e) {
				self.closeModal.call(self, e);
			});

			// Enter key in address list
			$(document).on('keypress', '.ocb-address-row', function(e) {
				if (e.which === 13) {
					self.confirmAddressSelection.call(self, e);
				}
			});
		},

		openAddressModal: function(e) {
			e.preventDefault();

			var $button = $(e.currentTarget);
			var postcodeField = $button.data('postcode-field');
			var location = $button.data('location');
			var addressField = $button.data('address-field');

			// Get postcode value WITH space (full postcode)
			var postcodeValue = $('input[name="' + postcodeField + '"]').val().trim();

			if (!postcodeValue) {
				alert('Please enter a postcode first');
				return;
			}

			// Validate UK postcode format
			if (!this.isValidUKPostcode(postcodeValue)) {
				alert('Please enter a valid UK postcode');
				return;
			}

			// Store references for later use
			// Store full postcode WITH space
			this.fullPostcode = postcodeValue.toUpperCase();
			this.currentPostcode = this.fullPostcode.replace(/\s/g, '');
			this.currentLocation = location;
			this.currentPostcodeField = postcodeField;
			this.currentAddressField = addressField;
			this.selectedAddressIndex = -1;
			this.addressesData = [];

			// Show modal
			$('#ocb-address-modal').removeClass('hidden');
			this.isModalOpen = true;
			$('#ocb-address-tbody').html('');
			$('#ocb-address-pagination').html('');
			$('#ocb-address-error').hide().html('');
			$('#ocb-address-loading').removeClass('hidden');

			// Fetch addresses
			this.fetchAddresses(this.fullPostcode);
		},

		isValidUKPostcode: function(postcode) {
			postcode = postcode.toUpperCase().replace(/\s/g, '');
			var pattern = /^[A-Z]{1,2}[0-9][0-9A-Z]?[0-9][A-Z]{2}$/;
			return pattern.test(postcode);
		},

		fetchAddresses: function(postcode) {
			// Preserve full postcode WITH space for API calls
			var fullPostcode = postcode.toUpperCase();
			var cleanPostcode = fullPostcode.replace(/\s/g, '');
			var self = this;

			// Extract outcode (first part) for validation
			var outcodeMatch = cleanPostcode.match(/^([A-Z]{1,2}[0-9]{1,2})/);
			this.postcodeOutcode = outcodeMatch ? outcodeMatch[1] : '';

			// Log for debugging
			
			

			// Check Cache using full postcode
			// DISABLED CACHE as per user request to resolve potential conflicts
			var cacheKey = this.md5(fullPostcode);
			/* 
			if (this.addressCache[cacheKey]) {
				
				setTimeout(function() {
					self.renderAddressList(self.addressCache[cacheKey]);
				}, 100);
				return;
			}
			*/

			// Try Ideal Postcodes first (UK street addresses like "21a Quarry Road, Headington, Oxford, OX3 8NT")
			var useIdealPostcodes = typeof ocbAddressData !== 'undefined' && ocbAddressData.idealPostcodesAvailable;
			if (useIdealPostcodes) {
				self.fetchFromIdealPostcodes(cleanPostcode, fullPostcode);
				return;
			}

			// Try GetAddress.io if no Ideal Postcodes key
			var useGetAddress = typeof ocbAddressData !== 'undefined' && ocbAddressData.getaddressAvailable;
			if (useGetAddress) {
				self.fetchFromGetAddress(cleanPostcode, fullPostcode);
				return;
			}

			// Fallback: Postcodes.io + Google Places
			// First get postcode geolocation from Postcodes.io
			$.ajax({
				url: 'https://api.postcodes.io/postcodes/' + encodeURIComponent(cleanPostcode),
				type: 'GET',
				dataType: 'json',
				timeout: 5000,
				success: function(response) {
					if (response.status === 200 && response.result) {
						var result = response.result;
						
						// Store for fallback
						self.postcodesIoData = result;
						// Direct Google Places search (skipping Nominatim as requested)
						self.fetchFromGooglePlaces(result, []);
					} else {
						self.showAddressError('Postcode not found. Please check and try again.');
					}
				},
				error: function(xhr, status, error) {
					
					// Even if postcodes.io fails, try Google Places with raw postcode
					self.fetchFromGooglePlaces({
						postcode: cleanPostcode,
						latitude: null,
						longitude: null
					}, []);
				}
			});
		},

		fetchAddressesFromNominatimRadius: function(postcodeResult) {
			var self = this;

			// Strategy: Do 3 searches to get maximum results
			// 1. Postcode + City name (e.g., "M1 1AE Manchester")
			// 2. City name alone (e.g., "Manchester")
			// 3. Reverse geocode at center point

			var allAddresses = [];
			var searchesCompleted = 0;
			var totalSearches = 3;

			var completeSearch = function() {
				searchesCompleted++;
				if (searchesCompleted === totalSearches) {
					// All searches done, process results
					var deduped = self.removeDuplicateAddresses(allAddresses);
					if (deduped.length > 0) {
						// Render Nominatim results
						self.renderAddressList(deduped);
						// Also try Google Places
						self.fetchFromGooglePlaces(postcodeResult, deduped);
					} else {
						// No Nominatim results, try Google Places
						self.fetchFromGooglePlaces(postcodeResult);
					}
				}
			};

			// Search 1: Postcode + City (best for finding specific businesses)
			var city = postcodeResult.admin_district || postcodeResult.region || 'UK';
			var query1 = postcodeResult.postcode + ' ' + city;
			$.ajax({
				url: 'https://nominatim.openstreetmap.org/search',
				type: 'GET',
				data: {
					q: query1,
					format: 'json',
					addressdetails: 1,
					limit: 30
				},
				dataType: 'json',
				timeout: 8000,
				success: function(response) {
					if (response && response.length > 0) {
						var addresses = self.buildAddressesFromNominatimSearch(response, postcodeResult);
						allAddresses = allAddresses.concat(addresses);
					}
					completeSearch();
				},
				error: function() {
					completeSearch();
				}
			});

			// Search 2: City name alone (for broader results)
			$.ajax({
				url: 'https://nominatim.openstreetmap.org/search',
				type: 'GET',
				data: {
					q: city + ' United Kingdom',
					format: 'json',
					addressdetails: 1,
					limit: 30,
					viewbox: (postcodeResult.longitude - 0.1) + ',' + (postcodeResult.latitude - 0.1) + ',' + (postcodeResult.longitude + 0.1) + ',' + (postcodeResult.latitude + 0.1)
				},
				dataType: 'json',
				timeout: 8000,
				success: function(response) {
					if (response && response.length > 0) {
						var addresses = self.buildAddressesFromNominatimSearch(response, postcodeResult);
						allAddresses = allAddresses.concat(addresses);
					}
					completeSearch();
				},
				error: function() {
					completeSearch();
				}
			});

			// Search 3: Reverse geocode at center point with different zoom levels
			$.ajax({
				url: 'https://nominatim.openstreetmap.org/reverse',
				type: 'GET',
				data: {
					lat: postcodeResult.latitude,
					lon: postcodeResult.longitude,
					format: 'json',
					addressdetails: 1,
					zoom: 20
				},
				dataType: 'json',
				timeout: 8000,
				success: function(response) {
					if (response && response.address) {
						var addresses = self.buildAddressesFromNominatimReverse(response, postcodeResult);
						allAddresses = allAddresses.concat(addresses);
					}
					completeSearch();
				},
				error: function() {
					completeSearch();
				}
			});
		},

		fetchFromIdealPostcodes: function(cleanPostcode, fullPostcode) {
			var self = this;
			var nonce = $('[name="ocb_api_nonce_field"]').val() || (typeof ocbAddressData !== 'undefined' ? ocbAddressData.nonce : '');
			var url = typeof ocbAddressData !== 'undefined' ? ocbAddressData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

			$.ajax({
				url: url,
				type: 'POST',
				data: {
					action: 'ocb_search_idealpostcodes',
					postcode: fullPostcode,
					page: 0,
					nonce: nonce
				},
				dataType: 'json',
				timeout: 10000,
				success: function(response) {
					if (response.success && response.data && response.data.length > 0) {
						self.renderAddressList(response.data);
					} else {
						self.fallbackToGetAddressOrGoogle(cleanPostcode, fullPostcode);
					}
				},
				error: function() {
					self.fallbackToGetAddressOrGoogle(cleanPostcode, fullPostcode);
				}
			});
		},

		fallbackToGetAddressOrGoogle: function(cleanPostcode, fullPostcode) {
			var self = this;
			var useGetAddress = typeof ocbAddressData !== 'undefined' && ocbAddressData.getaddressAvailable;
			if (useGetAddress) {
				self.fetchFromGetAddress(cleanPostcode, fullPostcode);
				return;
			}
			$.ajax({
				url: 'https://api.postcodes.io/postcodes/' + encodeURIComponent(cleanPostcode),
				type: 'GET',
				dataType: 'json',
				timeout: 5000,
				success: function(pcResponse) {
					if (pcResponse.status === 200 && pcResponse.result) {
						self.postcodesIoData = pcResponse.result;
						self.fetchFromGooglePlaces(pcResponse.result, []);
					} else {
						self.showAddressError('Postcode not found. Please check and try again.');
					}
				},
				error: function() {
					self.fetchFromGooglePlaces({ postcode: cleanPostcode, latitude: null, longitude: null }, []);
				}
			});
		},

		fetchFromGetAddress: function(cleanPostcode, fullPostcode) {
			var self = this;
			var nonce = $('[name="ocb_api_nonce_field"]').val() || (typeof ocbAddressData !== 'undefined' ? ocbAddressData.nonce : '');
			var url = typeof ocbAddressData !== 'undefined' ? ocbAddressData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

			$.ajax({
				url: url,
				type: 'POST',
				data: {
					action: 'ocb_search_getaddress',
					postcode: fullPostcode,
					nonce: nonce
				},
				dataType: 'json',
				timeout: 10000,
				success: function(response) {
					if (response.success && response.data && response.data.length > 0) {
						self.renderAddressList(response.data);
					} else {
						self.fallbackToGetAddressOrGoogle(cleanPostcode, fullPostcode);
					}
				},
				error: function() {
					self.fallbackToGetAddressOrGoogle(cleanPostcode, fullPostcode);
				}
			});
		},

		fetchFromGooglePlaces: function(postcodeResult, existingAddresses) {
			var self = this;

			// Get nonce from page
			var nonce = $('[name="ocb_api_nonce_field"]').val() || (typeof ocbAddressData !== 'undefined' ? ocbAddressData.nonce : '');
			
			// Get AJAX URL
			var url = typeof ocbAddressData !== 'undefined' ? ocbAddressData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
			
			

			$.ajax({
				url: url,
				type: 'POST',
				data: {
					action: 'ocb_search_places',
					postcode: postcodeResult.postcode,
					lat: postcodeResult.latitude,
					lon: postcodeResult.longitude,
					nonce: nonce
				},
				dataType: 'json',
				timeout: 10000,
				success: function(response) {
					if (response.success && response.data && response.data.length) {
						
						
						// Validate results against postcode
						var validatedPlaces = self.validateAddressesByPostcode(response.data, self.fullPostcode, self.postcodeOutcode);
						

						if (validatedPlaces.length === 0) {
							console.warn("No addresses matched the postcode validation, using Postcodes.io fallback");
							// Try fallback to Postcodes.io data
							if (self.postcodesIoData) {
								var fallbackAddresses = self.createAddressesFromPostcodesIO(self.postcodesIoData);
								if (fallbackAddresses.length > 0) {
									self.renderAddressList(fallbackAddresses);
									return;
								}
							}
							self.showAddressError("We couldn't confirm this address automatically. Please enter it manually.");
							return;
						}

						// Map PHP response - keep structure consistent with PHP
						var places = validatedPlaces.map(function(place) {
							return {
								id: place.id,
								name: place.name || '',
								formatted: place.formatted || '',
								lat: place.lat,
								lon: place.lon,
								postcode: place.postcode || postcodeResult.postcode
							};
						});
						
						// If we already have other addresses, merge and deduplicate
						if (existingAddresses && existingAddresses.length > 0) {
							var allAddresses = existingAddresses.concat(places);
							allAddresses = self.removeDuplicateAddresses(allAddresses);
							if (allAddresses.length > existingAddresses.length) {
								self.renderAddressList(allAddresses);
							}
						} else {
							// Use Google Places only
							self.renderAddressList(places);
						}
					} else {
						// No results from Google Places, fallback to Postcodes.io data
						
						if (self.postcodesIoData) {
							var fallbackAddresses = self.createAddressesFromPostcodesIO(self.postcodesIoData);
							if (fallbackAddresses.length > 0) {
								self.renderAddressList(fallbackAddresses);
								return;
							}
						}
						// Handle error response
						var errorMsg = 'No addresses found. Please check and try again.';
						if (response.data) {
							if (typeof response.data === 'string') {
								errorMsg = response.data;
							} else if (response.data.message) {
								errorMsg = response.data.message;
							}
						}
						
						self.showAddressError(errorMsg);
					}
				},
				error: function(xhr, status, error) {
					var errorMsg = 'Error searching addresses. Please try again.';
					
					if (xhr.status === 403) {
						errorMsg = 'Permission denied. API key may not be configured.';
					} else if (xhr.status === 500) {
						errorMsg = 'Server error. Please contact support.';
					}
					self.showAddressError(errorMsg);
				}
			});
		},

		/**
		 * Validate addresses match the postcode
		 * Filters out addresses from different cities/postcodes
		 */
		validateAddressesByPostcode: function(addresses, fullPostcode, outcode) {
			var self = this;
			var fullPostcodeClean = fullPostcode.replace(/\s/g, '').toUpperCase();
			
			// First pass: Try to find EXACT postcode matches
			var exactMatches = [];
			$.each(addresses, function(i, place) {
				var postcodeField = (place.postcode || '').replace(/\s/g, '').toUpperCase();
				var formattedAddress = (place.formatted || '').replace(/\s/g, '').toUpperCase();
				
				if (postcodeField === fullPostcodeClean || formattedAddress.indexOf(fullPostcodeClean) !== -1) {
					exactMatches.push(place);
				}
			});
			
			// If we found plenty of exact postcode matches, use only those
			if (exactMatches.length >= 3) {
				return exactMatches;
			}
			
			// Fall back to outcode matching AND includes exact matches (if any < 3)
			var validatedList = exactMatches;
			var seenFormatted = {};
			$.each(exactMatches, function(i, p) { seenFormatted[p.formatted] = true; });

			$.each(addresses, function(i, place) {
				if (seenFormatted[place.formatted]) return;

				var validation = self.validateAddressPostcode(place, fullPostcode, outcode);
				if (validation.isValid) {
					validatedList.push(place);
					seenFormatted[place.formatted] = true;
				}
			});

			return validatedList;
		},

		/**
		 * Single address postcode validation
		 * Returns { isValid: bool, reason: string }
		 * Allows some flexibility for dense urban areas with multiple nearby postcodes
		 */
		validateAddressPostcode: function(place, fullPostcode, outcode) {
			// If place has postcode info, validate it
			if (place.postcode) {
				var placePostcodeClean = place.postcode.replace(/\s/g, '').toUpperCase();
				var searchPostcodeClean = fullPostcode.replace(/\s/g, '').toUpperCase();
				var placeOutcode = placePostcodeClean.substring(0, outcode.length);
				
				// Strict check: if postcodes are completely different beyond outcode
				// (e.g., M1 vs B33), reject it
				if (placeOutcode.toUpperCase() !== outcode.toUpperCase()) {
					return { isValid: false, reason: "Postcode outcode mismatch (" + placeOutcode + " vs " + outcode + ")" };
				}
				
				// Allow same-outcode results even if full postcode differs
				// (e.g., SW1A vs SW1W are both in Westminster area)
				// Will log warning during population if there's a full postcode mismatch
			}

			// Validate by city if postcode validation passed or N/A
			// Extract city from place if available
			var placeCity = (place.formatted || '').split(',').pop().trim().toLowerCase();
			
			// For now, if city is in the address and we have outcode, assume validation passed
			// More sophisticated matching would require a postcode-to-city database
			if (outcode && placeCity.length > 2) {
				// Basic checks for common mismatches - only reject if VERY different
				if (outcode.match(/^EH/) && !placeCity.match(/edinburgh|scotland/i)) {
					return { isValid: false, reason: "City mismatch for Edinburgh postcode" };
				}
				if (outcode.match(/^M\d/) && !placeCity.match(/manchester|greater manchester/i)) {
					return { isValid: false, reason: "City mismatch for Manchester postcode" };
				}
				if (outcode.match(/^B\d/) && !placeCity.match(/birmingham|west midland/i)) {
					return { isValid: false, reason: "City mismatch for Birmingham postcode" };
				}
			}

			return { isValid: true, reason: "" };
		},

		/**
		 * Create address options from Postcodes.io data when Google Places doesn't have details
		 */
		createAddressesFromPostcodesIO: function(result) {
			var addresses = [];
			
			// Primary address: street + district + postcode
			var parts = [];
			var displayName = '';
			
			if (result.street_name) {
				parts.push(result.street_name);
				displayName = result.street_name;
			}
			
			if (result.admin_ward && result.admin_ward !== result.street_name) {
				parts.push(result.admin_ward);
				if (!displayName) displayName = result.admin_ward;
			}
			
			if (result.admin_district && result.admin_district !== result.admin_ward && parts.indexOf(result.admin_district) === -1) {
				parts.push(result.admin_district);
				if (!displayName) displayName = result.admin_district;
			}
			
			if (result.postcode && parts.indexOf(result.postcode) === -1) {
				parts.push(result.postcode);
			}
			
			var mainAddress = parts.join(', ');
			
			if (mainAddress.length > 5) {
				addresses.push({
					id: null,
					name: displayName || result.postcode,
					formatted: mainAddress,
					lat: result.latitude,
					lon: result.longitude,
					postcode: result.postcode
				});
			}
			
			// Alternative: just district + postcode if different from above
			if (result.admin_district && result.street_name !== result.admin_district) {
				var districtParts = [result.admin_district, result.postcode];
				var districtAddress = districtParts.join(', ');
				
				if (districtAddress !== mainAddress && !addresses.find(function(a) { return a.formatted === districtAddress; })) {
					addresses.push({
						id: null,
						name: result.admin_district,
						formatted: districtAddress,
						lat: result.latitude,
						lon: result.longitude,
						postcode: result.postcode
					});
				}
			}
			
			// Alternative: just postcode
			if (addresses.length === 0) {
				addresses.push({
					id: null,
					name: result.postcode,
					formatted: result.postcode,
					lat: result.latitude,
					lon: result.longitude,
					postcode: result.postcode
				});
			}
			
			
			return addresses;
		},

		fetchAddressesFromNominatimReverse: function(postcodeResult) {
			var self = this;

			// Fallback: Use /reverse endpoint for coordinate-based lookup
			$.ajax({
				url: 'https://nominatim.openstreetmap.org/reverse',
				type: 'GET',
				data: {
					lat: postcodeResult.latitude,
					lon: postcodeResult.longitude,
					format: 'json',
					addressdetails: 1,
					zoom: 18
				},
				dataType: 'json',
				timeout: 8000,
				success: function(response) {
					if (response && response.address) {
						var addresses = self.buildAddressesFromNominatimReverse(response, postcodeResult);
						// If only got a few addresses from Nominatim, combine with Postcodes.io variants
						if (addresses && addresses.length > 0) {
							if (addresses.length < 3) {
								var fallbackAddresses = self.formatPostcodeIOResult(postcodeResult);
								// Merge and remove duplicates
								addresses = addresses.concat(fallbackAddresses);
								addresses = self.removeDuplicateAddresses(addresses);
							}
							self.renderAddressList(addresses);
						} else {
							// Fallback to postcode location variants
							var fallbackAddresses = self.formatPostcodeIOResult(postcodeResult);
							self.renderAddressList(fallbackAddresses);
						}
					} else {
						// Fallback to postcode location variants
						var fallbackAddresses = self.formatPostcodeIOResult(postcodeResult);
						self.renderAddressList(fallbackAddresses);
					}
				},
				error: function(xhr, status, error) {
					// Fallback to postcode location variants
					var fallbackAddresses = self.formatPostcodeIOResult(postcodeResult);
					self.renderAddressList(fallbackAddresses);
				}
			});
		},

		buildAddressesFromNominatimSearch: function(results, postcodeResult) {
			/**
			 * Parse Nominatim /search results for postcode area
			 * Returns array of unique, meaningful addresses 
			 */
			var addresses = [];
			var seen = {};
			var postcode = postcodeResult.postcode;

			for (var i = 0; i < results.length && addresses.length < 15; i++) {
				var result = results[i];
				
				// Build full address from components
				if (!result.address) continue;

				var addr = result.address;
				var parts = [];
				var formatted = '';

				// Prioritize: building/POI name, then number+road, then suburb/city
				if (addr.building && addr.building.length > 2) {
					parts.push(addr.building);
					formatted = addr.building;
				}
				if (addr.house_number && addr.road) {
					parts.push(addr.house_number + ' ' + addr.road);
					if (!formatted) formatted = addr.house_number + ' ' + addr.road;
				} else if (addr.road && addr.road.length > 2) {
					parts.push(addr.road);
					if (!formatted) formatted = addr.road;
				}
				if (addr.suburb && addr.suburb !== formatted && parts.indexOf(addr.suburb) === -1) {
					parts.push(addr.suburb);
				}
				if (addr.city && addr.city !== formatted && parts.indexOf(addr.city) === -1) {
					parts.push(addr.city);
				}
				if (postcode && parts.indexOf(postcode) === -1) {
					parts.push(postcode);
				}

				var fullAddress = parts.filter(function(p) { return p && p.trim().length > 0; }).join(', ');
				
				// Skip very short addresses and duplicates
				if (fullAddress.length < 5) continue;
				
				var key = fullAddress.toLowerCase();
				if (seen[key]) continue;
				
				seen[key] = true;
				addresses.push({
					id: addresses.length,
				name: formatted,
				formatted: fullAddress,
				lat: result.lat,
				lon: result.lon,
				postcode: postcodeResult.postcode
			});
		}

		return addresses;
	},

	removeDuplicateAddresses: function(addresses) {
			/**
			 * Remove duplicate addresses by checking the 'formatted' property
			 */
			var seen = {};
			var unique = [];
			for (var i = 0; i < addresses.length; i++) {
				var fullAddr = (addresses[i].formatted || '').toLowerCase();
				if (!seen[fullAddr]) {
					seen[fullAddr] = true;
					unique.push(addresses[i]);
				}
			}
			return unique;
		},

		buildAddressesFromNominatimReverse: function(response, postcodeResult) {
			var addresses = [];
			var address = response.address;
			var postcode = postcodeResult.postcode;

			// Build main address - prioritize detailed components
			var parts = [];
			if (address.house_number) parts.push(address.house_number);
			if (address.road) parts.push(address.road);
			if (address.building && address.building !== address.road) parts.push(address.building);
			if (address.suburb) parts.push(address.suburb);
			if (address.neighbourhood && address.neighbourhood !== address.suburb) parts.push(address.neighbourhood);
			if (address.city) parts.push(address.city);
			if (address.county && address.county !== address.city) parts.push(address.county);
			if (postcode) parts.push(postcode);

			var mainAddress = parts.filter(function(p) { return p && p.trim(); }).join(', ');
			if (mainAddress && mainAddress.length > 5) {
				addresses.push({
					id: 0,
					name: (address.house_number ? address.house_number + ' ' : '') + (address.road || address.building || address.suburb || address.city || ''),
					formatted: mainAddress,
					lat: response.lat,
					lon: response.lon,
					postcode: postcode
				});
			}

			// Add building variant if available
			if (address.building && address.building !== address.road) {
				var buildingAddress = [address.building];
				if (address.city) buildingAddress.push(address.city);
				if (address.county && address.county !== address.city) buildingAddress.push(address.county);
				if (postcode) buildingAddress.push(postcode);
				var buildingFull = buildingAddress.filter(function(p) { return p && p.trim(); }).join(', ');
				if (buildingFull.length > 5 && buildingFull !== mainAddress) {
					addresses.push({
						id: 1,
						name: address.building,
						formatted: buildingFull,
						lat: response.lat,
						lon: response.lon,
						postcode: postcode
					});
				}
			}

			// Add street variant if we have road/house_number
			if (address.road || address.house_number) {
				var streetParts = [];
				if (address.house_number) streetParts.push(address.house_number);
				if (address.road) streetParts.push(address.road);
				if (address.suburb) streetParts.push(address.suburb);
				if (postcode) streetParts.push(postcode);
				var streetFull = streetParts.filter(function(p) { return p && p.trim(); }).join(', ');
				if (streetFull.length > 5 && streetFull !== mainAddress) {
					addresses.push({
						id: 2,
						name: (address.house_number ? address.house_number + ' ' : '') + (address.road || ''),
						formatted: streetFull,
						lat: response.lat,
						lon: response.lon,
						postcode: postcode
					});
				}
			}

			// Add city/district variant
			if (address.city) {
				var cityAddress = [address.city];
				if (address.county && address.county !== address.city) cityAddress.push(address.county);
				if (postcode) cityAddress.push(postcode);
				var cityFull = cityAddress.filter(function(p) { return p && p.trim(); }).join(', ');
				if (cityFull.length > 5 && cityFull !== mainAddress) {
					addresses.push({
						id: 3,
						name: address.city,
						formatted: cityFull,
						lat: response.lat,
						lon: response.lon,
						postcode: postcode
					});
				}
			}

			return addresses;
		},

		formatPostcodeIOResult: function(result) {
			/**
			 * Format Postcodes.io result into address options
			 * Postcodes.io provides postcode geolocation data with admin areas
			 */
			var addresses = [];

			// Main postcode address
			var street = result.street_name || result.administrative_county || result.county || '';
			var district = result.admin_district || result.district || '';
			var outcode = result.outcode || '';

			var mainAddress = {
				id: 0,
				name: street || district || result.postcode,
				formatted: this.buildFullPostcodeAddress({
					street: street,
					district: district,
					county: result.county,
					postcode: result.postcode
				}),
				lat: result.latitude,
				lon: result.longitude,
				postcode: result.postcode
			};
			addresses.push(mainAddress);

			// Admin ward option
			if (result.admin_ward && result.admin_ward !== district) {
				addresses.push({
					id: 1,
					name: result.admin_ward,
					formatted: this.buildFullPostcodeAddress({
						street: result.admin_ward,
						district: district,
						postcode: result.postcode
					}),
					lat: result.latitude,
					lon: result.longitude,
					postcode: result.postcode
				});
			}

			// District option
			if (district && district !== street) {
				addresses.push({
					id: 2,
					name: district,
					formatted: this.buildFullPostcodeAddress({
						street: district,
						county: result.county,
						postcode: result.postcode
					}),
					lat: result.latitude,
					lon: result.longitude,
					postcode: result.postcode
				});
			}

			// Parliamentary constituency
			if (result.parliamentary_constituency && result.parliamentary_constituency !== district) {
				addresses.push({
					id: 3,
					name: result.parliamentary_constituency,
					formatted: this.buildFullPostcodeAddress({
						street: result.parliamentary_constituency,
						district: district,
						postcode: result.postcode
					}),
					lat: result.latitude,
					lon: result.longitude,
					postcode: result.postcode
				});
			}

			// Remove duplicates
			var seen = {};
			addresses = addresses.filter(function(addr) {
				if (!seen[addr.formatted]) {
					seen[addr.formatted] = true;
					return true;
				}
				return false;
			});

			return addresses;
		},

		buildFullPostcodeAddress: function(parts) {
			var addressParts = [];
			if (parts.street) addressParts.push(parts.street);
			if (parts.district && parts.district !== parts.street) addressParts.push(parts.district);
			if (parts.county && parts.county !== parts.district && parts.county !== parts.street) addressParts.push(parts.county);
			if (parts.postcode) addressParts.push(parts.postcode);

			return addressParts.filter(function(p) { return p && p.trim(); }).join(', ');
		},

		renderAddressList: function(addresses) {
			this.addressesData = addresses;
			
			// Hide loading spinner
			$('#ocb-address-loading').addClass('hidden');
			$('#ocb-address-error').hide();
			$('#ocb-address-list').show();

			// Cache the results using full postcode as key
			if (this.fullPostcode) {
			var cacheKey = this.md5(this.fullPostcode);
			if (!this.addressCache[cacheKey]) {
				this.addressCache[cacheKey] = addresses;
			}
		}
			this.totalPages = Math.ceil(addresses.length / this.addressesPerPage);
			this.currentPage = 1;

			// Render first page
			this.renderAddressPage();
		},

		/**
		 * Simple MD5 hash function for cache keys
		 * Used to create consistent cache keys from postcodes
		 */
		md5: function(str) {
			// Simple hash implementation (not cryptographic but good enough for cache keys)
			var hash = 0;
			if (str.length === 0) return hash.toString();
			for (var i = 0; i < str.length; i++) {
				var char = str.charCodeAt(i);
				hash = ((hash << 5) - hash) + char;
				hash = hash & hash; // Convert to 32bit integer
			}
			return Math.abs(hash).toString(16);
		},

		renderAddressPage: function() {
			var self = this;
			var startIndex = (this.currentPage - 1) * this.addressesPerPage;
			var endIndex = Math.min(startIndex + this.addressesPerPage, this.addressesData.length);
			var pageAddresses = this.addressesData.slice(startIndex, endIndex);


			var html = '';
			$.each(pageAddresses, function(i, addr) {
				var actualIndex = startIndex + i;
				var isSelected = (actualIndex === self.selectedAddressIndex) ? ' checked' : '';
				html += '<tr class="ocb-address-row" data-index="' + actualIndex + '">';
				html += '<td class="ocb-address-col-select">';
				html += '<input type="radio" name="address-selection" value="' + actualIndex + '"' + isSelected + ' />';
				html += '</td>';
				html += '<td class="ocb-address-col-address">';
				html += '<a href="#" class="ocb-address-link" data-index="' + actualIndex + '">';
				
				var displayName = addr.name || '';
				var displayAddress = addr.formatted || '';
				var displayPostcode = addr.postcode || '';

				// Display Name (e.g. "Jactin House")
				if (displayName) {
					html += '<div class="ocb-address-name"><strong>' + self.escapeHtml(displayName) + '</strong></div>';
				}
				
				// Display Full Address
				var detailsText = '';
				
				if (displayAddress) {
					// If the address is exactly the same as the name, don't repeat it
					if (displayName && displayAddress === displayName) {
						detailsText = ''; 
					} else {
						// Otherwise, SHOW EVERYTHING. Do not try to strip the name from the start.
						// The user wants the "whole address" visible.
						// Even if it duplicates the name slightly ("Jactin House, 24 Hood St..."), it's better than hiding the street.
						detailsText = displayAddress;
					}
				}
				
				// Ensure postcode is visible if it wasn't in the address string
				// (Only append if we are sure it's not there)
				if (displayPostcode && detailsText.toLowerCase().indexOf(displayPostcode.toLowerCase()) === -1) {
					if (detailsText) {
						detailsText += ', ' + displayPostcode;
					} else {
						detailsText = displayPostcode;
					}
				}
				
				if (detailsText) {
					html += '<div class="ocb-address-details">' + self.escapeHtml(detailsText) + '</div>';
				}
				
				html += '</a>';
				html += '</td>';
				html += '</tr>';
			});

			var $tbody = $('#ocb-address-tbody');
			$tbody.html(html);

			// Render pagination
			this.renderPagination();
		},

		renderPagination: function() {
			if (this.totalPages <= 1) {
				$('#ocb-address-pagination').html('');
				return;
			}

			var self = this;
			var html = '<div class="ocb-pagination">';

			for (var i = 1; i <= this.totalPages; i++) {
				var activeClass = (i === this.currentPage) ? ' active' : '';
				html += '<a href="#" class="ocb-page-link' + activeClass + '" data-page="' + i + '">' + i + '</a>';
			}

			html += '</div>';
			$('#ocb-address-pagination').html(html);
		},

		selectAddressItem: function(e) {
			e.preventDefault();
			var $target = $(e.currentTarget);
			var $row = $target.closest('.ocb-address-row');
			var index = $row.data('index');

			// Remove previous selection
			$('.ocb-address-row').removeClass('ocb-address-row-selected');

			// Add selection to clicked item
			$row.addClass('ocb-address-row-selected');
			$row.find('input[type="radio"]').prop('checked', true);

			this.selectedAddressIndex = index;
		},

		confirmAddressSelection: function(e) {
			if (e) e.preventDefault();
			var self = this;


			if (this.selectedAddressIndex < 0 || this.selectedAddressIndex >= this.addressesData.length) {
				alert('Please select an address');
				return;
			}

			var selectedAddress = this.addressesData[this.selectedAddressIndex];

			// --- Ideal Postcodes: Populate directly from raw data (no extra API call) ---
			if (selectedAddress.source === 'idealpostcodes' && selectedAddress.raw) {
				this.populateFormFromIdealPostcodes(selectedAddress);
				this.closeModal();
				return;
			}

			// --- GetAddress.io: Fetch full details for form population ---
			if (selectedAddress.source === 'getaddress' && selectedAddress.id) {
				var $selectBtn = $('.ocb-address-modal-footer .ocb-btn-primary');
				var originalText = $selectBtn.text();
				$selectBtn.text('Getting details...').prop('disabled', true);

				var nonce = $('[name="ocb_api_nonce_field"]').val() || (typeof ocbAddressData !== 'undefined' ? ocbAddressData.nonce : '');
				var url = typeof ocbAddressData !== 'undefined' ? ocbAddressData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

				$.ajax({
					url: url,
					type: 'POST',
					data: {
						action: 'ocb_get_getaddress_details',
						getaddress_id: selectedAddress.id,
						nonce: nonce
					},
					dataType: 'json',
					success: function(response) {
						if (response.success && response.data) {
							self.populateFormFromGetAddress(response.data, selectedAddress);
						} else {
							self.populateFormWithFallback(selectedAddress);
						}
					},
					error: function() {
						self.populateFormWithFallback(selectedAddress);
					},
					complete: function() {
						$selectBtn.text(originalText).prop('disabled', false);
						self.closeModal();
					}
				});
				return;
			}

			// --- Google Places: Fetch Full Details from Google Places API ---
			// This gets us structured data (street, city, postcode) separately
			if (selectedAddress.id) {
				var $selectBtn = $('.ocb-address-modal-footer .ocb-btn-primary');
				var originalText = $selectBtn.text();
				$selectBtn.text('Getting details...').prop('disabled', true);

				var nonce = $('[name="ocb_api_nonce_field"]').val() || (typeof ocbAddressData !== 'undefined' ? ocbAddressData.nonce : '');
				var url = typeof ocbAddressData !== 'undefined' ? ocbAddressData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

				$.ajax({
					url: url,
					type: 'POST',
					data: {
						action: 'ocb_get_place_details',
						place_id: selectedAddress.id,
						nonce: nonce
					},
					dataType: 'json',
					success: function(response) {
						if (response.success && response.data) {
							self.populateFormWithDetails(response.data, selectedAddress);
						} else {
							// Fallback to old parsing if details fetch fails
							console.warn('Details fetch failed, using fallback parsing');
							self.populateFormWithFallback(selectedAddress);
						}
					},
					error: function() {
						self.populateFormWithFallback(selectedAddress);
					},
					complete: function() {
						$selectBtn.text(originalText).prop('disabled', false);
						self.closeModal();
					}
				});
			} else {
				// No Place ID (unlikely with new logic), use fallback
				this.populateFormWithFallback(selectedAddress);
				this.closeModal();
			}
		},

		populateFormWithDetails: function(details, selectedAddress) {
			/**
			 * CRITICAL FIX: Use the EXACT data shown in the list
			 * This ensures: What's displayed = What's filled
			 */
			var components = details.address_components || [];
			var getComponent = function(type) {
				for (var i = 0; i < components.length; i++) {
					if (components[i].types.indexOf(type) !== -1) {
						return components[i].long_name;
					}
				}
				return '';
			};

			var result = {
				addressLine1: '',
				addressLine2: '',
				city: '',
				postcode: '',
				company: ''
			};

			// THE CORE FIX: Use the data shown in the list
			var displayedName = selectedAddress.name || '';           // e.g., "Jactin House"
			var displayedFullAddress = selectedAddress.formatted || ''; // e.g., "24 Hood Street, Ancoats, Manchester M4 6WX"
			var displayedPostcode = selectedAddress.postcode || '';
			
			var placeName = details.name || displayedName || '';
			var types = details.types || [];
			var isEstablishment = types.indexOf('establishment') !== -1 || 
			                     types.indexOf('point_of_interest') !== -1 ||
			                     types.indexOf('premise') !== -1 ||
			                     types.indexOf('landmark') !== -1;

			// Extract components
			var streetNumber = getComponent('street_number') || '';
			var route = getComponent('route') || '';
			var subpremise = getComponent('subpremise') || '';
			var premise = getComponent('premise') || '';
			var subLocality = getComponent('sublocality_level_1') || getComponent('neighborhood') || '';
			var city = getComponent('postal_town') || getComponent('locality') || '';
			var postcode = getComponent('postal_code') || '';

			// LOGIC: If it's a named establishment with a street address, show both
			if (isEstablishment && placeName && (streetNumber || route)) {
				// Business name in Line 1, street in Line 2
				result.addressLine1 = placeName;
				result.company = placeName;
				
				var line2Parts = [];
				if (streetNumber && route) {
					line2Parts.push(streetNumber + ' ' + route);
				} else if (route) {
					line2Parts.push(route);
				}
				if (subpremise) {
					line2Parts.push(subpremise);
				}
				if (subLocality && subLocality !== city && line2Parts.join(', ').indexOf(subLocality) === -1) {
					line2Parts.push(subLocality);
				}
				result.addressLine2 = line2Parts.join(', ').trim();
			} else if (streetNumber || route) {
				// Regular street address
				if (streetNumber && route) {
					result.addressLine1 = streetNumber + ' ' + route;
				} else if (route) {
					result.addressLine1 = route;
				}
				
				var line2Parts = [];
				if (subpremise) line2Parts.push(subpremise);
				if (subLocality && subLocality !== city) {
					line2Parts.push(subLocality);
				}
				result.addressLine2 = line2Parts.join(', ').trim();
				
				if (placeName && isEstablishment) {
					result.company = placeName;
				}
			} else {
				// FALLBACK: Parse the displayed full address string
				// This ensures we don't lose information
				var addrParts = displayedFullAddress.split(',').map(function(p) { return p.trim(); });
				
				if (addrParts.length > 0) {
					result.addressLine1 = addrParts[0];
				}
				if (addrParts.length > 1) {
					// Join all middle parts for Line 2 (exclude the last part if it's postcode)
					var middleParts = addrParts.slice(1);
					// Remove postcode from end if it's there
					if (middleParts.length > 0 && /^[A-Z0-9]{1,4}\s?[A-Z0-9]{1,3}$/i.test(middleParts[middleParts.length - 1])) {
						middleParts.pop();
					}
					result.addressLine2 = middleParts.join(', ');
				}
				
				if (placeName && isEstablishment) {
					result.company = placeName;
				}
			}

			// Fill City
			result.city = city || '';

			// Fill Postcode - use what we have
			result.postcode = postcode || displayedPostcode || '';

			// Ensure Company is captured
			if (!result.company && placeName && isEstablishment) {
				result.company = placeName;
			}

			// GUARANTEE: If Line 1 is still empty, use the displayed address
			if (!result.addressLine1 && displayedFullAddress) {
				var firstComma = displayedFullAddress.indexOf(',');
				if (firstComma > 0) {
					result.addressLine1 = displayedFullAddress.substring(0, firstComma).trim();
				} else {
					result.addressLine1 = displayedFullAddress;
				}
			}

			// Trim all
			result.addressLine1 = (result.addressLine1 || '').trim();
			result.addressLine2 = (result.addressLine2 || '').trim();
			result.city = (result.city || '').trim();
			result.postcode = (result.postcode || '').trim();
			result.company = (result.company || '').trim();

			this.fillFormFields(result);
		},

		populateFormFromIdealPostcodes: function(selectedAddress) {
			var raw = selectedAddress.raw || {};
			var line1 = (raw.line_1 || '').trim();
			var line2 = (raw.line_2 || '').trim();
			var line3 = (raw.line_3 || '').trim();
			var town = (raw.post_town || '').trim();
			var postcode = (raw.postcode || selectedAddress.postcode || '').trim();

			var result = {
				addressLine1: line1,
				addressLine2: [line2, line3].filter(Boolean).join(', '),
				city: town,
				postcode: postcode,
				company: ''
			};
			this.fillFormFields(result);
		},

		populateFormFromGetAddress: function(details, selectedAddress) {
			var result = {
				addressLine1: details.address_line1 || '',
				addressLine2: details.address_line2 || '',
				city: details.city || '',
				postcode: details.postcode || selectedAddress.postcode || '',
				company: ''
			};
			this.fillFormFields(result);
		},

		populateFormWithFallback: function(selectedAddress) {
			/**
			 * Fallback parsing when Google Places details not available
			 * Uses simple address string parsing
			 */
			
			
			var companyName = '';
			var title = selectedAddress.name || '';
			var addressText = selectedAddress.formatted || '';
			
			// Extract company name if title is different from full address
			if (title && addressText.indexOf(title) === -1 && title.length > 2) {
				companyName = title;
			}

			var parsedAddress = this.parseAddressComponents(addressText);
			if (companyName && !parsedAddress.company) {
				parsedAddress.company = companyName;
			}
			
			// Ensure addressLine1 is not empty
			if (!parsedAddress.addressLine1) {
				if (selectedAddress.formatted) {
					parsedAddress.addressLine1 = selectedAddress.formatted;
				}
				parsedAddress.addressLine2 = '';
			}

			
			this.fillFormFields(parsedAddress);
		},

		fillFormFields: function(data) {
			var isCollection = this.currentLocation === 'collection';
			var addressFieldName = isCollection ? 'step3_collection_address' : 'step3_delivery_address';
			var addressLine2FieldName = isCollection ? 'step3_collection_address_line2' : 'step3_delivery_address_line2';
			var companyFieldName = isCollection ? 'step3_collection_company' : 'step3_delivery_company';
			var postcodeFieldName = isCollection ? 'collection_postcode' : 'delivery_postcode';
			var cityFieldName = isCollection ? 'step3_collection_city' : 'step3_delivery_city';
			
			// Fallback field names (try standard names if custom step3 names fail)
			var fallbackAddressName = isCollection ? 'collection_address' : 'delivery_address';
			var fallbackAddressLine2Name = isCollection ? 'collection_address_line2' : 'delivery_address_line2';
			var fallbackCompanyName = isCollection ? 'collection_company' : 'delivery_company';
			var fallbackCityName = isCollection ? 'collection_city' : 'delivery_city';

			var self = this;

			var setFieldValue = function(name, value, fallbackName) {
				var val = (value === undefined || value === null) ? '' : String(value);

				// Try primary name/ID
				var $field = $('[name="' + name + '"], #' + name);
				
				// Try fallback if not found
				if ($field.length === 0 && fallbackName) {
					$field = $('[name="' + fallbackName + '"], #' + fallbackName + ', textarea[name="' + fallbackName + '"]');
				}
				
				if ($field.length > 0) {
					$field.val(value);
				$field.trigger('change').trigger('input').trigger('blur');
				
				// Force update for specific fields (address textarea often needs explicit update)
				if ($field.is('textarea')) {
					$field.text(value);
					$field.html(value);
				}
				
				try {
					if ($field[0]) {
						if ($field.is('input, textarea, select')) {
							$field[0].dispatchEvent(new Event('change', { bubbles: true }));
							$field[0].dispatchEvent(new Event('input', { bubbles: true }));
						}
					}
				} catch(e) {}

				return true;
			} else {
				return false;
			}
		};

			// Build address for main field - street parts (backend appends address_line2 + city)
			// Match reference format: "21a Quarry Road, Headington" or "Flat 1, 1 Quarry Road, Headington"
			var streetParts = [];
			if (data.addressLine1) streetParts.push(data.addressLine1);
			if (data.addressLine2) streetParts.push(data.addressLine2);
			var fullAddress = streetParts.join(', ');

			// 1. Set main address field (required)
			setFieldValue(addressFieldName, fullAddress || data.addressLine1 || '', fallbackAddressName);

			// 2. Address line 2 - optional (apartment, suite); only if not already in main
			setFieldValue(addressLine2FieldName, '', fallbackAddressLine2Name);

			// 3. Set company
			setFieldValue(companyFieldName, data.company || '', fallbackCompanyName);

			// 4. Set city
			var citySet = setFieldValue(cityFieldName, data.city || '', fallbackCityName);
			
			// Special: If city field not found, append city to address if not already there
			if (!citySet && data.city) {
				var $addrField = $('[name="' + addressFieldName + '"], #' + addressFieldName);
				if ($addrField.length === 0 && fallbackAddressName) {
					$addrField = $('[name="' + fallbackAddressName + '"], #' + fallbackAddressName);
				}
				
				if ($addrField.length > 0) {
					var currentAddr = $addrField.val();
					if (currentAddr && currentAddr.indexOf(data.city) === -1) {
						$addrField.val(currentAddr + ', ' + data.city);
						
					}
				}
			}
			
			// 5. Update postcode field
			if (data.postcode) {
				setFieldValue(postcodeFieldName, data.postcode);
				
				var $displayField = isCollection 
					? $('.ocb-form-section:contains("Collection") .ocb-postcode-display') 
					: $('.ocb-form-section:contains("Delivery") .ocb-postcode-display');
				
				if ($displayField.length === 0) {
					var $container = isCollection ? $('.ocb-postcode-container').first() : $('.ocb-postcode-container').last();
					$displayField = $container.find('.ocb-postcode-display');
				}

				if ($displayField.length > 0) {
					$displayField.val(data.postcode);
				}
			}
			
			// Highlight updated fields
			var highlightField = function(name, fallbackName) {
				var $field = $('[name="' + name + '"], #' + name);
				if ($field.length === 0 && fallbackName) {
					$field = $('[name="' + fallbackName + '"], #' + fallbackName);
				}
				if ($field.length > 0) {
					$field.addClass('ocb-field-updated');
					setTimeout(function() { $field.removeClass('ocb-field-updated'); }, 2000);
				}
			};

			highlightField(addressFieldName, fallbackAddressName);
			highlightField(addressLine2FieldName, fallbackAddressLine2Name);
			highlightField(companyFieldName, fallbackCompanyName);
			highlightField(cityFieldName, fallbackCityName);
			highlightField(postcodeFieldName);
		},

		parseAddressComponents: function(fullAddress) {
			/**
			 * Parse address string into intelligent components
			 * Extracts: street address, sub-building, city, postcode, company
			 * Strict parsing to avoid duplication in fields
			 */
			var parts = fullAddress.split(',').map(function(p) { return p.trim(); }).filter(function(p) { return p.length > 0; });
			
			var result = {
				addressLine1: '',
				addressLine2: '',
				company: '',
				city: '',
				postcode: ''
			};

			if (parts.length === 0) {
				return result;
			}

			// 1. Remove "United Kingdom", "UK", etc. from the end
			var lastPart = parts[parts.length - 1];
			if (/^(UK|United Kingdom|Great Britain|GB)$/i.test(lastPart)) {
				parts.pop();
			}

			if (parts.length === 0) return result;

			// 2. Extract postcode from end (must be UK postcode format)
			lastPart = parts[parts.length - 1];
			if (this.isUKPostcode(lastPart)) {
				result.postcode = lastPart;
				parts.pop();
			}

			// 3. Extract city (if we have remaining parts)
			if (parts.length > 0) {
				var potentialCity = parts[parts.length - 1];
				
				// Check against common UK cities
				var commonCities = [
					'London', 'Manchester', 'Birmingham', 'Leeds', 'Glasgow', 'Liverpool', 'Newcastle',
					'Sheffield', 'Bristol', 'Edinburgh', 'Cardiff', 'Belfast', 'Leicester', 'Coventry',
					'Nottingham', 'Bradford', 'Milton Keynes', 'Wolverhampton', 'Stoke', 'Derby',
					'Plymouth', 'Swindon', 'Slough', 'Blackpool', 'Gateshead'
				];
				
				// Case-insensitive city match
				var isCityName = commonCities.some(function(city) { 
					return city.toLowerCase() === potentialCity.toLowerCase(); 
				});

				if (isCityName && parts.length >= 2) {
					result.city = potentialCity;
					parts.pop();
				}
			}

			// 4. Remaining parts: Company and Street Address
			var companyKeywords = /\b(council|ltd|limited|company|corp|corporation|authority|centre|center|house|building|hospital|museum|school|university|bank|office|clinic|institute|foundation|society|association|trust|studios|studio|group|partners|partnership|services|service|systems|enterprises|network|travelodge|premier|travel|lodge|hotel|inn|motel|inn|lodge)\b/i;

			if (parts.length >= 2) {
				// Two or more parts remaining: check if first is company
				var firstPart = parts[0];
				var hasNumbers = /\d/.test(firstPart);
				var looksLikeCompany = companyKeywords.test(firstPart);

				if (looksLikeCompany && !hasNumbers) {
					// First part is likely company name
					result.company = firstPart;
					result.addressLine1 = parts.slice(1).join(', ');
				} else {
					// First part is address
					result.addressLine1 = firstPart;
					// No duplication: only use remaining parts if not company-like
					if (parts.length === 2) {
						result.addressLine2 = parts[1];
					} else if (parts.length > 2) {
						// Multiple remaining - first is line2, rest is company
						result.addressLine2 = parts[1];
						// Check if remaining looks like company
						var potential_company = parts.slice(2).join(', ');
						if (companyKeywords.test(potential_company)) {
							result.company = potential_company;
						} else {
							// If not company-like, append to address
							result.addressLine2 = parts.slice(1).join(', ');
						}
					}
				}
			} else if (parts.length === 1) {
				// Only one part left
				var onlyPart = parts[0];
				var hasNumbers = /\d/.test(onlyPart);
				var looksLikeCompany = companyKeywords.test(onlyPart) && !hasNumbers;

				if (looksLikeCompany) {
					result.company = onlyPart;
				} else {
					result.addressLine1 = onlyPart;
				}
			}

			// Safety: If addressLine1 is still empty, use city as fallback
			if (!result.addressLine1 && result.city) {
				result.addressLine1 = result.city;
				result.city = ''; // Don't duplicate
			}

			// Ensure no field has null/undefined values
			result.addressLine1 = (result.addressLine1 || '').trim();
			result.addressLine2 = (result.addressLine2 || '').trim();
			result.city = (result.city || '').trim();
			result.postcode = (result.postcode || '').trim();
			result.company = (result.company || '').trim();

			return result;
		},

		isUKPostcode: function(postcode) {
			postcode = postcode.toUpperCase().replace(/\s/g, '');
			var pattern = /^[A-Z]{1,2}[0-9][0-9A-Z]?[0-9][A-Z]{2}$/;
			return pattern.test(postcode);
		},

		showAddressError: function(message) {
			$('#ocb-address-loading').addClass('hidden');
			$('#ocb-address-error').html(message).show();
		},

		closeModal: function(e) {
			if (e) e.preventDefault();
			
			// Reset modal state
			$('#ocb-address-modal').addClass('hidden');
			this.isModalOpen = false;
			$('#ocb-address-tbody').html('');
			$('#ocb-address-pagination').html('');
			$('#ocb-address-error').hide().html('');
			$('#ocb-address-loading').removeClass('hidden');
			this.selectedAddressIndex = -1;
			this.addressesData = [];
			this.currentPostcode = '';
			this.fullPostcode = '';
			this.postcodeOutcode = '';
			this.currentLocation = '';
			this.currentPostcodeField = '';
			this.currentAddressField = '';
			this.currentPage = 1;
			this.totalPages = 1;
		},

		escapeHtml: function(text) {
			if (!text) return '';
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	// Initialize on document ready - with fallback
	if (document.readyState === 'loading') {
		$(document).ready(function() {
			AddressLookup.init();
		});
	} else {
		// DOM is already loaded
		AddressLookup.init();
	}

	// Also initialize on jQuery ready as backup
	$(function() {
		if (typeof AddressLookup !== 'undefined' && AddressLookup.addressesData !== undefined) {
		} else {
			AddressLookup.init();
		}
	});

})(jQuery);
