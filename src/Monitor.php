<?php
/**
 * WordPress plugin controller
 *
 * @since 1.0.0
 * @package SupportMonitor
 */

namespace WDG\SupportMonitor;

final class Monitor {

	/**
	 * Name of our cron event
	 *
	 * @var string
	 * @access public
	 */
	const EVENT = 'wdg_support_monitor';

	/**
	 * Name of our last run setting
	 *
	 * @var string
	 * @access public
	 */
	const LAST_RUN_KEY = 'wdg_support_monitor_last_run';

	/**
	 * Singleton holder
	 *
	 * @var \WDG\SupportMonitor\Monitor
	 * @access private
	 * @static
	 */
	private static $_instance;

	/**
	 * singleton method for plugin, don't need more than one of these
	 *
	 * @return \WDG\SupportMonitor\Monitor
	 * @access public
	 * @static
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
	 * @var string
	 * @access private
	 */
	private $api_endpoint;

	/**
	 * The key that is generated from the supmon tool
	 *
	 * @var string
	 * @access private
	 */
	private $api_secret;

	/**
	 * The last time our cron was executed - saved to the options table
	 *
	 * @var \StdClass
	 * @access private
	 */
	private $last_run;

	/**
	 * Plugin construct
	 *
	 * @access private
	 * @return \WDG\SupportMonitor\Monitor
	 */
	private function __construct() {
		// define the secret key in the config file or hash the server name to enter in the supmon backend
		if ( defined( 'WDG_SUPPORT_MONITOR_API_SECRET' ) && ! empty( WDG_SUPPORT_MONITOR_API_SECRET ) ) {
			$this->api_secret = WDG_SUPPORT_MONITOR_API_SECRET;
		} else {
			$this->api_secret = hash( 'sha256', php_uname( 'n' ) );
		}

		// allow the domain to be set in wp-config.php for testing
		if ( defined( 'WDG_SUPPORT_MONITOR_API_ENDPOINT' ) && ! empty( WDG_SUPPORT_MONITOR_API_ENDPOINT ) && is_string( WDG_SUPPORT_MONITOR_API_ENDPOINT ) ) {
			$this->api_endpoint = untrailingslashit( WDG_SUPPORT_MONITOR_API_ENDPOINT );
		}

		// don't schedule an event if we're misconfigured
		if ( empty( $this->api_secret ) || empty( $this->api_endpoint ) ) {
			return;
		}

		// modify site_url to use constant if defined
		if ( defined( 'WDG_SUPPORT_MONITOR_SITE_URL' ) && WDG_SUPPORT_MONITOR_SITE_URL ) {
			// return apply_filters( 'site_url', $url, $path, $scheme, $blog_id );
			add_filter(
				'site_url',
				function ( $url ) {
					return WDG_SUPPORT_MONITOR_SITE_URL;
				}
			);
		}

		dd( site_url() );

		// allow localhost api endpoint requests for testing
		if ( defined( 'WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST' ) && WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST ) {
			add_filter(
				'http_request_host_is_external',
				function ( $external, $host, $url ) {
					if ( $url === $this->get_api_endpoint() ) {
						return true;
					}
					return $external;
				},
				10,
				3
			);
		}

		$this->get_last_run();

		// add our post action to our cron event
		add_action( self::EVENT, [ $this, 'post' ] );
	}

	/**
	 * Get the last run of the monitor
	 *
	 * @return \StdClass
	 */
	public function get_last_run() {
		if ( ! isset( $this->last_run ) ) {
			$this->last_run = get_option( self::LAST_RUN_KEY );
		}

		return $this->last_run;
	}

	/**
	 * Get the api endpoint of the monitor
	 *
	 * @return \StdClass
	 */
	public function get_api_endpoint() {
		return $this->api_endpoint;
	}

	/**
	 * Get the api secret of the monitor
	 *
	 * @return \StdClass
	 */
	public function get_api_secret() {
		return $this->api_secret;
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
		$data            = new \StdClass;
		$data->url       = site_url();
		$data->timestamp = time();
		$data->key       = hash( 'sha256', $data->url . $this->api_secret . $data->timestamp );
		$data->core      = $this->compile_core();
		$data->addons    = $this->compile_addons();

		return $data;
	}

	/**
	 * Post our data to our support server
	 *
	 * @param boolean $blocking - whether to wait for a response from the server
	 * @return \WP_Error|\StdClass (boolean) - whether the data was successfully posted or not - always true if blocking is false
	 */
	public function post( $blocking = false ) {
		flush(); // flush the output just in case we're on the front end

		$data = $this->compile();

		if ( empty( $data ) ) {
			return new \WP_Error( 'no-data', 'No data to send!' );
		}

		$request_args = [
			'timeout' => 30,
			'blocking' => $blocking,
			'headers' => [
				'Content-Type' => 'application/json'
			],
			'body' => json_encode( $data )
		];

		if ( ! wp_http_validate_url( $this->api_endpoint ) ) {
			return new \WP_Error( 'invalid-api-endpoint', sprintf( 'Invalid API Endpoint: %s', strval( $this->api_endpoint ) ) );
		}

		$request = wp_remote_post( $this->api_endpoint, $request_args );

		$response = new \StdClass;

		$response->timestamp = current_time( 'mysql', 0 );
		$response->data      = $data;
		$response->request   = $request;

		if ( ! $blocking || ( $request['response']['code'] >= 200 && $request['response']['code'] < 300 ) ) {
			$response->success = true;
		} else {
			$response->success = true;
		}

		update_option( self::LAST_RUN_KEY, $response );

		return $response;
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
	 * @access public
	 * @return boolean - false if there was a problem scheduling the event
	 */
	public function schedule() {
		if ( ! wp_next_scheduled ( self::EVENT ) ) {
			return wp_schedule_event( time(), 'twicedaily', self::EVENT );
		}

		return true;
	}

	/**
	 * Unschedule our cron hook
	 *
	 * @access public
	 * @return boolean - false if there was a problem unscheduling the event
	 */
	public function unschedule() {
		$timestamp = wp_next_scheduled( self::EVENT );

		if ( ! empty( $timestamp ) ) {
			return wp_unschedule_event( $timestamp, self::EVENT );
		}

		return true;
	}

	/**
	 * Schedule our cron on activation
	 *
	 * @access public
	 * @return void
	 */
	public static function activation_hook() {
		self::get_instance()->schedule();
	}

	/**
	 * Placeholder for deletion hook to delete our options data
	 *
	 * @access public
	 * @return void
	 */
	public static function deactivation_hook() {
		self::get_instance()->unschedule();
	}

	/**
	 * Placeholder for deletion hook to delete our options data
	 *
	 * @access public
	 * @return void
	 */
	public static function uninstall_hook() {
		delete_option( self::LAST_RUN_KEY );
	}
}

Monitor::get_instance();
