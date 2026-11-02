<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Http;

use WP_Http;

/**
 * Reads and probes the `headers` of WordPress HTTP request args the way
 * WP_Http itself will — shared by every `http_request_args` filter that adds
 * a header, and by the `http_response` observer that reads one back.
 *
 * Two facts about WordPress every such filter has to respect:
 *  - `headers` may arrive as a raw `"Name: value\r\n"` string. WP_Http parses
 *    it only AFTER `http_request_args`, so a filter that reads a string as "no
 *    headers" and writes an array back drops everything the caller set.
 *  - Header names are case-insensitive (RFC 9110), and callers mix
 *    `License-Key` / `license-key`. A caller-set header in any case must stop
 *    a filter from adding a second spelling.
 *
 * @since 8.0.0
 */
final class Request_Headers {

	/**
	 * The request's headers as a `name => value` array.
	 *
	 * Absent or null → none. A raw header string is parsed by
	 * `WP_Http::processHeaders()`, the parser WP_Http applies to it later, so
	 * the result is what would have been sent (names lower-cased by WP).
	 * Writing the array back is safe: WP_Http skips parsing an array.
	 *
	 * @param array<string, mixed> $args WP HTTP request args.
	 *
	 * @return array<int|string, mixed>
	 */
	public static function from_args( array $args ): array {

		$headers = $args['headers'] ?? array();

		if ( is_array( $headers ) ) {
			return $headers;
		}

		if ( is_string( $headers ) && '' !== $headers ) {
			return WP_Http::processHeaders( $headers )['headers'];
		}

		return array();
	}

	/**
	 * Whether a header with this name exists, in any case.
	 *
	 * Only keys are names; a list-style entry (`0 => 'Name: value'`) is not one
	 * — WordPress would transmit it as a header literally named `0`.
	 *
	 * @param array<int|string, mixed> $headers Headers as `name => value`.
	 * @param string                   $name    Header name to look for.
	 *
	 * @return bool
	 */
	public static function has( array $headers, string $name ): bool {

		$needle = strtolower( $name );

		foreach ( array_keys( $headers ) as $key ) {
			if ( strtolower( (string) $key ) === $needle ) {
				return true;
			}
		}

		return false;
	}
}
