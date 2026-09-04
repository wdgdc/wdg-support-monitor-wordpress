# Changelog

## Unreleased

- Add optional `wdg-core-version` reporting when Composer metadata shows `wdgdc/wikit-core` is installed.
- Add the `wdg_support_monitor_extras` filter so projects can extend or override the `extras` payload before it is sent.

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

- Allow localhost support endpoints for development and testing.
- Clean up the reporting flow and refresh the initial documentation.

## 1.0.0

- Initial release of the WordPress support monitor package.
- Add support payload reporting for WordPress core, installed addons, and update recommendations.
- Add Composer-compatible bootstrapping, WP-CLI commands, request signing, and configuration safeguards for misconfigured installs.
