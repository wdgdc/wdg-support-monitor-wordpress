# WDG Support Monitor for WordPress

## Installation

### Plugin - Preferred

Install this as a zip file to the wp-content/plugins folder

### MU-Plugin

Install the plugin in it's own directory within wp-content/mu-plugins and then include the index.php file from a custom php file.

## Composer

As a dependency of a plugin or theme, install the package from the composer repo and the package will automatically load itself.

## Configuration

Copy the configuration constants from the WDG Support Monitor for the property. You will need to define:

```
defined( 'WDG_SUPPORT_MONITOR_API_ENDPOINT', 'XXXXXXX' );
defined( 'WDG_SUPPORT_MONITOR_API_SECRET', 'XXXXXXX' );
```
