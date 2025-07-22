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
class ThemeImport {

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
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_get_all_theme_slugs', [ $this, 'ajax_get_all_theme_slugs' ] );
		add_action( 'wp_ajax_import_theme_batch', [ $this, 'ajax_import_theme_batch' ] );
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
					'importing'              => __( 'Importing...', 'aspirecloud' ),
					'complete'               => __( 'Import Complete!', 'aspirecloud' ),
					'error'                  => __( 'Import Error!', 'aspirecloud' ),
					'getting_slugs'          => __( 'Getting theme list...', 'aspirecloud' ),
					'downloading_theme_data' => __( 'Downloading theme data...', 'aspirecloud' ),
					/* translators: %1$d: current page number, %2$d: total pages */
					'importing_page'         => __( 'Importing page %1$d of %2$d...', 'aspirecloud' ),
					/* translators: %1$d: current batch number, %2$d: total batches */
					'importing_batch'        => __( 'Importing batch %1$d of %2$d...', 'aspirecloud' ),
					/* translators: %1$d: imported count, %2$d: total themes */
					'imported_themes'        => __( 'Imported %1$d of %2$d themes', 'aspirecloud' ),
					'confirm_clear'          => __( 'Are you sure you want to clear all theme data? This action cannot be undone.', 'aspirecloud' ),
					'clearing_data'          => __( 'Clearing data...', 'aspirecloud' ),
					'data_cleared'           => __( 'Data cleared successfully!', 'aspirecloud' ),
					'getting_clear_count'    => __( 'Getting theme count for clearing...', 'aspirecloud' ),
					/* translators: %1$d: current batch number, %2$d: total batches */
					'clearing_batch'         => __( 'Clearing batch %1$d of %2$d...', 'aspirecloud' ),
					/* translators: %1$d: cleared count, %2$d: total themes */
					'cleared_themes'         => __( 'Cleared %1$d of %2$d themes', 'aspirecloud' ),
					'resting'                => __( 'Resting for 30 seconds...', 'aspirecloud' ),
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
			<h1><?php esc_html_e( 'Import Themes', 'aspirecloud' ); ?></h1>
			<p><?php esc_html_e( 'Import all themes from the WordPress.org repository into your local database.', 'aspirecloud' ); ?></p>

			<div class="aspirecloud-import-container">
				<div class="aspirecloud-import-button-container">
					<button id="import-themes-btn" class="button button-primary button-large">
						<?php esc_html_e( 'Import All Themes', 'aspirecloud' ); ?>
					</button>

					<button id="clear-themes-data-btn" class="button button-secondary button-large aspirecloud-clear-data-btn">
						<?php esc_html_e( 'Clear All Data', 'aspirecloud' ); ?>
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
	 * AJAX handler to get a batch of theme slugs.
	 */
	public function ajax_get_all_theme_slugs() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aspirecloud' ) );
		}

		// Include the themes API functions
		if ( ! function_exists( 'themes_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		$page     = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
		$per_page = 200; // Use smaller batch size for better performance

		// Get total values from JavaScript (if provided) or calculate on first page
		$total_themes = isset( $_POST['total_themes'] ) ? (int) $_POST['total_themes'] : 0;
		$total_pages  = isset( $_POST['total_pages'] ) ? (int) $_POST['total_pages'] : 0;

		// If this is the first page or totals not provided, get the total count
		if ( 1 === $page || 0 === $total_themes ) {
			$count_response = themes_api(
				'query_themes',
				[
					'per_page' => 1,
					'page'     => 1,
				]
			);

			if ( is_wp_error( $count_response ) ) {
				wp_send_json_error( $count_response->get_error_message() );
			}

			if ( ! isset( $count_response->info['results'] ) ) {
				wp_send_json_error( __( 'Unable to get total theme count.', 'aspirecloud' ) );
			}

			$total_themes = (int) $count_response->info['results'];
			$total_pages  = ceil( $total_themes / $per_page );
		}

		// Get theme slugs for this page
		$api_response = themes_api(
			'query_themes',
			[
				'per_page' => $per_page,
				'page'     => $page,
				'fields'   => [
					'slug'               => true,  // Only field we need
					'description'        => false,
					'sections'           => false,
					'rating'             => false,
					'ratings'            => false,
					'downloaded'         => false,
					'downloadlink'       => false,
					'last_updated'       => false,
					'tags'               => false,
					'homepage'           => false,
					'screenshots'        => false,
					'screenshot_count'   => false,
					'screenshot_url'     => false,
					'photon_screenshots' => false,
					'template'           => false,
					'parent'             => false,
					'versions'           => false,
					'theme_url'          => false,
					'extended_author'    => false,
				],
			]
		);

		if ( is_wp_error( $api_response ) ) {
			wp_send_json_error( $api_response->get_error_message() );
		}

		$slugs = [];
		if ( isset( $api_response->themes ) && is_array( $api_response->themes ) ) {
			foreach ( $api_response->themes as $theme ) {
				$slug = null;
				if ( is_object( $theme ) && isset( $theme->slug ) ) {
					$slug = $theme->slug;
				} elseif ( is_array( $theme ) && isset( $theme['slug'] ) ) {
					$slug = $theme['slug'];
				}

				if ( $slug ) {
					$slugs[] = $slug;
				}
			}
		}

		wp_send_json_success(
			[
				'theme_slugs'  => $slugs,
				'page'         => $page,
				'per_page'     => $per_page,
				'has_more'     => $page < $total_pages,
				'total_themes' => $total_themes,
				'total_pages'  => $total_pages,
			]
		);
	}

	/**
	 * AJAX handler to import a batch of themes using their slugs.
	 */
	public function ajax_import_theme_batch() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aspirecloud' ) );
		}

		// Include the themes API functions
		if ( ! function_exists( 'themes_api' ) ) {
			include_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		// Include download functions
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$theme_slugs = isset( $_POST['theme_slugs'] ) ? (array) $_POST['theme_slugs'] : [];

		if ( empty( $theme_slugs ) ) {
			wp_send_json_error( __( 'No theme slugs provided.', 'aspirecloud' ) );
		}

		$imported_count = 0;
		$errors         = [];

		foreach ( $theme_slugs as $slug ) {
			try {
				// Get detailed theme information
				$theme_data = themes_api( 'theme_information', [ 'slug' => $slug ] );

				if ( is_wp_error( $theme_data ) ) {
					$errors[] = sprintf(
						/* translators: %1$s: theme slug, %2$s: error message */
						__( 'Error getting info for %1$s: %2$s', 'aspirecloud' ),
						$slug,
						$theme_data->get_error_message()
					);
					continue;
				}

				$theme_info = new ThemeInfo();

				// Map API data to ThemeInfo properties
				$this->map_detailed_theme_data( $theme_info, $theme_data );

				// Download and localize assets
				$this->download_theme_assets( $theme_info, $theme_data );

				// Check if theme already exists
				$existing_theme = $this->themes_model->get_theme_by_slug( $slug );

				if ( $existing_theme ) {
					$result = $this->themes_model->update_theme_post( $existing_theme->ID, $theme_info );
				} else {
					$result = $this->themes_model->create_theme_post( $theme_info );
				}

				if ( ! is_wp_error( $result ) ) {
					++$imported_count;
				} else {
					$errors[] = sprintf(
						/* translators: %1$s: theme name, %2$s: error message */
						__( 'Error importing %1$s: %2$s', 'aspirecloud' ),
						$theme_data->name ?? $slug,
						$result->get_error_message()
					);
				}
			} catch ( \Exception $e ) {
				$errors[] = sprintf(
					/* translators: %1$s: theme slug, %2$s: error message */
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
	 * Map detailed API theme data to ThemeInfo object.
	 *
	 * @param ThemeInfo $theme_info The theme info object.
	 * @param object    $theme_data The API theme data.
	 */
	private function map_detailed_theme_data( $theme_info, $theme_data ) {
		// Basic properties
		$theme_info->__set( 'name', $theme_data->name ?? '' );
		$theme_info->__set( 'slug', $theme_data->slug ?? '' );
		$theme_info->__set( 'version', $theme_data->version ?? '' );
		$theme_info->__set( 'author', $theme_data->author ?? '' );
		$theme_info->__set( 'description', $theme_data->description ?? '' );
		$theme_info->__set( 'preview_url', $theme_data->preview_url ?? '' );
		$theme_info->__set( 'download_link', $theme_data->download_link ?? '' );

		// Numeric properties
		$theme_info->__set( 'downloaded', $theme_data->downloaded ?? 0 );
		$theme_info->__set( 'active_installs', $theme_data->active_installs ?? 0 );
		$theme_info->__set( 'num_ratings', $theme_data->num_ratings ?? 0 );

		// WordPress version requirements
		$theme_info->__set( 'requires', $theme_data->requires ?? '' );
		$theme_info->__set( 'tested', $theme_data->tested ?? '' );
		$theme_info->__set( 'requires_php', $theme_data->requires_php ?? '' );

		// Dates
		$theme_info->__set( 'creation_time', $theme_data->creation_time ?? '' );
		$theme_info->__set( 'last_updated', $theme_data->last_updated ?? '' );

		// Array properties
		if ( isset( $theme_data->tags ) && is_array( $theme_data->tags ) ) {
			$theme_info->__set( 'tags', array_keys( $theme_data->tags ) );
		}

		if ( isset( $theme_data->sections ) && is_array( $theme_data->sections ) ) {
			$theme_info->__set( 'sections', $theme_data->sections );
		}

		if ( isset( $theme_data->ratings ) && is_array( $theme_data->ratings ) ) {
			$theme_info->__set( 'ratings', $theme_data->ratings );
		}

		if ( isset( $theme_data->screenshot_url ) ) {
			$theme_info->__set( 'screenshot_url', $theme_data->screenshot_url );
		}
	}

	/**
	 * Download and localize theme assets (screenshots, zip files).
	 *
	 * @param ThemeInfo $theme_info The theme info object.
	 * @param object    $theme_data The API theme data.
	 */
	private function download_theme_assets( $theme_info, $theme_data ) {
		$upload_dir = wp_upload_dir();
		$aspire_dir = $upload_dir['basedir'] . '/aspirecloud/themes/' . $theme_data->slug;

		// Create directory if it doesn't exist
		if ( ! file_exists( $aspire_dir ) ) {
			wp_mkdir_p( $aspire_dir );
		}

		// Download screenshot
		if ( ! empty( $theme_data->screenshot_url ) ) {
			$screenshot_filename = 'screenshot.png';
			$local_screenshot    = $this->download_and_save_file( $theme_data->screenshot_url, $aspire_dir, $screenshot_filename );
			if ( $local_screenshot ) {
				$local_screenshot_url = $upload_dir['baseurl'] . '/aspirecloud/themes/' . $theme_data->slug . '/' . basename( $local_screenshot );
				$theme_info->__set( 'screenshot_url', $local_screenshot_url );
			}
		}

		// Download theme ZIP file
		if ( ! empty( $theme_data->download_link ) ) {
			$zip_filename = $theme_data->slug . '-' . $theme_data->version . '.zip';
			$local_zip    = $this->download_and_save_file( $theme_data->download_link, $aspire_dir, $zip_filename );
			if ( $local_zip ) {
				$local_download_url = $upload_dir['baseurl'] . '/aspirecloud/themes/' . $theme_data->slug . '/' . basename( $local_zip );
				$theme_info->__set( 'download_link', $local_download_url );
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

	/**
	 * AJAX handler to get the total count of themes for clearing.
	 */
	public function ajax_get_themes_clear_count() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aspirecloud' ) );
		}

		$total_themes  = wp_count_posts( 'ac_theme' );
		$total_count   = array_sum( (array) $total_themes ) - ( $total_themes->auto_draft ?? 0 );
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
	 * AJAX handler to clear all theme data.
	 */
	public function ajax_clear_themes_data() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aspirecloud' ) );
		}

		$batch_size = 25;

		// Get next batch of themes (always get the first 25, since we're deleting them)
		$themes = get_posts(
			[
				'post_type'      => 'ac_theme',
				'posts_per_page' => $batch_size,
				'post_status'    => 'any',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$deleted_count = 0;
		$errors        = [];

		foreach ( $themes as $theme ) {
			$result = wp_delete_post( $theme->ID, true );
			if ( $result ) {
				++$deleted_count;
			} else {
				$errors[] = sprintf(
					/* translators: %s: theme title */
					__( 'Failed to delete theme: %s', 'aspirecloud' ),
					$theme->post_title
				);
			}
		}

		// Check if there are more themes to delete
		$remaining_themes = get_posts(
			[
				'post_type'      => 'ac_theme',
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
				'has_more'      => count( $remaining_themes ) > 0,
			]
		);
	}
}
