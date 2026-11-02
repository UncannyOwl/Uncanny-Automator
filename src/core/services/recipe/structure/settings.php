<?php
namespace Uncanny_Automator\Services\Recipe\Structure;

use Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Collection_Manager;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Settings_Repository;
use Uncanny_Automator\Services\Recipe\Common;

/**
 * Represents the settings object inside the recipe object.
 *
 * Contains recipe settings like run limits and throttling configuration.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure
 *
 * @since 6.9
 */
final class Settings implements \JsonSerializable {

	/**
	 * @since 6.9
	 */
	use Common\Trait_JSON_Serializer;

	/**
	 * @since 6.9
	 */
	use Common\Trait_Setter_Getter;

	/**
	 * Total times the recipe can run (across all users).
	 *
	 * @var \stdClass
	 */
	protected $recipe_total_times;

	/**
	 * Limit per user - how many times a single user can trigger the recipe.
	 *
	 * @var \stdClass
	 */
	protected $limit_per_user;

	/**
	 * Recipe throttle settings.
	 *
	 * @var \stdClass
	 */
	protected $recipe_throttle;

	/**
	 * @var \Uncanny_Automator\Services\Recipe\Structure
	 */
	private static $recipe = null;

	/**
	 * @param \Uncanny_Automator\Services\Recipe\Structure $recipe The recipe structure object.
	 */
	public function __construct( \Uncanny_Automator\Services\Recipe\Structure $recipe ) {
		self::$recipe = $recipe;

		// We use `new \stdClass()` instead of `(object) array()` to ensure JSON serialization
		// produces `{}` (empty object) rather than `[]` (empty array). PHP's json_encode()
		// can inconsistently serialize `(object) array()` as an array in some contexts.
		$this->recipe_total_times = new \stdClass();
		$this->limit_per_user     = new \stdClass();
		$this->recipe_throttle    = new \stdClass();

		$this->hydrate_properties();
	}

	/**
	 * Hydrates object properties.
	 *
	 * @return self
	 */
	private function hydrate_properties() {

		$recipe_id = absint( self::$recipe->get_recipe_id() );

		$this->limit_per_user     = $this->limit_object( automator_get_recipe_times_per_user( $recipe_id ) );
		$this->recipe_total_times = $this->limit_object( automator_get_recipe_total_times( $recipe_id ) );
		$this->recipe_throttle    = $this->get_throttle_settings();

		return $this;
	}

	/**
	 * Build the {TIMES} display object from a completion-limit helper result.
	 * A WP_Error or unlimited (-1) yields the empty-object "no limit" shape.
	 *
	 * @param int|\WP_Error $limit
	 */
	private function limit_object( $limit ): \stdClass {
		$value = is_wp_error( $limit ) ? -1 : (int) $limit;
		return $this->format_limit_value( $value, 'int' );
	}

	/**
	 * Formats the limit value. Returns empty object for unlimited (-1), otherwise TIMES structure.
	 *
	 * We use `new \stdClass()` throughout to guarantee JSON serialization as objects.
	 * Using `(object) array()` can produce `[]` instead of `{}` in edge cases.
	 *
	 * @param mixed  $value The limit value.
	 * @param string $type  The field type.
	 *
	 * @return \stdClass Empty object if unlimited, or object with TIMES structure.
	 */
	private function format_limit_value( $value, $type ) {

		// Unlimited (-1) returns empty object to indicate "no limit set".
		// Use loose comparison to handle both int and string "-1" from legacy meta.
		if ( -1 == $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			return new \stdClass();
		}

		// Build the TIMES structure for limits that are set.
		$times        = new \stdClass();
		$times->type  = $type;
		$times->value = $value;

		$result        = new \stdClass();
		$result->TIMES = $times; //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return $result;
	}

	/**
	 * Gets the throttle settings for the recipe.
	 *
	 * @return \stdClass Empty object if no settings, or object with throttle configuration.
	 */
	private function get_throttle_settings() {

		$recipe_id = self::$recipe->get_recipe_id();

		$settings_repository = new Settings_Repository();
		$settings_repository->set_recipe_id( $recipe_id );
		$settings_repository->set_setting_id( 'recipe_throttle' );

		$field_manager = new Field_Collection_Manager( $settings_repository );

		$collection = $field_manager->get_field_collection();

		// Return empty stdClass if collection is empty or only contains empty values.
		// We use `new \stdClass()` to ensure JSON serializes as `{}` not `[]`.
		if ( empty( $collection ) || ( is_array( $collection ) && $this->is_empty_collection( $collection ) ) ) {
			return new \stdClass();
		}

		// Cast populated collection to object for consistent JSON object output.
		return (object) $collection;
	}

	/**
	 * Checks if a collection is effectively empty.
	 *
	 * @param array $collection The collection to check.
	 *
	 * @return bool True if empty, false otherwise.
	 */
	private function is_empty_collection( array $collection ) {

		foreach ( $collection as $value ) {
			if ( ! empty( $value ) && '' !== $value ) {
				return false;
			}
		}

		return true;
	}
}
