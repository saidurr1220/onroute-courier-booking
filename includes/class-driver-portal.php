<?php
/**
 * Driver Portal - Mobile Interface
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Courier_Booking_Driver_Portal {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'render_portal' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ocb_driver_update', array( $this, 'handle_driver_update' ) );
		add_action( 'wp_ajax_nopriv_ocb_driver_update', array( $this, 'handle_driver_update' ) );
	}

	/**
	 * Generate a secure driver token and link it to the booking
	 */
	public static function get_portal_url( $booking_id ) {
		// reuse existing token if available? No, just make a standardized stable one per booking?
		// Better: Generate a hash based on secret + booking ID + created_at so it's stateless but secure.
		// However, we want it to be short/friendly-ish.
		
		// Let's use a transient approach for revocability and simple mapping
		// Check if one exists?
		$existing_token = get_transient( 'ocb_booking_token_' . $booking_id );
		if ( $existing_token ) {
			return home_url( '/?ocb_driver_token=' . $existing_token );
		}

		$token = bin2hex( random_bytes( 16 ) );
		set_transient( 'ocb_driver_token_' . $token, $booking_id, YEAR_IN_SECONDS ); // Valid for 1 year
		set_transient( 'ocb_booking_token_' . $booking_id, $token, YEAR_IN_SECONDS ); // Reverse lookup

		return home_url( '/?ocb_driver_token=' . $token );
	}

	/**
	 * Enqueue signature pad if on portal
	 */
	public function enqueue_scripts() {
		if ( isset( $_GET['ocb_driver_token'] ) ) {
			wp_enqueue_script( 'signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js', array(), '4.1.7', true );
			wp_enqueue_style( 'dashicons' );
		}
	}

	/**
	 * Render the driver portal page
	 */
	public function render_portal() {
		if ( ! isset( $_GET['ocb_driver_token'] ) ) {
			return;
		}

		$token = sanitize_text_field( $_GET['ocb_driver_token'] );
		$booking_id = get_transient( 'ocb_driver_token_' . $token );

		if ( ! $booking_id ) {
			wp_die( '<h1>Invalid or Expired Link</h1><p>Please contact admin for a new link.</p>', 'OnRoute Driver Portal', array( 'response' => 403 ) );
		}

		$booking = new OnRoute_Courier_Booking_Booking();
		$b = $booking->get( $booking_id );

		if ( ! $b ) {
			wp_die( 'Booking not found.' );
		}

		// determine current stage
		$stage = 'pickup';
		if ( ! empty( $b->collected_at ) ) {
			$stage = 'delivery';
		}
		if ( ! empty( $b->delivered_at ) ) {
			$stage = 'completed';
		}

		// Render HTML
		?>
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
			<title>OnRoute Driver Portal</title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f6f9; color: #333; margin: 0; padding: 20px; line-height: 1.5; }
				.container { max-width: 500px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
				.header { background: #1a1a1a; color: #fff; padding: 20px; text-align: center; }
				.header h1 { margin: 0; font-size: 18px; color: #D4AF37; text-transform: uppercase; letter-spacing: 1px; }
				.header p { margin: 5px 0 0; font-size: 12px; color: #999; }
				.content { padding: 25px; }
				
				.job-card { background: #f8f9fa; border: 1px solid #eee; border-radius: 8px; padding: 15px; margin-bottom: 25px; }
				.job-row { display: flex; margin-bottom: 10px; font-size: 14px; }
				.job-row:last-child { margin-bottom: 0; }
				.job-label { width: 100px; color: #777; font-weight: 500; flex-shrink: 0; }
				.job-value { font-weight: 600; color: #111; }

				.stage-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 20px; }
				.stage-pickup { background: #fff3cd; color: #856404; }
				.stage-delivery { background: #d4edda; color: #155724; }
				.stage-completed { background: #cce5ff; color: #004085; }

				.form-group { margin-bottom: 20px; }
				label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
				input[type="text"] { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
				input[type="text"]:focus { border-color: #D4AF37; outline: none; }
				
				.signature-pad-wrapper { border: 2px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff; position: relative; }
				canvas { display: block; width: 100%; height: 200px; touch-action: none; }
				.clear-btn { position: absolute; top: 10px; right: 10px; background: #eee; border: none; padding: 5px 10px; border-radius: 4px; font-size: 12px; cursor: pointer; color: #555; }

				button.submit-btn { width: 100%; padding: 16px; background: #D4AF37; color: #000; font-weight: 700; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.2s; }
				button.submit-btn:hover { background: #C49F27; }

				.success-message { text-align: center; padding: 40px 20px; }
				.success-icon { font-size: 60px; color: #28a745; margin-bottom: 20px; display: block; }
			</style>
			<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1>Driver Portal</h1>
					<p>OnRoute Couriers</p>
				</div>
				<div class="content">
					<?php if ( $stage === 'completed' ) : ?>
						<div class="success-message">
							<span class="success-icon">✓</span>
							<h2>Job Completed!</h2>
							<p>This booking has been fully processed.</p>
							<div class="job-card" style="margin-top: 20px; text-align: left;">
								<div class="job-row">
									<span class="job-label">Ref:</span>
									<span class="job-value"><?php echo esc_html( $b->booking_reference ); ?></span>
								</div>
								<div class="job-row">
									<span class="job-label">Status:</span>
									<span class="job-value" style="color: green;">Delivered</span>
								</div>
							</div>
						</div>
					<?php else : ?>
						<div style="text-align: center;">
							<span class="stage-badge stage-<?php echo $stage; ?>">
								Current Step: <?php echo ucfirst( $stage ); ?>
							</span>
						</div>

						<div class="job-card">
							<div class="job-row">
								<span class="job-label">Ref:</span>
								<span class="job-value"><?php echo esc_html( $b->booking_reference ); ?></span>
							</div>
							<div class="job-row">
								<span class="job-label">Address:</span>
								<span class="job-value">
									<?php echo ( $stage === 'pickup' ) ? esc_html( $b->pickup_postcode ) : esc_html( $b->delivery_postcode ); ?>
								</span>
							</div>
							<?php if ( $stage === 'pickup' && $b->pickup_address ) : ?>
							<div class="job-row" style="margin-top: 5px;">
								<span class="job-label"></span>
								<span class="job-value" style="font-size: 13px; font-weight: normal;"><?php echo esc_html( $b->pickup_address ); ?></span>
							</div>
							<?php elseif ( $stage === 'delivery' && $b->delivery_address ) : ?>
							<div class="job-row" style="margin-top: 5px;">
								<span class="job-label"></span>
								<span class="job-value" style="font-size: 13px; font-weight: normal;"><?php echo esc_html( $b->delivery_address ); ?></span>
							</div>
							<?php endif; ?>
						</div>

						<form id="driver-form">
							<div class="form-group">
								<label>
									<?php echo ( $stage === 'pickup' ) ? 'Handed over by (Name):' : 'Received by (Name):'; ?>
								</label>
								<input type="text" id="signer_name" required placeholder="Enter name...">
							</div>

							<div class="form-group">
								<label>Signature:</label>
								<div class="signature-pad-wrapper">
									<canvas id="signature-pad"></canvas>
									<button type="button" class="clear-btn" id="clear-sig">Clear</button>
								</div>
							</div>

							<button type="submit" class="submit-btn" id="submit-btn">
								Confirm <?php echo ucfirst( $stage ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $stage !== 'completed' ) : ?>
			<script>
				document.addEventListener('DOMContentLoaded', function() {
					var canvas = document.getElementById('signature-pad');
					var signaturePad = new SignaturePad(canvas, {
						backgroundColor: 'rgb(255, 255, 255)'
					});

					function resizeCanvas() {
						var ratio =  Math.max(window.devicePixelRatio || 1, 1);
						canvas.width = canvas.offsetWidth * ratio;
						canvas.height = canvas.offsetHeight * ratio;
						canvas.getContext("2d").scale(ratio, ratio);
						signaturePad.clear();
					}
					window.addEventListener("resize", resizeCanvas);
					resizeCanvas();

					document.getElementById('clear-sig').addEventListener('click', function() {
						signaturePad.clear();
					});

					document.getElementById('driver-form').addEventListener('submit', function(e) {
						e.preventDefault();
						if (signaturePad.isEmpty()) {
							alert("Please provide a signature.");
							return;
						}
						
						var btn = document.getElementById('submit-btn');
						var originalText = btn.innerText;
						btn.innerText = 'Processing...';
						btn.disabled = true;

						var data = new FormData();
						data.append('action', 'ocb_driver_update');
						data.append('token', '<?php echo $token; ?>');
						data.append('name', document.getElementById('signer_name').value);
						data.append('signature', signaturePad.toDataURL());
						data.append('stage', '<?php echo $stage; ?>');

						fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
							method: 'POST',
							body: data
						})
						.then(response => response.json())
						.then(res => {
							if (res.success) {
								alert('Success! ' + res.data.message);
								location.reload();
							} else {
								alert('Error: ' + (res.data.message || 'Unknown error'));
								btn.innerText = originalText;
								btn.disabled = false;
							}
						})
						.catch(err => {
							alert('Network Error');
							btn.innerText = originalText;
							btn.disabled = false;
						});
					});
				});
			</script>
			<?php endif; ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Handle AJAX status update from portal
	 */
	public function handle_driver_update() {
		$token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$signature = isset( $_POST['signature'] ) ? $_POST['signature'] : '';
		$stage = isset( $_POST['stage'] ) ? sanitize_text_field( $_POST['stage'] ) : '';

		$booking_id = get_transient( 'ocb_driver_token_' . $token );

		if ( ! $booking_id || empty( $name ) || empty( $signature ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request or expired session.' ) );
		}

		global $wpdb;
		$table = OnRoute_Courier_Booking_Database::get_bookings_table();
		$b = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $booking_id ) );

		if ( ! $b ) {
			wp_send_json_error( array( 'message' => 'Booking not found.' ) );
		}

		$update_data = array();
		
		if ( $stage === 'pickup' ) {
			$update_data['collected_by_name'] = $name;
			$update_data['collection_signature'] = $signature;
			$update_data['collected_at'] = current_time( 'mysql' );
			
			// Only update status if currently just booked/pending
			if ( in_array( $b->status, array('booked', 'pending', 'confirmed') ) ) {
				$update_data['status'] = 'collected';
			}

			$wpdb->update( $table, $update_data, array( 'id' => $booking_id ) );
			OnRoute_Courier_Booking_Emails::send_collection_confirmation( $booking_id );
			
			wp_send_json_success( array( 'message' => 'Pickup confirmed.' ) );
		} 
		elseif ( $stage === 'delivery' ) {
			$update_data['delivered_to_name'] = $name;
			$update_data['delivery_signature'] = $signature;
			$update_data['delivered_at'] = current_time( 'mysql' );
			$update_data['status'] = 'completed'; // Always complete here

			$wpdb->update( $table, $update_data, array( 'id' => $booking_id ) );
			OnRoute_Courier_Booking_Emails::send_delivery_confirmation( $booking_id );
			
			wp_send_json_success( array( 'message' => 'Delivery confirmed.' ) );
		}

		wp_send_json_error( array( 'message' => 'Invalid stage.' ) );
	}
}
