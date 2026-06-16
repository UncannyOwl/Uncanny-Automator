<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Unified execution log store — owns all execution log CRUD for the recipe runner.
 *
 * Tables: uap_trigger_log, uap_trigger_log_meta, uap_action_log, uap_action_log_meta,
 * uap_recipe_log, uap_recipe_count, uap_closure_log, uap_closure_log_meta.
 *
 * Legacy DB handlers (Automator_DB_Handler_*) delegate TO this class.
 * The recipe runner is the source of truth for execution log writes.
 *
 * Phase 5 of the api-layer refactor moved this from
 * `application/recipe_runner/services/Log_Store` to its correct home in
 * `database/stores/` and added constructor `$wpdb` injection to match the
 * other stores in this folder.
 *
 * @package Uncanny_Automator\App\Infrastructure\Database\Stores
 * @since   7.4.0
 */
final class WP_Execution_Log_Store implements Execution_Log_Store {

	/**
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb wpdb instance.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	// ── Trigger logs ──

	/**
	 * @param int  $user_id        The user ID.
	 * @param int  $trigger_id     The trigger ID.
	 * @param int  $recipe_id      The recipe ID.
	 * @param bool $is_completed   Whether the trigger is completed.
	 * @param int  $recipe_log_id  The recipe log ID.
	 *
	 * @return int The trigger log ID.
	 */
	public function add_trigger( int $user_id, int $trigger_id, int $recipe_id, bool $is_completed, int $recipe_log_id ): int {

		$result = $this->wpdb->insert(
			$this->wpdb->prefix . 'uap_trigger_log',
			array(
				'date_time'               => current_time( 'mysql' ),
				'user_id'                 => $user_id,
				'automator_trigger_id'    => $trigger_id,
				'automator_recipe_id'     => $recipe_id,
				'completed'               => $is_completed,
				'automator_recipe_log_id' => $recipe_log_id,
			),
			array( '%s', '%d', '%d', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			automator_log( 'Failed to insert trigger log row. Recipe: ' . $recipe_id . ', Trigger: ' . $trigger_id, 'WP_Execution_Log_Store' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @param int   $trigger_id     The trigger ID.
	 * @param int   $trigger_log_id The trigger log ID.
	 * @param int   $run_number     The run number.
	 * @param array $meta           Meta data array with user_id, meta_key, meta_value, etc.
	 *
	 * @return int|null Insert result or null on validation failure.
	 */
	public function add_trigger_meta( int $trigger_id, int $trigger_log_id, int $run_number, array $meta ): ?int {

		$user_id    = isset( $meta['user_id'] ) ? absint( $meta['user_id'] ) : 0;
		$meta_key   = isset( $meta['meta_key'] ) ? sanitize_text_field( $meta['meta_key'] ) : '';
		$meta_value = $meta['meta_value'] ?? '';
		$run_time   = $meta['run_time'] ?? current_time( 'mysql' );

		// Legacy fallback: default to current user when user_id not provided.
		// Preserved for backward compat with callers that omit user_id.
		// Explicit 0 from anonymous recipes is valid and NOT overridden.
		if ( ! isset( $meta['user_id'] ) ) {
			$user_id = get_current_user_id();
		}

		if ( ! is_numeric( $trigger_log_id ) || empty( $meta_key ) ) {
			return null;
		}

		// Dedup: don't insert duplicate sentence_human_readable.
		if ( 'sentence_human_readable' === $meta_key ) {
			if ( ! empty( $this->get_trigger_sentence_meta( $user_id, $trigger_log_id, $run_number, $meta_key ) ) ) {
				return null;
			}
		}

		$this->wpdb->insert(
			$this->wpdb->prefix . 'uap_trigger_log_meta',
			array(
				'user_id'                  => $user_id,
				'automator_trigger_log_id' => $trigger_log_id,
				'automator_trigger_id'     => $trigger_id,
				'run_number'               => $run_number,
				'meta_key'                 => $meta_key,
				'meta_value'               => $meta_value,
				'run_time'                 => $run_time,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @param int   $trigger_id     The trigger ID.
	 * @param int   $user_id        The user ID.
	 * @param int   $recipe_id      The recipe ID.
	 * @param int   $recipe_log_id  The recipe log ID.
	 * @param int   $trigger_log_id The trigger log ID.
	 *
	 * @return void
	 */
	public function mark_trigger_complete( int $trigger_id, int $user_id, int $recipe_id, int $recipe_log_id, int $trigger_log_id ): void {

		$where        = array(
			'user_id'                 => $user_id,
			'automator_trigger_id'    => $trigger_id,
			'automator_recipe_id'     => $recipe_id,
			'ID'                      => $trigger_log_id,
			'automator_recipe_log_id' => $recipe_log_id,
		);
		$where_format = array( '%d', '%d', '%d', '%d', '%d' );

		$updated = $this->wpdb->update(
			$this->wpdb->prefix . 'uap_trigger_log',
			array(
				'completed' => true,
				'date_time' => current_time( 'mysql' ),
			),
			$where,
			array( '%d', '%s' ),
			$where_format
		);

		if ( $updated ) {
			Dispatcher::action( 'automator_trigger_marked_complete', $trigger_id, $user_id, $recipe_id, $recipe_log_id, $trigger_log_id );
		}
	}

	/**
	 * Check if a trigger is completed for a given recipe run.
	 *
	 * Delegates to legacy handler — complex JOIN query with two code paths.
	 *
	 * @param int   $user_id        The user ID.
	 * @param int   $trigger_id     The trigger ID.
	 * @param int   $recipe_id      The recipe ID.
	 * @param int   $recipe_log_id  The recipe log ID.
	 * @param bool  $process_recipe Whether to use simple or JOIN query.
	 * @param array $args           Trigger args.
	 *
	 * @return bool
	 */
	public function is_trigger_completed( int $user_id, int $trigger_id, int $recipe_id, int $recipe_log_id, bool $process_recipe, array $args ): bool {

		$table = $this->wpdb->prefix . 'uap_trigger_log';

		if ( $process_recipe ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT completed FROM {$table} WHERE user_id = %d AND automator_trigger_id = %d AND automator_recipe_id = %d AND automator_recipe_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$trigger_id,
					$recipe_id,
					$recipe_log_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT t.completed FROM {$table} t LEFT JOIN {$this->wpdb->prefix}uap_recipe_log r ON t.automator_recipe_log_id = r.ID LEFT JOIN {$this->wpdb->prefix}uap_action_log a ON t.automator_recipe_log_id = a.automator_recipe_log_id WHERE t.user_id = %d AND t.automator_trigger_id = %d AND t.automator_recipe_id = %d AND t.automator_recipe_log_id = %d AND r.completed = 1 AND a.completed = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$trigger_id,
					$recipe_id,
					$recipe_log_id
				)
			);
		}

		return ! empty( $result );
	}

	/**
	 * @param int      $user_id        The user ID.
	 * @param int      $recipe_id      The recipe ID.
	 * @param int      $recipe_log_id  The recipe log ID.
	 * @param int|null $run_number     The run number.
	 *
	 * @return array
	 */
	public function get_triggers_by_recipe_log_id( int $user_id, int $recipe_id, int $recipe_log_id, $run_number = null ): array {

		$base_query = "SELECT t.ID as trigger_log_id, t.automator_trigger_id FROM {$this->wpdb->prefix}uap_trigger_log t JOIN {$this->wpdb->prefix}uap_recipe_log r ON r.ID = t.automator_recipe_log_id AND t.automator_recipe_log_id = %d WHERE t.automator_recipe_id = %d AND r.user_id = %d AND t.completed = %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$params = array( $recipe_log_id, $recipe_id, $user_id, 1 );

		if ( null !== $run_number ) {
			$base_query .= ' AND r.run_number = %d';
			$params[]    = $run_number;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->get_results(
			$this->wpdb->prepare( $base_query, $params )
		);

		return is_array( $result ) ? $result : array();
	}

	// ── Action logs ──

	/**
	 * @param array $data Action log data (user_id, action_id, recipe_id, recipe_log_id, completed, error_message, date_time).
	 *
	 * @return int The action log ID.
	 */
	public function add_action( array $data ): int {

		$error_message = $data['error_message'] ?? '';

		if ( ! empty( $error_message ) ) {
			$error_message = wp_kses(
				$error_message,
				array(
					'a' => array(
						'href' => array(),
						'title' => array(),
						'target' => array(),
					),
					'br' => array(),
				)
			);
		}

		$date_time = $data['date_time'] ?? current_time( 'mysql' );

		$result = $this->wpdb->insert(
			$this->wpdb->prefix . 'uap_action_log',
			array(
				'date_time'               => $date_time,
				'user_id'                 => absint( $data['user_id'] ),
				'automator_action_id'     => absint( $data['action_id'] ),
				'automator_recipe_id'     => absint( $data['recipe_id'] ),
				'automator_recipe_log_id' => absint( $data['recipe_log_id'] ),
				'completed'               => $data['completed'],
				'error_message'           => $error_message,
			),
			array( '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			automator_log( 'Failed to insert action log row. Recipe: ' . absint( $data['recipe_id'] ) . ', Action: ' . absint( $data['action_id'] ), 'WP_Execution_Log_Store' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @param int    $user_id       The user ID.
	 * @param int    $action_log_id The action log ID.
	 * @param int    $action_id     The action ID.
	 * @param string $meta_key      The meta key.
	 * @param string $meta_value    The meta value.
	 *
	 * @return void
	 */
	public function add_action_meta( int $user_id, int $action_log_id, int $action_id, string $meta_key, string $meta_value ): void {

		$this->wpdb->insert(
			$this->wpdb->prefix . 'uap_action_log_meta',
			array(
				'user_id'                 => $user_id,
				'automator_action_log_id' => $action_log_id,
				'automator_action_id'     => $action_id,
				'meta_key'                => $meta_key,
				'meta_value'              => $meta_value,
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Mark an action's completion status in uap_action_log.
	 *
	 * Error messages are no longer written here — they go to uap_error_log
	 * via Action_Error_Store. The error_message column is left untouched
	 * (preserves any legacy data, new runs get empty string).
	 *
	 * @param int $action_id      The action ID.
	 * @param int $recipe_log_id  The recipe log ID.
	 * @param int $completed      The completion status.
	 *
	 * @return void
	 */
	public function mark_action_complete( int $action_id, int $recipe_log_id, int $completed, string $error_message = '' ): void {

		$updated = $this->wpdb->update(
			$this->wpdb->prefix . 'uap_action_log',
			array(
				'completed' => $completed,
				'date_time' => current_time( 'mysql' ),
			),
			array(
				'automator_action_id'     => $action_id,
				'automator_recipe_log_id' => $recipe_log_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		if ( $updated ) {
			// Persist the error to uap_error_log (SSOT) when legacy callers
			// pass a 4th arg. The uap_action_log.error_message column stays
			// frozen — only the error_log table receives new writes. Without
			// this, the 4-arg public API silently discards the message.
			if ( '' !== $error_message ) {
				$this->persist_legacy_error( $action_id, $recipe_log_id, $completed, $error_message );
			}

			Dispatcher::action( 'automator_action_completion_status_changed', $action_id, $recipe_log_id, null, $completed, $error_message );

			$status_name = Automator_Status::get_class_name( $completed );

			if ( '' !== $status_name ) {
				$status_name = str_replace( '-', '_', $status_name );
				Dispatcher::action( "automator_action_marked_{$status_name}", $action_id, $recipe_log_id, null, $completed, $error_message );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function mark_action_scheduled( int $action_id, int $recipe_log_id, ?string $date_time = null ): void {

		// Record the supplied scheduled/delayed run time so the log reflects when
		// the action is due rather than the trigger time; fall back to the current
		// time for deferred dispatches with no schedule (e.g. background processing).
		$date_time = ! empty( $date_time ) ? $date_time : current_time( 'mysql' );

		// Genuine transition NOT_COMPLETED → IN_PROGRESS (also stamps the scheduled
		// date). Guarded so a row the worker already finished (terminal status) is
		// never downgraded — see the interface docblock for the dispatch-vs-worker
		// race. Only this real status change dispatches the status events.
		$promoted = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->wpdb->prefix}uap_action_log
				SET completed = %d, date_time = %s
				WHERE automator_action_id = %d
				AND automator_recipe_log_id = %d
				AND completed = %d",
				Automator_Status::IN_PROGRESS,
				$date_time,
				$action_id,
				$recipe_log_id,
				Automator_Status::NOT_COMPLETED
			)
		);

		if ( $promoted ) {
			Dispatcher::action( 'automator_action_completion_status_changed', $action_id, $recipe_log_id, null, Automator_Status::IN_PROGRESS, '' );
			Dispatcher::action( 'automator_action_marked_in_progress', $action_id, $recipe_log_id, null, Automator_Status::IN_PROGRESS, '' );
			return;
		}

		// No transition: Pro's async postpone already moved the row to IN_PROGRESS
		// before this finalize ran (logging the trigger time). Correct the
		// scheduled date only — the status is unchanged, so the status-change
		// events must NOT re-fire. A terminal row won't match the IN_PROGRESS
		// guard, so it is still left untouched.
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->wpdb->prefix}uap_action_log
				SET date_time = %s
				WHERE automator_action_id = %d
				AND automator_recipe_log_id = %d
				AND completed = %d",
				$date_time,
				$action_id,
				$recipe_log_id,
				Automator_Status::IN_PROGRESS
			)
		);
	}

	/**
	 * Persist a legacy-style error string to uap_error_log.
	 *
	 * Resolves the action_log_id from (action_id, recipe_log_id) and writes
	 * through Action_Error_Store so the error is visible to
	 * Recipe_Status_Resolver and any admin UI reading from uap_error_log.
	 *
	 * @param int    $action_id     The action template ID.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param int    $completed     The completion status constant.
	 * @param string $error_message The legacy error string.
	 *
	 * @return void
	 */
	private function persist_legacy_error( int $action_id, int $recipe_log_id, int $completed, string $error_message ): void {

		$action_log_id = $this->get_action_log_id_by_action_and_recipe_log( $action_id, $recipe_log_id );

		if ( 0 === $action_log_id ) {
			return;
		}

		$error = new Action_Error(
			Error_Code::EXECUTION_FAILED,
			$error_message,
			array(
				'completed' => $completed,
				'legacy'    => true,
			)
		);

		Database::get_action_error_store()->store( $recipe_log_id, $action_log_id, $error );
	}

	/**
	 * Mark a single uap_action_log row complete by primary key.
	 *
	 * Targets ONE row — safe for loop iterations where multiple rows exist
	 * for the same (action_id, recipe_log_id) pair. Webhook and async-
	 * completion callers that already hold the row ID must use this path.
	 *
	 * Mirrors mark_action_complete()'s hook + error_log behavior so the two
	 * paths produce identical side effects for single-row writes.
	 *
	 * @param int    $action_log_id The uap_action_log primary key.
	 * @param int    $completed     Completion status constant.
	 * @param string $error_message Optional error message.
	 *
	 * @return void
	 */
	public function mark_action_complete_by_id( int $action_log_id, int $completed, string $error_message = '' ): void {

		$updated = $this->wpdb->update(
			$this->wpdb->prefix . 'uap_action_log',
			array(
				'completed' => $completed,
				'date_time' => current_time( 'mysql' ),
			),
			array( 'ID' => $action_log_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		if ( ! $updated ) {
			return;
		}

		// Recover the action_id + recipe_log_id pair for hook payloads.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT automator_action_id, automator_recipe_log_id FROM {$this->wpdb->prefix}uap_action_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_log_id
			)
		);

		if ( null === $row ) {
			return;
		}

		$action_id     = (int) $row->automator_action_id;
		$recipe_log_id = (int) $row->automator_recipe_log_id;

		if ( '' !== $error_message ) {
			$error = new Action_Error(
				Error_Code::EXECUTION_FAILED,
				$error_message,
				array(
					'completed' => $completed,
					'by_id'     => true,
				)
			);
			Database::get_action_error_store()->store( $recipe_log_id, $action_log_id, $error );
		}

		Dispatcher::action( 'automator_action_completion_status_changed', $action_id, $recipe_log_id, null, $completed, $error_message );

		$status_name = Automator_Status::get_class_name( $completed );
		if ( '' !== $status_name ) {
			$status_name = str_replace( '-', '_', $status_name );
			Dispatcher::action( "automator_action_marked_{$status_name}", $action_id, $recipe_log_id, null, $completed, $error_message );
		}
	}

	/**
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return array
	 */
	public function get_action_error_messages( int $recipe_log_id ): array {

		// Read from uap_error_log (source of truth) joined with uap_action_log for the completed status.
		// Falls back to uap_action_log.error_message for pre-migration rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT COALESCE(e.error_message, a.error_message) as error_message, a.completed
				FROM {$this->wpdb->prefix}uap_action_log a
				LEFT JOIN {$this->wpdb->prefix}uap_error_log e ON e.action_log_id = a.ID
				WHERE a.automator_recipe_log_id = %d
				AND (e.error_message IS NOT NULL OR (a.error_message IS NOT NULL AND a.error_message != ''))
				ORDER BY a.ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @param int $action_log_id The action log ID.
	 *
	 * @return int|null
	 */
	public function get_action_completion_status( int $action_log_id ): ?int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT completed FROM {$this->wpdb->prefix}uap_action_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_log_id
			)
		);

		return null !== $status ? (int) $status : null;
	}

	/**
	 * Get the action log ID for a given action + recipe log combination.
	 *
	 * @param int $action_id      The action post ID.
	 * @param int $recipe_log_id  The recipe log ID.
	 *
	 * @return int
	 */
	public function get_action_log_id_by_action_and_recipe_log( int $action_id, int $recipe_log_id ): int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT ID FROM {$this->wpdb->prefix}uap_action_log WHERE automator_action_id = %d AND automator_recipe_log_id = %d ORDER BY ID DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_id,
				$recipe_log_id
			)
		);

		return null !== $id ? (int) $id : 0;
	}

	/**
	 * Get all trigger_log_meta rows for a given trigger log.
	 *
	 * Used by snapshot capture to copy trigger context for replay.
	 *
	 * @param int $trigger_log_id The trigger log ID.
	 *
	 * @return array Array of associative arrays with meta_key and meta_value.
	 */
	public function get_trigger_meta_rows( int $trigger_log_id ): array {

		if ( 0 === $trigger_log_id ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$this->wpdb->prefix}uap_trigger_log_meta WHERE automator_trigger_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$trigger_log_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get all action completion statuses for a recipe log.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int[] Array of Automator_Status integers.
	 */
	public function get_all_action_statuses( int $recipe_log_id ): array {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT completed FROM {$this->wpdb->prefix}uap_action_log WHERE automator_recipe_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return is_array( $rows ) ? array_map( 'intval', $rows ) : array();
	}

	// ── Recipe logs ──

	/**
	 * @param int $user_id    The user ID.
	 * @param int $recipe_id  The recipe ID.
	 * @param int $completed  The completion status.
	 * @param int $run_number The run number.
	 *
	 * @return int|null The recipe log ID.
	 */
	public function add_recipe_log( int $user_id, int $recipe_id, int $completed, int $run_number ): ?int {

		$this->wpdb->insert(
			$this->wpdb->prefix . 'uap_recipe_log',
			array(
				'date_time'           => current_time( 'mysql' ),
				'user_id'             => $user_id,
				'automator_recipe_id' => $recipe_id,
				'completed'           => $completed,
				'run_number'          => $run_number,
			),
			array( '%s', '%d', '%d', '%d', '%d' )
		);

		return 0 !== $this->wpdb->insert_id ? (int) $this->wpdb->insert_id : null;
	}

	/**
	 * Get the run_number for a recipe log entry.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int|null
	 */
	public function get_recipe_run_number( int $recipe_log_id ): ?int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$run_number = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT run_number FROM {$this->wpdb->prefix}uap_recipe_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return null !== $run_number ? (int) $run_number : null;
	}
	/**
	 * Mark recipe complete.
	 *
	 * @param int $recipe_log_id The ID.
	 * @param int $completed The completed.
	 */
	public function mark_recipe_complete( int $recipe_log_id, int $completed ): void {

		$updated = $this->wpdb->update(
			$this->wpdb->prefix . 'uap_recipe_log',
			array(
				'date_time' => current_time( 'mysql' ),
				'completed' => $completed,
			),
			array( 'ID' => $recipe_log_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		if ( $updated ) {
			Dispatcher::action( 'automator_recipe_marked_complete', $recipe_log_id, $completed );
		}
	}

	/**
	 * @param int $recipe_id     The recipe ID.
	 * @param int $recipe_log_id The recipe log ID.
	 * @param int $error_status  The error status.
	 *
	 * @return void
	 */
	public function mark_recipe_complete_with_error( int $recipe_id, int $recipe_log_id, int $error_status ): void {

		$updated = $this->wpdb->update(
			$this->wpdb->prefix . 'uap_recipe_log',
			array( 'completed' => $error_status ),
			array(
				'ID' => $recipe_log_id,
				'automator_recipe_id' => $recipe_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);

		if ( $updated ) {
			Dispatcher::action( 'automator_recipe_marked_complete_with_error', $recipe_id, $recipe_log_id, $error_status );
		}
	}

	/**
	 * @param int   $recipe_log_id The recipe log ID.
	 * @param array $args          Recipe args.
	 *
	 * @return int
	 */
	public function get_scheduled_actions_count( int $recipe_log_id, array $args ): int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->wpdb->prefix}uap_action_log WHERE completed = %d AND automator_recipe_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				Automator_Status::IN_PROGRESS,
				$recipe_log_id
			)
		);

		return absint( Dispatcher::filter( 'automator_has_scheduled_actions', absint( $count ), $recipe_log_id, $args ) );
	}

	/**
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int|null
	 */
	public function recipe_log_pre_exists( int $recipe_id, int $user_id ): ?int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT ID FROM {$this->wpdb->prefix}uap_recipe_log WHERE completed = %d AND automator_recipe_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				-1,
				$recipe_id,
				$user_id
			)
		);

		return null !== $id ? (int) $id : null;
	}

	/**
	 * @param int $recipe_id     The recipe ID.
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return void
	 */
	public function mark_recipe_incomplete( int $recipe_id, int $recipe_log_id ): void {

		$this->wpdb->update(
			$this->wpdb->prefix . 'uap_recipe_log',
			array( 'completed' => 0 ),
			array(
				'ID' => $recipe_log_id,
				'automator_recipe_id' => $recipe_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return void
	 */
	public function update_recipe_count( int $recipe_id ): void {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->wpdb->prefix}uap_recipe_count SET runs = runs + 1 WHERE recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id
			)
		);
	}

	/**
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return object|null Row with automator_recipe_id and user_id, or null when no row matches.
	 */
	public function get_recipe_log_row( int $recipe_log_id ): ?object {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT automator_recipe_id, user_id FROM {$this->wpdb->prefix}uap_recipe_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Get the current recipe status from the recipe log.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int|null The current status, or null if no record exists.
	 */
	public function get_recipe_status( int $recipe_log_id ): ?int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT completed FROM {$this->wpdb->prefix}uap_recipe_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return null !== $status ? (int) $status : null;
	}

	// ── Closure logs ──

	/**
	 * @param array $data Closure entry data.
	 *
	 * @return int|null The closure log ID, or null on insert failure. The legacy
	 *                  signature returned `int|false`; Phase 1c of the api-layer
	 *                  refactor (review note C33) tightened it to `?int` so the
	 *                  contract has a single absent-value sentinel.
	 */
	public function add_closure_entry( array $data ): ?int {

		$data = wp_parse_args(
			$data,
			array(
				'user_id'                 => null,
				'automator_closure_id'    => null,
				'automator_recipe_id'     => null,
				'automator_recipe_log_id' => null,
				'completed'               => 0,
			)
		);

		$inserted = $this->wpdb->insert(
			$this->wpdb->prefix . 'uap_closure_log',
			array(
				'user_id'                 => $data['user_id'],
				'automator_closure_id'    => $data['automator_closure_id'],
				'automator_recipe_id'     => $data['automator_recipe_id'],
				'automator_recipe_log_id' => $data['automator_recipe_log_id'],
				'completed'               => $data['completed'],
			),
			array( '%d', '%d', '%d', '%d', '%d' )
		);

		if ( false === $inserted ) {
			return null;
		}

		$insert_id = (int) $this->wpdb->insert_id;

		return $insert_id > 0 ? $insert_id : null;
	}

	/**
	 * @param array  $identifiers Closure identifiers (user_id, automator_closure_id, automator_closure_log_id).
	 * @param string $meta_key    The meta key.
	 * @param string $meta_value  The meta value.
	 *
	 * @return void
	 */
	public function add_closure_entry_meta( array $identifiers, string $meta_key, string $meta_value ): void {

		if ( empty( $meta_key ) ) {
			return;
		}

		$identifiers = wp_parse_args(
			$identifiers,
			array(
				'user_id'                  => null,
				'automator_closure_id'     => null,
				'automator_closure_log_id' => null,
			)
		);

		$this->wpdb->insert(
			$this->wpdb->prefix . 'uap_closure_log_meta',
			array(
				'user_id'                  => $identifiers['user_id'],
				'automator_closure_id'     => $identifiers['automator_closure_id'],
				'automator_closure_log_id' => $identifiers['automator_closure_log_id'],
				'meta_key'                 => $meta_key,
				'meta_value'               => $meta_value,
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Update a closure log entry's completion status.
	 *
	 * @param int $closure_log_id The closure log ID.
	 * @param int $completed      The completion status.
	 *
	 * @return void
	 */
	public function mark_closure_complete( int $closure_log_id, int $completed ): void {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->update(
			$this->wpdb->prefix . 'uap_closure_log',
			array( 'completed' => $completed ),
			array( 'ID' => $closure_log_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	// ── Trigger log lookups ──

	/**
	 * Get all trigger log IDs for a recipe log.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int[]
	 */
	public function get_trigger_log_ids_by_recipe_log( int $recipe_log_id ): array {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT ID FROM {$this->wpdb->prefix}uap_trigger_log WHERE automator_recipe_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Correct run_number on trigger log meta rows.
	 *
	 * Replaces stale values written during Stage 1 with the authoritative
	 * value from the recipe log row.
	 *
	 * @param int[] $trigger_log_ids    Trigger log IDs to update.
	 * @param int   $stale_run_number   The run number to replace.
	 * @param int   $correct_run_number The authoritative run number.
	 *
	 * @return void
	 */
	public function sync_trigger_meta_run_numbers( array $trigger_log_ids, int $stale_run_number, int $correct_run_number ): void {

		foreach ( $trigger_log_ids as $trigger_log_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->update(
				$this->wpdb->prefix . 'uap_trigger_log_meta',
				array( 'run_number' => $correct_run_number ),
				array(
					'automator_trigger_log_id' => $trigger_log_id,
					'run_number'               => $stale_run_number,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}
	}

	// ── Recipe count tracking ──

	/**
	 * Insert a recipe count row (atomic, idempotent via INSERT IGNORE).
	 *
	 * Seeds the initial count from completed recipe log entries.
	 * UNIQUE KEY on recipe_id (added in 7.3.0) prevents duplicates.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return void
	 */
	public function insert_recipe_count( int $recipe_id ): void {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT IGNORE INTO {$this->wpdb->prefix}uap_recipe_count (recipe_id, runs)
				SELECT %d, COUNT(*)
				FROM {$this->wpdb->prefix}uap_recipe_log
				WHERE automator_recipe_id = %d
				  AND completed != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id,
				$recipe_id,
				Automator_Status::NOT_COMPLETED
			)
		);
	}

	// ── Recipe log lookups ──

	/**
	 * Find a pending recipe log — any status NOT in the terminal set.
	 *
	 * @param int   $recipe_id        The recipe ID.
	 * @param int   $user_id          The user ID.
	 * @param int[] $terminal_statuses Statuses that count as terminal.
	 *
	 * @return int|null The log ID if found.
	 */
	public function find_pending_recipe_log( int $recipe_id, int $user_id, array $terminal_statuses ): ?int {

		$placeholders = implode( ', ', array_fill( 0, count( $terminal_statuses ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT ID FROM {$this->wpdb->prefix}uap_recipe_log WHERE completed NOT IN ({$placeholders}) AND automator_recipe_id = %d AND user_id = %d",
				...array_merge( $terminal_statuses, array( $recipe_id, $user_id ) )
			)
		);

		return null !== $log_id ? (int) $log_id : null;
	}

	/**
	 * Count completed recipe runs for a user+recipe pair.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return int
	 */
	public function get_user_completed_recipe_count( int $recipe_id, int $user_id ): int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(completed) FROM {$this->wpdb->prefix}uap_recipe_log WHERE completed = 1 AND user_id = %d AND automator_recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$recipe_id
			)
		);
	}

	/**
	 * Get the highest log_number for a recipe (for sequential numbering under lock).
	 *
	 * Uses MAX(log_number) vs COUNT(*) to handle gaps from deleted rows.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int
	 */
	public function get_max_recipe_log_number( int $recipe_id ): int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT MAX(log_number) as max_log_number, COUNT(*) as total_count FROM {$this->wpdb->prefix}uap_recipe_log WHERE automator_recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id
			)
		);

		if ( null === $result ) {
			return 0;
		}

		$max_log_number = null !== $result->max_log_number ? absint( $result->max_log_number ) : 0;

		return max( $max_log_number, absint( $result->total_count ) );
	}

	/**
	 * Insert a recipe log row.
	 *
	 * Low-level insert — callers are responsible for locking and run_number.
	 *
	 * @param int      $recipe_id  The recipe ID.
	 * @param int      $user_id    The user ID.
	 * @param int      $run_number The run number.
	 * @param int|null $log_number The sequential log number (null if lock failed).
	 *
	 * @return int The inserted ID.
	 */
	public function insert_recipe_log_row( int $recipe_id, int $user_id, int $run_number, ?int $log_number ): int {

		$insert = array(
			'date_time'           => '0000-00-00 00:00:00',
			'user_id'             => $user_id,
			'automator_recipe_id' => $recipe_id,
			'completed'           => -1,
			'run_number'          => $run_number,
		);

		$format = array( '%s', '%d', '%d', '%d', '%d' );

		if ( null !== $log_number ) {
			$insert['log_number'] = $log_number;
			$format[]             = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->insert( $this->wpdb->prefix . 'uap_recipe_log', $insert, $format );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Acquire a MySQL advisory lock.
	 *
	 * @param string $lock_name The lock name.
	 * @param int    $timeout   Lock timeout in seconds.
	 *
	 * @return bool Whether the lock was acquired.
	 */
	public function acquire_lock( string $lock_name, int $timeout = 10 ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return '1' === $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, $timeout ) );
	}

	/**
	 * Release a MySQL advisory lock.
	 *
	 * @param string $lock_name The lock name.
	 *
	 * @return void
	 */
	public function release_lock( string $lock_name ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( $this->wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}

	/**
	 * Get the next AUTO_INCREMENT value without inserting a row.
	 *
	 * Handles MySQL 8's cached INFORMATION_SCHEMA stats.
	 *
	 * @param string $table_name The unprefixed table name (e.g. 'uap_recipe_log').
	 *
	 * @return int|null
	 */
	public function get_next_auto_increment( string $table_name ): ?int {

		// MySQL 8+ caches INFORMATION_SCHEMA stats. Try to disable for fresh read.
		// Suppressed — restricted hosting may lack SET SESSION privilege.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$has_expiry = $this->wpdb->get_results( "SHOW VARIABLES LIKE 'information_schema_stats_expiry'" );

		if ( ! empty( $has_expiry ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->wpdb->suppress_errors( true );
			$this->wpdb->query( 'SET information_schema_stats_expiry = 0;' );
			$this->wpdb->suppress_errors( false );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$next_id = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$this->wpdb->prefix . $table_name
			)
		);

		return null !== $next_id ? (int) $next_id : null;
	}

	/**
	 * Update user_id across trigger_log, recipe_log, and trigger_log_meta tables.
	 *
	 * Called after user resolution in Everyone recipes.
	 *
	 * @param int $user_id        The resolved user ID.
	 * @param int $recipe_log_id  The recipe log ID.
	 * @param int $trigger_log_id The trigger log ID.
	 *
	 * @return void
	 */
	public function update_logs_user_id( int $user_id, int $recipe_log_id, int $trigger_log_id ): void {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->update(
			$this->wpdb->prefix . 'uap_trigger_log',
			array( 'user_id' => $user_id ),
			array( 'ID' => $trigger_log_id ),
			array( '%d' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->update(
			$this->wpdb->prefix . 'uap_recipe_log',
			array( 'user_id' => $user_id ),
			array( 'ID' => $recipe_log_id ),
			array( '%d' ),
			array( '%d' )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->update(
			$this->wpdb->prefix . 'uap_trigger_log_meta',
			array(
				'user_id'  => $user_id,
				'run_time' => current_time( 'mysql' ),
			),
			array( 'automator_trigger_log_id' => $trigger_log_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get the run_number from trigger_log_meta for a specific trigger log + user.
	 *
	 * @param int $trigger_log_id The trigger log ID.
	 * @param int $user_id        The user ID.
	 *
	 * @return int|null
	 */
	public function get_trigger_meta_run_number( int $trigger_log_id, int $user_id ): ?int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$run_number = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT run_number FROM {$this->wpdb->prefix}uap_trigger_log_meta WHERE automator_trigger_log_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$trigger_log_id,
				$user_id
			)
		);

		return null !== $run_number ? (int) $run_number : null;
	}

	/**
	 * Count globally completed recipe runs.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return int
	 */
	public function get_global_completion_count( int $recipe_id ): int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(completed) FROM {$this->wpdb->prefix}uap_recipe_log WHERE automator_recipe_id = %d AND completed = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id,
				Automator_Status::COMPLETED
			)
		);
	}

	/**
	 * Batch get global completion counts grouped by recipe ID.
	 *
	 * @param int[] $recipe_ids Recipe IDs.
	 *
	 * @return array Array of objects with recipe_id and num_times.
	 */
	public function batch_get_global_completion_counts( array $recipe_ids ): array {

		$placeholders = implode( ', ', array_fill( 0, count( $recipe_ids ), '%d' ) );
		$params       = array_merge( $recipe_ids, array( Automator_Status::COMPLETED ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT automator_recipe_id AS recipe_id, COUNT(completed) AS num_times
				FROM {$this->wpdb->prefix}uap_recipe_log
				WHERE automator_recipe_id IN ({$placeholders})
					AND completed = %d
				GROUP BY automator_recipe_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			),
			OBJECT
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	/**
	 * Batch get per-user completion counts grouped by recipe ID.
	 *
	 * @param int   $user_id    The user ID.
	 * @param int[] $recipe_ids Recipe IDs.
	 *
	 * @return array Array of objects with recipe_id and num_times.
	 */
	public function batch_get_user_completion_counts( int $user_id, array $recipe_ids ): array {

		$placeholders = implode( ', ', array_fill( 0, count( $recipe_ids ), '%d' ) );
		$params       = array_merge( array( $user_id ), $recipe_ids, array( Automator_Status::COMPLETED ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT automator_recipe_id AS recipe_id, COUNT(completed) AS num_times
				FROM {$this->wpdb->prefix}uap_recipe_log
				WHERE user_id = %d
					AND automator_recipe_id IN ({$placeholders})
					AND completed = %d
				GROUP BY automator_recipe_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			),
			OBJECT
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	// ── Run number lookups ──

	/**
	 * Get the max run_number for a recipe+user, excluding certain statuses.
	 *
	 * @param int   $recipe_id         The recipe ID.
	 * @param int   $user_id           The user ID.
	 * @param int[] $excluded_statuses Statuses to exclude.
	 *
	 * @return int|null Null when no matching rows.
	 */
	public function get_max_run_number( int $recipe_id, int $user_id, array $excluded_statuses ): ?int {

		$placeholders = implode( ', ', array_fill( 0, count( $excluded_statuses ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$run_number = $this->wpdb->get_var(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT MAX(run_number)
				FROM {$this->wpdb->prefix}uap_recipe_log
				WHERE completed NOT IN ({$placeholders})
					AND automator_recipe_id = %d
					AND user_id = %d",
				...array_merge( $excluded_statuses, array( $recipe_id, $user_id ) )
			)
		);

		return is_numeric( $run_number ) ? (int) $run_number : null;
	}

	// ── Recipe log meta ──

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
	public function add_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key, string $meta_value ): void {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->insert(
			$this->wpdb->prefix . 'uap_recipe_log_meta',
			array(
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $recipe_log_id,
				'meta_key'      => $meta_key,
				'meta_value'    => $meta_value,
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}

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
	public function copy_recipe_log_meta( int $source_log_id, int $target_log_id, int $recipe_id, array $keys ): void {

		foreach ( $keys as $meta_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$value = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT meta_value FROM {$this->wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$source_log_id,
					$meta_key
				)
			);

			if ( null !== $value ) {
				$this->add_recipe_log_meta( $recipe_id, $target_log_id, $meta_key, $value );
			}
		}
	}

	// ── Private helpers ──

	/**
	 * Check if a trigger sentence meta already exists (dedup for sentence_human_readable).
	 *
	 * @param int    $user_id        The user ID.
	 * @param int    $trigger_log_id The trigger log ID.
	 * @param int    $run_number     The run number.
	 * @param string $meta_key       The meta key.
	 *
	 * @return string|null
	 */
	private function get_trigger_sentence_meta( int $user_id, int $trigger_log_id, int $run_number, string $meta_key ): ?string {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT meta_value FROM {$this->wpdb->prefix}uap_trigger_log_meta WHERE user_id = %d AND automator_trigger_log_id = %d AND run_number = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$trigger_log_id,
				$run_number,
				$meta_key
			)
		);
	}
}
