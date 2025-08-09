<?php
/**
 * Themes Custom Post Type Class.
 *
 * @package aspire-cloud
 * @author  AspirePress
 */

namespace AspireCloud\Model;

/**
 * Class Themes
 *
 * Handles the creation and management of the themes custom post type
 * with all ThemeInfo properties as custom fields.
 */
class Themes {

	/**
	 * Post type name.
	 */
	const POST_TYPE = 'ac_theme';

	/**
	 * Initialize the themes custom post type.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_theme_meta' ] );
	}

	/**
	 * Get all theme properties from ThemeInfo class.
	 *
	 * @return array List of all theme properties.
	 */
	private function get_theme_properties() {
		return ThemeInfo::get_all_properties();
	}

	/**
	 * Register the themes custom post type.
	 */
	public function register_post_type() {
		$labels = [
			'name'                  => __( 'Themes', 'aspirecloud' ),
			'singular_name'         => __( 'Theme', 'aspirecloud' ),
			'menu_name'             => __( 'Themes', 'aspirecloud' ),
			'name_admin_bar'        => __( 'Theme', 'aspirecloud' ),
			'archives'              => __( 'Theme Archives', 'aspirecloud' ),
			'attributes'            => __( 'Theme Attributes', 'aspirecloud' ),
			'parent_item_colon'     => __( 'Parent Theme:', 'aspirecloud' ),
			'all_items'             => __( 'All Themes', 'aspirecloud' ),
			'add_new_item'          => __( 'Add New Theme', 'aspirecloud' ),
			'add_new'               => __( 'Add New', 'aspirecloud' ),
			'new_item'              => __( 'New Theme', 'aspirecloud' ),
			'edit_item'             => __( 'Edit Theme', 'aspirecloud' ),
			'update_item'           => __( 'Update Theme', 'aspirecloud' ),
			'view_item'             => __( 'View Theme', 'aspirecloud' ),
			'view_items'            => __( 'View Themes', 'aspirecloud' ),
			'search_items'          => __( 'Search Theme', 'aspirecloud' ),
			'not_found'             => __( 'Not found', 'aspirecloud' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'aspirecloud' ),
			'featured_image'        => __( 'Featured Image', 'aspirecloud' ),
			'set_featured_image'    => __( 'Set featured image', 'aspirecloud' ),
			'remove_featured_image' => __( 'Remove featured image', 'aspirecloud' ),
			'use_featured_image'    => __( 'Use as featured image', 'aspirecloud' ),
			'insert_into_item'      => __( 'Insert into theme', 'aspirecloud' ),
			'uploaded_to_this_item' => __( 'Uploaded to this theme', 'aspirecloud' ),
			'items_list'            => __( 'Themes list', 'aspirecloud' ),
			'items_list_navigation' => __( 'Themes list navigation', 'aspirecloud' ),
			'filter_items_list'     => __( 'Filter themes list', 'aspirecloud' ),
		];

		$args = [
			'label'               => __( 'Theme', 'aspirecloud' ),
			'description'         => __( 'WordPress Theme Repository', 'aspirecloud' ),
			'labels'              => $labels,
			'supports'            => [ 'title' ],
			'taxonomies'          => [],
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-admin-appearance',
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
	 * Add meta boxes for theme properties.
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'theme_details',
			__( 'Theme Details', 'aspirecloud' ),
			[ $this, 'render_theme_meta_box' ],
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the theme meta box.
	 *
	 * @param \WP_Post $post The post object.
	 */
	/**
	 * Render the theme meta box.
	 *
	 * @param \WP_Post $post The post object.
	 */
	public function render_theme_meta_box( $post ) {
		$properties     = $this->get_theme_properties();
		$theme_instance = $this;

		// Include the view file using Utilities
		Utilities::include_file( 'themes-meta.php', compact( 'post', 'properties', 'theme_instance' ) );
	}

	/**
	 * Save theme meta data.
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_theme_meta( $post_id ) {
		// Verify nonce
		if ( ! isset( $_POST['theme_meta_nonce'] ) || ! wp_verify_nonce( $_POST['theme_meta_nonce'], 'save_theme_meta' ) ) {
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

		// Save each theme property
		foreach ( $this->get_theme_properties() as $property ) {
			$meta_key = '__' . $property;

			if ( isset( $_POST[ $meta_key ] ) ) {
				$value = $_POST[ $meta_key ];

				// Handle array fields (decode JSON)
				if ( in_array( $property, [ 'sections', 'tags', 'versions', 'banners', 'ratings' ], true ) ) {
					$decoded = json_decode( $value, true );
					if ( JSON_ERROR_NONE === json_last_error() ) {
						$value = $decoded;
					}
				}

				// Sanitize and save
				$sanitized_value = $this->sanitize_theme_meta( $property, $value );
				update_post_meta( $post_id, $meta_key, $sanitized_value );
			}
		}
	}

	/**
	 * Sanitize theme meta value based on property type.
	 * Uses AssetInfo class sanitization logic instead of hardcoding.
	 *
	 * @param string $property The property name.
	 * @param mixed  $value    The value to sanitize.
	 * @return mixed The sanitized value.
	 */
	private function sanitize_theme_meta( $property, $value ) {
		// Create a temporary AssetInfo instance to leverage its sanitization
		$temp_asset = new \AspireCloud\Model\AssetInfo();

		// Use the AssetInfo __set method which handles all sanitization
		$temp_asset->__set( $property, $value );

		// Return the sanitized value using __get method
		return $temp_asset->__get( $property );
	}

	/**
	 * Create a theme post from ThemeInfo object.
	 *
	 * @param \AspireCloud\Model\ThemeInfo $theme_info The theme info object.
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	public function create_theme_post( $theme_info ) {
		$post_data = [
			'post_title'   => $theme_info->get_name(),
			'post_content' => $theme_info->get_description(),
			'post_status'  => 'publish',
			'post_type'    => self::POST_TYPE,
		];

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save all theme properties as meta fields
		foreach ( $this->get_theme_properties() as $property ) {
			$meta_key = '__' . $property;
			$value    = $theme_info->__get( $property );

			if ( null !== $value ) {
				$sanitized_value = $this->sanitize_theme_meta( $property, $value );
				update_post_meta( $post_id, $meta_key, $sanitized_value );
			}
		}

		return $post_id;
	}

	/**
	 * Update an existing theme post from ThemeInfo object.
	 *
	 * @param int                          $post_id    The post ID to update.
	 * @param \AspireCloud\Model\ThemeInfo $theme_info The theme info object.
	 * @return int|WP_Error The post ID or WP_Error on failure.
	 */
	public function update_theme_post( $post_id, $theme_info ) {
		$post_data = [
			'ID'           => $post_id,
			'post_title'   => $theme_info->get_name(),
			'post_content' => $theme_info->get_description(),
		];

		$result = wp_update_post( $post_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update all theme properties as meta fields
		foreach ( $this->get_theme_properties() as $property ) {
			$meta_key = '__' . $property;
			$value    = $theme_info->__get( $property );

			if ( null !== $value ) {
				$sanitized_value = $this->sanitize_theme_meta( $property, $value );
				update_post_meta( $post_id, $meta_key, $sanitized_value );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}

		return $post_id;
	}

	/**
	 * Get theme by slug.
	 *
	 * @param string $slug The theme slug.
	 * @return \WP_Post|null The post object or null if not found.
	 */
	public function get_theme_by_slug( $slug ) {
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
