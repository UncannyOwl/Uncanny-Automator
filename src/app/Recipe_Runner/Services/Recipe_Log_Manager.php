<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Recipe log creation, simulation, and locking.
 *
 * Handles creating recipe log entries with MySQL locking to prevent race conditions.
 * Each public method has a single responsibility — no method exceeds CC 3.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.2
 */
class Recipe_Log_Manager {

	/**
	 * @var Run_Number_Service|null
	 */
	private $run_number;

	/**
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * @var Recipe_Data_Provider
	 */
	private $data_provider;

	/**
	 * @var Idempotency_Guard
	 */
	private $idempotency_guard;

	/**
	 * @param Execution_Log_Store|null $log_store         Optional log store instance.
	 * @param Recipe_Data_Provider|null $data_provider    Optional data provider instance.
	 * @param Idempotency_Guard|null   $idempotency_guard Optional idempotency guard instance.
	 * @param Run_Number_Service|null  $run_number        Optional shared run number service.
	 */
	public function __construct( ?Execution_Log_Store $log_store = null, ?Recipe_Data_Provider $data_provider = null, ?Idempotency_Guard $idempotency_guard = null, ?Run_Number_Service $run_number = null ) {
		$this->log_store         = $log_store ?? Database::get_execution_log_store();
		$this->data_provider     = $data_provider ?? new Recipe_Data_Provider();
		$this->idempotency_guard = $idempotency_guard ?? new Idempotency_Guard();
		$this->run_number        = $run_number;
	}

	/**
	 * @return Run_Number_Service
	 */
	private function run_number(): Run_Number_Service {
		$this->run_number = $this->run_number ?? new Run_Number_Service();
		return $this->run_number;
	}

	/**
	 * Find or create a recipe log entry.
	 *
	 * Three modes:
	 *   1. Existing in-progress log found → return it.
	 *   2. Simulate → return the next AUTO_INCREMENT ID without inserting.
	 *   3. Create → insert a real log row under a MySQL advisory lock.
	 *
	 * @param int   $recipe_id      The recipe ID.
	 * @param int   $user_id        The user ID.
	 * @param bool  $create_recipe  Whether to actually insert when no existing log.
	 * @param array $args           Trigger args (forwarded to hooks).
	 * @param bool  $maybe_simulate Whether to simulate (get next ID without inserting).
	 *
	 * @return array{existing: bool, recipe_log_id: int|null}
	 */
	public function maybe_create_recipe_log_entry( int $recipe_id, int $user_id, bool $create_recipe = true, array $args = array(), bool $maybe_simulate = false ): array {

		// Skip idempotency check during simulation — the real pass comes right after.
		if ( ! $maybe_simulate ) {
			$trigger_id = $args['trigger_id'] ?? 0;
			$event_hash = $args['event_hash'] ?? '';

			if ( $this->idempotency_guard->is_duplicate( $recipe_id, $user_id, absint( $trigger_id ), $event_hash ) ) {
				return $this->result( false, null );
			}
		}

		$existing_log_id = $this->find_pending_log( $recipe_id, $user_id );

		if ( null !== $existing_log_id && 0 !== absint( $user_id ) ) {
			return $this->result( true, $existing_log_id );
		}

		if ( $maybe_simulate ) {
			return $this->result( false, $this->simulate_next_log_id() );
		}

		if ( $create_recipe ) {
			return $this->result( false, $this->insert_recipe_log( $recipe_id, $user_id ) );
		}

		return $this->result( false, null );
	}

	/**
	 * Insert a recipe log entry with MySQL advisory lock for race-condition safety.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int|null The inserted recipe log ID, or null if run limit reached.
	 */
	public function insert_recipe_log( int $recipe_id, int $user_id ): ?int {

		if ( $this->is_recipe_run_limit_reached( $recipe_id, $user_id ) ) {
			return null;
		}

		return $this->do_locked_insert( $recipe_id, $user_id );
	}

	/**
	 * Get the highest log number for a recipe (called under lock).
	 *
	 * Uses MAX(log_number) vs COUNT(*) to handle gaps from deleted rows.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int
	 */
	public function get_recipe_log_count( int $recipe_id ): int {
		return $this->log_store->get_max_recipe_log_number( $recipe_id );
	}

	/**
	 * Change recipe log status from -1 to 0 when applicable.
	 *
	 * @param int  $recipe_id      The recipe ID.
	 * @param int  $user_id        The user ID.
	 * @param int  $recipe_log_id  The recipe log ID.
	 * @param bool $change_to_zero Whether to perform the change.
	 *
	 * @return void
	 */
	public function maybe_change_recipe_log_to_zero( int $recipe_id, int $user_id, int $recipe_log_id, bool $change_to_zero = false ): void {

		if ( ! $change_to_zero ) {
			return;
		}

		$existing_id = $this->log_store->recipe_log_pre_exists( $recipe_id, $user_id );

		if ( ! empty( $existing_id ) && (int) $existing_id === $recipe_log_id ) {
			$this->log_store->mark_recipe_incomplete( $recipe_id, $recipe_log_id );
		}
	}

	/**
	 * Update user_id across trigger_log, recipe_log, and trigger_log_meta tables.
	 *
	 * Called after user resolution in Everyone recipes to associate the
	 * anonymous recipe run with the resolved WordPress user.
	 *
	 * @param int   $user_id        The resolved user ID.
	 * @param int   $recipe_log_id  The recipe log ID.
	 * @param int   $trigger_id     The trigger ID.
	 * @param int   $trigger_log_id The trigger log ID.
	 * @param array $parsed_data    Optional parsed field data to store as trigger meta.
	 *
	 * @return void
	 */
	public function update_logs_user_id( int $user_id, int $recipe_log_id, int $trigger_id, int $trigger_log_id, array $parsed_data = array() ): void {

		$this->log_store->update_logs_user_id( $user_id, $recipe_log_id, $trigger_log_id );

		if ( ! empty( $parsed_data ) ) {
			$run_number = $this->log_store->get_trigger_meta_run_number( $trigger_log_id, $user_id );

			$this->log_store->add_trigger_meta(
				$trigger_id,
				$trigger_log_id,
				(int) $run_number,
				array(
					'user_id'        => $user_id,
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_id,
					'run_number'     => (int) $run_number,
					'meta_key'       => 'parsed_data',
					'meta_value'     => maybe_serialize( $parsed_data ),
				)
			);
		}
	}

	/**
	 * Find a pending (not yet started) recipe log for a user.
	 *
	 * "Pending" means any status NOT in the terminal set — typically -1 or 0.
	 * IN_PROGRESS (5) is terminal here because it indicates an actively
	 * running recipe that should not be reused.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int|null The log ID if found.
	 */
	protected function find_pending_log( int $recipe_id, int $user_id ): ?int {

		$terminal_statuses = array(
			Automator_Status::COMPLETED,
			Automator_Status::COMPLETED_WITH_ERRORS,
			Automator_Status::IN_PROGRESS,
			Automator_Status::IN_PROGRESS_WITH_ERROR,
			Automator_Status::DID_NOTHING,
			Automator_Status::COMPLETED_AWAITING,
			Automator_Status::COMPLETED_WITH_NOTICE,
			Automator_Status::FAILED,
		);

		return $this->log_store->find_pending_recipe_log( $recipe_id, $user_id, $terminal_statuses );
	}

	/**
	 * Check if the per-user recipe run limit has been reached.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return bool
	 */
	protected function is_recipe_run_limit_reached( int $recipe_id, int $user_id ): bool {

		if ( 0 === absint( $user_id ) ) {
			return false;
		}

		$completed_count = $this->log_store->get_user_completed_recipe_count( $recipe_id, $user_id );

		return $this->data_provider->recipe_number_times_completed( $recipe_id, $completed_count );
	}

	/**
	 * Insert a recipe log row under a MySQL advisory lock.
	 *
	 * Falls back to lock-free insert if the lock cannot be acquired.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int The inserted log ID.
	 */
	protected function do_locked_insert( int $recipe_id, int $user_id ): int {

		$run_number = $this->run_number()->get_next( $recipe_id, $user_id );
		$lock_name  = 'automator_recipe_log_' . $recipe_id;

		if ( ! $this->log_store->acquire_lock( $lock_name ) ) {
			automator_log( "Automator: Failed to acquire lock for recipe log creation. Falling back without log_number. Recipe ID: {$recipe_id}", 'insert_recipe_log' );

			$log_id = $this->log_store->insert_recipe_log_row( $recipe_id, $user_id, $run_number, null );
			$this->run_number()->prime( $recipe_id, $user_id, $run_number );

			return $log_id;
		}

		try {
			$log_number = $this->get_recipe_log_count( $recipe_id ) + 1;

			$log_id = $this->log_store->insert_recipe_log_row( $recipe_id, $user_id, $run_number, $log_number );
			$this->run_number()->prime( $recipe_id, $user_id, $run_number );

			return $log_id;
		} finally {
			$this->log_store->release_lock( $lock_name );
		}
	}

	/**
	 * Get the next AUTO_INCREMENT value without inserting a row.
	 *
	 * @return int|null The simulated next log ID.
	 */
	protected function simulate_next_log_id(): ?int {
		return $this->log_store->get_next_auto_increment( 'uap_recipe_log' );
	}

	/**
	 * Build a standardized result array.
	 *
	 * @param bool     $existing      Whether an existing log was found.
	 * @param int|null $recipe_log_id The log ID.
	 *
	 * @return array{existing: bool, recipe_log_id: int|null}
	 */
	private function result( bool $existing, ?int $recipe_log_id ): array {
		return array(
			'existing'      => $existing,
			'recipe_log_id' => $recipe_log_id,
		);
	}
}
