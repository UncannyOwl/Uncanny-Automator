<?php
namespace Uncanny_Automator\Services\Recipe\Builder\Settings\Fields;

use Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Settings_Repository;

class Field_Manager {

	protected $field_repository;

	public function __construct( Settings_Repository $field_repository ) {
		$this->field_repository = $field_repository;
	}

	public static function create_instance( $recipe_id ) {
		$repo = new Settings_Repository();
		$repo->set_recipe_id( $recipe_id );
		return new self( $repo );
	}

	/**
	 * Save a field.
	 *
	 * @param Field $field The field to save.
	 * @throws \Exception If required properties are not set.
	 */
	public function save_field( Field $field ) {
		$this->field_repository->save_field( $field );
	}

	/**
	 * Retrieve a field.
	 *
	 * @param int    $recipe_id The recipe ID.
	 * @param string $field_id  The field ID.
	 *
	 * @return Field|null The retrieved field or null if not found.
	 */
	public function get_field( int $recipe_id, string $field_id ) {
		return $this->field_repository->get_field( $recipe_id, $field_id );
	}

	/**
	 * Delete a field.
	 *
	 * @param int    $recipe_id The recipe ID.
	 * @param string $field_id  The field ID.
	 */
	public function delete_field( int $recipe_id, string $field_id ) {
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
	public function update_field_value( int $recipe_id, string $field_id, $value ) {
		$field = $this->get_field( $recipe_id, $field_id );

		if ( ! $field ) {
			throw new \Exception(
				'Field not found: ' . esc_html( $field_id )
			);
		}

		$field->set_value( $value );
		$this->save_field( $field );
	}
}
