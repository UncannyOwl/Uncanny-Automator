<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\OAuth;

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

	/**
	 * Generate a new Bearer token for a user.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param array  $scopes     Token scopes (optional).
	 * @param int    $expires_in Expiry time in seconds (optional).
	 * @param string $name       Token name for identification (optional).
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
		);

		// Generate token hash for storage key.
		$token_hash = hash( 'sha256', $token );

		// Store encrypted token data in user meta.
		$encrypted_data = $this->encrypt_token_data( $token_data, $user_id );
		if ( false === $encrypted_data ) {
			return false;
		}

		// Get existing tokens and add new one.
		$user_tokens                = $this->get_encrypted_user_tokens( $user_id );
		$user_tokens[ $token_hash ] = $encrypted_data;

		// Store updated tokens.
		if ( ! update_user_meta( $user_id, 'automator_mcp_tokens_encrypted', $user_tokens ) ) {
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
		$meta_key = 'automator_mcp_internal_token';
		$cached   = get_user_meta( $user_id, $meta_key, true );

		if ( is_array( $cached ) && ! empty( $cached['token'] ) && ! empty( $cached['expires_at'] ) ) {
			$hash = $cached['token_hash'] ?? hash( 'sha256', $cached['token'] );
			if ( $cached['expires_at'] > time() ) {
				$tokens = $this->get_user_tokens( $user_id );
				if ( isset( $tokens[ $hash ] ) && ! empty( $tokens[ $hash ]['internal'] ) ) {
					return $cached['token'];
				}
			}
			delete_user_meta( $user_id, $meta_key );
		}

		$result = $this->generate_token( $user_id, $scopes, $expires_in, $name, array( 'internal' => true ) );
		if ( empty( $result['token'] ) ) {
			return null;
		}

		update_user_meta(
			$user_id,
			$meta_key,
			array(
				'token'      => $result['token'],
				'expires_at' => $result['expires_at'],
				'token_hash' => hash( 'sha256', $result['token'] ),
			)
		);

		return $result['token'];
	}

	/**
	 * Validate a Bearer token.
	 *
	 * @since 7.0.0
	 *
	 * @param string $token Bearer token.
	 * @return array|false Token data on success, false on failure.
	 */
	public function validate_token( $token ) {
		if ( empty( $token ) || ! str_starts_with( $token, self::TOKEN_PREFIX ) ) {
			return false;
		}

		// Generate token hash.
		$token_hash = hash( 'sha256', $token );

		// Find token data by checking all users' encrypted tokens.
		$token_data = $this->find_encrypted_token_data( $token_hash );

		if ( false === $token_data ) {
			return false;
		}

		// Check expiry.
		if ( $token_data['expires_at'] <= time() ) {
			$this->revoke_token( $token );
			return false;
		}

		// Update last used.
		$token_data['last_used'] = time();
		$this->update_encrypted_token_data( $token_data['user_id'], $token_hash, $token_data );

		// Log token usage.
		$this->log_token_event( 'token_used', $token_data['user_id'], $token_hash );

		return $token_data;
	}

	/**
	 * Get WordPress user from Bearer token.
	 *
	 * @since 7.0.0
	 *
	 * @param string $token Bearer token.
	 * @return \WP_User|false User object on success, false on failure.
	 */
	public function get_user_from_token( $token ) {
		$token_data = $this->validate_token( $token );

		if ( ! $token_data ) {
			return false;
		}

		$user = get_user_by( 'id', $token_data['user_id'] );
		return $user ? $user : false;
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

		$user_id = $token_data['user_id'];

		// Remove from encrypted user meta.
		$user_tokens = $this->get_encrypted_user_tokens( $user_id );
		if ( isset( $user_tokens[ $token_hash ] ) ) {
			unset( $user_tokens[ $token_hash ] );
			update_user_meta( $user_id, 'automator_mcp_tokens_encrypted', $user_tokens );

			// Log token revocation.
			$this->log_token_event( 'token_revoked', $user_id, $token_hash );

			return true;
		}

		return false;
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
		$cleanup_needed   = false;

		foreach ( $encrypted_tokens as $token_hash => $encrypted_data ) {
			$token_data = $this->decrypt_token_data( $encrypted_data, $user_id );

			if ( false === $token_data ) {
				// Failed to decrypt - remove invalid token.
				unset( $encrypted_tokens[ $token_hash ] );
				$cleanup_needed = true;
				continue;
			}

			// Check if token is still active.
			if ( $token_data['expires_at'] > time() ) {
				$active_tokens[ $token_hash ] = array(
					'name'       => $token_data['name'],
					'created_at' => $token_data['created_at'],
					'expires_at' => $token_data['expires_at'],
					'last_used'  => $token_data['last_used'],
					'scopes'     => $token_data['scopes'],
					'is_active'  => true,
					'internal'   => ! empty( $token_data['internal'] ),
				);
			} else {
				// Clean up expired token.
				unset( $encrypted_tokens[ $token_hash ] );
				$cleanup_needed = true;
			}
		}

		// Update user meta if we cleaned up expired/invalid tokens.
		if ( $cleanup_needed ) {
			update_user_meta( $user_id, 'automator_mcp_tokens_encrypted', $encrypted_tokens );
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
		$encrypted_tokens = $this->get_encrypted_user_tokens( $user_id );
		$revoked_count    = count( $encrypted_tokens );

		// Clear encrypted user meta.
		delete_user_meta( $user_id, 'automator_mcp_tokens_encrypted' );
		delete_user_meta( $user_id, 'automator_mcp_internal_token' );

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

	/**
	 * Get encryption key for a user.
	 *
	 * @since 7.0.0
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Encryption key.
	 */
	private function get_encryption_key( $user_id ) {
		// Use WordPress auth key as base + user-specific salt.
		$base_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$user_salt = wp_hash( 'mcp_token_salt_' . $user_id, 'auth' );

		// Create a 32-byte key using PBKDF2-like approach.
		return hash( 'sha256', $base_key . $user_salt, true );
	}

	/**
	 * Encrypt token data for storage.
	 *
	 * @since 7.0.0
	 *
	 * @param array $token_data Token data to encrypt.
	 * @param int   $user_id    User ID for key derivation.
	 * @return string|false Encrypted data or false on failure.
	 */
	private function encrypt_token_data( $token_data, $user_id ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return false;
		}

		$key = $this->get_encryption_key( $user_id );
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
	 * @return array|false Decrypted token data or false on failure.
	 */
	private function decrypt_token_data( $encrypted_data, $user_id ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return false;
		}

		$key  = $this->get_encryption_key( $user_id );
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
		$tokens = get_user_meta( $user_id, 'automator_mcp_tokens_encrypted', true );
		return $tokens ? $tokens : array();
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
				'automator_mcp_tokens_encrypted'
			)
		);

		foreach ( $user_ids as $user_id ) {
			$encrypted_tokens = $this->get_encrypted_user_tokens( $user_id );

			if ( isset( $encrypted_tokens[ $token_hash ] ) ) {
				return $this->decrypt_token_data( $encrypted_tokens[ $token_hash ], $user_id );
			}
		}

		return false;
	}

	/**
	 * Update encrypted token data.
	 *
	 * @since 7.0.0
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $token_hash Token hash.
	 * @param array  $token_data Updated token data.
	 * @return bool True on success, false on failure.
	 */
	private function update_encrypted_token_data( $user_id, $token_hash, $token_data ) {
		$encrypted_tokens = $this->get_encrypted_user_tokens( $user_id );
		$encrypted_data   = $this->encrypt_token_data( $token_data, $user_id );

		if ( false === $encrypted_data ) {
			return false;
		}

		$encrypted_tokens[ $token_hash ] = $encrypted_data;
		return update_user_meta( $user_id, 'automator_mcp_tokens_encrypted', $encrypted_tokens );
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
		do_action( 'automator_mcp_token_event', $log_data );
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
				'automator_mcp_tokens_encrypted'
			)
		);

		$cleaned_count = 0;
		foreach ( $user_ids as $user_id ) {
			$before_count = count( $this->get_encrypted_user_tokens( $user_id ) );
			$this->get_user_tokens( $user_id ); // This will clean up expired tokens.
			$after_count    = count( $this->get_encrypted_user_tokens( $user_id ) );
			$cleaned_count += ( $before_count - $after_count );
		}

		return $cleaned_count;
	}
}
