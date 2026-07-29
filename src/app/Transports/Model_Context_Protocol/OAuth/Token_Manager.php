<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

// phpcs:disable PSR2.Methods.FunctionClosingBrace.SpacingBeforeClose -- Deliberate breathing room at function boundaries.

use Uncanny_Automator\App\Recipe_Builder\Shared\Polyfill\Str;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Token Manager.
 *
 * Handles MCP Bearer token generation, validation, and management.
 * Uses encrypted user meta storage for security.
 *
 * @since 7.0.0
 */
class Token_Manager {

	/**
	 * Credential aggregate repository.
	 *
	 * @var Token_Credential_Repository
	 */
	private $credential_repository;

	/**
	 * Current-site identity and storage policy.
	 *
	 * @var Token_Site_Policy
	 */
	private $site_policy;

	/**
	 * Recoverable internal-token cache cipher.
	 *
	 * @var Internal_Token_Cache_Cipher
	 */
	private $internal_cache_cipher;

	/**
	 * Internal-token issuance lock.
	 *
	 * @var Internal_Token_Issuance_Lock
	 */
	private $issuance_lock;

	/**
	 * Constructor.
	 *
	 * @param Token_Credential_Repository|null   $credential_repository       Credential storage boundary.
	 * @param Internal_Token_Configuration|null  $internal_token_configuration Strong-secret policy.
	 * @param Token_Site_Policy|null             $site_policy                 Current-site storage policy.
	 * @param Internal_Token_Cache_Cipher|null   $internal_cache_cipher       Internal-cache cipher.
	 * @param Internal_Token_Issuance_Lock|null  $issuance_lock               Internal-token issuance lock.
	 */
	public function __construct(
		?Token_Credential_Repository $credential_repository = null,
		?Internal_Token_Configuration $internal_token_configuration = null,
		?Token_Site_Policy $site_policy = null,
		?Internal_Token_Cache_Cipher $internal_cache_cipher = null,
		?Internal_Token_Issuance_Lock $issuance_lock = null
	) {

		$this->credential_repository = null !== $credential_repository
			? $credential_repository
			: new Token_Credential_Repository();
		$this->site_policy           = $site_policy ?? new Token_Site_Policy();
		$configuration               = $internal_token_configuration ?? new Internal_Token_Configuration();
		$this->internal_cache_cipher = $internal_cache_cipher
			?? new Internal_Token_Cache_Cipher( $configuration, $this->site_policy );
		$this->issuance_lock         = $issuance_lock ?? new Internal_Token_Issuance_Lock();

	}

	/**
	 * Return the actionable configuration error when internal tokens are unsafe.
	 *
	 * @return \WP_Error|null Configuration error or null when ready.
	 */
	public function get_internal_token_configuration_error() {

		return $this->internal_cache_cipher->get_configuration_error();

	}

	/**
	 * Encryption method for token storage.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const ENCRYPTION_METHOD = 'aes-256-cbc';

	/**
	 * Hash algorithm for HMAC.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const HASH_ALGORITHM = 'sha256';

	/**
	 * Token prefix for easy identification.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const TOKEN_PREFIX = 'mcp_';

	/**
	 * Default token expiry (24 hours).
	 *
	 * @since 7.0.0
	 * @var int
	 */
	const DEFAULT_EXPIRY = DAY_IN_SECONDS;

	// -------------------------------------------------------------------------
	// Token issuance and internal cache.
	// -------------------------------------------------------------------------

	/**
	 * Generate a new Bearer token for a user.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param array  $scopes     Token scopes (optional).
	 * @param int    $expires_in Expiry time in seconds (optional).
	 * @param string $name       Token name for identification (optional).
	 * @param array  $metadata   Internal issuance metadata (optional).
	 * @return array|false Token data on success, false on failure.
	 */
	public function generate_token( $user_id, $scopes = array(), $expires_in = null, $name = '', $metadata = array() ) {

		// Validate user exists.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return false;
		}

		// Generate secure token.
		$token = self::TOKEN_PREFIX . wp_generate_uuid4() . '_' . wp_generate_password( 32, false );

		// Set expiry.
		$expires_in = $expires_in ? $expires_in : self::DEFAULT_EXPIRY;
		$expires_at = time() + $expires_in;

		// Token data.
		$token_data = array(
			'user_id'    => $user_id,
			'scopes'     => $scopes,
			'created_at' => time(),
			'expires_at' => $expires_at,
			'name'       => $name ? $name : 'MCP Token',
			'last_used'  => null,
			'internal'   => ! empty( $metadata['internal'] ),
			'site_id'    => $this->site_policy->get_site_id(),
			'audience'   => $this->site_policy->get_audience(),
		);

		// Generate token hash for storage key.
		$token_hash = hash( 'sha256', $token );

		// Store encrypted token data in user meta.
		$encrypted_data = $this->encrypt_token_data(
			$token_data,
			$user_id,
			$this->site_policy->get_primary_encryption_context()
		);
		if ( false === $encrypted_data ) {
			return false;
		}

		// Store the token through the serialized aggregate mutation boundary.
		$stored = $this->credential_repository->mutate(
			$user_id,
			$this->site_policy->get_token_meta_key(),
			function ( array $user_tokens ) use ( $token_hash, $encrypted_data ) {

				$user_tokens[ $token_hash ] = $encrypted_data;

				return $user_tokens;

			}
		);

		if ( ! $stored ) {
			return false;
		}

		// Log token creation.
		$this->log_token_event(
			'token_created',
			$user_id,
			$token_hash,
			array(
				'name'       => $token_data['name'],
				'expires_at' => $expires_at,
			)
		);

		return array(
			'token'      => $token,
			'expires_in' => $expires_in,
			'expires_at' => $expires_at,
			'scopes'     => $scopes,
		);

	}

	/**
	 * Retrieve or create an internal system token for chat usage.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param array  $scopes Token scopes.
	 * @param int    $expires_in Expiry seconds.
	 * @param string $name Token name for audit trail.
	 * @return string|null Bearer token string or null on failure.
	 */
	public function get_or_create_internal_token( $user_id, array $scopes, int $expires_in, string $name ) {

		$lock_owner = $this->issuance_lock->acquire( (int) $user_id );

		if ( false === $lock_owner ) {
			return null;
		}

		try {
			return $this->resolve_internal_token( $user_id, $scopes, $expires_in, $name );
		} finally {
			$this->issuance_lock->release( (int) $user_id, $lock_owner );
		}

	}

	/**
	 * Resolve an internal token while holding the user/site issuance lock.
	 *
	 * @since 7.4.1
	 *
	 * @param int    $user_id   WordPress user ID.
	 * @param array  $scopes    Token scopes.
	 * @param int    $expires_in Expiry seconds.
	 * @param string $name      Token name.
	 * @return string|null Bearer token string or null on failure.
	 */
	private function resolve_internal_token( $user_id, array $scopes, int $expires_in, string $name ) {

		if ( ! $this->site_policy->remove_legacy_network_credentials( (int) $user_id ) ) {
			return null;
		}

		// Recoverable bearer storage must never fall back to database-backed salts.
		if ( ! $this->internal_cache_cipher->is_ready() ) {
			$this->invalidate_internal_token_cache(
				$user_id,
				get_user_meta( $user_id, $this->site_policy->get_internal_token_meta_key(), true )
			);
			return null;
		}

		$cached = $this->get_internal_token_cache( $user_id );

		if ( null === $cached ) {
			return null;
		}

		if ( false !== $cached ) {
			$hash = $cached['token_hash'];
			if ( $cached['expires_at'] > time() ) {
				$tokens = $this->get_user_tokens( $user_id );
				if (
					isset( $tokens[ $hash ] )
					&& ! empty( $tokens[ $hash ]['internal'] )
					&& $this->internal_token_scopes_match( $tokens[ $hash ], $scopes )
				) {
					return $cached['token'];
				}
			}

			// The cache no longer represents an active internal token.
			if (
				! $this->revoke_internal_tokens_for_user( $user_id, $hash )
				|| ! $this->delete_internal_token_cache( $user_id )
			) {
				return null;
			}
		}

		$result = $this->generate_token( $user_id, $scopes, $expires_in, $name, array( 'internal' => true ) );
		if ( empty( $result['token'] ) ) {
			return null;
		}

		$cache = array(
			'token'      => $result['token'],
			'expires_at' => $result['expires_at'],
			'token_hash' => hash( 'sha256', $result['token'] ),
		);

		if ( ! $this->store_internal_token_cache( $user_id, $cache ) ) {
			// Never return a token whose recoverable cache failed to persist.
			$this->revoke_internal_tokens_for_user( $user_id, $cache['token_hash'] );
			return null;
		}

		return $result['token'];

	}

	/**
	 * Determine whether a cached internal credential has the requested grants.
	 *
	 * Internal Agent credentials are rotated when their authorization contract
	 * changes. This prevents a previously cached token from silently preserving
	 * either missing privileges or privileges the current caller did not request.
	 *
	 * @since 7.4.1
	 *
	 * @param array $token_data       Decrypted token record.
	 * @param array $requested_scopes Scopes required by the internal caller.
	 * @return bool Whether the scope sets match.
	 */
	private function internal_token_scopes_match( array $token_data, array $requested_scopes ) {

		if ( ! isset( $token_data['scopes'] ) || ! is_array( $token_data['scopes'] ) ) {
			return false;
		}

		return $this->normalize_scope_set( $token_data['scopes'] )
			=== $this->normalize_scope_set( $requested_scopes );

	}

	/**
	 * Normalize scopes for order-independent authorization comparisons.
	 *
	 * @since 7.4.1
	 *
	 * @param array $scopes Candidate scope values.
	 * @return string[] Sorted unique non-empty scopes.
	 */
	private function normalize_scope_set( array $scopes ) {

		$normalized = array();

		foreach ( $scopes as $scope ) {
			if ( ! is_string( $scope ) ) {
				continue;
			}

			$scope = trim( $scope );
			if ( '' !== $scope ) {
				$normalized[ $scope ] = $scope;
			}
		}

		ksort( $normalized, SORT_STRING );

		return array_values( $normalized );

	}

	/**
	 * Read and decrypt the internal chat token cache.
	 *
	 * Legacy plaintext cache values are encrypted in place before their token
	 * can be reused. If migration fails, the plaintext value is removed and the
	 * caller rotates to a new token.
	 *
	 * @since 7.4.1
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|false|null Decrypted cache data, false when safely absent,
	 *                          or null when invalidation could not be persisted.
	 */
	private function get_internal_token_cache( $user_id ) {

		$stored = get_user_meta( $user_id, $this->site_policy->get_internal_token_meta_key(), true );

		if ( empty( $stored ) ) {
			return false;
		}

		// Migrate the pre-8.0.0 plaintext cache on first use.
		if ( is_array( $stored ) && isset( $stored['token'] ) ) {
			if ( is_multisite() ) {
				return $this->reject_internal_token_cache( $user_id, $stored );
			}

			$legacy_cache = $this->normalize_internal_token_cache( $stored );

			if ( false === $legacy_cache || ! $this->store_internal_token_cache( $user_id, $legacy_cache ) ) {
				return $this->reject_internal_token_cache( $user_id, $stored );
			}

			return $legacy_cache;
		}

		if ( ! $this->is_valid_internal_cache_envelope( $stored ) ) {
			return $this->reject_internal_token_cache( $user_id, $stored );
		}

		$version = $stored['version'];

		// Migrate the prior CBC-HMAC cache only where its global key was valid.
		if ( Internal_Token_Cache_Cipher::LEGACY_VERSION === $version && ! is_multisite() ) {
			$decrypted = $this->decrypt_token_data(
				$stored['ciphertext'],
				$user_id,
				Internal_Token_Cache_Cipher::LEGACY_ENCRYPTION_CONTEXT
			);
			$cache     = $this->normalize_internal_token_cache( $decrypted );

			if ( ! $this->is_internal_cache_hash_valid( $cache, $stored['token_hash'] ) ) {
				return $this->reject_internal_token_cache( $user_id, $stored );
			}

			if ( ! $this->store_internal_token_cache( $user_id, $cache ) ) {
				return $this->reject_internal_token_cache( $user_id, $stored );
			}

			return $cache;
		}

		if ( Internal_Token_Cache_Cipher::VERSION !== $version ) {
			return $this->reject_internal_token_cache( $user_id, $stored );
		}

		$decrypted = $this->internal_cache_cipher->decrypt(
			$stored['ciphertext'],
			$user_id,
			$version,
			$stored['token_hash']
		);
		$cache     = $this->normalize_internal_token_cache( $decrypted );

		if ( ! $this->is_internal_cache_hash_valid( $cache, $stored['token_hash'] ) ) {
			return $this->reject_internal_token_cache( $user_id, $stored );
		}

		return $cache;

	}

	/**
	 * Encrypt and persist an internal chat token cache value.
	 *
	 * Only the non-secret token hash and envelope version remain readable in
	 * user meta. The bearer token and its expiry are authenticated ciphertext.
	 *
	 * @since 7.4.1
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $cache   Normalized internal token cache.
	 * @return bool Whether the encrypted cache was persisted.
	 */
	private function store_internal_token_cache( $user_id, array $cache ) {

		$encrypted = $this->internal_cache_cipher->encrypt(
			$cache,
			$user_id,
			Internal_Token_Cache_Cipher::VERSION,
			$cache['token_hash']
		);

		if ( false === $encrypted ) {
			return false;
		}

		return (bool) update_user_meta(
			$user_id,
			$this->site_policy->get_internal_token_meta_key(),
			array(
				'version'    => Internal_Token_Cache_Cipher::VERSION,
				'token_hash' => $cache['token_hash'],
				'ciphertext' => $encrypted,
			)
		);

	}

	/**
	 * Validate and normalize decrypted internal token cache data.
	 *
	 * @since 7.4.1
	 *
	 * @param mixed $cache Candidate cache data.
	 * @return array|false Normalized cache or false when invalid.
	 */
	private function normalize_internal_token_cache( $cache ) {

		if ( ! $this->is_valid_internal_cache_payload( $cache ) ) {
			return false;
		}

		$token_hash = hash( 'sha256', $cache['token'] );

		if ( ! $this->is_optional_internal_cache_hash_valid( $cache, $token_hash ) ) {
			return false;
		}

		return array(
			'token'      => $cache['token'],
			'expires_at' => (int) $cache['expires_at'],
			'token_hash' => $token_hash,
		);

	}

	/**
	 * Determine whether an outer cache envelope has the required shape.
	 *
	 * @since 7.4.1
	 *
	 * @param mixed $stored Stored cache value.
	 * @return bool Whether the envelope can be processed safely.
	 */
	private function is_valid_internal_cache_envelope( $stored ) {

		return is_array( $stored )
			&& isset( $stored['version'], $stored['token_hash'], $stored['ciphertext'] )
			&& is_int( $stored['version'] )
			&& is_string( $stored['token_hash'] )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/D', $stored['token_hash'] )
			&& ! empty( $stored['ciphertext'] )
			&& is_string( $stored['ciphertext'] );

	}

	/**
	 * Determine whether decrypted cache data contains a usable bearer.
	 *
	 * @since 7.4.1
	 *
	 * @param mixed $cache Candidate cache payload.
	 * @return bool Whether the payload can be normalized.
	 */
	private function is_valid_internal_cache_payload( $cache ) {

		return is_array( $cache )
			&& ! empty( $cache['token'] )
			&& is_string( $cache['token'] )
			&& Str::starts_with( $cache['token'], self::TOKEN_PREFIX )
			&& ! empty( $cache['expires_at'] )
			&& is_numeric( $cache['expires_at'] );

	}

	/**
	 * Determine whether an optional payload hash matches its bearer.
	 *
	 * @since 7.4.1
	 *
	 * @param array  $cache      Candidate cache payload.
	 * @param string $token_hash Hash derived from the bearer.
	 * @return bool Whether the optional hash is absent or valid.
	 */
	private function is_optional_internal_cache_hash_valid( array $cache, $token_hash ) {

		if ( ! isset( $cache['token_hash'] ) ) {
			return true;
		}

		return is_string( $cache['token_hash'] )
			&& hash_equals( $token_hash, $cache['token_hash'] );

	}

	/**
	 * Determine whether normalized cache data matches an outer hash.
	 *
	 * @since 7.4.1
	 *
	 * @param array|false $cache      Normalized cache data.
	 * @param string      $token_hash Outer envelope hash.
	 * @return bool Whether the cache and outer envelope agree.
	 */
	private function is_internal_cache_hash_valid( $cache, $token_hash ) {

		return is_array( $cache )
			&& hash_equals( $cache['token_hash'], $token_hash );

	}

	/**
	 * Reject an unreadable cache only after its current-site credentials are
	 * persistently revoked.
	 *
	 * @since 7.4.1
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param mixed $stored  Invalid stored cache value.
	 * @return false|null False when rotation is safe, null on persistence failure.
	 */
	private function reject_internal_token_cache( $user_id, $stored ) {

		if ( ! $this->invalidate_internal_token_cache( $user_id, $stored ) ) {
			return null;
		}

		return false;

	}

	/**
	 * Revoke current-site internal credentials and remove their cache.
	 *
	 * The readable outer hash is only a revocation hint because it is not
	 * trusted until AEAD verification succeeds. All decryptable internal token
	 * records are therefore removed as well.
	 *
	 * @since 7.4.1
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param mixed $stored  Stored cache value.
	 * @return bool Whether revocation and cache deletion were persisted.
	 */
	private function invalidate_internal_token_cache( $user_id, $stored ) {

		$token_hash = $this->get_stored_internal_token_hash( $stored );

		if ( ! $this->revoke_internal_tokens_for_user( $user_id, $token_hash ) ) {
			return false;
		}

		return $this->delete_internal_token_cache( $user_id );

	}

	/**
	 * Return a safe revocation hint from an outer cache envelope.
	 *
	 * @since 7.4.1
	 *
	 * @param mixed $stored Stored cache value.
	 * @return string Valid SHA-256 hash or an empty string.
	 */
	private function get_stored_internal_token_hash( $stored ) {

		if ( ! is_array( $stored ) || ! isset( $stored['token_hash'] ) ) {
			return '';
		}

		if ( ! is_string( $stored['token_hash'] ) ) {
			return '';
		}

		return 1 === preg_match( '/^[a-f0-9]{64}$/D', $stored['token_hash'] )
			? $stored['token_hash']
			: '';

	}

	/**
	 * Revoke every internal token and unreadable record for the current site.
	 *
	 * @since 7.4.1
	 *
	 * @param int    $user_id            WordPress user ID.
	 * @param string $preferred_token_hash Readable cache hash to remove even if
	 *                                     its primary record cannot be decrypted.
	 * @return bool Whether any required persistence succeeded.
	 */
	private function revoke_internal_tokens_for_user( $user_id, $preferred_token_hash = '' ) {

		$revoked_hashes = array();
		$updated        = $this->credential_repository->mutate(
			$user_id,
			$this->site_policy->get_token_meta_key(),
			function ( array $encrypted_tokens ) use ( $user_id, $preferred_token_hash, &$revoked_hashes ) {

				$updated_tokens = $encrypted_tokens;

				foreach ( $encrypted_tokens as $token_hash => $encrypted_data ) {
					$token_data = $this->decrypt_token_data(
						$encrypted_data,
						$user_id,
						$this->site_policy->get_primary_encryption_context()
					);

					$is_preferred = $preferred_token_hash && hash_equals( $preferred_token_hash, (string) $token_hash );
					$is_invalid   = ! is_array( $token_data ) || ! $this->site_policy->is_token_for_current_site( $token_data );
					$is_internal  = ! $is_invalid && ! empty( $token_data['internal'] );

					if ( $this->is_internal_token_revocable( $is_preferred, $is_invalid, $is_internal ) ) {
						unset( $updated_tokens[ $token_hash ] );
						$revoked_hashes[] = (string) $token_hash;
					}
				}

				return $updated_tokens;

			}
		);

		if ( ! $updated ) {
			return false;
		}

		foreach ( $revoked_hashes as $revoked_hash ) {
			delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( $revoked_hash ) );
		}

		if ( $preferred_token_hash && ! in_array( $preferred_token_hash, $revoked_hashes, true ) ) {
			delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( $preferred_token_hash ) );
		}

		return true;

	}

	/**
	 * Determine whether an internal credential must leave the aggregate.
	 *
	 * @since 7.4.1
	 *
	 * @param bool $is_preferred Whether the readable cache hash selected it.
	 * @param bool $is_invalid Whether the credential is unreadable or foreign.
	 * @param bool $is_internal Whether it is an internal Agent credential.
	 * @return bool Whether the credential must be revoked.
	 */
	private function is_internal_token_revocable( $is_preferred, $is_invalid, $is_internal ) {

		return $is_preferred || $is_invalid || $is_internal;

	}

	/**
	 * Delete the current-site internal cache with a reliable success result.
	 *
	 * @since 7.4.1
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool Whether the cache is absent.
	 */
	private function delete_internal_token_cache( $user_id ) {

		$meta_key = $this->site_policy->get_internal_token_meta_key();

		if ( ! metadata_exists( 'user', $user_id, $meta_key ) ) {
			return true;
		}

		return (bool) delete_user_meta( $user_id, $meta_key );

	}

	// -------------------------------------------------------------------------
	// Token validation and revocation.
	// -------------------------------------------------------------------------

	/**
	 * Validate a Bearer token.
	 *
	 * @since 7.0.0
	 *
	 * @param string $token Bearer token.
	 * @return array|false Token data on success, false on failure.
	 */
	public function validate_token( $token ) {

		if ( empty( $token ) || ! Str::starts_with( $token, self::TOKEN_PREFIX ) ) {
			return false;
		}

		// Generate token hash.
		$token_hash = hash( 'sha256', $token );

		// Find token data by checking all users' encrypted tokens.
		$token_data = $this->find_encrypted_token_data( $token_hash );

		if ( false === $token_data ) {
			return false;
		}

		// Reject copied ciphertext and legacy network-global token records.
		if ( ! $this->site_policy->is_token_for_current_site( $token_data ) ) {
			return false;
		}

		// Check expiry.
		if ( $token_data['expires_at'] <= time() ) {
			$this->revoke_token( $token );
			return false;
		}

		// Keep usage telemetry outside the credential aggregate. Rewriting the
		// aggregate here can race with revocation and resurrect a stale bearer.
		$token_data['last_used'] = time();
		update_user_meta(
			$token_data['user_id'],
			$this->site_policy->get_token_last_used_meta_key( $token_hash ),
			$token_data['last_used']
		);

		// Log token usage.
		$this->log_token_event( 'token_used', $token_data['user_id'], $token_hash );

		return $token_data;

	}

	/**
	 * Get the authenticated identity and authorization scopes for a Bearer token.
	 *
	 * Authorization boundaries should use this method so token scope is not
	 * discarded after authentication.
	 *
	 * @since 7.4.1
	 *
	 * @param string $token Bearer token.
	 * @return Authenticated_Token_Context|false Context on success, false on failure.
	 */
	public function get_context_from_token( $token ) {

		$token_data = $this->validate_token( $token );

		if ( ! is_array( $token_data ) ) {
			return false;
		}

		$user = get_user_by( 'id', $token_data['user_id'] );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		$scopes = isset( $token_data['scopes'] ) && is_array( $token_data['scopes'] )
			? $token_data['scopes']
			: array();

		return new Authenticated_Token_Context( $user, $scopes );

	}

	/**
	 * Get WordPress user from Bearer token.
	 *
	 * This compatibility method intentionally exposes identity only. New
	 * authorization code must use get_context_from_token() and enforce scope.
	 *
	 * @since 7.0.0
	 *
	 * @param string $token Bearer token.
	 * @return \WP_User|false User object on success, false on failure.
	 */
	public function get_user_from_token( $token ) {

		$context = $this->get_context_from_token( $token );

		if ( ! $context instanceof Authenticated_Token_Context ) {
			return false;
		}

		return $context->get_user();

	}

	/**
	 * Revoke a Bearer token.
	 *
	 * @since 7.0.0
	 *
	 * @param string $token Bearer token.
	 * @return bool True on success, false on failure.
	 */
	public function revoke_token( $token ) {

		$token_hash = hash( 'sha256', $token );
		$token_data = $this->find_encrypted_token_data( $token_hash );

		if ( false === $token_data ) {
			return false;
		}

		return $this->revoke_token_by_hash( $token_data['user_id'], $token_hash );

	}

	/**
	 * Revoke one token by its non-secret storage hash.
	 *
	 * Administrative token UIs cannot recover the one-time bearer value. This
	 * method keeps their revocation inside the same serialized mutation boundary
	 * as bearer-based revocation.
	 *
	 * @since 7.4.1
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token_hash SHA-256 token hash.
	 * @return bool True on success, false when missing or persistence fails.
	 */
	public function revoke_token_by_hash( $user_id, $token_hash ) {

		if ( ! is_string( $token_hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $token_hash ) ) {
			return false;
		}

		// Remove from encrypted user meta through the shared mutation boundary.
		$removed = false;
		$updated = $this->credential_repository->mutate(
			$user_id,
			$this->site_policy->get_token_meta_key(),
			function ( array $user_tokens ) use ( $token_hash, &$removed ) {

				if ( isset( $user_tokens[ $token_hash ] ) ) {
					unset( $user_tokens[ $token_hash ] );
					$removed = true;
				}

				return $user_tokens;

			}
		);

		if ( ! $updated || ! $removed ) {
			return false;
		}

		delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( $token_hash ) );

		// Log token revocation.
		$this->log_token_event( 'token_revoked', $user_id, $token_hash );

		return true;

	}

	/**
	 * Get all tokens for a user.
	 *
	 * @since 7.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Array of token data.
	 */
	public function get_user_tokens( $user_id ) {

		$encrypted_tokens = $this->get_encrypted_user_tokens( $user_id );
		$active_tokens    = array();

		foreach ( $encrypted_tokens as $token_hash => $encrypted_data ) {
			$token_data = $this->decrypt_token_data(
				$encrypted_data,
				$user_id,
				$this->site_policy->get_primary_encryption_context()
			);

			if ( false === $token_data || ! $this->site_policy->is_token_for_current_site( $token_data ) ) {
				// Failed to decrypt - omit invalid token without rewriting the
				// credential aggregate from this read path.
				delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( (string) $token_hash ) );
				continue;
			}

			// Check if token is still active.
			if ( $token_data['expires_at'] > time() ) {
				$last_used                    = get_user_meta(
					$user_id,
					$this->site_policy->get_token_last_used_meta_key( (string) $token_hash ),
					true
				);
				$active_tokens[ $token_hash ] = array(
					'name'       => $token_data['name'],
					'created_at' => $token_data['created_at'],
					'expires_at' => $token_data['expires_at'],
					'last_used'  => is_numeric( $last_used ) ? (int) $last_used : $token_data['last_used'],
					'scopes'     => $token_data['scopes'],
					'is_active'  => true,
					'internal'   => ! empty( $token_data['internal'] ),
				);
			} else {
				// Expired tokens are omitted here and removed by explicit cleanup.
				delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( (string) $token_hash ) );
			}
		}

		return $active_tokens;

	}

	/**
	 * Revoke all tokens for a user.
	 *
	 * @since 7.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of tokens revoked.
	 */
	public function revoke_user_tokens( $user_id ) {

		$encrypted_tokens = array();
		$revoked          = $this->credential_repository->mutate(
			$user_id,
			$this->site_policy->get_token_meta_key(),
			function ( array $current_tokens ) use ( &$encrypted_tokens ) {

				$encrypted_tokens = $current_tokens;

				return array();

			}
		);

		if ( ! $revoked ) {
			return 0;
		}

		$revoked_count = count( $encrypted_tokens );

		$this->delete_internal_token_cache( $user_id );
		$this->site_policy->remove_legacy_network_credentials( (int) $user_id );

		foreach ( array_keys( $encrypted_tokens ) as $token_hash ) {
			delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( (string) $token_hash ) );
		}

		// Log bulk revocation.
		if ( $revoked_count > 0 ) {
			$this->log_token_event(
				'tokens_bulk_revoked',
				$user_id,
				null,
				array(
					'count' => $revoked_count,
				)
			);
		}

		return $revoked_count;

	}

	// -------------------------------------------------------------------------
	// Primary token encryption.
	// -------------------------------------------------------------------------

	/**
	 * Get encryption key for a user.
	 *
	 * @since 7.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Encryption key.
	 */
	private function get_encryption_key( $user_id, $context = '' ) {

		// Use WordPress auth key as base + user-specific salt.
		$base_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$user_salt = wp_hash( 'mcp_token_salt_' . $user_id, 'auth' );
		$context   = is_string( $context ) ? $context : '';

		// Create a 32-byte key using PBKDF2-like approach.
		return hash( 'sha256', $base_key . $user_salt . $context, true );

	}

	/**
	 * Encrypt token data for storage.
	 *
	 * @since 7.0.0
	 *
	 * @param array  $token_data Token data to encrypt.
	 * @param int    $user_id    User ID for key derivation.
	 * @param string $context    Optional key-derivation context.
	 * @return string|false Encrypted data or false on failure.
	 */
	private function encrypt_token_data( $token_data, $user_id, $context = '' ) {

		if ( ! extension_loaded( 'openssl' ) ) {
			return false;
		}

		$key = $this->get_encryption_key( $user_id, $context );
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::ENCRYPTION_METHOD ) );

		if ( false === $iv ) {
			return false;
		}

		$serialized_data = wp_json_encode( $token_data );
		$encrypted       = openssl_encrypt( $serialized_data, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return false;
		}

		// Create HMAC for authentication.
		$hmac = hash_hmac( self::HASH_ALGORITHM, $iv . $encrypted, $key, true );

		// Return base64 encoded: hmac + iv + encrypted_data.
		return base64_encode( $hmac . $iv . $encrypted );  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- OAuth/JWT encoding.

	}

	/**
	 * Decrypt token data from storage.
	 *
	 * @since 7.0.0
	 *
	 * @param string $encrypted_data Encrypted data to decrypt.
	 * @param int    $user_id        User ID for key derivation.
	 * @param string $context        Optional key-derivation context.
	 * @return array|false Decrypted token data or false on failure.
	 */
	private function decrypt_token_data( $encrypted_data, $user_id, $context = '' ) {

		if ( ! extension_loaded( 'openssl' ) || ! is_string( $encrypted_data ) ) {
			return false;
		}

		$key  = $this->get_encryption_key( $user_id, $context );
		$data = base64_decode( $encrypted_data, true );  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- OAuth/JWT encoding.

		if ( false === $data ) {
			return false;
		}

		$hmac_length = hash( self::HASH_ALGORITHM, '', true );
		$hmac_length = strlen( $hmac_length );
		$iv_length   = openssl_cipher_iv_length( self::ENCRYPTION_METHOD );

		if ( strlen( $data ) < $hmac_length + $iv_length ) {
			return false;
		}

		// Extract components.
		$hmac      = substr( $data, 0, $hmac_length );
		$iv        = substr( $data, $hmac_length, $iv_length );
		$encrypted = substr( $data, $hmac_length + $iv_length );

		// Verify HMAC.
		$expected_hmac = hash_hmac( self::HASH_ALGORITHM, $iv . $encrypted, $key, true );
		if ( ! hash_equals( $hmac, $expected_hmac ) ) {
			return false; // Authentication failed.
		}

		// Decrypt data.
		$decrypted = openssl_decrypt( $encrypted, self::ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $decrypted ) {
			return false;
		}

		return json_decode( $decrypted, true );

	}

	/**
	 * Get encrypted user tokens from meta.
	 *
	 * @since 7.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Encrypted tokens array.
	 */
	private function get_encrypted_user_tokens( $user_id ) {

		return $this->credential_repository->get( $user_id, $this->site_policy->get_token_meta_key() );

	}

	/**
	 * Find encrypted token data by hash.
	 *
	 * @since 7.0.0
	 *
	 * @param string $token_hash Token hash to find.
	 * @return array|false Token data or false if not found.
	 */
	private function find_encrypted_token_data( $token_hash ) {

		// Get all users with MCP tokens (this could be optimized with a lookup table).
		global $wpdb;

		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$this->site_policy->get_token_meta_key()
			)
		);

		foreach ( $user_ids as $user_id ) {
			$encrypted_tokens = $this->get_encrypted_user_tokens( $user_id );

			if ( isset( $encrypted_tokens[ $token_hash ] ) ) {
				$token_data = $this->decrypt_token_data(
					$encrypted_tokens[ $token_hash ],
					$user_id,
					$this->site_policy->get_primary_encryption_context()
				);

				if ( $this->site_policy->is_token_for_current_site( $token_data ) ) {
					return $token_data;
				}
			}
		}

		return false;

	}

	/**
	 * Log token-related events for audit trail.
	 *
	 * @since 7.0.0
	 *
	 * @param string $event_type  Event type.
	 * @param int    $user_id     User ID.
	 * @param string $token_hash  Token hash (optional).
	 * @param array  $extra_data  Additional data (optional).
	 * @return void
	 */
	private function log_token_event( $event_type, $user_id, $token_hash = null, $extra_data = array() ) {

		$log_data = array(
			'event_type' => $event_type,
			'user_id'    => $user_id,
			'token_hash' => $token_hash,
			'timestamp'  => time(),
			'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown',
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown',
			'extra_data' => $extra_data,
		);

		// Use WordPress action for extensible logging.
		Dispatcher::action( 'automator_mcp_token_event', $log_data );

	}

	/**
	 * Clean up expired tokens from all users.
	 *
	 * @since 7.0.0
	 *
	 * @return int Number of tokens cleaned up.
	 */
	public function cleanup_expired_tokens() {

		global $wpdb;

		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$this->site_policy->get_token_meta_key()
			)
		);

		$cleaned_count = 0;
		foreach ( $user_ids as $user_id ) {
			$cleaned_count += $this->cleanup_user_token_records( $user_id );
		}

		return $cleaned_count;

	}

	/**
	 * Persistently remove expired and unreadable token records for one user.
	 *
	 * Credential cleanup is kept out of get_user_tokens() so ordinary reads
	 * cannot stale-write across a concurrent revocation.
	 *
	 * @since 7.4.1
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of records removed, or zero on persistence failure.
	 */
	private function cleanup_user_token_records( $user_id ) {

		$removed_hashes = array();
		$updated        = $this->credential_repository->mutate(
			$user_id,
			$this->site_policy->get_token_meta_key(),
			function ( array $encrypted_tokens ) use ( $user_id, &$removed_hashes ) {

				$active_tokens = $encrypted_tokens;

				foreach ( $encrypted_tokens as $token_hash => $encrypted_data ) {
					$token_data = $this->decrypt_token_data(
						$encrypted_data,
						$user_id,
						$this->site_policy->get_primary_encryption_context()
					);

					if (
						! $this->site_policy->is_token_for_current_site( $token_data )
						|| $token_data['expires_at'] <= time()
					) {
						unset( $active_tokens[ $token_hash ] );
						$removed_hashes[] = (string) $token_hash;
					}
				}

				return $active_tokens;

			}
		);

		if ( ! $updated ) {
			return 0;
		}

		foreach ( $removed_hashes as $token_hash ) {
			delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( $token_hash ) );
		}

		return count( $removed_hashes );

	}
}
