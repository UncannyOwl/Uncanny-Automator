<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Control_Registry;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;

/**
 * Anti-corruption boundary for the legacy recipe runner facade.
 *
 * Wraps `Automator()->recipe_runner->finalize_recipe()` and the runner's
 * block-control registry. Used by the stuck-recipe recovery cron, the
 * recipe-log wire and the block-control route.
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

	/**
	 * The runner's block-control registry.
	 *
	 * Wraps `Automator()->recipe_runner->block_controls()`.
	 *
	 * @return Block_Control_Registry
	 */
	public function block_controls(): Block_Control_Registry;

	/**
	 * Finish a background app action with its job's outcome.
	 *
	 * @param Background_Job        $job    The job record.
	 * @param Background_Job_Result $result The job's outcome.
	 *
	 * @return void
	 */
	public function complete_background_action( Background_Job $job, Background_Job_Result $result ): void;

	/**
	 * The action a background job belongs to, with the action data it runs on.
	 *
	 * Wraps `Automator()->recipe_runner->resolve_background_action( $job )`.
	 *
	 * @since 8.0.0
	 *
	 * @param Background_Job $job The job record.
	 *
	 * @return array{action: object, action_data: array<string, mixed>}|null Null when the action cannot complete a job.
	 */
	public function resolve_background_action( Background_Job $job ): ?array;

	/**
	 * Whether the runner has a user resolver registered.
	 *
	 * Wraps `Automator()->recipe_runner->has_user_resolver()`.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function has_user_resolver(): bool;
}
