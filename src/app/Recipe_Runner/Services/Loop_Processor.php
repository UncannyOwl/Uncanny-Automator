<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

/**
 * Processes a loop item from the recipe structure tree.
 *
 * Pro implements this with background queue dispatch via Loop\Execute.
 * Free plugin has no implementation — loop post types don't exist without Pro.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
interface Loop_Processor {

	/**
	 * Queue a loop item for background execution. Does NOT dispatch immediately.
	 * Call dispatch_all() after all loops are queued.
	 *
	 * @param array  $loop_item        Loop item from Recipe\Structure (id, items, iterable_expression, filters).
	 * @param array  $execution_context Execution state from Execution_Context::to_array().
	 * @param object $recipe_structure  The Recipe\Structure instance (typed as object — no contract for Structure yet).
	 *
	 * @return void
	 */
	public function process( array $loop_item, array $execution_context, $recipe_structure ): void;

	/**
	 * Dispatch all queued loops. Called by Action_Run_Stage after the tree
	 * walker finishes process_items(), ensuring ALL loops are queued before
	 * any of them start processing.
	 *
	 * @return void
	 */
	public function dispatch_all(): void;
}
