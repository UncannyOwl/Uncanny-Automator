<?php
/**
 * Field value normalizer.
 *
 * Single source of truth for coercing a stored or wire field value to a
 * strict PHP bool. Mirrors the frontend isCheckboxValueChecked util
 * (FILTER_VALIDATE_BOOLEAN) so both sides agree on the same truthy set.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities;

/**
 * Coerces field values to strict booleans.
 */
final class Field_Value_Normalizer {

	/**
	 * Normalize any stored/wire checkbox value to a strict bool.
	 *
	 * @param mixed $value Stored or wire value (bool, int, string, null, ...).
	 * @return bool
	 */
	public static function to_bool( $value ): bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Serialize any checkbox/bool value to the wire string the FE expects.
	 *
	 * FieldsOutput `value` is always a string; the FE reads checkboxes via
	 * isCheckboxValueChecked. Outbound mirror of to_bool() — one place owns
	 * the checkbox<->wire conversion in both directions.
	 *
	 * @param mixed $value Stored or domain value.
	 * @return string 'true' or 'false'.
	 */
	public static function to_wire_string( $value ): string {
		return self::to_bool( $value ) ? 'true' : 'false';
	}

	/**
	 * Whether a value is a recognized boolean-ish input (one that to_bool()
	 * can sensibly coerce), as opposed to genuinely ambiguous input.
	 *
	 * Accepts null and any scalar FILTER_VALIDATE_BOOLEAN recognizes
	 * (bool, 0/1, '0'/'1', true/false, yes/no, on/off, ''); rejects ambiguous
	 * scalars (2, -1, 'bananas') and non-scalars (arrays, objects). The
	 * validation companion to to_bool() — callers that must reject garbage
	 * (e.g. value-object constructors) gate on this instead of re-deriving it.
	 *
	 * @param mixed $value Value to test.
	 * @return bool
	 */
	public static function is_boolish( $value ): bool {
		if ( is_null( $value ) ) {
			return true;
		}
		if ( ! is_scalar( $value ) ) {
			return false;
		}
		return null !== filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}
}
