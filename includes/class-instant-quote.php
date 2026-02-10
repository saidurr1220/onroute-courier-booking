<?php
/**
 * Instant Quote Shortcode
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Instant_Quote {

    public function __construct() {
        add_shortcode( 'ocb_instant_quote', array( $this, 'render_shortcode' ) );
    }

    public function render_shortcode() {
        $form_submitted = false;
        $form_message = '';

        if ( isset( $_POST['ocb_instant_quote_nonce'] ) && wp_verify_nonce( $_POST['ocb_instant_quote_nonce'], 'ocb_instant_quote_submit' ) ) {
            
            $pickup = sanitize_text_field( $_POST['pickup_postcode'] );
            $delivery = sanitize_text_field( $_POST['delivery_postcode'] );
            $email = sanitize_email( $_POST['email'] );
            $service = sanitize_text_field( $_POST['service_type'] ); // 'same_day', 'priority', 'direct'

            // Generate "quote_*" service ID to distinguish from real bookings
            $service_id = 'quote_' . $service;

            if ( ! is_email( $email ) ) {
                $form_message = 'Please enter a valid email address.';
            } else {
                // Save to DB
                global $wpdb;
                $table = OnRoute_Courier_Booking_Database::get_bookings_table();
                
                $ref = 'QUOTE-' . strtoupper( substr( md5( uniqid() ), 0, 8 ) );

                $wpdb->insert( $table, array(
                    'booking_reference' => $ref,
                    'customer_email' => $email,
                    'pickup_postcode' => $pickup,
                    'delivery_postcode' => $delivery,
                    'service_id' => $service_id,
                    'status' => 'booked',
                    'created_at' => current_time( 'mysql' ),
                    // Fill required schema fields with dummy/default data
                    'vehicle_id' => 'small_van', // Default
                    'base_price' => 0.00,
                    'vat_amount' => 0.00,
                    'total_price' => 0.00,
                    'pickup_address' => $pickup, // Fallback
                    'delivery_address' => $delivery, // Fallback
                    'customer_phone' => 'Not Provided',
                    'collection_date' => current_time( 'Y-m-d' ),
                    'collection_time' => '09:00:00',
                    'delivery_date' => current_time( 'Y-m-d' ),
                    'delivery_time' => '17:00:00',
                    'payment_status' => 'unpaid'
                ));

                if ( false === $wpdb->last_error || empty($wpdb->last_error) ) {
                    // Send email to admin
                    $admin_email = get_option( 'admin_email' );
                    $subject = "New Instant Quote Request ($ref)";
                    $message = "New Instant Quote Request:\n\n";
                    $message .= "Reference: $ref\n";
                    $message .= "Pickup: $pickup\n";
                    $message .= "Delivery: $delivery\n";
                    $message .= "Email: $email\n";
                    $message .= "Service: " . ucfirst( str_replace( '_', ' ', $service ) ) . "\n";
                    
                    wp_mail( $admin_email, $subject, $message );
    
                    $form_submitted = true;
                    $form_message = 'Quote request sent! We will contact you shortly.';
                } else {
                    $form_message = 'Error submitting request: ' . $wpdb->last_error;
                }
            }
        }

        ob_start();
        ?>
        <!-- Import Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
        
        <div class="ocb-instant-quote-wrapper">
            <style>
                .ocb-instant-quote-wrapper {
                    width: 100%;
                    max-width: 585px; /* User requested 585px */
                    min-height: 380px; /* User requested 380px height */
                    padding: 30px;
                    background: transparent;
                    border-radius: 8px;
                    box-shadow: none;
                    font-family: 'Poppins', sans-serif; /* Body font */
                    margin: 0 auto;
                    box-sizing: border-box;
                }
                .ocb-iq-title {
                    text-align: center;
                    color: #d32f2f;
                    font-family: 'Rajdhani', sans-serif; /* Heading font */
                    font-size: 38px; /* Bigger size */
                    margin-bottom: 25px;
                    font-weight: 700;
                    text-transform: uppercase;
                    line-height: 1.2;
                }
                .ocb-iq-field {
                    margin-bottom: 18px;
                }
                .ocb-iq-label {
                    display: block;
                    color: #d32f2f;
                    font-weight: 600;
                    margin-bottom: 8px;
                    font-size: 16px;
                }
                .ocb-iq-input {
                    width: 100%;
                    padding: 12px;
                    background: #fdf2f2;
                    border: 1px solid #d32f2f;
                    border-radius: 4px;
                    font-size: 15px;
                    font-family: 'Poppins', sans-serif;
                    box-sizing: border-box;
                }
                .ocb-iq-checkbox-group {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 12px;
                }
                .ocb-iq-checkbox-label {
                    display: flex;
                    align-items: center;
                    background: #d32f2f;
                    color: white;
                    padding: 10px 15px;
                    border-radius: 4px;
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    flex: 1;
                    justify-content: center;
                    white-space: nowrap;
                }
                .ocb-iq-checkbox-label input {
                    margin-right: 8px;
                }
                .ocb-iq-submit {
                    width: 120px;
                    background: #d32f2f;
                    color: white;
                    border: none;
                    padding: 12px;
                    font-size: 18px;
                    font-family: 'Rajdhani', sans-serif;
                    font-weight: 700;
                    text-transform: uppercase;
                    border-radius: 4px;
                    cursor: pointer;
                    margin-top: 15px;
                    transition: background 0.3s;
                }
                .ocb-iq-submit:hover {
                    background: #b71c1c;
                }

                /* Responsive Design */
                @media (max-width: 768px) {
                    .ocb-instant-quote-wrapper {
                        max-width: 90%;
                        padding: 25px;
                    }
                    .ocb-iq-title {
                        font-size: 32px;
                    }
                    .ocb-iq-checkbox-label {
                        font-size: 13px;
                        padding: 8px 10px;
                    }
                }

                @media (max-width: 480px) {
                    .ocb-instant-quote-wrapper {
                        max-width: 100%;
                        padding: 20px;
                        border-radius: 0;
                    }
                    .ocb-iq-title {
                        font-size: 28px;
                    }
                    .ocb-iq-checkbox-group {
                        flex-direction: column;
                        gap: 8px;
                    }
                    .ocb-iq-checkbox-label {
                        width: 100%;
                        justify-content: flex-start;
                    }
                    .ocb-iq-submit {
                        width: 100%;
                    }
                }

                .ocb-iq-message {
                    padding: 10px;
                    margin-bottom: 15px;
                    border-radius: 4px;
                    text-align: center;
                }
                .ocb-iq-success { background: #d4edda; color: #155724; }
                .ocb-iq-error { background: #f8d7da; color: #721c24; }
            </style>

            <?php if ( $form_message ) : ?>
                <div class="ocb-iq-message <?php echo $form_submitted ? 'ocb-iq-success' : 'ocb-iq-error'; ?>">
                    <?php echo esc_html( $form_message ); ?>
                </div>
            <?php endif; ?>

            <h3 class="ocb-iq-title">Instant Quote</h3>
            
            <form method="post">
                <?php wp_nonce_field( 'ocb_instant_quote_submit', 'ocb_instant_quote_nonce' ); ?>
                
                <div class="ocb-iq-field">
                    <label class="ocb-iq-label">Pickup Postcode</label>
                    <input type="text" name="pickup_postcode" class="ocb-iq-input" placeholder="Pickup postcode" required>
                </div>

                <div class="ocb-iq-field">
                    <label class="ocb-iq-label">Delivery Postcode</label>
                    <input type="text" name="delivery_postcode" class="ocb-iq-input" placeholder="Delivery postcode" required>
                </div>

                <div class="ocb-iq-field">
                    <label class="ocb-iq-label">Email</label>
                    <input type="email" name="email" class="ocb-iq-input" placeholder="Email" required>
                </div>

                <div class="ocb-iq-field">
                    <label class="ocb-iq-label">Service Type</label>
                    <div class="ocb-iq-checkbox-group">
                        <label class="ocb-iq-checkbox-label">
                            <input type="radio" name="service_type" value="same_day" required> Same Day
                        </label>
                        <label class="ocb-iq-checkbox-label">
                            <input type="radio" name="service_type" value="priority"> Priority
                        </label>
                        <label class="ocb-iq-checkbox-label">
                            <input type="radio" name="service_type" value="direct"> Direct
                        </label>
                    </div>
                </div>

                <button type="submit" class="ocb-iq-submit">SEND</button>
                
                <p style="margin-top: 12px; padding: 8px; background: #fff4e6; border-left: 3px solid #ff9800; font-size: 12px; color: #e65100; line-height: 1.4;">
                    <strong>🌙 Night Rate:</strong> Deliveries between 10 PM - 6 AM are charged at 2× the standard rate.
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

new OnRoute_Instant_Quote();
