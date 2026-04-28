<?php
/**
 * PLUGIN FILE GENERATED! Run composer build-plugin to rebuild.
 * 
 * Plugin Name: WDG Support Monitor
 * Version: dev-master
 * Description: Monitors site status for support.
 * Author: WDG - The Web Development Group
 * Author URI: https://www.webdevelopmentgroup.com
 * Text Domain: wdg-support-monitor
 * Update URI: https://plugins.wdg.dev
 */

namespace WDG\SupportMonitor;

require_once __DIR__ . '/vendor/autoload.php';

\WDG\SupportMonitor\Core::instance();