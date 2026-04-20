<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\OAuth;

use WP_Error;
use WP_User;

/**
 * Bridge MCP bearer auth into the broader WordPress REST layer.
 *
 * This lets authenticated MCP clients call standard wp/v2 endpoints as the
 * underlying WordPress user instead of only our custom MCP routes.
 *
 * @since 7.1.0
 */
class Rest_Bearer_Authenticator {

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
	 * Cached resolved user for the current request.
	 *
	 * @var WP_User|false|null
	 */
	private WP_User|false|null $resolved_user = null;

	/**
	 * Whether the current request included a bearer token.
	 *
	 * @var bool
	 */
	private bool $has_bearer_token = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token_manager = new Token_Manager();
	}

	/**
	 * Register REST auth filters.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'determine_current_user', array( $this, 'determine_current_user' ), 20 );
		add_filter( 'rest_authentication_errors', array( $this, 'rest_authentication_errors' ), 20 );
	}

	/**
	 * Authenticate REST requests with the MCP bearer token.
	 *
	 * @param int|false $user_id Existing resolved user ID.
	 *
	 * @return int|false
	 */
	public function determine_current_user( $user_id ) {
		if ( ! $this->is_rest_request() || ! empty( $user_id ) ) {
			return $user_id;
		}

		$user = $this->resolve_bearer_user();
		if ( $user instanceof WP_User ) {
			return (int) $user->ID;
		}

		return $user_id;
	}

	/**
	 * Return a 401 when a bearer token is present but invalid.
	 *
	 * @param WP_Error|null|true $result Existing auth result.
	 *
	 * @return WP_Error|null|true
	 */
	public function rest_authentication_errors( $result ) {
		if ( ! $this->is_rest_request() || ! empty( $result ) ) {
			return $result;
		}

		$user = $this->resolve_bearer_user();
		if ( $user instanceof WP_User ) {
			wp_set_current_user( $user->ID );
			return $result;
		}

		if ( $this->has_bearer_token ) {
			return new WP_Error(
				'rest_forbidden',
				'Invalid or expired Bearer token.',
				array( 'status' => 401 )
			);
		}

		return $result;
	}

	/**
	 * Resolve the bearer token to a WordPress user once per request.
	 *
	 * @return WP_User|false
	 */
	private function resolve_bearer_user() {
		$token = $this->extract_bearer_token();
		if ( empty( $token ) ) {
			$this->has_bearer_token = false;
			return false;
		}

		if ( $this->resolved_token === $token && null !== $this->resolved_user ) {
			return $this->resolved_user;
		}

		$this->has_bearer_token = true;
		$this->resolved_token   = $token;

		$user = $this->token_manager->get_user_from_token( $token );
		if ( $user instanceof WP_User && user_can( $user, 'manage_options' ) ) {
			$this->resolved_user = $user;
			return $user;
		}

		$this->resolved_user = false;
		return false;
	}

	/**
	 * Extract the MCP bearer token from request headers.
	 *
	 * @return string|null
	 */
	private function extract_bearer_token(): ?string {
		$auth_header = $this->read_header( 'HTTP_AUTHORIZATION' );
		$creds       = $this->read_header( 'HTTP_X_AUTOMATOR_CREDS' );

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
	 * @param string $key Server key, e.g. HTTP_AUTHORIZATION.
	 *
	 * @return string
	 */
	private function read_header( string $key ): string {
		$value = $_SERVER[ $key ] ?? '';
		if ( empty( $value ) && 'HTTP_AUTHORIZATION' === $key ) {
			$value = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
		}
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Check whether the current request is a WordPress REST request.
	 *
	 * @return bool
	 */
	private function is_rest_request(): bool {
		if ( function_exists( 'wp_is_serving_rest_request' ) ) {
			return wp_is_serving_rest_request();
		}

		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
