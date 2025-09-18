<?php

namespace Uncanny_Automator;

/**
 * Automator Options Formatter
 *
 * This class is used to format the value of an option.
 * It is used to convert the value of an option to its proper type.
 *
 * @since <version>
 */
final class Automator_Option_Formatter {

	/**
	 * Format and convert a raw option value to its proper type.
	 *
	 * @param string $option The option name.
	 * @param mixed $value The raw value from storage.
	 * @param mixed $default_value The default value if conversion fails.
	 * @param string|null $type The type of the value. (integer|double|boolean|NULL)
	 *
	 * @return mixed The properly formatted value.
	 */
	public static function format_value( $value, $default_value, $type = null ) {

		// Unserialize the value if needed.
		$value = maybe_unserialize( $value );

		// Return false if the value is false.
		if ( '__false__' === $value || ( '' === $value && false === $default_value ) ) {
			return false;
		}

		// Return true if the value is true.
		if ( '__true__' === $value || ( '' === $value && true === $default_value ) ) {
			return true;
		}

		// Return null if the value is null.
		if ( '__null__' === $value || ( '' === $value && null === $default_value ) ) {
			return $default_value;
		}

		// Return '' if the value is truly empty.
		if ( '' === $value ) {
			return $value;
		}

		return self::cast_to_original_type( $value, $type );
	}

	/**
	 * Cast value to its original type based on stored type information.
	 *
	 * @param mixed $value The value to cast.
	 * @param string|null $original_type The original type.
	 *
	 * @return mixed The type-cast value.
	 */
	private static function cast_to_original_type( $value, $original_type ) {

		switch ( $original_type ) {
			case 'integer':
				return (int) $value;
			case 'double':
				return (float) $value;
			case 'boolean':
				return (bool) $value;
			case 'NULL':
				return null;
			default:
				return $value; // Return as-is for strings and other types.
		}
	}

	/**
	 * Encode a value for storage (maintains legacy compatibility).
	 *
	 * @param mixed $value The value to encode.
	 *
	 * @return mixed The encoded value for storage.
	 */
	public static function encode_value( $value ) {

		// Convert booleans to legacy encoding.
		if ( is_bool( $value ) ) {
			return $value ? '__true__' : '__false__';
		}

		// Convert null to legacy encoding.
		if ( null === $value ) {
			return '__null__';
		}

		// Return other values as-is (will be serialized if needed).
		return $value;
	}

	/**
	 * Get the type of a value for storage.
	 *
	 * @param mixed $value The value to get type for.
	 *
	 * @return string The PHP type name.
	 */
	public static function get_value_type( $value ) {
		return gettype( $value );
	}
}
