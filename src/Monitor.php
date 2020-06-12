<?php
/**
 * WordPress plugin controller
 *
 * @since 1.0.0
 * @package SupportMonitor
 */

namespace WDGDC\SupportMonitor;

final class Monitor {

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
		if ( ! isset( self::$_instance ) ) {
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
	 * The key that is generated from the supmon tool
	 *
	 * @access private
	 */
	private $secret_key;

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
	 * @return \WDGDC\SupportMonitor\Monitor
	 */
	private function __construct() {
		// define the secret key in the config file or hash the server name to enter in the supmon backend
		if ( defined( 'WDG_SUPPORT_MONITOR_SECRET_KEY' ) && ! empty( WDG_SUPPORT_MONITOR_SECRET_KEY ) ) {
			$this->secret_key = WDG_SUPPORT_MONITOR_SECRET_KEY;
		} else {
			$this->secret_key = hash( 'sha256', php_uname( 'n' ) );
		}

		// allow the domain to be set in wp-config.php for testing
		if ( defined( 'WDG_SUPPORT_MONITOR_POST_URL' ) && ! empty( WDG_SUPPORT_MONITOR_POST_URL ) && is_string( WDG_SUPPORT_MONITOR_POST_URL ) ) {
			$this->post_url = untrailingslashit( WDG_SUPPORT_MONITOR_POST_URL );
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

	public function get_last_run() {
		return $this->last_run;
	}

	/**
	 * Magic getter for referncing private (read-only) variables
	 *
	 * @param string
	 * @return mixed
	 * @access public
	 */
	public function __get( $param ) {
		if ( isset( $this->$param ) ) {
			return $this->$param;
		}
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
	 */
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
	 */
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

		$data = new \StdClass();

		// Current version
		$current_version = preg_replace( '/\-src$/', '', $GLOBALS['wp_version'] );
		$data->current = $current_version;

		// Recommended version
		wp_version_check();
		$api_version = get_site_transient( 'update_core' );

		if ( ! empty( $api_version->updates[0]->version ) ) {
			$data->recommended = $api_version->updates[0]->version;
		}

		return $data;
	}

	/**
	 * Compile our addon data
	 *
	 * @return array - collection of plugins and status
	 * @access private
	 */
	private function compile_addons() {
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
			if ( ! empty( $plugin_updates->response[ $plugin_file ] ) ) {
				$plugin_extra_data = (array) $plugin_updates->response[ $plugin_file ];
			} else if ( ! empty( $plugin_updates->no_update[ $plugin_file ] ) ) {
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

			$plugin_update_data = new \StdClass();
			$plugin_update_data->name = $slug;
			$plugin_update_data->display = $plugin_data['Name'];
			$plugin_update_data->type = $plugin_data['type'];
			$plugin_update_data->current = $plugin_data['Version'];
			$plugin_update_data->recommended = null;

			if ( isset( $plugin_updates->response[ $plugin_file ] ) ) {
				$plugin_update_data->recommended = $plugin_updates->response[ $plugin_file ]->new_version;
			}

			// mu-plugins and drop-ins are always active
			if ( 'plugin' === $plugin_data['type'] && ! is_plugin_active( $plugin_file ) && ! is_plugin_active_for_network( $plugin_file ) ) {
				$plugin_update_data->active = false;
			} else {
				$plugin_update_data->active = true;
			}

			// Recent state
			// $plugin_update_data['recent'] = isset( $recently_activated[ $plugin_file ] );

			array_push( $data, $plugin_update_data );
		}

		return $data;
	}

	/**
	 * Gather our core and plugin data
	 *
	 * @access public
	 * @return array - our data for sending to support
	 */
	public function compile() {
		$data = new \StdClass;
		$data->cms = 'Wordpress';
		$data->url = site_url();
		$data->key = $this->secret_key;
		$data->core = $this->compile_core();
		$data->addons = $this->compile_addons();

		return $data;
	}

	/**
	 * Private logger - currently only used with WP_CLI
	 *
	 * @param string $data - what you want to log
	 * @param string $method - the WP_CLI method to use (log, line, warning, error, success)
	 *
	 * @access private
	 */
	private function log( $data, $method = 'log' ) {
		// don't log anything if this is invoked from cron either as wp-cron or a wp-cli configured cron
		if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		if ( ! method_exists( 'WP_CLI', $method ) ) {
			$method = 'log';
		}

		// call_user_func( [ 'WP_CLI', $method ], strval( $data ) );
	}

	/**
	 * Post our data to our support server
	 *
	 * @param boolean $blocking - whether to wait for a response from the server
	 * @return mixed (boolean) - whether the data was successfully posted or not - always true if blocking is false
	 */
	public function post( $blocking = false ) {

		flush(); // flush the output just in case we're on the front end

		$data = $this->compile();

		if ( empty( $data ) ) {
			$this->log( 'No data to post!', 'error' );
			return false;
		}

		$this->log( 'Request', 'warning' );
		$this->log( sprintf( 'URL: %s', $this->post_url ) );

		$request_args = [
			'timeout' => 30,
			'blocking' => $blocking,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'body' => json_encode( $data )
		];

		foreach( $request_args as $arg => $val ) {
			if ( 'body' === $arg ) {
				$this->log( sprintf( '%s: %s', $arg, json_encode( $data, JSON_PRETTY_PRINT ) ) );
			} else {
				$this->log( sprintf( '%s: %s', $arg, ( is_array( $val ) ? json_encode( $val, JSON_PRETTY_PRINT ) : $val ) ) );
			}
		}

		if ( defined( 'WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST' ) && WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST ) {
			add_filter( 'http_request_host_is_external', '__return_true' );
		}

		if ( ! wp_http_validate_url( $this->post_url ) ) {
			$this->log( 'Invalid request URL! - ' . $this->post_url, 'error' );
			return false;
		}

		$request = wp_remote_post( $this->post_url, $request_args );

		if ( defined( 'WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST' ) && WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST ) {
			remove_filter( 'http_request_host_is_external', '__return_true' );
		}

		$this->log( '' );
		$this->log( 'Response', 'warning' );
		foreach( $request['headers'] as $name => $val ) {
			$this->log( sprintf( '%s: %s', $name, $val ) );
		}

		$this->log( '' );
		$this->log( strval( $request['body'] ) );

		if ( ! $blocking || $request['response']['code'] === 200 ) {
			$processed = true;

			$last_run = new \StdClass;
			$last_run->timestamp = current_time( 'mysql', 0 );
			$last_run->data = $data;

			$this->log( '' );
			$this->log( sprintf( 'Last Run: %s', print_r( $last_run, true) ) );

			update_option( self::LAST_RUN_KEY, $last_run );

			$this->log( 'Done!', 'success' );
			return true;
		}

		$this->log( 'Unable to post!', 'error' );
		return false;
	}

	/**
	 * Mark our plugin array as a standard plugin
	 *
	 * @param array $plugin
	 * @return array $plugin
	 *
	 * @access private
	 */
	private function model_plugin( $plugin ) {
		$plugin['type'] = 'plugin';
		return $plugin;
	}

	/**
	 * Mark our plugin array as a must-use plugin
	 *
	 * @param array $plugin
	 * @return array $plugin
	 *
	 * @access private
	 */
	private function model_mu_plugin( $plugin ) {
		$plugin['type'] = 'mu-plugin';
		return $plugin;
	}

	/**
	 * Mark our plugin array as a dropin plugin
	 *
	 * @param array $plugin
	 * @return array $plugin
	 *
	 * @access private
	 */
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
		if ( ! wp_next_scheduled ( self::EVENT ) ) {
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

		if ( ! empty( $timestamp ) ) {
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

Monitor::get_instance();
