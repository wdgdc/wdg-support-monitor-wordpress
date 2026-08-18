<?php
/**
 * Zip plugin for local testing
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

$slug = \WDG\SupportMonitor\Core::SLUG;
$cmd  = "zip -r {$slug}-{$version}.zip . -x@.zip-exclude ";
echo $cmd;
shell_exec($cmd);

