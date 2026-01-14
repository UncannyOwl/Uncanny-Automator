<?php
/**
 * Sentence Field Value Text Collection.
 *
 * Manages a collection of sentence field value text value objects.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections;

use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Field_Value_Text;

/**
 * Class Sentence_Field_Value_Text_Collection
 *
 * Collection for managing sentence field value text value objects.
 */
class Sentence_Field_Value_Text_Collection {

	/**
	 * Field values array.
	 *
	 * @var Sentence_Field_Value_Text[]
	 */
	private array $field_values = array();

	/**
	 * Add a field value to the collection.
	 *
	 * @param Sentence_Field_Value_Text $field_value The field value to add.
	 *
	 * @return void
	 */
	public function add( Sentence_Field_Value_Text $field_value ): void {
		$this->field_values[] = $field_value;
	}

	/**
	 * Remove a field value from the collection.
	 *
	 * Identifies by code to avoid object identity problems.
	 *
	 * @param Sentence_Field_Value_Text $field_value The field value to remove.
	 *
	 * @return void
	 */
	public function remove( Sentence_Field_Value_Text $field_value ): void {
		$code_to_remove = array_key_first( $field_value->get_value() );

		$this->field_values = array_values(
			array_filter(
				$this->field_values,
				static function ( Sentence_Field_Value_Text $fv ) use ( $code_to_remove ): bool {
					return array_key_first( $fv->get_value() ) !== $code_to_remove;
				}
			)
		);
	}

	/**
	 * Get a field value by code.
	 *
	 * @param string $code The field code to retrieve.
	 *
	 * @return Sentence_Field_Value_Text|null The field value if found, null otherwise.
	 */
	public function get( string $code ): ?Sentence_Field_Value_Text {
		foreach ( $this->field_values as $field_value ) {
			if ( array_key_first( $field_value->get_value() ) === $code ) {
				return $field_value;
			}
		}
		return null;
	}

	/**
	 * Get all field values in the collection.
	 *
	 * @return Sentence_Field_Value_Text[] Array of all field values.
	 */
	public function all(): array {
		return $this->field_values;
	}

	/**
	 * Get the count of field values in the collection.
	 *
	 * @return int The number of field values.
	 */
	public function count(): int {
		return count( $this->field_values );
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
	 * Convert collection to fields array.
	 *
	 * Converts all child value objects into the fields structure that
	 * Sentence_Human_Readable_Service expects.
	 *
	 * @return array {
	 *     Array of field data keyed by field code.
	 *
	 *     @type array $code {
	 *         Field data for the given code.
	 *
	 *         @type mixed  $value The raw field value.
	 *         @type string $text  The human-readable text.
	 *     }
	 * }
	 */
	public function to_fields_array(): array {
		$fields = array();

		foreach ( $this->field_values as $field_value ) {
			$fields = array_merge( $fields, $field_value->get_value() );
		}

		return $fields;
	}
}
