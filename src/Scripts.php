<?php

namespace WDG\SupportMonitor;

class Scripts {
	public static function release( $event ) {
		try {
			$fileName = "wdg-support-monitor-wordpress-{$event->getComposer()->getPackage()->getVersion()}.zip";

			exec( 'rm -rf ./vendor' );
			exec( 'composer install --no-dev --prefer-dist --optimize-autoloader' );
			exec( 'mkdir ./wdg-support-monitor && cp -r src vendor composer.json index.php LICENSE README.md wdg-support-monitor');
			exec( sprintf( 'zip -r %s wdg-support-monitor', $fileName ) );
			exec( 'rm -rf ./wdg-support-monitor' );
		} catch ( \Throwable $e ) {
			$event->getIO()->writeError( $e->getMessage() );
		}
	}
}