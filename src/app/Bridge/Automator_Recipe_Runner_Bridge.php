<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Recipe_Runner_Bridge}.
 *
 * @since 7.4.0
 */
final class Automator_Recipe_Runner_Bridge implements Recipe_Runner_Bridge {

	/**
	 * @inheritDoc
	 */
	public function finalize_recipe( int $recipe_id, int $user_id, int $recipe_log_id, bool $treat_incomplete_as_error = false ): void {
		\Automator()->recipe_runner->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, array(), $treat_incomplete_as_error );
	}
}
