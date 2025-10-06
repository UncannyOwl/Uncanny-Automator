<?php
//phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
namespace Uncanny_Automator;

/**
 * Class Automator_Compression
 *
 * Safe, optional gz compression helpers designed for MySQL LONGTEXT payloads.
 * - Requires zlib (gzcompress/gzuncompress). If not available, methods are no-ops.
 * - Uses a text-safe wrapper: 'gz:' . base64_encode(gzcompress(...)).
 * - Backwards compatible: non-prefixed strings are treated as plain text.
 *
 * @since 6.4.0
 */
class Automator_Compression {

	/**
	 * Prefix to identify compressed payloads.
	 *
	 * @var string
	 */
	const PREFIX = 'gz:';

	/**
	 * Compresses a string if zlib is available and the payload is large enough.
	 * Falls back to the original string on any failure.
	 *
	 * @param string $payload The plain string to compress.
	 *
	 * @return string The compressed string with 'gz:' prefix, or the original string.
	 */
	public static function maybe_compress_string( $payload ) {

		if ( ! is_string( $payload ) ) {
			return $payload;
		}

		// Allow hosts to tune thresholds without code changes.
		$min_bytes = absint( apply_filters( 'automator_compression_min_bytes', 2048 ) );
		$level     = absint( apply_filters( 'automator_compression_level', 4 ) );

		if ( $min_bytes <= 0 ) {
			$min_bytes = 2048;
		}

		// Do nothing if zlib is unavailable or payload is too small.
		if ( ! function_exists( 'gzcompress' ) || strlen( $payload ) < $min_bytes ) {
			return $payload;
		}

		// Compress with a moderate level to reduce CPU on shared hosting.
		$compressed = @gzcompress( $payload, $level ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $compressed ) {
			return $payload;
		}

		$wrapped = self::PREFIX . base64_encode( $compressed );

		// Avoid expanding small payloads due to base64 overhead.
		if ( strlen( $wrapped ) >= strlen( $payload ) ) {
			return $payload;
		}

		return $wrapped;
	}

	/**
	 * Decompresses a string produced by maybe_compress_string().
	 * Falls back to the original string on any failure.
	 *
	 * @param string $payload The stored string, possibly compressed.
	 *
	 * @return string The plain string on success, or the original string.
	 */
	public static function maybe_decompress_string( $payload ) {

		if ( ! is_string( $payload ) ) {
			return $payload;
		}

		if ( 0 !== strpos( $payload, self::PREFIX ) ) {
			return $payload;
		}

		if ( ! function_exists( 'gzuncompress' ) ) {
			return $payload;
		}

		$encoded = substr( $payload, strlen( self::PREFIX ) );
		$binary  = base64_decode( $encoded, true );

		if ( false === $binary ) {
			return $payload;
		}

		$decompressed = @gzuncompress( $binary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $decompressed ) {
			return $payload;
		}

		return $decompressed;
	}

	/**
	 * Indicates whether the given payload is a compressed string produced by this helper.
	 *
	 * @param mixed $payload The payload to test.
	 *
	 * @return bool True if it looks like a compressed payload, otherwise false.
	 */
	public static function is_compressed_string( $payload ) {
		return is_string( $payload ) && 0 === strpos( $payload, self::PREFIX );
	}
}
