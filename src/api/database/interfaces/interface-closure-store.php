<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Interfaces;

use Uncanny_Automator\Api\Components\Closure\Closure;
use Uncanny_Automator\Api\Components\Closure\Closure_Config;

/**
 * Closure Store Interface.
 *
 * Contract for persisting Closure entities.
 * Database-agnostic interface with WordPress implementation.
 *
 * @since 7.0.0
 */
interface Closure_Store {

	/**
	 * Persist a Closure from config (insert or update).
	 *
	 * @param Closure_Config $config Closure config to save.
	 * @return Closure Created Closure instance.
	 */
	public function save( Closure_Config $config ): Closure;

	/**
	 * Load a Closure by its ID.
	 *
	 * @param string $id Closure ID.
	 * @return Closure|null Closure instance or null if not found.
	 */
	public function get( string $id ): ?Closure;

	/**
	 * Delete a Closure.
	 *
	 * @param Closure $closure Closure to delete.
	 * @return void
	 */
	public function delete( Closure $closure ): void;

	/**
	 * Fetch multiple Closures (optionally filtered).
	 *
	 * @param array $filters Optional filters for querying closures.
	 * @return Closure[] Array of Closure instances.
	 */
	public function all( array $filters = array() ): array;

	/**
	 * Get Closures by Recipe ID.
	 *
	 * @param string $recipe_id Recipe ID.
	 * @return Closure[] Array of Closures belonging to the recipe.
	 */
	public function get_recipe_closures( string $recipe_id ): array;

	/**
	 * Get Closures by integration.
	 *
	 * @param string $integration Integration code (e.g., 'WP', 'WC').
	 * @return Closure[] Array of Closures from the integration.
	 */
	public function get_by_integration( string $integration ): array;
}
