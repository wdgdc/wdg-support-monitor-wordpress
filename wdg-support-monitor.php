<?php
/**
 * Plugin Name: WDG Support Monitor
 * Version: 0.1.0
 * Description: Monitors site status for support.
 * Author: Web Development Group (WDG)
 * Author URI: https://www.webdevelopmentgroup.com
 * Text Domain: wdg-support-monitor
 * Network: True
 */

namespace WDG_Support_Monitor;

use WP_CLI;

final class WDG_Support_Monitor {

	/**
	 * Name of our cron event
	 * 
	 * @access public
	 */
	const EVENT = 'wdg_support_monitor';

	/**
	 * Name of our last run setting
	 * 
	 * @access public
	 */
	const LAST_RUN_KEY = 'wdg_support_monitor_last_run';

	/**
	 * Singleton holder
	 * 
	 * @access private
	 */
	
	private static $_instance;

	/**
	 * singleton method for plugin, don't need more than one of these
	 * 
	 * @access public
	 */

	 public static function get_instance() {
		if ( !isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * The url domain our monitor should post to
	 * 
	 * @access private
	 */

	private $post_url = 'http://localhost';

	/**
	 * The name of the constant to override the post_url
	 * 
	 * @access private
	 */
	const POST_URL_CONSTANT = 'WDG_SUPPORT_MONITOR_POST_URL';

	/**
	 * The last time our cron was executed
	 * 
	 * @access private
	 */

	private $last_run;

	/**
	 * Plugin construct
	 *
	 * @access private
	 * @return WDG_Support_Monitor
	 */

	private function __construct() {
		// allow the domain to be set in wp-config.php for testing
		if ( defined( self::POST_URL_CONSTANT ) && !empty( constant( self::POST_URL_CONSTANT ) ) ) {
			if ( gettype( constant( self::POST_URL_CONSTANT ) ) === 'string' ) {
				$this->post_url = untrailingslashit( constant( self::POST_URL_CONSTANT ) );
			}
		}

		// run again if we don't know the last time it ran, or was over 12 hours ago (cron is probably disabled or having issues)
		$this->last_run = get_option( self::LAST_RUN_KEY );

		if ( empty( $this->last_run ) || empty( $this->last_run->timestamp ) || ( current_time('timestamp') - strtotime( $this->last_run->timestamp ) > 12 * HOUR_IN_SECONDS  ) ) {
			add_action( 'shutdown', [ $this, 'post' ] );
		}

		// add our post action to our cron event
		add_action( self::EVENT, [ $this, 'post' ] );

		// add our activate/deactivate/uninstall hooks
		register_activation_hook( __FILE__, [ __CLASS__, 'activation_hook' ] );
		register_deactivation_hook( __FILE__, [ __CLASS__, 'deactivation_hook' ] );
		register_uninstall_hook( __FILE__, [ __CLASS__, 'uninstall_hook' ] );
	}

	/**
	 * Ensure that all version strings have major.minor.patch when comparing
	 * 
	 * @param mixed (string|array) array or string semver number
	 * @return array - an array of major, minor, and patch versions
	 */

	private static function pad_version( $parts ) {
		if ( is_string( $parts ) ) {
			$parts = array_map( 'intval', explode('.', $parts ) );
		}
		
		if ( count( $parts ) < 3 ) {
			while( count( $parts ) < 3 ) {
				array_push( $parts, 0);
			}
		}

		return $parts;
	}

	/**
	 * The types of updates we consider
	 * 
	 * @access private
	 **/

	private static $update_types = [
		'major',
		'minor',
		'patch'
	];

	/**
	 * Get update type - a way of comparing versions to know if major, minor, or patch (according to semver at least)
	 * 
	 * @param mixed (string) - the current version
	 * @param mixed (string) - the comparison version
	 * @return string - major, update, minor, none, or ¯\_(ツ)_/¯
	 * @access private
	 **/

	 private static function get_update_type( $version, $update ) {
		if ( version_compare( $version, $update ) > -1 ) {
			return 'none';
		}

		$version = self::pad_version( $version );
		$update  = self::pad_version( $update );
		
		foreach( $update as $semver_index => $semver ) {
			if ( $semver > $version[ $semver_index ] ) {
				return self::$update_types[ $semver_index ];
			}
		}

		return '¯\_(ツ)_/¯';
	}

	/**
	 * Collect our available core updates
	 * 
	 * @return array - list of available major, minor, and patch updates
	 * @access private
	 */

	private function compile_core() {
		global $wp_version;

		wp_version_check();
		$api_version = get_site_transient( 'update_core' );

		$updates = array_combine( self::$update_types, array_map( '__return_empty_array', self::$update_types ) );

		$compare_version = preg_replace( '/\-src$/', '', $GLOBALS['wp_version'] );
		$compare_version_parts = self::pad_version( $compare_version );

		if ( !empty( $api_version->updates ) ) {

			foreach ( $api_version->updates as $update ) {
				$update_type = self::get_update_type( $compare_version, $update->version );

				// lets not add a second same version update to type
				if ( array_key_exists( $update_type, $updates ) && ( empty( $updates[ $update_type ] ) || !in_array( $update->version, array_column( $updates[ $update_type ], 'version' ) ) ) ) {
					array_push( $updates[ $update_type ], $update );
				}
			}
		}

		return array_filter( $updates );
	}

	/**
	 * Compile our plugin updates
	 * 
	 * @return array - collection of plugins and status
	 * @access private
	 */

	private function compile_plugins() {
		$data = [];

		// Get updates
		wp_update_plugins();
		$plugin_updates = get_site_transient( 'update_plugins' );

		$plugins    = array_map( [ $this, 'model_plugin' ], get_plugins() );
		$mu_plugins = array_map( [ $this, 'model_mu_plugin' ], get_mu_plugins() );
		$dropins    = array_map( [ $this, 'model_dropin' ], get_dropins() );

		$all_plugins = array_merge( $plugins, $mu_plugins, $dropins );

		// Get recently activated
		if ( is_multisite() ) {
			$recently_activated = get_site_option( 'recently_activated', array() );
		} else {
			$recently_activated = get_option( 'recently_activated', array() );
		}

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {			

			// Extra info if known. array_merge() ensures $plugin_data has precedence if keys collide.
			$plugin_extra_data = [];
			if ( !empty( $plugin_updates->response[ $plugin_file ] ) ) {
				$plugin_extra_data = (array) $plugin_updates->response[ $plugin_file ];
			} else if ( !empty( $plugin_updates->no_update[ $plugin_file ] ) ) {
				$plugin_extra_data = (array) $plugin_updates->no_update[ $plugin_file ];
			}

			$plugin_data = array_merge( $plugin_extra_data, $plugin_data );

			// Slug from filename
			if ( empty( $plugin_data['slug'] ) ) {
				$file = plugin_basename( $plugin_file );
				$slug = '.' !== dirname( $file ) ? dirname( $file ) : $file;
			} else {
				$slug = $plugin_data['slug'];
			}

			$plugin_update_data = array(
				'slug'        => $slug,
				'name'        => $plugin_data['Name'],
				'version'     => $plugin_data['Version'],
				'uri'         => $plugin_data['PluginURI'],
				'type'        => $plugin_data['type'],
				'update'      => false,
				'update_type' => null
			);

			if ( isset( $plugin_updates->response[ $plugin_file ] ) ) {
				$plugin_update_data[ 'update' ] = $plugin_updates->response[ $plugin_file ]->new_version;
				$plugin_update_data[ 'update_type' ] = self::get_update_type( $plugin_data['Version'], $plugin_updates->response[ $plugin_file ]->new_version );
			}

			// mu-plugins and drop-ins are always active
			if ( 'plugin' === $plugin_data['type'] && ! is_plugin_active( $plugin_file ) && ! is_plugin_active_for_network( $plugin_file ) ) {
				$plugin_update_data['active'] = false;
			} else {
				$plugin_update_data['active'] = true;
			}

			// Recent state
			$plugin_update_data['recent'] = isset( $recently_activated[ $plugin_file ] );

			array_push( $data, $plugin_update_data );
		}

		return $data;
	}

	/**
	 * Gather our core and plugin data
	 * 
	 * @access private
	 * @return array - our data for sending to support
	 */
	
	private function compile() {
		$data = new \StdClass;
		$data->core = $this->compile_core();
		$data->plugins = $this->compile_plugins();

		return $data;
	}

	/**
	 * Post our data to our support server
	 * 
	 * @param array $data - collection to be send to the server
	 * @param boolean $blocking - whether to wait for a response from the server
	 * @return mixed (boolean) - whether the data was successfully posted or not - always true if blocking is false
	 */

	public function post( $data = null, $blocking = false ) {

		flush(); // flush the output just in case we're on the front end

		if ( empty( $data ) ) {
			$data = $this->compile();
		}
		
		if ( !wp_http_validate_url( $this->post_url ) ) {
			return false;
		}

		$request = wp_remote_post( $this->post_url, [
			'timeout' => 30,
			'blocking' => $blocking,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'body' => json_encode( $data )
		] );

		if ( !$blocking || $request['response']['code'] === 200 ) {
			$processed = true;
			
			$last_run = new \StdClass;
			$last_run->timestamp = current_time( 'mysql', 0 );
			$last_run->data = $data;
			
			update_option( self::LAST_RUN_KEY, $last_run );

			return true;
		}

		return false;
	}

	private function model_plugin( $plugin ) {
		$plugin['type'] = 'plugin';
		return $plugin;
	}
	
	private function model_mu_plugin( $plugin ) {
		$plugin['type'] = 'mu-plugin';
		return $plugin;
	}
	
	private function model_dropin( $plugin ) {
		$plugin['type'] = 'drop-in';
		return $plugin;
	}

	/**
	 * Schedule our cron hook
	 * 
	 * @access private
	 * @return boolean - false if there was a problem scheduling the event
	 */

	private function schedule() {
		if ( !wp_next_scheduled ( self::EVENT ) ) {
			return wp_schedule_event( time(), 'twicedaily', self::EVENT ) !== false;
		}

		return true;
	}

	/**
	 * Unschedule our cron hook
	 * 
	 * @access private
	 * @return boolean - false if there was a problem unscheduling the event
	 */

	private function unschedule() {
		$timestamp = wp_next_scheduled( self::EVENT );
		
		if ( !empty( $timestamp ) ) {
			return wp_unschedule_event( $timestamp, self::EVENT ) !== false;
		}

		return true;
	}

	/**
	 * Schedule our cron on activation
	 * 
	 * @access public
	 * @return undefined
	 */
	
	public static function activation_hook() {
		self::get_instance()->schedule();
	}

	/**
	 * Placeholder for deletion hook to delete our options data
	 * 
	 * @access public
	 * @return undefined
	 */

	public static function deactivation_hook() {
		self::get_instance()->unschedule();
	}

	/**
	 * Placeholder for deletion hook to delete our options data
	 * 
	 * @access public
	 * @return undefined
	 */

	public static function uninstall_hook() {
		delete_option( self::LAST_RUN_KEY );
	}
}

WDG_Support_Monitor::get_instance();
