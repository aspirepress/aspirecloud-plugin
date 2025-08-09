<?php
/**
 * Plugin Import Controller Class.
 *
 * @package aspirecloud
 * @author  AspirePress
 */

namespace AspireCloud\Controller;

use AspireCloud\Model\Plugins;
use AspireCloud\Model\PluginInfo;

/**
 * Class PluginImport
 *
 * Handles the admin settings page for importing plugins from the WordPress.org API.
 */
class PluginImport extends AssetsImporter {

	/**
	 * The plugins model instance.
	 *
	 * @var Plugins
	 */
	private $plugins_model;

	/**
	 * Initialize the plugin import controller.
	 */
	public function __construct() {
		$this->plugins_model = new Plugins();
		$this->asset_type    = 'plugin';
		$this->post_type     = $this->plugins_model::POST_TYPE;
		$this->model         = $this->plugins_model;

		// Call parent constructor
		parent::__construct();

		// Register common AJAX handlers for database operations
		$this->register_common_ajax_handlers();

		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_get_total_plugins_count', [ $this, 'ajax_get_total_assets_count' ] );
		add_action( 'wp_ajax_import_plugin_metadata_batch', [ $this, 'ajax_import_metadata_batch' ] );
		add_action( 'wp_ajax_download_plugin_assets_batch', [ $this, 'ajax_download_assets_batch' ] );
		add_action( 'wp_ajax_clear_plugins_data', [ $this, 'ajax_clear_plugins_data' ] );
		add_action( 'wp_ajax_get_plugins_clear_count', [ $this, 'ajax_get_plugins_clear_count' ] );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=ac_plugin',
			__( 'Import Plugins', 'aspirecloud' ),
			__( 'Import Plugins', 'aspirecloud' ),
			'manage_options',
			'import-plugins',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'ac_plugin_page_import-plugins' !== $hook ) {
			return;
		}

		$this->enqueue_common_scripts();

		$plugin_specific_strings = [
			'getting_total_count' => __( 'Getting total plugin count...', 'aspirecloud' ),
			'importing_metadata'  => __( 'Importing plugin metadata...', 'aspirecloud' ),
			/* translators: %1$d: current batch number, %2$d: total batches */
			'importing_batch'     => __( 'Importing metadata batch %1$d of %2$d...', 'aspirecloud' ),
			/* translators: %1$d: imported count, %2$d: total plugins */
			'imported_plugins'    => __( 'Imported %1$d of %2$d plugins', 'aspirecloud' ),
			'metadata_complete'   => __( 'Metadata import complete! Starting file downloads...', 'aspirecloud' ),
			'downloading_files'   => __( 'Downloading plugin files...', 'aspirecloud' ),
			/* translators: %1$d: downloaded count, %2$d: total plugins */
			'downloaded_plugins'  => __( 'Downloaded files for %1$d of %2$d plugins', 'aspirecloud' ),
			'download_complete'   => __( 'All plugin files downloaded successfully!', 'aspirecloud' ),
			'clearing_data'       => __( 'Clearing plugin data...', 'aspirecloud' ),
			'data_cleared'        => __( 'Plugin data cleared successfully!', 'aspirecloud' ),
			'confirm_clear'       => __( 'Are you sure you want to delete all plugin data? This action cannot be undone.', 'aspirecloud' ),
			'getting_clear_count' => __( 'Getting plugin count for clearing...', 'aspirecloud' ),
			/* translators: %1$d: current batch number, %2$d: total batches */
			'clearing_batch'      => __( 'Clearing batch %1$d of %2$d...', 'aspirecloud' ),
			/* translators: %1$d: cleared count, %2$d: total plugins */
			'cleared_plugins'     => __( 'Cleared %1$d of %2$d plugins', 'aspirecloud' ),
		];

		$this->localize_common_scripts( $plugin_specific_strings );
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		$this->render_common_admin_page(
			__( 'Import Plugins', 'aspirecloud' ),
			__( 'Import all plugins from the WordPress.org repository into your local database.', 'aspirecloud' ),
			__( 'Import All Plugins', 'aspirecloud' ),
			'import-plugins-btn',
			__( 'Clear Plugin Data', 'aspirecloud' ),
			'clear-plugins-data-btn'
		);
	}

	/**
	 * Get total plugins count from API.
	 */
	protected function get_total_assets_count() {
		// Include the plugins API functions
		if ( ! function_exists( 'plugins_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$count_response = plugins_api(
			'query_plugins',
			[
				'per_page' => 1,
				'page'     => 1,
			]
		);

		if ( is_wp_error( $count_response ) ) {
			return $count_response;
		}

		if ( ! isset( $count_response->info['results'] ) ) {
			return new \WP_Error( 'api_error', __( 'Unable to get total plugin count.', 'aspirecloud' ) );
		}

		return [
			'total' => (int) $count_response->info['results'],
		];
	}

	/**
	 * Get plugins for a specific page from API with all data.
	 */
	protected function get_assets_page( $page, $per_page ) {
		// Include the plugins API functions
		if ( ! function_exists( 'plugins_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		// Get all available properties from PluginInfo class
		$available_properties = PluginInfo::get_all_properties();

		// Create fields array with all properties set to true
		$fields = [];
		foreach ( $available_properties as $property ) {
			$fields[ $property ] = true;
		}

		$api_response = plugins_api(
			'query_plugins',
			[
				'per_page' => $per_page,
				'page'     => $page,
				'browse'   => 'new',  // Sort by newest plugins
				'fields'   => $fields,
			]
		);

		if ( is_wp_error( $api_response ) ) {
			return $api_response;
		}

		$items = [];
		if ( isset( $api_response->plugins ) && is_array( $api_response->plugins ) ) {
			foreach ( $api_response->plugins as $plugin ) {
				if ( is_array( $plugin ) && isset( $plugin['slug'] ) ) {
					$items[] = $plugin;
				}
			}
		}

		return [ 'items' => $items ];
	}

	/**
	 * Get detailed plugin information from API.
	 * Note: Not used anymore since we get all data in get_assets_page.
	 */
	protected function get_detailed_asset_info( $slug ) {
		// This method is not used anymore since we fetch all data in get_assets_page
		// Keeping for compatibility with parent class
		return null;
	}

	/**
	 * Create plugin info object.
	 */
	protected function create_asset_info() {
		return new PluginInfo();
	}

	/**
	 * Load plugin metadata from post.
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
	 * Download plugin files and update metadata.
	 */
	public function download_asset_files( $asset_info, $post_id, $slug ) {
		$aspire_dir = $this->create_asset_directory( $slug, 'plugins' );

		// Check if directory creation failed
		if ( false === $aspire_dir ) {
			throw new \Exception( sprintf( 'Failed to create directory for plugin: %s', esc_html( $slug ) ) );
		}

		// Download banners
		$banners = get_post_meta( $post_id, '__banners', true );
		if ( ! empty( $banners ) ) {
			// Decode JSON if it's a string
			if ( is_string( $banners ) ) {
				$banners = json_decode( $banners, true );
			}
			if ( is_array( $banners ) ) {
				$local_banners = $this->download_image_assets( $banners, $aspire_dir, $slug, 'plugins', 'banner' );
				if ( ! empty( $local_banners ) ) {
					update_post_meta( $post_id, '__banners', $local_banners );
				}
			}
		}

		// Download icons
		$icons = get_post_meta( $post_id, '__icons', true );
		if ( ! empty( $icons ) ) {
			// Decode JSON if it's a string
			if ( is_string( $icons ) ) {
				$icons = json_decode( $icons, true );
			}
			if ( is_array( $icons ) ) {
				$local_icons = $this->download_image_assets( $icons, $aspire_dir, $slug, 'plugins', 'icon' );
				if ( ! empty( $local_icons ) ) {
					update_post_meta( $post_id, '__icons', $local_icons );
				}
			}
		}

		// Download plugin ZIP file
		$download_link = get_post_meta( $post_id, '__download_link', true );
		$version       = get_post_meta( $post_id, '__version', true );
		if ( ! empty( $download_link ) ) {
			$local_download_url = $this->download_zip_file( $download_link, $aspire_dir, $slug, $version, 'plugins' );
			if ( $local_download_url ) {
				update_post_meta( $post_id, '__download_link', $local_download_url );
			}
		}
	}

	/**
	 * Wrapper method for model access - get plugin by slug.
	 */
	protected function get_asset_by_slug( $slug ) {
		return $this->plugins_model->get_plugin_by_slug( $slug );
	}

	/**
	 * AJAX handler to get the total count of plugins for clearing.
	 */
	public function ajax_get_plugins_clear_count() {
		$this->ajax_get_clear_count();
	}

	/**
	 * AJAX handler to clear all plugin data.
	 */
	public function ajax_clear_plugins_data() {
		$this->ajax_clear_data();
	}
}
