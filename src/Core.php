<?php

namespace WDG\SupportMonitor;

class Core {

	/**
	 * Package name
	 */
	const PACKAGE = 'wdgdc/wdg-wp-support-monitor';

	/**
	 * Package slug
	 */
	const SLUG = 'wdg-support-monitor';
	
	/**
	 * The url of the info.json file
	 *
	 * @var string
	 * @access public
	 */
	const UPDATE_INFO_FILE_URL = 'https://plugins.wdg.dev/info.json';
	

	/**
	 * Version of the package/plugin
	 *
	 * @return string Package version number or the current time (for cache busting).
	 */
	public static function version() {
		static $version;
		if ( ! isset( $version ) ) {
			$version = (string) time();
			if ( function_exists( '\get_plugin_data' ) && defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . self::SLUG . '/' . self::SLUG . '.php' ) ) {
				$plugin_data = \get_plugin_data( WP_PLUGIN_DIR . '/' . self::SLUG . '/' . self::SLUG . '.php' );
				if ( ! empty( $plugin_data['Version'] ) ) {
					$version = $plugin_data['Version'];
					return $version;
				}
			}
			
			if ( class_exists( '\Composer\InstalledVersions' ) && \Composer\InstalledVersions::isInstalled( self::PACKAGE ) ) {
				$pretty_version = \Composer\InstalledVersions::getPrettyVersion( self::PACKAGE );
				if ( isset( $pretty_version ) ) {
					$version = $pretty_version;
					return $version;
				}
			}
		}
		return $version;
	}

	/**
	 * @var \WDG\SupportMonitor\Core
	 */
	private static $instance;

	/**
	 * Get the singleton instance.
	 *
	 * @return \WDG\SupportMonitor\Core
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init all hooks.
	 */
	public function init_hooks() {
		// add our activate/deactivate/uninstall hooks
		register_activation_hook( __FILE__, __NAMESPACE__ . '\Monitor::activation_hook' );
		register_deactivation_hook( __FILE__, __NAMESPACE__ . '\Monitor::deactivation_hook' );
		register_uninstall_hook( __FILE__, __NAMESPACE__ . '\Monitor::uninstall_hook' );
		// update hook
		add_filter( 'update_plugins_plugins.wdg.dev', [ $this, 'plugin_info' ], 20, 4 );

	}
	

	/*
	 * $res empty at this step
	 * $action 'plugin_information'
	 * $args stdClass Object 
	 */
	// false, $plugin_data, $plugin_file, $locale
	public function plugin_info( $update, $plugin_data, $plugin_file, $locale ){

		if ( $plugin_file !== str_replace(  '/src', '/index.php', plugin_basename( __DIR__ ) ) ) {
			return $update;
		}
		
		$update = \wp_cache_get( $plugin_file, 'plugins' );
		
		if ( false === $update ) {

			// info.json is the file with the actual plugin information on your server
			$remote = wp_remote_get(
				self::UPDATE_INFO_FILE_URL,
				array(
					'timeout' => 10,
					'headers' => array(
						'Accept' => 'application/json'
					)
				)
			);

			// do nothing if we don't get the correct response from the server
			if (
				is_wp_error( $remote )
				|| 200 !== wp_remote_retrieve_response_code( $remote )
				|| empty( wp_remote_retrieve_body( $remote ) )
			) {
				return $update;
			}

			$remote = json_decode( wp_remote_retrieve_body( $remote ) );

			$update = new \stdClass();
			$update->name = $remote->name;
			$update->slug = $remote->slug;
			$update->author = $remote->author;
			$update->author_profile = $remote->author_profile;
			$update->version = $remote->version;
			$update->tested = $remote->tested ?? '6.9.4';
			$update->requires_php = $remote->requires_php ?? '8.1';
			$update->url = $remote->download_url;
			$update->package = $remote->download_url;
			if( ! empty( $remote->sections->screenshots ) ) {
				$update->sections[ 'screenshots' ] = $remote->sections->screenshots;
			}

			\wp_cache_set( $plugin_file, $update, 'plugins', HOUR_IN_SECONDS * 12 );
		}
		
		return $update;

	}


}
