<?php
/**
 * Default template for headless WordPress.
 * This should rarely be used as most requests are handled by the API passthrough.
 *
 * @package aspire-cloud
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set JSON header
header( 'Content-Type: application/json' );

// Return a simple JSON response indicating this is a headless WordPress
echo wp_json_encode(
	[
		'message' => 'WordPress Core Services API',
		'version' => AC_VERSION,
		'status'  => 'active',
	]
);
exit;
