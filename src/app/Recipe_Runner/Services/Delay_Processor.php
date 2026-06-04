<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

/**
 * Processes a delay/schedule block from the recipe structure tree.
 *
 * Implementation schedules child items via Action Scheduler and marks
 * actions as IN_PROGRESS until the scheduled time fires.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
interface Delay_Processor {

	/**
	 * Process a delay item — schedule child execution for a future time.
	 *
	 * @param array  $delay_item        Delay item from Recipe\Structure (id, fields, paths).
	 * @param array  $execution_context Execution state from Execution_Context::to_array().
	 * @param object $recipe_structure  The Recipe\Structure instance.
	 *
	 * @return void
	 */
	public function process( array $delay_item, array $execution_context, $recipe_structure ): void;
}
