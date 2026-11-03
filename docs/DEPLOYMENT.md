# Deployment and Releases

This package ships through two channels, and a release has to cover both:

| Channel | Who uses it | How they get a new version |
| --- | --- | --- |
| Packaged plugin zip | Sites with the plugin in `wp-content/plugins/wdg-support-monitor` | WordPress self-update from `https://plugins.wdg.dev/info.json` |
| Composer package `wdgdc/wdg-wp-support-monitor` | Themes, plugins, and MU-plugin loaders that require it | `composer update`, resolved from git tags |

Both channels are driven by a git tag in the form `vX.Y.Z`. Publishing a GitHub release for that tag triggers the build.

## How the pieces fit

```
GitHub release published (tag vX.Y.Z)
  -> .github/workflows/release-zip.yml
       1. rsync repo into build/wdg-support-monitor (drops .git, .github, vendor, *.zip, ...)
       2. composer install --no-dev --optimize-autoloader   (COMPOSER_ROOT_VERSION=X.Y.Z)
       3. composer build-plugin                              (regenerates wdg-support-monitor.php with Version: X.Y.Z)
       4. zip as wdg-support-monitor-X.Y.Z.zip               (excludes composer.json, composer.lock, dotfiles)
       5. gh release upload -> attaches the zip to the GitHub release
       6. scp -> developer@$WDGDEV_HOST:/var/www/html/plugins/wdg-support-monitor-X.Y.Z.zip
  -> plugins.wdg.dev/info.json points sites at the new zip   (manual, see below)
  -> sites see the update in wp-admin / wp plugin list
```

### Version resolution

The version in the zip comes from the tag, not from the committed files:

- The workflow strips the `v` from the tag (`${GITHUB_REF_NAME/v/}`) and passes the result as `COMPOSER_ROOT_VERSION`.
- `composer build-plugin` runs `.build-plugin.php` with no argument, so it calls `Core::version()`. Outside WordPress that falls through to `Composer\InstalledVersions::getPrettyVersion()`, which returns `COMPOSER_ROOT_VERSION`.
- `.build-plugin.php` then writes the `Version:` header into `wdg-support-monitor.php`.

The bash substitution removes the first `v` anywhere in the tag, so stick to `vX.Y.Z` or `X.Y.Z` tag names.

### Self-update on installed sites

`Core::plugin_info()` hooks `update_plugins_plugins.wdg.dev` (matching the `Update URI` header). It fetches `https://plugins.wdg.dev/info.json`, caches the result for 12 hours in the `plugins` object cache group, and hands WordPress the update details. The file can be a single object or an array of objects; for an array, the entry with the highest `version` wins.

Fields read from each entry:

| Field | Required | Notes |
| --- | --- | --- |
| `name`, `slug`, `author`, `author_profile` | yes | `slug` must be `wdg-support-monitor` |
| `version` | yes | Compared with the installed `Version:` header |
| `download_url` | yes | Used for both `url` and `package` |
| `tested` | no | Defaults to `6.9.4` |
| `requires_php` | no | Defaults to `8.1` |
| `sections.screenshots` | no | |

Example entry:

```json
{
  "name": "WDG Support Monitor",
  "slug": "wdg-support-monitor",
  "author": "WDG - The Web Development Group",
  "author_profile": "https://www.webdevelopmentgroup.com",
  "version": "1.1.3",
  "download_url": "https://plugins.wdg.dev/plugins/wdg-support-monitor-1.1.3.zip"
}
```

Composer and MU-plugin installs do not use this path. The `Update URI` header lives only in the generated plugin file, and those installs load `vendor/autoload.php` directly.

## Cutting a release

1. **Update the changelog.** Move the entries under `## Unreleased` in `CHANGELOG.md` into a new `## X.Y.Z` heading. Leave an empty `## Unreleased` heading at the top.

2. **Regenerate the plugin file.** CI regenerates it anyway, but keep the committed copy in sync so the repo matches what ships:

   ```bash
   composer install
   composer build-plugin X.Y.Z
   ```

   Confirm `wdg-support-monitor.php` shows `Version: X.Y.Z`.

3. **Merge to `master`.** Open a PR from the feature branch, get it reviewed, and merge. Releases are cut from `master`.

4. **Publish the GitHub release.**

   ```bash
   gh release create vX.Y.Z --target master --title "vX.Y.Z" --notes "<changelog entries>"
   ```

   The workflow listens for `release: published`. A draft release does not trigger it until you publish it. Pushing a tag without creating a release does not trigger it at all (though Composer consumers will still see the tag).

5. **Watch the workflow.** `gh run watch` or the Actions tab. When it finishes, the release has a `wdg-support-monitor-X.Y.Z.zip` asset and the same file sits in `/var/www/html/plugins/` on the WDG Dev host.

6. **Update `info.json` on plugins.wdg.dev.** The workflow does not touch it. Add or update the entry so `version` is `X.Y.Z` and `download_url` points at the uploaded zip. Until this happens, no site sees the update.

7. **Verify.**
   - Download the release zip and check that it contains a single `wdg-support-monitor/` folder, a `vendor/` directory, and a `wdg-support-monitor.php` header with the right version.
   - On a site running the plugin, clear the cache (`wp cache flush`, or wait out the 12 hour cache) and run `wp plugin list --name=wdg-support-monitor --fields=name,version,update_version`.
   - After updating a site, run `wp wdg-support-monitor update` and confirm a successful response from the endpoint.

## Configuration the workflow needs

Set these in the GitHub repo settings under Secrets and variables > Actions:

| Name | Kind | Purpose |
| --- | --- | --- |
| `WDGDEV_HOST` | variable | Hostname of the WDG Dev server that hosts plugin zips |
| `WDGDEV_SSH_KEY` | secret | Private key for the `developer` user on that host |
| `GITHUB_TOKEN` | built in | Uploads the zip to the release (job has `contents: write`) |

The host key is collected with `ssh-keyscan` on every run rather than pinned.

## Local test builds

To build a zip without publishing a release:

```bash
composer install --no-dev --optimize-autoloader
composer build-plugin X.Y.Z-test
composer zip-plugin X.Y.Z-test
```

`zip-plugin` zips the working directory using `.zip-exclude`. Unlike the CI build, the files sit at the root of the archive instead of inside a `wdg-support-monitor/` folder, so unzip it into `wp-content/plugins/wdg-support-monitor/` by hand rather than uploading it through wp-admin. Run `composer install` again afterward to restore dev dependencies, and `composer build-plugin` with the real version so you don't commit a test version header.

Zip files are gitignored.

## Rolling back

WordPress does not downgrade plugins through the update API. To back out a bad release:

1. Remove or correct the bad entry in `info.json` so no more sites pick it up.
2. Fix forward: revert the change on `master` and cut a new patch release (for example `1.1.4`).

For a single site that needs the old build right away, install the previous zip from its GitHub release manually (`wp plugin install <zip> --force`).

For Composer consumers, pin the previous tag in the consuming project's `composer.json` until the patch release is out.

## Legacy: `composer release`

`composer release` runs `Scripts::release`, an older manual flow that built the zip and an `info.json` locally. It reads the version from a root `index.php` that no longer exists, so it fails as-is. Use the GitHub release flow above.
