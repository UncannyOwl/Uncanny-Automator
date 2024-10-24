<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations\Traits;

/**
 * Trait Token_Loopable_Hydratable
 *
 * Handles the hydration of loopable tokens, including support for dot notation,
 * loopable items, and various entity ID formats. This trait allows parsing and
 * extraction of values from complex token references.
 *
 * @package Uncanny_Automator\Services\Loopable\Data_Integrations\Traits
 */
trait Token_Loopable_Hydratable {

	/**
	 * Hydrates a loopable token node by extracting its value from the token reference.
	 * Supports various formats such as numeric token IDs, dot notation, and XML structures.
	 *
	 * @param array  $extracted_token   The token information including the token ID.
	 * @param array  $tokens_reference  The full token reference array containing entity data.
	 * @param mixed  $entity_id         The ID of the entity being processed, could be numeric or string.
	 *
	 * @return mixed The hydrated value of the token or an empty string if the token is not found.
	 */
	public static function hydrate_loopable_node( $extracted_token, $tokens_reference, $entity_id ) {

		// Split the token ID by dot notation for further processing.
		$token_id_with_dot_notation = explode( '.', $extracted_token['token_id'] );

		// Extract the loopable item index if the token ID is numeric.
		$loopable_item_index = self::extract_loopable_item_index_number( $extracted_token['token_id'] );

		// Check if the extracted index exists in the tokens reference.
		if ( null !== $loopable_item_index ) {
			$tokens_reference_numeric = array_values( $tokens_reference[ $entity_id ] );
			if ( isset( $tokens_reference_numeric[ $loopable_item_index ] ) ) {
				return $tokens_reference_numeric[ $loopable_item_index ]; // Return the numeric token value.
			}
		}

		// If the token ID contains dot notation, retrieve the value by dot notation.
		if ( count( (array) $token_id_with_dot_notation ) >= 2 ) {
			$value = self::get_value_by_dot_notation( $tokens_reference[ $entity_id ], $extracted_token['token_id'] );
			return $value;
		}

		// Handle cases where the entity data is an array with a numeric index.
		if ( isset( $tokens_reference[ $entity_id ][0] ) && 1 === count( (array) $tokens_reference[ $entity_id ][0] ) ) {
			// Check if the value is scalar and return it if true.
			if ( is_scalar( $tokens_reference[ $entity_id ][0] ) ) {
				return $tokens_reference[ $entity_id ][0];
			}

			// Return the value associated with the token ID.
			return $tokens_reference[ $entity_id ][0][ $extracted_token['token_id'] ];
		}

		// If the entity ID is not numeric, return the token reference value or an empty string.
		if ( ! is_numeric( $entity_id ) ) {
			return $tokens_reference[ $entity_id ] ?? '';
		}

		// Handle special case for XML structures where the current item value is stored.
		if ( isset( $tokens_reference[ $entity_id ]['_loopable_xml_text'] ) ) {
			return $tokens_reference[ $entity_id ]['_loopable_xml_text'];
		}

		// Handle special token 'current_item_value'.
		if ( 'current_item_value' === $extracted_token['token_id'] ) {
			return $tokens_reference[ $entity_id ];
		}

		// Return the normal token value if available.
		return $tokens_reference[ $entity_id ][ $extracted_token['token_id'] ] ?? '';
	}

	/**
	 * Retrieves a value from a nested array using dot notation.
	 *
	 * @param array  $array         The array to search through.
	 * @param string $dot_notation  The dot notation string representing the keys.
	 *
	 * @return mixed The value found by the dot notation, or null if not found.
	 */
	public static function get_value_by_dot_notation( $array, $dot_notation ) {
		// Split the dot notation into an array of keys.
		$keys = explode( '.', $dot_notation );

		// Traverse the array using the keys.
		foreach ( $keys as $key ) {

			if ( isset( $array[ $key ] ) ) {
				$array = $array[ $key ]; // Move deeper into the array.
			}

			// Handle special case for XML structures.
			if ( isset( $array[0]['_loopable_xml_text'] ) ) {
				$array = $array[0]['_loopable_xml_text'];
			}

			// Stop processing if the key does not exist.
			if ( ! isset( $array ) ) {
				return null; // Return null if the value is not found.
			}
		}

		return $array; // Return the final value.
	}

	/**
	 * Extracts the loopable item index number from a string if it matches a specific pattern.
	 *
	 * @param string $string The string potentially containing the loopable item index.
	 *
	 * @return int|null The extracted index number, or null if not found.
	 */
	public static function extract_loopable_item_index_number( $string ) {
		// Return null if the provided string is not a valid string.
		if ( ! is_string( $string ) ) {
			return null;
		}

		// Check if the string matches the pattern 'loopable_item_index_#'.
		if ( preg_match( '/^loopable_item_index_(\d+)$/', $string, $matches ) ) {
			return (int) $matches[1]; // Return the extracted number as an integer.
		}

		return null; // Return null if the pattern does not match.
	}

}
