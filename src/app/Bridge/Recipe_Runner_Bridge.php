<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy recipe runner facade.
 *
 * Wraps `Automator()->recipe_runner->finalize_recipe()`. Used by the
 * stuck-recipe recovery cron and any other surface that needs to mark a
 * run completed through the legacy execution path.
 *
 * @since 7.4.0
 */
interface Recipe_Runner_Bridge {

	/**
	 * Finalise a recipe run via the legacy runner.
	 *
	 * Wraps `Automator()->recipe_runner->finalize_recipe( $recipe_id, $user_id, $recipe_log_id )`.
	 *
	 * @param int  $recipe_id                 Recipe post ID.
	 * @param int  $user_id                   User ID.
	 * @param int  $recipe_log_id             Recipe log row id.
	 * @param bool $treat_incomplete_as_error When true, NOT_COMPLETED action rows are treated as
	 *                                        stuck errors (recovery context).
	 * @return void
	 */
	public function finalize_recipe( int $recipe_id, int $user_id, int $recipe_log_id, bool $treat_incomplete_as_error = false ): void;
}
