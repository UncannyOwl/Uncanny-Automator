<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Site identity provisioning — owns the per-site UUIDv4 that scopes this
 * install's idempotency keys (MVP plan A.1 + A.2, Contract A).
 *
 * That is the whole job. This UUID is the idempotency-key scope only:
 * platform site identity lives in `Site_Credentials_Store::site_uuid()`,
 * and drift detection lives in `Registration\Site_Address_Drift::drift()`. The
 * UUID IS sent in the `POST /app/v1/sites/register` body, but the platform
 * ignores that field and derives its own site_id from the normalized
 * Site-Name header, so nothing downstream treats it as an identity. The
 * re-mint below exists so a DB clone cannot go on sharing the original's
 * idempotency scope.
 *
 * Generation strategy:
 *  - **UUIDv4 via `wp_generate_uuid4()`** — pure 122-bit randomness from
 *    `random_bytes()` under the hood. No environmental leak (v1 MAC,
 *    v5 namespace, v7 timestamp) and no `ramsey/uuid` Composer dep.
 *    Plan A.1 originally specified `ramsey/uuid`; deviation taken because
 *    we only need v4 generation, not parsing/validation/comparison.
 *  - **Idempotent:** `ensure()` regenerates only when the stored value
 *    is missing OR when `home_url()` has moved since the mint (a DB clone
 *    / staging copy must not go on sharing the original's idempotency
 *    scope).
 *  - **Pre-network:** generation is local-only. Plugin owns its identity
 *    from activation regardless of platform reachability; the network
 *    POST happens later in `Platform_Site_Registration`.
 *
 * Option keys:
 *  - `automator_site_uuid`              — the UUIDv4 string
 *  - `automator_site_url_at_uuid_mint`  — `home_url()` at mint time,
 *    used by the re-mint guard above
 *
 * @since 8.0.0
 */
final class Site_Identity_Service {

	private const OPTION_UUID        = 'automator_site_uuid';
	private const OPTION_URL_AT_MINT = 'automator_site_url_at_uuid_mint';

	/**
	 * Return the site's UUIDv4, generating + persisting on first call or
	 * after a detected URL drift. Idempotent — safe to call from any
	 * request entry point.
	 *
	 * @return string UUIDv4 string (lowercase, hyphenated).
	 */
	public function ensure(): string {

		$current_url = $this->current_url();
		$stored      = (string) automator_get_option( self::OPTION_UUID, '' );
		$mint_url    = (string) automator_get_option( self::OPTION_URL_AT_MINT, '' );

		// Mint case 1: never minted.
		// Mint case 2: URL drifted since last mint AND we have a recorded
		// mint URL to compare against. The recorded-URL guard avoids
		// re-minting for sites upgraded from a pre-7.6.0 install where
		// the mint-url marker was never written (legacy UUID without
		// drift tracking is still better than rotating it every load).
		$drifted = '' !== $mint_url && $mint_url !== $current_url;

		if ( '' === $stored || $drifted ) {
			$stored = $this->mint();
			automator_update_option( self::OPTION_UUID, $stored );
			automator_update_option( self::OPTION_URL_AT_MINT, $current_url );
		}

		return $stored;
	}

	/**
	 * Read the persisted UUID without minting. Returns null when the
	 * UUID has not yet been provisioned — callers that want
	 * read-or-mint semantics should use `ensure()` instead.
	 */
	public function get(): ?string {

		$stored = (string) automator_get_option( self::OPTION_UUID, '' );
		return '' !== $stored ? $stored : null;
	}

	/**
	 * Force-regenerate the UUID. Reserved for ops-driven recovery
	 * (compromise event, manual re-registration after a botched
	 * platform-side cleanup). Not called by any normal flow.
	 *
	 * @return string The newly minted UUIDv4.
	 */
	public function regenerate(): string {

		$fresh = $this->mint();
		automator_update_option( self::OPTION_UUID, $fresh );
		automator_update_option( self::OPTION_URL_AT_MINT, $this->current_url() );
		return $fresh;
	}

	/**
	 * Mint a fresh UUIDv4 via WordPress core. `wp_generate_uuid4()`
	 * landed in WP 4.7 (Dec 2016) and uses `random_bytes(16)` under the
	 * hood — pure 122-bit randomness from kernel entropy, no host or
	 * network leak. Plugin's minimum WP version is well past 4.7; no
	 * fallback needed.
	 *
	 * @return string A lowercase hyphenated UUIDv4 string.
	 */
	private function mint(): string {

		return wp_generate_uuid4();
	}

	/**
	 * Read the current site's `home_url()`. Wrapped behind
	 * `function_exists` so the class can be unit-instantiated in
	 * environments where WordPress isn't fully bootstrapped.
	 *
	 * @return string
	 */
	private function current_url(): string {

		return function_exists( 'home_url' ) ? (string) home_url() : '';
	}
}
