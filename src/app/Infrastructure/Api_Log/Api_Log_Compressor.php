<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Api_Log;

/**
 * Compresses `uap_api_log` column payloads (`params` / `request` /
 * `response`) with `gzcompress` + base64 to reduce table size — the
 * single biggest contributor to log-table bloat per repeated customer
 * complaints.
 *
 * Encoded blobs are prefixed with `gz1:` so {@see decode()} can detect
 * the format and fall back to {@see automator_safe_unserialize()} for legacy
 * rows that predate this layer. Both writers (v2 `Api_Server`, v3
 * `Api_Log_Entry`, webhook `trait-webhooks`) and all readers (resend,
 * replay, list-table debug views) route through this class so the
 * encoding choice is changed in exactly one place.
 *
 * Format version 1: JSON-encode → `gzcompress` (level 6) → base64 →
 * prefix `gz1:`. Future versions bump the prefix (`gz2:`, etc.) and
 * decode picks the right path. Empty / null / `false` JSON returns an
 * empty string so the column write is null-safe.
 *
 * @since 8.0.0
 */
final class Api_Log_Compressor {

	/**
	 * Format-1 marker — encode/decode keys on this prefix.
	 */
	private const PREFIX = 'gz1:';

	/**
	 * `gzcompress` level. 6 is the zlib default — best balance of CPU
	 * vs ratio. Higher (9) saves ~3-5% more bytes for ~3x the CPU; not
	 * worth it for log writes on the hot path.
	 */
	private const LEVEL = 6;

	/**
	 * Encode a value into a `gz1:`-prefixed blob suitable for the
	 * `params` / `request` / `response` columns.
	 *
	 * @param mixed $value Anything `wp_json_encode` accepts. Objects
	 *                     should expose a public to-array shape; the
	 *                     callers extract a compact array before
	 *                     calling this method (Request VO → compact
	 *                     replay payload, etc.) — encode itself does
	 *                     no shape transformation.
	 *
	 * @return string Empty string for null / unencodable input;
	 *                otherwise a `gz1:` blob.
	 */
	public static function encode( $value ): string {

		if ( null === $value ) {
			return '';
		}

		$json = wp_json_encode( $value );
		if ( false === $json ) {
			return '';
		}

		$compressed = gzcompress( $json, self::LEVEL );
		if ( false === $compressed ) {
			return '';
		}

		return self::PREFIX . base64_encode( $compressed ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport-only encoding for binary-safe storage
	}

	/**
	 * Decode a `gz1:` blob back to the original PHP value. Falls back
	 * to {@see automator_safe_unserialize()} for legacy rows so existing
	 * `uap_api_log` data remains readable through the format
	 * transition.
	 *
	 * @param mixed $blob Column value as read from the row. Strings
	 *                    are normal; arrays / null / false return
	 *                    safely typed. Corrupt `gz1:` payloads (bad
	 *                    base64, gzip header damage) return an empty
	 *                    array — the row is unreadable but the request
	 *                    doesn't fatal.
	 *
	 * @return mixed
	 */
	public static function decode( $blob ) {

		if ( is_array( $blob ) ) {
			return $blob;
		}

		if ( ! is_string( $blob ) || '' === $blob ) {
			return array();
		}

		if ( 0 !== strpos( $blob, self::PREFIX ) ) {
			// Legacy row pre-dating this layer. Never revive objects (CVE-2026-82627):
			// a serialized object decodes to __PHP_Incomplete_Class and the caller skips it.
			return automator_safe_unserialize( $blob );
		}

		$encoded = substr( $blob, strlen( self::PREFIX ) );

		$bytes = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- inverse of encode()
		if ( false === $bytes ) {
			return array();
		}

		$json = @gzuncompress( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- corrupt blob → empty result, not a warning storm
		if ( false === $json ) {
			return array();
		}

		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
