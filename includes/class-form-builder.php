<?php
/**
 * Form Builder Class - Manages form fields and rendering
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Form_Builder {

	/**
	 * Get form fields configuration
	 * Always merges defaults to ensure new fields are available even if saved config is outdated
	 */
	public static function get_form_fields() {
		$saved_fields = get_option( 'ocb_form_fields', array() );
		$default_fields = self::get_default_fields();
		
		// Merge: defaults first, then any saved customizations override
		// This ensures new fields are always available
		$fields = array_merge( $default_fields, $saved_fields );
		
		// CRITICAL: Force-add city and company fields if missing (database cache issue)
		$critical_fields = array(
			'step3_collection_city',
			'step3_collection_company',
			'step3_delivery_city',
			'step3_delivery_company'
		);
		
		foreach ($critical_fields as $field_key) {
			if (!isset($fields[$field_key]) || !is_array($fields[$field_key])) {
				if (isset($default_fields[$field_key])) {
					$fields[$field_key] = $default_fields[$field_key];
				}
			}
		}
		
		return $fields;
	}

	/**
	 * Get default form fields
	 */
	public static function get_default_fields() {
		return array(
			// Step 1: Quote Form
			'step1_collection_postcode' => array(
				'label' => 'Collection Postcode',
				'type' => 'text',
				'required' => true,
				'active' => true,
				'step' => 1,
			),
			'step1_delivery_postcode' => array(
				'label' => 'Delivery Postcode',
				'type' => 'text',
				'required' => true,
				'active' => true,
				'step' => 1,
			),
			'step1_ready_now' => array(
				'label' => 'Timing',
				'type' => 'radio',
				'required' => true,
				'active' => true,
				'step' => 1,
				'options' => array(
					'ready_now' => 'Ready Now',
					'pre_book' => 'Pre-book',
				),
			),
			// Step 3: Booking Details
			'step3_first_name' => array(
				'label' => 'First Name',
				'type' => 'text',
				'required' => true,
				'active' => true,
				'step' => 3,
			),
			'step3_last_name' => array(
				'label' => 'Last Name',
				'type' => 'text',
				'required' => true,
				'active' => true,
				'step' => 3,
			),
			'step3_email' => array(
				'label' => 'Email Address',
				'type' => 'email',
				'required' => true,
				'active' => true,
				'step' => 3,
			),
			'step3_phone' => array(
				'label' => 'Phone Number',
				'type' => 'tel',
				'required' => true,
				'active' => true,
				'step' => 3,
			),
			'step3_collection_address' => array(
				'label' => 'Collection Address',
				'type' => 'textarea',
				'required' => true,
				'active' => true,
				'step' => 3,
			),
			'step3_collection_company' => array(
				'label' => 'Company',
				'type' => 'text',
				'required' => false,
				'active' => true,
				'step' => 3,
			),
			'step3_collection_city' => array(
				'label' => 'City',
				'type' => 'text',
				'required' => false,
				'active' => true,
				'step' => 3,
			),
			'step3_delivery_address' => array(
				'label' => 'Delivery Address',
				'type' => 'textarea',
				'required' => true,
				'active' => true,
				'step' => 3,
			),
			'step3_delivery_company' => array(
				'label' => 'Company',
				'type' => 'text',
				'required' => false,
				'active' => true,
				'step' => 3,
			),
			'step3_delivery_city' => array(
				'label' => 'City',
				'type' => 'text',
				'required' => false,
				'active' => true,
				'step' => 3,
			),
			'step3_collection_date' => array(
				'label' => 'Collection Date',
				'type' => 'date',
				'required' => true,
				'active' => true,
				'step' => 3,
			),
			'step3_collection_time' => array(
				'label' => 'Collection Time',
				'type' => 'time',
				'required' => false,
				'active' => true,
				'step' => 3,
			),
			'step3_delivery_date' => array(
				'label' => 'Delivery Date',
				'type' => 'date',
				'required' => true,
				'active' => true,
				'step' => 3,
			),
			'step3_delivery_time' => array(
				'label' => 'Delivery Time',
				'type' => 'time',
				'required' => false,
				'active' => true,
				'step' => 3,
			),
		);
	}

	/**
	 * Get fields for specific step
	 */
	public static function get_step_fields( $step ) {
		$all_fields = self::get_form_fields();
		$step_fields = array();

		foreach ( $all_fields as $key => $field ) {
			if ( isset( $field['step'] ) && $field['step'] == $step && $field['active'] ) {
				$step_fields[ $key ] = $field;
			}
		}

		return $step_fields;
	}

	/**
	 * Render form field
	 */
	public static function render_field( $name, $field, $value = '' ) {
		$required_attr = $field['required'] ? 'required' : '';
		$required_html = $field['required'] ? ' <span class="ocb-required">*</span>' : '';

		?>
		<div class="ocb-form-group">
			<label for="<?php echo esc_attr( $name ); ?>">
				<?php echo esc_html( $field['label'] ); ?>
				<?php echo wp_kses_post( $required_html ); ?>
			</label>

			<?php
			switch ( $field['type'] ) {
				case 'text':
				case 'email':
				case 'tel':
					$type = $field['type'];
					echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $required_attr ? ' required' : '' ) . ' />';
					break;

				case 'date':
					echo '<input type="date" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-flatpickr-date' . ( $required_attr ? ' required' : '' ) . ' />';
					break;

				case 'time':
					echo '<input type="time" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-flatpickr-time' . ( $required_attr ? ' required' : '' ) . ' />';
					break;

				case 'textarea':
					echo '<textarea id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . ( $required_attr ? ' required' : '' ) . '>' . esc_textarea( $value ) . '</textarea>';
					break;

				case 'radio':
					foreach ( $field['options'] as $option_value => $option_label ) {
						$checked = checked( $value, $option_value, false );
						echo '<label class="ocb-radio-label"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $option_value ) . '"' . $checked . ( $required_attr ? ' required' : '' ) . ' />' . esc_html( $option_label ) . '</label>';
					}
					break;

				case 'select':
					echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . ( $required_attr ? ' required' : '' ) . '><option value="">-- Select --</option>';
					foreach ( $field['options'] as $option_value => $option_label ) {
						$selected = selected( $value, $option_value, false );
						echo '<option value="' . esc_attr( $option_value ) . '"' . $selected . '>' . esc_html( $option_label ) . '</option>';
					}
					echo '</select>';
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Sanitize form data
	 */
	public static function sanitize_field_data( $name, $value, $field ) {
		switch ( $field['type'] ) {
			case 'email':
				return sanitize_email( $value );
			case 'tel':
			case 'text':
				return sanitize_text_field( $value );
			case 'date':
			case 'time':
				return sanitize_text_field( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'radio':
			case 'select':
				return isset( $field['options'][ $value ] ) ? sanitize_text_field( $value ) : '';
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Validate form data
	 */
	public static function validate_field_data( $name, $value, $field ) {
		if ( $field['required'] && empty( $value ) ) {
			return new WP_Error( 'field_required', $field['label'] . ' is required' );
		}

		switch ( $field['type'] ) {
			case 'email':
				if ( ! empty( $value ) && ! is_email( $value ) ) {
					return new WP_Error( 'invalid_email', 'Invalid email address' );
				}
				break;
			case 'date':
				if ( ! empty( $value ) && ! strtotime( $value ) ) {
					return new WP_Error( 'invalid_date', 'Invalid date format' );
				}
				break;
			case 'time':
				if ( ! empty( $value ) && ! preg_match( '/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
					return new WP_Error( 'invalid_time', 'Invalid time format' );
				}
				break;
		}

		return true;
	}

	/**
	 * Reset field cache - forces update to latest defaults
	 * Call this after plugin updates to ensure new fields are available
	 */
	public static function reset_fields_cache() {
		delete_option( 'ocb_form_fields' );
		// Fields will be regenerated from defaults on next load
	}
}
