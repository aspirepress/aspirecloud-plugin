<?php
/**
 * AspireCloud - WordPress Core Services API.
 *
 * @package     aspire-cloud
 * @author      AspireCloud
 * @copyright   AspireCloud
 * @license     GPLv2
 *
 * Plugin Name:       AspireCloud
 * Plugin URI:        https://aspirepress.org/
 * Description:       WordPress Core Services API.
 * Version:           0.0.1
 * Author:            AspirePress
 * Author URI:        https://docs.aspirepress.org/aspirecloud/
 * Requires at least: 5.3
 * Requires PHP:      7.4
 * Tested up to:      6.8.1
 * License:           GPLv2
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt
 * Text Domain:       aspirecloud
 * Domain Path:       /languages
 * Update URI:        https://aspirepress.org
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! defined( 'AC_VERSION' ) ) {
	define( 'AC_VERSION', '0.0.1' );
}

if ( ! defined( 'AC_SOURCE_API_ENDPOINT' ) ) {
	define( 'AC_SOURCE_API_ENDPOINT', 'https://api.wordpress.org' );
}

add_action( 'plugins_loaded', 'define_constant' );
function define_constant() {
	if ( ! defined( 'AC_PATH' ) ) {
		define( 'AC_PATH', __DIR__ );
	}
}

// Load the autoloader
require_once __DIR__ . '/includes/autoload.php';

// Initialize the plugin functionality earlier to ensure custom post types register properly
add_action( 'plugins_loaded', 'aspire_cloud_init' );

// Plugin activation and deactivation hooks// Plugin activation and deactivation hooks
register_activation_hook( __FILE__, 'aspire_cloud_activate' );
register_deactivation_hook( __FILE__, 'aspire_cloud_deactivate' );

/**
 * Plugin activation callback.
 */
function aspire_cloud_activate() {
	// Initialize the controllers to register rewrite rules
	aspire_cloud_init();

	// Flush rewrite rules on activation to ensure custom post types work
	flush_rewrite_rules();
}

/**
 * Plugin deactivation callback.
 */
function aspire_cloud_deactivate() {
	// Flush rewrite rules on deactivation to clean up
	flush_rewrite_rules();
}

/**
 * Initialize AspireCloud plugin.
 */
function aspire_cloud_init() {
	// Initialize the Passthrough API controller
	new \AspireCloud\Controller\Passthrough();

	// Initialize the Headless WordPress controller
	new \AspireCloud\Controller\Headless();

	// Initialize custom post types
	new \AspireCloud\Model\Plugins();
	new \AspireCloud\Model\Themes();
}
