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

}
