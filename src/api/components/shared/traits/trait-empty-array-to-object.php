<?php
/**
 * Trait for normalizing empty PHP arrays to stdClass for JSON serialization.
 *
 * PHP's array() serializes to [] in JSON when empty, but {} when it has string keys.
 * MCP outputSchema requires object fields to always be {}, never [].
 * Use this trait in any class that builds data for JSON responses.
 *
 * @package Uncanny_Automator
 * @since   7.1.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Shared\Traits;

/**
 * Trait Empty_Array_To_Object.
 *
 * Provides a helper to ensure PHP arrays serialize as JSON objects ({}) not arrays ([]).
 *
 * @since 7.1.0
 */
trait Empty_Array_To_Object {

	/**
	 * Ensure a value serializes as a JSON object, not an empty array.
	 *
	 * - Empty array → stdClass (serializes as {})
	 * - Non-empty associative array → passed through (serializes as {key: value})
	 * - null → null (unchanged)
	 * - Any other value → unchanged
	 *
	 * @param mixed $value Value to normalize.
	 * @return mixed Normalized value.
	 */
	protected function ensure_object( $value ) {
		if ( is_array( $value ) && empty( $value ) ) {
			return new \stdClass();
		}
		return $value;
	}

	/**
	 * Return an empty stdClass that serializes as {} in JSON.
	 *
	 * @return \stdClass Empty object.
	 */
	protected function empty_object(): \stdClass {
		return new \stdClass();
	}
}
