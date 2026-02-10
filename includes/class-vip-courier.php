<?php
/**
 * VIP Courier Form Handler
 * 
 * Provides a shortcode [vip_courier_form] to embed the enquiry form anywhere.
 * Handles form submissions and database insertion.
 * 
 * @package OnRoute_Courier_Booking
 */

if ( !defined('ABSPATH')) {
    exit;
}

class OnRoute_VIP_Courier {

    /**
     * Constructor
     */
    public function __construct() {
        // Form shortcode
        add_shortcode('vip_courier_form', array($this, 'render_form'));
        
        // Hook early to catch form submissions
        add_action('init', array($this, 'handle_form_submission'));
        
        // AJAX handlers
        add_action('wp_ajax_nopriv_vip_submit_enquiry', array($this, 'ajax_submit_enquiry'));
        add_action('wp_ajax_vip_submit_enquiry', array($this, 'ajax_submit_enquiry'));
        
        // Enqueue fonts and scripts for the form styling
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Handle Form Submission
     */
    public function handle_form_submission() {
        if ( isset($_POST['vip_action']) && $_POST['vip_action'] === 'submit_enquiry' ) {

            // Verify nonce
            if ( ! isset($_POST['vip_enquiry_nonce']) || ! wp_verify_nonce($_POST['vip_enquiry_nonce'], 'vip_courier_enquiry') ) {
                $this->redirect_with_status('error', 'Security check failed. Please refresh and try again.');
                return; // Note: redirect_with_status exits
            }

            // Honeypot Check (Bot Protection)
            if ( ! empty( $_POST['website_url'] ) ) {
                return; // Silently fail for bots
            }

            // Sanitize input
            $name = sanitize_text_field($_POST['full_name'] ?? '');
            $email = sanitize_email($_POST['contact_email'] ?? '');
            $phone = sanitize_text_field($_POST['contact_phone'] ?? ''); 
            $details = sanitize_textarea_field($_POST['delivery_details'] ?? '');
            $privacy_accepted = isset($_POST['privacy_check']) ? 1 : 0;

            // Validation
            if ( empty($name) || empty($email) || empty($details) ) {
                $this->redirect_with_status('error', 'Please fill in all required fields.');
                return;
            }

            if ( ! is_email($email) ) {
                $this->redirect_with_status('error', 'Please enter a valid email address.');
                return;
            }

            if ( ! $privacy_accepted ) {
                $this->redirect_with_status('error', 'Please accept the Security Vetting Terms.');
                return;
            }

            // Save to database
            global $wpdb;
            $table = $wpdb->prefix . 'ocb_bookings';

            // Check if table exists
            if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                 // Fallback if table doesn't exist - email only
                 $this->send_emails($name, $email, $phone, $details);
                 $this->redirect_with_status('success', 'Your enquiry has been submitted. (Email fallback)');
                 return;
            }

            // Generate Reference
            $reference = 'VIP-' . strtoupper(substr(md5(uniqid()), 0, 8));

            $data = array(
                'booking_reference' => $reference,
                'customer_name'     => $name,
                'customer_email'    => $email,
                'customer_phone'    => $phone ?: 'Not Provided',
                'pickup_address'    => 'VIP Enquiry - See Notes',
                'pickup_postcode'   => 'VIP',
                'delivery_postcode' => 'VIP',
                'delivery_address'  => 'VIP Enquiry - See Notes',
                'collection_date'   => current_time('Y-m-d'),
                'collection_time'   => '09:00:00',
                'delivery_date'     => current_time('Y-m-d'),
                'delivery_time'     => '17:00:00',
                'vehicle_id'        => 'vip_white_glove',
                'service_id'        => 'vip_secure',
                'base_price'        => 0.00,
                'vat_amount'        => 0.00,
                'total_price'       => 0.00,
                'notes'             => "VIP White Glove Service Enquiry:\n\nPhone: $phone\n\nDetails: " . $details,
                'status'            => 'pending',
                'payment_status'    => 'unpaid',
                'created_at'        => current_time('mysql'),
            );

            $result = $wpdb->insert($table, $data);

            if ( $result === false ) {
                error_log('VIP Courier DB Error: ' . $wpdb->last_error);
                $this->redirect_with_status('error', 'System error. Please contact support.');
                return;
            }

            // Send Emails
            $this->send_emails($name, $email, $phone, $details);

            // Redirect on Success
            $this->redirect_with_status('success', 'Your enquiry has been submitted successfully.');
        }
    }

    /**
     * Helper to Redirect
     */
    private function redirect_with_status($status, $message) {
        set_transient('vip_form_message', $message, 60);
        // Redirect back to the page where the form was submitted
        $url = add_query_arg('vip_status', $status, wp_get_referer() ? wp_get_referer() : home_url());
        wp_safe_redirect($url . '#vip-enquiry-form');
        exit;
    }

    /**
     * Helper to Send Emails
     */
    private function send_emails($name, $email, $phone, $details) {
        // Send confirmation email to admin
        $admin_email = get_option('admin_email');
        $subject = 'VIP Enquiry: ' . $name;
        
        $message  = "<h3>New VIP White Glove Enquiry</h3>";
        $message .= "<p><strong>Name:</strong> " . esc_html($name) . "</p>";
        $message .= "<p><strong>Email:</strong> " . esc_html($email) . "</p>";
        $message .= "<p><strong>Phone:</strong> " . esc_html($phone) . "</p>";
        $message .= "<p><strong>Details:</strong><br>" . nl2br(esc_html($details)) . "</p>";
        $message .= "<p><a href='" . admin_url('admin.php?page=ocb-bookings') . "'>View in Dashboard</a></p>";

        if ( class_exists( 'OnRoute_Courier_Booking_Emails' ) ) {
            OnRoute_Courier_Booking_Emails::send_html_mail($admin_email, $subject, $message);
        } else {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($admin_email, $subject, $message, $headers);
        }

        // Send confirmation to client
        $client_subject = 'Receipt of VIP Enquiry - OnRoute Couriers';
        $client_message  = "<p>Dear " . esc_html($name) . ",</p>";
        $client_message .= "<p>We have received your enquiry for our VIP White Glove service.</p>";
        $client_message .= "<p>A security consultant will review your requirements and contact you shortly.</p>";
        $client_message .= "<p>Kind regards,<br><strong>OnRoute VIP Team</strong></p>";

        if ( class_exists( 'OnRoute_Courier_Booking_Emails' ) ) {
            OnRoute_Courier_Booking_Emails::send_html_mail($email, $client_subject, $client_message);
        } else {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($email, $client_subject, $client_message, $headers);
        }
    }
    
    /**
     * AJAX Handler for Form Submission
     */
    public function ajax_submit_enquiry() {
        // Verify nonce
        if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'vip_courier_enquiry') ) {
            wp_send_json_error(array('message' => 'Security check failed. Please refresh and try again.'));
            die();
        }

        // Honeypot Check
        if ( ! empty($_POST['website_url']) ) {
            wp_send_json_error(array('message' => 'Invalid submission.'));
            die();
        }

        // Sanitize input
        $name = sanitize_text_field($_POST['full_name'] ?? '');
        $email = sanitize_email($_POST['contact_email'] ?? '');
        $phone = sanitize_text_field($_POST['contact_phone'] ?? '');
        $details = sanitize_textarea_field($_POST['delivery_details'] ?? '');
        $privacy_accepted = isset($_POST['privacy_check']) ? 1 : 0;

        // Validation
        if ( empty($name) || empty($email) || empty($details) ) {
            wp_send_json_error(array('message' => 'Please fill in all required fields.'));
            die();
        }

        if ( ! is_email($email) ) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
            die();
        }

        if ( ! $privacy_accepted ) {
            wp_send_json_error(array('message' => 'Please accept the Security Vetting Terms.'));
            die();
        }

        // Save to database
        global $wpdb;
        $table = $wpdb->prefix . 'ocb_bookings';

        // Generate Reference
        $reference = 'VIP-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $data = array(
            'booking_reference' => $reference,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone ?: 'Not Provided',
            'pickup_address' => 'VIP Enquiry - See Notes',
            'pickup_postcode' => 'VIP',
            'delivery_postcode' => 'VIP',
            'delivery_address' => 'VIP Enquiry - See Notes',
            'collection_date' => current_time('Y-m-d'),
            'collection_time' => '09:00:00',
            'delivery_date' => current_time('Y-m-d'),
            'delivery_time' => '17:00:00',
            'vehicle_id' => 'vip_white_glove',
            'service_id' => 'vip_secure',
            'base_price' => 0.00,
            'vat_amount' => 0.00,
            'total_price' => 0.00,
            'notes' => "VIP White Glove Service Enquiry:\n\nPhone: $phone\n\nDetails: " . $details,
            'status' => 'booked',
            'payment_status' => 'unpaid',
            'created_at' => current_time('mysql'),
        );

        // Check if table exists before inserting
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table ) {
            $result = $wpdb->insert($table, $data);
            if ( $result === false ) {
                error_log('VIP Courier DB Error: ' . $wpdb->last_error);
                // Continue even if DB fails - send email as backup
            }
        }

        // Send Emails
        $this->send_emails($name, $email, $phone, $details);

        // Success response
        wp_send_json_success(array(
            'reference' => $reference,
            'message' => 'Your enquiry has been submitted successfully.'
        ));
        die();
    }

    /**
     * Enqueue Assets (Fonts & Scripts)
     */
    public function enqueue_assets() {
        wp_enqueue_style('vip-fonts-playfair', 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Manrope:wght@300;400;600&display=swap');
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
        
        // Enqueue VIP form script
        wp_enqueue_script('vip-courier-form', ONROUTE_COURIER_BOOKING_URL . 'assets/vip-form.js', array('jquery'), '1.3.0', true);
        
        // Localize script with nonce and AJAX URL
        wp_localize_script('vip-courier-form', 'vipFormData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vip_courier_enquiry')
        ));
    }

    /**
     * Render VIP Courier Form (Shortcode)
     */
    public function render_form($atts) {
        ob_start();

        // Check for Status Messages
        $form_message = '';
        $message_type = '';

        if ( isset($_GET['vip_status']) ) {
            $form_message = get_transient('vip_form_message');
            $message_type = $_GET['vip_status']; // success or error
            delete_transient('vip_form_message');
            
            if ( ! $form_message ) {
                 if( $message_type === 'success' ) $form_message = 'Submission successful.';
                 if( $message_type === 'error' ) $form_message = 'An error occurred.';
            }
        }

        ?>
        <div id="vip-enquiry-form" class="vip-form-wrapper">

            <?php if ( $form_message ) : ?>
                <div class="vip-notice-<?php echo esc_attr($message_type); ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; background: <?php echo $message_type === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $message_type === 'success' ? '#155724' : '#721c24'; ?>;">
                    <?php echo esc_html($form_message); ?>
                </div>
            <?php endif; ?>

            <!-- Success Overlay -->
            <div id="vip-success-overlay" class="vip-success-overlay">
                <div class="vip-success-content">
                    <div class="vip-success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 class="vip-success-title">Enquiry Received</h2>
                    <p class="vip-success-subtitle">Thank you for your confidence in our service</p>
                    <div class="vip-success-message">
                        <p><i class="fas fa-info-circle" style="color: #C5A059; margin-right: 8px;"></i>Your VIP White Glove enquiry has been submitted successfully.</p>
                        <p style="margin-top: 12px; margin-bottom: 0;"><i class="fas fa-users" style="color: #C5A059; margin-right: 8px;"></i>Our dedicated team will contact you shortly at <span id="vip-success-email" style="color: #C5A059; font-weight: 600;"></span></p>
                    </div>
                    <div class="vip-success-ref">
                        Reference: <span id="vip-success-ref">—</span>
                    </div>
                    <p style="font-size: 12px; color: #666; margin-bottom: 25px;">Please check your email for a confirmation and next steps</p>
                    <button type="button" class="vip-success-close-btn" onclick="document.getElementById('vip-success-overlay').classList.remove('show');">Dismiss</button>
                </div>
            </div>
            
            <style>
                /* VIP Form Styles */
                .vip-form-wrapper {
                    max-width: 100%;
                    width: 100%;
                    margin: 0 auto;
                    font-family: 'Manrope', sans-serif;
                    box-sizing: border-box;
                    text-align: center;
                }
                
                .vip-card {
                     background-color: #0a0a0a;
                     border: 1px solid #333;
                     border-radius: 8px;
                     padding: 24px; /* Further reduced */
                     box-shadow: 0 10px 30px rgba(0,0,0,0.5);
                     color: #f0f0f0;
                     position: relative;
                     overflow: hidden;
                     transition: all 0.3s ease;
                     margin: 0 auto;
                }

                .vip-card.form-disabled {
                    opacity: 0.6;
                    pointer-events: none;
                }
                
                /* Gold Accent Line */
                .vip-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 2px; /* Slight reduction for aesthetics */
                    background: linear-gradient(90deg, #9F111B, #C5A059, #9F111B);
                }

                .vip-form-title {
                    font-family: 'Playfair Display', serif;
                    font-size: 24px; /* Reduced from 32px */
                    color: #fff;
                    margin-bottom: 8px; /* Further reduced */
                    text-align: center;
                    letter-spacing: 0.05em;
                }
                
                .vip-form-subtitle {
                    text-align: center;
                    color: #888;
                    margin-bottom: 18px; /* Further reduced */
                    font-size: 12px; /* Slightly smaller */
                    text-transform: uppercase;
                    letter-spacing: 0.15em;
                }

                .vip-form-msg {
                    padding: 10px;
                    margin-bottom: 15px;
                    border-radius: 4px;
                    font-size: 13px;
                    text-align: center;
                }
                .vip-form-success {
                    background-color: rgba(197, 160, 89, 0.2);
                    color: #C5A059;
                    border: 1px solid #C5A059;
                }
                .vip-form-error {
                    background-color: rgba(159, 17, 27, 0.2);
                    color: #ff6b6b;
                    border: 1px solid #9F111B;
                }

                .vip-form-group {
                    margin-bottom: 12px; /* Further reduced from 15px */
                    text-align: left;
                }

                .vip-form-group label {
                    display: block;
                    margin-bottom: 4px; /* Further reduced */
                    font-weight: 600;
                    font-size: 11px; /* Further reduced */
                    color: #aaa;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .vip-input-field {
                    width: 100%;
                    padding: 9px 11px; /* Further reduced */
                    background-color: rgba(255, 255, 255, 0.05);
                    border: 1px solid #333;
                    border-radius: 4px;
                    color: #fff;
                    font-size: 13px; /* Further reduced from 14px */
                    transition: all 0.3s ease;
                    box-sizing: border-box; /* Ensure padding doesn't affect width */
                }

                .vip-input-field:focus {
                    outline: none;
                    border-color: #C5A059;
                    background-color: rgba(255, 255, 255, 0.08);
                    box-shadow: 0 0 0 1px #C5A059;
                }
                
                .vip-input-field::placeholder {
                    color: #555;
                }
                
                textarea.vip-input-field {
                    resize: vertical;
                    min-height: 80px; /* Reduced from default */
                }

                .vip-checkbox-label {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-size: 11px; /* Reduced from 12px */
                    color: #888;
                    cursor: pointer;
                    margin-top: 6px; /* Reduced */
                    text-align: left;
                }
                
                .vip-checkbox-label input[type="checkbox"] {
                    accent-color: #C5A059;
                    width: 14px; /* Reduced */
                    height: 14px;
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    appearance: none;
                    background-color: rgba(255, 255, 255, 0.05);
                    border: 1px solid #333;
                    border-radius: 3px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                
                .vip-checkbox-label input[type="checkbox"]:checked {
                    background-color: #C5A059;
                    border-color: #C5A059;
                }
                
                .vip-checkbox-label input[type="checkbox"]:focus {
                    outline: none;
                    box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.3);
                }

                .vip-submit-btn {
                    margin-top: 16px; /* Further reduced from 20px */
                    width: 100%;
                    padding: 11px; /* Further reduced from 12px */
                    background: linear-gradient(135deg, #C5A059 0%, #E8D5C0 50%, #C5A059 100%);
                    color: #0a0a0a;
                    border: none;
                    border-radius: 4px;
                    font-family: 'Manrope', sans-serif;
                    font-weight: 700;
                    font-size: 12px; /* Further reduced from 13px */
                    text-transform: uppercase;
                    letter-spacing: 0.15em;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 15px rgba(197, 160, 89, 0.3);
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    appearance: none;
                    -webkit-tap-highlight-color: transparent;
                }

                .vip-submit-btn:hover {
                    background: linear-gradient(135deg, #E8D5C0 0%, #C5A059 100%);
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(197, 160, 89, 0.4);
                    color: #0a0a0a !important;
                }
                
                .vip-submit-btn:active,
                .vip-submit-btn:focus {
                    outline: none !important;
                    box-shadow: none !important;
                    background: #C5A059 !important;
                    color: #0a0a0a !important;
                    border: none !important;
                    transform: translateY(0) !important;
                    -webkit-appearance: none !important;
                    box-shadow: 0 0 0 4px rgba(197, 160, 89, 0.2) !important;
                }
                
                .vip-submit-btn::-moz-focus-inner {
                    border: none !important;
                    padding: 0 !important;
                }

                /* ===== SUCCESS STATE ANIMATION ===== */
                .vip-success-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.85);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    opacity: 0;
                    visibility: hidden;
                    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
                }

                .vip-success-overlay.show {
                    opacity: 1;
                    visibility: visible;
                }

                .vip-success-content {
                    background: linear-gradient(135deg, rgba(10, 10, 10, 0.98) 0%, rgba(20, 15, 20, 0.98) 100%);
                    border: 1px solid #C5A059;
                    border-radius: 12px;
                    padding: 55px 45px;
                    text-align: center;
                    max-width: 500px;
                    box-shadow: 0 20px 60px rgba(197, 160, 89, 0.25);
                    transform: scale(0.8);
                    opacity: 0;
                    transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.2s;
                }

                .vip-success-overlay.show .vip-success-content {
                    transform: scale(1);
                    opacity: 1;
                }

                .vip-success-icon {
                    width: 80px;
                    height: 80px;
                    margin: 0 auto 25px;
                    background: linear-gradient(135deg, #C5A059 0%, #E8D5C0 50%, #C5A059 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 10px 30px rgba(197, 160, 89, 0.3);
                    animation: successPulse 2s ease-in-out infinite;
                }

                @keyframes successPulse {
                    0%, 100% { box-shadow: 0 10px 30px rgba(197, 160, 89, 0.3); transform: scale(1); }
                    50% { box-shadow: 0 10px 40px rgba(197, 160, 89, 0.5); transform: scale(1.05); }
                }

                .vip-success-icon svg,
                .vip-success-icon i {
                    color: #0a0a0a;
                    font-size: 40px;
                    width: 40px;
                    height: 40px;
                }

                .vip-success-title {
                    font-family: 'Playfair Display', serif;
                    font-size: 32px;
                    color: #C5A059;
                    margin-bottom: 12px;
                    letter-spacing: 0.05em;
                    font-weight: 700;
                }

                .vip-success-subtitle {
                    font-size: 16px;
                    color: #fff;
                    margin-bottom: 20px;
                    font-weight: 500;
                    letter-spacing: 0.02em;
                }

                .vip-success-message {
                    font-size: 14px;
                    color: #aaa;
                    line-height: 1.8;
                    margin-bottom: 30px;
                    padding: 20px;
                    background: rgba(197, 160, 89, 0.08);
                    border-left: 3px solid #C5A059;
                    border-radius: 4px;
                    text-align: left;
                }

                .vip-success-ref {
                    margin: 15px 0;
                    padding: 12px;
                    background: rgba(255, 255, 255, 0.02);
                    border: 1px solid #333;
                    border-radius: 4px;
                    font-family: 'Courier New', monospace;
                    color: #C5A059;
                    font-weight: 600;
                    font-size: 14px;
                    letter-spacing: 0.15em;
                }

                .vip-success-close-btn {
                    background: transparent;
                    border: 2px solid #C5A059;
                    color: #C5A059;
                    padding: 12px 35px;
                    border-radius: 4px;
                    font-family: 'Manrope', sans-serif;
                    font-weight: 600;
                    font-size: 13px;
                    text-transform: uppercase;
                    letter-spacing: 0.1em;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    inline-size: auto;
                }

                .vip-success-close-btn:hover {
                    background: #C5A059;
                    color: #0a0a0a;
                    box-shadow: 0 5px 15px rgba(197, 160, 89, 0.3);
                    transform: translateY(-2px);
                }

                .vip-form-disabled {
                    opacity: 0.5;
                    pointer-events: none;
                }

                /* Loading state */
                .vip-submit-btn.loading {
                    opacity: 0.7;
                    pointer-events: none;
                }

                .vip-submit-btn.loading::after {
                    content: '';
                    display: inline-block;
                    width: 14px;
                    height: 14px;
                    margin-left: 8px;
                    border: 2px solid rgba(255, 255, 255, 0.3);
                    border-top-color: #fff;
                    border-radius: 50%;
                    animation: spin 0.8s linear infinite;
                    vertical-align: middle;
                }

                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
                
                /* Mobile Responsiveness */
                @media (max-width: 600px) {
                    .vip-card {
                        padding: 18px 15px;
                    }
                    .vip-form-title {
                        font-size: 20px;
                    }
                    .vip-success-content {
                        max-width: 85%;
                        padding: 35px 25px;
                    }
                    .vip-success-title {
                        font-size: 24px;
                    }
                }
            </style>

            <div class="vip-card" id="vip-card">
                <h2 class="vip-form-title">VIP White Glove Enquiry</h2>
                <div class="vip-form-subtitle">Discreet. Secure. Exceptional.</div>

                    <form id="vip-enquiry-form-elem" method="POST" action="#">
                        <!-- Nonce is handled by AJAX via vipFormData.nonce -->
                        
                        <div style="display:none; visibility:hidden;">
                            <input type="text" name="website_url" value="" tabindex="-1" autocomplete="off"/>
                        </div>

                        <div class="vip-form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" required placeholder="Enter your full name" class="vip-input-field">
                        </div>

                        <div class="vip-form-group">
                            <label>Email Address</label>
                            <input type="email" name="contact_email" required placeholder="name@company.com" class="vip-input-field">
                        </div>

                        <div class="vip-form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="contact_phone" placeholder="+1 (555) 000-0000" class="vip-input-field">
                        </div>

                        <div class="vip-form-group">
                            <label>Delivery Requirements</label>
                            <textarea name="delivery_details" required rows="3" placeholder="Please describe your item and delivery timeline..." class="vip-input-field"></textarea>
                        </div>

                        <div class="vip-form-group">
                            <label class="vip-checkbox-label">
                                <input type="checkbox" name="privacy_check" required>
                                <span>I understand this service requires Security Vetting.</span>
                            </label>
                        </div>

                        <button type="submit" class="vip-submit-btn">Submit Secure Enquiry</button>
                        
                    </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}