<?php
/**
 * Plugin Name: WDG Support Monitor
 * Version: 1.0.1
 * Description: Monitors site status for support.
 * Author: WDG - The Web Development Group
 * Author URI: https://www.webdevelopmentgroup.com
 * Text Domain: wdg-support-monitor
 */

namespace WDG\SupportMonitor;

require_once __DIR__ . '/vendor/autoload.php';

// add our activate/deactivate/uninstall hooks
register_activation_hook( __FILE__, __NAMESPACE__ . '\Monitor::activation_hook' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\Monitor::deactivation_hook' );
register_uninstall_hook( __FILE__, __NAMESPACE__ . '\Monitor::uninstall_hook' );
