<?php

namespace WDG\SupportMonitor;

class Scripts {
	public static function release( $event ) {
		try {
			$version   = self::get_plugin_version();
			$file_name = "wdg-support-monitor-{$version}.zip";
			exec( 'rm -rf ./vendor' );
			exec( 'composer install --no-dev --prefer-dist --optimize-autoloader' );
			exec( 'mkdir ./wdg-support-monitor && cp -r src vendor composer.json index.php LICENSE README.md wdg-support-monitor');
			self::create_info_json( $version, $file_name );
			exec( sprintf( 'zip -r %s wdg-support-monitor', $file_name ) );
			exec( 'rm -rf ./wdg-support-monitor' );
			
			echo "\n\nPlease make sure to upload the file {$file_name} to the wdgdev plugins directory";
		} catch ( \Throwable $e ) {
			$event->getIO()->writeError( $e->getMessage() );
		}
	}
	
	private static function create_info_json( $version, $file_name ) {
	
		$info_json = [
			'name' => "WDG Support Monitor",	
			"slug" => "wdg-support-monitor",
			'author' => 'WDG - The Web Development Group',
			'author_profile' => 'https://www.webdevelopmentgroup.com',
			'version' => $version,
			'download_url' => 'https://plugins.wdg.dev/plugins/' . $file_name
		];
		
		file_put_contents( dirname( __DIR__ ) . '/wdg-support-monitor/info.json', json_encode( $info_json, JSON_PRETTY_PRINT ) );
		
	}

	/**
	 * Parse the plugin version from the main plugin file header.
	 *
	 * @return string
	 */
	private static function get_plugin_version() {
		$plugin_file = dirname( __DIR__ ) . '/index.php';
		$contents    = file_get_contents( $plugin_file );

		if ( false === $contents ) {
			throw new \RuntimeException( 'Unable to read plugin file.' );
		}

		if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $contents, $matches ) ) {
			throw new \RuntimeException( 'Unable to find plugin version in file header.' );
		}

		return trim( $matches[1] );
	}
}