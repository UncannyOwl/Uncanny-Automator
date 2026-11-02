<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

use SplObjectStorage;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Rest_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Request_Scope;
use WP_Error;
use WP_REST_Request;

/**
 * Restrict MCP bearer-authenticated requests to approved REST routes.
 *
 * Keeps native authentication (Basic/cookies/application passwords) untouched.
 * The allowlist is only applied when the request is authenticated by a valid
 * MCP bearer token, including headerless subrequests made while that bearer
 * identity is active.
 *
 * @since 7.2.3
 * @since 7.2.3 Added Page Builder agent REST route access.
 * @since 7.2.3 Defers bearer identity until the route allowlist succeeds.
 */
class Rest_Bearer_Route_Authorizer {

	/**
	 * Bearer credential resolver.
	 *
	 * @since 7.2.3
	 * @var Rest_Bearer_Authenticator
	 */
	private Rest_Bearer_Authenticator $authenticator;

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
	 * Route decisions already made for a REST request.
	 *
	 * The same request is checked before argument validation and again before
	 * its callback. Cache the result so extension filters run once and both
	 * boundaries enforce the same decision.
	 *
	 * @since 7.2.3
	 * @var SplObjectStorage<WP_REST_Request,array<string,bool>>
	 */
	private SplObjectStorage $route_decisions;

	/**
	 * Constructor.
	 *
	 * @since 7.2.3
	 */
	public function __construct( ?Token_Manager $token_manager = null, ?Rest_Bearer_Authenticator $authenticator = null ) {
		$token_manager         = $token_manager ?? new Token_Manager();
		$this->authenticator   = $authenticator ?? new Rest_Bearer_Authenticator( $token_manager );
		$this->route_decisions = new SplObjectStorage();
	}

	/**
	 * Register REST authorization filter.
	 *
	 * @since 7.2.3
	 *
	 * @return void
	 */
	public function init(): void {
		// WordPress sanitizes route arguments before rest_request_before_callbacks,
		// and core sanitizers may check the current user's capabilities. Apply the
		// same exact route/method allowlist at pre-dispatch so approved Agent routes
		// have identity during validation without exposing it to other routes.
		add_filter(
			'rest_pre_dispatch',
			array( $this, 'preauthorize_bearer_route_access' ),
			20,
			3
		);
		add_filter(
			'rest_request_before_callbacks',
			array( $this, 'enforce_bearer_route_access' ),
			20,
			3
		);
	}

	/**
	 * Apply the bearer route boundary before WordPress validates route arguments.
	 *
	 * @param mixed $result  Existing pre-dispatch result.
	 * @param mixed $server  REST server instance.
	 * @param mixed $request REST request object.
	 *
	 * @return mixed
	 */
	public function preauthorize_bearer_route_access( $result, $server, $request ) {
		if ( ! empty( $result ) ) {
			return $result;
		}

		return $this->enforce_bearer_route_access( $result, array(), $request );
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
		if ( ! $request instanceof WP_REST_Request || is_wp_error( $response ) ) {
			return $response;
		}

		// Only a checked MCP tool may dispatch this exact native request. The
		// external bearer still grants no direct authority on ordinary REST URLs.
		if ( Rest_Request_Scope::is_active() ) {
			return Rest_Request_Scope::authorize( $request ) ?? $response;
		}

		// Resolve before the owned-route bypass so the original bearer remains
		// available to headerless _embed and rest_do_request() subrequests.
		$context = $this->resolve_bearer_context( $request );

		// Keep owned MCP transport routes governed by their own authentication.
		if ( $this->is_owned_mcp_route( $request ) ) {
			return $response;
		}

		// Only enforce this allowlist when auth source is MCP bearer.
		if ( ! $context instanceof Authenticated_Token_Context ) {
			// An expired/revoked Automator credential must not silently become an
			// anonymous successful read. Other plugins' bearer formats and ordinary
			// public requests remain governed by their own authentication.
			if (
				$this->authenticator->has_automator_bearer( $request )
				&& $this->is_allowed_bearer_route(
					$this->normalize_route( (string) $request->get_route() ),
					strtoupper( (string) $request->get_method() ),
					$request
				)
			) {
				return new WP_Error( 'rest_invalid_token', 'The Automator bearer token is invalid or expired.', array( 'status' => 401 ) );
			}
			return $response;
		}

		$method = strtoupper( (string) $request->get_method() );
		$route  = $this->normalize_route( (string) $request->get_route() );

		// Standards OAuth access tokens are audience-bound to the MCP endpoint.
		// Only the proprietary first-party Agent credential may reach the
		// separately allowlisted WordPress REST surfaces used by the product.
		if ( ! $context->is_first_party_agent() ) {
			return new WP_Error(
				'rest_forbidden',
				'MCP OAuth tokens are restricted to the MCP resource.',
				array( 'status' => 403 )
			);
		}

		if ( ! $this->is_allowed_bearer_route( $route, $method, $request ) ) {
			return new WP_Error(
				'rest_forbidden',
				'MCP bearer token is not allowed to access this REST route.',
				array( 'status' => 403 )
			);
		}

		$required_scope = $this->get_required_bearer_scope( $method );
		if ( $context->has_scope( $required_scope ) ) {
			// Automator has approved the exact route, method and scope. Install
			// identity only at this boundary so core may safely validate arguments
			// and, after the second check, run the permission callback and handler.
			wp_set_current_user( $context->get_user()->ID );
			return $response;
		}

		return new WP_Error(
			'rest_insufficient_scope',
			'Bearer token does not grant the required REST scope.',
			array(
				'status'         => 403,
				'required_scope' => $required_scope,
			)
		);
	}

	/**
	 * Resolve bearer-authenticated identity and scopes for this request.
	 *
	 * @since 7.2.3
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return Authenticated_Token_Context|false
	 */
	private function resolve_bearer_context( WP_REST_Request $request ) {
		return $this->authenticator->resolve_request_context( $request );
	}

	/**
	 * Resolve the granular scope required by an allowed bearer REST method.
	 *
	 * @param string $method HTTP method.
	 * @return string Required scope.
	 */
	private function get_required_bearer_scope( string $method ): string {
		if ( 'GET' === $method ) {
			return Authenticated_Token_Context::SCOPE_AGENT_READ;
		}

		return Authenticated_Token_Context::SCOPE_AGENT_WRITE;
	}

	/**
	 * Check whether an exact route/method pair belongs to the MCP transport.
	 *
	 * Do not trust the whole /mcp namespace. New transport routes must be added
	 * deliberately so an unrelated subroute cannot inherit bearer identity.
	 *
	 * @since 7.2.3
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool
	 */
	private function is_owned_mcp_route( WP_REST_Request $request ): bool {
		$route  = $this->normalize_route( (string) $request->get_route() );
		$method = strtoupper( (string) $request->get_method() );
		$base   = '/' . trim( Mcp_Rest_Controller::ROUTE_NAMESPACE . '/' . Mcp_Rest_Controller::ROUTE_BASE, '/' );

		$owned_routes = array(
			$base                                    => array( 'GET', 'POST', 'DELETE' ),
			$base . '/dropdown'                      => array( 'GET' ),
			$base . '/key-binding/proof'             => array( 'POST' ),
			$base . '/hosted/read-credential'        => array( 'POST', 'DELETE' ),
			$base . '/hosted/read-credential/status' => array( 'GET' ),
			$base . '/media-transfer'                => array( 'POST' ),
		);

		return isset( $owned_routes[ $route ] )
			&& in_array( $method, $owned_routes[ $route ], true );
	}

	/**
	 * Determine if route/method pair is allowed for MCP bearer REST access.
	 *
	 * @since 7.2.3
	 * @since 7.2.3 Added PUT/PATCH support for writer update operations.
	 * @since 7.2.3 Added Page Builder agent REST routes and extension filter.
	 *
	 * @param string $route  Normalized REST route.
	 * @param string $method HTTP method.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return bool
	 */
	private function is_allowed_bearer_route( string $route, string $method, WP_REST_Request $request ): bool {
		$decision_key = $method . '|' . $route;
		$decisions    = $this->route_decisions->offsetExists( $request ) // contains() is deprecated in PHP 8.5.
			? $this->route_decisions[ $request ]
			: array();

		if ( array_key_exists( $decision_key, $decisions ) ) {
			return $decisions[ $decision_key ];
		}

		$allowed = ( 'GET' === $method && '/automator/v1/agent-context' === $route )
			|| $this->is_allowed_writer_route( $route, $method )
			|| $this->is_allowed_page_builder_route( $route, $method );

		/**
		 * Allow trusted host integrations to opt MCP bearer tokens into their own REST routes.
		 *
		 * The route has already been normalized to a lowercase absolute path with no trailing slash.
		 * Integrations should still use their own REST permission callbacks for object-level access.
		 *
		 * @since 7.2.3
		 *
		 * @param bool            $allowed Whether the route is allowed.
		 * @param string          $route   Normalized REST route.
		 * @param string          $method  HTTP method.
		 * @param WP_REST_Request $request REST request object.
		 */
		if ( ! $allowed ) {
			$allowed = (bool) apply_filters(
				'automator_mcp_bearer_allowed_rest_route',
				false,
				$route,
				$method,
				$request
			);
		}

		$decisions[ $decision_key ]        = $allowed;
		$this->route_decisions[ $request ] = $decisions;

		return $allowed;
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
	 * Determine if route/method pair is allowed for Page Builder agent access.
	 *
	 * Page Builder exposes a dedicated /agent surface for AI-owned reads and
	 * writes. Its REST permission callbacks still enforce user capabilities and
	 * object ownership; this gate only allows MCP bearer auth to reach that surface.
	 *
	 * @since 7.2.3
	 *
	 * @param string $route  Normalized REST route.
	 * @param string $method HTTP method.
	 *
	 * @return bool
	 */
	private function is_allowed_page_builder_route( string $route, string $method ): bool {
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return false;
		}

		$prefix = '/uncanny-page-builder/v1/agent';

		return $route === $prefix || 0 === strpos( $route, $prefix . '/' );
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
