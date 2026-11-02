<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Run_Data_Bundle;

/**
 * Block type to control. One control per block type; the last registration wins.
 */
final class Block_Control_Registry {

	/**
	 * Registered controls keyed by block type.
	 *
	 * @var array<string, Block_Control>
	 */
	private array $controls = array();

	/**
	 * Register the control for a block type.
	 *
	 * @param string        $block_type One of the `Block_Type::*` strings.
	 * @param Block_Control $control    The implementation.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When the block type is empty.
	 */
	public function register( string $block_type, Block_Control $control ): void {
		if ( '' === $block_type ) {
			throw new \InvalidArgumentException( 'A block control needs a block type.' );
		}

		$this->controls[ $block_type ] = $control;
	}

	/**
	 * The control payload for a block of a run, or null when the type has no control or the block offers nothing.
	 *
	 * @param string               $block_type Block type.
	 * @param array<string, mixed> $block      The block's tree node.
	 * @param Run_Data_Bundle      $bundle     The run.
	 *
	 * @return array{process_code: string, actions: list<'run_now'|'pause'|'resume'|'cancel'>}|null
	 */
	public function control_for( string $block_type, array $block, Run_Data_Bundle $bundle ): ?array {
		$control = $this->controls[ $block_type ] ?? null;

		return null === $control ? null : $control->control_for( $block, $bundle );
	}

	/**
	 * Apply one action to the process the code names; the block type is the prefix before the first `:`.
	 *
	 * @param string $process_code The code a control shipped in control_for().
	 * @param string $action       'run_now' | 'pause' | 'resume' | 'cancel'.
	 *
	 * @return array{success: bool, message: string}|\WP_Error
	 */
	public function handle( string $process_code, string $action ) {
		$separator  = strpos( $process_code, ':' );
		$block_type = false === $separator ? '' : substr( $process_code, 0, $separator );
		$control    = $this->controls[ $block_type ] ?? null;

		if ( null === $control ) {
			return new \WP_Error( 'rest_block_control_unknown', 'No control handles this process code.', array( 'status' => 404 ) );
		}

		return $control->handle( $process_code, $action );
	}

	/**
	 * Every registered control keyed by block type.
	 *
	 * @return array<string, Block_Control>
	 */
	public function all(): array {
		return $this->controls;
	}
}
