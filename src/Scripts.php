<?php

namespace WDG\SupportMonitor;

class Scripts {
	public static function release( $event ) {
		try {
			$fileName = "wdg-support-monitor-wordpress-{$event->getComposer()->getPackage()->getVersion()}.zip";

			exec( 'rm -rf ./vendor' );
			exec( 'composer install --no-dev --prefer-dist --optimize-autoloader' );
			exec( sprintf( 'zip -r %s src vendor composer.json index.php LICENSE README.md', $fileName ) );
		} catch ( \Throwable $e ) {
			$event->getIO()->writeError( $e->getMessage() );
		}
	}
}