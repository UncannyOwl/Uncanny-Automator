<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

/**
 * Class Utils.
 * Provides utility functions for handling various data formats, particularly focused on JSON and array conversions.
 *
 * @package uncanny-automator
 */
class Utils {

	/**
	 * Safely converts the input to a string, handling special cases for arrays and booleans.
	 *
	 * @param mixed $input The input to convert.
	 * @param bool $as_json Whether to convert arrays and objects into JSON. Otherwise, serialized.
	 *
	 * @return string The converted or original string.
	 */
	public static function convert_to_string( $input = '', $as_json = false ) {

		// Return the input if it's already a string.
		if ( is_string( $input ) ) {
			return $input;
		}

		// Handle boolean inputs.
		if ( is_bool( $input ) ) {
			return $input ? 'true' : 'false'; // Return "true" or "false" as a string.
		}

		// Handle array and object inputs.
		if ( is_object( $input ) || is_array( $input ) ) {
			if ( true === $as_json ) {
				return wp_json_encode( $input );
			}
			return maybe_serialize( $input );
		}

		// Safely convert non-string and non-special inputs to a string.
		return strval( $input );

	}

	/**
	 * Recursively finds and returns the keys from the first object it encounters in the data array.
	 *
	 * @param mixed $data The array or object to scan.
	 * @return array The keys of the first object encountered, or an empty array if none is found.
	 */
	public static function get_first_object_keys( $data ) {

		if ( is_array( $data ) ) {
			// If the array is associative, return its keys.
			if ( array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) {
				return array_keys( $data );
			}

			// Otherwise, iterate through the array elements.
			foreach ( $data as $item ) {
				$result = self::get_first_object_keys( $item );
				if ( ! empty( $result ) ) {
					return $result;
				}
			}
		}

		return array(); // Return an empty array if no object is found.
	}

	/**
	 * Identifies and returns the keys or key-value pair from the JSON structure.
	 * If the path resolves to an array of objects, it will check if all elements have identical keys and return those keys.
	 * If the path resolves to an array of scalars, it returns array('Current_item_value').
	 *
	 * @param string $json The JSON string.
	 * @param string $path The dot notation path to traverse the JSON structure.
	 * @return array The keys of the JSON object, or key-value pair if the path resolves to a scalar, or array('Current_item_value') if the path resolves to an array of scalars.
	 */
	public static function identify_keys( $json, $path ) {

		if ( ! is_string( $json ) ) {
			return array();
		}

		// Decode the JSON into an associative array.
		$data = json_decode( $json, true );

		// Handle decoding errors.
		if ( null === $data && json_last_error() !== JSON_ERROR_NONE ) {
			return array();
		}

		// If the path is just '$.', scan for the first object and get its keys.
		if ( '$.' === trim( $path ) ) {

			// If it's an array of associative arrays with identical keys, return the common keys.
			if ( is_array( $data ) && self::is_assoc( $data ) && self::is_associative_with_identical_keys( $data ) ) {
				return array_keys( reset( $data ) ); // Return the keys of the first element (all have the same keys).
			}

			return self::get_first_object_keys( $data );
		}

		// Replace array index notations (e.g., items[1]) with dot notation (e.g., items.1).
		$path = preg_replace( '/\[(\d+)\]/', '.$1', $path );

		// Split the path into keys (remove $ and split by dot).
		$keys = explode( '.', trim( $path, '$.' ) );

		// Traverse the array based on the path provided.
		$current_data = $data;
		foreach ( $keys as $key ) {
			if ( is_array( $current_data ) && array_key_exists( $key, $current_data ) ) {
				$current_data = $current_data[ $key ];
			} else {
				return array();  // Return an empty array if the path is invalid.
			}
		}

		// If it's an associative array, return its keys.
		if ( is_array( $current_data ) && self::is_assoc( $current_data ) ) {
			return array_keys( $current_data );
		}

		// If it's an array of associative arrays with identical keys, return the common keys.
		if ( is_array( $current_data ) && ! self::is_assoc( $current_data ) && self::is_associative_with_identical_keys( $current_data ) ) {
			return array_keys( reset( $current_data ) ); // Return the keys of the first element (all have the same keys).
		}

		// If the current data is an indexed array (not associative), return array('Current_item_value').
		if ( is_array( $current_data ) && ! self::is_assoc( $current_data ) ) {
			return array( 'current_item_value' );  // Single item placeholder.
		}

		// If the current data is scalar (string, number, boolean), return the key-value pair.
		if ( is_scalar( $current_data ) ) {
			return array( $key => '_SCALAR_' );
		}

		// Return an empty array if the result is not an array or the path is invalid.
		return array();
	}

	/**
	 * Determines if the given array is associative and all its elements are arrays with identical keys.
	 *
	 * @param array $array The array to check.
	 * @return bool True if the array is associative and all elements are arrays with identical keys, false otherwise.
	 */
	public static function is_associative_with_identical_keys( $array ) {

		// Ensure the input is a non-empty array.
		if ( ! is_array( $array ) || empty( $array ) ) {
			return false;
		}

		// Get the keys of the first element for comparison.
		$first_element = reset( $array );

		// Ensure the first element is an associative array (not indexed).
		if ( ! is_array( $first_element ) || array_keys( $first_element ) === range( 0, count( $first_element ) - 1 ) ) {
			return false;
		}

		// Extract the keys from the first element for comparison.
		$first_keys = array_keys( $first_element );

		// Iterate through the rest of the array elements to ensure they are associative arrays with identical keys.
		foreach ( $array as $element ) {
			// Ensure the element is an associative array.
			if ( ! is_array( $element ) || array_keys( $element ) === range( 0, count( $element ) - 1 ) ) {
				return false;  // The element is not associative or has a different structure.
			}

			// Compare the keys of the current element with the first element's keys.
			if ( array_keys( $element ) !== $first_keys ) {
				return false;  // The keys differ, so the arrays are not identical.
			}
		}

		// If all elements passed the checks, return true.
		return true;
	}

	/**
	 * Helper function to check if an array is associative.
	 *
	 * @param array $array The array to check.
	 * @return bool True if the array is associative, false if indexed.
	 */
	public static function is_assoc( $array ) {
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	/**
	 * Sanitizes array for JSON.
	 *
	 * This function can be slow. So use sparingly.
	 *
	 * @param mixed[] $array The array to sanitize.
	 *
	 * @return array|object The sanitized array or object.
	 */
	public static function sanitize_array_for_json( $array ) {

		// Recursively sanitize all values in the array.
		array_walk_recursive(
			$array,
			function ( &$value ) {
				if ( is_string( $value ) ) {
					// Ensure valid UTF-8 encoding.
					$value = mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );

					// Convert newlines to literal `\n`.
					$value = str_replace( array( "\r", "\n" ), "\\n", $value );

					// Optionally encode HTML entities to prevent issues with HTML characters in JSON.
					$value = htmlentities( $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8' );

					// Remove any control characters that might break the JSON encoding.
					$value = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );
				}
			}
		);

		return $array;
	}

	/**
	 * Retrieves the top-level paths for iterable values in the array or object.
	 *
	 * @param mixed[] $data The input data.
	 * @param string $prefix The path prefix, defaults to '$'.
	 *
	 * @return mixed[] The top-level iterable paths, excluding numeric keys.
	 */
	public static function get_json_array_paths( $data, $prefix = '$' ) {

		$paths = array();

		if ( is_array( $data ) || is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				// Skip numeric keys (indexed arrays).
				if ( is_int( $key ) ) {
					continue; // Skip numeric keys since they represent indexed arrays.
				}

				$current_path = $prefix . '.' . $key;

				// Add to paths only if the value is iterable (array or object).
				if ( is_array( $value ) || is_object( $value ) ) {
					$paths[] = $current_path;
				}
			}
		}

		return $paths; // Return only root-level iterable paths, excluding numeric keys.

	}

	/**
	 * Retrieves all nested paths for iterable values in the array or object.
	 *
	 * @param mixed[] $data The input data.
	 * @param string $prefix The path prefix, defaults to '$'.
	 *
	 * @return mixed[] The full iterable paths, including nested ones.
	 */
	public static function get_all_iterable_paths( $data, $prefix = '$' ) {

		$paths = array();

		// Check if the input is an array or object.
		if ( is_array( $data ) || is_object( $data ) ) {

			foreach ( $data as $key => $value ) {
				// Skip numeric keys (indexed arrays).
				if ( is_int( $key ) ) {
					continue; // Skip numeric keys as they are unpredictable.
				}

				// Construct the current path.
				$current_path = $prefix . '.' . $key;

				// Add to paths if the value is iterable (array or object).
				if ( is_array( $value ) || is_object( $value ) ) {
					$paths[] = $current_path;

					// Recursively process nested arrays/objects.
					$paths = array_merge( $paths, self::get_all_iterable_paths( $value, $current_path ) );
				}
			}
		}

		return $paths; // Return all iterable paths including nested ones.
	}

	/**
	 * Encode non-ASCII characters in the URL path, query, and fragment.
	 *
	 * @param string $url The original URL to encode.
	 * @return string|false Returns the properly encoded URL, or false if the URL can't be parsed.
	 */
	public static function encode_url( $url ) {

		// Parse the URL into components (scheme, host, path, etc.).
		$parsed_url = wp_parse_url( $url );

		if ( false === $parsed_url ) {
			return false; // If the URL can't be parsed, return false.
		}

		// Handle URL components
		$scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host   = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port   = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';

		// Encode the path and query separately to handle non-ASCII characters.
		$path  = isset( $parsed_url['path'] ) ? implode( '/', array_map( 'rawurlencode', explode( '/', $parsed_url['path'] ) ) ) : '';
		$query = isset( $parsed_url['query'] ) ? '?' . rawurlencode( $parsed_url['query'] ) : '';

		// Handle fragment if present.
		$fragment = isset( $parsed_url['fragment'] ) ? '#' . rawurlencode( $parsed_url['fragment'] ) : '';

		// Rebuild the URL.
		$encoded_url = $scheme . $host . $port . $path . $query . $fragment;

		return $encoded_url;
	}

}
