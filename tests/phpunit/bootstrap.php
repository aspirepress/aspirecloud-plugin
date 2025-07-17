<?php
/**
 * PHPUnit bootstrap file for AspireCloud tests.
 *
 * @package aspirecloud
 */

// Define test environment
define( 'AC_RUN_TESTS', true );

// Composer autoloader
$_composer_autoload_path = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( file_exists( $_composer_autoload_path ) ) {
	require_once $_composer_autoload_path;
}

// WordPress test environment
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/aspire-cloud.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
