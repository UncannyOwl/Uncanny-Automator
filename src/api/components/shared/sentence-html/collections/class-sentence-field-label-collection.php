<?php
/**
 * Sentence Field Label Collection.
 *
 * Manages a collection of sentence field label value objects.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections;

use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Field_Label;

/**
 * Class Sentence_Field_Label_Collection
 *
 * Collection for managing sentence field label value objects.
 */
class Sentence_Field_Label_Collection {

	/**
	 * Field labels array.
	 *
	 * @var Sentence_Field_Label[]
	 */
	private array $field_labels = array();

	/**
	 * Add a field label to the collection.
	 *
	 * @param Sentence_Field_Label $field_label The field label to add.
	 *
	 * @return void
	 */
	public function add( Sentence_Field_Label $field_label ): void {
		$this->field_labels[] = $field_label;
	}

	/**
	 * Remove a field label from the collection.
	 *
	 * @param Sentence_Field_Label $field_label The field label to remove.
	 *
	 * @return void
	 */
	public function remove( Sentence_Field_Label $field_label ): void {
		$code_to_remove = $field_label->get_value()['code'];

		$this->field_labels = array_values(
			array_filter(
				$this->field_labels,
				static function ( Sentence_Field_Label $fl ) use ( $code_to_remove ): bool {
					return $fl->get_value()['code'] !== $code_to_remove;
				}
			)
		);
	}

	/**
	 * Get a field label by code.
	 *
	 * @param string $code The field code to retrieve.
	 *
	 * @return Sentence_Field_Label|null The field label if found, null otherwise.
	 */
	public function get( string $code ): ?Sentence_Field_Label {
		foreach ( $this->field_labels as $field_label ) {
			if ( $field_label->get_value()['code'] === $code ) {
				return $field_label;
			}
		}

		return null;
	}

	/**
	 * Get all field labels in the collection.
	 *
	 * @return Sentence_Field_Label[] Array of all field labels.
	 */
	public function all(): array {
		return $this->field_labels;
	}

	/**
	 * Get the count of field labels in the collection.
	 *
	 * @return int The number of field labels.
	 */
	public function count(): int {
		return count( $this->field_labels );
	}

	/**
	 * Check if the collection is empty.
	 *
	 * @return bool True if the collection is empty, false otherwise.
	 */
	public function is_empty(): bool {
		return $this->count() === 0;
	}

	/**
	 * Convert collection to label map.
	 *
	 * Returns a map of field codes to labels for use by sentence services.
	 *
	 * @return array Map of field codes to labels (e.g., ['LDCOURSE' => 'Course']).
	 */
	public function to_label_map(): array {
		$map = array();

		foreach ( $this->field_labels as $field_label ) {
			$data                 = $field_label->get_value();
			$map[ $data['code'] ] = $data['label'];
		}

		return $map;
	}
}
