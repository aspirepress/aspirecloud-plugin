<?php
/**
 * Plugins Custom Post Type Class.
 *
 * @package aspire-cloud
 * @author  AspirePress
 */

namespace AspireCloud\Model;

/**
 * Class Plugins
 *
 * Handles the creation and management of the plugins custom post type
 * with all PluginInfo properties as custom fields.
 */
class Plugins {

	/**
	 * Post type name.
	 */
	const POST_TYPE = 'ac_plugin';

	/**
	 * Initialize the plugins custom post type.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_plugin_meta' ] );
	}

	/**
	 * Get all plugin properties from PluginInfo class.
	 *
	 * @return array List of all plugin properties.
	 */
	private function get_plugin_properties() {
		return PluginInfo::get_all_properties();
	}

	/**
	 * Register the plugins custom post type.
	 */
	public function register_post_type() {
		$labels = [
			'name'                  => __( 'Plugins', 'aspirecloud' ),
			'singular_name'         => __( 'Plugin', 'aspirecloud' ),
			'menu_name'             => __( 'Plugins', 'aspirecloud' ),
			'name_admin_bar'        => __( 'Plugin', 'aspirecloud' ),
			'archives'              => __( 'Plugin Archives', 'aspirecloud' ),
			'attributes'            => __( 'Plugin Attributes', 'aspirecloud' ),
			'parent_item_colon'     => __( 'Parent Plugin:', 'aspirecloud' ),
			'all_items'             => __( 'All Plugins', 'aspirecloud' ),
			'add_new_item'          => __( 'Add New Plugin', 'aspirecloud' ),
			'add_new'               => __( 'Add New', 'aspirecloud' ),
			'new_item'              => __( 'New Plugin', 'aspirecloud' ),
			'edit_item'             => __( 'Edit Plugin', 'aspirecloud' ),
			'update_item'           => __( 'Update Plugin', 'aspirecloud' ),
			'view_item'             => __( 'View Plugin', 'aspirecloud' ),
			'view_items'            => __( 'View Plugins', 'aspirecloud' ),
			'search_items'          => __( 'Search Plugin', 'aspirecloud' ),
			'not_found'             => __( 'Not found', 'aspirecloud' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'aspirecloud' ),
			'featured_image'        => __( 'Featured Image', 'aspirecloud' ),
			'set_featured_image'    => __( 'Set featured image', 'aspirecloud' ),
			'remove_featured_image' => __( 'Remove featured image', 'aspirecloud' ),
			'use_featured_image'    => __( 'Use as featured image', 'aspirecloud' ),
			'insert_into_item'      => __( 'Insert into plugin', 'aspirecloud' ),
			'uploaded_to_this_item' => __( 'Uploaded to this plugin', 'aspirecloud' ),
			'items_list'            => __( 'Plugins list', 'aspirecloud' ),
			'items_list_navigation' => __( 'Plugins list navigation', 'aspirecloud' ),
			'filter_items_list'     => __( 'Filter plugins list', 'aspirecloud' ),
		];

		$args = [
			'label'               => __( 'Plugin', 'aspirecloud' ),
			'description'         => __( 'WordPress Plugin Repository', 'aspirecloud' ),
			'labels'              => $labels,
			'supports'            => [ 'title' ],
			'taxonomies'          => [],
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-admin-plugins',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => false,
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Add meta boxes for plugin properties.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'plugin_details',
			__( 'Plugin Details', 'aspirecloud' ),
			[ $this, 'render_plugin_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the plugin meta box.
	 *
	 * @param \WP_Post $post The post object.
	 */
	public function render_plugin_meta_box( $post ) {
		$properties      = $this->get_plugin_properties();
		$plugin_instance = $this;

		// Include the view file using Utilities
		Utilities::include_file( 'plugins-meta.php', compact( 'post', 'properties', 'plugin_instance' ) );
	}

	/**
	 * Save plugin meta data.
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_plugin_meta( $post_id ) {
		// Verify nonce
		if ( ! isset( $_POST['plugin_meta_nonce'] ) || ! wp_verify_nonce( $_POST['plugin_meta_nonce'], 'save_plugin_meta' ) ) {
			return;
		}

		// Check if user has permission
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if this is the correct post type
		if ( get_post_type( $post_id ) !== self::POST_TYPE ) {
			return;
		}

		// Save each plugin property
		foreach ( $this->get_plugin_properties() as $property ) {
			$meta_key = '__' . $property;

			if ( isset( $_POST[ $meta_key ] ) ) {
				$value = $_POST[ $meta_key ];

				// Handle array fields (decode JSON)
				if ( in_array( $property, [ 'sections', 'tags', 'versions', 'banners', 'ratings', 'icons', 'requires_plugins' ], true ) ) {
					$decoded = json_decode( $value, true );
					if ( JSON_ERROR_NONE === json_last_error() ) {
						$value = $decoded;
					}
				}

				// Sanitize and save
				$sanitized_value = $this->sanitize_plugin_meta( $property, $value );
				update_post_meta( $post_id, $meta_key, $sanitized_value );
			}
		}
	}

	/**
	 * Sanitize plugin meta value based on property type.
	 * Uses AssetInfo class sanitization logic instead of hardcoding.
	 *
	 * @param string $property The property name.
	 * @param mixed  $value    The value to sanitize.
	 * @return mixed The sanitized value.
	 */
	private function sanitize_plugin_meta( $property, $value ) {
		// Create a temporary AssetInfo instance to leverage its sanitization
		$temp_asset = new \AspireCloud\Model\AssetInfo();

		// Use the AssetInfo __set method which handles all sanitization
		$temp_asset->__set( $property, $value );

		// Return the sanitized value using __get method
		return $temp_asset->__get( $property );
	}

	/**
	 * Create a plugin post from PluginInfo object.
	 *
	 * @param \AspireCloud\Model\PluginInfo $plugin_info The plugin info object.
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	public function create_plugin_post( $plugin_info ) {
		$post_data = [
			'post_title'   => $plugin_info->get_name(),
			'post_content' => $plugin_info->get_description(),
			'post_status'  => 'publish',
			'post_type'    => self::POST_TYPE,
		];

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save all plugin properties as meta fields
		foreach ( $this->get_plugin_properties() as $property ) {
			$meta_key = '__' . $property;
			$value    = $plugin_info->__get( $property );

			if ( null !== $value ) {
				$sanitized_value = $this->sanitize_plugin_meta( $property, $value );
				update_post_meta( $post_id, $meta_key, $sanitized_value );
			}
		}

		return $post_id;
	}

	/**
	 * Update an existing plugin post from PluginInfo object.
	 *
	 * @param int                           $post_id     The post ID to update.
	 * @param \AspireCloud\Model\PluginInfo $plugin_info The plugin info object.
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	public function update_plugin_post( $post_id, $plugin_info ) {
		$post_data = [
			'ID'           => $post_id,
			'post_title'   => $plugin_info->get_name(),
			'post_content' => $plugin_info->get_description(),
		];

		$result = wp_update_post( $post_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update all plugin properties as meta fields
		foreach ( $this->get_plugin_properties() as $property ) {
			$meta_key = '__' . $property;
			$value    = $plugin_info->__get( $property );

			if ( null !== $value ) {
				$sanitized_value = $this->sanitize_plugin_meta( $property, $value );
				update_post_meta( $post_id, $meta_key, $sanitized_value );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}

		return $post_id;
	}

	/**
	 * Get plugin by slug.
	 *
	 * @param string $slug The plugin slug.
	 * @return \WP_Post|null The post object or null if not found.
	 */
	public function get_plugin_by_slug( $slug ) {
		$posts = get_posts(
			[
				'post_type'   => self::POST_TYPE,
				'meta_query'  => [
					[
						'key'   => '__slug',
						'value' => $slug,
					],
				],
				'numberposts' => 1,
			]
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}
}
