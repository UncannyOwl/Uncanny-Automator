<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Builder\Action\Action;

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

	/**
	 * Delete an action by its post ID.
	 *
	 * @param int $action_id The action post ID.
	 * @param int $recipe_id Optional recipe ID for scoping.
	 *
	 * @return void
	 */
	public function delete_by_id( int $action_id, int $recipe_id = 0 ): void;

	/**
	 * Get the underlying WP_Post for an action.
	 *
	 * @param int $action_id The action post ID.
	 *
	 * @return \WP_Post|null
	 */
	public function get_wp_post( int $action_id ): ?\WP_Post;

	/**
	 * Build an Action aggregate from a WP_Post object.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return Action|null
	 */
	public function build_action_from_post( \WP_Post $post ): ?Action;

	/**
	 * Get the registered post type for actions.
	 *
	 * @return string
	 */
	public function get_post_type(): string;
}
