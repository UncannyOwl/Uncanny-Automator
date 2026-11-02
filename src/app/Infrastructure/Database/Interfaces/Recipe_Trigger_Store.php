<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Triggers;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Trigger;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Value_Objects\Trigger_Id;

/**
 * Recipe Trigger Store Interface.
 *
 * Database-agnostic contract for recipe trigger persistence.
 * WordPress developers will see this as "trigger database functions contract".
 *
 * @since 7.0.0
 */
interface Recipe_Trigger_Store {

	/**
	 * Save all triggers for a recipe.
	 *
	 * @param Recipe_Id       $recipe_id Recipe ID.
	 * @param Recipe_Triggers $triggers Triggers collection.
	 * @return Recipe_Triggers The saved triggers collection with IDs and all persisted values.
	 */
	public function save_recipe_triggers( Recipe_Id $recipe_id, Recipe_Triggers $triggers ): Recipe_Triggers;

	/**
	 * Get all triggers for a recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return Recipe_Triggers Triggers collection.
	 */
	public function get_recipe_triggers( Recipe_Id $recipe_id ): Recipe_Triggers;

	/**
	 * Get all trigger WP_Posts for a recipe, ordered by menu_order ASC.
	 *
	 * Returns raw WP_Post objects without forcing full Trigger aggregate
	 * hydration. Intended for callers that need ordered post discovery
	 * (notably `Recipe_Tree_Builder`) where the strict VO invariants of
	 * the full Trigger aggregate (non-empty hook name, matching user_type,
	 * etc.) shouldn't gate post visibility.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return \WP_Post[] Trigger posts in menu_order.
	 */
	public function get_recipe_trigger_posts( int $recipe_id ): array;

	/**
	 * Add single trigger to recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @param Trigger   $trigger Trigger to add.
	 * @return Trigger The saved Trigger with generated ID and all persisted values.
	 */
	public function add_trigger_to_recipe( Recipe_Id $recipe_id, Trigger $trigger ): Trigger;

	/**
	 * Update single trigger in recipe.
	 *
	 * @param Recipe_Id  $recipe_id Recipe ID.
	 * @param Trigger_Id $trigger_id Trigger ID.
	 * @param Trigger    $trigger Updated trigger.
	 * @return Trigger The updated Trigger with all persisted values.
	 * @throws \Exception If update fails.
	 */
	public function update_recipe_trigger( Recipe_Id $recipe_id, Trigger_Id $trigger_id, Trigger $trigger ): Trigger;

	/**
	 * Remove trigger from recipe.
	 *
	 * Deletes the trigger only when it is one of the recipe's triggers.
	 *
	 * @since 7.0.0
	 * @since 8.0.0 Throws when the trigger is not one of the recipe's.
	 *
	 * @param Recipe_Id  $recipe_id Recipe ID.
	 * @param Trigger_Id $trigger_id Trigger ID.
	 * @throws \Exception When the trigger is not one of the recipe's triggers, or it cannot be deleted.
	 */
	public function remove_trigger_from_recipe( Recipe_Id $recipe_id, Trigger_Id $trigger_id ): void;

	/**
	 * Set trigger logic for recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @param string    $logic Trigger logic ('all' or 'any').
	 */
	public function set_recipe_trigger_logic( Recipe_Id $recipe_id, string $logic ): void;

	/**
	 * Get trigger logic for recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return string|null Trigger logic or null.
	 */
	public function get_recipe_trigger_logic( Recipe_Id $recipe_id ): ?string;

	/**
	 * Check if recipe has triggers.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return bool True if recipe has triggers.
	 */
	public function recipe_has_triggers( Recipe_Id $recipe_id ): bool;

	/**
	 * Whether the recipe has at least one live (published) trigger.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return bool True when a published trigger exists.
	 */
	public function recipe_has_live_triggers( Recipe_Id $recipe_id ): bool;

	/**
	 * Count triggers for recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return int Number of triggers.
	 */
	public function count_recipe_triggers( Recipe_Id $recipe_id ): int;

	/**
	 * Check if any trigger in the recipe is a manual trigger.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 *
	 * @return bool
	 */
	public function recipe_has_manual_trigger( Recipe_Id $recipe_id ): bool;

	/**
	 * Whether the recipe has a published RECIPE_MANUAL_TRIGGER_ANON trigger.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return bool
	 */
	public function recipe_has_live_manual_trigger( Recipe_Id $recipe_id ): bool;

	/**
	 * The post ID of the recipe's manual (run-now) trigger, or null when none.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 *
	 * @return int|null
	 */
	public function get_manual_trigger_id( Recipe_Id $recipe_id ): ?int;

	/**
	 * The post ID of the first trigger in a recipe matching a trigger code, or
	 * null when none. Generic primitive behind `get_manual_trigger_id()` and the
	 * `automator_get_recipe_trigger_id()` / `automator_recipe_has_trigger()` helpers.
	 *
	 * @param Recipe_Id $recipe_id    Recipe ID.
	 * @param string    $trigger_code Trigger code to match.
	 *
	 * @return int|null
	 */
	public function get_trigger_id_by_code( Recipe_Id $recipe_id, string $trigger_code ): ?int;

	/**
	 * Build a Trigger aggregate from a WP_Post object.
	 *
	 * @param \WP_Post $trigger_post The trigger post.
	 *
	 * @return Trigger|null
	 */
	public function hydrate_trigger_from_post( \WP_Post $trigger_post ): ?Trigger;

	/**
	 * Read what a trigger post stores, without the aggregate's rules.
	 *
	 * @since 8.0.0
	 *
	 * @param int $trigger_post_id The trigger post ID.
	 *
	 * @return array|null Keys `trigger_code`, `integration`, `sentence`, `sentence_human_readable`,
	 *                    `sentence_human_readable_html`, `trigger_hook` and `configuration`, among
	 *                    others; null when the post stores no trigger code.
	 */
	public function parse_trigger_meta( int $trigger_post_id ): ?array;

	/**
	 * Get the underlying WP_Post for a trigger.
	 *
	 * @param int $trigger_id The trigger post ID.
	 *
	 * @return \WP_Post|null
	 */
	public function get_wp_post( int $trigger_id ): ?\WP_Post;

	/**
	 * Usage per "INTEGRATION:CODE" for triggers in published and draft recipes, at any nesting depth: `all` (live and switched off, published and draft recipes), `published` (live in published recipes) and `last_used` (newest `post_date`, GMT unix).
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
