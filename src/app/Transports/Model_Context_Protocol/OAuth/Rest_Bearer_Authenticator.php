<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

use WP_Error;
use WP_REST_Request;

/**
 * Resolve MCP bearer credentials for the broader WordPress REST layer.
 *
 * Identity installation is deliberately deferred to
 * Rest_Bearer_Route_Authorizer, where WordPress has matched an exact route and
 * method. This resolver authenticates the credential without exposing its
 * administrator identity to pre-dispatch hooks or parameter callbacks.
 *
 * @since 7.2.2
 * @since 7.2.2.2 Invalid bearer-token failures are owned by the matched route.
 * @since 7.2.2.2 Prevents recursive bearer-token resolution during token metadata updates.
 * @since 7.2.3 Defers WordPress identity installation until route authorization.
 * @since 7.6.2 Answers core's authentication query for Automator-owned routes so
 *              REST-restricting security plugins cannot block them.
 */
class Rest_Bearer_Authenticator {

	/**
	 * Routes Automator authenticates itself.
	 *
	 * The MCP endpoint answers its own 401 with a bearer challenge, and the
	 * OAuth endpoints are public by protocol. Both sets are matched exactly so a
	 * third-party route registered under the same prefix is never covered.
	 *
	 * @var string[]
	 */
	private const OWNED_ROUTES = array(
		'/automator/v1/mcp',
		'/automator/v1/mcp/dropdown',
		'/automator/v1/mcp/hosted/read-credential',
		'/automator/v1/mcp/hosted/read-credential/status',
		'/automator/v1/mcp/key-binding/proof',
		'/automator/v1/mcp/media-transfer',
		'/automator/v1/mcp/settings',
		'/automator/v1/oauth/connection',
		'/automator/v1/oauth/token',
		'/automator/v1/oauth/revoke',
		'/automator/v1/oauth/register',
		'/automator/v1/oauth/authorize',
	);

	/**
	 * Token manager.
	 *
	 * @var Token_Manager
	 */
	private Token_Manager $token_manager;

	/**
	 * Cached token string for the current request.
	 *
	 * @var string|null
	 */
	private ?string $resolved_token = null;

	/**
	 * Cached resolved context for the current request.
	 *
	 * @var Authenticated_Token_Context|false|null
	 */
	private $resolved_context = null;

	/**
	 * Whether bearer-token resolution is already running.
	 *
	 * Token validation updates token metadata. That update can fire WordPress
	 * hooks that ask for the current user, which re-enters this authenticator.
	 *
	 * @since 7.2.2.2
	 *
	 * @var bool
	 */
	private bool $is_resolving_bearer_context = false;

	/**
	 * Constructor.
	 */
	public function __construct( ?Token_Manager $token_manager = null ) {
		$this->token_manager = $token_manager ?? new Token_Manager();
	}

	/**
	 * Register REST auth filters.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 1: security plugins that require a logged-in user for every REST
		// request (Solid Security "Restricted Access", Disable REST API, Force Login
		// and similar) hook this filter at the default priority and return an error
		// for anonymous requests. Running first lets Automator answer for the routes
		// it authenticates itself; well-behaved plugins return a non-empty result
		// unchanged, exactly as this method does for theirs.
		add_filter( 'rest_authentication_errors', array( $this, 'rest_authentication_errors' ), 1 );
	}

	/**
	 * Stage bearer authentication without installing WordPress identity.
	 *
	 * WordPress calls this filter before it has matched a REST route. Installing
	 * the bearer user here would let earlier dispatch hooks and argument
	 * callbacks observe administrator identity before the route allowlist runs.
	 * Matched routes remain responsible for their public error response.
	 *
	 * For Automator-owned routes the answer is `true`: the MCP endpoint issues its
	 * own 401 with a bearer challenge and the OAuth endpoints are public by
	 * protocol, so a generic "anonymous REST is forbidden" error from another
	 * plugin must not pre-empt them. `true` only tells core and later filters that
	 * authentication is settled; it never installs a user. A cookie identity that
	 * core would otherwise clear for lack of a nonce is cleared here for the same
	 * reason, so nothing observes it before the route allowlist runs.
	 *
	 * @param WP_Error|null|true $result Existing auth result.
	 *
	 * @return WP_Error|null|true
	 */
	public function rest_authentication_errors( $result ) {
		if ( ! $this->is_rest_request() || ! empty( $result ) ) {
			return $result;
		}

		if ( $this->is_resolving_bearer_context ) {
			return $result;
		}

		$context = $this->resolve_bearer_context( $this->extract_server_bearer_token() );

		$route = $this->get_owned_route();
		if ( null === $route ) {
			return $result;
		}

		if ( $context instanceof Authenticated_Token_Context && 0 === strpos( $route, '/automator/v1/mcp' ) ) {
			if ( is_user_logged_in() ) {
				wp_set_current_user( 0 );
			}

			return true;
		}

		// An anonymous request to an owned route is settled: the route answers with
		// its own protocol response. A cookie-authenticated request is left to core
		// and to the consent controller's own cookie gate.
		if ( ! is_user_logged_in() ) {
			return true;
		}

		return $result;
	}

	/**
	 * Resolve the Automator-owned REST route targeted by the current request.
	 *
	 * WordPress's parsed rest_route is authoritative, including query overrides
	 * of a rewritten path. URI parsing is only a fallback for embedded callers
	 * that have not populated WordPress's query variables.
	 *
	 * @return string|null Normalized owned route, or null for any other request.
	 */
	private function get_owned_route(): ?string {
		$route = '';
		if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			$route = is_string( $GLOBALS['wp']->query_vars['rest_route'] )
				? $this->normalize_route( $GLOBALS['wp']->query_vars['rest_route'] )
				: '';
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			$route = $this->route_from_uri( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared against a fixed allowlist only.
		}

		return in_array( $route, self::OWNED_ROUTES, true ) ? $route : null;
	}

	/**
	 * Extract the REST route from a raw request URI.
	 *
	 * @param string $request_uri Raw request URI.
	 *
	 * @return string Normalized route or '' when the URI is not a REST request.
	 */
	private function route_from_uri( string $request_uri ): string {
		$parts = wp_parse_url( $request_uri );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$query = isset( $parts['query'] ) && is_string( $parts['query'] ) ? $parts['query'] : '';
		parse_str( $query, $query_params );
		if ( array_key_exists( 'rest_route', $query_params ) ) {
			return is_string( $query_params['rest_route'] ) ? $this->normalize_route( $query_params['rest_route'] ) : '';
		}

		$rest_prefix = trim( (string) rest_get_url_prefix(), '/' );
		$path        = isset( $parts['path'] ) && is_string( $parts['path'] ) ? $parts['path'] : '';
		if ( '' !== $path && '' !== $rest_prefix ) {
			$pattern = '#/' . preg_quote( $rest_prefix, '#' ) . '(/.*)?$#i';
			if ( 1 === preg_match( $pattern, $path, $matches ) ) {
				return $this->normalize_route( (string) ( $matches[1] ?? '/' ) );
			}
		}

		return '';
	}

	/**
	 * Normalize a route the way core matches it: leading slash, no trailing slash.
	 *
	 * @param string $route Raw route.
	 *
	 * @return string
	 */
	private function normalize_route( string $route ): string {
		$route = trim( $route );
		if ( '' === $route ) {
			return '';
		}

		$route = '/' . ltrim( $route, '/' );

		return strtolower( rtrim( $route, '/' ) );
	}

	/**
	 * Resolve the bearer context available to a matched REST request.
	 *
	 * Headerless internal and embedded requests inherit the original HTTP
	 * credential from server globals. This preserves the bearer provenance so
	 * the route authorizer can apply the same allowlist to every subrequest.
	 *
	 * @param WP_REST_Request $request Matched REST request.
	 *
	 * @return Authenticated_Token_Context|false
	 */
	public function resolve_request_context( WP_REST_Request $request ) {
		if (
			$this->resolved_context instanceof Authenticated_Token_Context
			&& (int) $this->resolved_context->get_user()->ID === get_current_user_id()
		) {
			return $this->resolved_context;
		}

		// The original HTTP bearer is authoritative for every subrequest in this
		// PHP request. A nested rest_do_request() must not escape its allowlist by
		// supplying a different or invalid Authorization header.
		$token = $this->extract_server_bearer_token();
		if ( empty( $token ) ) {
			$token = $this->extract_request_bearer_token( $request );
		}

		return $this->resolve_bearer_context( $token );
	}

	/** Identify our credential format without claiming that authentication succeeded. */
	public function has_automator_bearer( WP_REST_Request $request ): bool {
		$token = $this->extract_server_bearer_token() ?? $this->extract_request_bearer_token( $request );
		return is_string( $token ) && 0 === strpos( $token, Token_Manager::TOKEN_PREFIX );
	}

	/**
	 * Resolve one bearer credential once per request.
	 *
	 * @param string|null $token Bearer credential.
	 *
	 * @return Authenticated_Token_Context|false
	 */
	private function resolve_bearer_context( ?string $token ) {
		if ( empty( $token ) ) {
			return false;
		}

		if ( $this->resolved_token === $token && null !== $this->resolved_context ) {
			return $this->resolved_context;
		}

		if ( $this->is_resolving_bearer_context ) {
			return false;
		}

		$this->resolved_token = $token;

		$this->is_resolving_bearer_context = true;

		try {
			$context = $this->token_manager->get_context_from_token( $token );
			if ( ! $context instanceof Authenticated_Token_Context ) {
				$this->resolved_context = false;
				return false;
			}

			$user = $context->get_user();
			if ( user_can( $user, 'manage_options' ) ) {
				$this->resolved_context = $context;
				return $context;
			}

			$this->resolved_context = false;
			return false;
		} finally {
			$this->is_resolving_bearer_context = false;
		}
	}

	/**
	 * Extract the MCP bearer token from a REST request.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return string|null
	 */
	private function extract_request_bearer_token( WP_REST_Request $request ): ?string {
		return $this->extract_token_from_headers(
			(string) $request->get_header( 'authorization' ),
			(string) $request->get_header( 'x-automator-creds' )
		);
	}

	/**
	 * Extract the original inbound MCP bearer token from server globals.
	 *
	 * @return string|null
	 */
	private function extract_server_bearer_token(): ?string {
		return $this->extract_token_from_headers(
			$this->read_header( 'HTTP_AUTHORIZATION' ),
			$this->read_header( 'HTTP_X_AUTOMATOR_CREDS' )
		);
	}

	/**
	 * Extract a bearer token from primary and fallback header values.
	 *
	 * @param string $auth_header Authorization header.
	 * @param string $creds       First-party fallback header.
	 *
	 * @return string|null
	 */
	private function extract_token_from_headers( string $auth_header, string $creds ): ?string {

		if ( false === strpos( strtolower( (string) $auth_header ), 'bearer' ) ) {
			$auth_header = $creds;
		}

		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			return trim( (string) $matches[1] );
		}

		return null;
	}

	/**
	 * Read a request header from PHP server globals.
	 *
	 * @since 7.2.2.2 Unslashes server-header input before use.
	 *
	 * @param string $key Server key, e.g. HTTP_AUTHORIZATION.
	 *
	 * @return string
	 */
	private function read_header( string $key ): string {
		$value = '';
		if ( isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ) {
			// Header values are opaque credential strings and must not be mutated.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque bearer credentials are intentionally not text-sanitized.
			$value = wp_unslash( $_SERVER[ $key ] );
		}

		if ( '' === $value && 'HTTP_AUTHORIZATION' === $key ) {
			if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) && is_string( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque bearer credentials are intentionally not text-sanitized.
				$value = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
			}
		}

		return $value;
	}

	/**
	 * Check whether the current request is a WordPress REST request.
	 *
	 * @return bool
	 */
	private function is_rest_request(): bool {
		// Core defines REST_REQUEST before it serves a request; the dispatch flag
		// covers rest_do_request() calls made outside of that. Either is enough.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request();
	}
}
