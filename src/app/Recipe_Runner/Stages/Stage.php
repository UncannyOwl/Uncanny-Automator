<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Stages;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Pipeline_Context;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;

/**
 * Stage contract for the recipe execution pipeline.
 *
 * Each stage executes forward-only — no rollback, no compensation.
 * 90% of Automator actions are irreversible (emails, API calls, user creation),
 * so the pipeline pattern (not saga) is the correct fit.
 *
 * Lives alongside the five `*_Stage` implementations that consume it
 * (Trigger_Entry_Stage, Trigger_Complete_Stage, Action_Run_Stage,
 * Recipe_Complete_Stage, Closure_Stage).
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Stages
 * @since   7.2
 */
interface Stage {

	/**
	 * Execute this pipeline stage.
	 *
	 * @param Pipeline_Context $context Immutable input context.
	 * @param Pipeline_Result  $result  Accumulated results from prior stages.
	 *
	 * @return Pipeline_Result The (possibly modified) result to pass to the next stage.
	 */
	public function execute( Pipeline_Context $context, Pipeline_Result $result ): Pipeline_Result;
}
