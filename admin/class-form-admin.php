<?php
/**
 * Admin Form Settings Page
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Form_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_form_menu' ), 11 );
		add_action( 'admin_init', array( $this, 'handle_form_updates' ) );
	}

	/**
	 * Add form settings menu
	 */
	public function add_form_menu() {
		add_submenu_page(
			'ocb-dashboard',
			'Form Settings',
			'Form Settings',
			'manage_options',
			'ocb-form-settings',
			array( $this, 'render_form_settings' )
		);
	}

	/**
	 * Handle form updates
	 */
	public function handle_form_updates() {
		if ( ! isset( $_POST['ocb_save_form_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'ocb_form_settings_nonce' );

		// Get all field data from POST
		$fields = OnRoute_Courier_Booking_Form_Settings::get_fields();

		// Process step settings
		if ( isset( $_POST['ocb_step'] ) && is_array( $_POST['ocb_step'] ) ) {
			foreach ( $_POST['ocb_step'] as $step_id => $step_data ) {
				$step_id = sanitize_key( $step_data );
				if ( isset( $fields[ $step_id ] ) ) {
					$fields[ $step_id ]['enabled'] = isset( $_POST['ocb_step_enabled'][ $step_id ] ) ? 1 : 0;
				}
			}
		}

		// Process field settings
		if ( isset( $_POST['ocb_field'] ) && is_array( $_POST['ocb_field'] ) ) {
			foreach ( $_POST['ocb_field'] as $field_key => $field_data ) {
				// Field key format: step_fieldname
				$parts = explode( '_', $field_key, 2 );
				if ( count( $parts ) === 2 ) {
					$step = sanitize_key( $parts[0] );
					$field_name = sanitize_key( $parts[1] );

					if ( isset( $fields[ $step ]['fields'][ $field_name ] ) ) {
						// Update label
						if ( isset( $_POST['ocb_field_label'][ $field_key ] ) ) {
							$fields[ $step ]['fields'][ $field_name ]['label'] = sanitize_text_field( $_POST['ocb_field_label'][ $field_key ] );
						}

						// Update enabled
						$fields[ $step ]['fields'][ $field_name ]['enabled'] = isset( $_POST['ocb_field_enabled'][ $field_key ] ) ? 1 : 0;

						// Update required
						$fields[ $step ]['fields'][ $field_name ]['required'] = isset( $_POST['ocb_field_required'][ $field_key ] ) ? 1 : 0;

						// Update placeholder
						if ( isset( $_POST['ocb_field_placeholder'][ $field_key ] ) ) {
							$fields[ $step ]['fields'][ $field_name ]['placeholder'] = sanitize_text_field( $_POST['ocb_field_placeholder'][ $field_key ] );
						}
					}
				}
			}
		}

		OnRoute_Courier_Booking_Form_Settings::save_fields( $fields );
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-success is-dismissible"><p>Form settings updated successfully!</p></div>';
		} );
	}

	/**
	 * Render form settings page
	 */
	public function render_form_settings() {
		$fields = OnRoute_Courier_Booking_Form_Settings::get_fields();
		?>
		<div class="wrap ocb-admin">
			<h1>Form Settings</h1>

			<form method="POST" class="ocb-form-settings-form">
				<?php wp_nonce_field( 'ocb_form_settings_nonce' ); ?>

				<div class="ocb-settings-section">
					<h2>Form Steps & Fields</h2>

					<?php foreach ( $fields as $step_id => $step ) : ?>
						<div class="ocb-step-settings" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
							<h3 style="margin-top: 0;">
								<label>
									<input type="checkbox" name="ocb_step_enabled[<?php echo esc_attr( $step_id ); ?>]" value="1" <?php checked( $step['enabled'], 1 ); ?> />
									<?php echo esc_html( $step['title'] ); ?> (<?php echo esc_html( $step_id ); ?>)
								</label>
							</h3>

							<?php if ( ! empty( $step['fields'] ) ) : ?>
								<table class="wp-list-table widefat fixed striped ocb-field-table">
									<thead>
										<tr>
											<th style="width: 30px;"></th>
											<th style="width: 200px;">Field</th>
											<th>Label</th>
											<th style="width: 100px;">Required</th>
											<th style="width: 150px;">Placeholder</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $step['fields'] as $field_id => $field ) : ?>
											<?php
											$field_key = $step_id . '_' . $field_id;
											$checked = isset( $field['enabled'] ) ? $field['enabled'] : 0;
											$required = isset( $field['required'] ) ? $field['required'] : 0;
											?>
											<tr>
												<td>
													<input type="checkbox" name="ocb_field_enabled[<?php echo esc_attr( $field_key ); ?>]" value="1" <?php checked( $checked, 1 ); ?> />
												</td>
												<td style="font-weight: bold;">
													<?php echo esc_html( $field_id ); ?>
													<br>
													<small>(<?php echo esc_html( isset( $field['type'] ) ? $field['type'] : 'text' ); ?>)</small>
												</td>
												<td>
													<input type="text" name="ocb_field_label[<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( isset( $field['label'] ) ? $field['label'] : '' ); ?>" class="regular-text" />
												</td>
												<td>
													<label>
														<input type="checkbox" name="ocb_field_required[<?php echo esc_attr( $field_key ); ?>]" value="1" <?php checked( $required, 1 ); ?> />
														Required
													</label>
												</td>
												<td>
													<input type="text" name="ocb_field_placeholder[<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>" class="regular-text" />
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="ocb-form-actions">
					<?php submit_button( 'Save Form Settings' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ocb-form-settings&reset=1' ) ); ?>" class="button button-secondary" onclick="return confirm('Reset all form settings to defaults?')">Reset to Defaults</a>
				</div>
			</form>

			<div class="ocb-settings-section" style="margin-top: 40px; background: #f0f8ff; padding: 20px; border-left: 4px solid #007cba; border-radius: 5px;">
				<h3>💡 How to Use the Booking Form</h3>
				<p><strong>Add to any page:</strong></p>
				<code>[onroute_courier_booking_form]</code>
				<p style="margin-top: 15px;"><strong>Or use the Gutenberg block:</strong></p>
				<p>Search for "Courier Booking Form" when editing a page</p>
				<p style="margin-top: 15px; color: #666;">The form will respect all settings above. Disabled steps won't appear. Disabled fields won't show.</p>
			</div>
		</div>
		<?php
	}
}
