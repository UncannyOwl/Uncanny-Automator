<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Run_Snapshot_Store;
use Uncanny_Automator\App\Recipe_Runner\Dtos\Pipeline_Result;

/**
 * Prepares a recipe replay from a stored run snapshot.
 *
 * Extracted from Recipe_Runner — handles snapshot loading, log creation,
 * trigger log/meta duplication, and replay provenance recording. Returns
 * an execution-ready Pipeline_Result that the Runner feeds into stages 3-5.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
final class Replay_Service {

	/**
	 * Meta keys copied from the original recipe log to the replay.
	 * These are recipe-structure metadata that the REST log viewer needs.
	 */
	private const STRUCTURE_META_KEYS = array( 'actions_flow', 'triggers_logic' );

	/**
	 * @var Run_Snapshot_Store
	 */
	private Run_Snapshot_Store $snapshot_store;

	/**
	 * @var Recipe_Log_Manager
	 */
	private Recipe_Log_Manager $log_manager;

	/**
	 * @var Execution_Log_Store
	 */
	private Execution_Log_Store $log_store;

	/**
	 * @param Run_Snapshot_Store|null  $snapshot_store Optional snapshot store.
	 * @param Recipe_Log_Manager|null     $log_manager    Optional log manager.
	 * @param Execution_Log_Store|null $log_store      Optional execution log store.
	 */
	public function __construct(
		?Run_Snapshot_Store $snapshot_store = null,
		?Recipe_Log_Manager $log_manager = null,
		?Execution_Log_Store $log_store = null
	) {
		$this->snapshot_store = $snapshot_store ?? Database::get_run_snapshot_store();
		$this->log_manager   = $log_manager ?? new Recipe_Log_Manager();
		$this->log_store     = $log_store ?? Database::get_execution_log_store();
	}

	/**
	 * Prepare a replay from a snapshot — everything except stage execution.
	 *
	 * Loads the snapshot, creates a new recipe log, duplicates trigger logs
	 * and meta, records replay provenance, and returns an execution-ready
	 * Pipeline_Result. The caller (Recipe_Runner) feeds this into
	 * execute_stages_from( 'action_run' ).
	 *
	 * @param int $original_recipe_log_id The recipe log ID to replay.
	 *
	 * @return Pipeline_Result Ready for stage execution, or halted on error.
	 */
	public function prepare_replay( int $original_recipe_log_id ): Pipeline_Result {

		$snapshot_vo = $this->snapshot_store->get( $original_recipe_log_id );

		if ( null === $snapshot_vo ) {
			$result = new Pipeline_Result();
			return $result->halt( 'Snapshot not found or expired.' );
		}

		$snapshot  = $snapshot_vo->to_array();
		$recipe_id = (int) ( $snapshot['recipe_id'] ?? 0 );
		$user_id   = (int) ( $snapshot['user_id'] ?? 0 );

		// 1. Create new recipe log (handles locking, run_number, log_number).
		$new_log_id = $this->log_manager->insert_recipe_log( $recipe_id, $user_id );

		if ( null === $new_log_id ) {
			$result = new Pipeline_Result();
			return $result->halt( 'Failed to create recipe log for replay.' );
		}

		$run_number = $this->log_store->get_recipe_run_number( $new_log_id ) ?? 1;

		// Copy structure metadata (actions_flow, triggers_logic) for the log viewer.
		$this->log_store->copy_recipe_log_meta( $original_recipe_log_id, $new_log_id, $recipe_id, self::STRUCTURE_META_KEYS );

		// Record replay provenance.
		$this->log_store->add_recipe_log_meta( $recipe_id, $new_log_id, 'replay_source', (string) $original_recipe_log_id );

		// 2. Rebuild trigger logs + copy meta from snapshot.
		$args = $this->rebuild_trigger_context( $snapshot, $new_log_id, $run_number, $recipe_id, $user_id, $original_recipe_log_id );

		// 3. Build execution-ready result.
		$result = new Pipeline_Result();
		$result->set_execution_ready( $recipe_id, $user_id, $new_log_id, $args );

		return $result;
	}

	/**
	 * Rebuild trigger log entries and meta from the snapshot.
	 *
	 * Creates new trigger log rows (marked complete) and copies all meta
	 * rows so the token parser can resolve values for the replayed run.
	 *
	 * @param array $snapshot                 The snapshot data.
	 * @param int   $new_log_id              The new recipe log ID.
	 * @param int   $run_number              The run number for the new log.
	 * @param int   $recipe_id               The recipe ID.
	 * @param int   $user_id                 The user ID.
	 * @param int   $original_recipe_log_id  The original recipe log ID.
	 *
	 * @return array The rebuilt trigger args.
	 */
	private function rebuild_trigger_context( array $snapshot, int $new_log_id, int $run_number, int $recipe_id, int $user_id, int $original_recipe_log_id ): array {

		$args                  = $snapshot['trigger_args'] ?? array();
		$args['recipe_log_id'] = $new_log_id;
		$args['run_number']    = $run_number;
		$args['replay_source'] = $original_recipe_log_id;
		$args['recipe_id']     = $recipe_id;
		$args['user_id']       = $user_id;

		$triggers = $snapshot['triggers'] ?? array();

		foreach ( $triggers as $trigger_data ) {

			$trigger_id = (int) ( $trigger_data['trigger_id'] ?? 0 );

			if ( 0 === $trigger_id ) {
				continue;
			}

			// Create new trigger log entry (marked complete — triggers already validated).
			$new_trigger_log_id = $this->log_store->add_trigger(
				$user_id,
				$trigger_id,
				$recipe_id,
				true,
				$new_log_id
			);

			if ( 0 === $new_trigger_log_id ) {
				continue;
			}

			// Copy trigger meta rows — this is what the token parser reads.
			$meta_rows = $trigger_data['meta_rows'] ?? array();

			foreach ( $meta_rows as $meta ) {
				$this->log_store->add_trigger_meta(
					$trigger_id,
					$new_trigger_log_id,
					$run_number,
					array(
						'user_id'        => $user_id,
						'trigger_id'     => $trigger_id,
						'trigger_log_id' => $new_trigger_log_id,
						'run_number'     => $run_number,
						'meta_key'       => $meta['meta_key'] ?? '',
						'meta_value'     => $meta['meta_value'] ?? '',
					)
				);
			}

			// Set the trigger context for token resolution.
			$args['trigger_id']     = $trigger_id;
			$args['trigger_log_id'] = $new_trigger_log_id;

			// Build recipe_triggers array for multi-trigger support.
			$args['recipe_triggers'][ $trigger_id ] = array(
				'recipe_id'      => $recipe_id,
				'recipe_log_id'  => $new_log_id,
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $new_trigger_log_id,
				'user_id'        => $user_id,
				'run_number'     => $run_number,
				'meta'           => $args['meta'] ?? '',
				'code'           => $args['code'] ?? '',
			);
		}

		return $args;
	}
}
