<?php
/**
 * Payment Settings Management
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Payment_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		// Payment Mode Settings
		register_setting( 'ocb_settings_group', 'ocb_payment_enabled' );
		register_setting( 'ocb_settings_group', 'ocb_payment_mode' ); // none, deposit, full
		register_setting( 'ocb_settings_group', 'ocb_deposit_type' ); // fixed, percent
		register_setting( 'ocb_settings_group', 'ocb_deposit_amount' );

		// Stripe Settings
		register_setting( 'ocb_settings_group', 'ocb_stripe_test_mode' );
		register_setting( 'ocb_settings_group', 'ocb_stripe_test_publishable_key' );
		register_setting( 'ocb_settings_group', 'ocb_stripe_test_secret_key' );
		register_setting( 'ocb_settings_group', 'ocb_stripe_live_publishable_key' );
		register_setting( 'ocb_settings_group', 'ocb_stripe_live_secret_key' );

		// Night Time Rate Settings
		register_setting( 'ocb_settings_group', 'ocb_night_enabled' );
		register_setting( 'ocb_settings_group', 'ocb_night_start' );
		register_setting( 'ocb_settings_group', 'ocb_night_end' );
		register_setting( 'ocb_settings_group', 'ocb_night_multiplier' );
		register_setting( 'ocb_settings_group', 'ocb_night_apply_mode' ); // collection_only, either, both
	}

	/**
	 * Render settings section
	 */
	public static function render_settings_section() {
		$settings = self::get_settings();
		?>
		<div class="ocb-settings-section">
			<h2><span class="dashicons dashicons-money"></span> Payment Settings</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="ocb_payment_enabled">Enable Payments:</label></th>
					<td>
						<label class="switch">
							<input type="checkbox" id="ocb_payment_enabled" name="ocb_payment_enabled" value="1" <?php checked( $settings['enabled'], 1 ); ?>>
							<span class="slider round"></span>
						</label>
						<p class="description">Enable payment collection during booking request</p>
					</td>
				</tr>

				<tbody id="ocb-payment-options" style="<?php echo $settings['enabled'] ? '' : 'display:none;'; ?>">
					<tr>
						<th scope="row"><label for="ocb_payment_mode">Payment Mode:</label></th>
						<td>
							<select id="ocb_payment_mode" name="ocb_payment_mode" class="regular-text">
								<option value="none" <?php selected( $settings['mode'], 'none' ); ?>>No Payment (Pay Later)</option>
								<option value="deposit" <?php selected( $settings['mode'], 'deposit' ); ?>>Partial Payment (Deposit)</option>
								<option value="full" <?php selected( $settings['mode'], 'full' ); ?>>Full Payment</option>
							</select>
						</td>
					</tr>

					<tr class="ocb-deposit-settings" style="<?php echo $settings['mode'] === 'deposit' ? '' : 'display:none;'; ?>">
						<th scope="row"><label>Deposit Amount:</label></th>
						<td>
							<input type="number" name="ocb_deposit_amount" value="<?php echo esc_attr( $settings['deposit_amount'] ); ?>" step="0.01" class="small-text">
							<select name="ocb_deposit_type">
								<option value="fixed" <?php selected( $settings['deposit_type'], 'fixed' ); ?>>£ (Fixed)</option>
								<option value="percent" <?php selected( $settings['deposit_type'], 'percent' ); ?>>% (Percentage)</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">Stripe Environment:</th>
						<td>
							<label>
								<input type="radio" name="ocb_stripe_test_mode" value="1" <?php checked( $settings['test_mode'], 1 ); ?>> Test Mode
							</label>
							&nbsp;&nbsp;
							<label>
								<input type="radio" name="ocb_stripe_test_mode" value="0" <?php checked( $settings['test_mode'], 0 ); ?>> Live Mode
							</label>
						</td>
					</tr>

					<tr class="ocb-stripe-test" style="<?php echo $settings['test_mode'] ? '' : 'display:none;'; ?>">
						<th scope="row"><label>Test Keys:</label></th>
						<td>
							<input type="text" name="ocb_stripe_test_publishable_key" value="<?php echo esc_attr( $settings['test_pk'] ); ?>" class="regular-text" placeholder="pk_test_...">
							<br><br>
							<input type="password" name="ocb_stripe_test_secret_key" value="<?php echo esc_attr( $settings['test_sk'] ); ?>" class="regular-text" placeholder="sk_test_...">
						</td>
					</tr>

					<tr class="ocb-stripe-live" style="<?php echo $settings['test_mode'] ? 'display:none;' : ''; ?>">
						<th scope="row"><label>Live Keys:</label></th>
						<td>
							<input type="text" name="ocb_stripe_live_publishable_key" value="<?php echo esc_attr( $settings['live_pk'] ); ?>" class="regular-text" placeholder="pk_live_...">
							<br><br>
							<input type="password" name="ocb_stripe_live_secret_key" value="<?php echo esc_attr( $settings['live_sk'] ); ?>" class="regular-text" placeholder="sk_live_...">
						</td>
					</tr>
				</tbody>
			</table>

			<script>
				jQuery(document).ready(function($) {
					// Toggle Payment Options
					$('#ocb_payment_enabled').change(function() {
						if($(this).is(':checked')) {
							$('#ocb-payment-options').slideDown();
						} else {
							$('#ocb-payment-options').slideUp();
						}
					});

					// Toggle Deposit Settings
					$('#ocb_payment_mode').change(function() {
						if($(this).val() === 'deposit') {
							$('.ocb-deposit-settings').slideDown();
						} else {
							$('.ocb-deposit-settings').slideUp();
						}
					});

					// Toggle Stripe Env
					$('input[name="ocb_stripe_test_mode"]').change(function() {
						if($(this).val() == 1) {
							$('.ocb-stripe-test').show();
							$('.ocb-stripe-live').hide();
						} else {
							$('.ocb-stripe-test').hide();
							$('.ocb-stripe-live').show();
						}
					});
				});
			</script>
		</div>

		<!-- Night Time Rate Settings -->
		<div class="ocb-settings-section" style="margin-top: 30px;">
			<h2><span class="dashicons dashicons-clock"></span> Night Time Rate Settings</h2>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="ocb_night_enabled">Enable Night Time Rate:</label></th>
					<td>
						<label class="switch">
							<input type="checkbox" id="ocb_night_enabled" name="ocb_night_enabled" value="1" <?php checked( $settings['night_enabled'], 1 ); ?>>
							<span class="slider round"></span>
						</label>
						<p class="description">Enable higher rates during night hours</p>
					</td>
				</tr>

				<tbody id="ocb-night-options" style="<?php echo $settings['night_enabled'] ? '' : 'display:none;'; ?>">
					<tr>
						<th scope="row"><label for="ocb_night_start">Night Rate Start Time (24h):</label></th>
						<td>
							<input type="number" id="ocb_night_start" name="ocb_night_start" value="<?php echo esc_attr( $settings['night_start'] ); ?>" min="0" max="23" step="1" class="small-text">
							<p class="description">Time when night rate starts (e.g., 22 for 10 PM)</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ocb_night_end">Night Rate End Time (24h):</label></th>
						<td>
							<input type="number" id="ocb_night_end" name="ocb_night_end" value="<?php echo esc_attr( $settings['night_end'] ); ?>" min="0" max="23" step="1" class="small-text">
							<p class="description">Time when night rate ends (e.g., 6 for 6 AM)</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ocb_night_multiplier">Night Rate Multiplier:</label></th>
						<td>
							<input type="number" id="ocb_night_multiplier" name="ocb_night_multiplier" value="<?php echo esc_attr( $settings['night_multiplier'] ); ?>" min="1" step="0.1" class="small-text">
							<p class="description">Price multiplier for night bookings (e.g., 2 = double price). Default: 2.0</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ocb_night_apply_mode">Apply Night Rate For:</label></th>
						<td>
							<select id="ocb_night_apply_mode" name="ocb_night_apply_mode" class="regular-text">
								<option value="either" <?php selected( $settings['night_apply_mode'], 'either' ); ?>>Either Collection OR Delivery (Recommended)</option>
								<option value="collection_only" <?php selected( $settings['night_apply_mode'], 'collection_only' ); ?>>Collection Time Only</option>
								<option value="both" <?php selected( $settings['night_apply_mode'], 'both' ); ?>>Both Collection AND Delivery</option>
							</select>
							<p class="description">When to apply the night rate multiplier</p>
						</td>
					</tr>
				</tbody>
			</table>

			<script>
				jQuery(document).ready(function($) {
					// Toggle Night Options
					$('#ocb_night_enabled').change(function() {
						if($(this).is(':checked')) {
							$('#ocb-night-options').slideDown();
						} else {
							$('#ocb-night-options').slideUp();
						}
					});
				});
			</script>
		</div>
		<?php
	}


	/**
	 * Get all settings
	 */
	public static function get_settings() {
		return array(
			'enabled' => get_option( 'ocb_payment_enabled', false ),
			'mode' => get_option( 'ocb_payment_mode', 'full' ),
			'deposit_type' => get_option( 'ocb_deposit_type', 'percent' ),
			'deposit_amount' => get_option( 'ocb_deposit_amount', 10 ),
			'test_mode' => get_option( 'ocb_stripe_test_mode', true ),
			'test_pk' => get_option( 'ocb_stripe_test_publishable_key', '' ),
			'test_sk' => get_option( 'ocb_stripe_test_secret_key', '' ),
			'live_pk' => get_option( 'ocb_stripe_live_publishable_key', '' ),
			'live_sk' => get_option( 'ocb_stripe_live_secret_key', '' ),
			// Night Time Settings
			'night_enabled' => get_option( 'ocb_night_enabled', 1 ),
			'night_start' => get_option( 'ocb_night_start', 22 ),
			'night_end' => get_option( 'ocb_night_end', 6 ),
			'night_multiplier' => get_option( 'ocb_night_multiplier', 2 ),
			'night_apply_mode' => get_option( 'ocb_night_apply_mode', 'either' ), // collection_only, either, both
		);
	}

	/**
	 * Get active Stripe keys based on mode
	 */
	public static function get_stripe_keys() {
		$settings = self::get_settings();
		if ( $settings['test_mode'] ) {
			return array(
				'publishable' => $settings['test_pk'],
				'secret' => $settings['test_sk'],
			);
		}
		return array(
			'publishable' => $settings['live_pk'],
			'secret' => $settings['live_sk'],
		);
	}
}
