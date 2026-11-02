<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

/**
 * SSRF-resistant Client ID Metadata Document fetcher.
 */
final class OAuth_Cimd_Client_Fetcher {

	private const MAX_BYTES = 32768;

	/** Why the last fetch() returned null, for the authentication log. */
	private string $last_failure = '';

	/**
	 * Why the most recent fetch() call returned null.
	 *
	 * Values are short machine words: unsafe_url, dns_unresolvable, http_<status>,
	 * http_request_failed:<code>, content_type, dns_mismatch, body_too_large,
	 * invalid_metadata, client_name, auth_method, response_types, grant_types,
	 * redirect_uris.
	 */
	public function get_last_failure(): string {
		return $this->last_failure;
	}

	/**
	 * Resolve and validate one HTTPS Client ID Metadata Document.
	 *
	 * @return array|null Normalized public client metadata.
	 */
	public function fetch( string $client_id ): ?array {
		$this->last_failure = '';
		$cache_key          = 'automator_mcp_cimd_' . hash( 'sha256', $client_id );
		$cached             = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		// Only successful metadata is cached. Ignore legacy failure markers so
		// a recovered endpoint can be retried immediately (CIMD section 4.4).

		if ( strlen( $client_id ) > 2048 || ! $this->is_safe_https_url( $client_id ) ) {
			return $this->fail( 'unsafe_url' );
		}

		$before = $this->resolve_public_addresses( (string) wp_parse_url( $client_id, PHP_URL_HOST ) );
		if ( empty( $before ) ) {
			return $this->fail( 'dns_unresolvable' );
		}

		$response = wp_safe_remote_get(
			$client_id,
			array(
				'timeout'             => 5,
				'redirection'         => 0,
				'reject_unsafe_urls'  => true,
				'limit_response_size' => self::MAX_BYTES,
				'headers'             => array( 'Accept' => 'application/json' ),
				'user-agent'          => 'Uncanny-Automator-MCP-CIMD/1.0',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->fail( 'http_request_failed:' . $response->get_error_code() );
		}
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $this->fail( 'http_' . (int) wp_remote_retrieve_response_code( $response ) );
		}
		$content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( 0 !== strpos( $content_type, 'application/json' ) ) {
			return $this->fail( 'content_type' );
		}

		$after = $this->resolve_public_addresses( (string) wp_parse_url( $client_id, PHP_URL_HOST ) );
		if ( empty( $after ) || $before !== $after ) {
			return $this->fail( 'dns_mismatch' );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || strlen( $body ) > self::MAX_BYTES ) {
			return $this->fail( 'body_too_large' );
		}

		$metadata = json_decode( $body, true );
		if ( ! is_array( $metadata ) || ! isset( $metadata['client_id'] ) || ! is_string( $metadata['client_id'] ) || ! hash_equals( $client_id, $metadata['client_id'] ) ) {
			return $this->fail( 'invalid_metadata' );
		}
		if ( ! isset( $metadata['client_name'] ) || ! is_string( $metadata['client_name'] ) || '' === trim( $metadata['client_name'] ) || $this->character_length( $metadata['client_name'] ) > 100 ) {
			return $this->fail( 'client_name' );
		}
		if ( isset( $metadata['token_endpoint_auth_method'] ) && 'none' !== $metadata['token_endpoint_auth_method'] ) {
			return $this->fail( 'auth_method' );
		}
		if ( isset( $metadata['response_types'] ) && ( ! is_array( $metadata['response_types'] ) || ! in_array( 'code', $metadata['response_types'], true ) ) ) {
			return $this->fail( 'response_types' );
		}
		if ( isset( $metadata['grant_types'] ) && ( ! is_array( $metadata['grant_types'] ) || ! in_array( 'authorization_code', $metadata['grant_types'], true ) ) ) {
			return $this->fail( 'grant_types' );
		}

		$redirect_uris = $this->normalize_redirect_uris( $metadata['redirect_uris'] ?? null );
		if ( empty( $redirect_uris ) ) {
			return $this->fail( 'redirect_uris' );
		}

		$client = array(
			'client_id'     => $client_id,
			'name'          => sanitize_text_field( $metadata['client_name'] ),
			'type'          => 'public',
			'redirect_uris' => $redirect_uris,
			'source'        => 'cimd',
			'active'        => true,
		);

		set_transient( $cache_key, $client, $this->cache_lifetime( $response ) );

		return $client;
	}

	/**
	 * Normalize exact redirect URI values.
	 *
	 * @param mixed $values Candidate list.
	 * @return string[]
	 */
	private function normalize_redirect_uris( $values ): array {
		if ( ! is_array( $values ) || count( $values ) > 20 ) {
			return array();
		}

		$normalized = array();
		foreach ( $values as $uri ) {
			if ( ! is_string( $uri ) || strlen( $uri ) > 2048 || ! $this->is_safe_redirect_uri( $uri ) ) {
				return array();
			}
			$normalized[] = $uri;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Validate a redirect with the same rule administrators' registrations use:
	 * https or loopback http.
	 */
	private function is_safe_redirect_uri( string $uri ): bool {
		return OAuth_Client_Registry::is_valid_redirect_uri( $uri );
	}

	/**
	 * Record why this fetch produced no client.
	 */
	private function fail( string $reason ): ?array {
		$this->last_failure = $reason;

		return null;
	}

	/**
	 * Character length that does not count bytes when multibyte support exists.
	 */
	private function character_length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Validate the CIMD URL before any DNS or HTTP work.
	 */
	private function is_safe_https_url( string $url ): bool {
		$parts = wp_parse_url( $url );

		return is_array( $parts )
			&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
			&& isset( $parts['host'] )
			&& isset( $parts['path'] )
			&& '/' !== $parts['path']
			&& '' !== $parts['path']
			&& ! isset( $parts['user'] )
			&& ! isset( $parts['pass'] )
			&& ! isset( $parts['fragment'] )
			&& ( ! isset( $parts['port'] ) || 443 === (int) $parts['port'] )
			&& false !== wp_http_validate_url( $url );
	}

	/**
	 * Respect a bounded public max-age without retaining mutable clients forever.
	 *
	 * @param array|\WP_Error $response WordPress HTTP response.
	 */
	private function cache_lifetime( $response ): int {
		$cache_control = strtolower( (string) wp_remote_retrieve_header( $response, 'cache-control' ) );
		if ( 1 === preg_match( '/(?:^|,)\s*max-age\s*=\s*(\d+)/', $cache_control, $matches ) ) {
			return max( MINUTE_IN_SECONDS, min( HOUR_IN_SECONDS, (int) $matches[1] ) );
		}

		return 5 * MINUTE_IN_SECONDS;
	}

	/**
	 * Resolve a hostname and reject any private, reserved, or malformed address.
	 *
	 * @return string[] Sorted public IP addresses.
	 */
	private function resolve_public_addresses( string $host ): array {
		$host = trim( $host, '[]' );
		if ( '' === $host ) {
			return array();
		}

		$addresses = array();
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$addresses[] = $host;
		} else {
			// A DNS warning exposes no recoverable detail here; an empty lookup
			// is handled as an invalid CIMD host immediately below.
			$records = function_exists( 'dns_get_record' ) ? @dns_get_record( $host, DNS_A | DNS_AAAA ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! is_array( $records ) ) {
				return array();
			}
			foreach ( $records as $record ) {
				if ( isset( $record['ip'] ) && is_string( $record['ip'] ) ) {
					$addresses[] = $record['ip'];
				}
				if ( isset( $record['ipv6'] ) && is_string( $record['ipv6'] ) ) {
					$addresses[] = $record['ipv6'];
				}
			}
		}

		$addresses = array_values( array_unique( $addresses ) );
		foreach ( $addresses as $address ) {
			if (
				false === filter_var(
					$address,
					FILTER_VALIDATE_IP,
					FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
				)
			) {
				return array();
			}
		}

		sort( $addresses, SORT_STRING );

		return $addresses;
	}
}
