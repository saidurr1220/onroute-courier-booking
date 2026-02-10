<?php
/**
 * Email notifications class
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Courier_Booking_Emails {

	/**
	 * Constructor to initialize hooks
	 */
	public function __construct() {
		add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
	}

	/**
	 * Configure SMTP for Gmail
	 */
	public function configure_smtp( $phpmailer ) {
		if ( ! get_option( 'ocb_smtp_enabled', 1 ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = get_option( 'ocb_smtp_host', 'smtp.gmail.com' );
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Port       = get_option( 'ocb_smtp_port', '587' );
		$phpmailer->SMTPSecure = get_option( 'ocb_smtp_encryption', 'tls' );
		$phpmailer->Username   = get_option( 'ocb_smtp_username', 'ops@onroutecouriers.com' );
		$phpmailer->Password   = get_option( 'ocb_smtp_password', 'qyap zhxa gmiw rrac' );
		$phpmailer->From       = get_option( 'ocb_smtp_from_email', 'ops@onroutecouriers.com' );
		$phpmailer->FromName   = get_option( 'ocb_smtp_from_name', 'OnRoute Couriers' );
	}

	/**
	 * Send booking confirmation email to customer and admin
	 * 
	 * @param int $booking_id Booking ID
	 */
	public static function send_booking_confirmation( $booking_id ) {
		global $wpdb;
		$booking_obj = new OnRoute_Courier_Booking_Booking();
		$b = $booking_obj->get( $booking_id );

		if ( ! $b ) return;

		// Prevent duplicate booking confirmations
		$logs_table = OnRoute_Courier_Booking_Database::get_email_logs_table();
		$already_sent = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $logs_table WHERE booking_id = %d AND subject LIKE %s",
			$booking_id,
			'%Booking Confirmation%'
		) );

		if ( $already_sent > 0 ) {
			return; // Skip if already sent
		}

		$admin_email = 'bookings@onroutecouriers.com';
		if ( ! empty( $b->user_id ) ) {
			$admin_email = 'admin@onroutecouriers.com';
		}
		$site_name = 'OnRoute Couriers';

		$subject = "Booking Confirmation: " . $b->booking_reference;
		
		$message = self::get_booking_details_html( $b );

		// Send to Customer
		self::send_html_mail( $b->customer_email, $subject, $message, $booking_id );

		// Generate Driver Link for Admin Email
		$driver_link = '';
		if ( class_exists( 'OnRoute_Courier_Booking_Driver_Portal' ) ) {
			$driver_url = OnRoute_Courier_Booking_Driver_Portal::get_portal_url( $booking_id );
			$driver_link = '<div style="margin-top: 20px; padding: 20px; background: #e9ecef; border-left: 5px solid #007bff;">';
			$driver_link .= '<h3 style="margin-top: 0;">🚚 Driver Actions</h3>';
			$driver_link .= '<p>Use the secure link below to update job status (Pickup/Delivery) and capture signatures without logging in:</p>';
			$driver_link .= '<p><a href="' . esc_url( $driver_url ) . '" style="display: inline-block; padding: 12px 20px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold;">Open Driver App</a></p>';
			$driver_link .= '<p style="font-size: 12px; color: #666;">Forward this link to the driver if needed.</p>';
			$driver_link .= '</div>';
		}

		// Send to Admin (with driver link)
		self::send_html_mail( $admin_email, "New Booking Received: " . $b->booking_reference, $message . $driver_link, $booking_id );
	}

	/**
	 * Send collection confirmation email to customer and admin
	 * 
	 * @param int $booking_id Booking ID
	 */
	public static function send_collection_confirmation( $booking_id ) {
		$booking_obj = new OnRoute_Courier_Booking_Booking();
		$b = $booking_obj->get( $booking_id );

		if ( ! $b ) return;

		$admin_email = 'bookings@onroutecouriers.com';
		if ( ! empty( $b->user_id ) ) {
			$admin_email = 'admin@onroutecouriers.com';
		}
		$subject = "Item Collected: " . $b->booking_reference;

		$primary_color = get_option( 'ocb_email_primary_color', '#D4AF37' );

		$content = "<h2 style='color: " . esc_attr( $primary_color ) . ";'>Collection Confirmation</h2>";
		$content .= "<p>Your item has been successfully collected.</p>";
		$content .= "<p><strong>Collected from:</strong> " . esc_html( $b->collected_by_name ) . "</p>";
		$content .= "<p><strong>Collected at:</strong> " . esc_html( date( 'd/m/Y H:i', strtotime( $b->collected_at ) ) ) . "</p>";
		
		if ( ! empty( $b->collection_signature ) ) {
			$content .= "<p><strong>Signature:</strong><br><img src='" . $b->collection_signature . "' style='max-width:300px; border:1px solid #ddd; background: #fff; padding: 10px;'></p>";
		}

		$message = self::apply_branding( $content, $b->booking_reference );

		// Send to Customer
		self::send_html_mail( $b->customer_email, $subject, $message, $booking_id );

		// Send to Admin
		self::send_html_mail( $admin_email, "Collection Completed: " . $b->booking_reference, $message, $booking_id );
	}

	/**
	 * Send delivery confirmation email to customer and admin
	 * 
	 * @param int $booking_id Booking ID
	 */
	public static function send_delivery_confirmation( $booking_id ) {
		$booking_obj = new OnRoute_Courier_Booking_Booking();
		$b = $booking_obj->get( $booking_id );

		if ( ! $b ) return;

		$admin_email = 'bookings@onroutecouriers.com';
		if ( ! empty( $b->user_id ) ) {
			$admin_email = 'admin@onroutecouriers.com';
		}
		$subject = "Item Delivered: " . $b->booking_reference;

		$primary_color = get_option( 'ocb_email_primary_color', '#D4AF37' );

		$content = "<h2 style='color: " . esc_attr( $primary_color ) . ";'>Delivery Confirmation</h2>";
		$content .= "<p>Your item has been successfully delivered.</p>";
		$content .= "<p><strong>Delivered to:</strong> " . esc_html( $b->delivered_to_name ) . "</p>";
		$content .= "<p><strong>Delivered at:</strong> " . esc_html( date( 'd/m/Y H:i', strtotime( $b->delivered_at ) ) ) . "</p>";
		
		if ( ! empty( $b->delivery_signature ) ) {
			$content .= "<p><strong>Signature:</strong><br><img src='" . $b->delivery_signature . "' style='max-width:300px; border:1px solid #ddd; background: #fff; padding: 10px;'></p>";
		}

		$message = self::apply_branding( $content, $b->booking_reference );

		// Send to Customer
		self::send_html_mail( $b->customer_email, $subject, $message, $booking_id );

		// Send to Admin
		self::send_html_mail( $admin_email, "Delivery Completed: " . $b->booking_reference, $message, $booking_id );
	}

	/**
	 * Get booking details HTML for emails
	 */
	private static function get_booking_details_html( $b ) {
		// Friendly names for vehicle and service
		$vehicle_names = array(
			'small_van' => 'Small Van',
			'mwb'       => 'Medium Van (MWB)',
			'lwb'       => 'Large Van (LWB)'
		);
		$service_names = array(
			'same_day' => 'Same Day',
			'priority' => 'Priority (Timed)',
			'direct'   => 'Dedicated / Direct'
		);

		$vehicle = $vehicle_names[ $b->vehicle_id ] ?? ucfirst( str_replace( '_', ' ', $b->vehicle_id ) );
		$service = $service_names[ $b->service_id ] ?? ucfirst( str_replace( '_', ' ', $b->service_id ) );

		$logo_url = get_option( 'ocb_email_logo_url', '' );
		$primary_color = get_option( 'ocb_email_primary_color', '#D4AF37' );
		$footer_text = get_option( 'ocb_email_footer_text', '© ' . date('Y') . ' OnRoute Couriers. All rights reserved.' );

		ob_start();
		?>
		<div style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee;">
			<div style="background: #1a1a1a; padding: 30px; text-align: center;">
				<?php if ( ! empty( $logo_url ) ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="OnRoute Couriers" style="max-width: 200px; height: auto; margin-bottom: 10px;">
				<?php else : ?>
					<h1 style="color: <?php echo esc_attr( $primary_color ); ?>; margin: 0; font-size: 24px;">OnRoute Couriers</h1>
				<?php endif; ?>
				<p style="color: #fff; margin: 5px 0 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Reliable & Secure Logistics</p>
			</div>
			
			<div style="padding: 24px;">
				<h3 style="color: <?php echo esc_attr( $primary_color ); ?>; border-bottom: 2px solid <?php echo esc_attr( $primary_color ); ?>; padding-bottom: 8px;">Booking Details</h3>
				<table cellpadding="10" cellspacing="0" border="1" style="border-collapse: collapse; width: 100%; border: 1px solid #eee;">
					<tr>
						<td style="width: 35%; background-color: #f9f9f9;"><strong>Booking Reference</strong></td>
						<td style="font-weight: bold; color: <?php echo esc_attr( $primary_color ); ?>;"><?php echo esc_html( $b->booking_reference ); ?></td>
					</tr>
					<tr>
						<td style="background-color: #f9f9f9;"><strong>Pickup Address</strong></td>
						<td><?php echo nl2br( esc_html( $b->pickup_address ) ); ?><br><strong><?php echo esc_html( $b->pickup_postcode ); ?></strong></td>
					</tr>
					<tr>
						<td style="background-color: #f9f9f9;"><strong>Delivery Address</strong></td>
						<td><?php echo nl2br( esc_html( $b->delivery_address ) ); ?><br><strong><?php echo esc_html( $b->delivery_postcode ); ?></strong></td>
					</tr>
					<tr>
						<td style="background-color: #f9f9f9;"><strong>Collection Time</strong></td>
						<td><?php echo esc_html( date( 'l, j F Y', strtotime( $b->collection_date ) ) ); ?> at <?php echo esc_html( date( 'H:i', strtotime( $b->collection_time ) ) ); ?></td>
					</tr>
					<tr>
						<td style="background-color: #f9f9f9;"><strong>Vehicle Type</strong></td>
						<td><?php echo esc_html( $vehicle ); ?></td>
					</tr>
					<tr>
						<td style="background-color: #f9f9f9;"><strong>Service Level</strong></td>
						<td><?php echo esc_html( $service ); ?></td>
					</tr>
					<tr>
						<td style="background-color: #f9f9f9;"><strong>Total Price Paid</strong></td>
						<td style="font-weight: bold; font-size: 18px;">£<?php echo number_format( $b->total_price, 2 ); ?></td>
					</tr>
				</table>
				
				<div style="margin-top: 30px; font-size: 13px; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 20px; line-height: 1.6;">
					<p style="margin: 0;"><?php echo nl2br( esc_html( $footer_text ) ); ?></p>
					<p style="margin: 10px 0 0;">Contact: bookings@onroutecouriers.com | Tel: 0207 786 1000</p>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send notification for a new Agent/Business account registration
	 */
	public static function send_new_account_notification( $user_id, $company_name ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;

		$admin_email = 'admin@onroutecouriers.com';
		$subject     = "New Agent Account Registered: " . $company_name;
		
		$content = "<h2>New Account Registration</h2>";
		$content .= "<p>A new agent account has been registered on the website and is pending review.</p>";
		$content .= "<ul>";
		$content .= "<li><strong>Business Name:</strong> " . esc_html( $company_name ) . "</li>";
		$content .= "<li><strong>Contact Name:</strong> " . esc_html( $user->display_name ) . "</li>";
		$content .= "<li><strong>Email:</strong> " . esc_html( $user->user_email ) . "</li>";
		$content .= "</ul>";
		$content .= "<p><a href='" . admin_url( 'user-edit.php?user_id=' . $user_id ) . "' style='background:#D4AF37; color:#fff; padding:10px 15px; text-decoration:none; border-radius:3px;'>View User Profile</a></p>";

		$message = self::apply_branding( $content );
		self::send_html_mail( $admin_email, $subject, $message );
	}

	/**
	 * Send notification for a credit account application
	 */
	public static function send_credit_application_notification( $user_id, $company_name, $phone = '', $address = '' ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;

		$admin_email = 'admin@onroutecouriers.com';
		$subject     = "New Credit Account Application: " . $company_name;

		$content = "<h2>Credit Account Application</h2>";
		$content .= "<p>An existing agent has submitted a formal application for a Credit Account (Monthly Billing).</p>";
		$content .= "<ul>";
		$content .= "<li><strong>Business:</strong> " . esc_html( $company_name ) . "</li>";
		$content .= "<li><strong>Agent Email:</strong> " . esc_html( $user->user_email ) . "</li>";
		$content .= "<li><strong>Phone:</strong> " . esc_html( $phone ) . "</li>";
		$content .= "</ul>";
		if ( ! empty( $address ) ) {
			$content .= "<p><strong>Address:</strong><br>" . nl2br( esc_html( $address ) ) . "</p>";
		}
		$content .= "<p>Log in to the WordPress admin to review and approve this credit application.</p>";

		$message = self::apply_branding( $content );
		self::send_html_mail( $admin_email, $subject, $message );
	}

	/**
	 * Helper to send HTML mail
	 */
	public static function send_html_mail( $to, $subject, $message, $booking_id = null ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent = wp_mail( $to, $subject, $message, $headers );
		
		self::log_email( $to, $subject, $sent, $booking_id );
	}

	/**
	 * Log email to database
	 */
	private static function log_email( $to, $subject, $success, $booking_id ) {
		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_email_logs_table();
		
		$wpdb->insert(
			$table,
			array(
				'booking_id' => $booking_id,
				'recipient_email' => $to,
				'subject' => $subject,
				'status' => $success ? 'sent' : 'failed',
				'sent_at' => current_time( 'mysql' )
			)
		);
	}

	/**
	 * Wrap content with professional branding template
	 */
	public static function apply_branding( $content, $reference = '' ) {
		$logo_url = get_option( 'ocb_email_logo_url', '' );
		$primary_color = get_option( 'ocb_email_primary_color', '#D4AF37' );
		$footer_text = get_option( 'ocb_email_footer_text', '© ' . date('Y') . ' OnRoute Couriers. All rights reserved.' );

		ob_start();
		?>
		<div style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee;">
			<div style="background: #1a1a1a; padding: 30px; text-align: center;">
				<?php if ( ! empty( $logo_url ) ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="OnRoute Couriers" style="max-width: 200px; height: auto; margin-bottom: 10px;">
				<?php else : ?>
					<h1 style="color: <?php echo esc_attr( $primary_color ); ?>; margin: 0; font-size: 24px;">OnRoute Couriers</h1>
				<?php endif; ?>
				<?php if ( $reference ) : ?>
					<p style="color: #fff; margin: 5px 0 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Ref: <?php echo esc_html( $reference ); ?></p>
				<?php endif; ?>
			</div>
			
			<div style="padding: 24px; line-height: 1.6;">
				<?php echo $content; ?>
				
				<div style="margin-top: 30px; font-size: 13px; color: #777; text-align: center; border-top: 1px solid #eee; padding-top: 20px; line-height: 1.6;">
					<p style="margin: 0;"><?php echo nl2br( esc_html( $footer_text ) ); ?></p>
					<p style="margin: 10px 0 0;">Contact: bookings@onroutecouriers.com | Tel: 0207 786 1000</p>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
