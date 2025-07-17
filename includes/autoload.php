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
		$class_name = strtolower( str_replace( [ 'AspireCloud\\', '_' ], [ '', '-' ], $class_name ) );
		$file       = __DIR__ . DIRECTORY_SEPARATOR . 'class-' . $class_name . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
