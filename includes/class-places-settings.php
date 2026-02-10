<?php
/**
 * Google Places API Settings Admin Page
 * Allows admin to configure Google Places API key
 * 
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Courier_Booking_Places_Settings {

	/**
	 * Initialize the settings page
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Add settings page to admin menu
	 */
	public static function add_settings_page() {
		add_options_page(
			'OnRoute Google Places Settings',
			'OnRoute Places API',
			'manage_options',
			'onroute-places-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 */
	public static function register_settings() {
		register_setting( 'onroute_places_settings_group', 'onroute_google_places_api_key' );
	}

	/**
	 * Render settings page
	 */
	public static function render_settings_page() {
		?>
		<div class="wrap">
			<h1>OnRoute Courier - Google Places API Settings</h1>
			<p>Configure your Google Places API key for advanced address lookup.</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'onroute_places_settings_group' ); ?>
				<?php do_settings_sections( 'onroute_places_settings_group' ); ?>

				<table class="form-table">
					<thead>
						<tr>
							<th>Setting</th>
							<th>Value</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								<label for="onroute_google_places_api_key">Google Places API Key</label>
								<p class="description">
									Get your API key from <a href="https://console.cloud.google.com/apis/dashboard" target="_blank">Google Cloud Console</a>
									<br>Required APIs:
									<ul>
										<li>Places API</li>
										<li>Maps JavaScript API</li>
									</ul>
								</p>
							</td>
							<td>
								<input type="password" id="onroute_google_places_api_key" name="onroute_google_places_api_key" 
									value="<?php echo esc_attr( get_option( 'onroute_google_places_api_key', '' ) ); ?>" 
									style="width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								<button type="button" class="button" onclick="toggleApiKeyVisibility(event)">Show/Hide</button>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( 'Save Settings' ); ?>
			</form>

			<div style="margin-top: 40px; padding: 15px; background: #f5f5f5; border-radius: 4px;">
				<h3>Testing</h3>
				<p>Once you save the API key, Google Places results will be automatically used in the address lookup modal.</p>
				<p>The system will:</p>
				<ol>
					<li>First fetch from Nominatim (free, open data)</li>
					<li>Then fetch from Google Places (more accurate, requires API key)</li>
					<li>Combine and deduplicate results</li>
					<li>Show user the best address options</li>
				</ol>
			</div>
		</div>

		<script>
			function toggleApiKeyVisibility(e) {
				e.preventDefault();
				var input = document.getElementById('onroute_google_places_api_key');
				if (input.type === 'password') {
					input.type = 'text';
				} else {
					input.type = 'password';
				}
			}
		</script>
		<?php
	}
}

// Initialize on admin_loaded
if ( is_admin() ) {
	OnRoute_Courier_Booking_Places_Settings::init();
}
