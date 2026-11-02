<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Execution_Context;

/**
 * A block processor that wants one call after the whole walk, once every block has been dispatched.
 *
 * The loop processor queues in process() and dispatches here, so every loop is queued before any starts.
 */
interface Walk_Completion_Aware {

	/**
	 * Called once per walk (initial run or resume) after the last node was dispatched.
	 *
	 * @param Execution_Context $context The run.
	 *
	 * @return void
	 */
	public function after_walk( Execution_Context $context ): void;
}
