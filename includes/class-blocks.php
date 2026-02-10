<?php
/**
 * Gutenberg Block Registration
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Blocks {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register Gutenberg blocks
	 */
	public function register_blocks() {
		// Register the booking form block
		register_block_type(
			ONROUTE_COURIER_BOOKING_PATH . 'blocks/booking-form',
			array(
				'render_callback' => array( $this, 'render_booking_form_block' ),
			)
		);
	}

	/**
	 * Render booking form block
	 */
	public function render_booking_form_block( $attributes ) {
		return do_shortcode( '[onroute_courier_booking_form]' );
	}
}
