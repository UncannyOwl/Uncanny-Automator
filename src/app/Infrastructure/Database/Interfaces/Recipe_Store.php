<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe;

interface Recipe_Store {

	/**
	 * Persist a Recipe (insert or update).
	 */
	public function save( Recipe $recipe ): Recipe;

	/**
	 * Load a Recipe by its ID.
	 */
	public function get( int $id ): ?Recipe;

	/**
	 * Delete a Recipe.
	 */
	public function delete( Recipe $recipe ): void;

	/**
	 * Fetch multiple Recipes (optionally filtered).
	 *
	 * @return Recipe[]
	 */
	public function all( array $filters = array() ): array;

	/**
	 * Get recipe IDs that match a specific field value.
	 *
	 * @param mixed $field_value The field value to search for.
	 *
	 * @return array
	 */
	public function get_recipe_ids_from_field_value( $field_value ): array;

	/**
	 * Get the underlying WP_Post for a recipe.
	 *
	 * @param int $id The recipe post ID.
	 *
	 * @return \WP_Post|null
	 */
	public function get_wp_post( int $id ): ?\WP_Post;

	/**
	 * Get the registered post type for recipes.
	 *
	 * @return string
	 */
	public function get_post_type(): string;
}
