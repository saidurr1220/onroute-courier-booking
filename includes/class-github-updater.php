<?php
/**
 * GitHub Updater for OnRoute Courier Booking
 *
 * @package OnRoute_Courier_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OnRoute_Courier_Booking_GitHub_Updater {

	private $slug;
	private $pluginData;
	private $username;
	private $repo;
	private $githubAPIResult;
	private $accessToken;

	/**
	 * Constructor
	 */
	public function __construct( $pluginFile, $username, $repo, $accessToken = '' ) {
		$this->slug        = plugin_basename( $pluginFile );
		$this->username    = $username;
		$this->repo        = $repo;
		$this->accessToken = $accessToken;

		// Hook into the update transient
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		
		// Hook into the plugin details popup
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );

		// Fix folder name during update
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_selection' ), 10, 4 );
	}

	/**
	 * Rename the source folder to match the plugin slug during installation.
	 * GitHub zipballs create folders like 'repo-name-tag'.
	 */
	public function fix_source_selection( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		// Check if it's our plugin being installed/updated
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->slug ) {
			return $source;
		}

		// The folder name we want (e.g., 'onroute-courier-booking')
		$plugin_name = 'onroute-courier-booking';
		$new_source  = trailingslashit( $remote_source ) . $plugin_name;

		// If the source (extracted GitHub folder) is different from our plugin folder name
		if ( $source !== $new_source ) {
			// If a folder with the same name already exists in the temp directory, remove it
			if ( $wp_filesystem->is_dir( $new_source ) ) {
				$wp_filesystem->delete( $new_source, true );
			}

			// Move the extracted folder to the correct name
			if ( $wp_filesystem->move( $source, $new_source, true ) ) {
				return $new_source;
			}
		}

		return $source;
	}

	/**
	 * Get GitHub Repository Info
	 */
	private function get_repository_info() {
		if ( ! is_null( $this->githubAPIResult ) ) {
			return $this->githubAPIResult;
		}

		// Using /releases instead of /releases/latest to support pre-releases if needed
		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases";
		
		// Add default args with User-Agent required by GitHub
		$args = array(
			'headers' => array(
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			),
		);

		// Add access token if provided (for private repos)
		if ( ! empty( $this->accessToken ) ) {
			$args['headers']['Authorization'] = "token {$this->accessToken}";
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$results = json_decode( wp_remote_retrieve_body( $response ) );
		
		// If using /releases, it returns an array. We take the most recent one.
		if ( is_array( $results ) && ! empty( $results ) ) {
			$this->githubAPIResult = $results[0];
		} else {
			$this->githubAPIResult = false;
		}

		return $this->githubAPIResult;
	}

	/**
	 * Check for Update
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$repo_info = $this->get_repository_info();

		if ( ! $repo_info || ! isset( $repo_info->tag_name ) ) {
			return $transient;
		}

		$latest_version = ltrim( $repo_info->tag_name, 'v' );
		$current_version = ONROUTE_COURIER_BOOKING_VERSION;

		if ( version_compare( $latest_version, $current_version, '>' ) ) {
			$obj = new stdClass();
			$obj->slug        = 'onroute-courier-booking'; // Explicit short slug
			$obj->plugin      = $this->slug;            // Full: 'onroute-courier-booking/onroute-courier-booking.php'
			$obj->new_version = $latest_version;
			$obj->url         = "https://github.com/{$this->username}/{$this->repo}";
			$obj->package     = $repo_info->zipball_url;

			$transient->response[ $this->slug ] = $obj;
		}

		return $transient;
	}

	/**
	 * Show Plugin Details in Popup
	 */
	public function plugin_popup( $false, $action, $args ) {
		$short_slug = 'onroute-courier-booking';
		
		if ( ! isset( $args->slug ) || ( $args->slug !== $this->slug && $args->slug !== $short_slug ) ) {
			return $false;
		}

		$repo_info = $this->get_repository_info();

		if ( ! $repo_info ) {
			return $false;
		}

		$api_obj = new stdClass();
		$api_obj->name           = 'OnRoute Courier Booking';
		$api_obj->slug           = $short_slug;
		$api_obj->version        = ltrim( $repo_info->tag_name, 'v' );
		$api_obj->author         = '<a href="https://saidur-it.vercel.app">Md. Saidur Rahman</a>';
		$api_obj->homepage       = "https://github.com/{$this->username}/{$this->repo}";
		$api_obj->requires       = '5.8';
		$api_obj->tested         = '6.4';
		$api_obj->last_updated   = $repo_info->published_at;
		$api_obj->sections       = array(
			'description' => 'Automatically updated from GitHub repository.',
			'changelog'   => $repo_info->body,
		);
		$api_obj->download_link  = $repo_info->zipball_url;

		return $api_obj;
	}
}
