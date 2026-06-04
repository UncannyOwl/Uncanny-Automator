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
 */
interface Action_Error_Store {

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
	 * Check whether a recipe run has at least one actionable error.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 * @return bool True if at least one actionable error exists.
	 */
	public function has_actionable_errors( int $recipe_log_id ): bool;

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
	 * Backfill uap_error_log from legacy uap_action_log rows.
	 *
	 * @param int $batch_size Number of rows to process per call.
	 * @return int Count of migrated rows.
	 */
	public function migrate_legacy_errors( int $batch_size = 500 ): int;
}
