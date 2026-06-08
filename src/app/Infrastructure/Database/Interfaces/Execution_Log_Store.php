<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Contract for the execution log store.
 *
 * Owns CRUD across the execution log tables: uap_trigger_log,
 * uap_trigger_log_meta, uap_action_log, uap_action_log_meta,
 * uap_recipe_log, uap_recipe_count, uap_closure_log,
 * uap_closure_log_meta. Legacy DB handlers (Automator_DB_Handler_*)
 * delegate to the implementation. The recipe runner is the source of
 * truth for execution log writes.
 *
 * Defined as an open contract — the implementation is intentionally wide
 * because the legacy execution log surface is wide. Phase 5 of the
 * api-layer refactor moved it from `application/recipe_runner/services/`
 * to its correct home in `database/stores/` without altering the surface.
 *
 * @since 7.4.0
 */
interface Execution_Log_Store {

	// Trigger logs.
	public function add_trigger( int $user_id, int $trigger_id, int $recipe_id, bool $is_completed, int $recipe_log_id ): int;

	/**
	 * Insert a trigger meta row.
	 *
	 * @return int|null Insert ID, or null when the meta payload fails validation
	 *                  (missing meta_key, non-numeric trigger_log_id, dedup hit on
	 *                  sentence_human_readable, etc.).
	 */
	public function add_trigger_meta( int $trigger_id, int $trigger_log_id, int $run_number, array $meta ): ?int;

	public function mark_trigger_complete( int $trigger_id, int $user_id, int $recipe_id, int $recipe_log_id, int $trigger_log_id ): void;
	public function is_trigger_completed( int $user_id, int $trigger_id, int $recipe_id, int $recipe_log_id, bool $process_recipe, array $args ): bool;
	public function get_triggers_by_recipe_log_id( int $user_id, int $recipe_id, int $recipe_log_id, $run_number = null ): array;
	public function get_trigger_meta_rows( int $trigger_log_id ): array;

	// Action logs.
	public function add_action( array $data ): int;
	public function add_action_meta( int $user_id, int $action_log_id, int $action_id, string $meta_key, string $meta_value ): void;
	public function mark_action_complete( int $action_id, int $recipe_log_id, int $completed, string $error_message = '' ): void;

	/**
	 * Mark a single action_log row complete by primary key.
	 *
	 * Required for callers that need to target ONE iteration of a loop —
	 * `mark_action_complete()` updates every `uap_action_log` row matching
	 * `(action_id, recipe_log_id)`, which stomps sibling loop iterations.
	 * Webhook and async-completion paths that already hold the row's `ID`
	 * must use this instead.
	 *
	 * @param int    $action_log_id The uap_action_log primary key.
	 * @param int    $completed     Completion status constant.
	 * @param string $error_message Optional error message, routed to uap_error_log.
	 *
	 * @return void
	 */
	public function mark_action_complete_by_id( int $action_log_id, int $completed, string $error_message = '' ): void;

	/**
	 * Mark a deferred (background/async-dispatched) action's row IN_PROGRESS —
	 * but ONLY when it is still pending (NOT_COMPLETED).
	 *
	 * The background/async worker request fires inside the before-action
	 * filter (non-blocking POST); on fast servers the worker completes the
	 * action — terminal status, recipe finalized — BEFORE the dispatching
	 * request finalizes the deferral. An unconditional IN_PROGRESS write
	 * would downgrade that finished row and strand the run "In progress"
	 * forever. The WHERE guard makes the transition atomic.
	 *
	 * @param int $action_id     The action post ID.
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return void
	 */
	public function mark_action_scheduled( int $action_id, int $recipe_log_id ): void;
	public function get_action_error_messages( int $recipe_log_id ): array;
	public function get_action_completion_status( int $action_log_id ): ?int;

	/**
	 * Look up the action log ID for a (action, recipe_log) pair.
	 *
	 * Returns `0` (not `null`) when no row exists. The 0 sentinel is preserved
	 * for backward compatibility with the legacy `Automator_DB_Handler_Action::get_id()`
	 * surface — many callers compare to `0` directly.
	 */
	public function get_action_log_id_by_action_and_recipe_log( int $action_id, int $recipe_log_id ): int;

	public function get_all_action_statuses( int $recipe_log_id ): array;

	// Recipe logs.
	public function add_recipe_log( int $user_id, int $recipe_id, int $completed, int $run_number ): ?int;
	public function get_recipe_run_number( int $recipe_log_id ): ?int;
	public function mark_recipe_complete( int $recipe_log_id, int $completed ): void;
	public function mark_recipe_complete_with_error( int $recipe_id, int $recipe_log_id, int $error_status ): void;
	public function get_scheduled_actions_count( int $recipe_log_id, array $args ): int;
	public function recipe_log_pre_exists( int $recipe_id, int $user_id ): ?int;
	public function mark_recipe_incomplete( int $recipe_id, int $recipe_log_id ): void;
	public function update_recipe_count( int $recipe_id ): void;

	/**
	 * Fetch the raw recipe-log row for a given recipe_log_id.
	 *
	 * @return object|null wpdb row object, or null when no row matches.
	 */
	public function get_recipe_log_row( int $recipe_log_id ): ?object;

	public function get_recipe_status( int $recipe_log_id ): ?int;

	// Closure logs.

	/**
	 * Insert a closure log entry.
	 *
	 * @return int|null Insert ID, or null on insert failure. Note: in 7.3 the legacy
	 *                  signature returned `int|false`. Phase 1c of the api-layer
	 *                  refactor (review note C33) tightened this to `?int` so the
	 *                  contract has a single absent-value sentinel. Consumers must
	 *                  use `null !==` instead of `false !==`.
	 */
	public function add_closure_entry( array $data ): ?int;

	public function add_closure_entry_meta( array $identifiers, string $meta_key, string $meta_value ): void;

	/**
	 * Update a closure log entry's completion status.
	 *
	 * @param int $closure_log_id The closure log ID.
	 * @param int $completed      The completion status.
	 *
	 * @return void
	 */
	public function mark_closure_complete( int $closure_log_id, int $completed ): void;

	// Trigger log lookups for run-number sync.

	/**
	 * Get all trigger log IDs for a recipe log.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int[] Array of trigger log IDs.
	 */
	public function get_trigger_log_ids_by_recipe_log( int $recipe_log_id ): array;

	/**
	 * Correct run_number on trigger log meta rows — replaces stale values
	 * written during Stage 1 with the authoritative value from the recipe log.
	 *
	 * @param int[] $trigger_log_ids Trigger log IDs to update.
	 * @param int   $stale_run_number  The run number to replace.
	 * @param int   $correct_run_number The authoritative run number.
	 *
	 * @return void
	 */
	public function sync_trigger_meta_run_numbers( array $trigger_log_ids, int $stale_run_number, int $correct_run_number ): void;

	// Recipe count tracking.

	/**
	 * Insert a recipe count row (atomic, idempotent via INSERT IGNORE).
	 *
	 * Seeds the initial count from completed recipe log entries.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return void
	 */
	public function insert_recipe_count( int $recipe_id ): void;

	// Recipe log lookups.

	/**
	 * @param int   $recipe_id        The recipe ID.
	 * @param int   $user_id          The user ID.
	 * @param int[] $terminal_statuses Statuses that count as terminal.
	 *
	 * @return int|null
	 */
	public function find_pending_recipe_log( int $recipe_id, int $user_id, array $terminal_statuses ): ?int;

	/**
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int
	 */
	public function get_user_completed_recipe_count( int $recipe_id, int $user_id ): int;

	/**
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int
	 */
	public function get_max_recipe_log_number( int $recipe_id ): int;

	/**
	 * @param int      $recipe_id  The recipe ID.
	 * @param int      $user_id    The user ID.
	 * @param int      $run_number The run number.
	 * @param int|null $log_number The sequential log number.
	 *
	 * @return int
	 */
	public function insert_recipe_log_row( int $recipe_id, int $user_id, int $run_number, ?int $log_number ): int;

	/** @return bool */
	public function acquire_lock( string $lock_name, int $timeout = 10 ): bool;

	/** @return void */
	public function release_lock( string $lock_name ): void;

	/**
	 * @param string $table_name Unprefixed table name.
	 *
	 * @return int|null
	 */
	public function get_next_auto_increment( string $table_name ): ?int;

	/**
	 * @param int $user_id        The resolved user ID.
	 * @param int $recipe_log_id  The recipe log ID.
	 * @param int $trigger_log_id The trigger log ID.
	 *
	 * @return void
	 */
	public function update_logs_user_id( int $user_id, int $recipe_log_id, int $trigger_log_id ): void;

	/**
	 * @param int $trigger_log_id The trigger log ID.
	 * @param int $user_id        The user ID.
	 *
	 * @return int|null
	 */
	public function get_trigger_meta_run_number( int $trigger_log_id, int $user_id ): ?int;

	// Completion counts.

	/**
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int
	 */
	public function get_global_completion_count( int $recipe_id ): int;

	/**
	 * @param int[] $recipe_ids Recipe IDs.
	 *
	 * @return array
	 */
	public function batch_get_global_completion_counts( array $recipe_ids ): array;

	/**
	 * @param int   $user_id    The user ID.
	 * @param int[] $recipe_ids Recipe IDs.
	 *
	 * @return array
	 */
	public function batch_get_user_completion_counts( int $user_id, array $recipe_ids ): array;

	// Run number lookups.

	/**
	 * Get the max run_number for a recipe+user, excluding certain statuses.
	 *
	 * @param int   $recipe_id         The recipe ID.
	 * @param int   $user_id           The user ID.
	 * @param int[] $excluded_statuses Statuses to exclude.
	 *
	 * @return int|null Null when no matching rows.
	 */
	public function get_max_run_number( int $recipe_id, int $user_id, array $excluded_statuses ): ?int;

	// Recipe log meta.

	/**
	 * Insert a single recipe log meta row.
	 *
	 * @param int    $recipe_id     The recipe ID.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 * @param string $meta_value    The meta value.
	 *
	 * @return void
	 */
	public function add_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key, string $meta_value ): void;

	/**
	 * Copy specific recipe log meta rows from one log to another.
	 *
	 * Used by replay to duplicate structure metadata (actions_flow, triggers_logic)
	 * from the original run to the replayed run.
	 *
	 * @param int      $source_log_id The source recipe log ID.
	 * @param int      $target_log_id The target recipe log ID.
	 * @param int      $recipe_id     The recipe ID.
	 * @param string[] $keys          Meta keys to copy.
	 *
	 * @return void
	 */
	public function copy_recipe_log_meta( int $source_log_id, int $target_log_id, int $recipe_id, array $keys ): void;
}
