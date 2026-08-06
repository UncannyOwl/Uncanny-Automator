<?php
/**
 * WordPress site key-binding proof route.
 *
 * @since 7.5.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Rest_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use WP_Error;
use WP_REST_Request;

/**
 * Signs one short-lived Agent challenge with the WordPress site key.
 */
class Key_Binding_Challenge_Controller {

	private const VERSION           = 2;
	private const ROUTE             = '/mcp/key-binding/proof';
	private const MAX_CHALLENGE_TTL = 120;
	private const CLOCK_SKEW        = 30;

	/**
	 * Token manager.
	 *
	 * @var Token_Manager
	 */
	private Token_Manager $token_manager;

	/**
	 * Site signing-key manager.
	 *
	 * @var Site_Signing_Key_Manager
	 */
	private Site_Signing_Key_Manager $key_manager;

	/**
	 * Site-name callback.
	 *
	 * @var callable
	 */
	private $site_name_provider;

	/**
	 * MCP URL callback.
	 *
	 * @var callable
	 */
	private $mcp_url_provider;

	/**
	 * Clock callback.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param Token_Manager|null $token_manager Token manager.
	 * @param Site_Signing_Key_Manager|null $key_manager Site signing-key manager.
	 * @param callable|null $site_name_provider Site-name callback.
	 * @param callable|null $mcp_url_provider MCP URL callback.
	 * @param callable|null $clock Clock callback.
	 */
	public function __construct(
		?Token_Manager $token_manager = null,
		?Site_Signing_Key_Manager $key_manager = null,
		?callable $site_name_provider = null,
		?callable $mcp_url_provider = null,
		?callable $clock = null
	) {
		$this->token_manager      = $token_manager ?? new Token_Manager();
		$this->key_manager        = $key_manager ?? new Site_Signing_Key_Manager();
		$this->site_name_provider = $site_name_provider ?? array( Api_Server::class, 'get_site_name' );
		$this->mcp_url_provider   = $mcp_url_provider ?? static function (): string {
			return (string) rest_url( Mcp_Rest_Controller::ROUTE_NAMESPACE . '/' . Mcp_Rest_Controller::ROUTE_BASE );
		};
		$this->clock              = $clock ?? 'time';
	}

	/**
	 * Register the proof route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Mcp_Rest_Controller::ROUTE_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'sign_challenge' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Require an administrator bearer with the exact binding scope.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return true|WP_Error
	 */
	public function check_permissions( WP_REST_Request $request ) {
		$token = $this->extract_bearer_token( $request );

		if ( null === $token ) {
			return new WP_Error( 'rest_missing_bearer', 'A Bearer token is required.', array( 'status' => 401 ) );
		}

		$context = $this->token_manager->get_context_from_token( $token );

		if ( ! $context instanceof Authenticated_Token_Context ) {
			return new WP_Error( 'rest_invalid_bearer', 'The Bearer token is invalid or expired.', array( 'status' => 401 ) );
		}

		if ( ! user_can( $context->get_user(), 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', 'The Bearer token user cannot bind an Agent key.', array( 'status' => 403 ) );
		}

		// Do not use has_scope(). Its legacy umbrella grant must not authorize key binding.
		if ( ! in_array( Authenticated_Token_Context::SCOPE_KEY_BINDING, $context->get_scopes(), true ) ) {
			return new WP_Error(
				'rest_insufficient_scope',
				'The Bearer token does not grant the key-binding scope.',
				array(
					'status'         => 403,
					'required_scope' => Authenticated_Token_Context::SCOPE_KEY_BINDING,
				)
			);
		}

		return true;
	}

	/**
	 * Validate and sign one Agent challenge.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function sign_challenge( WP_REST_Request $request ) {
		$challenge = $this->normalize_challenge( $request );

		if ( false === $challenge ) {
			return new WP_Error(
				'invalid_key_binding_challenge',
				'The key-binding challenge is invalid.',
				array( 'status' => 400 )
			);
		}

		$canonical = $this->canonicalize_challenge( $challenge );

		$signed_record = $this->key_manager->sign_with_public_key_record( $canonical );

		if ( $signed_record instanceof WP_Error ) {
			return $signed_record;
		}

		if ( ! hash_equals( $signed_record['fingerprint'], $challenge['wordpress_key_fingerprint'] ) ) {
			return new WP_Error(
				'invalid_key_binding_challenge',
				'The key-binding challenge is invalid.',
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'version'                   => self::VERSION,
				'challenge_id'              => $challenge['challenge_id'],
				'wordpress_public_key'      => $signed_record['public_key'],
				'wordpress_key_fingerprint' => $signed_record['fingerprint'],
				'signature'                 => $signed_record['signature'],
			)
		);
	}

	/**
	 * Build the fixed canonical challenge shape.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return array<string,mixed>|false
	 */
	private function normalize_challenge( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$required_fields = array(
			'version',
			'purpose',
			'challenge_id',
			'issued_at',
			'expires_at',
			'site_name',
			'mcp_url',
			'wordpress_key_fingerprint',
		);

		if ( ! is_array( $params ) ) {
			return false;
		}

		$provided_fields = array_keys( $params );
		sort( $provided_fields );
		sort( $required_fields );

		if ( $provided_fields !== $required_fields ) {
			return false;
		}

		if (
			self::VERSION !== $params['version']
			|| Authenticated_Token_Context::SCOPE_KEY_BINDING !== $params['purpose']
			|| ! is_string( $params['challenge_id'] )
			|| ! is_int( $params['issued_at'] )
			|| ! is_int( $params['expires_at'] )
			|| ! is_string( $params['site_name'] )
			|| ! is_string( $params['mcp_url'] )
			|| ! is_string( $params['wordpress_key_fingerprint'] )
		) {
			return false;
		}

		$challenge_bytes   = $this->decode_base64url( $params['challenge_id'] );
		$fingerprint_bytes = $this->decode_base64url( $params['wordpress_key_fingerprint'] );
		$now               = (int) call_user_func( $this->clock );
		$expected_site     = trim( (string) call_user_func( $this->site_name_provider ) );
		$expected_url      = untrailingslashit( esc_url_raw( (string) call_user_func( $this->mcp_url_provider ) ) );
		$challenge_url     = untrailingslashit( esc_url_raw( $params['mcp_url'] ) );

		if (
			false === $challenge_bytes
			|| 32 !== strlen( $challenge_bytes )
			|| false === $fingerprint_bytes
			|| 32 !== strlen( $fingerprint_bytes )
			|| $params['issued_at'] > $now + self::CLOCK_SKEW
			|| $params['expires_at'] <= $now
			|| $params['expires_at'] <= $params['issued_at']
			|| $params['expires_at'] - $params['issued_at'] > self::MAX_CHALLENGE_TTL
			|| '' === $expected_site
			|| ! hash_equals( $params['site_name'], trim( $params['site_name'] ) )
			|| ! hash_equals( $expected_site, trim( $params['site_name'] ) )
			|| '' === $expected_url
			|| ! hash_equals( $params['mcp_url'], $challenge_url )
			|| ! hash_equals( $expected_url, $challenge_url )
		) {
			return false;
		}

		return array(
			'version'                   => self::VERSION,
			'purpose'                   => Authenticated_Token_Context::SCOPE_KEY_BINDING,
			'challenge_id'              => $params['challenge_id'],
			'issued_at'                 => $params['issued_at'],
			'expires_at'                => $params['expires_at'],
			'site_name'                 => trim( $params['site_name'] ),
			'mcp_url'                   => $challenge_url,
			'wordpress_key_fingerprint' => $params['wordpress_key_fingerprint'],
		);
	}

	/**
	 * Extract one Bearer token from the primary or fallback header.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return string|null
	 */
	private function extract_bearer_token( WP_REST_Request $request ): ?string {
		$header = trim( (string) $request->get_header( 'authorization' ) );

		// Some Apache configurations remove Authorization. MCP sends the same Bearer token in this fallback header.
		if ( false === stripos( $header, 'bearer' ) ) {
			$header = trim( (string) $request->get_header( 'x-automator-creds' ) );
		}

		if ( 1 !== preg_match( '/^Bearer\s+([^\s]+)$/i', $header, $matches ) ) {
			return null;
		}

		return (string) $matches[1];
	}

	/**
	 * Build length-prefixed signature bytes.
	 *
	 * @param array<string,mixed> $challenge Normalized challenge.
	 * @return string
	 */
	private function canonicalize_challenge( array $challenge ): string {
		$canonical = 'uncanny-agent-key-binding-v2';

		foreach ( $challenge as $name => $value ) {
			$name       = (string) $name;
			$value      = (string) $value;
			$canonical .= strlen( $name ) . ':' . $name . strlen( $value ) . ':' . $value;
		}

		return $canonical;
	}

	/**
	 * Decode strict unpadded base64url.
	 *
	 * @param string $value Encoded value.
	 * @return string|false
	 */
	private function decode_base64url( string $value ) {
		if ( '' === $value || false !== strpos( $value, '=' ) || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
			return false;
		}

		$padding = ( 4 - strlen( $value ) % 4 ) % 4;
		$decoded = base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', $padding ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Protocol encoding.

		if ( false === $decoded ) {
			return false;
		}

		$canonical = rtrim( strtr( base64_encode( $decoded ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Protocol encoding.

		return hash_equals( $value, $canonical ) ? $decoded : false;
	}
}
