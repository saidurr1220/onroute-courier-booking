<?php
/**
 * Form Settings Management
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Form_Settings {

	/**
	 * Get default form fields
	 */
	public static function get_default_fields() {
		return array(
			'step1' => array(
				'title' => 'Quote Request',
				'enabled' => true,
				'fields' => array(
					'pickup_code' => array(
						'label' => 'Collection Postcode',
						'type' => 'text',
						'required' => true,
						'placeholder' => 'e.g., SW1A 1AA',
						'enabled' => true,
					),
					'delivery_code' => array(
						'label' => 'Delivery Postcode',
						'type' => 'text',
						'required' => true,
						'placeholder' => 'e.g., M1 1AE',
						'enabled' => true,
					),
					'booking_type' => array(
						'label' => 'Booking Type',
						'type' => 'radio',
						'required' => true,
						'options' => array(
							'now' => 'Ready Now',
							'pre_book' => 'Pre-Book',
						),
						'enabled' => true,
					),
				),
			),
			'step2' => array(
				'title' => 'Quote Results',
				'enabled' => true,
				'fields' => array(),
			),
			'step3' => array(
				'title' => 'Booking Details',
				'enabled' => true,
				'fields' => array(
					'customer_email' => array(
						'label' => 'Email Address',
						'type' => 'email',
						'required' => true,
						'enabled' => true,
					),
					'customer_phone' => array(
						'label' => 'Phone Number',
						'type' => 'tel',
						'required' => true,
						'enabled' => true,
					),
					'pickup_address' => array(
						'label' => 'Collection Address',
						'type' => 'textarea',
						'required' => true,
						'enabled' => true,
					),
					'delivery_address' => array(
						'label' => 'Delivery Address',
						'type' => 'textarea',
						'required' => true,
						'enabled' => true,
					),
					'collection_date' => array(
						'label' => 'Collection Date',
						'type' => 'date',
						'required' => true,
						'enabled' => true,
					),
					'collection_time' => array(
						'label' => 'Collection Time',
						'type' => 'time',
						'required' => true,
						'enabled' => true,
					),
					'delivery_date' => array(
						'label' => 'Delivery Date',
						'type' => 'date',
						'required' => true,
						'enabled' => true,
					),
					'delivery_time' => array(
						'label' => 'Delivery Time',
						'type' => 'time',
						'required' => false,
						'enabled' => true,
					),
				),
			),
			'step4' => array(
				'title' => 'Review & Book',
				'enabled' => true,
				'fields' => array(
					'promo_code' => array(
						'label' => 'Promo Code',
						'type' => 'text',
						'required' => false,
						'enabled' => true,
					),
					'terms_accepted' => array(
						'label' => 'I agree to the Terms & Conditions',
						'type' => 'checkbox',
						'required' => true,
						'enabled' => true,
					),
				),
			),
		);
	}

	/**
	 * Get saved form fields
	 */
	public static function get_fields() {
		$saved = get_option( 'ocb_form_fields' );
		if ( ! $saved ) {
			return self::get_default_fields();
		}
		return $saved;
	}

	/**
	 * Save form fields
	 */
	public static function save_fields( $fields ) {
		return update_option( 'ocb_form_fields', $fields );
	}

	/**
	 * Get field by ID
	 */
	public static function get_field( $step, $field_id ) {
		$fields = self::get_fields();
		if ( isset( $fields[ $step ]['fields'][ $field_id ] ) ) {
			return $fields[ $step ]['fields'][ $field_id ];
		}
		return null;
	}

	/**
	 * Check if field is enabled
	 */
	public static function is_field_enabled( $step, $field_id ) {
		$field = self::get_field( $step, $field_id );
		return $field && isset( $field['enabled'] ) ? $field['enabled'] : false;
	}

	/**
	 * Get all fields for a step
	 */
	public static function get_step_fields( $step ) {
		$fields = self::get_fields();
		if ( isset( $fields[ $step ]['fields'] ) ) {
			return array_filter(
				$fields[ $step ]['fields'],
				function( $field ) {
					return isset( $field['enabled'] ) && $field['enabled'];
				}
			);
		}
		return array();
	}

	/**
	 * Check if step is enabled
	 */
	public static function is_step_enabled( $step ) {
		$fields = self::get_fields();
		return isset( $fields[ $step ]['enabled'] ) ? $fields[ $step ]['enabled'] : false;
	}

	/**
	 * Get step title
	 */
	public static function get_step_title( $step ) {
		$fields = self::get_fields();
		return isset( $fields[ $step ]['title'] ) ? $fields[ $step ]['title'] : '';
	}

	/**
	 * Reset to defaults
	 */
	public static function reset_to_defaults() {
		self::save_fields( self::get_default_fields() );
	}
}
