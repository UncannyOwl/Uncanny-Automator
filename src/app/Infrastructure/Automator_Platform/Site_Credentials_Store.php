<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Site-level credential channel: signing-secret, fingerprint, site_id, callback URL.
 *
 * Stores the per-site credentials issued by the Automator Platform on
 * handshake. Supports in-place rotation: storing a new secret shifts the
 * prior one into a `previous` slot that remains valid for 24h, giving the
 * platform a grace window where callbacks signed with the old secret still
 * verify. {@see prune_expired_previous()} clears the previous slot after
 * the window closes.
 *
 * Option keys (all site-level):
 *  - automator_platform_site_signing_secret             — the active secret
 *  - automator_platform_site_signing_secret_previous    — prior secret, within grace
 *  - automator_platform_site_signing_secret_fingerprint — platform-issued fingerprint
 *  - automator_platform_site_signing_secret_version     — scheme version (e.g. v1)
 *  - automator_platform_site_id                         — platform-issued site id
 *  - automator_platform_site_uuid                       — platform-issued site uuid (issued, never trusted; distinct from the locally minted `automator_site_uuid`, which is idempotency scope only)
 *  - automator_platform_registered_license_fingerprint  — sha256[0:8] of the License-Key we registered with
 *  - automator_platform_registered_site_name            — Site-Name we registered with (drift check)
 *  - automator_platform_callback_url                    — callback receiver URL
 *  - automator_platform_challenge_url                   — registration challenge URL it registered with (the old address's, once the site moves)
 *  - automator_platform_credentials_rotated_at          — unix ts of last rotation
 *
 * @since 8.0.0
 * @package Uncanny_Automator\App\Infrastructure\Automator_Platform
 */
final class Site_Credentials_Store {

	/**
	 * Rotation grace: how long the previous secret remains accepted.
	 */
	private const GRACE_SECONDS = 24 * HOUR_IN_SECONDS;

	/**
	 * Persist credentials. If the active secret changes, the prior value is
	 * shifted into the `previous` slot and the rotated-at timestamp is set.
	 *
	 * @param array<string, mixed> $credentials Accepts any subset of:
	 *   signing_secret, fingerprint, version, site_id, site_uuid,
	 *   registered_license_fingerprint, registered_site_name, callback_url,
	 *   challenge_url.
	 *   Unspecified (or empty) keys are left untouched.
	 *
	 * @return void
	 */
	public function store( array $credentials ): void {

		$incoming = isset( $credentials['signing_secret'] ) ? (string) $credentials['signing_secret'] : '';
		$existing = $this->signing_secret();

		// Rotate: prior active → previous slot (sealed like the active
		// slot). Only on actual change. Not autoloaded — read only on
		// inbound callback verification, never on the front-end hot path.
		if ( '' !== $incoming && '' !== (string) $existing && $existing !== $incoming ) {
			automator_update_option( 'automator_platform_site_signing_secret_previous', self::seal( (string) $existing ), false );
			automator_update_option( 'automator_platform_credentials_rotated_at', time(), false );
		}

		$field_to_option = array(
			'signing_secret'                 => 'automator_platform_site_signing_secret',
			'fingerprint'                    => 'automator_platform_site_signing_secret_fingerprint',
			'version'                        => 'automator_platform_site_signing_secret_version',
			'site_id'                        => 'automator_platform_site_id',
			'site_uuid'                      => 'automator_platform_site_uuid',
			'registered_license_fingerprint' => 'automator_platform_registered_license_fingerprint',
			'registered_site_name'           => 'automator_platform_registered_site_name',
			'callback_url'                   => 'automator_platform_callback_url',
			'challenge_url'                  => 'automator_platform_challenge_url',
		);

		foreach ( $field_to_option as $field => $option_key ) {
			if ( ! isset( $credentials[ $field ] ) ) {
				continue;
			}
			$value = (string) $credentials[ $field ];
			if ( '' === $value ) {
				continue;
			}
			// The signing secret is sealed at rest (see seal()) — a
			// DB-only leak (SQLi in another plugin, exposed backup) must
			// not yield the key that forges platform callbacks.
			if ( 'signing_secret' === $field ) {
				$value = self::seal( $value );
			}

			// The active signing_secret is autoloaded, and ONLY when it is
			// actually sealed. It is the one secret on a hot path:
			// register_if_needed()->has() reads it on `init` priority 20 every
			// page load (Application_Bootstrap), so a non-autoloaded read adds
			// a per-request DB query on every registered site. A SEALED secret
			// in the autoload / alloptions cache exposes only a useless enc0:
			// blob — but an UNSEALED plaintext fallback (degraded AUTH_KEY /
			// no OpenSSL) must NOT ride that cache into backups / support
			// exports, so it is stored non-autoloaded (one extra DB read on an
			// already-degraded site) and the degrade is surfaced rather than
			// failing silently. Every other credential key is read only on
			// inbound callback verification or the (rare) registration dispatch
			// — except the two drift markers, see below.
			$is_sealed_secret = ( 'signing_secret' === $field && 0 === strpos( $value, 'enc0:' ) );

			if ( 'signing_secret' === $field && ! $is_sealed_secret ) {
				automator_log(
					'Platform signing secret stored WITHOUT at-rest sealing (AUTH_KEY undefined/placeholder, or OpenSSL unavailable). Define a strong AUTH_KEY in wp-config.php.',
					'Site_Credentials_Store',
					false
				);
			}

			// The two drift markers autoload as well: they are not secrets, and
			// the drift check reads both on `init` priority 20 every page load,
			// so a non-autoloaded read costs two DB queries per request on every
			// registered site.
			$autoload = $is_sealed_secret
				|| 'registered_license_fingerprint' === $field
				|| 'registered_site_name' === $field;

			automator_update_option( $option_key, $value, $autoload );
		}
	}

	/**
	 * Seal a secret with an AUTH_KEY-derived AES-256-GCM key so the value
	 * at rest in the options table is useless without filesystem access
	 * (wp-config.php). No-op passthrough when AUTH_KEY is unavailable —
	 * plaintext then matches the pre-seal behavior.
	 *
	 * Trade-off, documented deliberately: rotating AUTH_KEY orphans the
	 * sealed secret (unseal() returns ''), callbacks are refused
	 * `401 SIGNATURE_REJECTED` (`signature_mismatch`), and recovery follows
	 * the lost-secret path: the refused delivery asks for a re-verification,
	 * and a register without a secret is recovered through the platform's
	 * host challenge (see Platform_Site_Registration).
	 *
	 * @param string $plain The base64 signing secret as issued.
	 *
	 * @return string `enc0:` envelope, or the input when sealing is unavailable.
	 */
	private static function seal( string $plain ): string {

		// Treat WordPress's stock placeholder AUTH_KEY as absent — deriving
		// the seal key from a publicly-known constant is no better than
		// plaintext and worse (false sense of protection). Mirrors WP core's
		// own `put your unique phrase here` placeholder check.
		if (
			! defined( 'AUTH_KEY' )
			|| '' === (string) AUTH_KEY
			|| 'put your unique phrase here' === (string) AUTH_KEY
			|| ! function_exists( 'openssl_encrypt' )
		) {
			return $plain;
		}

		$key   = hash( 'sha256', (string) AUTH_KEY . '|automator-platform-credentials', true );
		$nonce = random_bytes( 12 );
		$tag   = '';
		$ct    = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );

		if ( false === $ct ) {
			return $plain;
		}

		return 'enc0:' . base64_encode( $nonce . $ct . $tag );
	}

	/**
	 * Reverse {@see seal()}. Legacy plaintext rows (no `enc0:` prefix)
	 * pass through unchanged; a sealed value that cannot be opened
	 * (AUTH_KEY rotated/undefined) yields '' — treated everywhere as
	 * "no secret on file".
	 *
	 * @param string $stored The stored option value.
	 *
	 * @return string The base64 signing secret, or ''.
	 */
	private static function unseal( string $stored ): string {

		if ( 0 !== strpos( $stored, 'enc0:' ) ) {
			return $stored;
		}

		if ( ! defined( 'AUTH_KEY' ) || '' === (string) AUTH_KEY || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$blob = base64_decode( substr( $stored, 5 ), true );
		if ( false === $blob || strlen( $blob ) <= 28 ) {
			return '';
		}

		$key   = hash( 'sha256', (string) AUTH_KEY . '|automator-platform-credentials', true );
		$plain = openssl_decrypt(
			substr( $blob, 12, -16 ),
			'aes-256-gcm',
			$key,
			OPENSSL_RAW_DATA,
			substr( $blob, 0, 12 ),
			substr( $blob, -16 )
		);

		return false === $plain ? '' : $plain;
	}

	/**
	 * @return string|null
	 */
	public function signing_secret(): ?string {

		$value = self::unseal( (string) automator_get_option( 'automator_platform_site_signing_secret' ) );
		return '' !== $value ? $value : null;
	}

	/**
	 * The prior secret, valid ONLY within the rotation grace window.
	 *
	 * The window is enforced HERE, at read time — not left to
	 * {@see prune_expired_previous()}, whose daily WP-cron never fires on
	 * `DISABLE_WP_CRON` or low-traffic sites, which would leave a
	 * rotated-out (possibly leaked) secret verifying callbacks
	 * indefinitely. Fails closed: no rotation timestamp on file, or past
	 * `GRACE_SECONDS`, returns null. The prune cron stays as storage
	 * hygiene, but revocation no longer depends on it.
	 *
	 * @return string|null
	 */
	public function previous_signing_secret(): ?string {

		$rotated_at = (int) automator_get_option( 'automator_platform_credentials_rotated_at', 0 );
		if ( 0 === $rotated_at || ( time() - $rotated_at ) > self::GRACE_SECONDS ) {
			return null;
		}

		$value = self::unseal( (string) automator_get_option( 'automator_platform_site_signing_secret_previous' ) );
		return '' !== $value ? $value : null;
	}

	/**
	 * @return string|null
	 */
	public function fingerprint(): ?string {

		$value = automator_get_option( 'automator_platform_site_signing_secret_fingerprint' );
		return $value ? (string) $value : null;
	}

	/**
	 * @return string|null
	 */
	public function site_id(): ?string {

		$value = automator_get_option( 'automator_platform_site_id' );
		return $value ? (string) $value : null;
	}

	/**
	 * Platform-issued site uuid. Custodied, never trusted: authorization is
	 * proof of possession of the signing secret plus license membership.
	 *
	 * @since 8.0.0
	 *
	 * @return string|null
	 */
	public function site_uuid(): ?string {

		$value = automator_get_option( 'automator_platform_site_uuid' );
		return $value ? (string) $value : null;
	}

	/**
	 * sha256[0:8] of the License-Key this site last registered with. Compared
	 * against the current key to detect a license swap (drift).
	 *
	 * @since 8.0.0
	 *
	 * @return string|null
	 */
	public function registered_license_fingerprint(): ?string {

		$value = automator_get_option( 'automator_platform_registered_license_fingerprint' );
		return $value ? (string) $value : null;
	}

	/**
	 * The Site-Name this site last registered with. Compared against the
	 * current site name to detect a rename/migration (drift).
	 *
	 * @since 8.0.0
	 *
	 * @return string|null
	 */
	public function registered_site_name(): ?string {

		$value = automator_get_option( 'automator_platform_registered_site_name' );
		return $value ? (string) $value : null;
	}

	/**
	 * The registration challenge URL this site last registered with — once the
	 * site moves, the OLD address's. A move this install cannot sign hands it
	 * to the platform, which looks there for proof the site left (move
	 * contract §10).
	 *
	 * @since 8.0.0
	 *
	 * @return string|null
	 */
	public function registered_challenge_url(): ?string {

		$value = automator_get_option( 'automator_platform_challenge_url' );
		return $value ? (string) $value : null;
	}

	/**
	 * @return bool
	 */
	public function has(): bool {

		return null !== $this->signing_secret();
	}

	/**
	 * Whether this install has registered with the platform: it holds a signing
	 * secret, or the record a registration stores beside it — the site_uuid the
	 * platform issued, or the Site-Name it registered under. The record outlives
	 * a secret this install can no longer read (AUTH_KEY rotated, see seal())
	 * and {@see forget_registration()}; the platform still has this install's
	 * callback URL then, and still delivers to it. An install with none of them
	 * never gave the platform a callback URL.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function has_registered(): bool {

		return $this->has() || null !== $this->site_uuid() || null !== $this->registered_site_name();
	}

	/**
	 * Drop the `previous` secret once the rotation grace window has elapsed.
	 *
	 * @return void
	 */
	public function prune_expired_previous(): void {

		$rotated_at = (int) automator_get_option( 'automator_platform_credentials_rotated_at', 0 );
		if ( 0 === $rotated_at ) {
			return;
		}

		if ( ( time() - $rotated_at ) > self::GRACE_SECONDS ) {
			automator_delete_option( 'automator_platform_site_signing_secret_previous' );
		}
	}

	/**
	 * Erase the signing-secret family (active + previous + fingerprint + version
	 * + rotated-at) so this install no longer holds a platform-issued secret.
	 *
	 * The copy lifecycle does NOT use this: a copy keeps the secret it inherited
	 * until the platform has accepted it, and {@see store()} then rotates that
	 * secret out. Erasing it first left a failed copy with no secret while the
	 * platform refused to re-register a site it already knew. This is a reset
	 * for recovery tooling and tests.
	 *
	 * Deliberately PRESERVES the platform-issued `site_uuid` (the clone signal)
	 * and the drift markers (persist_registration overwrites them on the next
	 * successful register). Also leaves the local
	 * Site_Identity_Service UUID (site_id) — that is the install's own stable id,
	 * not platform-issued state.
	 *
	 * @return void
	 */
	public function forget_registration(): void {

		foreach (
			array(
				'automator_platform_site_signing_secret',
				'automator_platform_site_signing_secret_previous',
				'automator_platform_site_signing_secret_fingerprint',
				'automator_platform_site_signing_secret_version',
				'automator_platform_credentials_rotated_at',
			) as $option_key
		) {
			automator_delete_option( $option_key );
		}
	}
}
