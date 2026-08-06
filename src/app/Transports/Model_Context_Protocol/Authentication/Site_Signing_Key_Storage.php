<?php
/**
 * Encrypted WordPress site signing-key storage.
 *
 * @since 7.5.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication;

/**
 * Stores one Ed25519 key pair for each WordPress blog.
 */
class Site_Signing_Key_Storage {

	private const OPTION_NAME                = 'automator_mcp_site_signing_key';
	private const VERSION                    = 1;
	private const WRAPPING_KEY_VERSION       = 1;
	private const ENCRYPTION_METHOD          = 'aes-256-gcm';
	private const ENCRYPTION_CONTEXT         = 'automator_mcp_site_signing_key_v1';
	private const SECRET_FINGERPRINT_CONTEXT = 'automator_mcp_site_signing_key_secret_v1';
	private const NONCE_LENGTH               = 12;
	private const TAG_LENGTH                 = 16;

	/**
	 * Wrapping-secret configuration.
	 *
	 * @var Site_Signing_Key_Configuration
	 */
	private Site_Signing_Key_Configuration $configuration;

	/**
	 * Option getter.
	 *
	 * @var callable
	 */
	private $getter;

	/**
	 * Atomic option creator.
	 *
	 * @var callable
	 */
	private $creator;

	/**
	 * Option replacer.
	 *
	 * @var callable
	 */
	private $replacer;

	/**
	 * Blog ID callback.
	 *
	 * @var callable
	 */
	private $blog_id_provider;

	/**
	 * Random-byte callback.
	 *
	 * @var callable
	 */
	private $random_bytes_provider;

	/**
	 * Record ID callback.
	 *
	 * @var callable
	 */
	private $record_id_provider;

	/**
	 * Constructor.
	 *
	 * @param Site_Signing_Key_Configuration|null $configuration Wrapping-secret configuration.
	 * @param callable|null $getter Option getter.
	 * @param callable|null $creator Atomic option creator.
	 * @param callable|null $blog_id_provider Blog ID callback.
	 * @param callable|null $random_bytes_provider Random-byte callback.
	 * @param callable|null $record_id_provider Record ID callback.
	 * @param callable|null $replacer Option replacer.
	 */
	public function __construct(
		?Site_Signing_Key_Configuration $configuration = null,
		?callable $getter = null,
		?callable $creator = null,
		?callable $blog_id_provider = null,
		?callable $random_bytes_provider = null,
		?callable $record_id_provider = null,
		?callable $replacer = null
	) {
		$this->configuration         = $configuration ?? new Site_Signing_Key_Configuration();
		$this->getter                = $getter ?? 'automator_get_option';
		$this->creator               = $creator ?? 'automator_add_option';
		$this->blog_id_provider      = $blog_id_provider ?? 'get_current_blog_id';
		$this->random_bytes_provider = $random_bytes_provider ?? 'random_bytes';
		$this->record_id_provider    = $record_id_provider ?? 'wp_generate_uuid4';
		$this->replacer              = $replacer ?? array( $this, 'replace_option' );
	}

	/**
	 * Return whether a stored record exists.
	 *
	 * @return bool
	 */
	public function has_record(): bool {
		$missing = new \stdClass();

		return call_user_func( $this->getter, self::OPTION_NAME, $missing, false ) !== $missing;
	}

	/**
	 * Return whether storage can create the first key record.
	 *
	 * @return bool
	 */
	public function is_ready_to_create(): bool {
		return $this->has_crypto_support() && false !== $this->configuration->select_secret();
	}

	/**
	 * Return whether the stored wrapping secret changed.
	 *
	 * @return bool
	 */
	public function has_wrapping_secret_changed(): bool {
		$record = $this->read_record();

		if ( ! $this->has_valid_record_shape( $record ) || ! $this->has_crypto_support() ) {
			return false;
		}

		$key_material = $this->configuration->get_secret( $record['secret_source'] );

		if ( false === $key_material ) {
			// A missing source does not prove rotation. Keep the identity unchanged so the source can be restored.
			return false;
		}

		$current_fingerprint = $this->build_secret_fingerprint( $key_material );

		if ( hash_equals( $record['secret_fingerprint'], $current_fingerprint ) ) {
			return false;
		}

		// A valid decryption means the stored fingerprint changed, not the wrapping secret. Fail closed as tampering.
		$key_pair = $this->decrypt_record( $record, $key_material );

		if ( is_array( $key_pair ) ) {
			$this->clear_private_key( $key_pair['private_key'] );

			return false;
		}

		return true;
	}

	/**
	 * Load and decrypt the stored key pair.
	 *
	 * @return array{public_key:string,private_key:string}|false
	 */
	public function load( bool $skip_cache = false ) {
		$record = $this->read_record( $skip_cache );

		if ( ! $this->has_valid_record_shape( $record ) || ! $this->has_crypto_support() ) {
			return false;
		}

		$key_material = $this->configuration->get_secret( $record['secret_source'] );

		// The manager handles a confirmed secret rotation. Other unreadable records remain unchanged.
		if (
			false === $key_material
			|| ! hash_equals( $record['secret_fingerprint'], $this->build_secret_fingerprint( $key_material ) )
		) {
			return false;
		}

		return $this->decrypt_record( $record, $key_material );
	}

	/**
	 * Encrypt and atomically create one key record.
	 *
	 * @param string $public_key Ed25519 public key bytes.
	 * @param string $private_key Ed25519 private key bytes.
	 * @return bool
	 */
	public function create( string $public_key, string $private_key ): bool {
		$record = $this->build_record( $public_key, $private_key );

		return is_array( $record ) && (bool) call_user_func( $this->creator, self::OPTION_NAME, $record, false, false );
	}

	/**
	 * Replace one key record after its wrapping secret changes.
	 *
	 * @param string $public_key Ed25519 public key bytes.
	 * @param string $private_key Ed25519 private key bytes.
	 * @return bool
	 */
	public function replace( string $public_key, string $private_key ): bool {
		$current_record = $this->read_record( true );

		if ( ! is_array( $current_record ) ) {
			return false;
		}

		$record = $this->build_record( $public_key, $private_key );

		return is_array( $record )
			&& (bool) call_user_func( $this->replacer, self::OPTION_NAME, $current_record, $record, false );
	}

	/**
	 * Build one encrypted key record.
	 *
	 * @param string $public_key Ed25519 public key bytes.
	 * @param string $private_key Ed25519 private key bytes.
	 * @return array<string,mixed>|false
	 */
	private function build_record( string $public_key, string $private_key ) {
		if (
			! $this->has_crypto_support()
			|| SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $public_key )
			|| SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $private_key )
			|| ! hash_equals( $public_key, sodium_crypto_sign_publickey_from_secretkey( $private_key ) )
		) {
			return false;
		}

		$secret = $this->configuration->select_secret();

		if ( false === $secret ) {
			return false;
		}

		$record_id   = (string) call_user_func( $this->record_id_provider );
		$fingerprint = $this->encode_base64url( hash( 'sha256', $public_key, true ) );

		if ( ! $this->is_uuid( $record_id ) ) {
			return false;
		}

		$key = $this->derive_encryption_key( $secret['material'], $record_id, $secret['source'], $fingerprint );
		$aad = $this->build_authenticated_data( $record_id, $secret['source'], $fingerprint );

		if ( false === $key || false === $aad ) {
			return false;
		}

		try {
			$nonce = call_user_func( $this->random_bytes_provider, self::NONCE_LENGTH );
		} catch ( \Throwable $exception ) {
			return false;
		}

		if ( ! is_string( $nonce ) || self::NONCE_LENGTH !== strlen( $nonce ) ) {
			return false;
		}

		$tag        = '';
		$ciphertext = openssl_encrypt(
			$private_key,
			self::ENCRYPTION_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			$aad,
			self::TAG_LENGTH
		);

		if ( false === $ciphertext || self::TAG_LENGTH !== strlen( $tag ) ) {
			return false;
		}

		$record = array(
			'version'              => self::VERSION,
			'wrapping_key_version' => self::WRAPPING_KEY_VERSION,
			'record_id'            => $record_id,
			'secret_source'        => $secret['source'],
			'secret_fingerprint'   => $this->build_secret_fingerprint( $secret['material'] ),
			'public_key'           => $this->encode_base64url( $public_key ),
			'fingerprint'          => $fingerprint,
			'nonce'                => $this->encode_base64url( $nonce ),
			'tag'                  => $this->encode_base64url( $tag ),
			'ciphertext'           => $this->encode_base64url( $ciphertext ),
		);

		return $record;
	}

	/**
	 * Decrypt one validated key record with the supplied secret.
	 *
	 * @param array<string,mixed> $record Stored record.
	 * @param string $key_material Wrapping secret.
	 * @return array{public_key:string,private_key:string}|false
	 */
	private function decrypt_record( array $record, string $key_material ) {
		$public_key  = $this->decode_base64url( $record['public_key'] );
		$fingerprint = $this->decode_base64url( $record['fingerprint'] );
		$nonce       = $this->decode_base64url( $record['nonce'] );
		$tag         = $this->decode_base64url( $record['tag'] );
		$ciphertext  = $this->decode_base64url( $record['ciphertext'] );

		if (
			false === $public_key
			|| false === $fingerprint
			|| false === $nonce
			|| false === $tag
			|| false === $ciphertext
			|| SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $public_key )
			|| 32 !== strlen( $fingerprint )
			|| self::NONCE_LENGTH !== strlen( $nonce )
			|| self::TAG_LENGTH !== strlen( $tag )
			|| ! hash_equals( hash( 'sha256', $public_key, true ), $fingerprint )
		) {
			return false;
		}

		// Do not add secret_fingerprint to AAD. Decryption with the current secret distinguishes metadata tampering from secret rotation.
		$key = $this->derive_encryption_key( $key_material, $record['record_id'], $record['secret_source'], $record['fingerprint'] );
		$aad = $this->build_authenticated_data( $record['record_id'], $record['secret_source'], $record['fingerprint'] );

		if ( false === $key || false === $aad ) {
			return false;
		}

		$private_key = openssl_decrypt(
			$ciphertext,
			self::ENCRYPTION_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			$aad
		);

		if (
			false === $private_key
			|| SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $private_key )
			|| ! hash_equals( $public_key, sodium_crypto_sign_publickey_from_secretkey( $private_key ) )
		) {
			return false;
		}

		return array(
			'public_key'  => $public_key,
			'private_key' => $private_key,
		);
	}

	/**
	 * Read the raw stored record.
	 *
	 * @return array<string,mixed>|null
	 */
	private function read_record( bool $skip_cache = false ): ?array {
		$value = call_user_func( $this->getter, self::OPTION_NAME, null, $skip_cache );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Replace the expected record and reject a concurrent winner.
	 *
	 * @param string $option_name Option name.
	 * @param array<string,mixed> $current_record Expected record.
	 * @param array<string,mixed> $new_record Replacement record.
	 * @param bool $autoload Whether to autoload the option.
	 * @return bool
	 */
	private function replace_option( string $option_name, array $current_record, array $new_record, bool $autoload ): bool {
		global $wpdb;

		$table          = $wpdb->prefix . 'uap_options';
		$serialized_old = maybe_serialize( $current_record );
		$serialized_new = maybe_serialize( $new_record );
		$autoload_value = $autoload ? 'yes' : 'no';
		$updated        = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET option_value = %s, autoload = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from wpdb.
				$serialized_new,
				$autoload_value,
				$option_name,
				$serialized_old
			)
		);

		if ( 1 !== $updated ) {
			return false;
		}

		// Refresh the Automator option caches after the atomic database replacement.
		return automator_update_option( $option_name, $new_record, $autoload );
	}

	/**
	 * Validate the outer record shape.
	 *
	 * @param mixed $record Stored value.
	 * @return bool
	 */
	private function has_valid_record_shape( $record ): bool {
		if (
			! is_array( $record )
			|| self::VERSION !== ( $record['version'] ?? null )
			|| self::WRAPPING_KEY_VERSION !== ( $record['wrapping_key_version'] ?? null )
			|| ! isset( $record['record_id'] )
			|| ! is_string( $record['record_id'] )
			|| ! $this->is_uuid( $record['record_id'] )
			|| ! isset( $record['secret_source'] )
			|| ! is_string( $record['secret_source'] )
		) {
			return false;
		}

		foreach ( array( 'secret_fingerprint', 'public_key', 'fingerprint', 'nonce', 'tag', 'ciphertext' ) as $field ) {
			if ( ! isset( $record[ $field ] ) || ! is_string( $record[ $field ] ) || '' === $record[ $field ] ) {
				return false;
			}
		}

		$secret_fingerprint = $this->decode_base64url( $record['secret_fingerprint'] );

		if ( false === $secret_fingerprint || 32 !== strlen( $secret_fingerprint ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Build a stable fingerprint for one wrapping secret.
	 *
	 * The fingerprint detects secret rotation without storing the secret.
	 *
	 * @param string $key_material Wrapping secret.
	 * @return string
	 */
	private function build_secret_fingerprint( string $key_material ): string {
		return $this->encode_base64url(
			hash_hmac( 'sha256', self::SECRET_FINGERPRINT_CONTEXT, $key_material, true )
		);
	}

	/**
	 * Derive the site-bound encryption key.
	 *
	 * @param string $key_material External secret.
	 * @param string $record_id Record ID.
	 * @param string $secret_source Secret source name.
	 * @param string $fingerprint Public-key fingerprint.
	 * @return string|false
	 */
	private function derive_encryption_key( string $key_material, string $record_id, string $secret_source, string $fingerprint ) {
		$info = wp_json_encode(
			array(
				'context'       => self::ENCRYPTION_CONTEXT,
				'blog_id'       => $this->get_blog_id(),
				'record_id'     => $record_id,
				'secret_source' => $secret_source,
				'fingerprint'   => $fingerprint,
			)
		);

		if ( false === $info ) {
			return false;
		}

		return hash_hkdf( 'sha256', $key_material, 32, $info, 'uncanny-automator-mcp-site-signing-key-v1' );
	}

	/**
	 * Build authenticated storage metadata.
	 *
	 * @param string $record_id Record ID.
	 * @param string $secret_source Secret source name.
	 * @param string $fingerprint Public-key fingerprint.
	 * @return string|false
	 */
	private function build_authenticated_data( string $record_id, string $secret_source, string $fingerprint ) {
		return wp_json_encode(
			array(
				'context'              => self::ENCRYPTION_CONTEXT,
				'version'              => self::VERSION,
				'wrapping_key_version' => self::WRAPPING_KEY_VERSION,
				'blog_id'              => $this->get_blog_id(),
				'record_id'            => $record_id,
				'secret_source'        => $secret_source,
				'fingerprint'          => $fingerprint,
			)
		);
	}

	/**
	 * Return whether required cryptography is available.
	 *
	 * @return bool
	 */
	private function has_crypto_support(): bool {
		return extension_loaded( 'openssl' )
			&& defined( 'SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES' )
			&& defined( 'SODIUM_CRYPTO_SIGN_SECRETKEYBYTES' )
			&& function_exists( 'sodium_crypto_sign_publickey_from_secretkey' )
			&& in_array( self::ENCRYPTION_METHOD, openssl_get_cipher_methods(), true );
	}

	/**
	 * Get the current blog ID.
	 *
	 * @return int
	 */
	private function get_blog_id(): int {
		return absint( call_user_func( $this->blog_id_provider ) );
	}

	/**
	 * Clear one in-memory private-key copy.
	 *
	 * @param string $private_key Private-key bytes.
	 * @return void
	 */
	private function clear_private_key( string &$private_key ): void {
		if ( function_exists( 'sodium_memzero' ) ) {
			sodium_memzero( $private_key );
		}
	}

	/**
	 * Validate a UUID.
	 *
	 * @param string $value Candidate UUID.
	 * @return bool
	 */
	private function is_uuid( string $value ): bool {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value );
	}

	/**
	 * Encode binary data as unpadded base64url.
	 *
	 * @param string $value Binary value.
	 * @return string
	 */
	private function encode_base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Protocol encoding.
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

		if ( false === $decoded || ! hash_equals( $value, $this->encode_base64url( $decoded ) ) ) {
			return false;
		}

		return $decoded;
	}
}
