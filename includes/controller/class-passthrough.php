<?php
/**
 * Passthrough API Controller.
 *
 * @package aspire-cloud
 * @author  AspirePress
 */

namespace AspireCloud\Controller;

/**
 * Class Passthrough
 *
 * Creates a REST API endpoint that passes through GET requests to the source API endpoint.
 */
class Passthrough {

	/**
	 * Initialize the passthrough functionality.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_action( 'wp', [ $this, 'handle_root_api_request' ], 1 );
		add_action( 'parse_request', [ $this, 'early_request_handler' ] );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'',
			'/api/(?P<path>.*)',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'passthrough_request' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'path' => [
						'description' => 'The API path to passthrough to the source API endpoint',
						'type'        => 'string',
						'required'    => false,
						'default'     => '',
					],
				],
			]
		);
	}

	/**
	 * Add rewrite rules for root API endpoints.
	 */
	public function add_rewrite_rules() {
		// Add specific rewrite rule for secret-key API
		add_rewrite_rule( '^secret-key/(.+)/?$', 'index.php?aspire_api_path=secret-key/$matches[1]', 'top' );

		// Add general rewrite rule to catch all other requests
		add_rewrite_rule( '^([^/]+(?:/[^/]*)*)/?$', 'index.php?aspire_api_path=$matches[1]', 'top' );

		add_rewrite_tag( '%aspire_api_path%', '([^&]+)' );
	}

	/**
	 * Handle requests before WordPress processes them.
	 *
	 * @param WP $wp The WordPress environment.
	 */
	public function early_request_handler( $wp ) {
		// Get the request path
		$request_path = $wp->request;

		// If we have a request path and it's not a WordPress path, handle it
		if ( ! empty( $request_path ) && ! $this->is_wordpress_path( $request_path ) ) {
			$this->process_api_request( $request_path );
		}
	}

	/**
	 * Handle API requests at the root level.
	 */
	public function handle_root_api_request() {
		$api_path = get_query_var( 'aspire_api_path' );

		// Skip WordPress admin, wp-content, wp-includes, and other WordPress paths
		if ( $this->is_wordpress_path( $api_path ) ) {
			return;
		}

		// If we have a path, handle it as an API request
		if ( ! empty( $api_path ) ) {
			$this->process_api_request( $api_path );
		}
	}

	/**
	 * Check if the path is a WordPress internal path that should not be intercepted.
	 *
	 * @param string $path The path to check.
	 * @return bool True if it's a WordPress path.
	 */
	private function is_wordpress_path( $path ) {
		$wordpress_paths = [
			'wp-admin',
			'wp-content',
			'wp-includes',
			'wp-json',
			'wp-login.php',
			'wp-cron.php',
			'xmlrpc.php',
			'favicon.ico',
			'robots.txt',
			'sitemap.xml',
		];

		foreach ( $wordpress_paths as $wp_path ) {
			if ( strpos( $path, $wp_path ) === 0 ) {
				return true;
			}
		}

		// Check for file extensions that should be served normally
		$file_extensions = [ '.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot' ];
		foreach ( $file_extensions as $ext ) {
			if ( substr( $path, -strlen( $ext ) ) === $ext ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Process the API request and return the source API endpoint's response.
	 *
	 * @param string $path The API path.
	 */
	private function process_api_request( $path ) {
		// Build the API URL
		$api_url = AC_SOURCE_API_ENDPOINT . '/' . ltrim( $path, '/' );

		// Add query parameters if they exist
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET ) ) {
			// Remove WordPress query vars
			$query_params = $_GET;
			unset( $query_params['aspire_api_path'] );

			if ( ! empty( $query_params ) ) {
				$api_url .= '?' . http_build_query( $query_params );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Make the request to the source API endpoint
		$response = wp_remote_get(
			$api_url,
			[
				'timeout' => 30,
				'headers' => [
					'User-Agent' => 'AspireCloud/1.0',
				],
			]
		);

		// Handle errors
		if ( is_wp_error( $response ) ) {
			wp_die( 'Failed to fetch data from the source API endpoint: ' . esc_html( $response->get_error_message() ), 'API Error', [ 'response' => 500 ] );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Set appropriate headers
		$allowed_headers = [
			'content-type',
			'cache-control',
			'expires',
			'last-modified',
			'etag',
		];

		foreach ( $allowed_headers as $header ) {
			if ( isset( $headers[ $header ] ) ) {
				header( $header . ': ' . $headers[ $header ] );
			}
		}

		// Set status code
		http_response_code( $status_code );

		// Output the response and exit
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $body;
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Handle the passthrough request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error The response or error.
	 */
	public function passthrough_request( $request ) {
		$path         = $request->get_param( 'path' );
		$query_params = $request->get_query_params();

		// Remove the 'path' parameter from query params since it's not part of the actual query
		unset( $query_params['path'] );

		// Build the API URL
		$api_url = AC_SOURCE_API_ENDPOINT . '/' . ltrim( $path, '/' );

		// Add query parameters if they exist
		if ( ! empty( $query_params ) ) {
			$api_url .= '?' . http_build_query( $query_params );
		}

		// Make the request to the source API endpoint
		$response = wp_remote_get(
			$api_url,
			[
				'timeout' => 30,
				'headers' => [
					'User-Agent' => 'AspireCloud/1.0',
				],
			]
		);

		// Handle errors
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'passthrough_error',
				'Failed to fetch data from the source API endpoint: ' . $response->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Create REST response
		$rest_response = new \WP_REST_Response( $body, $status_code );

		// Pass through relevant headers
		$allowed_headers = [
			'content-type',
			'cache-control',
			'expires',
			'last-modified',
			'etag',
		];

		foreach ( $allowed_headers as $header ) {
			if ( isset( $headers[ $header ] ) ) {
				$rest_response->header( $header, $headers[ $header ] );
			}
		}

		return $rest_response;
	}
}
