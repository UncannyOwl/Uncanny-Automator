<?php
/**
 * WordPress post meta storage forms, read without constructing PHP objects.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Utilities;

/**
 * Core reads meta with maybe_unserialize(), which constructs any class named in
 * stored data. This codec never calls it: it applies core's is_serialized() gate,
 * then decodes with allowed_classes => false, so stored objects stay inert.
 */
final class Post_Meta_Value {

	/** Literal text; WordPress returns it unchanged. */
	public const TEXT = 'text';

	/** SQL NULL; WordPress returns an empty string. */
	public const NULL = 'null';

	/** A serialized PHP array; WordPress returns the array. */
	public const SERIALIZED_ARRAY = 'serialized_array';

	/** Serialized text, such as a serialized-looking string stored again; WordPress returns the inner text. */
	public const SERIALIZED_STRING = 'serialized_string';

	/** A serialized integer, float, boolean or null, written outside the meta API. */
	public const SERIALIZED_SCALAR = 'serialized_scalar';

	/** Serialized data naming a PHP class. Owned by its plugin; never decoded here. */
	public const PHP_OBJECT = 'php_object';

	/** Serialized-looking data PHP cannot decode, usually from a length-changing search and replace. */
	public const INVALID_SERIALIZED = 'invalid_serialized';

	/** Nesting accepted in values written through this tool. */
	public const MAX_DEPTH = 32;

	/** Fingerprint of a key with no stored rows. */
	public const ABSENT_HASH = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

	/**
	 * Entries and nesting one decoded value may have. PHP references can make a
	 * decoded array cyclic, so inspection stops here instead of following them.
	 */
	private const INSPECT_NODES = 200000;
	private const INSPECT_DEPTH = 512;

	/**
	 * Describe one meta_value column as WordPress would read it.
	 *
	 * The value is present whenever it decoded safely; unavailable explains why
	 * JSON cannot carry it. Larger serialized rows than $max_bytes are classified
	 * by their first token without decoding, and marked inspected: false.
	 *
	 * @param string|null $raw       Stored column value.
	 * @param int|null    $max_bytes Largest serialized row to decode, or null for any.
	 * @return array{stored_as: string, value?: mixed, unavailable?: string, references?: bool, inspected?: bool}
	 */
	public static function decode( ?string $raw, ?int $max_bytes = null ): array {
		if ( null === $raw ) {
			return array(
				'stored_as' => self::NULL,
				'value' => null,
			);
		}
		// Same gate as maybe_unserialize(); anything else is literal text to WordPress.
		if ( ! is_serialized( $raw ) ) {
			$result = array(
				'stored_as' => self::TEXT,
				'value' => $raw,
			);
			if ( ! self::is_utf8( $raw ) ) {
				$result['unavailable'] = 'not_utf8';
			}
			return $result;
		}
		if ( null !== $max_bytes && strlen( $raw ) > $max_bytes ) {
			return array(
				'stored_as' => self::token_kind( $raw ),
				'unavailable' => 'too_large',
				'inspected' => false,
			);
		}
		$decoded = self::unserialize( trim( $raw ) );
		if ( null === $decoded ) {
			return array(
				'stored_as' => self::INVALID_SERIALIZED,
				'unavailable' => 'invalid_serialized',
			);
		}
		$facts = self::inspect( $decoded[0] );
		if ( $facts['object'] ) {
			return array(
				'stored_as' => self::PHP_OBJECT,
				'unavailable' => 'php_object',
			);
		}
		if ( $facts['exceeded'] ) {
			// Objects beyond the limit cannot be ruled out, so nothing may write it.
			return array(
				'stored_as' => self::SERIALIZED_ARRAY,
				'unavailable' => 'too_complex',
				'references' => $facts['references'],
			);
		}
		if ( is_array( $decoded[0] ) ) {
			$stored_as = self::SERIALIZED_ARRAY;
		} else {
			$stored_as = is_string( $decoded[0] ) ? self::SERIALIZED_STRING : self::SERIALIZED_SCALAR;
		}
		$result = array(
			'stored_as' => $stored_as,
			'value' => $decoded[0],
		);
		if ( null !== $facts['unavailable'] ) {
			$result['unavailable'] = $facts['unavailable'];
		}
		// PHP references survive serialization: changing one place changes the other.
		if ( $facts['references'] ) {
			$result['references'] = true;
		}
		return $result;
	}

	/**
	 * The kind of value a stored form holds. NULL reads as empty text.
	 */
	public static function kind( string $stored_as ): string {
		return self::NULL === $stored_as ? self::TEXT : $stored_as;
	}

	/**
	 * The native value to store for a top-level input, or null when WordPress would
	 * not keep it faithfully. Core stores top-level scalars as text: true becomes
	 * "1", false "", null SQL NULL and 0.1 + 0.2 "0.3".
	 *
	 * @param mixed $value Decoded JSON input.
	 * @return string|array|null
	 */
	public static function top_level( $value ) {
		if ( is_int( $value ) ) {
			return (string) $value;
		}
		return is_string( $value ) || is_array( $value ) ? $value : null;
	}

	/**
	 * The stored form a native value takes.
	 *
	 * @param string|array $value Native value from top_level() or a patch.
	 */
	public static function shape( $value ): string {
		if ( is_array( $value ) ) {
			return self::SERIALIZED_ARRAY;
		}
		// maybe_serialize() serializes text again when it already looks serialized.
		return is_serialized( $value, false ) ? self::SERIALIZED_STRING : self::TEXT;
	}

	/**
	 * The column add_metadata() and update_metadata_by_mid() write for a native
	 * value. Compared with storage, never written.
	 *
	 * @param mixed $value Native or sanitized value.
	 */
	public static function stored_form( $value ): string {
		return (string) maybe_serialize( $value );
	}

	/**
	 * Refuse input WordPress cannot store as given.
	 *
	 * @param mixed $value Decoded JSON input.
	 * @return string|null Reason, or null when the value can be stored.
	 */
	public static function invalid_input( $value, int $depth = 0 ): ?string {
		if ( $depth > self::MAX_DEPTH ) {
			return 'too_deep';
		}
		if ( is_string( $value ) ) {
			return self::is_utf8( $value ) ? null : 'not_utf8';
		}
		if ( is_float( $value ) ) {
			return is_finite( $value ) ? null : 'not_finite';
		}
		if ( null === $value || is_bool( $value ) || is_int( $value ) ) {
			return null;
		}
		if ( ! is_array( $value ) ) {
			return 'unsupported_type';
		}
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && ! self::is_utf8( $key ) ) {
				return 'not_utf8';
			}
			$reason = self::invalid_input( $item, $depth + 1 );
			if ( null !== $reason ) {
				return $reason;
			}
		}
		return null;
	}

	/**
	 * Fingerprint every row of one key, so added, removed and changed rows all count.
	 *
	 * @param array<int, array{meta_id: int, meta_value: string|null}> $rows Rows in meta_id order.
	 */
	public static function hash( array $rows ): string {
		// Hashed piece by piece, so large values are never copied into one string.
		$context = hash_init( 'sha256' );
		foreach ( $rows as $row ) {
			$raw = $row['meta_value'];
			hash_update( $context, $row['meta_id'] . ':' . ( null === $raw ? 'N' : strlen( $raw ) . ':' ) );
			if ( null !== $raw ) {
				hash_update( $context, $raw );
			}
			hash_update( $context, ';' );
		}
		return hash_final( $context );
	}

	/**
	 * Walk a decoded value once, within fixed limits, recording what matters.
	 *
	 * @param mixed $value Decoded value.
	 * @return array{object: bool, references: bool, unavailable: string|null, exceeded: bool}
	 */
	private static function inspect( $value ): array {
		$facts = array(
			'object' => false,
			'references' => false,
			'unavailable' => null,
			'exceeded' => false,
		);
		$stack = array( array( $value, 0 ) );
		$nodes = 0;
		while ( $stack ) {
			list( $item, $depth ) = array_pop( $stack );
			if ( ++$nodes > self::INSPECT_NODES || $depth > self::INSPECT_DEPTH ) {
				$facts['exceeded'] = true;
				return $facts;
			}
			if ( is_object( $item ) ) {
				$facts['object'] = true;
				return $facts;
			}
			if ( is_string( $item ) && ! self::is_utf8( $item ) ) {
				$facts['unavailable'] = $facts['unavailable'] ?? 'not_utf8';
			}
			if ( is_float( $item ) && ! is_finite( $item ) ) {
				$facts['unavailable'] = $facts['unavailable'] ?? 'not_json';
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			foreach ( $item as $key => $child ) {
				if ( is_string( $key ) && ! self::is_utf8( $key ) ) {
					$facts['unavailable'] = $facts['unavailable'] ?? 'not_utf8';
				}
				if ( ! $facts['references'] && null !== \ReflectionReference::fromArrayElement( $item, $key ) ) {
					$facts['references'] = true;
				}
				$stack[] = array( $child, $depth + 1 );
			}
		}
		return $facts;
	}

	/** The stored form named by a serialized row's first token, for rows too large to decode. */
	private static function token_kind( string $raw ): string {
		$token = substr( ltrim( $raw ), 0, 1 );
		if ( 'a' === $token ) {
			return self::SERIALIZED_ARRAY;
		}
		if ( 's' === $token ) {
			return self::SERIALIZED_STRING;
		}
		return in_array( $token, array( 'O', 'E' ), true ) ? self::PHP_OBJECT : self::SERIALIZED_SCALAR;
	}

	/**
	 * Decode with classes disabled. Returns null when PHP cannot decode the data.
	 *
	 * @return array{0: mixed}|null
	 */
	private static function unserialize( string $data ): ?array {
		if ( 'b:0;' === $data ) {
			return array( false );
		}
		// Corrupt data raises a notice; the null result carries the outcome.
		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Restored in finally below.
			static function (): bool {
				return true;
			}
		);
		try {
			$value = unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Classes are disabled, so no stored object is constructed.
		} catch ( \Throwable $error ) {
			return null;
		} finally {
			restore_error_handler();
		}
		return false === $value ? null : array( $value );
	}

	/** Valid UTF-8, which JSON and the meta columns both require. */
	private static function is_utf8( string $value ): bool {
		return 1 === preg_match( '//u', $value );
	}
}
