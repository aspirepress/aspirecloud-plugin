<?php
/**
 * The Class for Miscellaneous Helper Functions.
 *
 * @package aspirecloud
 * @author  AspirePress
 */

namespace AspireCloud\Model;

/**
 * The Class for Utility Functions.
 */
class Utilities {

	/**
	 * Return the content of the File after processing.
	 *
	 * @param string $file File name.
	 * @param array  $args Data to pass to the file.
	 */
	public static function include_file( $file, $args = [] ) {
		$file_path = AC_PATH . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $file;
		if ( ( '' !== $file ) && file_exists( $file_path ) ) {
			//phpcs:disable
			// Usage of extract() is necessary in this content to simulate templating functionality.
			extract( $args );
			//phpcs:enable
			include $file_path;
		}
	}
}
