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
	 * @param Recipe_Id  $recipe_id Recipe ID.
	 * @param Trigger_Id $trigger_id Trigger ID.
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
	 * Delete all triggers for recipe.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 */
	public function delete_recipe_triggers( Recipe_Id $recipe_id ): void;

	/**
	 * Check if recipe has triggers.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @return bool True if recipe has triggers.
	 */
	public function recipe_has_triggers( Recipe_Id $recipe_id ): bool;

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
	 * Build a Trigger aggregate from a WP_Post object.
	 *
	 * @param \WP_Post $trigger_post The trigger post.
	 *
	 * @return Trigger|null
	 */
	public function hydrate_trigger_from_post( \WP_Post $trigger_post ): ?Trigger;

	/**
	 * Get the underlying WP_Post for a trigger.
	 *
	 * @param int $trigger_id The trigger post ID.
	 *
	 * @return \WP_Post|null
	 */
	public function get_wp_post( int $trigger_id ): ?\WP_Post;

	/**
	 * Get the registered post type for triggers.
	 *
	 * @return string
	 */
	public function get_post_type(): string;
}
