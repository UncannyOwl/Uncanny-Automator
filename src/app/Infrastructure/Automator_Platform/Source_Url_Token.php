<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Mints the `X-Automator-Source-Token` header for upload-from-url
 * platform calls (Phase 2 plan task 5b, P7).
 *
 * Two-part JWT-style envelope:
 *
 *     <base64url(JCS-canonical JSON payload)>.<base64url(HMAC-SHA256 over the JSON bytes)>
 *
 * Payload shape:
 *
 *     { "action_id": <int>, "exp": <unix-seconds>, "recipe_run_id": <int>, "url_hash": "<sha256-hex>" }
 *
 * The HMAC operates over the EXACT bytes of the canonicalized JSON
 * payload — same bytes the platform verifier will re-encode on its
 * side. Drift in encoding flags (escape rules, key order, whitespace)
 * → signature mismatch → 401. {@see Json_Canonical} owns the
 * canonical-bytes contract.
 *
 * Reuses {@see Hmac_Validator::decode_secret()}'s base64-soft-decode
 * pattern: the platform mints raw 32 bytes and ships the base64-encoded
 * form, but the HMAC is computed against the raw bytes — the plugin
 * MUST decode before signing.
 *
 * Used internally by `App_Action::prepare_upload_request()`; per-action
 * authors don't see this class directly.
 *
 * @since 8.0.0
 */
final class Source_Url_Token {

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @var Json_Canonical
	 */
	private $canonical;

	/**
	 * @param Site_Credentials_Store $store     Source of the per-site signing secret.
	 * @param Json_Canonical         $canonical Canonical-JSON encoder for the signed payload bytes.
	 */
	public function __construct( Site_Credentials_Store $store, Json_Canonical $canonical ) {

		$this->store     = $store;
		$this->canonical = $canonical;
	}

	/**
	 * Mint a source-token header value.
	 *
	 * @param string $source_url    The signed source URL the platform
	 *                              will fetch from. The token binds the
	 *                              token to this URL via sha256 hash.
	 * @param int    $exp           Unix timestamp at which the token
	 *                              expires (caller-controlled; typically
	 *                              `time() + 300`).
	 * @param int    $recipe_log_id Recipe-log id (binds the token to a run).
	 * @param int    $action_id     Action id (binds the token to an action).
	 *
	 * @return string Two-part JWT-style token (`<payload>.<signature>`).
	 */
	public function mint( string $source_url, int $exp, int $recipe_log_id, int $action_id ): string {

		$payload = $this->canonical->encode(
			array(
				'action_id'     => $action_id,
				'exp'           => $exp,
				'recipe_run_id' => $recipe_log_id,
				'url_hash'      => hash( 'sha256', $source_url ),
			)
		);

		$signature = hash_hmac( 'sha256', $payload, $this->signing_secret_raw(), true );

		return self::base64url_encode( $payload ) . '.' . self::base64url_encode( $signature );
	}

	/**
	 * Decode the stored signing secret to the raw bytes the platform
	 * signs with.
	 *
	 * Soft-decode: an unparseable input falls back to the original
	 * string — see {@see Hmac_Validator::decode_secret()} for the
	 * canonical pattern this mirrors.
	 *
	 * @return string Raw secret bytes for HMAC.
	 */
	private function signing_secret_raw(): string {

		$stored = (string) $this->store->signing_secret();

		if ( '' === $stored ) {
			return '';
		}

		$decoded = base64_decode( $stored, true );
		return false === $decoded ? $stored : $decoded;
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
}
