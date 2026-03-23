<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Interfaces;

use Uncanny_Automator\Api\Components\Recipe\Recipe;

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
}
