<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Contract H extension — wire-encrypted credentials (specced
 * 2026-04-29 in `docs/planning/2026-04-28-pr-7227-salvage-playbook.md`).
 *
 * Defends sensitive offramp fields (`access_token`, future
 * `refresh_token` / `webhook_secret`) against the WP-plugin threat
 * model where TLS-only protection isn't enough: WP_DEBUG_LOG in prod,
 * customer-pasted support-ticket logs, hosting-provider HTTP body
 * capture (New Relic / Datadog), corp MITM proxies, debug mu-plugins.
 *
 * Wire shape: `enc:v1:<base64( nonce[12] || ciphertext || tag[16] )>`
 *
 * Construction:
 *   - HKDF-SHA-256(IKM=signing_secret_raw, salt="",
 *                  info="automator-wire:v1:" . $context, len=32)
 *   - AES-256-GCM with 12-byte random nonce, empty AAD, 16-byte tag.
 *
 * The `$context` label binds the derived key to the field's semantic
 * purpose (`"access_token"`, `"refresh_token"`, etc.). Cross-context
 * decryption produces an auth-tag mismatch — defensive against
 * field-confusion attacks.
 *
 * Forward-compat: `maybe_decrypt()` returns bare strings unchanged so
 * the plugin can ship unwrap-capable code BEFORE the platform flips
 * encryption ON. Once both ends are deployed for one minor, platform
 * flips the wrap; older plugin versions just see plaintext as today.
 *
 * Key rotation: tries `current` signing_secret first, falls back to
 * `previous` on auth-tag failure — mirrors `Hmac_Validator`'s dual-slot
 * pattern. No new key material to manage.
 *
 * @since 8.0.0
 */
final class Wire_Cipher {

	private const PREFIX    = 'enc:v1:';
	private const NONCE_LEN = 12;
	private const TAG_LEN   = 16;
	private const KEY_LEN   = 32;
	private const KDF_INFO  = 'automator-wire:v1:';
	private const CIPHER    = 'aes-256-gcm';

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @param Site_Credentials_Store $store Source of the current/previous
	 *                                      signing secrets used as HKDF IKM.
	 */
	public function __construct( Site_Credentials_Store $store ) {

		$this->store = $store;
	}

	/**
	 * If `$value` is `enc:v1:`-prefixed, decrypt and return plaintext.
	 * If bare, return it unchanged (transitional cohabitation phase
	 * during platform rollout — both shapes are valid mid-rollout).
	 *
	 * Throws on tamper / malformed payload — silent fallback on a
	 * prefixed-but-broken wire would hide an active attack.
	 *
	 * @param string $value   Wire-format string from the platform response.
	 * @param string $context Field-purpose label per the allowlist
	 *                        (`access_token`, `refresh_token`, etc.).
	 *
	 * @return string Plaintext.
	 *
	 * @throws \RuntimeException When the wire payload fails decryption.
	 */
	public function maybe_decrypt( string $value, string $context ): string {

		if ( ! $this->is_wire_encrypted( $value ) ) {
			return $value;
		}

		return $this->decrypt( $value, $context );
	}

	/**
	 * Cheap prefix check — exposed so callers can skip-allocate on
	 * obviously-bare strings without entering the decrypt path.
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	public function is_wire_encrypted( string $value ): bool {

		return 0 === strncmp( $value, self::PREFIX, strlen( self::PREFIX ) );
	}

	/**
	 * Decrypt an `enc:v1:`-prefixed wire payload. Tries the current
	 * signing_secret first, falls back to the previous secret within
	 * the rotation grace window.
	 *
	 * @param string $value
	 * @param string $context
	 *
	 * @return string
	 *
	 * @throws \RuntimeException
	 */
	private function decrypt( string $value, string $context ): string {

		$payload = self::base64_decode_strict( substr( $value, strlen( self::PREFIX ) ) );
		if ( strlen( $payload ) < self::NONCE_LEN + self::TAG_LEN ) {
			throw new \RuntimeException( 'Wire payload too short.' );
		}

		$nonce      = substr( $payload, 0, self::NONCE_LEN );
		$tag        = substr( $payload, -self::TAG_LEN );
		$ciphertext = substr( $payload, self::NONCE_LEN, -self::TAG_LEN );

		$current  = self::raw_secret( (string) $this->store->signing_secret() );
		$previous = self::raw_secret( (string) ( $this->store->previous_signing_secret() ?? '' ) );

		$tried = false;
		foreach ( array( $current, $previous ) as $ikm ) {
			if ( '' === $ikm ) {
				continue;
			}
			$tried = true;
			$plain = self::try_decrypt( $ciphertext, $nonce, $tag, $ikm, $context );
			if ( null !== $plain ) {
				return $plain;
			}
		}

		// Distinguish "no key to try" from a genuine crypto failure — the
		// former points at registration / a rotated AUTH_KEY (see
		// Site_Credentials_Store), not a tampered/mis-signed payload.
		if ( ! $tried ) {
			throw new \RuntimeException( 'Wire decrypt failed: no signing secret on file (site not registered, or AUTH_KEY rotated).' );
		}

		throw new \RuntimeException( 'Wire decrypt failed: auth tag mismatch under current and previous secrets.' );
	}

	/**
	 * @return string|null Plaintext on success, null on tag mismatch.
	 */
	private static function try_decrypt( string $ciphertext, string $nonce, string $tag, string $ikm, string $context ): ?string {

		$key   = hash_hkdf( 'sha256', $ikm, self::KEY_LEN, self::KDF_INFO . $context, '' );
		$plain = openssl_decrypt( $ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag );

		return false === $plain ? null : $plain;
	}

	/**
	 * Companion to `Hmac_Validator::decode_secret()` — store keeps the
	 * signing_secret base64-encoded, the cipher needs the raw 32 bytes.
	 * Unlike decode_secret() (which falls back to the original string on
	 * a failed decode), this returns '' so the slot is skipped — a garbage
	 * key would only fail the GCM tag check anyway.
	 *
	 * @param string $stored
	 *
	 * @return string
	 */
	private static function raw_secret( string $stored ): string {

		if ( '' === $stored ) {
			return '';
		}

		$decoded = base64_decode( $stored, true );
		return false === $decoded ? '' : $decoded;
	}

	/**
	 * Strict base64 decode that rejects whitespace / invalid bytes.
	 * Throws rather than passing through corrupted input — silent
	 * coercion would hide tampering by stripping non-base64 padding.
	 *
	 * @param string $encoded
	 *
	 * @return string
	 *
	 * @throws \RuntimeException
	 */
	private static function base64_decode_strict( string $encoded ): string {

		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded ) {
			throw new \RuntimeException( 'Wire payload base64 decode failed.' );
		}
		return $decoded;
	}
}
