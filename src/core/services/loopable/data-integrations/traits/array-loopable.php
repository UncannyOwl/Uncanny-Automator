<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Services\Loopable\Data_Integrations\Traits;

use Uncanny_Automator\Services\Loopable\Data_Integrations\Array_Group_Classifier;
use Uncanny_Automator\Services\Loopable\Data_Integrations\Utils;
use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;

/**
 * Trait Array_Loopable
 *
 * Handles the creation of loopable items from arrays, based on classification of the array.
 * This trait supports different array structures and creates loopable tokens for each item.
 *
 * @package Uncanny_Automator\Services\Loopable\Data_Integrations\Traits
 */
trait Array_Loopable {

	/**
	 * Create loopable items from a given array and classification.
	 *
	 * This method processes the array based on its classification and populates the
	 * loopable token collection with appropriate items.
	 *
	 * @param Loopable_Token_Collection $loopable       The collection of loopable tokens to be populated.
	 * @param array                     $loopable_array The array to classify and create loopable items from.
	 *
	 * @return Loopable_Token_Collection The populated loopable token collection.
	 */
	public static function create_loopables( Loopable_Token_Collection $loopable, array $loopable_array ) {

		// Classify the array to determine its structure.
		// @see Notion<Token-loops-data-integrations-array-classification-101a5c56a2ef80838c5bc7c8462e3c54>.
		$classification = Array_Group_Classifier::classify_array( $loopable_array );

		// Sanitize the array to ensure it is safe for JSON usage.
		$loopable_array = Utils::sanitize_array_for_json( $loopable_array );

		// Regular loopable items: iterable arrays with the same number of elements and keys for each element.
		if ( 'g1' === $classification ) {
			foreach ( $loopable_array as $item ) {
				$loopable->create_item( $item ); // Create a loopable item for each element.
			}
		}

		// Classification g2a: the array elements are accessed directly and are not safe for iteration.
		if ( 'g2a' === $classification ) {
			$loopable->create_item( $loopable_array ); // Create a single loopable item from the entire array.
		}

		// Classification g2b: loopable items for arrays that contain strictly scalar values.
		if ( 'g2b' === $classification ) {
			foreach ( $loopable_array as $item ) {
				$_item = array( $item );
				$loopable->create_item( $_item ); // Wrap each scalar value in an array and create an item.
			}
		}

		// Classification g2c: loopable items that require specific conditions, such as mixed scalar and array values.
		if ( 'g2c' === $classification ) {
			foreach ( $loopable_array as $item ) {
				$_item = $item;
				// If the item is scalar, wrap it in an array before creating a loopable item.
				if ( is_scalar( $item ) ) {
					$_item = array( $item );
					$loopable->create_item( $_item );
					continue;
				}
				$loopable->create_item( $item ); // Otherwise, create an item directly from the array element.
			}
		}

		return $loopable; // Return the populated loopable token collection.
	}
}
