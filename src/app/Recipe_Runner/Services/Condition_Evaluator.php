<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

/**
 * Evaluates filter/condition blocks from the recipe structure tree.
 *
 * Returns whether the conditions passed. The caller decides which
 * path to take (IF/ELSE) based on the result.
 *
 * Pro implements the actual condition logic. Current behavior falls
 * back to the automator_before_action_executed filter chain
 * (Actions_Conditions at priority 5) which evaluates per-action.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
interface Condition_Evaluator {

	/**
	 * Evaluate filter conditions for a block.
	 *
	 * @param array $filter_item       Filter item from Recipe\Structure (id, conditions, logic, items/paths).
	 * @param array $execution_context Execution state from Execution_Context::to_array().
	 *
	 * @return bool True if conditions are met.
	 */
	public function evaluate( array $filter_item, array $execution_context ): bool;
}
