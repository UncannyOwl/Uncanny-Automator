<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Services;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Who may delete a recipe's runs: a user with the admin capability, as on
 * the legacy Logs tables, unless the
 * `automator_allow_users_to_delete_in_progress_recipe_runs` filter refuses
 * it for the recipe.
 *
 * @since 8.1.0
 */
final class Recipe_Run_Delete_Policy {

	/**
	 * Whether the current user may delete the recipe's runs.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return bool
	 */
	public function can_delete( int $recipe_id ): bool {
		if ( ! current_user_can( automator_get_admin_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The admin capability is filterable.
			return false;
		}

		return true === Dispatcher::filter( 'automator_allow_users_to_delete_in_progress_recipe_runs', true, $recipe_id );
	}
}
