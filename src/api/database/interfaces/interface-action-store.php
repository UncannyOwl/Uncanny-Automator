<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Interfaces;

use Uncanny_Automator\Api\Components\Action\Action;

/**
 * Action Store Interface.
 *
 * Contract for persisting Action entities.
 * Database-agnostic interface with WordPress implementation.
 *
 * @since 7.0.0
 */
interface Action_Store {

	/**
	 * Persist an Action (insert or update).
	 *
	 * @param Action $action Action to save.
	 * @return Action The saved Action with ID and all persisted values.
	 */
	public function save( Action $action ): Action;

	/**
	 * Load an Action by its ID.
	 *
	 * @param int $id Action ID.
	 * @return Action|null Action instance or null if not found.
	 */
	public function get( int $id ): ?Action;

	/**
	 * Delete an Action.
	 *
	 * @param Action $action Action to delete.
	 * @return void
	 * @throws \Exception If delete fails or action is not persisted.
	 */
	public function delete( Action $action ): void;

	/**
	 * Fetch multiple Actions (optionally filtered).
	 *
	 * @param array $filters Optional filters for querying actions.
	 * @return Action[] Array of Action instances.
	 */
	public function all( array $filters = array() ): array;

	/**
	 * Get Actions by Recipe ID.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return Action[] Array of Actions belonging to the recipe.
	 */
	public function get_recipe_actions( int $recipe_id ): array;

	/**
	 * Get Actions by integration.
	 *
	 * @param string $integration Integration code (e.g., 'WP', 'WC').
	 * @return Action[] Array of Actions from the integration.
	 */
	public function get_by_integration( string $integration ): array;
}
