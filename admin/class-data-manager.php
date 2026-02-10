<?php
/**
 * Data Management & Backup
 *
 * @package OnRoute_Courier_Booking
 */

class OnRoute_Courier_Booking_Data_Manager {

	public function __construct() {
		add_action( 'admin_post_ocb_backup_download', array( $this, 'download_backup' ) );
		add_action( 'admin_post_ocb_restore_backup', array( $this, 'restore_backup' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings() {
		register_setting( 'ocb_data_settings', 'ocb_delete_data_on_uninstall' );
	}

	public function render_page() {
		?>
		<div class="wrap ocb-admin">
			<h1><?php esc_html_e( 'OnRoute Courier Booking - Backup & Restore', 'onroute-courier-booking' ); ?></h1>
			<p>Backup and restore your full business data and plugin settings.</p>
			<?php $this->render_data_manager_only(); ?>
		</div>
		<?php
	}

	/**
	 * Render data manager content (for unified settings tab)
	 */
	public function render_data_manager_only() {
		?>
		<div class="ocb-data-management-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px;">
			<div class="card" style="margin: 0; padding: 20px;">
				<h2>Backup</h2>
				<p>Download a <strong>Full Business Backup</strong> (v2.0) of your bookings, agent accounts, promo codes, and settings as a JSON file.</p>
				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
					<input type="hidden" name="action" value="ocb_backup_download">
					<?php wp_nonce_field('ocb_backup_action'); ?>
					<button type="submit" class="button button-primary" style="height: 40px; padding: 0 20px; font-size: 14px;">Download Full Backup</button>
				</form>
				<p class="description" style="margin-top: 15px;">Use this file to restore everything if the plugin is reset or moved to a new site.</p>
			</div>

			<div class="card" style="margin: 0; padding: 20px;">
				<h2>Restore</h2>
				<p>Upload a JSON backup file to restore your business data. <strong>This will merge data safely using replacement logic.</strong></p>
				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="ocb_restore_backup">
					<?php wp_nonce_field('ocb_restore_action'); ?>
					<input type="file" name="backup_file" accept=".json" required style="margin-bottom: 15px;">
					<br>
					<button type="submit" class="button button-secondary" onclick="return confirm('Restore data? It is recommended to download a current backup first.')">Upload & Restore Data</button>
				</form>
				<?php if ( isset($_GET['restored']) ): ?>
					<div class="notice notice-success inline" style="margin-top: 15px;"><p>Data restored successfully. Imported items: <?php echo intval($_GET['count']); ?></p></div>
				<?php endif; ?>
                <?php if ( isset($_GET['error']) ): ?>
					<div class="notice notice-error inline" style="margin-top: 15px;"><p>Error: <?php echo esc_html($_GET['error']); ?></p></div>
				<?php endif; ?>
			</div>

			<div class="card" style="margin: 0; padding: 20px;">
				<h2>Uninstall Settings</h2>
				<p>Manage what happens to your data when the plugin is removed.</p>
				<form method="post" action="options.php">
					<?php settings_fields( 'ocb_data_settings' ); ?>
					<label style="display: block; margin-bottom: 10px; font-weight: 500;">
						<input type="checkbox" name="ocb_delete_data_on_uninstall" value="1" <?php checked( 1, get_option( 'ocb_delete_data_on_uninstall' ), true ); ?> />
						Delete all data (bookings & settings) when plugin is deleted?
					</label>
					<p class="description">Keep this UNCHECKED for safety if you plan to reinstall the plugin later.</p>
					<?php submit_button('Save Uninstall Preference'); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Download Backup JSON
	 */
	public function download_backup() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'ocb_backup_action' );

		global $wpdb;
		
		// 1. Export Tables
		$tables = array(
			'bookings'          => OnRoute_Courier_Booking_Database::get_bookings_table(),
			'business_accounts' => OnRoute_Courier_Booking_Database::get_business_accounts_table(),
			'promo_codes'       => OnRoute_Courier_Booking_Database::get_promos_table(),
			'email_logs'        => OnRoute_Courier_Booking_Database::get_email_logs_table(),
		);

		$table_data = array();
		foreach ( $tables as $key => $table ) {
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
				$table_data[ $key ] = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
			}
		}

		// 2. Export Options
		// Fetch all options starting with ocb_ or onroute_
		$options_results = $wpdb->get_results( 
			"SELECT option_name, option_value FROM {$wpdb->options} 
			 WHERE option_name LIKE 'ocb_%' OR option_name LIKE 'onroute_%'", 
			ARRAY_A 
		);

		$options_data = array();
		foreach ( $options_results as $opt ) {
			// Unserialize if necessary
			$options_data[ $opt['option_name'] ] = maybe_unserialize( $opt['option_value'] );
		}

		$backup_data = array(
			'version'       => '2.0',
			'date'          => current_time( 'mysql' ),
			'site_url'      => get_site_url(),
			'table_data'    => $table_data,
			'options_data'  => $options_data
		);

		$json = json_encode( $backup_data, JSON_PRETTY_PRINT );
		
		if ( false === $json ) {
			// If JSON encoding fails (usually due to binary/UTF-8 issues), try to fix common issues
			$json = json_encode( $backup_data, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE );
		}

		if ( false === $json ) {
			wp_die( 'Backup failed: Data could not be encoded to JSON. This may be due to malformed data or memory limits. Error: ' . json_last_error_msg() );
		}

		$filename = 'ocb_full_backup_' . date('Y-m-d_His') . '.json';

		// Clean all output buffers to ensure no whitespace/notices precede the JSON
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		echo $json;
		exit;
	}

	/**
	 * Restore Backup JSON
	 */
	public function restore_backup() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		check_admin_referer( 'ocb_restore_action' );

		if ( ! isset( $_FILES['backup_file'] ) || $_FILES['backup_file']['error'] !== 0 ) {
			wp_die( 'File upload failed.' );
		}

		$json = file_get_contents( $_FILES['backup_file']['tmp_name'] );
		$data = json_decode( $json, true );

		if ( ! $data ) {
            $redirect = add_query_arg('error', 'Invalid backup file format', admin_url('admin.php?page=ocb-tools&tab=backup'));
			wp_redirect($redirect);
            exit;
		}

		global $wpdb;
		$imported_stats = array();

		// Case 1: Legacy v1.0 Backup
		if ( isset($data['version']) && $data['version'] === '1.0' ) {
			// Restore Options
			if ( isset($data['options']) ) {
				foreach ( $data['options'] as $key => $val ) {
					update_option( $key, $val );
				}
			}
			// Restore Bookings
			$table = OnRoute_Courier_Booking_Database::get_bookings_table();
			$count = 0;
			foreach ( $data['bookings'] as $row ) {
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE booking_reference = %s", $row['booking_reference'] ) );
				if ( ! $exists ) {
					unset( $row['id'] );
					$wpdb->insert( $table, $row );
					$count++;
				}
			}
			$imported_stats['bookings'] = $count;
		} 
		// Case 2: Modern v2.0 Backup
		else {
			// 1. Restore Options
			if ( isset($data['options_data']) ) {
				foreach ( $data['options_data'] as $key => $val ) {
					update_option( $key, $val );
				}
			}

			// 2. Restore Tables
			if ( isset($data['table_data']) ) {
				$table_map = array(
					'bookings'          => OnRoute_Courier_Booking_Database::get_bookings_table(),
					'business_accounts' => OnRoute_Courier_Booking_Database::get_business_accounts_table(),
					'promo_codes'       => OnRoute_Courier_Booking_Database::get_promos_table(),
					'email_logs'        => OnRoute_Courier_Booking_Database::get_email_logs_table(),
				);

				foreach ( $data['table_data'] as $key => $rows ) {
					if ( ! isset($table_map[$key]) ) continue;
					$table = $table_map[$key];
					$count = 0;

					foreach ( $rows as $row ) {
						$should_insert = true;
						
						// Check for duplicates based on table type
						if ( $key === 'bookings' ) {
							$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE booking_reference = %s", $row['booking_reference'] ) );
							if ($exists) $should_insert = false;
						} elseif ( $key === 'promo_codes' ) {
							$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE code = %s", $row['code'] ) );
							if ($exists) $should_insert = false;
						} elseif ( $key === 'business_accounts' ) {
							$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE user_id = %d AND id = %d", $row['user_id'], $row['id'] ) );
							if ($exists) $should_insert = false;
						}

						if ( $should_insert ) {
							// For business accounts and logging, we might want to preserve IDs if possible,
							// but inserts usually handle auto-increment.
							// To be safe and "restore shb kisu", we use REPLACE if ID is present.
							$wpdb->replace( $table, $row );
							$count++;
						}
					}
					$imported_stats[$key] = $count;
				}
			}
		}

		$total_count = array_sum($imported_stats);
        $redirect = add_query_arg(array('restored' => 'true', 'count' => $total_count), admin_url('admin.php?page=ocb-tools&tab=backup'));
		wp_redirect($redirect);
		exit;
	}
}

