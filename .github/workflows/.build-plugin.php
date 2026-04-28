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
	$version = \WDG\GTMAnalytics\Core::version();
}

// File
$file = 'index.php';

// Template
$template = file_get_contents( $file );

// Message
$message = 'PLUGIN FILE GENERATED! Run composer build-plugin to rebuild.';

// Write plugin file
file_put_contents( $file, sprintf( $template, $version, $message ) );


