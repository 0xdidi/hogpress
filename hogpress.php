<?php
/**
 * Plugin Name:       Connect for PostHog
 * Plugin URI:        https://example.com/hogpress
 * Description:       Install, configure, and get real value from PostHog on WordPress: server-side events, correct identity stitching, no-flicker feature flags, and dashboards provisioned for you.
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      8.2
 * Author:            Great Anthony
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hogpress
 * Domain Path:       /languages
 *
 * @package Hogpress
 *
 * NOTE: "Connect for PostHog" is a placeholder public name. The final name and
 * text domain are an open decision (PRD section 11) to resolve before Phase 7.
 * The codename "hogpress" is used for the text domain, prefixes, and namespace.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Single source of truth for plugin metadata. Prefixed per coding standards.
define( 'HOGPRESS_VERSION', '0.1.0' );
define( 'HOGPRESS_FILE', __FILE__ );
define( 'HOGPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'HOGPRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'HOGPRESS_MIN_PHP', '8.2' );

/**
 * Render an admin notice and bail out when the PHP version is too low.
 *
 * We never fatal on an unsupported host. We show a clear notice and stop loading
 * so the rest of the site keeps working.
 *
 * @return void
 */
function hogpress_php_version_notice() {
	$message = sprintf(
		/* translators: 1: required PHP version, 2: current PHP version. */
		esc_html__( 'Connect for PostHog requires PHP %1$s or higher. You are running PHP %2$s. The plugin has been kept inactive to avoid breaking your site.', 'hogpress' ),
		esc_html( HOGPRESS_MIN_PHP ),
		esc_html( PHP_VERSION )
	);
	printf( '<div class="notice notice-error"><p>%s</p></div>', wp_kses_post( $message ) );
}

// Guard the PHP version before loading any plugin code that may use newer syntax.
if ( version_compare( PHP_VERSION, HOGPRESS_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', 'hogpress_php_version_notice' );
	return;
}

/**
 * Lightweight PSR-4 style autoloader for the plugin's own classes.
 *
 * Maps the Hogpress\ namespace to the src/ directory. This keeps our classes
 * loading without a Composer dump step on every change. Third-party libraries
 * (posthog-php) are loaded from the vendored Composer autoloader below.
 *
 * @param string $class_name Fully qualified class name.
 * @return void
 */
function hogpress_autoload( $class_name ) {
	$prefix   = 'Hogpress\\';
	$base_dir = HOGPRESS_DIR . 'src/';

	$len = strlen( $prefix );
	if ( 0 !== strncmp( $prefix, $class_name, $len ) ) {
		return;
	}

	$relative = substr( $class_name, $len );
	$path     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_readable( $path ) ) {
		require $path;
	}
}
spl_autoload_register( 'hogpress_autoload' );

// Load vendored third-party libraries (posthog-php) when present.
$hogpress_vendor_autoload = HOGPRESS_DIR . 'vendor/autoload.php';
if ( is_readable( $hogpress_vendor_autoload ) ) {
	require $hogpress_vendor_autoload;
}

// Activation, deactivation, and uninstall hooks.
register_activation_hook( __FILE__, array( '\Hogpress\Platform\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\Hogpress\Platform\Plugin', 'deactivate' ) );

/**
 * Boot the plugin once WordPress and other plugins are loaded.
 *
 * @return void
 */
function hogpress_boot() {
	\Hogpress\Platform\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'hogpress_boot' );
