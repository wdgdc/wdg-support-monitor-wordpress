# WDG Support Monitor for WordPress

WDG Support Monitor collects a small site status payload and posts it to a configured support endpoint. The report is built from WordPress core update data, installed addons, and a small `extras` payload. When the cron event is scheduled, the monitor runs on WordPress's `twicedaily` schedule.

The package can be used in three ways:

- as a standard WordPress plugin
- as a Composer dependency loaded by a theme or another plugin
- as a Composer-backed MU-plugin loader

## Overview

At runtime the monitor:

- builds a payload for the current site
- signs the payload with the site URL, the configured secret, and the current timestamp
- posts the payload to `WDG_SUPPORT_MONITOR_API_ENDPOINT`
- stores the last run result in the `wdg_support_monitor_last_run` option

## What Is Reported

Each report includes:

- the site URL
- a timestamp
- a request key derived from the site URL, secret, and timestamp
- WordPress core `current` and `recommended` versions
- installed standard plugins, MU-plugins, and drop-ins
- each addon's slug, display name, type, installed version, available update version when present, and active state
- `extras` data

The default `extras` payload currently includes:

- `php_version`
- `wdg-core-version` when Composer metadata is available and `wdgdc/wikit-core` is installed

## Installation

### Standard Plugin

This is the preferred install mode when you want WordPress to treat the package as a normal plugin.

1. Install the packaged plugin in `wp-content/plugins/wdg-support-monitor`.
2. Define the required configuration constants in `wp-config.php`.
3. Activate the plugin in WordPress.
4. Confirm or create the cron schedule with `wp wdg-support-monitor schedule`.

The packaged plugin entry file is `wdg-support-monitor.php`. It loads Composer autoloading and boots the plugin's update integration.

### Composer

Install the package as a dependency:

```bash
composer require wdgdc/wdg-wp-support-monitor
```

Then make sure your application loads Composer's autoloader.

Composer autoloading is configured to load `src/index.php` automatically, so the monitor instance and WP-CLI command are bootstrapped as soon as `vendor/autoload.php` is required.

Use this mode when the package should live inside a theme, another plugin, or a larger Composer-managed project. In this mode you should manage scheduling as part of deployment or with WP-CLI.

### MU-Plugin

For MU-plugin usage, install the package with Composer somewhere your WordPress codebase can reach, then add a small loader file in `wp-content/mu-plugins`.

The loader should require Composer's autoloader, not `src/index.php`, because Composer autoloading already boots `src/index.php`.

Example MU-plugin loader:

```php
<?php
/**
 * Plugin Name: WDG Support Monitor Loader
 */

require_once WPMU_PLUGIN_DIR . '/../vendor/autoload.php';
```

Adjust the path to `vendor/autoload.php` for your project structure.

As with a normal Composer install, use WP-CLI or your deployment/bootstrap process to ensure the cron event is scheduled.

## Configuration Constants

Add these constants in `wp-config.php` or another early bootstrap location:

```php
define( 'WDG_SUPPORT_MONITOR_API_ENDPOINT', 'https://example.com/api/site-status' );
define( 'WDG_SUPPORT_MONITOR_API_SECRET', 'replace-with-shared-secret' );
define( 'WDG_SUPPORT_MONITOR_SITE_URL', 'https://example.com' );
define( 'WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST', true );
```

Supported constants:

- `WDG_SUPPORT_MONITOR_API_ENDPOINT`: required. Absolute URL that receives the JSON report.
- `WDG_SUPPORT_MONITOR_API_SECRET`: optional but recommended. Shared secret used when building the request key. If omitted, the plugin falls back to a hash of the server hostname.
- `WDG_SUPPORT_MONITOR_SITE_URL`: optional. Overrides `site_url()` in the outgoing payload.
- `WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST`: optional. When truthy, allows requests to the configured endpoint even when it resolves as a local host during development.

If the endpoint constant is missing or empty, the monitor does not finish bootstrapping and no cron event should be scheduled.

## WP-CLI Commands

The package registers `wp wdg-support-monitor`.

Available subcommands:

- `wp wdg-support-monitor info`: shows the configured endpoint, secret, site URL, last run, and next scheduled run
- `wp wdg-support-monitor report`: builds the report locally without sending it
- `wp wdg-support-monitor update`: sends the report with a blocking HTTP request and prints the response status and body
- `wp wdg-support-monitor schedule`: schedules the `wdg_support_monitor` cron event
- `wp wdg-support-monitor unschedule`: removes the scheduled cron event

`info`, `report`, and `update` support `--format=yaml|json|table`. `report` and `update` also support `--pretty=true|false` when using JSON output.

## Extras Filter

Use the `wdg_support_monitor_extras` filter to add or change custom metadata before the report is sent:

```php
add_filter(
	'wdg_support_monitor_extras',
	function ( $extras ) {
		$extras['environment'] = wp_get_environment_type();
		$extras['region']      = 'us-east-1';

		return $extras;
	}
);
```

Return a plain associative array. The default extras are provided first, then your filter runs.

## Update Behavior

Packaged plugin builds include a generated `wdg-support-monitor.php` file with:

- `Update URI: https://plugins.wdg.dev`
- remote release metadata fetched from `https://plugins.wdg.dev/info.json`

This update flow is intended for the packaged standard plugin install. Composer and MU-plugin installs should continue to use Composer-based dependency management instead of the plugin zip update path.

## Development Notes

- `wdg-support-monitor.php` is a generated file. Rebuild it with `composer build-plugin` or `php .build-plugin.php [version]`.
- Composer autoloading uses `autoload.files` to boot `src/index.php`.
- The WP-CLI command is registered in `src/index.php`.
- The monitoring logic lives in `src/Monitor.php`.
- Keep release notes in `CHANGELOG.md`.

## Changelog

See `CHANGELOG.md` for release notes and unreleased changes.
