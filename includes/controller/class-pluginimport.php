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
class PluginImport {

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
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_get_all_plugin_slugs', [ $this, 'ajax_get_all_plugin_slugs' ] );
		add_action( 'wp_ajax_import_plugin_batch', [ $this, 'ajax_import_plugin_batch' ] );
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

		wp_enqueue_style(
			'aspirecloud-admin',
			AC_URL . 'assets/css/admin.css',
			[],
			AC_VERSION
		);

		// Enqueue the progress bar class first
		wp_enqueue_script(
			'aspirecloud-progress-bar',
			AC_URL . 'assets/js/progress-bar.js',
			[ 'jquery' ],
			AC_VERSION,
			true
		);

		// Enqueue the import assets class
		wp_enqueue_script(
			'aspirecloud-import-assets',
			AC_URL . 'assets/js/import-assets.js',
			[ 'jquery', 'aspirecloud-progress-bar' ],
			AC_VERSION,
			true
		);

		// Enqueue the clear assets class
		wp_enqueue_script(
			'aspirecloud-clear-assets',
			AC_URL . 'assets/js/clear-assets.js',
			[ 'jquery', 'aspirecloud-progress-bar' ],
			AC_VERSION,
			true
		);

		// Enqueue the main admin script
		wp_enqueue_script(
			'aspirecloud-admin',
			AC_URL . 'assets/js/admin.js',
			[ 'jquery', 'aspirecloud-progress-bar', 'aspirecloud-import-assets', 'aspirecloud-clear-assets' ],
			AC_VERSION,
			true
		);

		wp_localize_script(
			'aspirecloud-admin',
			'aspirecloud_ajax',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aspirecloud_import_nonce' ),
				'strings'  => [
					'importing'               => __( 'Importing...', 'aspirecloud' ),
					'complete'                => __( 'Import Complete!', 'aspirecloud' ),
					'error'                   => __( 'Import Error!', 'aspirecloud' ),
					'getting_slugs'           => __( 'Getting plugin list...', 'aspirecloud' ),
					'downloading_plugin_data' => __( 'Downloading plugin data...', 'aspirecloud' ),
					/* translators: %1$d: current page number, %2$d: total pages */
					'importing_page'          => __( 'Importing page %1$d of %2$d...', 'aspirecloud' ),
					/* translators: %1$d: current batch number, %2$d: total batches */
					'importing_batch'         => __( 'Importing batch %1$d of %2$d...', 'aspirecloud' ),
					/* translators: %1$d: imported count, %2$d: total plugins */
					'imported_plugins'        => __( 'Imported %1$d of %2$d plugins', 'aspirecloud' ),
					'clearing_data'           => __( 'Clearing plugin data...', 'aspirecloud' ),
					'data_cleared'            => __( 'Plugin data cleared successfully!', 'aspirecloud' ),
					'confirm_clear'           => __( 'Are you sure you want to delete all plugin data? This action cannot be undone.', 'aspirecloud' ),
					'getting_clear_count'     => __( 'Getting plugin count for clearing...', 'aspirecloud' ),
					/* translators: %1$d: current batch number, %2$d: total batches */
					'clearing_batch'          => __( 'Clearing batch %1$d of %2$d...', 'aspirecloud' ),
					/* translators: %1$d: cleared count, %2$d: total plugins */
					'cleared_plugins'         => __( 'Cleared %1$d of %2$d plugins', 'aspirecloud' ),
					'resting'                 => __( 'Resting for 30 seconds...', 'aspirecloud' ),
				],
			]
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		?>
		<div class="wrap aspirecloud-import-page">
			<h1><?php esc_html_e( 'Import Plugins', 'aspirecloud' ); ?></h1>
			<p><?php esc_html_e( 'Import all plugins from the WordPress.org repository into your local database.', 'aspirecloud' ); ?></p>

			<div class="aspirecloud-import-container">
				<div class="aspirecloud-import-button-container">
					<button id="import-plugins-btn" class="button button-primary button-large">
						<?php esc_html_e( 'Import All Plugins', 'aspirecloud' ); ?>
					</button>

					<button id="clear-plugins-data-btn" class="button button-secondary button-large aspirecloud-clear-data-btn">
						<?php esc_html_e( 'Clear Plugin Data', 'aspirecloud' ); ?>
					</button>
				</div>

				<div class="aspirecloud-progress-container" style="display: none;">
					<div class="aspirecloud-progress-bar">
						<div class="aspirecloud-progress-fill"></div>
					</div>
					<div class="aspirecloud-progress-text">
						<span id="progress-status"><?php esc_html_e( 'Preparing import...', 'aspirecloud' ); ?></span>
					</div>
					<div class="aspirecloud-progress-details">
						<span id="progress-details"></span>
					</div>
				</div>
			</div>

			<div class="aspirecloud-ajax-overlay" style="display: none;">
				<div class="aspirecloud-ajax-spinner">
					<div class="spinner is-active"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler to get a batch of plugin slugs from browse endpoint.
	 */
	public function ajax_get_all_plugin_slugs() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aspirecloud' ) );
		}

		// Include the plugins API functions
		if ( ! function_exists( 'plugins_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$page     = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
		$per_page = 200; // Use smaller batch size for better performance

		// Get total values from JavaScript (if provided) or calculate on first page
		$total_plugins = isset( $_POST['total_plugins'] ) ? (int) $_POST['total_plugins'] : 0;
		$total_pages   = isset( $_POST['total_pages'] ) ? (int) $_POST['total_pages'] : 0;

		// If this is the first page or totals not provided, get the total count
		if ( 1 === $page || 0 === $total_plugins ) {
			$count_response = plugins_api(
				'query_plugins',
				[
					'per_page' => 1,
					'page'     => 1,
				]
			);

			if ( is_wp_error( $count_response ) ) {
				wp_send_json_error( $count_response->get_error_message() );
			}

			if ( ! isset( $count_response->info['results'] ) ) {
				wp_send_json_error( __( 'Unable to get total plugin count.', 'aspirecloud' ) );
			}

			$total_plugins = (int) $count_response->info['results'];
			$total_pages   = ceil( $total_plugins / $per_page );
		}

		// Get plugin slugs for this page
		$api_response = plugins_api(
			'query_plugins',
			[
				'per_page' => $per_page,
				'page'     => $page,
				'fields'   => [
					'slug'              => true,  // Only field we need
					'short_description' => false,
					'description'       => false,
					'sections'          => false,
					'tested'            => false,
					'requires'          => false,
					'requires_php'      => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'downloadlink'      => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'versions'          => false,
					'donate_link'       => false,
					'reviews'           => false,
					'banners'           => false,
					'icons'             => false,
					'active_installs'   => false,
					'contributors'      => false,
				],
			]
		);

		if ( is_wp_error( $api_response ) ) {
			wp_send_json_error( $api_response->get_error_message() );
		}

		$slugs = [];
		if ( isset( $api_response->plugins ) && is_array( $api_response->plugins ) ) {
			foreach ( $api_response->plugins as $plugin ) {
				$slug = null;
				if ( is_object( $plugin ) && isset( $plugin->slug ) ) {
					$slug = $plugin->slug;
				} elseif ( is_array( $plugin ) && isset( $plugin['slug'] ) ) {
					$slug = $plugin['slug'];
				}

				if ( $slug ) {
					$slugs[] = $slug;
				}
			}
		}

		wp_send_json_success(
			[
				'plugin_slugs'  => $slugs,
				'page'          => $page,
				'per_page'      => $per_page,
				'has_more'      => $page < $total_pages,
				'total_plugins' => $total_plugins,
				'total_pages'   => $total_pages,
			]
		);
	}

	/**
	 * AJAX handler to import a batch of plugins using their slugs.
	 */
	public function ajax_import_plugin_batch() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aspirecloud' ) );
		}

		// Include the plugins API functions
		if ( ! function_exists( 'plugins_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		// Include download functions
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$plugin_slugs = isset( $_POST['plugin_slugs'] ) ? (array) $_POST['plugin_slugs'] : [];

		if ( empty( $plugin_slugs ) ) {
			wp_send_json_error( __( 'No plugin slugs provided.', 'aspirecloud' ) );
		}

		$imported_count = 0;
		$errors         = [];

		foreach ( $plugin_slugs as $slug ) {
			try {
				// Get detailed plugin information
				$plugin_data = plugins_api( 'plugin_information', [ 'slug' => $slug ] );

				if ( is_wp_error( $plugin_data ) ) {
					$errors[] = sprintf(
						/* translators: %1$s: plugin slug, %2$s: error message */
						__( 'Error getting info for %1$s: %2$s', 'aspirecloud' ),
						$slug,
						$plugin_data->get_error_message()
					);
					continue;
				}

				$plugin_info = new PluginInfo();

				// Map API data to PluginInfo properties
				$this->map_detailed_plugin_data( $plugin_info, $plugin_data );

				// Download and localize assets
				$this->download_plugin_assets( $plugin_info, $plugin_data );

				// Check if plugin already exists
				$existing_plugin = $this->plugins_model->get_plugin_by_slug( $slug );

				if ( $existing_plugin ) {
					$result = $this->plugins_model->update_plugin_post( $existing_plugin->ID, $plugin_info );
				} else {
					$result = $this->plugins_model->create_plugin_post( $plugin_info );
				}

				if ( ! is_wp_error( $result ) ) {
					++$imported_count;
				} else {
					$errors[] = sprintf(
						/* translators: %1$s: plugin name, %2$s: error message */
						__( 'Error importing %1$s: %2$s', 'aspirecloud' ),
						$plugin_data->name ?? $slug,
						$result->get_error_message()
					);
				}
			} catch ( \Exception $e ) {
				$errors[] = sprintf(
					/* translators: %1$s: plugin slug, %2$s: error message */
					__( 'Exception importing %1$s: %2$s', 'aspirecloud' ),
					$slug,
					$e->getMessage()
				);
			}
		}

		wp_send_json_success(
			[
				'imported_count' => $imported_count,
				'errors'         => $errors,
			]
		);
	}

	/**
	 * AJAX handler to get the total count of plugins for clearing.
	 */
	public function ajax_get_plugins_clear_count() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aspirecloud' ) );
		}

		$total_plugins = wp_count_posts( $this->plugins_model::POST_TYPE );
		$total_count   = array_sum( (array) $total_plugins ) - ( $total_plugins->auto_draft ?? 0 );
		$batch_size    = 25;
		$total_batches = ceil( $total_count / $batch_size );

		wp_send_json_success(
			[
				'total'         => $total_count,
				'total_batches' => $total_batches,
				'batch_size'    => $batch_size,
			]
		);
	}

	/**
	 * AJAX handler to clear all plugin data.
	 */
	public function ajax_clear_plugins_data() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aspirecloud' ) );
		}

		$batch_size = 25;

		// Get next batch of plugins (always get the first 25, since we're deleting them)
		$plugin_posts = get_posts(
			[
				'post_type'      => $this->plugins_model::POST_TYPE,
				'posts_per_page' => $batch_size,
				'post_status'    => 'any',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$deleted_count = 0;
		$errors        = [];

		foreach ( $plugin_posts as $post ) {
			$result = wp_delete_post( $post->ID, true );
			if ( $result ) {
				++$deleted_count;
			} else {
				$errors[] = sprintf(
					/* translators: %s: plugin post title */
					__( 'Failed to delete plugin: %s', 'aspirecloud' ),
					$post->post_title
				);
			}
		}

		// Check if there are more plugins to delete
		$remaining_plugins = get_posts(
			[
				'post_type'      => $this->plugins_model::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			]
		);

		wp_send_json_success(
			[
				'deleted_count' => $deleted_count,
				'batch_size'    => $batch_size,
				'errors'        => $errors,
				'has_more'      => count( $remaining_plugins ) > 0,
			]
		);
	}

	/**
	 * Map detailed API plugin data to PluginInfo object.
	 *
	 * @param PluginInfo $plugin_info The plugin info object.
	 * @param object     $plugin_data The API plugin data.
	 */
	private function map_detailed_plugin_data( $plugin_info, $plugin_data ) {
		// Basic properties
		$plugin_info->__set( 'name', $plugin_data->name ?? '' );
		$plugin_info->__set( 'slug', $plugin_data->slug ?? '' );
		$plugin_info->__set( 'version', $plugin_data->version ?? '' );
		$plugin_info->__set( 'author', $plugin_data->author ?? '' );
		$plugin_info->__set( 'description', $plugin_data->short_description ?? '' );
		$plugin_info->__set( 'homepage', $plugin_data->homepage ?? '' );
		$plugin_info->__set( 'download_link', $plugin_data->download_link ?? '' );

		// Numeric properties
		$plugin_info->__set( 'downloaded', $plugin_data->downloaded ?? 0 );
		$plugin_info->__set( 'active_installs', $plugin_data->active_installs ?? 0 );
		$plugin_info->__set( 'num_ratings', $plugin_data->num_ratings ?? 0 );

		// WordPress version requirements
		$plugin_info->__set( 'requires', $plugin_data->requires ?? '' );
		$plugin_info->__set( 'tested', $plugin_data->tested ?? '' );
		$plugin_info->__set( 'requires_php', $plugin_data->requires_php ?? '' );

		// Dates
		$plugin_info->__set( 'added', $plugin_data->added ?? '' );
		$plugin_info->__set( 'last_updated', $plugin_data->last_updated ?? '' );

		// Array properties
		if ( isset( $plugin_data->tags ) && is_array( $plugin_data->tags ) ) {
			$plugin_info->__set( 'tags', array_keys( $plugin_data->tags ) );
		}

		if ( isset( $plugin_data->sections ) && is_array( $plugin_data->sections ) ) {
			$plugin_info->__set( 'sections', $plugin_data->sections );
		}

		if ( isset( $plugin_data->ratings ) && is_array( $plugin_data->ratings ) ) {
			$plugin_info->__set( 'ratings', $plugin_data->ratings );
		}

		if ( isset( $plugin_data->banners ) && is_array( $plugin_data->banners ) ) {
			$plugin_info->__set( 'banners', $plugin_data->banners );
		}

		if ( isset( $plugin_data->icons ) && is_array( $plugin_data->icons ) ) {
			$plugin_info->__set( 'icons', $plugin_data->icons );
		}

		// Additional detailed properties
		if ( isset( $plugin_data->support_threads ) ) {
			$plugin_info->__set( 'support_threads', $plugin_data->support_threads );
		}

		if ( isset( $plugin_data->support_threads_resolved ) ) {
			$plugin_info->__set( 'support_threads_resolved', $plugin_data->support_threads_resolved );
		}

		if ( isset( $plugin_data->contributors ) && is_array( $plugin_data->contributors ) ) {
			$plugin_info->__set( 'contributors', $plugin_data->contributors );
		}

		if ( isset( $plugin_data->donate_link ) ) {
			$plugin_info->__set( 'donate_link', $plugin_data->donate_link );
		}
	}

	/**
	 * Download and localize plugin assets (banners, icons, zip files).
	 *
	 * @param PluginInfo $plugin_info The plugin info object.
	 * @param object     $plugin_data The API plugin data.
	 */
	private function download_plugin_assets( $plugin_info, $plugin_data ) {
		$upload_dir = wp_upload_dir();
		$aspire_dir = $upload_dir['basedir'] . '/aspirecloud/plugins/' . $plugin_data->slug;

		// Create directory if it doesn't exist
		if ( ! file_exists( $aspire_dir ) ) {
			wp_mkdir_p( $aspire_dir );
		}

		// Download banners
		if ( isset( $plugin_data->banners ) && is_array( $plugin_data->banners ) ) {
			$local_banners = [];
			foreach ( $plugin_data->banners as $size => $url ) {
				if ( ! empty( $url ) ) {
					$local_path = $this->download_and_save_file( $url, $aspire_dir, 'banner-' . $size );
					if ( $local_path ) {
						$local_banners[ $size ] = $upload_dir['baseurl'] . '/aspirecloud/plugins/' . $plugin_data->slug . '/' . basename( $local_path );
					}
				}
			}
			if ( ! empty( $local_banners ) ) {
				$plugin_info->__set( 'banners', $local_banners );
			}
		}

		// Download icons
		if ( isset( $plugin_data->icons ) && is_array( $plugin_data->icons ) ) {
			$local_icons = [];
			foreach ( $plugin_data->icons as $size => $url ) {
				if ( ! empty( $url ) ) {
					$local_path = $this->download_and_save_file( $url, $aspire_dir, 'icon-' . $size );
					if ( $local_path ) {
						$local_icons[ $size ] = $upload_dir['baseurl'] . '/aspirecloud/plugins/' . $plugin_data->slug . '/' . basename( $local_path );
					}
				}
			}
			if ( ! empty( $local_icons ) ) {
				$plugin_info->__set( 'icons', $local_icons );
			}
		}

		// Download plugin ZIP file
		if ( ! empty( $plugin_data->download_link ) ) {
			$zip_filename = $plugin_data->slug . '-' . $plugin_data->version . '.zip';
			$local_zip    = $this->download_and_save_file( $plugin_data->download_link, $aspire_dir, $zip_filename );
			if ( $local_zip ) {
				$local_download_url = $upload_dir['baseurl'] . '/aspirecloud/plugins/' . $plugin_data->slug . '/' . basename( $local_zip );
				$plugin_info->__set( 'download_link', $local_download_url );
			}
		}
	}

	/**
	 * Download and save a file locally.
	 *
	 * @param string $url       The URL to download.
	 * @param string $directory The directory to save to.
	 * @param string $filename  The filename to save as.
	 * @return string|false The local file path on success, false on failure.
	 */
	private function download_and_save_file( $url, $directory, $filename ) {
		// Download the file
		$temp_file = download_url( $url );

		if ( is_wp_error( $temp_file ) ) {
			return false;
		}

		// Get file extension from URL or temp file
		$extension = pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
		if ( empty( $extension ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $temp_file );
			finfo_close( $finfo );

			switch ( $mime ) {
				case 'image/jpeg':
					$extension = 'jpg';
					break;
				case 'image/png':
					$extension = 'png';
					break;
				case 'image/gif':
					$extension = 'gif';
					break;
				case 'application/zip':
					$extension = 'zip';
					break;
				default:
					$extension = 'bin';
			}
		}

		// Add extension if not present
		if ( ! pathinfo( $filename, PATHINFO_EXTENSION ) ) {
			$filename .= '.' . $extension;
		}

		$local_path = $directory . '/' . $filename;

		// Move the file to the final location
		if ( copy( $temp_file, $local_path ) ) {
			wp_delete_file( $temp_file );
			return $local_path;
		}

		wp_delete_file( $temp_file );
		return false;
	}
}
