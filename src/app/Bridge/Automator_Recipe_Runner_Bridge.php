<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Control_Registry;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;

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

	/**
	 * @inheritDoc
	 */
	public function block_controls(): Block_Control_Registry {
		return \Automator()->recipe_runner->block_controls();
	}

	/**
	 * @inheritDoc
	 */
	public function complete_background_action( Background_Job $job, Background_Job_Result $result ): void {
		\Automator()->recipe_runner->complete_background_action( $job, $result );
	}

	/**
	 * @inheritDoc
	 */
	public function resolve_background_action( Background_Job $job ): ?array {
		return \Automator()->recipe_runner->resolve_background_action( $job );
	}

	/**
	 * @inheritDoc
	 */
	public function has_user_resolver(): bool {
		return \Automator()->recipe_runner->has_user_resolver();
	}
}
