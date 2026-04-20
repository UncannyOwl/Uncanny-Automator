<?php

namespace Uncanny_Automator;

/**
 * Repair structured JSON strings whose inner quotes were unslashed before storage.
 *
 * This helper targets array/object JSON payloads stored in post meta. It
 * deliberately ignores JSON scalars and plain strings because the WordPress
 * meta corruption bug only affects structured JSON that contains escaped
 * quotes. The parser operates on bytes but only inspects ASCII JSON syntax
 * characters, which is safe for UTF-8 input because multibyte sequences cannot
 * contain those bytes.
 */
class Json_String_Repair {

	/**
	 * Conservative maximum nesting depth while scanning malformed JSON.
	 */
	private const MAX_STACK_DEPTH = 512;

	/**
	 * Determine if the value looks like a structured JSON payload worth checking.
	 *
	 * @param mixed $value Raw meta value.
	 *
	 * @return bool
	 */
	public static function looks_like_structured_json( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$trimmed = trim( $value );
		$length  = strlen( $trimmed );

		if ( $length < 2 ) {
			return false;
		}

		$first = $trimmed[0];
		$last  = $trimmed[ $length - 1 ];

		if ( ! ( ( '{' === $first && '}' === $last ) || ( '[' === $first && ']' === $last ) ) ) {
			return false;
		}

		return false !== strpos( $trimmed, '"' );
	}

	/**
	 * Repair a malformed structured JSON string when the input is recoverable.
	 *
	 * Leaves valid JSON untouched and returns the original string when the
	 * payload cannot be repaired safely.
	 *
	 * @param string $value Raw meta value.
	 *
	 * @return string
	 */
	public static function repair( string $value ): string {
		if ( ! self::looks_like_structured_json( $value ) || self::is_valid_structured_json( $value ) ) {
			return $value;
		}

		$repaired = self::repair_unescaped_inner_quotes( $value );

		return is_string( $repaired ) && self::is_valid_structured_json( $repaired ) ? $repaired : $value;
	}

	/**
	 * Prepare a structured JSON string for WordPress meta storage.
	 *
	 * WordPress unslashes strings passed to both wp_insert_post(meta_input)
	 * and update_post_meta(). Valid JSON strings that contain escaped quotes
	 * must be pre-slashed or they become invalid at rest.
	 *
	 * @param mixed $value Raw meta value.
	 *
	 * @return mixed Storage-safe meta value.
	 */
	public static function slash_for_storage( $value ) {
		if ( ! self::looks_like_structured_json( $value ) ) {
			return $value;
		}

		$candidate = self::is_valid_structured_json( $value )
			? $value
			: self::repair( $value );

		return self::is_valid_structured_json( $candidate ) ? wp_slash( $candidate ) : $value;
	}

	/**
	 * Check whether the string is valid array/object JSON.
	 *
	 * @param string $value JSON candidate.
	 *
	 * @return bool
	 */
	private static function is_valid_structured_json( string $value ): bool {
		if ( ! self::looks_like_structured_json( $value ) ) {
			return false;
		}

		$decoded = json_decode( $value, true );

		return JSON_ERROR_NONE === json_last_error() && is_array( $decoded );
	}

	/**
	 * Re-escape quotes that are clearly part of JSON string content.
	 *
	 * @param string $json Potentially malformed JSON.
	 *
	 * @return string|null
	 */
	private static function repair_unescaped_inner_quotes( string $json ): ?string {
		$length         = strlen( $json );
		$result         = '';
		$stack          = array();
		$in_string      = false;
		$is_escaped     = false;
		$string_context = 'value';

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $json[ $i ];

			if ( ! $in_string ) {
				if ( '"' === $char ) {
					$in_string      = true;
					$is_escaped     = false;
					$string_context = self::is_object_key_context( $stack ) ? 'key' : 'value';
					$result        .= $char;
					continue;
				}

				if ( ! self::update_parser_state( $stack, $char ) ) {
					return null;
				}

				$result .= $char;
				continue;
			}

			if ( $is_escaped ) {
				$result    .= $char;
				$is_escaped = false;
				continue;
			}

			if ( '\\' === $char ) {
				$result    .= $char;
				$is_escaped = true;
				continue;
			}

			if ( '"' === $char ) {
				if ( self::is_closing_quote( $json, $i, $string_context, $stack ) ) {
					$in_string = false;

					if ( 'key' === $string_context && ! empty( $stack ) ) {
						$top_index = count( $stack ) - 1;
						if ( 'object' === $stack[ $top_index ]['type'] ) {
							$stack[ $top_index ]['expects'] = 'colon';
						}
					}

					$result .= $char;
					continue;
				}

				$result .= '\\"';
				continue;
			}

			$result .= $char;
		}

		// Unterminated string — the input is not recoverable.
		if ( $in_string ) {
			return null;
		}

		return $result;
	}

	/**
	 * Determine whether the current string is an object key string.
	 *
	 * @param array<int, array<string, string>> $stack Parser stack.
	 *
	 * @return bool
	 */
	private static function is_object_key_context( array $stack ): bool {
		if ( empty( $stack ) ) {
			return false;
		}

		$top = $stack[ count( $stack ) - 1 ];

		return 'object' === $top['type'] && 'key' === $top['expects'];
	}

	/**
	 * Determine if the current quote should terminate the string.
	 *
	 * @param string                            $json           Raw JSON string.
	 * @param int                               $position       Current quote position.
	 * @param string                            $string_context 'key' or 'value'.
	 * @param array<int, array<string, string>> $stack          Parser stack.
	 *
	 * @return bool
	 */
	private static function is_closing_quote( string $json, int $position, string $string_context, array $stack ): bool {
		$next = self::next_non_whitespace_char( $json, $position + 1 );

		if ( null === $next ) {
			return true;
		}

		if ( 'key' === $string_context ) {
			return ':' === $next;
		}

		if ( '}' === $next || ']' === $next ) {
			return true;
		}

		if ( ',' !== $next ) {
			return false;
		}

		$after_comma = self::next_non_whitespace_char_after( $json, $position + 1, ',' );
		if ( null === $after_comma ) {
			return true;
		}

		return self::is_valid_next_token_after_value_comma( $after_comma, $stack );
	}

	/**
	 * Find the next non-whitespace character after the given position.
	 *
	 * @param string $json     Raw JSON string.
	 * @param int    $position Start position.
	 *
	 * @return string|null
	 */
	private static function next_non_whitespace_char( string $json, int $position ): ?string {
		$length = strlen( $json );

		for ( $i = $position; $i < $length; $i++ ) {
			$char = $json[ $i ];

			if ( '' !== trim( $char ) ) {
				return $char;
			}
		}

		return null;
	}

	/**
	 * Find the next non-whitespace character after a required separator.
	 *
	 * @param string $json      Raw JSON string.
	 * @param int    $position  Start position.
	 * @param string $separator Expected separator character.
	 *
	 * @return string|null
	 */
	private static function next_non_whitespace_char_after( string $json, int $position, string $separator ): ?string {
		$length = strlen( $json );

		for ( $i = $position; $i < $length; $i++ ) {
			$char = $json[ $i ];

			if ( '' === trim( $char ) ) {
				continue;
			}

			return $separator === $char ? self::next_non_whitespace_char( $json, $i + 1 ) : null;
		}

		return null;
	}

	/**
	 * Validate the next token after a comma that follows a string value.
	 *
	 * For array values we stay conservative and only treat string/object/array
	 * continuations as definitive. This avoids misclassifying inner quotes in
	 * CSV-like text such as `",123` as closing quotes. The tradeoff is that
	 * mixed-type arrays whose next element is a scalar may remain unrepaired;
	 * in that case repair() returns the original malformed JSON unchanged.
	 *
	 * @param string                            $token Next non-whitespace token.
	 * @param array<int, array<string, string>> $stack Parser stack.
	 *
	 * @return bool
	 */
	private static function is_valid_next_token_after_value_comma( string $token, array $stack ): bool {
		if ( empty( $stack ) ) {
			return false;
		}

		$top = $stack[ count( $stack ) - 1 ];

		if ( 'object' === $top['type'] ) {
			return '"' === $token;
		}

		return '"' === $token
			|| '{' === $token
			|| '[' === $token
			|| ']' === $token;
	}

	/**
	 * Track JSON container state while outside strings.
	 *
	 * @param array<int, array<string, string>> $stack Parser stack.
	 * @param string                            $char  Current character.
	 *
	 * @return bool
	 */
	private static function update_parser_state( array &$stack, string $char ): bool {
		switch ( $char ) {
			case '{':
				if ( count( $stack ) >= self::MAX_STACK_DEPTH ) {
					return false;
				}

				$stack[] = array(
					'type'    => 'object',
					'expects' => 'key',
				);
				return true;

			case '[':
				if ( count( $stack ) >= self::MAX_STACK_DEPTH ) {
					return false;
				}

				$stack[] = array(
					'type' => 'array',
				);
				return true;

			case ':':
				if ( ! empty( $stack ) ) {
					$top_index = count( $stack ) - 1;
					if ( 'object' === $stack[ $top_index ]['type'] ) {
						$stack[ $top_index ]['expects'] = 'value';
					}
				}
				return true;

			case ',':
				if ( ! empty( $stack ) ) {
					$top_index = count( $stack ) - 1;
					if ( 'object' === $stack[ $top_index ]['type'] ) {
						$stack[ $top_index ]['expects'] = 'key';
					}
				}
				return true;

			case '}':
			case ']':
				if ( ! empty( $stack ) ) {
					array_pop( $stack );
				}
				return true;
		}

		return true;
	}
}
