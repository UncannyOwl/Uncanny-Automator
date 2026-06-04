<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Recipe_Object_Bridge}.
 *
 * Only place in `src/app/` permitted to call `Automator()->get_recipe_object()`,
 * `->get_recipes_data()`, `->get_recipe_data()`, and
 * `->get->recipes_from_trigger_code()`.
 *
 * @since 7.4.0
 */
final class Automator_Recipe_Object_Bridge implements Recipe_Object_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_recipe_as_array( int $recipe_id ): ?array {
		$recipe = \Automator()->get_recipe_object( $recipe_id, ARRAY_A );

		if ( ! is_array( $recipe ) || empty( $recipe ) ) {
			return null;
		}

		return $recipe;
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipe_as_object( int $recipe_id ) {
		$recipe = \Automator()->get_recipe_object( $recipe_id, 'OBJECT' );

		return empty( $recipe ) ? null : $recipe;
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipes_data( bool $force_refresh = false, ?int $recipe_id = null ): array {
		$result = \Automator()->get_recipes_data( $force_refresh, $recipe_id );

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipe_data_by_type( string $type, int $recipe_id ): array {
		$result = \Automator()->get_recipe_data( $type, $recipe_id, array(), true );

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipes_for_trigger_code( string $trigger_code, ?int $recipe_id = null ): array {
		$result = \Automator()->get->recipes_from_trigger_code( $trigger_code, $recipe_id );

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipe_type( int $recipe_id ): string {

		if ( 0 === $recipe_id ) {
			return 'user';
		}

		$type = get_post_meta( $recipe_id, 'uap_recipe_type', true );

		return ! empty( $type ) ? (string) $type : 'user';
	}
}
