<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Interfaces\Loop;

use Uncanny_Automator\Api\Components\Loop\Loop;

/**
 * Loop Store Interface.
 *
 * Contract for persisting Loop aggregates.
 * Database-agnostic interface with WordPress implementation.
 *
 * The Loop store coordinates with the Filter store to manage
 * the complete aggregate including child filter entities.
 *
 * @since 7.0.0
 */
interface Loop_Store {

	/**
	 * Persist a Loop (insert or update).
	 *
	 * This also persists any associated filters via the Filter store.
	 *
	 * @param Loop $loop Loop to save.
	 * @return Loop The saved Loop with ID and all persisted values.
	 */
	public function save( Loop $loop ): Loop;

	/**
	 * Load a Loop by its ID.
	 *
	 * Includes all associated filters.
	 *
	 * @param int $id Loop ID.
	 * @return Loop|null Loop instance or null if not found.
	 */
	public function get( int $id ): ?Loop;

	/**
	 * Delete a Loop and its filters.
	 *
	 * @param Loop $loop Loop to delete.
	 * @return void
	 * @throws \Exception If delete fails or loop is not persisted.
	 */
	public function delete( Loop $loop ): void;

	/**
	 * Fetch multiple Loops (optionally filtered).
	 *
	 * @param array $filters Optional filters for querying loops.
	 * @return Loop[] Array of Loop instances.
	 */
	public function all( array $filters = array() ): array;

	/**
	 * Get Loops by Recipe ID.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return Loop[] Array of Loops belonging to the recipe.
	 */
	public function get_recipe_loops( int $recipe_id ): array;

	/**
	 * Check if a loop exists.
	 *
	 * @param int $id Loop ID.
	 * @return bool True if exists.
	 */
	public function exists( int $id ): bool;
}
