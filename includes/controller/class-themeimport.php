<?php
/**
 * Theme Import Controller Class.
 *
 * @package aspirecloud
 * @author  AspirePress
 */

namespace AspireCloud\Controller;

use AspireCloud\Model\Themes;
use AspireCloud\Model\ThemeInfo;

/**
 * Class ThemeImport
 *
 * Handles the admin settings page for importing themes from the WordPress.org API.
 */
class ThemeImport extends AssetsImporter {

	/**
	 * The themes model instance.
	 *
	 * @var Themes
	 */
	private $themes_model;

	/**
	 * Initialize the theme import controller.
	 */
	public function __construct() {
		$this->themes_model = new Themes();
		$this->asset_type   = 'theme';
		$this->post_type    = 'ac_theme';
		$this->model        = $this->themes_model;

		// Call parent constructor
		parent::__construct();

		// Register common AJAX handlers for database operations
		$this->register_common_ajax_handlers();

		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_get_total_themes_count', [ $this, 'ajax_get_total_assets_count' ] );
		add_action( 'wp_ajax_import_theme_metadata_batch', [ $this, 'ajax_import_metadata_batch' ] );
		add_action( 'wp_ajax_download_theme_assets_batch', [ $this, 'ajax_download_assets_batch' ] );
		add_action( 'wp_ajax_clear_themes_data', [ $this, 'ajax_clear_themes_data' ] );
		add_action( 'wp_ajax_get_themes_clear_count', [ $this, 'ajax_get_themes_clear_count' ] );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=ac_theme',
			__( 'Import Themes', 'aspirecloud' ),
			__( 'Import Themes', 'aspirecloud' ),
			'manage_options',
			'import-themes',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'ac_theme_page_import-themes' !== $hook ) {
			return;
		}

		$this->enqueue_common_scripts();

		$theme_specific_strings = [
			'getting_total_count' => __( 'Getting total theme count...', 'aspirecloud' ),
			'importing_metadata'  => __( 'Importing theme metadata...', 'aspirecloud' ),
			/* translators: %1$d: current batch number, %2$d: total batches */
			'importing_batch'     => __( 'Importing metadata batch %1$d of %2$d...', 'aspirecloud' ),
			/* translators: %1$d: imported count, %2$d: total themes */
			'imported_themes'     => __( 'Imported %1$d of %2$d themes', 'aspirecloud' ),
			'metadata_complete'   => __( 'Metadata import complete! Starting file downloads...', 'aspirecloud' ),
			'downloading_files'   => __( 'Downloading theme files...', 'aspirecloud' ),
			/* translators: %1$d: downloaded count, %2$d: total themes */
			'downloaded_themes'   => __( 'Downloaded files for %1$d of %2$d themes', 'aspirecloud' ),
			'download_complete'   => __( 'All theme files downloaded successfully!', 'aspirecloud' ),
			'confirm_clear'       => __( 'Are you sure you want to clear all theme data? This action cannot be undone.', 'aspirecloud' ),
			'clearing_data'       => __( 'Clearing data...', 'aspirecloud' ),
			'data_cleared'        => __( 'Data cleared successfully!', 'aspirecloud' ),
			'getting_clear_count' => __( 'Getting theme count for clearing...', 'aspirecloud' ),
			/* translators: %1$d: current batch number, %2$d: total batches */
			'clearing_batch'      => __( 'Clearing batch %1$d of %2$d...', 'aspirecloud' ),
			/* translators: %1$d: cleared count, %2$d: total themes */
			'cleared_themes'      => __( 'Cleared %1$d of %2$d themes', 'aspirecloud' ),
		];

		$this->localize_common_scripts( $theme_specific_strings );
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		$this->render_common_admin_page(
			__( 'Import Themes', 'aspirecloud' ),
			__( 'Import themes from the connected repository into your local database.', 'aspirecloud' ),
			__( 'Import Themes', 'aspirecloud' ),
			'import-themes-btn',
			__( 'Clear Theme Data', 'aspirecloud' ),
			'clear-themes-data-btn'
		);
	}

	/**
	 * Get total themes count from API.
	 */
	protected function get_total_assets_count() {
		// Include the themes API functions
		if ( ! function_exists( 'themes_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		$count_response = themes_api(
			'query_themes',
			[
				'per_page' => 1,
				'page'     => 1,
			]
		);

		if ( is_wp_error( $count_response ) ) {
			return $count_response;
		}

		if ( ! isset( $count_response->info['results'] ) ) {
			return new \WP_Error( 'api_error', __( 'Unable to get total theme count.', 'aspirecloud' ) );
		}

		return [
			'total' => (int) $count_response->info['results'],
		];
	}

	/**
	 * Get themes for a specific page from API with all data.
	 */
	protected function get_assets_page( $page, $per_page ) {
		// Include the themes API functions
		if ( ! function_exists( 'themes_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		// Get all available properties from ThemeInfo class
		$available_properties = ThemeInfo::get_all_properties();

		// Create fields array with all properties set to true
		$fields = [];
		foreach ( $available_properties as $property ) {
			$fields[ $property ] = true;
		}

		$api_response = themes_api(
			'query_themes',
			[
				'per_page' => $per_page,
				'page'     => $page,
				'browse'   => 'new',  // Sort by newest themes
				'fields'   => $fields,
			]
		);

		if ( is_wp_error( $api_response ) ) {
			return $api_response;
		}

		$items = [];
		if ( isset( $api_response->themes ) && is_array( $api_response->themes ) ) {
			foreach ( $api_response->themes as $theme ) {
				if ( is_object( $theme ) && isset( $theme->slug ) ) {
					$items[] = (array) $theme; // Return full theme object with all data
				}
			}
		}

		return [ 'items' => $items ];
	}

	/**
	 * Get detailed theme information from API.
	 * Note: Not used anymore since we get all data in get_assets_page.
	 */
	protected function get_detailed_asset_info( $slug ) {
		// This method is not used anymore since we fetch all data in get_assets_page
		// Keeping for compatibility with parent class
		return null;
	}

	/**
	 * Create theme info object.
	 */
	protected function create_asset_info() {
		return new ThemeInfo();
	}

	/**
	 * Load theme metadata from post.
	 */
	protected function load_asset_metadata( $asset_info, $post_id ) {
		// Get all post meta for this post
		$all_meta = get_post_meta( $post_id );

		// Load all metadata into the asset_info object
		foreach ( $all_meta as $meta_key => $meta_values ) {
			// Skip meta keys that don't have our double underscore prefix
			if ( ! str_starts_with( $meta_key, '__' ) ) {
				continue;
			}

			// Remove the double underscore prefix to get the original property name
			$property_name = substr( $meta_key, 2 );

			// get_post_meta returns arrays, so get the first value
			$value = isset( $meta_values[0] ) ? $meta_values[0] : '';

			if ( ! empty( $value ) ) {
				// Try to decode JSON values
				$decoded_value = json_decode( $value, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$asset_info->__set( $property_name, $decoded_value );
				} else {
					$asset_info->__set( $property_name, $value );
				}
			}
		}
	}

	/**
	 * Download theme files and update metadata.
	 */
	public function download_asset_files( $asset_info, $post_id, $slug ) {
		$aspire_dir = $this->create_asset_directory( $slug, 'themes' );

		// Download screenshot
		$screenshot_url = get_post_meta( $post_id, '__screenshot_url', true );
		if ( ! empty( $screenshot_url ) ) {
			$local_screenshot_url = $this->download_single_image( $screenshot_url, $aspire_dir, $slug, 'themes', 'screenshot.png' );
			if ( $local_screenshot_url ) {
				update_post_meta( $post_id, '__screenshot_url', $local_screenshot_url );
			}
		}

		// Download theme ZIP file
		$download_link = get_post_meta( $post_id, '__download_link', true );
		$version       = get_post_meta( $post_id, '__version', true );
		if ( ! empty( $download_link ) ) {
			$local_download_url = $this->download_zip_file( $download_link, $aspire_dir, $slug, $version, 'themes' );
			if ( $local_download_url ) {
				update_post_meta( $post_id, '__download_link', $local_download_url );
			}
		}
	}

	/**
	 * Wrapper method for model access - get theme by slug.
	 */
	protected function get_asset_by_slug( $slug ) {
		return $this->themes_model->get_theme_by_slug( $slug );
	}

	/**
	 * AJAX handler to get the total count of themes for clearing.
	 */
	public function ajax_get_themes_clear_count() {
		$this->ajax_get_clear_count();
	}

	/**
	 * AJAX handler to clear all theme data.
	 */
	public function ajax_clear_themes_data() {
		$this->ajax_clear_data();
	}
}
