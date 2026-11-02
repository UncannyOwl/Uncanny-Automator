<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Internal_Token_Configuration;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;

/**
 * Rotating refresh-token families with replay detection.
 */
final class OAuth_Refresh_Token_Service {

	public const LIFETIME = 90 * DAY_IN_SECONDS;

	/**
	 * A client that lost the rotation response may retry the previous token
	 * once within this window. Later or repeated replays revoke the family.
	 */
	private const REPLAY_GRACE_SECONDS = 10;

	/**
	 * Compact, self-authenticating refresh-token format. Format 3 is signed with
	 * strong wp-config.php key material only; format 2 used `wp_salt( 'auth' )`,
	 * which WordPress can source entirely from the database.
	 */
	private const TOKEN_FORMAT = 3;

	private const LEGACY_TOKEN_FORMAT = 2;

	private const TOKEN_PREFIX = 'mrt_';

	private OAuth_Artifact_Store $store;

	private Token_Manager $token_manager;

	private OAuth_User_Revocation_Marker $revocation_marker;

	private Internal_Token_Configuration $key_configuration;

	/**
	 * Constructor.
	 */
	public function __construct( ?OAuth_Artifact_Store $store = null, ?Token_Manager $token_manager = null, ?Internal_Token_Configuration $key_configuration = null ) {
		$this->store             = $store ?? new OAuth_Artifact_Store();
		$this->token_manager     = $token_manager ?? new Token_Manager();
		$this->revocation_marker = new OAuth_User_Revocation_Marker( $this->store );
		$this->key_configuration = $key_configuration ?? new Internal_Token_Configuration();
	}

	/**
	 * Start a refresh family.
	 *
	 * @param float|null $issued_at When the grant's issuance began (its authorization
	 *                              code); defaults to now.
	 *
	 * @return array{token:string,family_id:string,generation:int,expires_in:int}|false
	 */
	public function issue( int $user_id, array $scopes, array $client, ?float $issued_at = null ) {
		$family_id = wp_generate_uuid4();
		$token     = $this->generate_token( $family_id, 0 );
		if ( null === $token ) {
			return false;
		}
		$expires_at = time() + self::LIFETIME;
		$family     = array(
			'family_id'        => $family_id,
			'token_format'     => self::TOKEN_FORMAT,
			'current_hash'     => hash( 'sha256', $token ),
			'generation'       => 0,
			'previous_used_at' => null,
			'user_id'          => $user_id,
			'scopes'           => array_values( $scopes ),
			'client_id'        => (string) $client['client_id'],
			'client_type'      => (string) $client['type'],
			'issued_at'        => $issued_at ?? microtime( true ),
			'expires_at'       => $expires_at,
			'revoked_at'       => null,
		);

		if ( ! $this->store->put( 'family', $family_id, $family ) ) {
			$this->store->delete( 'family', $family_id );
			return false;
		}

		return array(
			'token'      => $token,
			'family_id'  => $family_id,
			'generation' => 0,
			'expires_in' => self::LIFETIME,
		);
	}

	/**
	 * Inspect a refresh token without changing its state.
	 */
	public function inspect( string $token ): ?array {
		$parsed = $this->parse_compact_token( $token );
		if ( ! is_array( $parsed ) ) {
			return null;
		}

		$family = $this->store->get( 'family', $parsed['family_id'] );
		return is_array( $family ) ? $this->compact_record( $token, $parsed, $family ) : null;
	}

	/**
	 * Rotate one refresh token and revoke the family on replay.
	 * The optional issuer runs before releasing the family lock. Its result is
	 * returned as "access" so callers never issue a token after cancellation.
	 * The issuer must retire access tokens from generations before the new one:
	 * after a grace retry, those include the lost response's access token.
	 * $scopes are the new access token's scopes; the family keeps the scopes
	 * originally granted (RFC 6749 section 6).
	 *
	 * @return array|false|null Rotation result, false when the token is invalid
	 *                          or reused, null when the family lock was busy and
	 *                          the caller should retry shortly.
	 */
	public function rotate( string $token, string $client_id, array $scopes, ?callable $issue_access = null ) {
		$initial = $this->inspect( $token );
		if ( ! is_array( $initial ) || ! isset( $initial['family_id'] ) || ! is_string( $initial['family_id'] ) ) {
			return false;
		}

		$family_id = $initial['family_id'];
		$result    = $this->store->with_lock(
			'family',
			$family_id,
			function ( OAuth_Artifact_Store $store ) use ( $token, $client_id, $scopes, $family_id, $issue_access ) {
				$family = $store->get( 'family', $family_id );
				if ( ! is_array( $family ) ) {
					return false;
				}

				$parsed = $this->parse_compact_token( $token );
				if ( ! is_array( $parsed ) ) {
					return false;
				}

				$rotated_at = time();
				$rotated    = $this->rotate_compact_token( $token, $parsed, $family, $client_id, $scopes, $store );

				// Keep issuance inside the same lock as rotation and cancellation.
				// A cancellation must not finish before a late access token is saved.
				if (
					is_array( $rotated )
					&& empty( $rotated['reuse'] )
					&& null !== $issue_access
				) {
					$rotated['access'] = $issue_access( $rotated );

					if ( ! is_array( $rotated['access'] ) ) {
						$latest = $store->get( 'family', $family_id );

						if ( is_array( $latest ) ) {
							$latest['revoked_at'] = time();
							$store->put( 'family', $family_id, $latest );
						}
						$this->token_manager->revoke_oauth_family( (int) $rotated['user_id'], $family_id );
					} elseif ( empty( $rotated['grace_retry'] ) && $rotated_at < time() ) {
						// Slow issuance: start the retry grace when the response is ready.
						$latest = $store->get( 'family', $family_id );

						if ( is_array( $latest ) ) {
							$latest['previous_used_at'] = time();
							$store->put( 'family', $family_id, $latest );
						}
					}
				}

				return $rotated;
			}
		);

		if ( null === $result ) {
			// Lock contention: a concurrent refresh for the same family is in
			// flight. The token was not consumed, so the client may retry.
			return null;
		}
		if ( is_array( $result ) && ! empty( $result['reuse'] ) ) {
			$this->token_manager->revoke_oauth_family( (int) $result['user_id'], $family_id );
			OAuth_Telemetry::record( 'refresh_token_reuse', 'family_revoked', $client_id, (int) $result['user_id'] );
			return false;
		}
		if ( is_array( $result ) && ! empty( $result['grace_retry'] ) ) {
			OAuth_Telemetry::record( 'refresh_token_reuse', 'retry_rotated', $client_id, (int) $result['user_id'] );
		}

		return is_array( $result ) ? $result : false;
	}

	/**
	 * Rotate a compact token using only the locked family row.
	 */
	private function rotate_compact_token( string $token, array $parsed, array $family, string $client_id, array $scopes, OAuth_Artifact_Store $store ) {
		if ( ! $this->compact_record( $token, $parsed, $family ) ) {
			return false;
		}

		$now               = time();
		$family_generation = (int) ( $family['generation'] ?? -1 );
		$token_generation  = (int) $parsed['generation'];
		$invalid           = ! empty( $family['revoked_at'] )
			|| (int) ( $family['expires_at'] ?? 0 ) <= $now
			|| ! isset( $family['client_id'] )
			|| ! is_string( $family['client_id'] )
			|| ! hash_equals( $family['client_id'], $client_id )
			|| $this->revocation_marker->covers( (int) ( $family['user_id'] ?? 0 ), $this->issued_at( $family ) );

		// A retry must present the exact token the last rotation replaced. A valid
		// signature alone is not enough: whoever holds the signing material could
		// mint another token for that generation.
		$is_recent_retry = $token_generation < $family_generation
			&& ! $invalid
			&& $token_generation + 1 === $family_generation
			&& isset( $family['previous_used_at'] )
			&& (int) $family['previous_used_at'] + self::REPLAY_GRACE_SECONDS >= $now
			&& is_string( $family['previous_hash'] ?? null )
			&& hash_equals( $family['previous_hash'], hash( 'sha256', $token ) );
		if ( $token_generation < $family_generation && ! $is_recent_retry ) {
			$family['revoked_at'] = $now;
			$store->put( 'family', (string) $family['family_id'], $family );
			return array(
				'reuse'     => true,
				'family_id' => (string) $family['family_id'],
				'user_id'   => (int) ( $family['user_id'] ?? 0 ),
			);
		}

		if ( $invalid || ( $token_generation !== $family_generation && ! $is_recent_retry ) ) {
			return false;
		}

		// A grace retry skips the lost generation and spends the grace: neither
		// that generation nor this one's predecessor can be retried again.
		$new_generation = $family_generation + 1;
		$new_token      = $this->generate_token( (string) $family['family_id'], $new_generation );
		if ( null === $new_token ) {
			return false;
		}
		$family['previous_hash']    = hash( 'sha256', $token );
		$family['current_hash']     = hash( 'sha256', $new_token );
		$family['generation']       = $new_generation;
		$family['previous_used_at'] = $is_recent_retry ? null : $now;
		// Rotation moves a legacy family onto the external-key format.
		$family['token_format'] = self::TOKEN_FORMAT;
		if ( ! $store->put( 'family', (string) $family['family_id'], $family ) ) {
			return false;
		}

		$result = $this->rotation_result( $new_token, $family, $client_id, $scopes );
		if ( $is_recent_retry ) {
			$result['grace_retry'] = true;
		}

		return $result;
	}

	/**
	 * Reconstruct the bounded credential fields required by the token endpoint.
	 */
	private function compact_record( string $token, array $parsed, array $family ): ?array {
		$family_id         = $family['family_id'] ?? null;
		$current_hash      = $family['current_hash'] ?? null;
		$family_generation = $family['generation'] ?? null;
		$family_format     = (int) ( $family['token_format'] ?? 0 );
		if (
			! in_array( $family_format, array( self::TOKEN_FORMAT, self::LEGACY_TOKEN_FORMAT ), true )
			|| ! is_string( $family_id )
			|| ! hash_equals( $family_id, (string) $parsed['family_id'] )
			|| ! is_string( $current_hash )
			|| ! is_int( $family_generation )
			|| ! isset( $family['user_id'], $family['scopes'], $family['client_id'], $family['client_type'], $family['expires_at'] )
			|| ! is_array( $family['scopes'] )
			|| ! is_string( $family['client_id'] )
			|| ! is_string( $family['client_type'] )
		) {
			return null;
		}

		$generation = (int) $parsed['generation'];
		if ( $generation > $family_generation ) {
			return null;
		}
		// The current token must carry the family's own format, so a family upgraded
		// to format 3 never accepts a current token signed the old way.
		if (
			$generation === $family_generation
			&& ( ! hash_equals( $current_hash, hash( 'sha256', $token ) ) || $family_format !== $parsed['format'] )
		) {
			return null;
		}

		$record = array(
			'family_id'   => $family_id,
			'generation'  => $generation,
			'status'      => $generation === $family_generation ? 'active' : 'used',
			'user_id'     => (int) $family['user_id'],
			'scopes'      => array_values( $family['scopes'] ),
			'client_id'   => $family['client_id'],
			'client_type' => $family['client_type'],
			'expires_at'  => (int) $family['expires_at'],
		);
		if ( $generation + 1 === $family_generation && isset( $family['previous_used_at'] ) ) {
			$record['used_at'] = (int) $family['previous_used_at'];
		}

		return $record;
	}

	/**
	 * Build one successful compact-family rotation result.
	 */
	private function rotation_result( string $token, array $family, string $client_id, array $scopes ): array {
		return array(
			'token'       => $token,
			'family_id'   => (string) $family['family_id'],
			'generation'  => (int) $family['generation'],
			'user_id'     => (int) $family['user_id'],
			'scopes'      => array_values( $scopes ),
			'client_id'   => $client_id,
			'client_type' => (string) $family['client_type'],
			'issued_at'   => (float) $this->issued_at( $family ),
			'expires_in'  => max( 0, (int) $family['expires_at'] - time() ),
		);
	}

	/**
	 * When the family's grant was issued, or null for a family saved before issue times were kept.
	 */
	private function issued_at( array $family ): ?float {
		return is_numeric( $family['issued_at'] ?? null ) ? (float) $family['issued_at'] : null;
	}

	/**
	 * Cancel one family under the rotation lock. Old signed refresh tokens can
	 * still identify it, so a lost renewal response does not prevent cleanup.
	 */
	public function revoke_family_checked( string $family_id, int $user_id, string $client_id ): bool {
		$result = $this->store->with_lock(
			'family',
			$family_id,
			function ( OAuth_Artifact_Store $store ) use ( $family_id, $user_id, $client_id ): bool {
				$family = $store->get( 'family', $family_id );

				if ( is_array( $family ) ) {
					if (
						(int) $family['user_id'] !== $user_id
						|| ! hash_equals( (string) $family['client_id'], $client_id )
					) {
						return false;
					}

					if ( empty( $family['revoked_at'] ) ) {
						$family['revoked_at'] = time();

						if ( ! $store->put( 'family', $family_id, $family ) ) {
							return false;
						}
					}
				}

				return $this->token_manager->revoke_oauth_family_checked( $user_id, $family_id );
			}
		);

		return true === $result;
	}

	/**
	 * Revoke a family when a later credential-issuance step cannot complete.
	 */
	public function revoke_family( string $family_id, int $user_id ): void {
		if ( '' === $family_id ) {
			return;
		}

		$this->store->with_lock(
			'family',
			$family_id,
			static function ( OAuth_Artifact_Store $store ) use ( $family_id ): bool {
				$family = $store->get( 'family', $family_id );
				if ( ! is_array( $family ) ) {
					return false;
				}
				$family['revoked_at'] = time();
				return $store->put( 'family', $family_id, $family );
			}
		);
		$this->token_manager->revoke_oauth_family( $user_id, $family_id );
	}

	/**
	 * Generate a compact refresh bearer whose HMAC binds its family and generation.
	 *
	 * @return string|null Null when strong wp-config.php key material is missing.
	 */
	private function generate_token( string $family_id, int $generation ): ?string {
		$key = $this->token_signing_key( self::TOKEN_FORMAT );
		if ( null === $key ) {
			return null;
		}
		$family_hex = str_replace( '-', '', strtolower( $family_id ) );
		$nonce      = wp_generate_password( 48, false, false );
		$payload    = $family_hex . ':' . $generation . ':' . $nonce;
		$signature  = hash_hmac( 'sha256', $payload, $key );

		return self::TOKEN_PREFIX . $family_hex . '_' . sprintf( '%08d', $generation ) . $nonce . $signature;
	}

	/**
	 * Parse and authenticate a compact token before using its family lookup ID.
	 *
	 * @return array{family_id:string,generation:int,format:int}|null
	 */
	private function parse_compact_token( string $token ): ?array {
		$pattern = '/^' . preg_quote( self::TOKEN_PREFIX, '/' ) . '([a-f0-9]{32})_([0-9]{8})([A-Za-z0-9]{48})([a-f0-9]{64})$/D';
		if ( 1 !== preg_match( $pattern, $token, $matches ) ) {
			return null;
		}

		$family_hex = $matches[1];
		$generation = (int) $matches[2];
		$payload    = $family_hex . ':' . $generation . ':' . $matches[3];
		foreach ( array( self::TOKEN_FORMAT, self::LEGACY_TOKEN_FORMAT ) as $format ) {
			$key = $this->token_signing_key( $format );
			if ( null !== $key && hash_equals( hash_hmac( 'sha256', $payload, $key ), $matches[4] ) ) {
				return array(
					'family_id'  => substr( $family_hex, 0, 8 ) . '-' . substr( $family_hex, 8, 4 ) . '-' . substr( $family_hex, 12, 4 ) . '-' . substr( $family_hex, 16, 4 ) . '-' . substr( $family_hex, 20 ),
					'generation' => $generation,
					'format'     => $format,
				);
			}
		}

		return null;
	}

	/**
	 * Derive the signing key for one token format, or null when it must not be used.
	 *
	 * Format 3 uses strong wp-config.php material only. Format 2 tokens are still
	 * verified while `wp_salt( 'auth' )` has a strong wp-config.php half, so their
	 * key is not recoverable from the database alone; their next rotation moves
	 * them to format 3. On a site whose auth salt lives only in the database they
	 * are refused.
	 */
	private function token_signing_key( int $format ): ?string {
		if ( self::TOKEN_FORMAT === $format ) {
			$material = $this->key_configuration->get_key_material();

			return false === $material ? null : hash_hmac( 'sha256', 'uncanny-automator:mcp:oauth-refresh-token:v3', $material, true );
		}

		return $this->key_configuration->has_external_auth_salt()
			? hash_hmac( 'sha256', 'uncanny-automator:mcp:oauth-refresh-token:v2', wp_salt( 'auth' ), true )
			: null;
	}
}
