<?php
namespace Uncanny_Automator\Services\Recipe\Builder\Settings\Fields;

use Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Settings_Repository;

class Field_Collection_Manager extends Field_Manager {

	protected $id = '';

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function get_id() {
		return $this->id;
	}
	/**
	 * Save a field.
	 *
	 * @param Field $field The field to save.
	 * @throws \Exception If required properties are not set.
	 */
	public function save_field_collection( Field_Collection $field_collection ) {
		$this->field_repository->save_field_collection( $field_collection );
	}

	/**
	 * @return array
	 */
	public function get_field_collection() {
		return $this->field_repository->get_field_collection();
	}

	/**
	 * Delete a field.
	 *
	 * @param int    $recipe_id The recipe ID.
	 * @param string $field_id  The field ID.
	 */
	public function delete_field_collection( int $recipe_id, string $field_id ) {
		$this->field_repository->delete_field( $recipe_id, $field_id );
	}

	/**
	 * Update a field's value.
	 *
	 * @param int    $recipe_id The recipe ID.
	 * @param string $field_id  The field ID.
	 * @param mixed  $value     The new value for the field.
	 *
	 * @throws \Exception If the field does not exist.
	 */
	public function update_field_collection_value( int $recipe_id, string $field_id, $value ) {
		$field = $this->get_field( $recipe_id, $field_id );

		if ( ! $field ) {
			throw new \Exception(
				sprintf(
					'Field not found: %s',
					esc_html( $field_id )
				)
			);
		}

		$field->set_value( $value );
		$this->save_field( $field );
	}
}
