<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

/**
 * Class responsible for detecting and handling keys within arrays.
 * It differentiates between associative, indexed, and XML-structured arrays.
 *
 * @package Uncanny_Automator\Services\Loopable\Data_Integrations
 */
class Array_Key_Detector {

	/**
	 * Detect keys in an array, handling both associative and indexed arrays,
	 * and special cases like '_loopable_xml_text'.
	 *
	 * @param array $array The input array.
	 *
	 * @return array The detected keys or 'current_item_value' if no keys are found.
	 */
	public static function detect_keys( $array ) {

		// If the input is not an array, return 'current_item_value' (for scalar values).
		if ( ! is_array( $array ) ) {
			return array( 'current_item_value' );
		}

		// Handle associative arrays.
		if ( self::is_associative( $array ) ) {

			// Special XML parsing case for arrays with '_loopable_xml_text'.
			if ( self::contains_loopable_xml_text_recursive( $array ) ) {
				return self::map_element_values( $array );
			}

			// Return all keys for associative arrays, even if '_loopable_xml_text' is absent.
			return self::collect_all_keys( $array );
		}

		// Handle indexed arrays.
		foreach ( $array as $value ) {

			// Process if the indexed array contains associative elements.
			if ( is_array( $value ) && self::is_associative( $value ) ) {

				// Check for special XML case in the subarray.
				if ( self::contains_loopable_xml_text_recursive( $value ) ) {
					return self::map_element_values( $value );
				}

				// Otherwise, collect keys normally.
				return self::collect_all_keys( $value );
			}
		}

		// If it's a non-associative array, return 'current_item_value' for scalar entries.
		return array( 'current_item_value' );
	}

	/**
	 * Recursively checks if an associative array contains the key '_loopable_xml_text' at any depth.
	 *
	 * @param array $array The array to search within.
	 *
	 * @return bool True if '_loopable_xml_text' is found, false otherwise.
	 */
	private static function contains_loopable_xml_text_recursive( $array ) {
		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) && isset( $value['_loopable_xml_text'] ) ) {
				return true;
			}
			if ( is_array( $value ) && self::contains_loopable_xml_text_recursive( $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Maps array keys by appending '.element_value', skipping multi-element arrays.
	 *
	 * @param array $array The array whose keys will be mapped.
	 *
	 * @return array The mapped keys.
	 */
	private static function map_element_values( $array ) {
		$mapped_keys = array();
		foreach ( array_keys( $array ) as $key ) {

			// Only map associative keys, skip numeric keys, and arrays with multiple elements.
			if ( ! is_int( $key ) && ( count( $array[ $key ] ) === 1 ) ) {
				$mapped_keys[] = $key . '.element_value';
			}
		}
		return $mapped_keys;
	}

	/**
	 * Retrieves nested keys from an array's subarrays.
	 *
	 * @param array $array The array to extract keys from.
	 *
	 * @return array The unique keys from subarrays.
	 */
	private static function get_nested_keys( $array ) {
		$keys = array();
		foreach ( $array as $value ) {
			if ( is_array( $value ) && self::is_associative( $value ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					if ( is_array( $sub_value ) && self::is_associative( $sub_value ) ) {
						foreach ( $sub_value as $inner_key => $inner_value ) {
							$keys[] = "{$sub_key}.{$inner_key}";
						}
					} else {
						$keys[] = "{$sub_key}";
					}
				}
			}
			break; // Only check the first sub-array if they all have the same keys.
		}
		return array_unique( $keys );
	}

	/**
	 * Collects all keys from an associative array, optionally with a prefix.
	 *
	 * @param array  $array  The array from which to collect keys.
	 * @param string $prefix The prefix to append to keys (default is empty).
	 *
	 * @return array The collected keys.
	 */
	private static function collect_all_keys( $array, $prefix = '' ) {
		$keys = array();
		foreach ( $array as $key => $value ) {
			$full_key = $prefix ? "{$prefix}.{$key}" : $key;

			if ( is_array( $value ) && self::is_associative( $value ) ) {
				$keys = array_merge( $keys, self::collect_all_keys( $value, $full_key ) );
			} else {
				$keys[] = $full_key;
			}
		}
		return $keys;
	}

	/**
	 * Determines if all subarrays of an array have the same keys.
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if all subarrays have the same keys, false otherwise.
	 */
	private static function all_subarrays_have_same_keys( $array ) {
		$first_keys = null;
		foreach ( $array as $subarray ) {
			if ( ! is_array( $subarray ) || ! self::is_associative( $subarray ) ) {
				return false;
			}
			if ( null === $first_keys ) {
				$first_keys = array_keys( $subarray );
			} elseif ( $first_keys !== array_keys( $subarray ) ) { //phpcs:ignore WordPress.PHP.YodaConditions.NotYoda
				return false;
			}
		}
		return true;
	}

	/**
	 * Checks if an array has any associative subarrays.
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if associative subarrays exist, false otherwise.
	 */
	private static function has_associative_subarrays( $array ) {
		foreach ( $array as $value ) {
			if ( is_array( $value ) && self::is_associative( $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if an array is associative (i.e., contains non-sequential keys).
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if the array is associative, false if it's indexed.
	 */
	private static function is_associative( $array ) {
		if ( empty( $array ) ) {
			return false;
		}
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}
}
