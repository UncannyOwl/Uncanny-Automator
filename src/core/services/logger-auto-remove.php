<?php

namespace Uncanny_Automator\Services;

use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\Logger\Db\Data_Access;
use Uncanny_Automator\Prune_Logs;
use Uncanny_Automator_Pro\Loops_Process_Registry;

/**
 * Handles auto removal of log.
 *
 * @since 5.4 - Added
 *
 * @package Uncanny_Automator\Services
 */
class Logger_Auto_Removal {

	/**
	 * @var Prune_Logs
	 */
	protected $prune_logs = null;

	/**
	 * @var string
	 */
	protected $loop_identifier = '';

	/**
	 * Registers dependencies
	 *
	 * @param string $loop_identifier .
	 *
	 * @return void
	 */
	public function __construct( $loop_identifier = '' ) {
		$this->prune_logs      = new Prune_Logs( false );
		$this->loop_identifier = $loop_identifier;
	}

	/**
	 * Registers various hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {

		/**
		 * @see \Uncanny_Automator\Automator_Recipe_Process_Complete
		 */
		add_action(
			'automator_recipe_process_complete_complete_actions_before_closures',
			array(
				$this,
				'log_remove',
			),
			10,
			4
		);

		/**
		 * Async action callback.
		 */
		add_action( 'automator_pro_async_action_execution_after_invoked', array( $this, 'log_async_remove' ), 10, 1 );

		/**
		 * Loops callback.
		 */
		add_action( 'automator_pro_loop_batch_completed', array( $this, 'log_loops_remove' ), 10, 3 );

	}

	/**
	 * Remove a specific log.
	 *
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param int $recipe_log_id
	 * @param mixed[] $args
	 *
	 * @return bool Returns false on bail. Otherwise, returns true.
	 */
	public function log_remove( $recipe_id, $user_id, $recipe_log_id, $args ) {

		$params = array(
			'recipe_id'     => $recipe_id,
			'recipe_log_id' => $recipe_log_id,
		);

		// Disable by default.
		if ( false === Prune_Logs::should_remove_log( $params ) ) {
			return false;
		}

		$record = Data_Access::find_recipe_log_by_id( $recipe_log_id );

		// Bail on empty record.
		if ( empty( $record ) ) {
			return false;
		}

		// Assume every array key exists because we are fetching raw results as ARRAY_A with table columns. No need to check.
		$status = $record['completed'] ?? null;

		// Bail if status is not in removable status.
		if ( ! in_array( absint( $status ), Automator_Status::get_removable_statuses(), true ) ) {
			return false;
		}

		update_post_meta( $record['automator_recipe_id'], 'automator_completed_runs', $record['run_number'] );

		$this->prune_logs->purge_logs( $record['automator_recipe_id'], $record['ID'], $record['run_number'] );

		return true;
	}

	/**
	 * Removes the log when all loop in a recipe is completed.
	 *
	 * The action hook `automator_pro_loops_recipe_process_completed` already takes delays into account.
	 *
	 * @param int $recipe_id
	 * @param int $recipe_log_id
	 * @param int $run_number
	 *
	 * @return bool True if purged. Otherwise, false.
	 */
	public function log_loops_remove( $loop ) {

		$identifier = 'uap_loops_' . $loop['filter_id'] . '_completed';

		static $count = 0;

		if ( 1 === $count ) {
			return false;
		}

		$instance = new self( $identifier );

		add_action( $identifier, array( $instance, 'log_loops_remove_handler' ) );

		$count ++;

		return true;

	}

	/**
	 * Removes the log from an async action.
	 *
	 * @param mixed[] $action_data
	 *
	 * @return bool Returns false on bail. Otherwise, returns true.
	 */
	public function log_async_remove( $action_data ) {

		$recipe_id     = $action_data['args']['recipe_id'] ?? null;
		$recipe_log_id = $action_data['args']['recipe_log_id'] ?? null;
		$run_number    = $action_data['args']['run_number'] ?? null;

		$params = array(
			'recipe_id'     => $recipe_id,
			'recipe_log_id' => $recipe_log_id,
		);

		$should_remove_log = Prune_Logs::should_remove_log( $params );

		// Disable by default.
		if ( false === $should_remove_log ) {
			return false;
		}

		update_post_meta( $recipe_id, 'automator_completed_runs', $run_number );

		// Delete the logs if there are no in progress actions left or if there are no loops left in processing.
		if (
			false === Data_Access::action_log_has_in_progress( $recipe_log_id ) &&
			false === Data_Access::loop_entries_has_in_progress( $recipe_log_id )
		) {

			$this->prune_logs->purge_logs( $recipe_id, $recipe_log_id, $run_number );

			return true;
		}

		return false;

	}

	/**
	 * @return bool
	 */
	public function log_loops_remove_handler() {

		$process = Loops_Process_Registry::extract_process_id(
			str_replace( array( 'uap_loops_', '_completed' ), '', $this->loop_identifier )
		);

		// Bail if process is invalid.
		if ( empty( $process ) ) {
			return false;
		}

		$recipe_id     = $process['recipe_id'] ?? null;
		$recipe_log_id = $process['recipe_log_id'] ?? null;
		$run_number    = $process['run_number'] ?? null;

		$params = array(
			'recipe_id'     => $recipe_id,
			'recipe_log_id' => $recipe_log_id,
		);

		$should_remove_log = Prune_Logs::should_remove_log( $params );

		// Disable by default.
		if ( false === $should_remove_log ) {
			return false;
		}

		update_post_meta( $recipe_id, 'automator_completed_runs', $run_number );

		// Delete the logs if there are no in progress actions left or if there are no loops left in processing.
		if (
			false === Data_Access::action_log_has_in_progress( $recipe_log_id ) &&
			false === Data_Access::loop_entries_has_in_progress( $recipe_log_id )
		) {

			$this->prune_logs->purge_logs( $recipe_id, $recipe_log_id, $run_number );

			return true;
		}

		return false;

	}
}
