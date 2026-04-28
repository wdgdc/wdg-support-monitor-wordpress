<?php
/**
 * Build plugin file
 */

// CLI Execution ONLY!
if ( 'cli' !== php_sapi_name() ) {
	exit;
}

require_once 'vendor/autoload.php';

// Collect (and discard) the script name
$script = array_shift( $argv );
// Collect version (string or null)
$version = array_shift( $argv );
if ( ! isset( $version ) ) {
	// Get version from plugin
	$version = \WDG\SupportMonitor\Core::version();
}

$template = <<<END
<?php
/**
 * PLUGIN FILE GENERATED! Run composer build-plugin to rebuild.
 * 
 * Plugin Name: WDG Support Monitor
 * Version: %s
 * Description: Monitors site status for support.
 * Author: WDG - The Web Development Group
 * Author URI: https://www.webdevelopmentgroup.com
 * Text Domain: wdg-support-monitor
 * Update URI: https://plugins.wdg.dev
 */

namespace WDG\SupportMonitor;

require_once __DIR__ . '/vendor/autoload.php';

\WDG\SupportMonitor\Core::instance();
END;

// File
$file = \WDG\SupportMonitor\Core::SLUG . '.php';

// Write plugin file
file_put_contents( $file, sprintf( $template, $version ) );


