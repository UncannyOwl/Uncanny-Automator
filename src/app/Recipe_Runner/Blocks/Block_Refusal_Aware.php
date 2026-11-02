<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Execution_Context;

/**
 * A block processor that records its own refusals.
 *
 * The walker refuses a block whose configuration is incomplete before
 * dispatching it, and completes the action rows under it with the reason
 * itself. A block whose runs leave no action rows (a loop) has nothing for
 * the walker to settle, so its processor records the refusal here instead.
 *
 * @since 8.0.0
 */
interface Block_Refusal_Aware {

	/**
	 * Called instead of process() when the block was refused.
	 *
	 * @param array<string, mixed> $block_node The refused block's tree node.
	 * @param Execution_Context    $context    The run.
	 * @param string               $reason     Plain-text reason, the same one written to the block's action rows.
	 *
	 * @return void
	 */
	public function refused( array $block_node, Execution_Context $context, string $reason ): void;
}
