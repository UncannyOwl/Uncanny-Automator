<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Run_Data_Bundle;

/**
 * What a user may do to one block of one run right now, and how to do it. Pro registers one per block type.
 */
interface Block_Control {

	/**
	 * The control payload for a block of a run, or null when the user can do nothing to it.
	 *
	 * @param array<string, mixed> $block  The block's tree node.
	 * @param Run_Data_Bundle      $bundle The run.
	 *
	 * @return array{process_code: string, actions: list<'run_now'|'pause'|'resume'|'cancel'>}|null
	 */
	public function control_for( array $block, Run_Data_Bundle $bundle ): ?array;

	/**
	 * Apply one action to the process the code names.
	 *
	 * @param string $process_code The code this control shipped in control_for().
	 * @param string $action       'run_now' | 'pause' | 'resume' | 'cancel'.
	 *
	 * @return array{success: bool, message: string}|\WP_Error
	 */
	public function handle( string $process_code, string $action );
}
