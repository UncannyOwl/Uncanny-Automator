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
	 * Get only the actions whose post_parent is the recipe itself (i.e.
	 * not nested inside a loop or a uo-block container), ordered by
	 * menu_order ASC.
	 *
	 * Intended for callers that need the recipe's top-level action list
	 * in display order — notably `Recipe_Tree_Builder` building the
	 * declarative content stream. The wider {@see get_recipe_actions()}
	 * spans loop descendants and orders by ID; it is unsuitable for
	 * tree-shape work.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return Action[] Top-level Actions in menu_order.
	 */
	public function get_top_level_recipe_actions( int $recipe_id ): array;

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
	 * Container-aware action ids for a recipe, filtered by post status.
	 *
	 * Covers direct recipe children AND actions inside any container
	 * (blocks nest arbitrarily).
	 *
	 * @param int      $recipe_id Recipe post ID.
	 * @param string[] $statuses  Post statuses to include.
	 * @return array<int> Action post IDs.
	 */
	public function get_recipe_action_ids( int $recipe_id, array $statuses ): array;

	/**
	 * Container-aware id => code map for a recipe's actions, read raw.
	 *
	 * Deliberately NO aggregate hydration: safety scans (the user-selector
	 * publish gate) must see a row's code even when the row would fail
	 * domain validation.
	 *
	 * @param int      $recipe_id Recipe post ID.
	 * @param string[] $statuses  Post statuses to include.
	 * @return array<int, string> Action ID => action code (may be empty string).
	 */
	public function get_recipe_action_codes( int $recipe_id, array $statuses ): array;

	/**
	 * Raw field values for one action: every non-internal meta value.
	 *
	 * Deliberately NO aggregate hydration — token safety scans want raw
	 * visibility, not domain validity. Internal (underscore-prefixed)
	 * keys are skipped; the first meta value per key is returned.
	 *
	 * @param int $action_id Action post ID.
	 * @return array<string, mixed> Meta key => first value.
	 */
	public function get_action_field_values( int $action_id ): array;

	/**
	 * Usage per "INTEGRATION:CODE" for actions in published and draft recipes, at any nesting depth: `all` (live and switched off, published and draft recipes), `published` (live in published recipes) and `last_used` (newest `post_date`, GMT unix).
	 *
	 * @since 8.0.0
	 *
	 * @return array<string, array{published: int, all: int, last_used: int}>
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the query fails.
	 */
	public function usage_census_by_code(): array;

	/**
	 * One row per published item in a published recipe, at any nesting depth: recipe id, post id, parent id, integration code, item code.
	 *
	 * `parent_id` is the item's post_parent. Exactly one row per post; a duplicated meta row yields the first value by meta_id.
	 *
	 * @return list<array{recipe_id: int, post_id: int, parent_id: int, integration_code: string, code: string}>
	 */
	public function usage_rows(): array;

	/**
	 * Deprecated one-line accessor retained for 8.0.0.
	 *
	 * Its callers (the recipe-item REST classes) move off it in a later PR;
	 * deleting it here would break them in the interim. Removed in the
	 * post-8.0.0 cleanup pass together with those callers.
	 *
	 * @deprecated 8.0.0
	 */
	public function get_post_type(): string;
}
