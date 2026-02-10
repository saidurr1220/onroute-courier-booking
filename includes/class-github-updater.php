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
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->slug ) {
			return $source;
		}

		$plugin_name = dirname( $this->slug );
		$new_source = trailingslashit( $remote_source ) . $plugin_name;

		if ( rename( $source, $new_source ) ) {
			return $new_source;
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

		$url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases/latest";
		
		// Add access token if provided (for private repos)
		$args = array();
		if ( ! empty( $this->accessToken ) ) {
			$args['headers'] = array(
				'Authorization' => "token {$this->accessToken}",
			);
		}

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$this->githubAPIResult = json_decode( wp_remote_retrieve_body( $response ) );
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

		$latest_version = str_replace( 'v', '', $repo_info->tag_name );
		$current_version = ONROUTE_COURIER_BOOKING_VERSION;

		if ( version_compare( $latest_version, $current_version, '>' ) ) {
			$obj = new stdClass();
			$obj->slug        = $this->slug;
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
		if ( ! isset( $args->slug ) || $args->slug !== $this->slug ) {
			return $false;
		}

		$repo_info = $this->get_repository_info();

		if ( ! $repo_info ) {
			return $false;
		}

		$api_obj = new stdClass();
		$api_obj->name           = 'OnRoute Courier Booking';
		$api_obj->slug           = $this->slug;
		$api_obj->version        = str_replace( 'v', '', $repo_info->tag_name );
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
