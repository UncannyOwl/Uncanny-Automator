<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Type-restricted RFC 8785 (JCS) canonical JSON encoder.
 *
 * Phase 2 upload-from-url dispatch needs the byte sequence the plugin
 * signs (and persists for retry-replay) to equal the byte sequence the
 * platform verifies. Two callers use this helper:
 *
 *  - {@see Source_Url_Token::mint()} — JSON payload `{action_id, exp,
 *    recipe_run_id, url_hash}` is JCS-encoded, then HMAC-signed. The
 *    platform verifies the HMAC over the RAW payload bytes and NEVER
 *    re-canonicalizes — the plugin is the authoritative signer and the
 *    verifier trusts its byte ordering (see the platform's
 *    `SourceTokenVerifier`). The requirement is therefore
 *    SELF-consistency: the same input must produce the same bytes on
 *    every plugin-side re-encode (mint at dispatch, re-mint on retry),
 *    NOT byte-parity with a platform canonicalizer that does not exist.
 *  - The upload-action body persistence path — body is JCS-encoded
 *    before being written to `uap_action_log_meta`, so retries replay
 *    bit-identical bytes and hit the platform's 24h R6 idempotency
 *    cache instead of tripping `VALIDATION_ERROR` (422).
 *
 * ⚠️ Type-restricted approximation, NOT a full RFC 8785 implementation.
 * Our payloads contain only:
 *  - integers in safe ranges (epoch seconds, post IDs)
 *  - sha256 hex strings (lowercase, ASCII-safe)
 *  - ASCII-safe path strings (signed URLs, vendor body fields)
 *  - booleans
 *  - no floats
 *  - no control chars / NULs
 *  - no special escape sequences (UTF-16 surrogate pairs, etc.)
 *
 * Full RFC 8785 has number canonicalization rules (trailing zeros,
 * exponent normalization) and escape-sequence rules that diverge from
 * PHP's `json_encode` output. The helper is deterministic on our
 * payload shape; future maintainers extending it to other payloads
 * (with floats, control chars, etc.) MUST verify their data shapes are
 * still in the safe subset OR migrate to a full JCS implementation
 * (e.g., `gamringer/php-json-canonicalization`).
 *
 * @since 8.0.0
 */
final class Json_Canonical {

	/**
	 * Encode an array as RFC 8785-style canonical JSON for our specific
	 * payload shapes.
	 *
	 * Sorts keys ASCIIbetically (recursive ksort), encodes via
	 * json_encode with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`,
	 * no `JSON_PRETTY_PRINT`. Deterministic on our payload shapes.
	 *
	 * Empty-array note: `encode( array() )` emits `[]`, not `{}` — an
	 * empty PHP array is list-shaped. This is unreachable in practice:
	 * the only caller ({@see Source_Url_Token::mint()}) always passes its
	 * four fixed fields. Left as-is rather than special-cased (KISS); if a
	 * future caller can pass an empty payload, decide `[]` vs `{}` there.
	 *
	 * @param array<string, mixed> $data Associative array; nested arrays
	 *                                   are sorted recursively.
	 *
	 * @return string Canonical JSON bytes.
	 *
	 * @throws \RuntimeException When json_encode fails. The result is an
	 *         HMAC input, so a silent fallback (e.g. `'{}'`) would make two
	 *         distinct unencodable payloads sign to identical bytes — a
	 *         forgery/collision hazard. Fail loud instead; the sole caller
	 *         runs under `Action::do_action()`'s catch, so the action fails
	 *         cleanly rather than shipping a bogus signature.
	 */
	public function encode( array $data ): string {

		$sorted = self::recursive_ksort( $data );

		$encoded = json_encode( $sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// json_encode returns false on encoding failure (resources, NaN,
		// non-UTF-8 bytes). Our type-restricted payloads can't trip these,
		// but this is signed material — never emit a constant fallback.
		if ( false === $encoded ) {
			throw new \RuntimeException( 'Json_Canonical::encode() failed to encode the payload; refusing to sign a non-deterministic fallback.' );
		}

		return $encoded;
	}

	/**
	 * Recursively sort an array's keys ASCIIbetically. Returns a new array
	 * with the JCS-canonical key order. Non-array values pass through
	 * unchanged.
	 *
	 * Use this when you need a canonically-keyed array for downstream code
	 * paths that re-encode (e.g., a HTTP client's own json_encode call) —
	 * the array's stored key order is the one the re-encode will emit.
	 *
	 * @param array<string, mixed> $data
	 *
	 * @return array<string, mixed>
	 */
	public function sort_recursive( array $data ): array {

		return self::recursive_ksort( $data );
	}

	/**
	 * Recursively ksort an associative array.
	 *
	 * Nested array values get their own keys sorted; numerically-indexed
	 * arrays (lists) are left in insertion order — JSON arrays are
	 * ordered sequences, so reordering them would change semantics.
	 *
	 * @param array<int|string, mixed> $data
	 *
	 * @return array<int|string, mixed>
	 */
	private static function recursive_ksort( array $data ): array {

		// Detect list-shaped arrays (sequential 0..n-1 integer keys);
		// preserve their order.
		if ( ! self::is_assoc( $data ) ) {
			foreach ( $data as $i => $v ) {
				if ( is_array( $v ) ) {
					$data[ $i ] = self::recursive_ksort( $v );
				}
			}
			return $data;
		}

		ksort( $data, SORT_STRING );

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::recursive_ksort( $value );
			}
		}

		return $data;
	}

	/**
	 * Whether an array is associative (string keys present) vs a list
	 * (sequential integer keys 0..n-1).
	 *
	 * @param array<int|string, mixed> $data
	 *
	 * @return bool True when at least one key is non-integer.
	 */
	private static function is_assoc( array $data ): bool {

		if ( array() === $data ) {
			return false;
		}

		foreach ( array_keys( $data ) as $key ) {
			if ( ! is_int( $key ) ) {
				return true;
			}
		}

		return false;
	}
}
