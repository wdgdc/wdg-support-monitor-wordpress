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
define( 'WDG_SUPPORT_MONITOR_API_ENDPOINT', 'XXXXXXX' );
define( 'WDG_SUPPORT_MONITOR_API_SECRET', 'XXXXXXX' );
```

### Constants

*WDG_SUPPORT_MONITOR_API_ENDPOINT*.   : required - URL to send site data to 
*WDG_SUPPORT_MONITOR_API_SECRET*      : required - Secret to use to validate request
*WDG_SUPPORT_MONITOR_SITE_URL*        : optional - Override site_url()  
*WDG_SUPPORT_MONITOR_ALLOW_LOCALHOST* : optional - Used for local development and debugging
