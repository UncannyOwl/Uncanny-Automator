<?php
namespace Uncanny_Automator\Services\Recipe\Process;

use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Manager;

class User_Run_Number_Threshold {

	private const UNLIMITED_RUN_LIMIT_NUMBER_LEGACY = -1;

	protected $recipe_id;
	protected $completed_times;
	protected $field_manager;
	protected $legacy_limit_meta = 'recipe_completions_allowed';
	protected $field_id          = 'recipe_times_per_user';

	public function __construct( Field_Manager $field_manager ) {
		$this->field_manager = $field_manager;
	}

	public function get_field_id() {
		return $this->field_id;
	}

	/**
	 * Get the recipe ID.
	 *
	 * @return mixed
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Set the recipe ID.
	 *
	 * @param mixed $recipe_id
	 * @return void
	 */
	public function set_recipe_id( int $recipe_id ) {
		$this->recipe_id = $recipe_id;
	}

	/**
	 * Get the user completed times.
	 *
	 * @return mixed
	 */
	public function get_completed_times() {
		return $this->completed_times;
	}

	/**
	 * Set the user completed times.
	 *
	 * @param int $completed_times
	 * @return void
	 */
	public function set_completed_times( int $completed_times ) {
		$this->completed_times = $completed_times;
	}

	/**
	 * Check if the user has reached the limit.
	 *
	 * @return bool
	 */
	public function has_run_times_reached_limit() {

		$field = $this->field_manager->get_field( $this->get_recipe_id(), $this->field_id );

		// Get run limit based on field type
		$run_limit = $this->get_run_limit( $field );

		// Check if unlimited runs are allowed
		if ( $this->has_unlimited_run_limit( $run_limit ) ) {
			return false;
		}

		// Compare completed runs against limit.
		return $this->get_completed_times() >= $run_limit;
	}

	/**
	 * Get the field value.
	 *
	 * @param Field $field The field object.
	 * @return int The field value.
	 */
	public function get_field_value( Field $field ) {
		return $field->get_value();
	}

	/**
	 * Get the field value legacy.
	 *
	 * @return int The field value.
	 */
	public function get_field_value_legacy(): int {
		$legacy_limit = get_post_meta( $this->get_recipe_id(), $this->legacy_limit_meta, true );

		// If there is no value from the legacy meta, return unlimited.
		if ( '' === $legacy_limit || ! is_numeric( $legacy_limit ) ) {
			return -1;
		}

		return intval( $legacy_limit );
	}

	/**
	 * Backwards compatibility: Get the limit value.
	 *
	 * @return int
	 */
	public function backwards_compat_get_limit_value( Field $field ): int {

		$recipe_id = $this->get_recipe_id();

		if ( is_null( $recipe_id ) ) {
			return false;
		}

		$field_recipe_times         = $this->get_field_value( $field );
		$completions_allowed_legacy = $this->get_field_value_legacy();

		// If there is a value on the new field, return it.
		if ( '' !== $field_recipe_times ) {
			return intval( $field_recipe_times );
		}

		// If there is no value from the new field and no value from legacy, return unlimited.
		if ( '' === $completions_allowed_legacy ) {
			return -1;
		}

		// Otherwise, if there is no value from the new field, has value from legacy, return it.
		return intval( $completions_allowed_legacy );
	}

	/**
	 * Gets the run limit value based on field type
	 *
	 * @param mixed $field The field object
	 * @return int The run limit value
	 */
	private function get_run_limit( $field ): int {

		// Backwards compatibility.
		if ( $field instanceof Field ) {
			return intval( $this->backwards_compat_get_limit_value( $field ) );
		}

		return intval( $this->get_field_value_legacy() );
	}

	private function has_unlimited_run_limit( $value ): bool {
		return self::UNLIMITED_RUN_LIMIT_NUMBER_LEGACY === $value;
	}
}
