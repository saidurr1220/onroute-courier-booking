<?php
/**
 * Admin Form Settings Page
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Form_Admin_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add menu page
	 */
	public function add_menu_page() {
		add_submenu_page(
			'onroute-courier-booking',
			'Form Settings',
			'Form Settings',
			'manage_options',
			'onroute-form-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'ocb-form-settings', 'ocb_form_fields' );
	}

	/**
	 * Render settings page
	 */
	public function render_page() {
		?>
		<div class="wrap ocb-admin">
			<h1><?php esc_html_e( 'OnRoute Courier Booking - Form Settings', 'onroute-courier-booking' ); ?></h1>
			<p>Customize the labels and visibility of fields in your booking form steps.</p>
			<?php $this->render_field_settings_only(); ?>
		</div>
		<?php
	}

	/**
	 * Render field settings only (for unified settings tab)
	 */
	public function render_field_settings_only() {
		?>
		<div class="ocb-settings-tabs">
			<ul class="subsubsub" style="float: none; margin-bottom: 20px;">
				<li><a href="#step1-fields" class="ocb-inner-tab ocb-tab-active">Step 1: Quote Fields</a> |</li>
				<li><a href="#step3-fields" class="ocb-inner-tab">Step 3: Booking Details Fields</a></li>
			</ul>

			<!-- Step 1 Fields -->
			<div id="step1-fields" class="ocb-tab-content ocb-tab-active">
				<h3><?php esc_html_e( 'Step 1: Quote Form Fields', 'onroute-courier-booking' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field ID', 'onroute-courier-booking' ); ?></th>
							<th><?php esc_html_e( 'Display Label', 'onroute-courier-booking' ); ?></th>
							<th><?php esc_html_e( 'Field Type', 'onroute-courier-booking' ); ?></th>
							<th style="text-align: center;"><?php esc_html_e( 'Required', 'onroute-courier-booking' ); ?></th>
							<th style="text-align: center;"><?php esc_html_e( 'Is Active', 'onroute-courier-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php $this->render_field_rows( 1 ); ?>
					</tbody>
				</table>
			</div>

			<!-- Step 3 Fields -->
			<div id="step3-fields" class="ocb-tab-content">
				<h3><?php esc_html_e( 'Step 3: Booking Details Form Fields', 'onroute-courier-booking' ); ?></h3>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field ID', 'onroute-courier-booking' ); ?></th>
							<th><?php esc_html_e( 'Display Label', 'onroute-courier-booking' ); ?></th>
							<th><?php esc_html_e( 'Field Type', 'onroute-courier-booking' ); ?></th>
							<th style="text-align: center;"><?php esc_html_e( 'Required', 'onroute-courier-booking' ); ?></th>
							<th style="text-align: center;"><?php esc_html_e( 'Is Active', 'onroute-courier-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php $this->render_field_rows( 3 ); ?>
					</tbody>
				</table>
			</div>
		</div>

		<style>
			.ocb-tab-content { display: none; margin-bottom: 20px; }
			.ocb-tab-content.ocb-tab-active { display: block; }
			.ocb-inner-tab.ocb-tab-active { font-weight: bold; color: #000; }
		</style>

		<script>
			jQuery(document).ready(function($) {
				$('.ocb-inner-tab').on('click', function(e) {
					e.preventDefault();
					$('.ocb-inner-tab').removeClass('ocb-tab-active');
					$('.ocb-tab-content').removeClass('ocb-tab-active');
					$(this).addClass('ocb-tab-active');
					$($(this).attr('href')).addClass('ocb-tab-active');
				});
			});
		</script>
		<?php
	}

	/**
	 * Render field rows for a specific step
	 *
	 * @param int $step The step number (1 or 3).
	 */
	private function render_field_rows( $step ) {
		$fields = OnRoute_Courier_Booking_Form_Builder::get_step_fields( $step );
		$all_fields = OnRoute_Courier_Booking_Form_Builder::get_form_fields();

		foreach ( $fields as $field_name => $field_config ) {
			$field_info = $all_fields[ $field_name ] ?? array();
			?>
			<tr>
				<td>
					<code><?php echo esc_html( $field_name ); ?></code>
				</td>
				<td>
					<input type="text" name="ocb_field_label_<?php echo esc_attr( $field_name ); ?>" 
						value="<?php echo esc_attr( $field_info['label'] ?? '' ); ?>" />
				</td>
				<td>
					<code><?php echo esc_html( $field_info['type'] ?? 'text' ); ?></code>
				</td>
				<td style="text-align: center;">
					<input type="checkbox" disabled <?php checked( ! empty( $field_info['required'] ) ); ?> />
				</td>
				<td style="text-align: center;">
					<input type="checkbox" name="ocb_field_active_<?php echo esc_attr( $field_name ); ?>" 
						value="1" <?php checked( ! empty( $field_info['active'] ), true ); ?> />
				</td>
			</tr>
			<?php
		}
	}
}
