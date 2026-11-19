<?php
/**
 * This file allows composer to autoload when it's a dependency of a theme or other plugin
 *
 * @package WDG\SupportMonitor
 * @since 1.0.0
 */
namespace WDG\SupportMonitor;

\WDG\SupportMonitor\Monitor::get_instance();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'wdg-support-monitor', __NAMESPACE__ . '\\Command' );
}
