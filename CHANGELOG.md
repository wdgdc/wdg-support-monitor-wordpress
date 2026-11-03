# Changelog

## Unreleased

## 1.1.3

- Add optional `wikit_core_version` reporting when Composer metadata shows `wdgdc/wikit-core` is installed.
- Show `Not configured` for the API endpoint in `wp wdg-support-monitor info` when `WDG_SUPPORT_MONITOR_API_ENDPOINT` isn't set, instead of `null`, which triggered a PHP 8.1+ deprecation notice in WP-CLI's YAML output.

## 1.1.2

- Extract version and self-update logic out of the monitor into a dedicated `Core` class.
- Split zip packaging into its own `zip-plugin` Composer command, separate from `build-plugin`.
- Fix the release zip workflow filename and add a `.zip-exclude` list for local test builds.

## 1.1.1

- Cache remote plugin release metadata before returning update information.
- Add the release zip workflow and related release packaging updates.

## 1.1.0

- Add self-update support backed by `https://plugins.wdg.dev/info.json`.
- Use the package version when building the plugin entry file.
- Generate the root plugin file as part of the release/build flow.

## 1.0.4

- Add `php_version` to the reported extras payload.

## 1.0.3

- Refine the packaging flow for zip builds.
- Use a stable plugin folder name inside release archives.
- Fix the packaged plugin version in the generated entry file.

## 1.0.2

- Add `WDG_SUPPORT_MONITOR_SITE_URL` so the reported site URL can be overridden.
- Remove unneeded filtering as part of the reporting cleanup.

## 1.0.1

- When `WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST` is enabled, treat the configured API endpoint as an external host (via `http_request_host_is_external`), so reports can be sent to a localhost endpoint during testing.
- Clean up the reporting flow and refresh the initial documentation.

## 1.0.0

- Initial release of the WordPress support monitor package.
- Add support payload reporting for WordPress core, installed addons, and update recommendations.
- Add Composer-compatible bootstrapping, WP-CLI commands, request signing, and configuration safeguards for misconfigured installs.
