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
			$valid_url = wp_http_validate_url( constant( self::POST_URL_CONSTANT ) );

			if ( gettype( $valid_url ) === 'string' ) {
				$this->post_url = untrailingslashit( $valid_url );
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
	 * Gather our plugin data
	 * 
	 * @access private
	 * @return array - our data for sending to support
	 */
	
	private function compile() {
		$data = [];

		$plugins    = array_map( [ $this, 'model_plugin' ], get_plugins() );
		$mu_plugins = array_map( [ $this, 'model_mu_plugin' ], get_mu_plugins() );
		$dropins    = array_map( [ $this, 'model_dropin' ], get_dropins() );

		$all_plugins = array_merge( $plugins, $mu_plugins, $dropins );

		// Get updates
		$plugin_updates = get_site_transient( 'update_plugins' );

		// Get recently activated
		if ( is_multisite() ) {
			$recently_activated = get_site_option( 'recently_activated', array() );
		} else {
			$recently_activated = get_option( 'recently_activated', array() );
		}

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			// Extra info if known. array_merge() ensures $plugin_data has precedence if keys collide.
			if ( isset( $plugin_updates->response[ $plugin_file ] ) ) {
				$plugin_data = array_merge( (array) $plugin_updates->response[ $plugin_file ], $plugin_data );
			} elseif ( isset( $plugin_info->no_update[ $plugin_file ] ) ) {
				$plugin_data = array_merge( (array) $plugin_updates->no_update[ $plugin_file ], $plugin_data );
			}

			// Slug from filename
			if ( empty( $plugin_data['slug'] ) ) {
				$file = plugin_basename( $plugin_file );
				$slug = '.' !== dirname( $file ) ? dirname( $file ) : $file;
			} else {
				$slug = $plugin_data['slug'];
			}

			$plugin_update_data = array(
				'slug'    => $slug,
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'uri'     => $plugin_data['PluginURI'],
				'type'    => $plugin_data['type'],
			);

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
	 * Post our data to our support server
	 * 
	 * @param array $data - collection to be send to the server
	 * @param boolean $blocking - whether to wait for a response from the server
	 * @return mixed (boolean) - whether the data was successfully posted or not - always true if blocking is false
	 */

	public function post( $data = null, $blocking = true ) {

		flush(); // flush the output just in case we're on the front end

		if ( empty( $data ) ) {
			$data = $this->compile();
		}
		
		if ( !wp_http_validate_url( $this->post_url ) ) {
			return false;
		}

		$processed = false;

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
		}
		
		return $processed;
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
