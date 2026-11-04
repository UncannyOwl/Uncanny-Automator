<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;

/**
 * Contract for the action error store.
 *
 * Persists structured {@see Action_Error} value objects into the
 * `uap_error_log` table alongside the legacy error_message column in
 * uap_action_log. Provides indexed queries for actionable-error checks so
 * the status resolver never needs strpos().
 *
 * @since 7.4.0
 * @since 8.0.0 Rewrites the stored error_message column (Error_Message_Rewrite_Store).
 */
interface Action_Error_Store extends Error_Message_Rewrite_Store {

	/**
	 * Insert a structured error into uap_error_log.
	 *
	 * @param int          $recipe_log_id The recipe log ID.
	 * @param int          $action_log_id The action log ID.
	 * @param Action_Error $error         The structured error value object.
	 *
	 * @return int The inserted row ID.
	 */
	public function store( int $recipe_log_id, int $action_log_id, Action_Error $error ): int;

	/**
	 * Store a system-level error (e.g. stuck recipe recovery, cron errors).
	 *
	 * @param int          $recipe_log_id The recipe log ID.
	 * @param Action_Error $error         The structured error.
	 *
	 * @return int The inserted row ID.
	 */
	public function store_system_error( int $recipe_log_id, Action_Error $error ): int;

	/**
	 * Store an error against a block (`item_type='block'`, `block_id` in the context).
	 *
	 * A run keeps at most one row per block and error code: a block in a loop body
	 * fails once per entity, and the first reason wins.
	 *
	 * @since 8.0.0
	 *
	 * @param int          $recipe_log_id The recipe log ID.
	 * @param int          $block_id      The block's post ID.
	 * @param Action_Error $error         The structured error.
	 *
	 * @return int The inserted row ID, or 0 when the run already holds this block and code.
	 */
	public function store_block_error( int $recipe_log_id, int $block_id, Action_Error $error ): int;

	/**
	 * Every block error of a run, oldest first.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return array Row objects with `item_type='block'`, or an empty array.
	 */
	public function get_block_errors( int $recipe_log_id ): array;

	/**
	 * Check whether a recipe run has at least one actionable error.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 * @return bool True if at least one actionable error exists.
	 */
	public function has_actionable_errors( int $recipe_log_id ): bool;

	/**
	 * Whether a run has at least one run-level error: a row store_system_error() wrote against this run
	 * (`item_type` 'system') whose code escalates to the recipe status (`is_actionable`). Rows written
	 * against no run (recipe log id 0) belong to no run.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return bool
	 */
	public function has_system_errors( int $recipe_log_id ): bool;

	/**
	 * Get all error rows for a recipe run, ordered by ID ascending.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 * @return array Array of row objects, or empty array if none found.
	 */
	public function get_by_recipe_log( int $recipe_log_id ): array;

	/**
	 * Get all error rows for a specific action log, ordered by ID ascending.
	 *
	 * @param int $action_log_id The action log ID.
	 * @return array Array of row objects, or empty array if none found.
	 */
	public function get_by_action_log( int $action_log_id ): array;

	/**
	 * The messages recorded for each of several action logs, oldest first.
	 *
	 * @since 8.0.1
	 *
	 * @param int[] $action_log_ids Action log IDs.
	 * @return array<int, string[]> Messages keyed by action log ID. An action log with no recorded error is left out.
	 */
	public function get_messages_by_action_logs( array $action_log_ids ): array;

	/**
	 * A prepared SQL condition, for a log list that composes its own query, that holds for each action log whose
	 * notes contain the search text. The notes are the messages recorded here; an action log with none (logged
	 * before 8.0 and not yet backfilled) keeps the message in its legacy column.
	 *
	 * @since 8.0.1
	 *
	 * @param string $action_log_id_column  The caller's column that holds the action log ID, such as `a.ID`.
	 * @param string $legacy_message_column The caller's column that holds the legacy message, such as `a.error_message`.
	 * @param string $search                The search text, matched anywhere in a message.
	 * @return string
	 * @throws \InvalidArgumentException When a column is not a plain column reference.
	 */
	public function notes_search_sql( string $action_log_id_column, string $legacy_message_column, string $search ): string;

	/**
	 * Backfill uap_error_log from legacy uap_action_log rows.
	 *
	 * @since 8.0.0 Counts only rows the database wrote, and raises when a row was not written.
	 *
	 * @param int $batch_size Number of rows to process per call.
	 * @return int Count of migrated rows.
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When a row of the batch was not written. The rows
	 *         that were written stay written; the next call selects the others again.
	 */
	public function migrate_legacy_errors( int $batch_size = 500 ): int;
}
