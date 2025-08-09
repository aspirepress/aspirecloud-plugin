<?php
/**
 * Assets Importer Base Controller Class.
 *
 * @package aspirecloud
 * @author  AspirePress
 */

namespace AspireCloud\Controller;

/**
 * Class AssetsImporter
 *
 * Base class for importing assets (plugins/themes) from the WordPress.org API.
 * Contains common functionality shared between plugin and theme importers.
 */
abstract class AssetsImporter {

	/**
	 * Batch size for metadata import operations.
	 */
	const METADATA_BATCH_SIZE = 100;

	/**
	 * Batch size for file download operations.
	 */
	const DOWNLOAD_BATCH_SIZE = 5;

	/**
	 * Batch size for clearing operations.
	 */
	const CLEAR_BATCH_SIZE = 1000;

	/**
	 * Database optimization state tracking.
	 *
	 * @var bool
	 */
	private static $db_optimized = false;

	/**
	 * The asset type (plugin or theme).
	 *
	 * @var string
	 */
	protected $asset_type;

	/**
	 * The post type for the assets.
	 *
	 * @var string
	 */
	protected $post_type;

	/**
	 * The model instance for handling assets.
	 *
	 * @var object
	 */
	protected $model;

	/**
	 * Constructor to register AJAX handlers.
	 */
	public function __construct() {
		// Child classes should call register_common_ajax_handlers() after setting up their properties
	}

	/**
	 * Register common AJAX handlers for database operations.
	 * Called by child classes after they initialize their properties.
	 */
	protected function register_common_ajax_handlers() {
		add_action( 'wp_ajax_aspirecloud_restore_database', [ $this, 'ajax_restore_database' ] );
		add_action( 'wp_ajax_aspirecloud_check_db_optimization', [ $this, 'ajax_check_db_optimization' ] );
	}

	/**
	 * Enqueue common scripts and styles.
	 */
	protected function enqueue_common_scripts() {
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

		// Enqueue the performance tracker class
		wp_enqueue_script(
			'aspirecloud-performance-tracker',
			AC_URL . 'assets/js/performance-tracker.js',
			[],
			AC_VERSION,
			true
		);

		// Enqueue the logger class
		wp_enqueue_script(
			'aspirecloud-logger',
			AC_URL . 'assets/js/logger.js',
			[ 'jquery' ],
			AC_VERSION,
			true
		);

		// Enqueue the metadata importer class
		wp_enqueue_script(
			'aspirecloud-metadata-importer',
			AC_URL . 'assets/js/metadata-importer.js',
			[ 'jquery', 'aspirecloud-performance-tracker', 'aspirecloud-logger' ],
			AC_VERSION,
			true
		);

		// Enqueue the file downloader class
		wp_enqueue_script(
			'aspirecloud-file-downloader',
			AC_URL . 'assets/js/file-downloader.js',
			[ 'jquery', 'aspirecloud-performance-tracker', 'aspirecloud-logger' ],
			AC_VERSION,
			true
		);

		// Enqueue the database manager class
		wp_enqueue_script(
			'aspirecloud-database-manager',
			AC_URL . 'assets/js/database-manager.js',
			[ 'jquery', 'aspirecloud-logger' ],
			AC_VERSION,
			true
		);

		// Enqueue the main import assets controller
		wp_enqueue_script(
			'aspirecloud-import-assets-controller',
			AC_URL . 'assets/js/import-assets-controller.js',
			[ 'jquery', 'aspirecloud-logger', 'aspirecloud-metadata-importer', 'aspirecloud-file-downloader', 'aspirecloud-database-manager' ],
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
			[ 'jquery', 'aspirecloud-progress-bar', 'aspirecloud-import-assets-controller', 'aspirecloud-clear-assets' ],
			AC_VERSION,
			true
		);
	}

	/**
	 * Localize common AJAX scripts with base strings.
	 *
	 * @param array $additional_strings Additional strings specific to the asset type.
	 */
	protected function localize_common_scripts( $additional_strings = [] ) {
		$base_strings = [
			'importing'           => __( 'Importing...', 'aspirecloud' ),
			'complete'            => __( 'Import Complete!', 'aspirecloud' ),
			'error'               => __( 'Import Error!', 'aspirecloud' ),
			'clearing_data'       => __( 'Clearing data...', 'aspirecloud' ),
			'data_cleared'        => __( 'Data cleared successfully!', 'aspirecloud' ),
			'confirm_clear'       => __( 'Are you sure you want to clear all data? This action cannot be undone.', 'aspirecloud' ),
			'getting_clear_count' => sprintf(
				/* translators: %s: asset type (plugin/theme) */
				__( 'Getting %s count for clearing...', 'aspirecloud' ),
				$this->asset_type
			),
			/* translators: %1$d: current batch number, %2$d: total batches */
			'clearing_batch'      => __( 'Clearing batch %1$d of %2$d...', 'aspirecloud' ),
			'resting'             => __( 'Resting for 30 seconds...', 'aspirecloud' ),
			'restoring_database'  => __( 'Restoring database features...', 'aspirecloud' ),
			'database_restored'   => __( 'Database features restored successfully!', 'aspirecloud' ),
			'confirm_restore'     => __( 'Are you sure you want to restore database features? This should only be done if an import failed.', 'aspirecloud' ),
		];

		$strings = array_merge( $base_strings, $additional_strings );

		wp_localize_script(
			'aspirecloud-admin',
			'aspirecloud_ajax',
			[
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'aspirecloud_import_nonce' ),
				'strings'             => $strings,
				'metadata_batch_size' => self::METADATA_BATCH_SIZE,
				'download_batch_size' => self::DOWNLOAD_BATCH_SIZE,
				'clear_batch_size'    => self::CLEAR_BATCH_SIZE,
			]
		);
	}

	/**
	 * Render common admin page structure.
	 *
	 * @param string $page_title The page title.
	 * @param string $page_description The page description.
	 * @param string $import_button_text The import button text.
	 * @param string $import_button_id The import button ID.
	 * @param string $clear_button_text The clear button text.
	 * @param string $clear_button_id The clear button ID.
	 */
	protected function render_common_admin_page( $page_title, $page_description, $import_button_text, $import_button_id, $clear_button_text, $clear_button_id ) {
		?>
		<div class="wrap aspirecloud-import-page">
			<h1><?php echo esc_html( $page_title ); ?></h1>
			<p><?php echo esc_html( $page_description ); ?></p>

			<div class="aspirecloud-import-container">
				<div class="aspirecloud-import-button-container">
					<button id="<?php echo esc_attr( $import_button_id ); ?>" class="button button-primary button-large">
						<?php echo esc_html( $import_button_text ); ?>
					</button>

					<button id="<?php echo esc_attr( $clear_button_id ); ?>" class="button button-secondary button-large aspirecloud-clear-data-btn">
						<?php echo esc_html( $clear_button_text ); ?>
					</button>

					<button id="restore-database-btn" class="button button-secondary button-large" style="<?php echo get_option( 'aspirecloud_db_optimized' ) ? '' : 'display: none;'; ?>">
						<?php esc_html_e( 'Restore Database', 'aspirecloud' ); ?>
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

				<div class="aspirecloud-log-container" style="display: none;">
					<div class="aspirecloud-log-header">
						<h4><?php esc_html_e( 'Operation Log', 'aspirecloud' ); ?></h4>
						<button type="button" class="aspirecloud-log-toggle" aria-label="<?php esc_attr_e( 'Toggle log visibility', 'aspirecloud' ); ?>">
							<span class="aspirecloud-log-toggle-text"><?php esc_html_e( 'Hide', 'aspirecloud' ); ?></span>
						</button>
						<button type="button" class="aspirecloud-log-clear" aria-label="<?php esc_attr_e( 'Clear log', 'aspirecloud' ); ?>">
							<?php esc_html_e( 'Clear', 'aspirecloud' ); ?>
						</button>
					</div>
					<div class="aspirecloud-log-content">
						<div class="aspirecloud-log-entries"></div>
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
	 * Download and save a file locally.
	 *
	 * @param string $url       The URL to download.
	 * @param string $directory The directory to save to.
	 * @param string $filename  The filename to save as.
	 * @return string|false The local file path on success, false on failure.
	 */
	protected function download_and_save_file( $url, $directory, $filename ) {
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
	 * Download banners/icons and return local URLs.
	 *
	 * @param array  $urls_array   Array of size => url pairs.
	 * @param string $aspire_dir   Directory to save files.
	 * @param string $slug         Asset slug.
	 * @param string $asset_type   Asset type (plugins/themes).
	 * @param string $file_prefix  File prefix (banner, icon, etc.).
	 * @return array Array of size => local_url pairs.
	 */
	protected function download_image_assets( $urls_array, $aspire_dir, $slug, $asset_type, $file_prefix ) {
		if ( empty( $urls_array ) || ! is_array( $urls_array ) ) {
			return [];
		}

		$upload_dir   = wp_upload_dir();
		$local_assets = [];

		foreach ( $urls_array as $size => $url ) {
			if ( ! empty( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$local_path = $this->download_and_save_file( $url, $aspire_dir, $file_prefix . '-' . $size );
				if ( $local_path ) {
					$local_assets[ $size ] = $upload_dir['baseurl'] . '/aspirecloud/' . $asset_type . '/' . $slug . '/' . basename( $local_path );
				}
			}
		}

		return $local_assets;
	}

	/**
	 * Download ZIP file and return local URL.
	 *
	 * @param string $download_url URL to download.
	 * @param string $aspire_dir   Directory to save file.
	 * @param string $slug         Asset slug.
	 * @param string $version      Asset version.
	 * @param string $asset_type   Asset type (plugins/themes).
	 * @return string|false Local URL on success, false on failure.
	 */
	protected function download_zip_file( $download_url, $aspire_dir, $slug, $version, $asset_type ) {
		if ( empty( $download_url ) || ! filter_var( $download_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$upload_dir   = wp_upload_dir();
		$zip_filename = $slug . '-' . $version . '.zip';
		$local_zip    = $this->download_and_save_file( $download_url, $aspire_dir, $zip_filename );

		if ( $local_zip ) {
			return $upload_dir['baseurl'] . '/aspirecloud/' . $asset_type . '/' . $slug . '/' . basename( $local_zip );
		}

		return false;
	}

	/**
	 * Download single image file and return local URL.
	 *
	 * @param string $image_url  URL to download.
	 * @param string $aspire_dir Directory to save file.
	 * @param string $slug       Asset slug.
	 * @param string $asset_type Asset type (plugins/themes).
	 * @param string $filename   Filename to save as.
	 * @return string|false Local URL on success, false on failure.
	 */
	protected function download_single_image( $image_url, $aspire_dir, $slug, $asset_type, $filename ) {
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$local_path = $this->download_and_save_file( $image_url, $aspire_dir, $filename );

		if ( $local_path ) {
			return $upload_dir['baseurl'] . '/aspirecloud/' . $asset_type . '/' . $slug . '/' . basename( $local_path );
		}

		return false;
	}

	/**
	 * Create asset directory if it doesn't exist.
	 *
	 * @param string $slug       Asset slug.
	 * @param string $asset_type Asset type (plugins/themes).
	 * @return string Directory path.
	 */
	protected function create_asset_directory( $slug, $asset_type ) {
		$upload_dir = wp_upload_dir();
		$aspire_dir = $upload_dir['basedir'] . '/aspirecloud/' . $asset_type . '/' . $slug;

		if ( ! file_exists( $aspire_dir ) ) {
			wp_mkdir_p( $aspire_dir );
		}

		return $aspire_dir;
	}

	/**
	 * Common AJAX handler to get the total count of assets for clearing.
	 */
	public function ajax_get_clear_count() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aspirecloud' ) );
		}

		$total_assets  = wp_count_posts( $this->post_type );
		$total_count   = array_sum( (array) $total_assets ) - ( $total_assets->auto_draft ?? 0 );
		$batch_size    = self::CLEAR_BATCH_SIZE;
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
	 * Common AJAX handler to clear all asset data.
	 */
	public function ajax_clear_data() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'aspirecloud' ) );
		}

		global $wpdb;

		$batch_size = self::CLEAR_BATCH_SIZE;

		// Get next batch of asset IDs
		$asset_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s LIMIT %d",
				$this->post_type,
				$batch_size
			)
		);

		$deleted_count = 0;
		$errors        = [];

		if ( ! empty( $asset_ids ) ) {
			// Sanitize asset IDs and create IN clause
			$sanitized_ids = array_map( 'absint', $asset_ids );
			$ids_string    = implode( ',', $sanitized_ids );

			// Delete post meta first (foreign key constraint)
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_string is sanitized with absint
			$meta_result = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($ids_string)" );

			// Delete posts
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ids_string is sanitized with absint
			$posts_result = $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ($ids_string)" );

			if ( false === $meta_result || false === $posts_result ) {
				$errors[] = __( 'Failed to delete assets via direct SQL.', 'aspirecloud' );
			} else {
				$deleted_count = count( $asset_ids );
			}
		}

		// Check if there are more assets to delete
		$remaining_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s",
				$this->post_type
			)
		);

		wp_send_json_success(
			[
				'deleted_count' => $deleted_count,
				'batch_size'    => $batch_size,
				'errors'        => $errors,
				'has_more'      => $remaining_count > 0,
			]
		);
	}

	/**
	 * Common AJAX handler to import metadata in batches.
	 * Phase 1: Import all metadata first without downloading assets.
	 */
	public function ajax_import_metadata_batch() {
		$this->check_ajax_permissions();
		$this->prepare_import_environment();

		// Initialize pagination parameters
		$pagination = $this->initialize_pagination_parameters();

		// Get assets data for current page
		$assets_data = $this->get_assets_page( $pagination['page'], $pagination['per_page'] );
		if ( is_wp_error( $assets_data ) ) {
			wp_send_json_error( $assets_data->get_error_message() );
		}

		// Process assets and prepare for bulk operations
		$import_data = $this->process_assets_for_import( $assets_data['items'] );

		// Execute bulk database operations
		$this->execute_bulk_import_operations( $import_data );

		// Clean up and send response
		$this->cleanup_and_respond( $import_data, $pagination );
	}

	/**
	 * Prepare the environment for import operations.
	 */
	private function prepare_import_environment() {
		wp_raise_memory_limit( 'admin' );
		set_time_limit( 300 );
		wp_cache_flush();

		// Optimize database for the first batch
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in check_ajax_permissions()
		$page = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
		if ( 1 === $page ) {
			$this->optimize_database_for_import();
		}
	}

	/**
	 * Initialize pagination parameters for the import batch.
	 *
	 * @return array Pagination data including page, per_page, total_assets, total_pages.
	 */
	private function initialize_pagination_parameters() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in check_ajax_permissions()
		$page     = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
		$per_page = self::METADATA_BATCH_SIZE; // Use centralized batch size

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in check_ajax_permissions()
		$total_assets = isset( $_POST['total_assets'] ) ? (int) $_POST['total_assets'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in check_ajax_permissions()
		$total_pages = isset( $_POST['total_pages'] ) ? (int) $_POST['total_pages'] : 0;

		// Calculate totals on first page or if not provided
		if ( 1 === $page || 0 === $total_assets ) {
			$count_data = $this->get_total_assets_count();
			if ( is_wp_error( $count_data ) ) {
				wp_send_json_error( $count_data->get_error_message() );
			}
			$total_assets = $count_data['total'];
			$total_pages  = ceil( $total_assets / $per_page );
		}

		return [
			'page'         => $page,
			'per_page'     => $per_page,
			'total_assets' => $total_assets,
			'total_pages'  => $total_pages,
		];
	}

	/**
	 * Process assets from API data and prepare for bulk import.
	 *
	 * @param array $assets_items Array of asset data from API.
	 * @return array Import data with separated new and existing assets.
	 */
	private function process_assets_for_import( $assets_items ) {
		global $wpdb;

		$import_data = [
			'new_posts'        => [],
			'new_meta'         => [],
			'update_post_ids'  => [],
			'update_meta_data' => [],
			'imported_count'   => 0,
			'errors'           => [],
		];

		// Check if we have assets to process
		if ( empty( $assets_items ) ) {
			$import_data['errors'][] = 'No assets data received from API';
			return $import_data;
		}

		$current_time     = current_time( 'mysql' );
		$current_time_gmt = current_time( 'mysql', 1 );

		foreach ( $assets_items as $asset_data ) {
			try {
				// Check asset data structure
				if ( ! isset( $asset_data['slug'] ) ) {
					$import_data['errors'][] = sprintf( 'Asset missing slug property. Available properties: %s', implode( ', ', array_keys( $asset_data ) ) );
					continue;
				}

				$existing_asset = $this->get_asset_by_slug( $asset_data['slug'] );
				$meta_data      = $this->convert_api_data_to_meta( $asset_data );

				if ( $existing_asset ) {
					$this->prepare_existing_asset_update( $existing_asset, $asset_data, $meta_data, $import_data );
				} else {
					$this->prepare_new_asset_insert( $asset_data, $meta_data, $current_time, $current_time_gmt, $import_data );
				}

				++$import_data['imported_count'];

			} catch ( \Exception $e ) {
				$asset_array             = is_object( $asset_data ) ? (array) $asset_data : $asset_data;
				$import_data['errors'][] = sprintf(
					/* translators: %1$s: asset slug, %2$s: error message */
					__( 'Exception importing %1$s: %2$s', 'aspirecloud' ),
					isset( $asset_array['slug'] ) ? $asset_array['slug'] : 'unknown',
					$e->getMessage()
				);
			}
		}

		return $import_data;
	}

	/**
	 * Convert API data to WordPress meta format.
	 *
	 * @param array $asset_data Asset data from API.
	 * @return array Meta data array.
	 */
	private function convert_api_data_to_meta( $asset_data ) {
		$meta_data = [];

		foreach ( $asset_data as $property => $value ) {
			if ( null !== $value && '' !== $value ) {
				$meta_data[] = [
					'meta_key'   => '__' . $property, // Prefix all meta keys with double underscore to make them private
					'meta_value' => is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : (string) $value,
				];
			}
		}

		return $meta_data;
	}

	/**
	 * Prepare existing asset for update.
	 *
	 * @param WP_Post $existing_asset Existing asset post.
	 * @param array   $asset_data     Asset data from API.
	 * @param array   $meta_data      Meta data array.
	 * @param array   &$import_data   Import data array (passed by reference).
	 */
	private function prepare_existing_asset_update( $existing_asset, $asset_data, $meta_data, &$import_data ) {
		global $wpdb;

		$import_data['update_post_ids'][]                       = $existing_asset->ID;
		$import_data['update_meta_data'][ $existing_asset->ID ] = $meta_data;

		// Update post title if changed
		$new_title = sanitize_text_field( $asset_data['name'] ?? $asset_data['slug'] );
		if ( $existing_asset->post_title !== $new_title ) {
			$wpdb->update(
				$wpdb->posts,
				[ 'post_title' => $new_title ],
				[ 'ID' => $existing_asset->ID ],
				[ '%s' ],
				[ '%d' ]
			);
		}
	}

	/**
	 * Prepare new asset for insert.
	 *
	 * @param array  $asset_data       Asset data from API.
	 * @param array  $meta_data        Meta data array.
	 * @param string $current_time     Current time.
	 * @param string $current_time_gmt Current GMT time.
	 * @param array  &$import_data     Import data array (passed by reference).
	 */
	private function prepare_new_asset_insert( $asset_data, $meta_data, $current_time, $current_time_gmt, &$import_data ) {
		global $wpdb;

		$post_title = sanitize_text_field( $asset_data['name'] ?? $asset_data['slug'] );
		$post_name  = sanitize_title( $asset_data['slug'] );

		$import_data['new_posts'][] = $wpdb->prepare(
			'(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)',
			$post_title,
			'',
			'publish',
			'closed',
			'closed',
			'',
			$post_name,
			'',
			$current_time,
			$current_time_gmt,
			$this->post_type
		);

		$import_data['new_meta'][] = $meta_data;
	}

	/**
	 * Execute bulk database operations for import.
	 *
	 * @param array &$import_data Import data with new and existing assets (passed by reference).
	 */
	private function execute_bulk_import_operations( &$import_data ) {
		if ( ! empty( $import_data['new_posts'] ) ) {
			$this->bulk_insert_new_posts( $import_data['new_posts'], $import_data['new_meta'] );
		}

		if ( ! empty( $import_data['update_post_ids'] ) ) {
			$this->bulk_update_existing_posts( $import_data['update_post_ids'], $import_data['update_meta_data'] );
		}
	}

	/**
	 * Bulk insert new posts and their metadata.
	 *
	 * @param array $post_values Post values for SQL insert.
	 * @param array $meta_values Meta values for each post.
	 */
	private function bulk_insert_new_posts( $post_values, $meta_values ) {
		global $wpdb;

		$sql = "INSERT INTO {$wpdb->posts} (post_title, post_content, post_status, comment_status, ping_status, post_password, post_name, post_excerpt, post_date, post_date_gmt, post_type) VALUES " . implode( ', ', $post_values );

		if ( false === $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			wp_send_json_error( __( 'Failed to insert posts via bulk SQL.', 'aspirecloud' ) );
		}

		$this->bulk_insert_meta_for_new_posts( $meta_values, $wpdb->insert_id, count( $post_values ) );
	}

	/**
	 * Bulk insert meta data for newly created posts.
	 *
	 * @param array $meta_values    Meta values for each post.
	 * @param int   $first_post_id  First post ID from the insert.
	 * @param int   $posts_count    Number of posts inserted.
	 */
	private function bulk_insert_meta_for_new_posts( $meta_values, $first_post_id, $posts_count ) {
		global $wpdb;

		$meta_insert_values = [];

		foreach ( range( $first_post_id, $first_post_id + $posts_count - 1 ) as $index => $post_id ) {
			if ( isset( $meta_values[ $index ] ) ) {
				foreach ( $meta_values[ $index ] as $meta_data ) {
					$meta_insert_values[] = $wpdb->prepare(
						'(%d, %s, %s)',
						$post_id,
						$meta_data['meta_key'],
						$meta_data['meta_value']
					);
				}
			}
		}

		if ( ! empty( $meta_insert_values ) ) {
			$meta_sql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $meta_insert_values );
			$wpdb->query( $meta_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Bulk update existing posts metadata.
	 *
	 * @param array $update_post_ids  Post IDs to update.
	 * @param array $update_meta_data Meta data for each post.
	 */
	private function bulk_update_existing_posts( $update_post_ids, $update_meta_data ) {
		global $wpdb;

		// Delete existing meta data for clean update
		$post_ids_placeholder = implode( ',', array_fill( 0, count( $update_post_ids ), '%d' ) );
		$delete_meta_sql      = "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($post_ids_placeholder)";
		$wpdb->query( $wpdb->prepare( $delete_meta_sql, $update_post_ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

		// Insert updated meta data
		$update_meta_insert_values = [];
		foreach ( $update_post_ids as $post_id ) {
			if ( isset( $update_meta_data[ $post_id ] ) ) {
				foreach ( $update_meta_data[ $post_id ] as $meta_data ) {
					$update_meta_insert_values[] = $wpdb->prepare(
						'(%d, %s, %s)',
						$post_id,
						$meta_data['meta_key'],
						$meta_data['meta_value']
					);
				}
			}
		}

		if ( ! empty( $update_meta_insert_values ) ) {
			$update_meta_sql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $update_meta_insert_values );
			$wpdb->query( $update_meta_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Clean up memory and send response.
	 *
	 * @param array $import_data Import data with results.
	 * @param array $pagination  Pagination data.
	 */
	private function cleanup_and_respond( $import_data, $pagination ) {
		// Clean up memory
		unset( $import_data['new_posts'], $import_data['new_meta'], $import_data['update_post_ids'], $import_data['update_meta_data'] );
		wp_cache_flush();
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		}

		// Restore database optimization on the last batch
		if ( $pagination['page'] >= $pagination['total_pages'] ) {
			$this->restore_database_after_import();
		}

		wp_send_json_success(
			[
				'imported_count' => $import_data['imported_count'],
				'page'           => $pagination['page'],
				'per_page'       => $pagination['per_page'],
				'has_more'       => $pagination['page'] < $pagination['total_pages'],
				'total_assets'   => $pagination['total_assets'],
				'total_pages'    => $pagination['total_pages'],
				'errors'         => $import_data['errors'],
			]
		);
	}

	/**
	 * Common AJAX handler to download assets.
	 * Phase 2: Download all asset files (banners, icons, zip files).
	 */
	public function ajax_download_assets_batch() {
		$this->check_ajax_permissions();

		// Include download functions
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$batch_size = self::DOWNLOAD_BATCH_SIZE; // Use centralized batch size
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in check_ajax_permissions()
		$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;

		// Get assets that need file downloads
		$assets = get_posts(
			[
				'post_type'      => $this->post_type,
				'posts_per_page' => $batch_size,
				'post_status'    => 'publish',
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		// Get total count for progress calculation
		if ( 0 === $offset ) {
			$total_assets = wp_count_posts( $this->post_type );
			$total_count  = $total_assets->publish ?? 0;
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is done in check_ajax_permissions()
			$total_count = isset( $_POST['total_assets'] ) ? (int) $_POST['total_assets'] : 0;
		}

		$downloaded_count = 0;
		$errors           = [];

		foreach ( $assets as $asset_post ) {
			try {
				$slug       = get_post_meta( $asset_post->ID, '__slug', true );
				$asset_info = $this->create_asset_info();

				// Load existing metadata
				$this->load_asset_metadata( $asset_info, $asset_post->ID );

				// Download and update asset files
				$this->download_asset_files( $asset_info, $asset_post->ID, $slug );

				++$downloaded_count;
			} catch ( \Exception $e ) {
				$errors[] = sprintf(
					/* translators: %1$s: asset title, %2$s: error message */
					__( 'Exception downloading files for %1$s: %2$s', 'aspirecloud' ),
					$asset_post->post_title,
					$e->getMessage()
				);
			}
		}

		$new_offset   = $offset + $batch_size;
		$has_more     = count( $assets ) === $batch_size;
		$total_offset = $offset + $downloaded_count;

		wp_send_json_success(
			[
				'downloaded_count' => $downloaded_count,
				'offset'           => $new_offset,
				'total_processed'  => $total_offset,
				'total_assets'     => $total_count,
				'has_more'         => $has_more,
				'errors'           => $errors,
			]
		);
	}

	/**
	 * Common AJAX handler to get the total count of assets for import.
	 */
	public function ajax_get_total_assets_count() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aspirecloud' ) );
		}

		$count_data = $this->get_total_assets_count();

		if ( is_wp_error( $count_data ) ) {
			wp_send_json_error( $count_data->get_error_message() );
		}

		wp_send_json_success( $count_data );
	}

	/**
	 * Common permission check for AJAX handlers.
	 */
	protected function check_ajax_permissions() {
		check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'aspirecloud' ) );
		}
	}

	/**
	 * Optimize database for bulk import operations.
	 * Disables indexes and other features to speed up imports.
	 */
	protected function optimize_database_for_import() {
		global $wpdb;

		if ( self::$db_optimized || get_option( 'aspirecloud_db_optimized' ) ) {
			return; // Already optimized
		}

		// Store optimization start time and state
		update_option( 'aspirecloud_db_optimized', current_time( 'mysql' ) );
		update_option( 'aspirecloud_db_optimization_start', current_time( 'mysql' ) );

		// Store original autocommit setting
		$wpdb->query( 'SET @original_autocommit = @@autocommit' );
		$wpdb->query( 'SET @original_unique_checks = @@unique_checks' );
		$wpdb->query( 'SET @original_foreign_key_checks = @@foreign_key_checks' );

		// Disable autocommit for better performance
		$wpdb->query( 'SET autocommit = 0' );

		// Disable unique checks
		$wpdb->query( 'SET unique_checks = 0' );

		// Disable foreign key checks (WordPress doesn't use them, but just in case)
		$wpdb->query( 'SET foreign_key_checks = 0' );

		// Disable indexes on posts and postmeta tables for faster inserts
		$wpdb->query( "ALTER TABLE {$wpdb->posts} DISABLE KEYS" );
		$wpdb->query( "ALTER TABLE {$wpdb->postmeta} DISABLE KEYS" );

		self::$db_optimized = true;
	}

	/**
	 * Restore database to normal operation after bulk import.
	 * Re-enables indexes and other features.
	 */
	protected function restore_database_after_import() {
		global $wpdb;

		if ( ! self::$db_optimized && ! get_option( 'aspirecloud_db_optimized' ) ) {
			return; // Not optimized
		}

		// Re-enable indexes
		$wpdb->query( "ALTER TABLE {$wpdb->posts} ENABLE KEYS" );
		$wpdb->query( "ALTER TABLE {$wpdb->postmeta} ENABLE KEYS" );

		// Restore original settings
		$wpdb->query( 'SET autocommit = @original_autocommit' );
		$wpdb->query( 'SET unique_checks = @original_unique_checks' );
		$wpdb->query( 'SET foreign_key_checks = @original_foreign_key_checks' );

		// Commit any remaining transactions
		$wpdb->query( 'COMMIT' );

		self::$db_optimized = false;

		// Store completion time and remove optimization state
		update_option( 'aspirecloud_db_optimization_end', current_time( 'mysql' ) );
		delete_option( 'aspirecloud_db_optimized' );
		delete_option( 'aspirecloud_db_optimization_start' );
	}

	/**
	 * AJAX handler to manually restore database features.
	 * Used as a recovery mechanism if import fails.
	 */
	public function ajax_restore_database() {
		// Clean any output that might have been generated
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Set JSON header
		header( 'Content-Type: application/json' );

		try {
			// Check if nonce exists
			if ( ! isset( $_POST['nonce'] ) ) {
				wp_send_json_error(
					[
						'message' => 'Nonce missing from request.',
						'debug'   => 'No nonce parameter in POST data',
					]
				);
				return;
			}

			// Verify nonce
			if ( ! wp_verify_nonce( $_POST['nonce'], 'aspirecloud_import_nonce' ) ) {
				wp_send_json_error(
					[
						'message' => 'Security check failed.',
						'debug'   => 'Nonce verification failed',
					]
				);
				return;
			}

			// Check permissions
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error(
					[
						'message' => 'Insufficient permissions.',
						'debug'   => 'User cannot manage options',
					]
				);
				return;
			}

			// Try to restore database
			$this->restore_database_after_import();

			wp_send_json_success(
				[
					'message' => __( 'Database features restored successfully.', 'aspirecloud' ),
					'debug'   => 'Database restoration completed',
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => __( 'Error restoring database features.', 'aspirecloud' ),
					'error'   => $e->getMessage(),
					'debug'   => 'Exception caught during restoration',
				]
			);
		}
	}

	/**
	 * AJAX handler to check database optimization state.
	 * Used to show/hide the recovery button.
	 */
	public function ajax_check_db_optimization() {
		// Clean any output that might have been generated
		if ( ob_get_level() ) {
			ob_clean();
		}

		try {
			check_ajax_referer( 'aspirecloud_import_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Insufficient permissions.', 'aspirecloud' ) );
				return;
			}

			$optimized  = get_option( 'aspirecloud_db_optimized' );
			$start_time = get_option( 'aspirecloud_db_optimization_start' );

			wp_send_json_success(
				[
					'optimized'    => ! empty( $optimized ),
					'start_time'   => $start_time,
					'current_time' => current_time( 'mysql' ),
				]
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				[
					'message' => __( 'Error checking database optimization state.', 'aspirecloud' ),
					'error'   => $e->getMessage(),
				]
			);
		}
	}

	/**
	 * Abstract method to be implemented by child classes for specific asset handling.
	 */
	abstract public function add_admin_menu();

	/**
	 * Abstract method to be implemented by child classes for specific script enqueuing.
	 */
	abstract public function enqueue_scripts( $hook );

	/**
	 * Abstract method to be implemented by child classes for rendering admin page.
	 */
	abstract public function render_admin_page();

	/**
	 * Abstract method to get total assets count from API.
	 */
	abstract protected function get_total_assets_count();

	/**
	 * Abstract method to get assets for a specific page from API.
	 */
	abstract protected function get_assets_page( $page, $per_page );

	/**
	 * Abstract method to get detailed asset information from API.
	 */
	abstract protected function get_detailed_asset_info( $slug );

	/**
	 * Abstract method to create asset info object.
	 */
	abstract protected function create_asset_info();

	/**
	 * Abstract method to load asset metadata from post.
	 */
	abstract protected function load_asset_metadata( $asset_info, $post_id );

	/**
	 * Abstract method to download asset files.
	 */
	abstract protected function download_asset_files( $asset_info, $post_id, $slug );

	/**
	 * Abstract method to get asset by slug.
	 */
	abstract protected function get_asset_by_slug( $slug );
}
