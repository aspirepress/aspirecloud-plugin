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

add_action( 'plugins_loaded', 'define_constant' );
function define_constant() {
	if ( ! defined( 'AC_PATH' ) ) {
		define( 'AC_PATH', __DIR__ );
	}
}

// Simple API endpoint functionality will be added here in the future
