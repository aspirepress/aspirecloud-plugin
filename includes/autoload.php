<?php
/**
 * The Autoloader.
 *
 * @package aspire-cloud
 */

spl_autoload_register( 'aspire_cloud_autoloader' );

/**
 * The Class Autoloader.
 *
 * @param string $class_name The name of the class to load.
 * @return void
 */
function aspire_cloud_autoloader( $class_name ) {
	if ( false !== strpos( $class_name, 'AspireCloud\\' ) ) {
		$class_name = str_replace( 'AspireCloud\\', '', $class_name );
		$parts      = explode( '\\', $class_name );

		// Convert to lowercase and replace underscores with hyphens
		$file_parts = array_map(
			function ( $part ) {
				return strtolower( str_replace( '_', '-', $part ) );
			},
			$parts
		);

		// Build the file path
		$file_path = __DIR__ . DIRECTORY_SEPARATOR;
		if ( count( $file_parts ) > 1 ) {
			// Add subdirectory (e.g., controller)
			$file_path .= $file_parts[0] . DIRECTORY_SEPARATOR;
			$file_name  = 'class-' . $file_parts[1] . '.php';
		} else {
			$file_name = 'class-' . $file_parts[0] . '.php';
		}

		$file = $file_path . $file_name;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
