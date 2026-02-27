<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Interfaces;

use Uncanny_Automator\Api\Components\User_Selector\User_Selector;

/**
 * User Selector Store Interface.
 *
 * Defines the contract for user selector persistence operations.
 * Implementations handle storage-specific concerns while maintaining
 * domain-agnostic interface for the service layer.
 *
 * @since 7.0.0
 */
interface User_Selector_Store {

	/**
	 * Persist a User_Selector (insert or update).
	 *
	 * @param User_Selector $user_selector User selector to persist.
	 * @return User_Selector Persisted user selector with ID.
	 */
	public function save( User_Selector $user_selector ): User_Selector;

	/**
	 * Load a User_Selector by recipe ID.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return User_Selector|null User selector or null if not found.
	 */
	public function get_by_recipe_id( int $recipe_id ): ?User_Selector;

	/**
	 * Delete a User_Selector by recipe ID.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return void
	 */
	public function delete_by_recipe_id( int $recipe_id ): void;

	/**
	 * Check if a recipe has a user selector configured.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return bool True if user selector exists.
	 */
	public function exists_for_recipe( int $recipe_id ): bool;
}
