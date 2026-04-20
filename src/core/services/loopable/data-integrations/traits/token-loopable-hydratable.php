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
	 * @param mixed  $data         The array to search through.
	 * @param string $dot_notation The dot notation string representing the keys.
	 *
	 * @return mixed The value found by the dot notation, or null if not found.
	 */
	public static function get_value_by_dot_notation( $data, $dot_notation ) {

		$keys = explode( '.', $dot_notation );

		foreach ( $keys as $key ) {

			if ( isset( $data[ $key ] ) ) {
				$data = $data[ $key ];
			} elseif ( is_array( $data ) && 1 === count( $data ) && isset( $data[0][ $key ] ) ) {
				// Unwrap single-element indexed arrays so nested keys such as '@attributes'
				// can be reached via paths like 'media:thumbnail.@attributes.url'.
				$data = $data[0][ $key ];
			}

			$data = self::resolve_xml_node( $data, $key );

			if ( ! isset( $data ) ) {
				return null;
			}
		}

		return $data;
	}

	/**
	 * Resolves the scalar value of a single XML node during dot-notation traversal.
	 *
	 * @param mixed  $node The current traversal value.
	 * @param string $key  The dot-notation key just processed.
	 *
	 * @return mixed
	 */
	private static function resolve_xml_node( $node, $key ) {

		if ( isset( $node[0]['_loopable_xml_text'] ) ) {
			return $node[0]['_loopable_xml_text'];
		}

		// Attribute-only XML elements (e.g. <media:thumbnail url="..."/>) carry no text
		// node. Resolve '.element_value' to the 'url' attribute when present, otherwise
		// fall back to the first attribute value.
		if ( 'element_value' === $key && isset( $node[0]['@attributes'] ) ) {
			$attrs = $node[0]['@attributes'];
			return $attrs['url'] ?? (string) reset( $attrs );
		}

		return $node;
	}

	/**
	 * Extracts the loopable item index number from a string if it matches a specific pattern.
	 *
	 * @param mixed $value The value potentially containing the loopable item index.
	 *
	 * @return int|null The extracted index number, or null if not found.
	 */
	public static function extract_loopable_item_index_number( $value ) {
		// Return null if the provided value is not a valid string.
		if ( ! is_string( $value ) ) {
			return null;
		}

		// Check if the string matches the pattern 'loopable_item_index_#'.
		if ( preg_match( '/^loopable_item_index_(\d+)$/', $value, $matches ) ) {
			return (int) $matches[1]; // Return the extracted number as an integer.
		}

		return null; // Return null if the pattern does not match.
	}
}
