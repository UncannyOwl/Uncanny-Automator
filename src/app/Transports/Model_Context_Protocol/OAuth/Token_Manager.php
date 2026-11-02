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

	private const CLEANUP_CURSOR     = 'automator_mcp_token_credential_cleanup_cursor';
	private const CLEANUP_RETRY      = 'automator_mcp_token_credential_cleanup_retry';
	private const CLEANUP_LOCK       = 'automator_mcp_token_credential_cleanup_lock';
	private const CLEANUP_LIMIT      = 20;
	private const USER_CLEANUP_LIMIT = 20;

	private bool $cleanup_confirmed = false;

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
	 * Direct token-hash credential index.
	 *
	 * @var Credential_Index_Repository
	 */
	private Credential_Index_Repository $credential_index;

	/**
	 * Constructor.
	 *
	 * @param Token_Credential_Repository|null   $credential_repository       Credential storage boundary.
	 * @param Internal_Token_Configuration|null  $internal_token_configuration Strong-secret policy.
	 * @param Token_Site_Policy|null             $site_policy                 Current-site storage policy.
	 * @param Internal_Token_Cache_Cipher|null   $internal_cache_cipher       Internal-cache cipher.
	 * @param Internal_Token_Issuance_Lock|null  $issuance_lock               Internal-token issuance lock.
	 * @param Credential_Index_Repository|null    $credential_index            Direct credential index.
	 */
	public function __construct(
		?Token_Credential_Repository $credential_repository = null,
		?Internal_Token_Configuration $internal_token_configuration = null,
		?Token_Site_Policy $site_policy = null,
		?Internal_Token_Cache_Cipher $internal_cache_cipher = null,
		?Internal_Token_Issuance_Lock $issuance_lock = null,
		?Credential_Index_Repository $credential_index = null
	) {

		$this->credential_repository = null !== $credential_repository
			? $credential_repository
			: new Token_Credential_Repository();
		$this->site_policy           = $site_policy ?? new Token_Site_Policy();
		$configuration               = $internal_token_configuration ?? new Internal_Token_Configuration();
		$this->internal_cache_cipher = $internal_cache_cipher
			?? new Internal_Token_Cache_Cipher( $configuration, $this->site_policy );
		$this->issuance_lock         = $issuance_lock ?? new Internal_Token_Issuance_Lock();
		$this->credential_index      = $credential_index ?? new Credential_Index_Repository();

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

		$purpose = isset( $metadata['purpose'] ) && is_string( $metadata['purpose'] )
			? $metadata['purpose']
			: Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT;
		if ( ! in_array( $purpose, $this->get_supported_credential_purposes(), true ) ) {
			return false;
		}

		// Set expiry.
		$expires_in = $expires_in ? (int) $expires_in : self::DEFAULT_EXPIRY;
		$expires_at = time() + $expires_in;

		$token = self::TOKEN_PREFIX . wp_generate_uuid4() . '_' . wp_generate_password( 32, false );

		$audience = $this->site_policy->get_audience();
		$resource = $this->site_policy->get_resource();
		if ( isset( $metadata['resource'] ) && ( ! is_string( $metadata['resource'] ) || ! hash_equals( $resource, untrailingslashit( $metadata['resource'] ) ) ) ) {
			return false;
		}

		$client_id = isset( $metadata['client_id'] ) && is_string( $metadata['client_id'] )
			? $metadata['client_id']
			: 'uncanny-agent';
		if ( '' === trim( $client_id ) || strlen( $client_id ) > 2048 ) {
			return false;
		}
		$scopes = $this->normalize_credential_scopes( $scopes, $purpose );

		// Token data.
		$token_data = array(
			'user_id'            => $user_id,
			'scopes'             => $scopes,
			'created_at'         => time(),
			'expires_at'         => $expires_at,
			'name'               => $name ? $name : 'MCP Token',
			'last_used'          => null,
			'internal'           => ! empty( $metadata['internal'] ),
			'site_id'            => $this->site_policy->get_site_id(),
			'audience'           => $audience,
			'resource'           => $resource,
			'purpose'            => $purpose,
			'client_id'          => $client_id,
			'revoked_at'         => null,
			'refresh_family'     => isset( $metadata['refresh_family'] ) && is_string( $metadata['refresh_family'] ) ? $metadata['refresh_family'] : '',
			'refresh_generation' => isset( $metadata['refresh_generation'] ) ? (int) $metadata['refresh_generation'] : 0,
			// When issuance began: the grant's own time for OAuth tokens, so a token
			// minted after a revocation from an older grant is still covered.
			'issued_at'          => isset( $metadata['grant_issued_at'] ) && is_numeric( $metadata['grant_issued_at'] )
				? (float) $metadata['grant_issued_at']
				: microtime( true ),
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

		if ( ! $this->credential_index->put( $token_hash, $token_data ) ) {
			$this->credential_repository->mutate(
				$user_id,
				$this->site_policy->get_token_meta_key(),
				static function ( array $user_tokens ) use ( $token_hash ): array {
					unset( $user_tokens[ $token_hash ] );
					return $user_tokens;
				}
			);
			return false;
		}

		// A concurrent bulk revocation can remove the aggregate after this token's
		// mutation commits but before its lookup index is published. Never return a
		// bearer unless both storage layers still agree that it is active.
		$current_tokens = $this->credential_repository->get( $user_id, $this->site_policy->get_token_meta_key() );
		if ( ! array_key_exists( $token_hash, $current_tokens ) ) {
			$this->credential_index->delete( $token_hash );
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
		$scopes = $this->normalize_credential_scopes(
			$scopes,
			Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT
		);

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
					&& ! $this->is_covered_by_revocation_marker( (int) $user_id, $tokens[ $hash ] )
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
	 * Return every credential purpose this release can issue and validate.
	 *
	 * @since 7.6
	 * @return string[]
	 */
	private function get_supported_credential_purposes(): array {
		return array(
			Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT,
			Authenticated_Token_Context::PURPOSE_OAUTH_MCP,
		);
	}

	/**
	 * Normalize grants and migrate the former tools/umbrella scopes.
	 *
	 * The first-party Agent keeps its existing product access while MCP tool
	 * authorization becomes read/write. OAuth credentials never receive Agent
	 * REST permissions through this migration path.
	 *
	 * @param array  $scopes  Requested or legacy scopes.
	 * @param string $purpose Credential purpose.
	 * @return string[]
	 */
	private function normalize_credential_scopes( array $scopes, string $purpose ): array {
		$normalized = $this->normalize_scope_set( $scopes );
		$is_legacy  = in_array( Authenticated_Token_Context::SCOPE_LEGACY, $normalized, true )
			|| in_array( Authenticated_Token_Context::SCOPE_TOOLS, $normalized, true );

		$normalized = array_values(
			array_diff(
				$normalized,
				array(
					Authenticated_Token_Context::SCOPE_LEGACY,
					Authenticated_Token_Context::SCOPE_TOOLS,
				)
			)
		);

		if ( $is_legacy ) {
			$normalized[] = Authenticated_Token_Context::SCOPE_READ;
			$normalized[] = Authenticated_Token_Context::SCOPE_WRITE;
		}

		if ( in_array( Authenticated_Token_Context::SCOPE_WRITE, $normalized, true ) ) {
			$normalized[] = Authenticated_Token_Context::SCOPE_READ;
		}

		if ( Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT === $purpose ) {
			$normalized[] = Authenticated_Token_Context::SCOPE_AGENT_READ;
			$normalized[] = Authenticated_Token_Context::SCOPE_AGENT_WRITE;
			$allowed      = array(
				Authenticated_Token_Context::SCOPE_READ,
				Authenticated_Token_Context::SCOPE_WRITE,
				Authenticated_Token_Context::SCOPE_AGENT_READ,
				Authenticated_Token_Context::SCOPE_AGENT_WRITE,
				Authenticated_Token_Context::SCOPE_KEY_BINDING,
				Authenticated_Token_Context::SCOPE_OFFLINE_ACCESS,
			);
		} else {
			$allowed = array(
				Authenticated_Token_Context::SCOPE_READ,
				Authenticated_Token_Context::SCOPE_WRITE,
				Authenticated_Token_Context::SCOPE_OFFLINE_ACCESS,
			);
		}

		return $this->normalize_scope_set( array_values( array_intersect( $normalized, $allowed ) ) );
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
			$this->credential_index->delete( $revoked_hash );
			delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( $revoked_hash ) );
		}

		// If the preferred hash was absent from this user's aggregate, do nothing
		// with it. The readable cache hash is not authenticated when cache
		// decryption fails; using it alone could revoke another user's credential.
		// A genuinely orphaned index fails aggregate membership during validation
		// and removes itself there.

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

		if ( ! is_string( $token ) || ! Str::starts_with( $token, self::TOKEN_PREFIX ) ) {
			return false;
		}

		// Generate token hash.
		$token_hash = hash( 'sha256', $token );

		// Resolve from the direct index. A one-time fallback migrates production
		// Agent credentials issued before this index existed without reconnecting.
		$token_data = $this->credential_index->get( $token_hash );
		if ( null === $token_data ) {
			$token_data = $this->migrate_legacy_credential( $token_hash );
		}

		if ( ! is_array( $token_data ) ) {
			return false;
		}

		$user_id     = isset( $token_data['user_id'] ) && is_numeric( $token_data['user_id'] )
			? (int) $token_data['user_id']
			: 0;
		$user_tokens = $user_id > 0
			? $this->credential_repository->get( $user_id, $this->site_policy->get_token_meta_key() )
			: array();

		// The index is only a locator. The authenticated encrypted aggregate owns
		// both membership and every authorization claim. This makes a malformed or
		// externally modified option row unable to widen scope, lifetime, purpose,
		// client, or resource after it has located the owning user.
		if ( ! array_key_exists( $token_hash, $user_tokens ) ) {
			$this->credential_index->delete( $token_hash );
			return false;
		}

		$authoritative = $this->decrypt_token_data(
			$user_tokens[ $token_hash ],
			$user_id,
			$this->site_policy->get_primary_encryption_context()
		);
		if ( false === $authoritative && is_multisite() ) {
			$authoritative = $this->decrypt_token_data(
				$user_tokens[ $token_hash ],
				$user_id,
				$this->site_policy->get_legacy_encryption_context()
			);
		}
		$token_data = $this->normalize_legacy_agent_credential( $authoritative );
		if ( ! is_array( $token_data ) || (int) ( $token_data['user_id'] ?? 0 ) !== $user_id ) {
			return false;
		}

		$purpose = $token_data['purpose'] ?? null;
		if ( ! is_string( $purpose ) || ! in_array( $purpose, $this->get_supported_credential_purposes(), true ) ) {
			return false;
		}

		// The authenticated audience binds the credential to this WordPress site.
		// Its resource may use either REST permalink form because both resolve to
		// the same MCP route and WordPress can switch between them after issuance.
		if ( ! $this->site_policy->is_token_for_current_site( $token_data ) ) {
			return false;
		}

		$resource = isset( $token_data['resource'] ) && is_string( $token_data['resource'] )
			? untrailingslashit( $token_data['resource'] )
			: '';
		if ( ! $this->site_policy->is_resource_for_current_site( $resource ) ) {
			return false;
		}

		// OAuth-managed Agent access must also stop on client revocation or when
		// browser-issued credentials are disabled. Embedded credentials have no
		// refresh family and keep their existing independent lifecycle.
		if ( Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT === $purpose && ! empty( $token_data['refresh_family'] ) ) {
			$clients = new Server\OAuth_Client_Registry();
			$client  = $clients->resolve( (string) ( $token_data['client_id'] ?? '' ) );
			if ( ! Server\OAuth_Server_Configuration::is_browser_flow_available()
				|| ! ( new Server\OAuth_Server_Configuration() )->can_use_existing_credentials()
				|| ! is_array( $client ) || ! Server\Hosted_Agent_Access::allows_client( $client ) ) {
				return false;
			}
		}

		if ( ! empty( $token_data['revoked_at'] ) ) {
			return false;
		}

		if ( $this->is_covered_by_revocation_marker( $user_id, $token_data ) ) {
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
		if ( $this->credential_index->touch( $token_hash, $token_data['last_used'] ) ) {
			update_user_meta(
				$token_data['user_id'],
				$this->site_policy->get_token_last_used_meta_key( $token_hash ),
				$token_data['last_used']
			);
		}

		// Log token usage.
		$this->log_token_event( 'token_used', $token_data['user_id'], $token_hash );

		return $token_data;

	}

	/**
	 * Whether the user's revocation marker covers a credential. A credential
	 * issued at or before the marker stays refused even if cleanup did not reach
	 * it. Older records without an issue time fall back to their creation second.
	 */
	private function is_covered_by_revocation_marker( int $user_id, array $token_data ): bool {
		$issued_at = is_numeric( $token_data['issued_at'] ?? null )
			? (float) $token_data['issued_at']
			: ( is_numeric( $token_data['created_at'] ?? null ) ? (float) $token_data['created_at'] : null );

		return ( new Server\OAuth_User_Revocation_Marker() )->covers( $user_id, $issued_at );
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

		return new Authenticated_Token_Context(
			$user,
			$scopes,
			$token_data['purpose'],
			isset( $token_data['resource'] ) && is_string( $token_data['resource'] ) ? $token_data['resource'] : '',
			isset( $token_data['client_id'] ) && is_string( $token_data['client_id'] ) ? $token_data['client_id'] : ''
		);

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
		$token_data = $this->credential_index->get( $token_hash );
		if ( null === $token_data ) {
			$token_data = $this->migrate_legacy_credential( $token_hash );
		}

		if ( ! is_array( $token_data ) ) {
			return false;
		}

		return $this->revoke_token_by_hash( $token_data['user_id'], $token_hash );

	}

	/**
	 * Revoke one token by its non-secret storage hash.
	 *
	 * Hash-based revocation uses the same serialized mutation boundary as
	 * bearer-based revocation, without needing the bearer value.
	 *
	 * @since 7.4.1
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $token_hash SHA-256 token hash.
	 * @return bool True on success, false when missing or persistence fails.
	 */
	public function revoke_token_by_hash( $user_id, $token_hash ) {
		return $this->revoke_token_hash_record( $user_id, $token_hash );
	}

	/**
	 * Revoke one token inside the serialized credential mutation boundary.
	 *
	 * @param int    $user_id       Credential owner.
	 * @param string $token_hash    SHA-256 token hash.
	 * @return bool Whether revocation completed.
	 */
	private function revoke_token_hash_record( $user_id, $token_hash ) {

		if ( ! is_string( $token_hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $token_hash ) ) {
			return false;
		}

		// Remove from encrypted user meta through the shared mutation boundary.
		$removed          = false;
		$aggregate_hashes = array();
		$updated          = $this->credential_repository->mutate(
			$user_id,
			$this->site_policy->get_token_meta_key(),
			function ( array $user_tokens ) use ( $token_hash, &$removed, &$aggregate_hashes ) {
				$aggregate_hashes = array_keys( $user_tokens );

				if ( isset( $user_tokens[ $token_hash ] ) ) {
					unset( $user_tokens[ $token_hash ] );
					$removed = true;
				}

				return $user_tokens;

			}
		);

		if ( ! $updated ) {
			// If a concurrent bulk revocation removed the aggregate after our
			// snapshot, fail closed by retiring every index entry from that
			// snapshot. If the aggregate still exists, preserve all entries so a
			// transient persistence failure cannot revoke a valid credential.
			if (
				! empty( $aggregate_hashes )
				&& empty( $this->credential_repository->get( $user_id, $this->site_policy->get_token_meta_key() ) )
			) {
				foreach ( $aggregate_hashes as $aggregate_hash ) {
					$this->credential_index->delete( (string) $aggregate_hash );
				}
			}

			return false;
		}

		if ( ! $removed ) {
			return false;
		}

		if ( ! $this->credential_index->delete( $token_hash ) ) {
			return false;
		}

		delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( $token_hash ) );

		$this->log_token_event( 'token_revoked', $user_id, $token_hash );

		return true;

	}

	/**
	 * Inspect an OAuth bearer for cancellation, including after expiry.
	 * This does not authenticate a request or update token usage.
	 */
	public function inspect_oauth_token_for_revocation( string $token ): ?array {
		$hash  = hash( 'sha256', $token );
		$index = $this->credential_index->get( $hash );

		if ( ! is_array( $index ) || empty( $index['user_id'] ) ) {
			return null;
		}

		$user_id = (int) $index['user_id'];
		$tokens  = $this->credential_repository->get( $user_id, $this->site_policy->get_token_meta_key() );

		if ( ! isset( $tokens[ $hash ] ) ) {
			return null;
		}

		$data = $this->decrypt_token_data( $tokens[ $hash ], $user_id, $this->site_policy->get_primary_encryption_context() );

		if (
			! is_array( $data )
			|| ! in_array( $data['purpose'] ?? '', array( Authenticated_Token_Context::PURPOSE_OAUTH_MCP, Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT ), true )
			|| ( empty( $data['refresh_family'] ) && Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT === ( $data['purpose'] ?? '' ) )
			|| ! $this->site_policy->is_token_for_current_site( $data )
			|| ! $this->site_policy->is_resource_for_current_site( (string) ( $data['resource'] ?? '' ) )
		) {
			return null;
		}

		return $data;
	}

	/** Report persistence failure separately from a family with no access tokens. */
	public function revoke_oauth_family_checked( int $user_id, string $family_id ): bool {
		return null !== $this->remove_oauth_family_generations( $user_id, $family_id, PHP_INT_MAX );
	}

	/**
	 * Revoke every OAuth access token belonging to a replayed refresh family.
	 */
	public function revoke_oauth_family( int $user_id, string $family_id ): int {
		return $this->revoke_oauth_family_generations_before( $user_id, $family_id, PHP_INT_MAX );
	}

	/**
	 * Revoke the access tokens a refresh rotation has superseded.
	 *
	 * After a rotation only the newest generation's access token is meant to
	 * be live. Retiring the older ones here keeps one access credential per
	 * family instead of one per refresh until the daily cleanup.
	 *
	 * @param int    $user_id    Credential owner.
	 * @param string $family_id  Refresh family.
	 * @param int    $generation Generations below this value are revoked.
	 * @return int Number of access tokens revoked.
	 */
	public function revoke_oauth_family_generations_before( int $user_id, string $family_id, int $generation ): int {
		return $this->remove_oauth_family_generations( $user_id, $family_id, $generation ) ?? 0;
	}

	/** Null means storage failed; zero means there was nothing left to remove. */
	private function remove_oauth_family_generations( int $user_id, string $family_id, int $generation ): ?int {
		if ( '' === $family_id ) {
			return 0;
		}

		$removed_hashes = array();
		$updated        = $this->credential_repository->mutate(
			$user_id,
			$this->site_policy->get_token_meta_key(),
			function ( array $encrypted_tokens ) use ( $user_id, $family_id, $generation, &$removed_hashes ) {
				foreach ( $encrypted_tokens as $token_hash => $encrypted_data ) {
					$token_data = $this->decrypt_token_data(
						$encrypted_data,
						$user_id,
						$this->site_policy->get_primary_encryption_context()
					);
					if (
						is_array( $token_data )
						&& in_array( $token_data['purpose'] ?? '', array( Authenticated_Token_Context::PURPOSE_OAUTH_MCP, Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT ), true )
						&& is_string( $token_data['refresh_family'] ?? null )
						&& hash_equals( $family_id, $token_data['refresh_family'] )
						&& (int) ( $token_data['refresh_generation'] ?? 0 ) < $generation
					) {
						// Keep the aggregate hashes until every index deletion succeeds.
						// A failed deletion must remain discoverable by the same retry.
						if ( ! $this->credential_index->delete( (string) $token_hash ) ) {
							return false;
						}
						unset( $encrypted_tokens[ $token_hash ] );
						$removed_hashes[] = (string) $token_hash;
					}
				}

				return $encrypted_tokens;
			}
		);

		if ( ! $updated ) {
			return null;
		}

		foreach ( $removed_hashes as $token_hash ) {
			delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( $token_hash ) );
		}

		return count( $removed_hashes );
	}

	/**
	 * Check both access-token stores before retiring an OAuth client.
	 *
	 * The caller holds the OAuth client lifecycle lock. Read raw database rows:
	 * cached reads and repository getters cannot distinguish corruption from
	 * absence. Aggregate-only credentials can survive failed index publication.
	 *
	 * @param string $client_id Exact OAuth client identifier.
	 * @return bool|null True when live, false when absent, null when unproven.
	 */
	public function has_live_client_tokens( string $client_id ): ?bool {
		$users = $this->get_active_client_user_ids( $client_id );

		return null === $users ? null : array() !== $users;
	}

	/**
	 * Return the users represented by live OAuth access grants for this client.
	 *
	 * @param string $client_id Exact OAuth client identifier.
	 * @return int[]|null Distinct grant owners, or null when the scan is unproven.
	 */
	public function get_active_client_user_ids( string $client_id ): ?array {
		$users = $this->get_active_client_users( array( $client_id ) );

		return null === $users ? null : $users[ $client_id ];
	}

	/**
	 * Scan once for the live access-grant owners of the requested clients.
	 *
	 * @param string[] $client_ids Exact client identifiers.
	 * @return array|null Client ID to distinct user IDs, or null when unproven.
	 */
	public function get_active_client_users( array $client_ids ): ?array {
		global $wpdb;

		$users = array();
		foreach ( $client_ids as $client_id ) {
			if ( ! is_string( $client_id ) || '' === $client_id || strlen( $client_id ) > 2048 ) {
				return null;
			}
			$users[ $client_id ] = array();
		}
		if ( array() === $users ) {
			return array();
		}

		$limit = 500;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh bounded retirement evidence; live credential state, must not be cached.
		$aggregates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s ORDER BY umeta_id ASC LIMIT %d",
				$this->site_policy->get_token_meta_key(),
				$limit + 1
			)
		);
		if ( ! is_array( $aggregates ) || '' !== $wpdb->last_error || count( $aggregates ) > $limit ) {
			return null;
		}

		$unknown = false;
		$scanned = 0;
		foreach ( $aggregates as $aggregate ) {
			$tokens = automator_safe_unserialize( $aggregate->meta_value );
			if ( ! is_array( $tokens ) || (int) $aggregate->user_id <= 0 ) {
				$unknown = true;
				continue;
			}
			foreach ( $tokens as $encrypted ) {
				if ( ++$scanned > 2000 ) {
					return null;
				}
				$record = $this->decrypt_token_data( $encrypted, (int) $aggregate->user_id, $this->site_policy->get_primary_encryption_context() );
				if ( false === $record && is_multisite() ) {
					$record = $this->decrypt_token_data( $encrypted, (int) $aggregate->user_id, $this->site_policy->get_legacy_encryption_context() );
				}
				foreach ( array_keys( $users ) as $client_id ) {
					$live = $this->is_live_client_token_record( $record, (string) $client_id );
					if ( true === $live ) {
						if ( (int) $record['user_id'] !== (int) $aggregate->user_id ) {
							$unknown = true;
						} else {
							$users[ $client_id ][ (int) $record['user_id'] ] = (int) $record['user_id'];
						}
					}
					$unknown = $unknown || null === $live;
				}
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Includes index-only credentials; prefix scan of the options table, live state, must not be cached.
		$indexed = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d",
				$wpdb->esc_like( 'automator_mcp_credential_v2_' ) . '%',
				$limit + 1
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $indexed ) || '' !== $wpdb->last_error || count( $indexed ) > $limit ) {
			return null;
		}
		foreach ( $indexed as $serialized ) {
			$record = automator_safe_unserialize( $serialized );
			foreach ( array_keys( $users ) as $client_id ) {
				$live = $this->is_live_client_token_record( $record, (string) $client_id );
				if ( true === $live ) {
					$users[ $client_id ][ (int) $record['user_id'] ] = (int) $record['user_id'];
				}
				$unknown = $unknown || null === $live;
			}
		}

		return $unknown ? null : array_map( 'array_values', $users );
	}

	/**
	 * Classify one stored credential without repairing or deleting evidence.
	 *
	 * @param mixed  $record Decrypted or indexed credential.
	 * @param string $client_id Client being retired.
	 * @return bool|null Live match, safe non-match, or unreadable evidence.
	 */
	private function is_live_client_token_record( $record, string $client_id ): ?bool {
		if ( ! is_array( $record ) ) {
			return null;
		}
		if ( is_string( $record['client_id'] ?? null ) && '' !== $record['client_id'] && ! hash_equals( $client_id, $record['client_id'] ) ) {
			// Another client's grant says nothing about this one, whatever its purpose.
			return false;
		}
		if ( ! is_numeric( $record['expires_at'] ?? null ) ) {
			return null;
		}
		if ( (float) $record['expires_at'] <= time() || ! empty( $record['revoked_at'] ) ) {
			return false;
		}
		if ( ! array_key_exists( 'purpose', $record ) ) {
			return is_array( $this->normalize_legacy_agent_credential( $record ) ) ? false : null;
		}
		if ( ! in_array( $record['purpose'], $this->get_supported_credential_purposes(), true ) ) {
			return null;
		}
		if ( ! in_array( $record['purpose'], array( Authenticated_Token_Context::PURPOSE_OAUTH_MCP, Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT ), true ) ) {
			return false;
		}
		// Ordinary Agent credentials do not represent an approval for this OAuth
		// client. Hosted OAuth credentials carry the exact registered client ID.
		if ( Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT === $record['purpose'] && ( ! is_string( $record['client_id'] ?? null ) || ! hash_equals( $client_id, $record['client_id'] ) ) ) {
			return false;
		}
		if ( ! is_string( $record['client_id'] ?? null ) || ! is_numeric( $record['user_id'] ?? null ) || (int) $record['user_id'] <= 0 || ! $this->is_current_or_legacy_site_token( $record ) ) {
			return null;
		}

		return hash_equals( $client_id, $record['client_id'] );
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
			if ( false === $token_data && is_multisite() ) {
				$token_data = $this->decrypt_token_data(
					$encrypted_data,
					$user_id,
					$this->site_policy->get_legacy_encryption_context()
				);
			}

			if ( false === $token_data || ! $this->is_current_or_legacy_site_token( $token_data ) ) {
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
					'purpose'    => isset( $token_data['purpose'] ) && is_string( $token_data['purpose'] )
						? $token_data['purpose']
						: Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT,
				);
			} else {
				// Expired tokens are omitted here and removed by explicit cleanup.
				delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( (string) $token_hash ) );
			}
		}

		return $active_tokens;

	}

	/**
	 * Whether the user has a stored credential aggregate on this site.
	 *
	 * Any stored row counts, readable or not, so the answer never rests on a
	 * decryption or a cache.
	 *
	 * @since 8.0.0
	 *
	 * @return bool|null Null when the aggregate could not be read.
	 */
	public function has_user_credentials( int $user_id ): ?bool {

		return $this->credential_repository->has_aggregate( $user_id, $this->site_policy->get_token_meta_key() );

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
			$this->credential_index->delete( (string) $token_hash );
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
	private function get_encryption_keys( $user_id, $context = '' ) {

		$context = is_string( $context ) ? $context : '';
		$master  = hash_hkdf(
			self::HASH_ALGORITHM,
			wp_salt( 'auth' ),
			32,
			'uncanny-automator:mcp-token:v2:' . $context,
			wp_hash( 'mcp_token_salt_' . $user_id, 'auth' )
		);

		return array(
			'encryption'     => hash_hkdf( self::HASH_ALGORITHM, $master, 32, 'encryption' ),
			'authentication' => hash_hkdf( self::HASH_ALGORITHM, $master, 32, 'authentication' ),
		);

	}

	/**
	 * Reproduce the pre-7.6 key so existing encrypted credentials remain valid.
	 */
	private function get_legacy_encryption_key( $user_id, $context = '' ) {

		$base_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$user_salt = wp_hash( 'mcp_token_salt_' . $user_id, 'auth' );
		$context   = is_string( $context ) ? $context : '';

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

		$keys = $this->get_encryption_keys( $user_id, $context );
		$iv   = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::ENCRYPTION_METHOD ) );

		if ( false === $iv ) {
			return false;
		}

		$serialized_data = wp_json_encode( $token_data );
		$encrypted       = openssl_encrypt( $serialized_data, self::ENCRYPTION_METHOD, $keys['encryption'], OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return false;
		}

		// Create HMAC for authentication.
		$hmac = hash_hmac( self::HASH_ALGORITHM, $iv . $encrypted, $keys['authentication'], true );

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

		$keys      = $this->get_encryption_keys( $user_id, $context );
		$decrypted = $this->decrypt_with_keys( $hmac, $iv, $encrypted, $keys['encryption'], $keys['authentication'] );
		if ( false !== $decrypted ) {
			return $decrypted;
		}

		// Existing sites may hold the original single-key envelope. Read it for
		// compatibility; every newly written credential uses separated HKDF keys.
		$legacy_key = $this->get_legacy_encryption_key( $user_id, $context );

		return $this->decrypt_with_keys( $hmac, $iv, $encrypted, $legacy_key, $legacy_key );

	}

	/**
	 * Authenticate and decrypt one parsed credential envelope.
	 *
	 * @return array|false
	 */
	private function decrypt_with_keys( string $hmac, string $iv, string $encrypted, string $encryption_key, string $authentication_key ) {

		$expected_hmac = hash_hmac( self::HASH_ALGORITHM, $iv . $encrypted, $authentication_key, true );
		if ( ! hash_equals( $hmac, $expected_hmac ) ) {
			return false;
		}

		$decrypted = openssl_decrypt( $encrypted, self::ENCRYPTION_METHOD, $encryption_key, OPENSSL_RAW_DATA, $iv );
		if ( false === $decrypted ) {
			return false;
		}

		$decoded = json_decode( $decrypted, true );

		return is_array( $decoded ) ? $decoded : false;

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scans credential usermeta rows by key; live credential state, must not be cached.
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

				if ( false === $token_data && is_multisite() ) {
					$token_data = $this->decrypt_token_data(
						$encrypted_tokens[ $token_hash ],
						$user_id,
						$this->site_policy->get_legacy_encryption_context()
					);
				}

				if ( $this->is_current_or_legacy_site_token( $token_data ) ) {
					return $token_data;
				}
			}
		}

		return false;

	}

	/**
	 * Migrate one pre-index production credential on first successful use.
	 *
	 * The encrypted aggregate remains as rollback evidence. The new index owns
	 * validation after this point, preserving the bearer value and token hash.
	 *
	 * @return array|false
	 */
	private function migrate_legacy_credential( string $token_hash ) {
		$token_data = $this->find_encrypted_token_data( $token_hash );
		if ( ! is_array( $token_data ) || array_key_exists( 'purpose', $token_data ) ) {
			return false;
		}

		$token_data = $this->normalize_legacy_agent_credential( $token_data );
		if ( ! is_array( $token_data ) ) {
			return false;
		}

		return $this->credential_index->put( $token_hash, $token_data ) ? $token_data : false;
	}

	/**
	 * Normalize only the exact pre-purpose Agent shape.
	 *
	 * Modern records—including records with explicit null identity fields—must
	 * never be restamped as first-party credentials.
	 *
	 * @param mixed $token_data Decrypted credential data.
	 * @return array|false
	 */
	private function normalize_legacy_agent_credential( $token_data ) {
		if ( ! is_array( $token_data ) ) {
			return false;
		}

		if ( array_key_exists( 'purpose', $token_data ) ) {
			return Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT === $token_data['purpose']
				|| in_array( $token_data['purpose'], $this->get_supported_credential_purposes(), true )
				? $token_data
				: false;
		}

		if ( ! $this->is_strict_legacy_site_token( $token_data ) && ! $this->is_legacy_site_bound_token( $token_data ) ) {
			return false;
		}

		$token_data['purpose']    = Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT;
		$token_data['client_id']  = 'uncanny-agent';
		$token_data['site_id']    = $this->site_policy->get_site_id();
		$token_data['resource']   = $this->site_policy->get_resource();
		$token_data['audience']   = $this->site_policy->get_audience();
		$token_data['revoked_at'] = null;
		$token_data['scopes']     = $this->normalize_credential_scopes(
			isset( $token_data['scopes'] ) && is_array( $token_data['scopes'] ) ? $token_data['scopes'] : array(),
			Authenticated_Token_Context::PURPOSE_FIRST_PARTY_AGENT
		);

		return $token_data;
	}

	/**
	 * Accept the canonical resource or the former site origin during migration.
	 */
	private function is_current_or_legacy_site_token( $token_data ): bool {
		if ( $this->site_policy->is_token_for_current_site( $token_data ) ) {
			return true;
		}

		if ( ! is_array( $token_data ) ) {
			return false;
		}

		if ( ! is_multisite() && $this->is_strict_legacy_site_token( $token_data ) ) {
			return true;
		}

		return isset( $token_data['site_id'], $token_data['audience'] )
			&& (int) $token_data['site_id'] === $this->site_policy->get_site_id()
			&& is_string( $token_data['audience'] )
			&& hash_equals( $this->site_policy->get_legacy_audience(), $token_data['audience'] );
	}

	/**
	 * Identify the original single-site token shape by absent—not null—keys.
	 */
	private function is_strict_legacy_site_token( array $token_data ): bool {
		return ! array_key_exists( 'purpose', $token_data )
			&& ! array_key_exists( 'client_id', $token_data )
			&& ! array_key_exists( 'resource', $token_data )
			&& ! array_key_exists( 'site_id', $token_data )
			&& ! array_key_exists( 'audience', $token_data );
	}

	/**
	 * Identify the pre-purpose multisite shape bound to the former audience.
	 */
	private function is_legacy_site_bound_token( array $token_data ): bool {
		return ! array_key_exists( 'purpose', $token_data )
			&& ! array_key_exists( 'client_id', $token_data )
			&& ! array_key_exists( 'resource', $token_data )
			&& isset( $token_data['site_id'], $token_data['audience'] )
			&& (int) $token_data['site_id'] === $this->site_policy->get_site_id()
			&& is_string( $token_data['audience'] )
			&& hash_equals( $this->site_policy->get_legacy_audience(), $token_data['audience'] );
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
	 * Clean one page of expired user credentials.
	 *
	 * @since 7.0.0
	 *
	 * @return int Number of tokens cleaned up.
	 */
	public function cleanup_expired_tokens() {

		global $wpdb;
		$this->cleanup_confirmed = false;
		$lock                    = new Atomic_Option_Lock();
		$owner                   = $lock->acquire( self::CLEANUP_LOCK, 60, 0 );
		if ( false === $owner ) {
			return 0;
		}

		try {
			// The lock is database-backed; discard option cache from before it.
			wp_cache_delete( self::CLEANUP_CURSOR, 'options' );
			wp_cache_delete( self::CLEANUP_RETRY, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$cursor   = get_option( self::CLEANUP_CURSOR, 0 );
			$retry    = ! empty( $cursor ) && (bool) get_option( self::CLEANUP_RETRY, false );
			$deadline = microtime( true ) + 1.0;
			if ( is_array( $cursor ) && isset( $cursor['row'], $cursor['user'], $cursor['next'], $cursor['remaining'] )
				&& is_string( $cursor['next'] ) ) {
				$batch = $this->cleanup_user_token_records( (int) $cursor['user'], $cursor['next'], (int) $cursor['remaining'], $deadline );
				if ( null === $batch ) {
					$this->cleanup_confirmed = $this->write_cleanup_option( self::CLEANUP_RETRY, 1 );
					return 0;
				}
				$retry                   = $retry || $batch['restarted'];
				$next_cursor             = null === $batch['next']
					? (int) $cursor['row']
					: array(
						'row'       => (int) $cursor['row'],
						'user'      => (int) $cursor['user'],
						'next'      => $batch['next'],
						'remaining' => $batch['remaining'],
					);
				$this->cleanup_confirmed = $this->save_cleanup_progress( $next_cursor, $retry );
				return $batch['removed'];
			}
			$cursor = is_numeric( $cursor ) ? max( 0, (int) $cursor ) : 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scans credential usermeta rows by key; live credential state, must not be cached.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT umeta_id, user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND umeta_id > %d ORDER BY umeta_id ASC LIMIT %d",
					$this->site_policy->get_token_meta_key(),
					$cursor,
					self::CLEANUP_LIMIT
				)
			);
			if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
				$this->cleanup_confirmed = $this->write_cleanup_option( self::CLEANUP_RETRY, 1 );
				return 0;
			}

			$cleaned_count = 0;
			$processed     = 0;
			$seen          = array();
			$continuation  = false;
			foreach ( $rows as $row ) {
				if ( 0 < $processed && microtime( true ) >= $deadline ) {
					break;
				}
				$cursor = (int) $row->umeta_id;
				++$processed;
				$user_id = (int) $row->user_id;
				if ( isset( $seen[ $user_id ] ) ) {
					continue;
				}
				$seen[ $user_id ] = true;
				$batch            = $this->cleanup_user_token_records( $user_id, null, null, $deadline );
				if ( null === $batch ) {
					$retry = true;
				} else {
					$cleaned_count += $batch['removed'];
					if ( null !== $batch['next'] ) {
						$cursor       = array(
							'row'       => $cursor,
							'user'      => $user_id,
							'next'      => $batch['next'],
							'remaining' => $batch['remaining'],
						);
						$continuation = true;
						break;
					}
				}
			}

			$finished                = ! $continuation && count( $rows ) === $processed && count( $rows ) < self::CLEANUP_LIMIT;
			$this->cleanup_confirmed = $this->save_cleanup_progress( $finished ? 0 : $cursor, $retry );
			return $cleaned_count;
		} finally {
			$lock->release( self::CLEANUP_LOCK, $owner );
		}

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
	 * @param string|null $start Next token hash to inspect, or null for the beginning.
	 * @param int|null $remaining Records left in the original user snapshot.
	 * @param float $deadline Time after which the page should stop.
	 * @return array|null Removed count and next token hash, or null on persistence failure.
	 */
	private function cleanup_user_token_records( $user_id, ?string $start, ?int $remaining, float $deadline ): ?array {

		$removed_hashes = array();
		$next           = null;
		$restarted      = false;
		$updated        = $this->credential_repository->mutate(
			$user_id,
			$this->site_policy->get_token_meta_key(),
			function ( array $encrypted_tokens ) use ( $user_id, $start, $deadline, &$remaining, &$removed_hashes, &$next, &$restarted ) {

				$active_tokens = $encrypted_tokens;
				$restarted     = null !== $start && ! array_key_exists( $start, $encrypted_tokens );
				$started       = null === $start || $restarted;
				$processed     = 0;
				$remaining     = null === $remaining ? count( $encrypted_tokens ) : max( 0, $remaining );

				foreach ( $encrypted_tokens as $token_hash => $encrypted_data ) {
					if ( ! $started ) {
						if ( (string) $token_hash !== $start ) {
							continue;
						}
						$started = true;
					}
					if ( 0 === $remaining ) {
						break;
					}
					if ( self::USER_CLEANUP_LIMIT <= $processed || ( 0 < $processed && microtime( true ) >= $deadline ) ) {
						$next = (string) $token_hash;
						break;
					}
					++$processed;
					--$remaining;
					$token_data = $this->decrypt_token_data(
						$encrypted_data,
						$user_id,
						$this->site_policy->get_primary_encryption_context()
					);
					if ( false === $token_data && is_multisite() ) {
						$token_data = $this->decrypt_token_data(
							$encrypted_data,
							$user_id,
							$this->site_policy->get_legacy_encryption_context()
						);
					}

					if (
						! $this->is_current_or_legacy_site_token( $token_data )
						|| $token_data['expires_at'] <= time()
					) {
						// Keep the aggregate until the locator is confirmed absent, so a
						// failed index deletion can be retried after an interruption.
						if ( 1 === preg_match( '/^[a-f0-9]{64}$/D', (string) $token_hash )
							&& ! $this->credential_index->delete( (string) $token_hash ) ) {
							return false;
						}
						unset( $active_tokens[ $token_hash ] );
						$removed_hashes[] = (string) $token_hash;
					}
				}

				return $active_tokens;

			},
			0
		);

		if ( ! $updated ) {
			return null;
		}

		foreach ( $removed_hashes as $token_hash ) {
			delete_user_meta( $user_id, $this->site_policy->get_token_last_used_meta_key( $token_hash ) );
		}

		return array(
			'removed'   => count( $removed_hashes ),
			'next'      => $next,
			'remaining' => $remaining,
			'restarted' => $restarted,
		);

	}

	/**
	 * Preserve a retry if the checkpoint could not be persisted.
	 *
	 * @since 8.0.0
	 * @param int|array $cursor Next position in this site's cleanup.
	 * @param bool $retry Whether the sweep encountered a failed mutation.
	 */
	private function save_cleanup_progress( $cursor, bool $retry ): bool {
		if ( $retry && ! $this->write_cleanup_option( self::CLEANUP_RETRY, 1 ) ) {
			return false;
		}
		if ( ! $this->write_cleanup_option( self::CLEANUP_CURSOR, $cursor ) ) {
			return $this->write_cleanup_option( self::CLEANUP_RETRY, 1 );
		}
		return $retry || $this->write_cleanup_option( self::CLEANUP_RETRY, 0 );
	}

	/**
	 * Report whether the last cleanup page or its retry was durably recorded.
	 *
	 * @since 8.0.0
	 */
	public function cleanup_was_confirmed(): bool {
		return $this->cleanup_confirmed;
	}

	/**
	 * Distinguish an unchanged option from a failed write.
	 *
	 * @since 8.0.0
	 * @param string $name Site-local option name.
	 * @param mixed $value Expected option value.
	 */
	private function write_cleanup_option( string $name, $value ): bool {
		if ( update_option( $name, $value, false ) ) {
			return true;
		}
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return (string) maybe_serialize( get_option( $name, null ) ) === (string) maybe_serialize( $value );
	}
}
