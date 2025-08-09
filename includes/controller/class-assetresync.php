<?php
/**
 * Asset Sync Controller Class.
 *
 * @package aspirecloud
 * @author  AspirePress
 */

namespace AspireCloud\Controller;

use AspireCloud\Model\Plugins;
use AspireCloud\Model\Themes;
use AspireCloud\Model\PluginInfo;
use AspireCloud\Model\ThemeInfo;
use AspireCloud\Controller\PluginImport;
use AspireCloud\Controller\ThemeImport;

/**
 * Class AssetResync
 *
 * Handles sync functionality for plugins and themes post types.
 * Adds metaboxes with sync buttons and handles API synchronization.
 */
class AssetResync {

	/**
	 * Initialize the asset sync controller.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'add_resync_metabox' ] );
		add_action( 'wp_ajax_resync_plugin', [ $this, 'ajax_resync_plugin' ] );
		add_action( 'wp_ajax_resync_theme', [ $this, 'ajax_resync_theme' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Add sync metabox to plugin and theme post types.
	 * Only shows if the slug meta field is available and non-empty.
	 */
	public function add_resync_metabox() {
		global $post;

		// Check if this is a valid post and has the required post type
		if ( ! $post || ! in_array( $post->post_type, [ 'ac_plugin', 'ac_theme' ], true ) ) {
			return;
		}

		// Get the slug meta field
		$slug = get_post_meta( $post->ID, '__slug', true );

		// If slug is empty, use post_name as fallback
		if ( empty( $slug ) ) {
			$slug = $post->post_name;
		}

		// Only add metabox if slug is available and non-empty
		if ( empty( $slug ) ) {
			return;
		}

		if ( 'ac_plugin' === $post->post_type ) {
			// Add metabox for plugins
			add_meta_box(
				'aspirecloud_sync_plugin',
				__( 'Sync Plugin', 'aspirecloud' ),
				[ $this, 'render_resync_metabox' ],
				'ac_plugin',
				'side',
				'high'
			);
		} elseif ( 'ac_theme' === $post->post_type ) {
			// Add metabox for themes
			add_meta_box(
				'aspirecloud_sync_theme',
				__( 'Sync Theme', 'aspirecloud' ),
				[ $this, 'render_resync_metabox' ],
				'ac_theme',
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the sync metabox.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_resync_metabox( $post ) {
		// Get current version
		$current_version = get_post_meta( $post->ID, '__version', true );
		$slug            = get_post_meta( $post->ID, '__slug', true );

		if ( empty( $slug ) ) {
			$slug = $post->post_name;
		}

		// Determine asset type
		$asset_type  = 'ac_plugin' === $post->post_type ? 'plugin' : 'theme';
		$asset_label = 'plugin' === $asset_type ? __( 'Plugin', 'aspirecloud' ) : __( 'Theme', 'aspirecloud' );

		wp_nonce_field( 'aspirecloud_resync_' . $asset_type, 'aspirecloud_resync_nonce' );
		?>
		<div id="aspirecloud-resync-container">
			<div class="aspirecloud-resync-info">
				<p>
					<strong><?php echo esc_html( $asset_label ); ?>:</strong> <?php echo esc_html( $slug ); ?><br>
					<strong><?php esc_html_e( 'Current Version:', 'aspirecloud' ); ?></strong>
					<span id="current-version"><?php echo esc_html( $current_version ? $current_version : __( 'Unknown', 'aspirecloud' ) ); ?></span>
				</p>
			</div>

			<div class="aspirecloud-resync-actions">
				<button type="button"
						id="resync-<?php echo esc_attr( $asset_type ); ?>-btn"
						class="button button-secondary"
						data-post-id="<?php echo esc_attr( $post->ID ); ?>"
						data-slug="<?php echo esc_attr( $slug ); ?>"
						data-asset-type="<?php echo esc_attr( $asset_type ); ?>">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Sync', 'aspirecloud' ); ?>
				</button>
			</div>

			<div id="resync-status" class="aspirecloud-resync-status" style="display: none;">
				<p class="resync-message"></p>
			</div>

			<div id="resync-progress" class="aspirecloud-resync-progress" style="display: none;">
				<div class="progress-bar">
					<div class="progress-fill" style="width: 0%;"></div>
				</div>
				<p class="progress-text"></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts for sync functionality.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on post edit screens for our post types
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		global $post;
		if ( ! $post || ! in_array( $post->post_type, [ 'ac_plugin', 'ac_theme' ], true ) ) {
			return;
		}

		// Enqueue admin CSS for sync styles
		wp_enqueue_style(
			'aspirecloud-admin',
			AC_URL . 'assets/css/admin.css',
			[],
			AC_VERSION
		);

		wp_enqueue_script(
			'aspirecloud-resync',
			AC_URL . 'assets/js/resync.js',
			[ 'jquery' ],
			AC_VERSION,
			true
		);

		wp_localize_script(
			'aspirecloud-resync',
			'aspirecloud_resync',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aspirecloud_resync' ),
				'strings'  => [
					'checking_version'  => __( 'Checking for updates...', 'aspirecloud' ),
					'no_update'         => __( 'No update available. Current version is up to date.', 'aspirecloud' ),
					// translators: %1$s is the current version, %2$s is the new version
					'update_available'  => __( 'Update available! Updating from version %1$s to %2$s...', 'aspirecloud' ),
					'updating_data'     => __( 'Updating metadata...', 'aspirecloud' ),
					'downloading_files' => __( 'Downloading files...', 'aspirecloud' ),
					'update_complete'   => __( 'Sync completed successfully!', 'aspirecloud' ),
					// translators: %s is the error message
					'update_error'      => __( 'Sync failed: %s', 'aspirecloud' ),
					'network_error'     => __( 'Network error occurred. Please try again.', 'aspirecloud' ),
					// translators: %s is the new version number
					'confirm_update'    => __( 'A newer version (%s) is available. Do you want to update?', 'aspirecloud' ),
					'refresh_message'   => __( 'Please refresh the page to view the updated data.', 'aspirecloud' ),
					'reload_link_text'  => __( 'Reload Page', 'aspirecloud' ),
				],
			]
		);
	}

	/**
	 * AJAX handler for plugin sync.
	 */
	public function ajax_resync_plugin() {
		$this->ajax_resync_asset( 'plugin' );
	}

	/**
	 * AJAX handler for theme sync.
	 */
	public function ajax_resync_theme() {
		$this->ajax_resync_asset( 'theme' );
	}

	/**
	 * Common AJAX handler for asset sync.
	 *
	 * @param string $asset_type Asset type (plugin or theme).
	 */
	private function ajax_resync_asset( $asset_type ) {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'aspirecloud_resync' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'aspirecloud' ) );
		}

		// Check user capabilities
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'aspirecloud' ) );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );
		$slug    = sanitize_text_field( $_POST['slug'] ?? '' );

		if ( ! $post_id || ! $slug ) {
			wp_send_json_error( __( 'Invalid post ID or slug.', 'aspirecloud' ) );
		}

		// Get current version
		$current_version = get_post_meta( $post_id, '__version', true );

		try {
			// Fetch latest data from API
			$api_data = $this->fetch_asset_data( $slug, $asset_type );

			if ( ! $api_data ) {
				wp_send_json_error( __( 'Failed to fetch data from API.', 'aspirecloud' ) );
			}

			$latest_version = $api_data['version'] ?? '';

			// Compare versions
			if ( empty( $latest_version ) ) {
				wp_send_json_error( __( 'No version information available from API.', 'aspirecloud' ) );
			}

			// If versions are the same, no update needed
			if ( version_compare( $latest_version, $current_version, '<=' ) ) {
				wp_send_json_success(
					[
						'action'          => 'no_update',
						'current_version' => $current_version,
						'latest_version'  => $latest_version,
						'message'         => __( 'No update available. Current version is up to date.', 'aspirecloud' ),
					]
				);
			}

			// Update available, proceed with update
			$this->update_asset_data( $post_id, $api_data, $asset_type );
			$this->redownload_asset_files( $post_id, $slug, $api_data, $asset_type );

			wp_send_json_success(
				[
					'action'          => 'updated',
					'current_version' => $current_version,
					'latest_version'  => $latest_version,
					'message'         => sprintf(
						// translators: %1$s is the current version, %2$s is the new version
						__( 'Successfully synced from version %1$s to %2$s.', 'aspirecloud' ),
						$current_version,
						$latest_version
					),
				]
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Fetch asset data from WordPress.org API.
	 *
	 * @param string $slug Asset slug.
	 * @param string $asset_type Asset type (plugin or theme).
	 * @return array|false Asset data or false on failure.
	 */
	private function fetch_asset_data( $slug, $asset_type ) {
		if ( $asset_type === 'plugin' ) {
			return $this->fetch_plugin_data( $slug );
		} else {
			return $this->fetch_theme_data( $slug );
		}
	}

	/**
	 * Fetch plugin data from WordPress.org API.
	 *
	 * @param string $slug Plugin slug.
	 * @return array|false Plugin data or false on failure.
	 */
	public function fetch_plugin_data( $slug ) {
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
			'plugin_information',
			[
				'slug'   => $slug,
				'fields' => $fields,
			]
		);

		if ( is_wp_error( $api_response ) ) {
			throw new \Exception( $api_response->get_error_message() );
		}

		// Convert object to array
		return (array) $api_response;
	}

	/**
	 * Fetch theme data from WordPress.org API.
	 *
	 * @param string $slug Theme slug.
	 * @return array|false Theme data or false on failure.
	 */
	public function fetch_theme_data( $slug ) {
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
			'theme_information',
			[
				'slug'   => $slug,
				'fields' => $fields,
			]
		);

		if ( is_wp_error( $api_response ) ) {
			throw new \Exception( $api_response->get_error_message() );
		}

		// Convert object to array
		return (array) $api_response;
	}

	/**
	 * Update asset data in the database.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $api_data API data.
	 * @param string $asset_type Asset type (plugin or theme).
	 */
	public function update_asset_data( $post_id, $api_data, $asset_type ) {
		// Create appropriate asset info object with API data
		if ( $asset_type === 'plugin' ) {
			$asset_info = new PluginInfo( $api_data );
		} else {
			$asset_info = new ThemeInfo( $api_data );
		}

		// Update post title and content
		wp_update_post(
			[
				'ID'           => $post_id,
				'post_title'   => $asset_info->name,
				'post_content' => $asset_info->description,
				'post_excerpt' => $asset_info->short_description ?? '',
			]
		);

		// Update all metadata
		$properties = $asset_info::get_all_properties();
		foreach ( $properties as $property ) {
			$value = $asset_info->__get( $property );
			if ( $value !== null ) {
				// Encode arrays and objects as JSON
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = wp_json_encode( $value );
				}
				update_post_meta( $post_id, '__' . $property, $value );
			}
		}
	}

	/**
	 * Redownload asset files.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $slug Asset slug.
	 * @param array  $api_data API data.
	 * @param string $asset_type Asset type (plugin or theme).
	 */
	public function redownload_asset_files( $post_id, $slug, $api_data, $asset_type ) {
		// Get the appropriate importer instance
		if ( $asset_type === 'plugin' ) {
			$importer = new PluginImport();
		} else {
			$importer = new ThemeImport();
		}

		// Create asset info object with API data
		if ( $asset_type === 'plugin' ) {
			$asset_info = new PluginInfo( $api_data );
		} else {
			$asset_info = new ThemeInfo( $api_data );
		}

		// Download asset files directly (now public method)
		try {
			$importer->download_asset_files( $asset_info, $post_id, $slug );
		} catch ( \Exception $e ) {
			throw new \Exception( sprintf( __( 'Failed to download files: %s', 'aspirecloud' ), $e->getMessage() ) );
		}
	}
}
