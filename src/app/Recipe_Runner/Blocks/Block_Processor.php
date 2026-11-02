<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Execution_Context;

/**
 * Executes one block node of the canonical tree.
 *
 * The processor decides which of the block's paths run and when by calling back into the
 * children runner; it reports one outcome. Pro registers one implementation per block type
 * through Recipe_Runner::register_block_processor(); Free registers none.
 */
interface Block_Processor {

	/**
	 * Run the block.
	 *
	 * @param array<string, mixed>  $block_node Tree node (`kind: block`) with `id`, `block_type`, `fields`, `modifiers`, `paths`.
	 * @param Execution_Context     $context    The run: recipe, user, recipe log, trigger args, row map.
	 * @param Block_Children_Runner $children   Runs one declared path of this block with the same context.
	 *
	 * @return Block_Outcome Completed; deferred when the processor owns completion later; or not run, with the reason, when the block ran none of its actions (in a loop body, after skipping the entity's items itself).
	 */
	public function process( array $block_node, Execution_Context $context, Block_Children_Runner $children ): Block_Outcome;
}
