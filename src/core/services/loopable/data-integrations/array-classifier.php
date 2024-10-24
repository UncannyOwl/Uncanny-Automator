<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations;

/**
 * Class Array_Group_Classifier
 *
 * This class classifies arrays into different groups based on their structure.
 * Group classifications are used to determine how arrays can be processed
 * for loopable tokens.
 *
 * @package Uncanny_Automator\Services\Loopable\Data_Integrations
 */
class Array_Group_Classifier {

	/**
	 * Classify the array into a group.
	 *
	 * This method classifies an array into one of four groups:
	 * - 'g1'  : Iterable arrays (Group 1).
	 * - 'g2a' : Direct access associative arrays (Group 2A).
	 * - 'g2b' : Arrays of scalar values (Group 2B).
	 * - 'g2c' : Arrays with mixed content (Group 2C).
	 *
	 * @param array $array Array to be classified.
	 *
	 * @return string Group classification ('g1', 'g2a', 'g2b', or 'g2c').
	 */
	public static function classify_array( $array ) {

		// Check if the array belongs to Group 1 (Iterables).
		if ( self::is_group_1( $array ) ) {
			return 'g1';
		}

		// Check if the array belongs to Group 2A (Direct Access).
		if ( self::is_group_2a( $array ) ) {
			return 'g2a';
		}

		// Check if the array belongs to Group 2B (Loopables with scalar values).
		if ( self::is_group_2b( $array ) ) {
			return 'g2b';
		}

		// If none of the above, it falls under Group 2C (Mixed Content).
		return 'g2c';
	}

	/**
	 * Check if the array belongs to Group 1 (Iterables).
	 *
	 * Group 1 arrays contain only other arrays, making them fully iterable.
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if the array belongs to Group 1, false otherwise.
	 */
	private static function is_group_1( $array ) {
		if ( ! is_array( $array ) ) {
			return false;
		}

		// Check if all elements in the array are arrays.
		foreach ( $array as $value ) {
			if ( ! is_array( $value ) ) {
				return false; // If any element is not an array, it's not Group 1.
			}
		}

		return true;
	}

	/**
	 * Check if the array belongs to Group 2A (Direct Access Associative Arrays).
	 *
	 * Group 2A arrays are associative arrays where elements can be accessed directly.
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if the array belongs to Group 2A, false otherwise.
	 */
	private static function is_group_2a( $array ) {
		if ( ! is_array( $array ) || ! self::is_associative( $array ) ) {
			return false;
		}

		// Associative arrays with scalar values or mixed content.
		return true;
	}

	/**
	 * Check if the array belongs to Group 2B (Array of Scalar Values).
	 *
	 * Group 2B arrays contain only scalar values (e.g., integers, strings).
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if the array belongs to Group 2B, false otherwise.
	 */
	private static function is_group_2b( $array ) {
		if ( ! is_array( $array ) || self::is_associative( $array ) ) {
			return false;
		}

		// Check if all elements in the array are scalar values.
		return self::all_scalars( $array );
	}

	/**
	 * Check if an array is associative.
	 *
	 * An array is considered associative if its keys are not a contiguous range of integers starting from 0.
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if the array is associative, false otherwise.
	 */
	private static function is_associative( $array ) {
		if ( empty( $array ) ) {
			return false;
		}

		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}

	/**
	 * Check if all elements in an array are scalar values.
	 *
	 * Scalar values include integers, strings, booleans, and floats.
	 *
	 * @param array $array The array to check.
	 *
	 * @return bool True if all elements are scalar values, false otherwise.
	 */
	private static function all_scalars( $array ) {
		foreach ( $array as $value ) {
			if ( ! is_scalar( $value ) ) {
				return false;
			}
		}

		return true;
	}
}
