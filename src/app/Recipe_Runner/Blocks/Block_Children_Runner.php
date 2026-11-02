<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;
use Uncanny_Automator\App\Recipe_Runner\Stages\Action_Run_Stage;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Execution_Context;
use Uncanny_Automator\Automator_Status;

/**
 * Runs one declared path of a block: the single primitive behind the initial walk, a delay
 * resume and a loop entity.
 *
 * For a block type with several paths, outside loop scope, it records the chosen path on the run
 * and flips every action row under the other paths to SKIPPED (through nested filter and delay
 * blocks, never into loop bodies), then walks the path's items one level deeper through the stage.
 */
final class Block_Children_Runner {

	/**
	 * The stage whose process_items() walks the path.
	 *
	 * @var Action_Run_Stage
	 */
	private Action_Run_Stage $stage;

	/**
	 * The walk's result; deferred blocks mark async work on it.
	 *
	 * @var Pipeline_Result
	 */
	private Pipeline_Result $result;

	/**
	 * Row transitions and run meta.
	 *
	 * @var Execution_Log_Store
	 */
	private Execution_Log_Store $log_store;

	/**
	 * Depth of the block being run; its path runs at depth + 1.
	 *
	 * @var int
	 */
	private int $depth;

	/**
	 * Bind the runner to a walk.
	 *
	 * @param Action_Run_Stage    $stage     The walking stage.
	 * @param Pipeline_Result     $result    The walk's result.
	 * @param Execution_Log_Store $log_store Log store.
	 * @param int                 $depth     Depth of the block being run.
	 */
	public function __construct( Action_Run_Stage $stage, Pipeline_Result $result, Execution_Log_Store $log_store, int $depth = 0 ) {
		$this->stage     = $stage;
		$this->result    = $result;
		$this->log_store = $log_store;
		$this->depth     = $depth;
	}

	/**
	 * Run one declared path of the block with the given context.
	 *
	 * @param array<string, mixed> $block_node Block node.
	 * @param string               $path_code  The path to run.
	 * @param Execution_Context    $context    The run.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When the block does not declare the path.
	 */
	public function run( array $block_node, string $path_code, Execution_Context $context ): void {
		$path = Tree_Walk::path( $block_node, $path_code );
		if ( null === $path ) {
			throw new \InvalidArgumentException(
				esc_html( sprintf( 'Block %d (%s) declares no path "%s".', (int) ( $block_node['id'] ?? 0 ), (string) ( $block_node['block_type'] ?? '' ), $path_code ) )
			);
		}

		if ( count( Block_Path_Code::for_block_type( (string) ( $block_node['block_type'] ?? '' ) ) ) > 1 && ! $context->is_loop_scoped() ) {
			$this->record_chosen_path( $block_node, $path_code, $context );
			$this->skip_untaken_paths( $block_node, $path_code, $context );
		}

		$this->stage->process_items( $path['items'], $context, $this->result, $this->depth + 1 );
	}

	/**
	 * The walk's result.
	 *
	 * @return Pipeline_Result
	 */
	public function result(): Pipeline_Result {
		return $this->result;
	}

	/**
	 * Merge this block's chosen path into the run's chosen-paths meta.
	 *
	 * @param array<string, mixed> $block_node Block node.
	 * @param string               $path_code  Chosen path.
	 * @param Execution_Context    $context    The run.
	 *
	 * @return void
	 */
	private function record_chosen_path( array $block_node, string $path_code, Execution_Context $context ): void {
		$recipe_log_id = $context->get_recipe_log_id();
		$block_id      = (int) ( $block_node['id'] ?? 0 );

		// Two sibling delay blocks can resume in the same second on two schedulers; read-merge-write unlocked loses an entry.
		$lock = 'automator_run_meta_' . $recipe_log_id;
		if ( ! $this->log_store->acquire_lock( $lock, 5 ) ) {
			automator_log( sprintf( 'Recipe log %d: run-meta lock not acquired; entry for block %d written unlocked.', $recipe_log_id, $block_id ), 'Recipe_Runner' );
		}

		try {
			$existing = json_decode( (string) $this->log_store->get_recipe_log_meta( $recipe_log_id, Execution_Log_Store::META_BLOCK_CHOSEN_PATHS ), true );
			$existing = is_array( $existing ) ? $existing : array();

			$existing[ (string) $block_id ] = array( 'chosen_path' => $path_code );

			$this->log_store->set_recipe_log_meta( $context->get_recipe_id(), $recipe_log_id, Execution_Log_Store::META_BLOCK_CHOSEN_PATHS, (string) wp_json_encode( $existing ) );
		} finally {
			$this->log_store->release_lock( $lock );
		}
	}

	/**
	 * Flip every action row under the paths not taken to SKIPPED.
	 *
	 * @param array<string, mixed> $block_node Block node.
	 * @param string               $path_code  Chosen path.
	 * @param Execution_Context    $context    The run.
	 *
	 * @return void
	 */
	private function skip_untaken_paths( array $block_node, string $path_code, Execution_Context $context ): void {
		foreach ( (array) ( $block_node['paths'] ?? array() ) as $path ) {
			if ( (string) ( $path['path_code'] ?? '' ) === $path_code ) {
				continue;
			}
			foreach ( Tree_Walk::action_ids( (array) ( $path['items'] ?? array() ) ) as $action_id ) {
				if ( null === $context->action_log_id_for( $action_id ) ) {
					continue;
				}
				$this->log_store->mark_action_complete( $action_id, $context->get_recipe_log_id(), Automator_Status::SKIPPED );
			}
		}
	}
}
