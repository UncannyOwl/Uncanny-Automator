<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\File_Serve;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;
use WP_Error;

/**
 * Mints + verifies signed URLs for the `?automator_serve=1` endpoint.
 *
 * The platform GETs/HEADs these URLs to stream source bytes for
 * upload-class actions. HMAC-SHA256 over the canonical pipe-delimited
 * tuple `"{p}|{exp}|{run}|{act}"` produces the wire signature, encoded
 * as base64url and embedded as `&sig=...`.
 *
 * Pure compute class — no I/O, no WP globals beyond `home_url()` (used
 * once during minting). The signing-secret source is injected via
 * {@see Site_Credentials_Store} so tests can swap a fixture without
 * fighting WordPress option globals.
 *
 * Mirrors the platform-side verifier specced in
 * `automator-platform/docs/active/2026-04-30-file-transfer-streaming-standard.md`
 * sections P1 and R1.
 *
 * @since 8.0.0
 */
final class Signed_Url {

	/**
	 * Maximum acceptable TTL on a signed URL (5 minutes).
	 *
	 * Caps both mint-side TTL and verify-side `exp - now` to defend
	 * against forged future-dated URLs that try to extend their own
	 * validity past the platform's expectation window.
	 */
	private const MAX_TTL_SECONDS = 300;

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @param Site_Credentials_Store $store Source of the per-site signing secret.
	 */
	public function __construct( Site_Credentials_Store $store ) {

		$this->store = $store;
	}

	/**
	 * Mint a signed URL for the local automator_serve endpoint.
	 *
	 * The TTL is hard-capped at {@see self::MAX_TTL_SECONDS}; callers
	 * passing a larger value silently get the cap. Mirrors the platform
	 * verifier's future-dated rejection — anything beyond the cap would
	 * fail verification anyway.
	 *
	 * @param string $p             Raw path or URL to be served.
	 * @param int    $recipe_log_id Recipe-log id (binds the URL to a run).
	 * @param int    $action_id     Action id (binds the URL to an action).
	 * @param int    $ttl_seconds   Requested TTL; capped at 300.
	 *
	 * @return string Fully-qualified signed URL (`home_url('/?automator_serve=1&...')`).
	 *
	 * @throws \RuntimeException When the site has no signing secret. Fails
	 *         here, at the source of the problem, rather than minting a URL
	 *         built on an empty key that verify() would opaquely 401 later.
	 */
	public function mint( string $p, int $recipe_log_id, int $action_id, int $ttl_seconds = 300 ): string {

		$secret = self::decode_secret( (string) $this->store->signing_secret() );
		if ( '' === $secret ) {
			throw new \RuntimeException( 'Cannot mint a signed URL: this site has no Automator platform signing secret (not registered).' );
		}

		$ttl = min( max( 1, $ttl_seconds ), self::MAX_TTL_SECONDS );
		$exp = time() + $ttl;

		$canonical = self::canonical_string( $p, $exp, $recipe_log_id, $action_id );
		$sig       = self::base64url_encode( hash_hmac( 'sha256', $canonical, $secret, true ) );

		$query = http_build_query(
			array(
				'automator_serve' => '1',
				'p'               => $p,
				'exp'             => $exp,
				'run'             => $recipe_log_id,
				'act'             => $action_id,
				'sig'             => $sig,
			)
		);

		return home_url( '/?' . $query );
	}

	/**
	 * Verify the signed-URL query parameters against the active signing secret.
	 *
	 * Returns `true` on pass; on failure returns a {@see WP_Error} carrying
	 * an HTTP-status hint in `data.status` (401 for tampering / missing,
	 * 410 for expired or future-dated). Constant-time HMAC compare via
	 * {@see hash_equals}.
	 *
	 * @param array<string, mixed> $query_params The decoded query string.
	 *
	 * @return true|WP_Error
	 */
	public function verify( array $query_params ) {

		$p   = isset( $query_params['p'] ) ? (string) $query_params['p'] : '';
		$exp = isset( $query_params['exp'] ) ? (int) $query_params['exp'] : 0;
		$run = isset( $query_params['run'] ) ? (int) $query_params['run'] : 0;
		$act = isset( $query_params['act'] ) ? (int) $query_params['act'] : 0;
		$sig = isset( $query_params['sig'] ) ? (string) $query_params['sig'] : '';

		if ( '' === $p || 0 === $exp || 0 === $run || 0 === $act || '' === $sig ) {
			return new WP_Error(
				'invalid_token',
				'Missing or malformed signed-URL parameters.',
				array( 'status' => 401 )
			);
		}

		$now = time();
		if ( $exp < $now ) {
			return new WP_Error(
				'expired',
				'Signed URL has expired.',
				array( 'status' => 410 )
			);
		}

		if ( $exp > ( $now + self::MAX_TTL_SECONDS ) ) {
			return new WP_Error(
				'future_dated',
				'Signed URL is dated beyond the maximum allowed TTL.',
				array( 'status' => 410 )
			);
		}

		// Current secret only — DELIBERATELY not the current-or-previous
		// grace Hmac_Validator honours during rotation. A rotation invalidates
		// in-flight signed URLs, but the 300s TTL makes that window small and
		// self-healing (the next mint uses the new secret), so the asymmetry
		// with the side-by-side Hmac_Validator is intentional, not an oversight.
		//
		// Fail closed on an absent signing secret: hash_hmac() with an empty
		// key is deterministic, so an unregistered site (no secret) would
		// otherwise accept any signature an attacker computes the same way.
		$secret = self::decode_secret( (string) $this->store->signing_secret() );
		if ( '' === $secret ) {
			return new WP_Error(
				'no_signing_secret',
				'No signing secret on file — cannot verify signed URL.',
				array( 'status' => 401 )
			);
		}

		$canonical = self::canonical_string( $p, $exp, $run, $act );
		$expected  = self::base64url_encode( hash_hmac( 'sha256', $canonical, $secret, true ) );

		if ( ! hash_equals( $expected, $sig ) ) {
			return new WP_Error(
				'signature_mismatch',
				'Signed URL signature did not match.',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Canonical pipe-delimited string the HMAC operates over.
	 *
	 * Single source of truth — both {@see mint} and {@see verify} call
	 * this to guarantee byte-identical inputs on both sides of the wire.
	 *
	 * @param string $p   Raw (NOT url-encoded) path or URL.
	 * @param int    $exp Unix expiry timestamp.
	 * @param int    $run Recipe-log id.
	 * @param int    $act Action id.
	 *
	 * @return string `"{p}|{exp}|{run}|{act}"`.
	 */
	public static function canonical_string( string $p, int $exp, int $run, int $act ): string {

		return $p . '|' . $exp . '|' . $run . '|' . $act;
	}

	/**
	 * Base64url-encode raw bytes (RFC 4648 §5, no padding).
	 *
	 * @param string $bytes Raw bytes.
	 *
	 * @return string URL-safe base64 string with no `=` padding.
	 */
	private static function base64url_encode( string $bytes ): string {

		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode the stored signing secret to the raw bytes the platform signs with.
	 *
	 * Mirrors {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Hmac_Validator::decode_secret()}'s
	 * soft-decode pattern: stored value is base64-encoded by Contract A's
	 * `/sites/register` response, but legacy fixtures or test overrides may
	 * pre-store raw bytes. Soft-fall-back keeps both shapes working.
	 *
	 * @param string $stored Whatever {@see Site_Credentials_Store::signing_secret()} returned.
	 *
	 * @return string Raw secret bytes for HMAC.
	 */
	private static function decode_secret( string $stored ): string {

		if ( '' === $stored ) {
			return '';
		}

		$decoded = base64_decode( $stored, true );
		return false === $decoded ? $stored : $decoded;
	}
}
