<?php
namespace Uncanny_Automator\Services\Recipe\Builder\Settings\Repository;

use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Collection;

class Settings_Repository {

	protected $recipe_id  = null;
	protected $setting_id = null;

	public function set_recipe_id( int $recipe_id ) {
		$this->recipe_id = $recipe_id;
	}

	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Get the value of setting_id
	 */
	public function get_setting_id() {
		return $this->setting_id;
	}

	/**
	 * Set the value of setting_id
	 *
	 * @return  self
	 */
	public function set_setting_id( $setting_id ) {
		$this->setting_id = $setting_id;

		return $this;
	}

	/**
	 * Save a Field to post meta.
	 *
	 * @param Field $field The field to save.
	 * @throws \Exception If required properties are not set.
	 */
	public function save_field( Field $field ) {

		$field->get_field(); // Ensures all required properties are set.

		$recipe_id = $this->get_recipe_id();

		if ( empty( $recipe_id ) ) {
			throw new \Exception( 'Invalid recipe id. ' );
		}
		if ( ! $recipe_id || ! get_post( $recipe_id ) ) {
			throw new \Exception( 'Invalid recipe_id provided for saving the field.' );
		}

		$meta_key   = 'field_' . $field->get_field_id();
		$meta_value = array(
			'type'   => $field->get_type(),
			'value'  => $field->get_value(),
			'backup' => $field->get_backup(),
		);

		update_post_meta( $recipe_id, $meta_key, $meta_value );
	}

	/**
	 * @param Field_Collection $field_collection
	 *
	 * @return int|bool
	 */
	public function save_field_collection( Field_Collection $field_collection ) {
		return update_post_meta( $this->get_recipe_id(), 'field_' . $this->get_setting_id(), $field_collection->get_fields_formatted() );
	}

	/**
	 * @return array
	 */
	public function get_field_collection() {
		return (array) get_post_meta( $this->get_recipe_id(), 'field_' . $this->get_setting_id(), true );
	}

	/**
	 * Retrieve a Field from post meta.
	 *
	 * @param int    $recipe_id  The recipe ID.
	 * @param string $field_id   The field ID.
	 *
	 * @return Field|null The retrieved field or null if not found.
	 */
	public function get_field( int $recipe_id, string $field_id ) {

		if ( ! $recipe_id || ! get_post( $recipe_id ) ) {
			return null;
		}

		$meta_key   = 'field_' . $field_id;
		$field_data = get_post_meta( $recipe_id, $meta_key, true );

		if ( ! is_array( $field_data ) || empty( $field_data ) ) {
			return null; // Field not found.
		}

		$field = new Field();
		$field->set_field_id( $field_id );
		$field->set_type( $field_data['type'] );
		$field->set_value( $field_data['value'] );
		$field->set_backup( isset( $field_data['backup'] ) && is_array( $field_data['backup'] ) ? $field_data['backup'] : array() );

		return $field;
	}

	/**
	 * Delete a Field from post meta.
	 *
	 * @param int    $recipe_id  The recipe ID.
	 * @param string $field_id   The field ID.
	 */
	public function delete_field( int $recipe_id, string $field_id ) {

		if ( ! $recipe_id || ! get_post( $recipe_id ) ) {
			return;
		}

		$meta_key = 'field_' . $field_id;

		delete_post_meta( $recipe_id, $meta_key );
	}
}
