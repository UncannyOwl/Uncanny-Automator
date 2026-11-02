<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

/**
 * Integrity-protected MRTR request state.
 *
 * The client treats this value as opaque. The signature binds it to the
 * current WordPress principal, MCP resource, method, original parameters, and
 * a short expiry so it can never become a client-controlled authorization
 * input.
 */
final class Mcp_Request_State {

	private const VERSION = 1;

	private const DEFAULT_TTL = 300;

	private Mcp_Resource_Identifier $resource_identifier;

	/**
	 * Constructor.
	 */
	public function __construct( ?Mcp_Resource_Identifier $resource_identifier = null ) {
		$this->resource_identifier = $resource_identifier ?? new Mcp_Resource_Identifier();
	}

	/**
	 * Create an opaque state value for a future MRTR retry.
	 */
	public function create( string $method, array $params, int $user_id, int $ttl = self::DEFAULT_TTL ): string {
		$payload = array(
			'v'        => self::VERSION,
			'method'   => $method,
			'user_id'  => $user_id,
			'resource' => $this->resource_identifier->get(),
			'expires'  => time() + max( 1, min( $ttl, HOUR_IN_SECONDS ) ),
			'digest'   => $this->request_digest( $params ),
			'nonce'    => wp_generate_uuid4(),
		);

		$encoded   = $this->base64url_encode( (string) wp_json_encode( $payload ) );
		$signature = hash_hmac( 'sha256', $encoded, $this->signing_key(), true );

		return $encoded . '.' . $this->base64url_encode( $signature );
	}

	/**
	 * Verify that a retry state was minted for this exact request and principal.
	 */
	public function verify( string $state, string $method, array $params, int $user_id ): bool {
		if ( strlen( $state ) > 8192 || 1 !== substr_count( $state, '.' ) ) {
			return false;
		}

		list( $encoded, $encoded_signature ) = explode( '.', $state, 2 );
		$signature                           = $this->base64url_decode( $encoded_signature );
		if ( null === $signature ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $encoded, $this->signing_key(), true );
		if ( ! hash_equals( $expected, $signature ) ) {
			return false;
		}

		$json = $this->base64url_decode( $encoded );
		if ( null === $json ) {
			return false;
		}

		$payload = json_decode( $json, true );
		if ( ! is_array( $payload ) ) {
			return false;
		}

		return self::VERSION === ( $payload['v'] ?? null )
			&& is_string( $payload['method'] ?? null )
			&& hash_equals( $payload['method'], $method )
			&& (int) ( $payload['user_id'] ?? 0 ) === $user_id
			&& is_string( $payload['resource'] ?? null )
			&& hash_equals( $payload['resource'], $this->resource_identifier->get() )
			&& (int) ( $payload['expires'] ?? 0 ) >= time()
			&& is_string( $payload['digest'] ?? null )
			&& hash_equals( $payload['digest'], $this->request_digest( $params ) );
	}

	/**
	 * Hash the result-affecting request params, excluding the retry envelope.
	 */
	private function request_digest( array $params ): string {
		unset( $params['_meta'], $params['inputResponses'], $params['requestState'] );
		$this->sort_recursive( $params );

		return hash( 'sha256', (string) wp_json_encode( $params ) );
	}

	/**
	 * Recursively sort object keys while preserving JSON list order.
	 */
	private function sort_recursive( array &$value ): void {
		$is_list = array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
		foreach ( $value as &$child ) {
			if ( is_array( $child ) ) {
				$this->sort_recursive( $child );
			}
		}
		unset( $child );

		if ( ! $is_list ) {
			ksort( $value, SORT_STRING );
		}
	}

	/**
	 * Derive a site-bound state-signing key.
	 */
	private function signing_key(): string {
		return hash_hmac( 'sha256', $this->resource_identifier->get(), wp_salt( 'auth' ), true );
	}

	/**
	 * Encode bytes using unpadded base64url.
	 */
	private function base64url_encode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Protocol token encoding.
	}

	/**
	 * Decode strict unpadded base64url.
	 */
	private function base64url_decode( string $value ): ?string {
		if ( '' === $value || 1 === preg_match( '/[^A-Za-z0-9_-]/', $value ) ) {
			return null;
		}

		$padding = ( 4 - strlen( $value ) % 4 ) % 4;
		$decoded = base64_decode( strtr( $value, '-_', '+/' ) . str_repeat( '=', $padding ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Protocol token decoding.

		return false === $decoded ? null : $decoded;
	}
}
