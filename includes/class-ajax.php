<?php
/**
 * AJAX Handler for OnRoute Courier Booking
 * Handles AJAX requests including secure API key access
 *
 * @package OnRoute_Courier_Booking
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class OCB_Ajax {
    
    /**
     * Initialize AJAX hooks
     */
    public function __construct() {
        // Public AJAX endpoint for getting API key (nonce-protected)
        add_action('wp_ajax_ocb_get_api_key', array($this, 'get_api_key'));
        add_action('wp_ajax_nopriv_ocb_get_api_key', array($this, 'get_api_key'));
        
        // Other AJAX endpoints
        // CONFLICT FIX: Removed calculate_price hook as it conflicts with OnRoute_Courier_Booking_Loader::ajax_calculate_price
        // add_action('wp_ajax_ocb_calculate_price', array($this, 'calculate_price'));
        // add_action('wp_ajax_nopriv_ocb_calculate_price', array($this, 'calculate_price'));
        
        add_action('wp_ajax_ocb_geocode_postcode', array($this, 'geocode_postcode'));
        add_action('wp_ajax_nopriv_ocb_geocode_postcode', array($this, 'geocode_postcode'));
        
        // Google Places API endpoint
        add_action('wp_ajax_ocb_search_places', array($this, 'search_places'));
        add_action('wp_ajax_nopriv_ocb_search_places', array($this, 'search_places'));

        // Google Place Details endpoint
        add_action('wp_ajax_ocb_get_place_details', array($this, 'get_place_details'));
        add_action('wp_ajax_nopriv_ocb_get_place_details', array($this, 'get_place_details'));

        // Ideal Postcodes UK address lookup (preferred)
        add_action('wp_ajax_ocb_search_idealpostcodes', array($this, 'search_idealpostcodes'));
        add_action('wp_ajax_nopriv_ocb_search_idealpostcodes', array($this, 'search_idealpostcodes'));

        // GetAddress.io UK address lookup (alternative)
        add_action('wp_ajax_ocb_search_getaddress', array($this, 'search_getaddress'));
        add_action('wp_ajax_nopriv_ocb_search_getaddress', array($this, 'search_getaddress'));

        add_action('wp_ajax_ocb_get_getaddress_details', array($this, 'get_getaddress_details'));
        add_action('wp_ajax_nopriv_ocb_get_getaddress_details', array($this, 'get_getaddress_details'));
    }
    
    /**
     * Get Google Maps API key securely
     * Protected by nonce verification
     */
    public function get_api_key() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ocb_api_nonce')) {
            wp_send_json_error(array(
                'message' => 'Security check failed'
            ));
            return;
        }
        
        // Get the API key from WordPress constant
        $api_key = defined('OCB_GOOGLE_MAPS_API_KEY') ? OCB_GOOGLE_MAPS_API_KEY : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => 'API key not configured'
            ));
            return;
        }
        
        wp_send_json_success(array(
            'api_key' => $api_key
        ));
    }
    
    /**
     * Calculate booking price
     */
    public function calculate_price() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ocb_price_nonce')) {
            wp_send_json_error(array(
                'message' => 'Security check failed'
            ));
            return;
        }
        
        $postcode = sanitize_text_field($_POST['postcode'] ?? '');
        $vehicle_type = sanitize_text_field($_POST['vehicle_type'] ?? '');
        $service_type = sanitize_text_field($_POST['service_type'] ?? '');
        
        // Use pricing class to calculate
        $pricing = new OCB_Pricing();
        $price = $pricing->calculate_price($postcode, $vehicle_type, $service_type);
        
        wp_send_json_success(array(
            'price' => $price,
            'formatted' => '£' . number_format($price, 2)
        ));
    }
    
    /**
     * Geocode postcode using Google Maps API
     */
    public function geocode_postcode() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ocb_geocode_nonce')) {
            wp_send_json_error(array(
                'message' => 'Security check failed'
            ));
            return;
        }
        
        $postcode = sanitize_text_field($_POST['postcode'] ?? '');
        
        if (empty($postcode)) {
            wp_send_json_error(array(
                'message' => 'Postcode is required'
            ));
            return;
        }
        
        // Get API key
        $api_key = defined('OCB_GOOGLE_MAPS_API_KEY') ? OCB_GOOGLE_MAPS_API_KEY : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => 'API key not configured'
            ));
            return;
        }
        
        // Make request to Google Geocoding API
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?address=%s&components=country:GB&key=%s',
            urlencode($postcode),
            $api_key
        );
        
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Failed to geocode postcode'
            ));
            return;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body['status'] !== 'OK' || empty($body['results'])) {
            wp_send_json_error(array(
                'message' => 'Invalid postcode'
            ));
            return;
        }
        
        $result = $body['results'][0];
        
        wp_send_json_success(array(
            'formatted_address' => $result['formatted_address'],
            'latitude' => $result['geometry']['location']['lat'],
            'longitude' => $result['geometry']['location']['lng'],
            'place_id' => $result['place_id']
        ));
    }
    
    /**
     * Search for places using Google Places API
     * Called from frontend when user selects address by postcode
     */
    public function search_places() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ocb_api_nonce' ) ) {
            wp_send_json_error( array(
                'message' => 'Security check failed'
            ) );
            return;
        }
        
        $postcode = sanitize_text_field( $_POST['postcode'] ?? '' );
        $lat = isset($_POST['lat']) ? sanitize_text_field($_POST['lat']) : '';
        $lon = isset($_POST['lon']) ? sanitize_text_field($_POST['lon']) : '';
        
        if ( empty( $postcode ) ) {
            wp_send_json_error( array(
                'message' => 'Postcode is required'
            ) );
            return;
        }
        
        // Use Google Places API class
        // Make sure it's loaded to avoid fatal if something went wrong with includes
        if ( ! class_exists( 'OnRoute_Courier_Booking_Places_API' ) ) {
             wp_send_json_error( array( 'message' => 'API handler missing' ) );
             return;
        }

        $result = OnRoute_Courier_Booking_Places_API::search_places( $postcode, 'GB', $lat, $lon );
        
        if ( ! is_array( $result ) || ! isset( $result['success'] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid API response format' ) );
            return;
        }

        if ( ! $result['success'] ) {
            wp_send_json_error( array(
                'message' => $result['message'],
                'data'    => $result['data'] ?? array()
            ) );
            return;
        }
        
        // Format and return results
        $places = array();
        if ( isset( $result['data'] ) && is_array( $result['data'] ) ) {
            foreach ( $result['data'] as $place ) {
                if ( ! is_array( $place ) ) continue;
                
                $places[] = array(
                    'id'        => $place['id'] ?? '',
                    'name'      => $place['name'] ?? '',
                    'formatted' => $place['formatted'] ?? '',
                    'lat'       => $place['lat'] ?? null,
                    'lon'       => $place['lon'] ?? null,
                    'postcode'  => $place['postcode'] ?? $postcode,
                    'types'     => $place['types'] ?? array()
                );
            }
        }
        
        wp_send_json_success( $places );
    }

    /**
     * Search UK addresses via Ideal Postcodes (street addresses like reference site)
     */
    public function search_idealpostcodes() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ocb_api_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
            return;
        }
        $postcode = sanitize_text_field( $_POST['postcode'] ?? '' );
        $page = isset( $_POST['page'] ) ? max( 0, (int) $_POST['page'] ) : 0;
        if ( empty( $postcode ) ) {
            wp_send_json_error( array( 'message' => 'Postcode is required' ) );
            return;
        }
        $result = OCB_Ideal_Postcodes_API::search_by_postcode( $postcode, $page );
        if ( ! $result['success'] ) {
            wp_send_json_error( array( 'message' => $result['message'] ?? 'Lookup failed', 'data' => array() ) );
            return;
        }
        wp_send_json_success( $result['data'] );
    }

    /**
     * Search UK addresses via GetAddress.io (street addresses, not businesses)
     */
    public function search_getaddress() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ocb_api_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
            return;
        }
        $postcode = sanitize_text_field( $_POST['postcode'] ?? '' );
        if ( empty( $postcode ) ) {
            wp_send_json_error( array( 'message' => 'Postcode is required' ) );
            return;
        }
        $result = OCB_GetAddress_API::search_by_postcode( $postcode );
        if ( ! $result['success'] ) {
            wp_send_json_error( array( 'message' => $result['message'] ?? 'Lookup failed', 'data' => array() ) );
            return;
        }
        wp_send_json_success( $result['data'] );
    }

    /**
     * Get GetAddress.io address details for form population
     */
    public function get_getaddress_details() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ocb_api_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
            return;
        }
        $id = sanitize_text_field( $_POST['getaddress_id'] ?? '' );
        if ( empty( $id ) ) {
            wp_send_json_error( array( 'message' => 'Address ID required' ) );
            return;
        }
        $result = OCB_GetAddress_API::get_address_details( $id );
        if ( ! $result['success'] ) {
            wp_send_json_error( array( 'message' => $result['message'] ?? 'Not found' ) );
        } else {
            wp_send_json_success( $result['data'] );
        }
    }

    /**
     * Get Place Details (DESTINATION)
     */
    public function get_place_details() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ocb_api_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
            return;
        }

        $place_id = sanitize_text_field( $_POST['place_id'] ?? '' );
        
        if ( empty( $place_id ) ) {
            wp_send_json_error( array( 'message' => 'Place ID is required' ) );
            return;
        }

        $result = OnRoute_Courier_Booking_Places_API::get_place_details( $place_id );
        
        if ( ! $result['success'] ) {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        } else {
            wp_send_json_success( $result['data'] );
        }
    }
}

// Initialize AJAX handler
new OCB_Ajax();
