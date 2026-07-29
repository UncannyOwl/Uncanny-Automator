<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

// phpcs:disable PSR2.Methods.FunctionClosingBrace.SpacingBeforeClose -- Deliberate breathing room at function boundaries.

use WP_Error;

/**
 * Encrypts recoverable internal MCP tokens with site-bound AEAD.
 *
 * Key material comes only from strong external configuration. The derived key
 * and authenticated data bind every envelope to its user, site, audience,
 * version, and outer token hash.
 *
 * @since 7.4.1
 */
final class Internal_Token_Cache_Cipher {

	/**
	 * Stable error code for a missing AEAD implementation.
	 *
	 * @var string
	 */
	const CIPHER_UNAVAILABLE_ERROR_CODE = 'automator_mcp_internal_token_cipher_unavailable';

	/**
	 * Current encrypted cache envelope version.
	 *
	 * @var int
	 */
	const VERSION = 2;

	/**
	 * Previous CBC-HMAC cache envelope version.
	 *
	 * @var int
	 */
	const LEGACY_VERSION = 1;

	/**
	 * AEAD encryption method.
	 *
	 * @var string
	 */
	const ENCRYPTION_METHOD = 'aes-256-gcm';

	/**
	 * Domain separator for key derivation and authenticated data.
	 *
	 * @var string
	 */
	const ENCRYPTION_CONTEXT = 'mcp_internal_token_cache_v2';

	/**
	 * Domain separator used by the previous cache envelope.
	 *
	 * @var string
	 */
	const LEGACY_ENCRYPTION_CONTEXT = 'mcp_internal_token_cache_v1';

	/**
	 * GCM nonce length in bytes.
	 *
	 * @var int
	 */
	const NONCE_LENGTH = 12;

	/**
	 * GCM authentication tag length in bytes.
	 *
	 * @var int
	 */
	const TAG_LENGTH = 16;

	/**
	 * Strong external-secret configuration.
	 *
	 * @var Internal_Token_Configuration
	 */
	private $configuration;

	/**
	 * Site identity policy.
	 *
	 * @var Token_Site_Policy
	 */
	private $site_policy;

	/**
	 * Optional cipher-availability callback used by diagnostics tests.
	 *
	 * @var callable|null
	 */
	private $cipher_availability_checker;

	/**
	 * Constructor.
	 *
	 * @param Internal_Token_Configuration|null $configuration Strong-secret policy.
	 * @param Token_Site_Policy|null             $site_policy Site identity policy.
	 * @param callable|null                      $cipher_availability_checker Optional cipher-availability callback.
	 */
	public function __construct(
		?Internal_Token_Configuration $configuration = null,
		?Token_Site_Policy $site_policy = null,
		?callable $cipher_availability_checker = null
	) {

		$this->configuration               = $configuration ?? new Internal_Token_Configuration();
		$this->site_policy                 = $site_policy ?? new Token_Site_Policy();
		$this->cipher_availability_checker = $cipher_availability_checker;

	}

	/**
	 * Determine whether safe external key material is available.
	 *
	 * @return bool Whether recoverable cache encryption is configured.
	 */
	public function is_ready(): bool {

		return $this->configuration->is_ready() && $this->is_cipher_available();

	}

	/**
	 * Return the actionable configuration error when encryption is unsafe.
	 *
	 * @return WP_Error|null Configuration error or null when ready.
	 */
	public function get_configuration_error(): ?WP_Error {

		if ( ! $this->configuration->is_ready() ) {
			return $this->configuration->get_error();
		}

		if ( ! $this->is_cipher_available() ) {
			return new WP_Error(
				self::CIPHER_UNAVAILABLE_ERROR_CODE,
				esc_html_x(
					'Uncanny Agent cannot encrypt its internal token because the OpenSSL AES-256-GCM cipher is unavailable.',
					'MCP internal token configuration error',
					'uncanny-automator'
				),
				array( 'status' => 500 )
			);
		}

		return null;

	}

	/**
	 * Encrypt a recoverable internal token using an AEAD envelope.
	 *
	 * @param array  $cache Normalized internal token cache.
	 * @param int    $user_id WordPress user ID.
	 * @param int    $version Cache envelope version.
	 * @param string $token_hash SHA-256 token hash from the outer envelope.
	 * @return string|false Encoded AEAD envelope or false on failure.
	 */
	public function encrypt( array $cache, int $user_id, int $version, string $token_hash ) {

		if ( ! $this->is_cipher_available() || ! $this->is_valid_token_hash( $token_hash ) ) {
			return false;
		}

		$key = $this->derive_key( $user_id );
		$aad = $this->build_authenticated_data( $user_id, $version, $token_hash );

		if ( false === $key || false === $aad ) {
			return false;
		}

		$nonce = $this->generate_nonce();

		if ( false === $nonce ) {
			return false;
		}

		$plaintext = wp_json_encode( $cache );

		if ( false === $plaintext ) {
			return false;
		}

		$tag        = '';
		$ciphertext = openssl_encrypt(
			$plaintext,
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

		return $this->encode_envelope( $nonce, $tag, $ciphertext );

	}

	/**
	 * Decrypt and authenticate a recoverable internal token cache.
	 *
	 * @param string $encoded_envelope Encoded AEAD envelope.
	 * @param int    $user_id WordPress user ID.
	 * @param int    $version Outer cache envelope version.
	 * @param string $token_hash Outer SHA-256 token hash.
	 * @return array|false Decrypted cache data or false on failure.
	 */
	public function decrypt( string $encoded_envelope, int $user_id, int $version, string $token_hash ) {

		if ( ! $this->is_cipher_available() || ! $this->is_valid_token_hash( $token_hash ) ) {
			return false;
		}

		$parts = $this->decode_envelope( $encoded_envelope );

		if ( false === $parts ) {
			return false;
		}

		$key = $this->derive_key( $user_id );
		$aad = $this->build_authenticated_data( $user_id, $version, $token_hash );

		if ( false === $key || false === $aad ) {
			return false;
		}

		$plaintext = openssl_decrypt(
			$parts['ciphertext'],
			self::ENCRYPTION_METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$parts['nonce'],
			$parts['tag'],
			$aad
		);

		if ( false === $plaintext ) {
			return false;
		}

		$cache = json_decode( $plaintext, true );

		return is_array( $cache ) ? $cache : false;

	}

	/**
	 * Derive a user- and site-bound cache key.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|false 32-byte key or false without safe material.
	 */
	private function derive_key( int $user_id ) {

		$key_material = $this->configuration->get_key_material();

		if ( false === $key_material ) {
			return false;
		}

		$info = wp_json_encode(
			array(
				'context'  => self::ENCRYPTION_CONTEXT,
				'user_id'  => $user_id,
				'site_id'  => $this->site_policy->get_site_id(),
				'audience' => $this->site_policy->get_audience(),
			)
		);

		if ( false === $info ) {
			return false;
		}

		return hash_hkdf(
			'sha256',
			$key_material,
			32,
			$info,
			'uncanny-automator-mcp-internal-token-cache-v2'
		);

	}

	/**
	 * Build canonical additional authenticated data for the envelope.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param int    $version Cache envelope version.
	 * @param string $token_hash Outer SHA-256 token hash.
	 * @return string|false Encoded authenticated data or false for invalid input.
	 */
	private function build_authenticated_data( int $user_id, int $version, string $token_hash ) {

		if ( self::VERSION !== $version || ! $this->is_valid_token_hash( $token_hash ) ) {
			return false;
		}

		return wp_json_encode(
			array(
				'context'    => self::ENCRYPTION_CONTEXT,
				'version'    => $version,
				'user_id'    => $user_id,
				'site_id'    => $this->site_policy->get_site_id(),
				'audience'   => $this->site_policy->get_audience(),
				'token_hash' => $token_hash,
			)
		);

	}

	/**
	 * Generate a cryptographically secure GCM nonce.
	 *
	 * @return string|false Nonce bytes or false when entropy is unavailable.
	 */
	private function generate_nonce() {

		try {
			return random_bytes( self::NONCE_LENGTH );
		} catch ( \Throwable $exception ) {
			return false;
		}

	}

	/**
	 * Encode binary envelope parts for WordPress metadata storage.
	 *
	 * @param string $nonce GCM nonce.
	 * @param string $tag GCM authentication tag.
	 * @param string $ciphertext Encrypted payload.
	 * @return string|false Encoded envelope or false on serialization failure.
	 */
	private function encode_envelope( string $nonce, string $tag, string $ciphertext ) {

		$envelope = wp_json_encode(
			array(
				'nonce'      => base64_encode( $nonce ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Binary envelope encoding.
				'tag'        => base64_encode( $tag ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Binary envelope encoding.
				'ciphertext' => base64_encode( $ciphertext ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Binary envelope encoding.
			)
		);

		if ( false === $envelope ) {
			return false;
		}

		return base64_encode( $envelope ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Binary envelope encoding.

	}

	/**
	 * Decode and validate binary envelope parts.
	 *
	 * @param string $encoded_envelope Encoded envelope.
	 * @return array|false Binary parts or false when malformed.
	 */
	private function decode_envelope( string $encoded_envelope ) {

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Binary envelope decoding.
		$serialized_envelope = base64_decode( $encoded_envelope, true );

		if ( false === $serialized_envelope ) {
			return false;
		}

		$envelope = json_decode( $serialized_envelope, true );

		if ( ! $this->has_valid_envelope_shape( $envelope ) ) {
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Binary envelope decoding.
		$nonce = base64_decode( $envelope['nonce'], true );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Binary envelope decoding.
		$tag = base64_decode( $envelope['tag'], true );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Binary envelope decoding.
		$ciphertext = base64_decode( $envelope['ciphertext'], true );

		if ( ! $this->has_valid_binary_parts( $nonce, $tag, $ciphertext ) ) {
			return false;
		}

		return array(
			'nonce'      => $nonce,
			'tag'        => $tag,
			'ciphertext' => $ciphertext,
		);

	}

	/**
	 * Determine whether the expected OpenSSL cipher is available.
	 *
	 * @return bool Whether encryption may proceed.
	 */
	private function is_cipher_available(): bool {

		if ( null !== $this->cipher_availability_checker ) {
			return (bool) call_user_func( $this->cipher_availability_checker );
		}

		return extension_loaded( 'openssl' )
			&& in_array( self::ENCRYPTION_METHOD, openssl_get_cipher_methods(), true );

	}

	/**
	 * Determine whether an outer token hash has the canonical shape.
	 *
	 * @param string $token_hash Candidate SHA-256 hash.
	 * @return bool Whether the hash is canonical.
	 */
	private function is_valid_token_hash( string $token_hash ): bool {

		return 1 === preg_match( '/^[a-f0-9]{64}$/D', $token_hash );

	}

	/**
	 * Determine whether decoded envelope fields have the exact expected shape.
	 *
	 * @param mixed $envelope Decoded JSON value.
	 * @return bool Whether all encoded parts are present and scalar.
	 */
	private function has_valid_envelope_shape( $envelope ): bool {

		if ( ! is_array( $envelope ) || 3 !== count( $envelope ) ) {
			return false;
		}

		if ( ! isset( $envelope['nonce'], $envelope['tag'], $envelope['ciphertext'] ) ) {
			return false;
		}

		return is_string( $envelope['nonce'] )
			&& is_string( $envelope['tag'] )
			&& is_string( $envelope['ciphertext'] );

	}

	/**
	 * Determine whether decoded binary parts satisfy the AEAD contract.
	 *
	 * @param mixed $nonce Decoded nonce.
	 * @param mixed $tag Decoded authentication tag.
	 * @param mixed $ciphertext Decoded ciphertext.
	 * @return bool Whether the binary envelope is valid.
	 */
	private function has_valid_binary_parts( $nonce, $tag, $ciphertext ): bool {

		return is_string( $nonce )
			&& is_string( $tag )
			&& is_string( $ciphertext )
			&& self::NONCE_LENGTH === strlen( $nonce )
			&& self::TAG_LENGTH === strlen( $tag )
			&& '' !== $ciphertext;

	}
}
