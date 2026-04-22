<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\OAuth;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Mcp_Rest_Controller;
use WP_Error;
use WP_REST_Request;
use WP_User;

/**
 * Restrict MCP bearer-authenticated requests to Writer-required REST routes.
 *
 * Keeps native authentication (Basic/cookies/application passwords) untouched.
 * The allowlist is only applied when the request is authenticated by a valid
 * MCP bearer token.
 *
 * @since 7.2.3
 */
class Rest_Bearer_Route_Authorizer {

	/**
	 * Token manager.
	 *
	 * @since 7.2.3
	 * @var Token_Manager
	 */
	private Token_Manager $token_manager;

	/**
	 * Cached post-type route definitions for the current request lifecycle.
	 *
	 * @since 7.2.3
	 * @var array<array<string>>|null
	 */
	private ?array $post_type_routes_cache = null;

	/**
	 * Cached taxonomy route definitions for the current request lifecycle.
	 *
	 * @since 7.2.3
	 * @var array<array<string>>|null
	 */
	private ?array $taxonomy_routes_cache = null;

	/**
	 * Constructor.
	 *
	 * @since 7.2.3
	 */
	public function __construct() {
		$this->token_manager = new Token_Manager();
	}

	/**
	 * Register REST authorization filter.
	 *
	 * @since 7.2.3
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter(
			'rest_request_before_callbacks',
			array( $this, 'enforce_bearer_route_access' ),
			20,
			3
		);
	}

	/**
	 * Enforce route-level allowlist for MCP bearer-authenticated requests.
	 *
	 * @since 7.2.3
	 *
	 * @param mixed           $response Existing response.
	 * @param mixed           $handler  Matched handler metadata.
	 * @param WP_REST_Request $request  REST request object.
	 *
	 * @return mixed
	 */
	public function enforce_bearer_route_access( $response, $handler, $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return $response;
		}

		// Keep MCP transport routes governed by their own permission callbacks.
		if ( $this->is_mcp_route( $request ) ) {
			return $response;
		}

		// Only enforce this allowlist when auth source is MCP bearer.
		$user = $this->resolve_bearer_user( $request );
		if ( ! $user instanceof WP_User ) {
			return $response;
		}

		$method = strtoupper( (string) $request->get_method() );
		$route  = $this->normalize_route( (string) $request->get_route() );

		if ( $this->is_allowed_writer_route( $route, $method ) ) {
			return $response;
		}

		return new WP_Error(
			'rest_forbidden',
			'MCP bearer token is not allowed to access this REST route.',
			array( 'status' => 403 )
		);
	}

	/**
	 * Resolve bearer-authenticated user for this request.
	 *
	 * @since 7.2.3
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_User|false
	 */
	private function resolve_bearer_user( WP_REST_Request $request ) {
		$token = $this->extract_bearer_token( $request );
		if ( empty( $token ) ) {
			return false;
		}

		$user = $this->token_manager->get_user_from_token( $token );
		if ( $user instanceof WP_User && user_can( $user, 'manage_options' ) ) {
			return $user;
		}

		return false;
	}

	/**
	 * Extract bearer token from Authorization or fallback creds header.
	 *
	 * @since 7.2.3
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return string|null
	 */
	private function extract_bearer_token( WP_REST_Request $request ): ?string {
		$auth_header = (string) $request->get_header( 'authorization' );
		$creds       = (string) $request->get_header( 'x-automator-creds' );

		if ( false === strpos( strtolower( $auth_header ), 'bearer' ) ) {
			$auth_header = $creds;
		}

		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			return trim( (string) $matches[1] );
		}

		return null;
	}

	/**
	 * Check if route is part of MCP transport.
	 *
	 * @since 7.2.3
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool
	 */
	private function is_mcp_route( WP_REST_Request $request ): bool {
		$route  = ltrim( strtolower( $this->normalize_route( (string) $request->get_route() ) ), '/' );
		$prefix = strtolower(
			trim(
				Mcp_Rest_Controller::ROUTE_NAMESPACE . '/' . Mcp_Rest_Controller::ROUTE_BASE,
				'/'
			)
		);

		return 0 === strpos( $route, $prefix );
	}

	/**
	 * Determine if route/method pair is allowed for Writer REST access.
	 *
	 * @since 7.2.3
	 * @since 7.2.3 Added PUT/PATCH support for writer update operations.
	 *
	 * @param string $route  Normalized REST route.
	 * @param string $method HTTP method.
	 *
	 * @return bool
	 */
	private function is_allowed_writer_route( string $route, string $method ): bool {
		if ( 'GET' === $method && in_array( $route, array( '/wp/v2/types', '/wp/v2/taxonomies' ), true ) ) {
			return true;
		}

		// Writer media listing + metadata update.
		if ( in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH' ), true ) && preg_match( '#^/wp/v2/media(?:/\d+)?$#', $route ) ) {
			return true;
		}

		// Writer post/page/CPT collection + item read/write.
		if ( $this->matches_collection_or_item_route( $route, $method, $this->get_post_type_routes() ) ) {
			return true;
		}

		// Writer taxonomy term lookup + creation.
		if ( $this->matches_collection_or_item_route( $route, $method, $this->get_taxonomy_routes() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Match collection/item route for route namespace + base combinations.
	 *
	 * @since 7.2.3
	 * @since 7.2.3 Added PUT/PATCH support for writer update operations.
	 *
	 * @param string               $route      Normalized route.
	 * @param string               $method     HTTP method.
	 * @param array<array<string>> $route_defs Route defs [namespace, rest_base].
	 *
	 * @return bool
	 */
	private function matches_collection_or_item_route( string $route, string $method, array $route_defs ): bool {
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH' ), true ) ) {
			return false;
		}

		foreach ( $route_defs as $route_def ) {
			$namespace = $route_def[0];
			$rest_base = $route_def[1];
			$prefix    = '/' . $namespace . '/' . $rest_base;

			if ( $route === $prefix ) {
				return true;
			}

			if ( 1 === preg_match( '#^' . preg_quote( $prefix, '#' ) . '/\d+$#', $route ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build REST route defs for post types exposed in REST.
	 *
	 * @since 7.2.3
	 * @since 7.2.3 Caches route definitions per request lifecycle.
	 *
	 * @return array<array<string>>
	 */
	private function get_post_type_routes(): array {
		if ( null !== $this->post_type_routes_cache ) {
			return $this->post_type_routes_cache;
		}

		$defs       = array();
		$post_types = get_post_types( array( 'show_in_rest' => true ), 'objects' );

		foreach ( $post_types as $post_type => $post_type_obj ) {
			$namespace = isset( $post_type_obj->rest_namespace ) && is_string( $post_type_obj->rest_namespace )
				? trim( $post_type_obj->rest_namespace, '/' )
				: 'wp/v2';
			$rest_base = isset( $post_type_obj->rest_base ) && is_string( $post_type_obj->rest_base ) && '' !== $post_type_obj->rest_base
				? trim( $post_type_obj->rest_base, '/' )
				: trim( (string) $post_type, '/' );

			if ( '' === $namespace || '' === $rest_base ) {
				continue;
			}

			$key          = strtolower( $namespace ) . '|' . strtolower( $rest_base );
			$defs[ $key ] = array( strtolower( $namespace ), strtolower( $rest_base ) );
		}

		$this->post_type_routes_cache = array_values( $defs );

		return $this->post_type_routes_cache;
	}

	/**
	 * Build REST route defs for taxonomies exposed in REST.
	 *
	 * @since 7.2.3
	 * @since 7.2.3 Caches route definitions per request lifecycle.
	 *
	 * @return array<array<string>>
	 */
	private function get_taxonomy_routes(): array {
		if ( null !== $this->taxonomy_routes_cache ) {
			return $this->taxonomy_routes_cache;
		}

		$defs       = array();
		$taxonomies = get_taxonomies( array( 'show_in_rest' => true ), 'objects' );

		foreach ( $taxonomies as $taxonomy => $taxonomy_obj ) {
			$namespace = isset( $taxonomy_obj->rest_namespace ) && is_string( $taxonomy_obj->rest_namespace )
				? trim( $taxonomy_obj->rest_namespace, '/' )
				: 'wp/v2';
			$rest_base = isset( $taxonomy_obj->rest_base ) && is_string( $taxonomy_obj->rest_base ) && '' !== $taxonomy_obj->rest_base
				? trim( $taxonomy_obj->rest_base, '/' )
				: trim( (string) $taxonomy, '/' );

			if ( '' === $namespace || '' === $rest_base ) {
				continue;
			}

			$key          = strtolower( $namespace ) . '|' . strtolower( $rest_base );
			$defs[ $key ] = array( strtolower( $namespace ), strtolower( $rest_base ) );
		}

		$this->taxonomy_routes_cache = array_values( $defs );

		return $this->taxonomy_routes_cache;
	}

	/**
	 * Normalize route to lowercase absolute form without trailing slash.
	 *
	 * @since 7.2.3
	 *
	 * @param string $route Raw route.
	 *
	 * @return string
	 */
	private function normalize_route( string $route ): string {
		$normalized = trim( $route );
		if ( '' === $normalized ) {
			return '/';
		}

		$normalized = '/' . ltrim( $normalized, '/' );
		$normalized = rtrim( $normalized, '/' );

		return strtolower( '' === $normalized ? '/' : $normalized );
	}
}
