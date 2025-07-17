<?php
/**
 * Headless WordPress Controller.
 *
 * @package aspire-cloud
 * @author  AspirePress
 */

namespace AspireCloud\Controller;

/**
 * Class Headless
 *
 * Handles headless WordPress functionality.
 */
class Headless {

	/**
	 * Initialize the headless functionality.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'disable_frontend' ] );
		add_action( 'wp_loaded', [ $this, 'redirect_frontend_to_admin' ] );
		add_filter( 'show_admin_bar', '__return_false' );
		add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_frontend_scripts' ], 100 );
		add_action( 'wp_print_styles', [ $this, 'dequeue_frontend_styles' ], 100 );
		add_filter( 'template_include', [ $this, 'disable_theme_templates' ] );
	}

	/**
	 * Disable frontend functionality.
	 */
	public function disable_frontend() {
		// Remove feed links
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'feed_links', 2 );

		// Remove unnecessary head elements
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'start_post_rel_link' );
		remove_action( 'wp_head', 'index_rel_link' );
		remove_action( 'wp_head', 'adjacent_posts_rel_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );

		// Disable XML-RPC
		add_filter( 'xmlrpc_enabled', '__return_false' );

		// Remove emoji scripts
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Redirect frontend requests to admin or API.
	 */
	public function redirect_frontend_to_admin() {
		// Don't redirect if we're already in admin, doing AJAX, or accessing REST API
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// Don't redirect if this is an API passthrough request
		if ( get_query_var( 'aspire_api_path' ) ) {
			return;
		}

		// Don't redirect login, cron, or other WordPress core functionality
		$current_url     = $_SERVER['REQUEST_URI'] ?? '';
		$wordpress_paths = [
			'/wp-login.php',
			'/wp-cron.php',
			'/xmlrpc.php',
			'/wp-admin/',
			'/wp-content/',
			'/wp-includes/',
			'/wp-json/',
		];

		foreach ( $wordpress_paths as $path ) {
			if ( strpos( $current_url, $path ) !== false ) {
				return;
			}
		}

		// For any path that should be handled by the API passthrough, don't redirect
		// Let the Passthrough controller handle all other requests
	}

	/**
	 * Dequeue frontend scripts.
	 */
	public function dequeue_frontend_scripts() {
		// Remove jQuery and other scripts that aren't needed for headless
		wp_dequeue_script( 'jquery' );
		wp_dequeue_script( 'wp-embed' );
	}

	/**
	 * Dequeue frontend styles.
	 */
	public function dequeue_frontend_styles() {
		// Remove theme styles
		global $wp_styles;
		if ( isset( $wp_styles->queue ) ) {
			foreach ( $wp_styles->queue as $style ) {
				wp_dequeue_style( $style );
			}
		}
	}

	/**
	 * Disable theme templates for headless operation.
	 *
	 * @param string $template The template path.
	 * @return string Empty string to prevent template loading.
	 */
	public function disable_theme_templates( $template ) {
		// Don't override template if this is an API passthrough request
		if ( get_query_var( 'aspire_api_path' ) ) {
			return $template;
		}

		// Don't override for WordPress core paths
		$current_url     = $_SERVER['REQUEST_URI'] ?? '';
		$wordpress_paths = [
			'/wp-login.php',
			'/wp-cron.php',
			'/xmlrpc.php',
			'/wp-admin/',
			'/wp-content/',
			'/wp-includes/',
			'/wp-json/',
		];

		foreach ( $wordpress_paths as $path ) {
			if ( strpos( $current_url, $path ) !== false ) {
				return $template;
			}
		}

		// For frontend requests that should show our headless template
		// Return our custom headless template
		return AC_PATH . '/includes/views/index.php';
	}
}
