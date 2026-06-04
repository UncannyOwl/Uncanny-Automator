<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for legacy recipe-object lookups.
 *
 * Wraps the `Automator()->get_recipe_object()`, `->get_recipes_data()`,
 * `->get_recipe_data()`, and `->get->recipes_from_trigger_code()` family.
 * Consumers depend on this contract instead of the global so the legacy
 * recipe-object surface can be deprecated incrementally.
 *
 * @since 7.4.0
 */
interface Recipe_Object_Bridge {

	/**
	 * Fetch a recipe as an associative array.
	 *
	 * Wraps `Automator()->get_recipe_object( $recipe_id, ARRAY_A )`.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return array|null Recipe data, or null if not found.
	 */
	public function get_recipe_as_array( int $recipe_id ): ?array;

	/**
	 * Fetch a recipe as a WP_Post object.
	 *
	 * Wraps `Automator()->get_recipe_object( $recipe_id, 'OBJECT' )`.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return mixed Recipe object (typically WP_Post), or null if not found.
	 */
	public function get_recipe_as_object( int $recipe_id );

	/**
	 * Fetch every recipe row, optionally filtered to a single recipe.
	 *
	 * Wraps `Automator()->get_recipes_data( $force_refresh, $recipe_id )`.
	 *
	 * Default for `$force_refresh` matches the legacy global's default
	 * (`false`) so consumers that don't care about cache freshness behave
	 * identically to the legacy callsite. Pass `true` explicitly when the
	 * caller needs an uncached read.
	 *
	 * @param bool     $force_refresh Force the legacy global to bypass any cache.
	 * @param int|null $recipe_id     Optional recipe filter.
	 * @return array Recipe rows.
	 */
	public function get_recipes_data( bool $force_refresh = false, ?int $recipe_id = null ): array;

	/**
	 * Fetch a recipe item subtree (triggers/actions/closures) for a recipe.
	 *
	 * Wraps `Automator()->get_recipe_data( $type, $recipe_id, array(), true )`.
	 *
	 * @param string $type      Item post type (e.g. AUTOMATOR_POST_TYPE_TRIGGER).
	 * @param int    $recipe_id Recipe post ID.
	 * @return array Item rows.
	 */
	public function get_recipe_data_by_type( string $type, int $recipe_id ): array;

	/**
	 * Fetch every recipe row that consumes a given trigger code.
	 *
	 * Wraps `Automator()->get->recipes_from_trigger_code( $trigger_code, $recipe_id )`.
	 *
	 * @param string   $trigger_code Trigger code.
	 * @param int|null $recipe_id    Optional recipe filter.
	 * @return array Recipe rows.
	 */
	public function get_recipes_for_trigger_code( string $trigger_code, ?int $recipe_id = null ): array;

	/**
	 * Get the recipe type (user, anonymous, etc.).
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return string The recipe type. Defaults to 'user' if not set.
	 */
	public function get_recipe_type( int $recipe_id ): string;
}
