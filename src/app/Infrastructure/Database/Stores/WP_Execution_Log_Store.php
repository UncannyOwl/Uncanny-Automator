<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Stores\Traits\Error_Message_Like;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Recipe_Builder\Loop\Iterable_Expression\Enums\Iteration_Type;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Unified execution log store — owns all execution log CRUD for the recipe runner.
 *
 * Tables: uap_trigger_log, uap_trigger_log_meta, uap_action_log, uap_action_log_meta,
 * uap_recipe_log, uap_recipe_count.
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
 * @since   8.0.0
 */
final class WP_Execution_Log_Store implements Execution_Log_Store {

	use Error_Message_Like;

	/**
	 * A method that runs a prepared query reads this into a local `$wpdb` first:
	 * the PreparedSQL sniff recognises `$wpdb->prepare()` only under that name.
	 *
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

		$wpdb = $this->wpdb;

		if ( $process_recipe ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT completed FROM `{$wpdb->prefix}uap_trigger_log` WHERE user_id = %d AND automator_trigger_id = %d AND automator_recipe_id = %d AND automator_recipe_log_id = %d",
					$user_id,
					$trigger_id,
					$recipe_id,
					$recipe_log_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT t.completed FROM `{$wpdb->prefix}uap_trigger_log` t LEFT JOIN `{$wpdb->prefix}uap_recipe_log` r ON t.automator_recipe_log_id = r.ID LEFT JOIN `{$wpdb->prefix}uap_action_log` a ON t.automator_recipe_log_id = a.automator_recipe_log_id WHERE t.user_id = %d AND t.automator_trigger_id = %d AND t.automator_recipe_id = %d AND t.automator_recipe_log_id = %d AND r.completed = 1 AND a.completed = 1",
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

		$wpdb = $this->wpdb;

		$base_query = "SELECT t.ID as trigger_log_id, t.automator_trigger_id FROM `{$wpdb->prefix}uap_trigger_log` t JOIN `{$wpdb->prefix}uap_recipe_log` r ON r.ID = t.automator_recipe_log_id AND t.automator_recipe_log_id = %d WHERE t.automator_recipe_id = %d AND r.user_id = %d AND t.completed = %d";

		$params = array( $recipe_log_id, $recipe_id, $user_id, 1 );

		if ( null !== $run_number ) {
			$base_query .= ' AND r.run_number = %d';
			$params[]    = $run_number;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; live state read, must not be cached. $base_query is built from literal fragments; every value is in $params.
		$result = $wpdb->get_results(
			$wpdb->prepare( $base_query, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $base_query holds the table names and %d placeholders only; every value is in $params.
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
					'a'  => array(
						'href'   => array(),
						'title'  => array(),
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
	 * @param int    $action_log_id The action log ID.
	 * @param string $meta_key      The meta key.
	 *
	 * @return string|null Most recent matching row's meta_value, or null when absent.
	 */
	public function get_action_meta_value( int $action_log_id, string $meta_key ): ?string {

		$wpdb = $this->wpdb;

		if ( 0 === $action_log_id || '' === $meta_key ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}uap_action_log_meta WHERE automator_action_log_id = %d AND meta_key = %s ORDER BY ID DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_log_id,
				$meta_key
			)
		);

		return null === $value ? null : (string) $value;
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

		$wpdb = $this->wpdb;

		// Read the prior status before the update so the hook payload
		// carries a real old_status (see mark_action_complete_by_id for
		// the rationale). Returns the most-recent row when multiple rows
		// share (action_id, recipe_log_id) — same scoping the UPDATE
		// below uses, so the read matches what we're about to mutate.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_status = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT completed FROM {$wpdb->prefix}uap_action_log WHERE automator_action_id = %d AND automator_recipe_log_id = %d ORDER BY ID DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_id,
				$recipe_log_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$updated = $wpdb->update(
			$wpdb->prefix . 'uap_action_log',
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

			Dispatcher::action( 'automator_action_completion_status_changed', $action_id, $recipe_log_id, $old_status, $completed, $error_message );

			$status_name = Automator_Status::get_class_name( $completed );

			if ( '' !== $status_name ) {
				$status_name = str_replace( '-', '_', $status_name );
				Dispatcher::action( "automator_action_marked_{$status_name}", $action_id, $recipe_log_id, $old_status, $completed, $error_message );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function mark_action_scheduled( int $action_id, int $recipe_log_id, ?string $date_time = null ): void {

		$wpdb = $this->wpdb;

		// Record the supplied scheduled/delayed run time so the log reflects when
		// the action is due rather than the trigger time; fall back to the current
		// time for deferred dispatches with no schedule (e.g. background processing).
		$date_time = ! empty( $date_time ) ? $date_time : current_time( 'mysql' );

		// Genuine transition NOT_COMPLETED → IN_PROGRESS (also stamps the scheduled
		// date). Guarded so a row the worker already finished (terminal status) is
		// never downgraded — see the interface docblock for the dispatch-vs-worker
		// race. Only this real status change dispatches the status events.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$promoted = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_action_log
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_action_log
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

		$wpdb = $this->wpdb;

		// Read the row BEFORE the update so the status-changed hook can
		// fire with the actual prior state. Without this the hook
		// receives `null` as old_status — debug logs show "0→N" with no
		// way to distinguish a fresh dispatch (NOT_COMPLETED → IN_PROGRESS)
		// from a DAG-blocked node finally clearing (BLOCKED_ON_DEPENDENCY
		// → IN_PROGRESS) or a webhook resume (COMPLETED_AWAITING →
		// COMPLETED). The cost is one extra SELECT per status flip; in
		// exchange every transition is traceable end-to-end.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT automator_action_id, automator_recipe_log_id, completed FROM {$wpdb->prefix}uap_action_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_log_id
			)
		);

		if ( null === $row ) {
			return;
		}

		$old_status = (int) $row->completed;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$updated = $wpdb->update(
			$wpdb->prefix . 'uap_action_log',
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

		Dispatcher::action( 'automator_action_completion_status_changed', $action_id, $recipe_log_id, $old_status, $completed, $error_message );

		$status_name = Automator_Status::get_class_name( $completed );
		if ( '' !== $status_name ) {
			$status_name = str_replace( '-', '_', $status_name );
			Dispatcher::action( "automator_action_marked_{$status_name}", $action_id, $recipe_log_id, $old_status, $completed, $error_message );
		}
	}

	/**
	 * Mark a single uap_action_log row complete only while it is still IN_PROGRESS.
	 *
	 * For a caller deciding from a row it read earlier: a worker that finished since keeps its result. Only a real
	 * change dispatches the status events.
	 *
	 * @param int $action_log_id The uap_action_log primary key.
	 * @param int $completed     Completion status constant.
	 *
	 * @return bool True when this call changed the row.
	 */
	public function mark_action_complete_if_in_progress( int $action_log_id, int $completed ): bool {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT automator_action_id, automator_recipe_log_id FROM {$wpdb->prefix}uap_action_log WHERE ID = %d",
				$action_log_id
			)
		);

		if ( null === $row ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_action_log
				SET completed = %d, date_time = %s
				WHERE ID = %d
				AND completed = %d",
				$completed,
				current_time( 'mysql' ),
				$action_log_id,
				Automator_Status::IN_PROGRESS
			)
		);

		if ( ! $updated ) {
			return false;
		}

		$action_id     = (int) $row->automator_action_id;
		$recipe_log_id = (int) $row->automator_recipe_log_id;

		Dispatcher::action( 'automator_action_completion_status_changed', $action_id, $recipe_log_id, Automator_Status::IN_PROGRESS, $completed, '' );

		$status_name = Automator_Status::get_class_name( $completed );
		if ( '' !== $status_name ) {
			$status_name = str_replace( '-', '_', $status_name );
			Dispatcher::action( "automator_action_marked_{$status_name}", $action_id, $recipe_log_id, Automator_Status::IN_PROGRESS, $completed, '' );
		}

		return true;
	}

	/**
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return array
	 */
	public function get_action_error_messages( int $recipe_log_id ): array {

		$wpdb = $this->wpdb;

		// Read from uap_error_log (source of truth) joined with uap_action_log for the completed status.
		// Falls back to uap_action_log.error_message for pre-migration rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COALESCE(e.error_message, a.error_message) as error_message, a.completed
				FROM {$wpdb->prefix}uap_action_log a
				LEFT JOIN {$wpdb->prefix}uap_error_log e ON e.action_log_id = a.ID
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT completed FROM {$wpdb->prefix}uap_action_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_action_log WHERE automator_action_id = %d AND automator_recipe_log_id = %d ORDER BY ID DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_id,
				$recipe_log_id
			)
		);

		return null !== $id ? (int) $id : 0;
	}

	/**
	 * Fetch the raw uap_action_log row for a given primary key.
	 *
	 * Returns the row's `user_id`, `automator_action_id`, `automator_recipe_id`,
	 * and `automator_recipe_log_id` — enough for callers that need to rebuild
	 * an action_data shape (resume / replay / dispatch paths).
	 *
	 * @param int $action_log_id The uap_action_log primary key.
	 *
	 * @return object|null wpdb row object, or null when no row matches.
	 */
	public function get_action_log_row( int $action_log_id ): ?object {

		$wpdb = $this->wpdb;

		if ( $action_log_id <= 0 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, automator_action_id, automator_recipe_id, automator_recipe_log_id FROM {$wpdb->prefix}uap_action_log WHERE ID = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_log_id
			)
		);

		return $row instanceof \stdClass ? $row : null;
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

		$wpdb = $this->wpdb;

		if ( 0 === $trigger_log_id ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE automator_trigger_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT completed FROM {$wpdb->prefix}uap_action_log WHERE automator_recipe_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$run_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT run_number FROM {$wpdb->prefix}uap_recipe_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
				'ID'                  => $recipe_log_id,
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}uap_action_log WHERE completed = %d AND automator_recipe_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_recipe_log WHERE completed = %d AND automator_recipe_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
				'ID'                  => $recipe_log_id,
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_recipe_count SET runs = runs + 1 WHERE recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT automator_recipe_id, user_id FROM {$wpdb->prefix}uap_recipe_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT completed FROM {$wpdb->prefix}uap_recipe_log WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return null !== $status ? (int) $status : null;
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_trigger_log WHERE automator_recipe_log_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}uap_recipe_count (recipe_id, runs)
				SELECT %d, COUNT(*)
				FROM {$wpdb->prefix}uap_recipe_log
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

		$wpdb = $this->wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $terminal_statuses ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$log_id = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is one %d per terminal status; the spread supplies one value per status plus the recipe and user ids.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT ID FROM {$wpdb->prefix}uap_recipe_log WHERE completed NOT IN ({$placeholders}) AND automator_recipe_id = %d AND user_id = %d",
				...array_merge( $terminal_statuses, array( $recipe_id, $user_id ) )
			)
		);

		return null !== $log_id ? (int) $log_id : null;
	}

	/**
	 * Runs whose actions stopped progressing: candidates for stuck recovery.
	 *
	 * Two conditions define "stuck", and both are about actions:
	 *
	 * 1. The run reached Stage 3: it has at least one action log row. A run
	 *    with no action rows has not started executing; it is a multi-trigger
	 *    recipe still collecting triggers, which legitimately stays open for
	 *    days or weeks, and finalizing it destroys the run. The INNER JOIN
	 *    excludes it.
	 * 2. No action row has been touched for $threshold_seconds. Age is measured
	 *    from MAX( al.date_time ), not rl.date_time: uap_recipe_log.date_time is
	 *    a completion timestamp, seeded '0000-00-00 00:00:00' by
	 *    insert_recipe_log_row() and only written by mark_recipe_complete(), so
	 *    an in-flight row always compares as older than any threshold.
	 *
	 * The run is NOT_COMPLETED (the walker never marked it) or IN_PROGRESS
	 * (Action_Run_Stage marks the run before the walk; a run that died anywhere
	 * after that mark, including after its last action completed and before
	 * Stage 4 closed it, sits there for good). A run holding an in-flight action
	 * row (IN_PROGRESS, IN_PROGRESS_WITH_ERROR, COMPLETED_AWAITING, QUEUED,
	 * BLOCKED_ON_DEPENDENCY) is excluded regardless of age, mirroring the has_scheduled set in
	 * Recipe_Status_Resolver::resolve(). A run holding a uap_loop_entries row
	 * is excluded while Pro's table is present: a loop's pending work lives in
	 * uap_loop_entries_items and owns no action row, so nothing above can tell
	 * it from a run that died mid-walk. Any entry excludes, not only an
	 * unfinished one: over-excluding leaves a run stuck exactly as it is today,
	 * under-excluding finalizes a live loop.
	 *
	 * Invariant this query bets on: every wait state owns a non-terminal action
	 * row or a loop entry. A wait state that owns neither is claimed here once
	 * idle for $threshold_seconds and finalized. Add a new wait state on the
	 * right side of that line.
	 *
	 * A background dispatch owns such a row with nothing behind it that is sure
	 * to finish it, so this query alone would leave its run open for good.
	 * find_lost_background_dispatches() finds those rows for recovery to fail.
	 *
	 * Trade-off: a run that died before writing its first action row is not
	 * recovered. From uap_recipe_log alone it is indistinguishable from a
	 * recipe awaiting triggers, and leaving it open is far cheaper than
	 * destroying valid long-running runs.
	 *
	 * @param int $threshold_seconds Idle time, measured from the run's newest action row.
	 * @param int $batch_size        Maximum rows.
	 *
	 * @return object[] Rows with ID, automator_recipe_id, user_id, date_time, last_action_activity.
	 */
	public function find_stuck_recipe_logs( int $threshold_seconds, int $batch_size ): array {

		$wpdb = $this->wpdb;

		$loop_exclusion = '';
		if ( $this->table_exists( $wpdb->prefix . 'uap_loop_entries' ) ) {
			$loop_exclusion = "AND NOT EXISTS (
				      SELECT 1
				      FROM {$wpdb->prefix}uap_loop_entries le
				      WHERE le.recipe_log_id = rl.ID
				  )";
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $loop_exclusion is a literal fragment built above from the table prefix; every value is bound by prepare().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; live state read, must not be cached. $loop_exclusion is a literal fragment built above from the table prefix; every value is bound by prepare().
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rl.ID, rl.automator_recipe_id, rl.user_id, rl.date_time,
				        MAX( al.date_time ) AS last_action_activity
				FROM {$wpdb->prefix}uap_recipe_log rl
				INNER JOIN {$wpdb->prefix}uap_action_log al
				        ON al.automator_recipe_log_id = rl.ID
				WHERE rl.completed IN ( %d, %d )
				  AND rl.ID NOT IN (
				      SELECT DISTINCT al2.automator_recipe_log_id
				      FROM {$wpdb->prefix}uap_action_log al2
				      WHERE al2.completed IN ( %d, %d, %d, %d, %d )
				  )
				  {$loop_exclusion}
				GROUP BY rl.ID, rl.automator_recipe_id, rl.user_id, rl.date_time
				HAVING MAX( al.date_time ) < DATE_SUB( NOW(), INTERVAL %d SECOND )
				ORDER BY last_action_activity ASC
				LIMIT %d",
				Automator_Status::NOT_COMPLETED,
				Automator_Status::IN_PROGRESS,
				Automator_Status::IN_PROGRESS,
				Automator_Status::IN_PROGRESS_WITH_ERROR,
				Automator_Status::COMPLETED_AWAITING,
				Automator_Status::QUEUED,
				Automator_Status::BLOCKED_ON_DEPENDENCY,
				$threshold_seconds,
				$batch_size
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Action rows still In progress on a background dispatch past the threshold.
	 *
	 * The worker is a request nothing tracks: it can be refused, expire in a queue or die, and none of those
	 * writes anything. Only a row marked at dispatch qualifies. A row with a job behind it finishes through the
	 * job, and an In progress row with neither mark could be anything, so it is left alone.
	 *
	 * @param int $threshold_seconds Time since the dispatch.
	 * @param int $batch_size        Maximum rows.
	 *
	 * @return object[] Rows with ID, automator_recipe_log_id, automator_recipe_id, user_id, date_time.
	 */
	public function find_lost_background_dispatches( int $threshold_seconds, int $batch_size ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT al.ID, al.automator_recipe_log_id, rl.automator_recipe_id, rl.user_id, al.date_time
				FROM {$wpdb->prefix}uap_action_log al
				INNER JOIN {$wpdb->prefix}uap_recipe_log rl
				        ON rl.ID = al.automator_recipe_log_id
				WHERE al.completed = %d
				  AND al.date_time < %s
				  AND EXISTS (
				      SELECT 1
				      FROM {$wpdb->prefix}uap_action_log_meta m
				      WHERE m.automator_action_log_id = al.ID
				        AND m.meta_key = %s
				  )
				ORDER BY al.date_time ASC
				LIMIT %d",
				Automator_Status::IN_PROGRESS,
				// Row times are site time, which can be hours from MySQL's NOW().
				wp_date( 'Y-m-d H:i:s', time() - $threshold_seconds ),
				self::ACTION_META_BACKGROUND_DISPATCH,
				$batch_size
			)
		);

		return is_array( $results ) ? $results : array();
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(completed) FROM {$wpdb->prefix}uap_recipe_log WHERE completed = 1 AND user_id = %d AND automator_recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT MAX(log_number) as max_log_number, COUNT(*) as total_count FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * The runs are the Recipes list's Completed runs number: the stored count,
	 * or every log row of the recipe while that count is missing or zero. The
	 * fallback subquery runs only for those recipes, on the recipe ID index.
	 *
	 * @param int $limit The most recipes to return.
	 *
	 * @return array<int,array{id:int,title:string,runs:int}>
	 *
	 * @throws Store_Exception When the query fails.
	 */
	public function get_most_active_recipes( int $limit ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS title,
					COALESCE(
						NULLIF( rc.runs, 0 ),
						( SELECT COUNT(*) FROM {$wpdb->prefix}uap_recipe_log l WHERE l.automator_recipe_id = p.ID )
					) AS runs
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}uap_recipe_count rc ON rc.recipe_id = p.ID
				WHERE p.post_type = %s
				  AND p.post_status IN ( 'publish', 'draft' )
				HAVING runs > 0
				ORDER BY runs DESC, p.post_date DESC, p.ID DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				AUTOMATOR_POST_TYPE_RECIPE,
				$limit
			),
			ARRAY_A
		);

		if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
			throw new Store_Exception( 'The most active recipes could not be read.' );
		}

		return array_map(
			static function ( array $row ): array {
				return array(
					'id'    => (int) $row['id'],
					'title' => (string) $row['title'],
					'runs'  => (int) $row['runs'],
				);
			},
			$rows
		);
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return '1' === $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, $timeout ) );
	}

	/**
	 * Release a MySQL advisory lock.
	 *
	 * @param string $lock_name The lock name.
	 *
	 * @return void
	 */
	public function release_lock( string $lock_name ): void {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
	}

	/**
	 * Get the next AUTO_INCREMENT value without inserting a row.
	 *
	 * Handles MySQL 8's cached INFORMATION_SCHEMA stats: the session's information_schema_stats_expiry is set to 0
	 * for the read and put back to its own value after it. The caller's wpdb error suppression is left as it was.
	 *
	 * @param string $table_name The unprefixed table name (e.g. 'uap_recipe_log').
	 *
	 * @return int|null
	 */
	public function get_next_auto_increment( string $table_name ): ?int {

		$wpdb = $this->wpdb;

		// MySQL 8+ caches INFORMATION_SCHEMA stats. Try to disable for fresh read.
		$previous_expiry = $this->session_stats_expiry();
		$expiry_changed  = null !== $previous_expiry && 0 !== $previous_expiry && $this->set_session_stats_expiry( 0 );

		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$next_id = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
					$wpdb->prefix . $table_name
				)
			);
		} finally {
			if ( $expiry_changed ) {
				$this->set_session_stats_expiry( $previous_expiry );
			}
		}

		return null !== $next_id ? (int) $next_id : null;
	}

	/**
	 * The session's information_schema_stats_expiry, in seconds.
	 *
	 * @since 8.0.1
	 *
	 * @return int|null Null when the server has no such variable (before MySQL 8.0, MariaDB).
	 */
	private function session_stats_expiry(): ?int {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Session setting, not a table read; nothing to cache.
		$row = $wpdb->get_row( "SHOW SESSION VARIABLES LIKE 'information_schema_stats_expiry'", ARRAY_A );

		return is_array( $row ) && isset( $row['Value'] ) && is_numeric( $row['Value'] ) ? (int) $row['Value'] : null;
	}

	/**
	 * Set the session's information_schema_stats_expiry.
	 *
	 * The SET runs with wpdb errors suppressed, since restricted hosting may lack the SET SESSION privilege, and the
	 * caller's suppression setting is put back after it.
	 *
	 * @since 8.0.1
	 *
	 * @param int $seconds The expiry.
	 *
	 * @return bool Whether the server took the value.
	 */
	private function set_session_stats_expiry( int $seconds ): bool {

		$wpdb     = $this->wpdb;
		$suppress = $wpdb->suppress_errors( true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Session setting, not a table read; nothing to cache.
		$result = $wpdb->query( $wpdb->prepare( 'SET SESSION information_schema_stats_expiry = %d', $seconds ) );

		$wpdb->suppress_errors( $suppress );

		return false !== $result;
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$run_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT run_number FROM {$wpdb->prefix}uap_trigger_log_meta WHERE automator_trigger_log_id = %d AND user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(completed) FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id = %d AND completed = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $recipe_ids ), '%d' ) );
		$params       = array_merge( $recipe_ids, array( Automator_Status::COMPLETED ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is one %d per id; the values are bound through $params.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT automator_recipe_id AS recipe_id, COUNT(completed) AS num_times
				FROM {$wpdb->prefix}uap_recipe_log
				WHERE automator_recipe_id IN ({$placeholders})
					AND completed = %d
				GROUP BY automator_recipe_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			),
			OBJECT
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $recipe_ids ), '%d' ) );
		$params       = array_merge( array( $user_id ), $recipe_ids, array( Automator_Status::COMPLETED ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is one %d per id; the values are bound through $params.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT automator_recipe_id AS recipe_id, COUNT(completed) AS num_times
				FROM {$wpdb->prefix}uap_recipe_log
				WHERE user_id = %d
					AND automator_recipe_id IN ({$placeholders})
					AND completed = %d
				GROUP BY automator_recipe_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			),
			OBJECT
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $excluded_statuses ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is one %d per status; the values are bound through the spread arguments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$run_number = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is one %d per excluded status; the spread supplies one value per status plus the recipe and user ids.
			$wpdb->prepare(
				"SELECT MAX(run_number)
				FROM {$wpdb->prefix}uap_recipe_log
				WHERE completed NOT IN ({$placeholders})
					AND automator_recipe_id = %d
					AND user_id = %d",
				...array_merge( $excluded_statuses, array( $recipe_id, $user_id ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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

		$wpdb = $this->wpdb;

		foreach ( $keys as $meta_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE user_id = %d AND automator_trigger_log_id = %d AND run_number = %d AND meta_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$trigger_log_id,
				$run_number,
				$meta_key
			)
		);
	}

	/**
	 * The newest recipe-log row for a recipe, or null when it has never run.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return object|null Row with { ID, run_number, completed }, or null when none.
	 */
	public function get_latest_recipe_log( int $recipe_id ): ?object {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, run_number, completed FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id = %d ORDER BY ID DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id
			)
		);

		return is_object( $row ) ? $row : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipe_log_row_by_ids( int $recipe_id, int $run_number, int $log_id ): ?array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID, date_time, user_id, automator_recipe_id, completed, run_number FROM {$wpdb->prefix}uap_recipe_log WHERE automator_recipe_id = %d AND run_number = %d AND ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id,
				$run_number,
				$log_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get_trigger_log_rows( int $recipe_log_id ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_trigger_log WHERE automator_recipe_log_id = %d ORDER BY date_time ASC, ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_action_log_rows( int $recipe_log_id ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_action_log WHERE automator_recipe_log_id = %d ORDER BY date_time ASC, ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_tokens_record_map( int $recipe_id, int $recipe_log_id, int $run_number ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$records = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tokens_record FROM {$wpdb->prefix}uap_tokens_log WHERE recipe_id = %d AND recipe_log_id = %d AND run_number = %d ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id,
				$recipe_log_id,
				$run_number
			)
		);
		$map     = array();
		foreach ( (array) $records as $json ) {
			$decoded = json_decode( (string) $json, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $code => $value ) {
					if ( is_scalar( $value ) ) {
						$map[ (string) $code ] = (string) $value;
					}
				}
			}
		}
		return $map;
	}

	/**
	 * @inheritDoc
	 */
	public function get_action_log_meta_bag( array $action_log_ids ): array {

		$wpdb = $this->wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $action_log_ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is one %d per id; the spread supplies the ids.
				"SELECT automator_action_log_id, meta_key, meta_value FROM {$wpdb->prefix}uap_action_log_meta WHERE automator_action_log_id IN ({$placeholders})",
				...$ids
			),
			ARRAY_A
		);
		$bag = array();
		foreach ( (array) $rows as $row ) {
			$bag[ (int) $row['automator_action_log_id'] ][ (string) $row['meta_key'] ] = (string) $row['meta_value'];
		}
		return $bag;
	}

	/**
	 * @inheritDoc
	 */
	public function get_trigger_log_meta_values( array $trigger_log_ids, string $meta_key ): array {

		$wpdb = $this->wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $trigger_log_ids ) ) );
		if ( empty( $ids ) || '' === $meta_key ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is one %d per id; the spread supplies the ids after the meta key.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is one %d per id; the spread supplies the ids after the meta key.
				"SELECT automator_trigger_log_id, meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = %s AND automator_trigger_log_id IN ({$placeholders}) ORDER BY ID ASC",
				$meta_key,
				...$ids
			),
			ARRAY_A
		);
		$values = array();
		foreach ( (array) $rows as $row ) {
			$values[ (int) $row['automator_trigger_log_id'] ][] = (string) $row['meta_value'];
		}
		return $values;
	}

	/**
	 * @inheritDoc
	 */
	public function get_trigger_log_meta_bag( array $trigger_log_ids ): array {

		$wpdb = $this->wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $trigger_log_ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is one %d per id; the spread supplies the ids.
				"SELECT automator_trigger_log_id, meta_key, meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE automator_trigger_log_id IN ({$placeholders})",
				...$ids
			),
			ARRAY_A
		);
		$bag = array();
		foreach ( (array) $rows as $row ) {
			$bag[ (int) $row['automator_trigger_log_id'] ][ (string) $row['meta_key'] ] = (string) $row['meta_value'];
		}
		return $bag;
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipe_log_meta_bag( int $recipe_log_id, array $extra_keys = array() ): array {

		$wpdb = $this->wpdb;

		$keys         = array_merge(
			array( 'triggers_logic', self::META_ACTION_DELAYS, self::META_BLOCK_CHOSEN_PATHS, self::META_BLOCK_RUN_CONFIG, self::META_BLOCKED_NODES, self::META_BLOCK_REFUSALS, self::META_LOOP_FILTER_OUTCOMES ),
			array_map( 'strval', $extra_keys )
		);
		$keys         = array_values( array_unique( array_filter( $keys ) ) );
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is one %s per meta key; the spread supplies the keys after the recipe log id.
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d AND meta_key IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is one %s per meta key.
				$recipe_log_id,
				...$keys
			),
			ARRAY_A
		);
		$bag = array();
		foreach ( (array) $rows as $row ) {
			$bag[ (string) $row['meta_key'] ] = (string) $row['meta_value'];
		}
		return $bag;
	}

	/**
	 * @inheritDoc
	 */
	public function get_recipe_log_meta_values( int $recipe_log_id, array $meta_keys ): array {

		$wpdb = $this->wpdb;

		$keys = array_values( array_unique( array_filter( array_map( 'strval', $meta_keys ) ) ) );
		if ( $recipe_log_id <= 0 || empty( $keys ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is one %s per meta key; the spread supplies the keys after the recipe log id.
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d AND meta_key IN ({$placeholders}) ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is one %s per meta key.
				$recipe_log_id,
				...$keys
			),
			ARRAY_A
		);

		$values = array();
		foreach ( (array) $rows as $row ) {
			$values[ (string) $row['meta_key'] ][] = (string) $row['meta_value'];
		}

		return $values;
	}

	/**
	 * @inheritDoc
	 */
	public function get_loop_entries( int $recipe_log_id, int $run_number ): array {

		$wpdb = $this->wpdb;

		$table = $wpdb->prefix . 'uap_loop_entries';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table ) . '` WHERE recipe_log_id = %d AND run_number = %d',
				$recipe_log_id,
				$run_number
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['loop_id'] ] = $row;
		}
		return $out;
	}

	/**
	 * @inheritDoc
	 */
	public function has_failed_loop_entries( int $recipe_log_id, array $except_loop_ids = array() ): bool {

		$wpdb = $this->wpdb;

		$table = $wpdb->prefix . 'uap_loop_entries';
		if ( 0 >= $recipe_log_id || ! $this->table_exists( $table ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pro's own table; live state read, must not be cached.
		$failed = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT loop_id FROM `' . esc_sql( $table ) . '` WHERE recipe_log_id = %d AND status = %s',
				$recipe_log_id,
				Automator_Status::get_class_name( Automator_Status::COMPLETED_WITH_ERRORS )
			)
		);

		return array() !== array_diff( array_map( 'intval', $failed ), array_map( 'intval', $except_loop_ids ) );
	}

	/**
	 * @inheritDoc
	 */
	public function get_loop_item_status_breakdown( int $recipe_log_id, int $run_number ): array {

		$wpdb = $this->wpdb;

		$table = $wpdb->prefix . 'uap_loop_entries_items';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT action_id, status, COUNT(*) AS n FROM `' . esc_sql( $table ) . '` WHERE recipe_log_id = %d AND recipe_run_number = %d GROUP BY action_id, status',
				$recipe_log_id,
				$run_number
			),
			ARRAY_A
		);
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['action_id'] ][ (string) $row['status'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * @inheritDoc
	 */
	public function get_action_iteration_rows( int $recipe_id, int $recipe_log_id, int $run_number, int $action_id, int $per_page, int $offset, string $search, string $loop_type, array $matched_entity_ids = array() ): array {

		$wpdb = $this->wpdb;

		$table = $wpdb->prefix . 'uap_loop_entries_items';
		if ( ! $this->table_exists( $table ) ) {
			return array();
		}
		list( $join_sql, $search_sql, $search_params ) = $this->iteration_search_fragments( $search, $loop_type, $matched_entity_ids );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; live state read, must not be cached. $join_sql and $search_sql are literal fragments from iteration_search_fragments().
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The search placeholders vary with the search term; the merged arguments carry one value per placeholder.
			$wpdb->prepare(
				'SELECT t.* FROM `' . esc_sql( $table ) . "` t{$join_sql} WHERE t.recipe_id = %d AND t.recipe_log_id = %d AND t.recipe_run_number = %d AND t.action_id = %d{$search_sql} ORDER BY t.ID ASC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $join_sql and $search_sql are literal fragments from iteration_search_fragments(); every value is bound through the merged arguments.
				array_merge( array( $recipe_id, $recipe_log_id, $run_number, $action_id ), $search_params, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		return (array) $rows;
	}

	/**
	 * @inheritDoc
	 */
	public function count_action_iteration_rows( int $recipe_id, int $recipe_log_id, int $run_number, int $action_id, string $search, string $loop_type, array $matched_entity_ids = array() ): int {

		$wpdb = $this->wpdb;

		$table = $wpdb->prefix . 'uap_loop_entries_items';
		if ( ! $this->table_exists( $table ) ) {
			return 0;
		}
		list( $join_sql, $search_sql, $search_params ) = $this->iteration_search_fragments( $search, $loop_type, $matched_entity_ids );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; live state read, must not be cached. $join_sql and $search_sql are literal fragments from iteration_search_fragments().
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The search placeholders vary with the search term; the merged arguments carry one value per placeholder.
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . esc_sql( $table ) . "` t{$join_sql} WHERE t.recipe_id = %d AND t.recipe_log_id = %d AND t.recipe_run_number = %d AND t.action_id = %d{$search_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $join_sql and $search_sql are literal fragments from iteration_search_fragments(); every value is bound through the merged arguments.
				array_merge( array( $recipe_id, $recipe_log_id, $run_number, $action_id ), $search_params )
			)
		);
	}

	/**
	 * Kind-aware search JOIN/WHERE fragments for the loop-iteration reads.
	 * Users loops match display_name/user_email; posts loops match
	 * post_title; both also match the entity id. Token loops match the given
	 * entity ids: their labels live in the parent's entity_ids JSON, not in a
	 * joinable table. Every kind matches error_message.
	 *
	 * @param string         $search             Raw search term; '' yields empty fragments.
	 * @param string         $loop_type          Loop kind: users|posts|token|''.
	 * @param array<int,int> $matched_entity_ids Token loops: entity ids whose label contains $search.
	 * @return array{0: string, 1: string, 2: array<int,int|string>} JOIN SQL, WHERE-suffix SQL, query params.
	 */
	protected function iteration_search_fragments( string $search, string $loop_type, array $matched_entity_ids ): array {
		if ( '' === $search ) {
			return array( '', '', array() );
		}
		$like = '%' . $this->wpdb->esc_like( $search ) . '%';
		if ( 'users' === $loop_type ) {
			return array(
				" LEFT JOIN {$this->wpdb->users} u ON u.ID = t.entity_id",
				' AND (u.display_name LIKE %s OR u.user_email LIKE %s OR CAST(t.entity_id AS CHAR) LIKE %s OR t.error_message LIKE %s)',
				array( $like, $like, $like, $like ),
			);
		}
		if ( 'posts' === $loop_type ) {
			return array(
				" LEFT JOIN {$this->wpdb->posts} p ON p.ID = t.entity_id",
				' AND (p.post_title LIKE %s OR CAST(t.entity_id AS CHAR) LIKE %s OR t.error_message LIKE %s)',
				array( $like, $like, $like ),
			);
		}
		if ( Iteration_Type::TOKEN === $loop_type ) {
			$placeholders = implode( ', ', array_fill( 0, count( $matched_entity_ids ), '%d' ) );
			return array(
				'',
				empty( $matched_entity_ids ) ? ' AND t.error_message LIKE %s' : " AND (t.entity_id IN ({$placeholders}) OR t.error_message LIKE %s)",
				array_merge( $matched_entity_ids, array( $like ) ),
			);
		}
		return array(
			'',
			' AND (CAST(t.entity_id AS CHAR) LIKE %s OR t.error_message LIKE %s)',
			array( $like, $like ),
		);
	}

	/**
	 * Whether a table exists (Pro loop tables are absent on Free-only sites).
	 *
	 * @param string $table Fully-prefixed table name.
	 * @return bool
	 */
	protected function table_exists( string $table ): bool {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return (string) $found === $table;
	}

	/**
	 * Read a single recipe-log-meta value.
	 *
	 * Returns null when no row exists for the (recipe_log_id, meta_key) pair.
	 * Empty string is a valid stored value and is preserved as `''`, NOT
	 * collapsed to null — callers can distinguish "missing" from "empty".
	 *
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 *
	 * @return string|null Stored value, or null when no row matches.
	 */
	public function get_recipe_log_meta( int $recipe_log_id, string $meta_key ): ?string {

		$wpdb = $this->wpdb;

		// Use get_row over get_var: get_var coerces empty-string cells to null,
		// which would collapse the "row exists with empty value" state into the
		// "no row" state and break the ?string contract.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d AND meta_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id,
				$meta_key
			)
		);

		return null === $row ? null : (string) $row->meta_value;
	}

	/**
	 * Whether a run has a recipe-log meta row for this key, whoever it was written for.
	 *
	 * The run owns its recipe-log meta: the user_id column is not consulted.
	 * The recipe ID is part of the lookup because the table is indexed on
	 * recipe_id and not on recipe_log_id; without it every call would read
	 * the whole table.
	 *
	 * @since 8.0.1
	 *
	 * @param int    $recipe_id     The recipe ID.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 *
	 * @return bool
	 */
	public function has_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key ): bool {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_id = %d AND recipe_log_id = %d AND meta_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id,
				$recipe_log_id,
				$meta_key
			)
		);

		return null !== $found;
	}

	/**
	 * Upsert: insert if no row, otherwise update meta_value in place.
	 *
	 * Read-then-write — caller must serialize on $recipe_log_id. There is
	 * no UNIQUE index on (recipe_log_id, meta_key) so concurrent writers
	 * can race. Single-writer in halt-and-resume runtime today.
	 *
	 * @param int    $recipe_id     The recipe ID.
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 * @param string $meta_value    The meta value.
	 *
	 * @return void
	 */
	public function set_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key, string $meta_value ): void {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d AND meta_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id,
				$meta_key
			)
		);

		if ( null === $existing ) {
			$this->add_recipe_log_meta( $recipe_id, $recipe_log_id, $meta_key, $meta_value );
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write.
		$wpdb->update(
			$wpdb->prefix . 'uap_recipe_log_meta',
			array( 'meta_value' => $meta_value ),
			array( 'ID' => (int) $existing ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete the row for a (recipe_log_id, meta_key) pair.
	 *
	 * No-op when no matching row exists — wpdb->delete returns 0 affected rows
	 * and the call returns void. Safe to call defensively in cleanup paths.
	 *
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param string $meta_key      The meta key.
	 *
	 * @return void
	 */
	public function delete_recipe_log_meta( int $recipe_log_id, string $meta_key ): void {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->delete(
			$this->wpdb->prefix . 'uap_recipe_log_meta',
			array(
				'recipe_log_id' => $recipe_log_id,
				'meta_key'      => $meta_key,
			),
			array( '%d', '%s' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function merge_recipe_log_meta_entry( int $recipe_id, int $recipe_log_id, string $meta_key, string $entry_key, ?array $entry ): bool {
		return $this->update_recipe_log_meta_record(
			$recipe_id,
			$recipe_log_id,
			$meta_key,
			$entry_key,
			static function ( array $record ) use ( $entry_key, $entry ): ?array {
				if ( null === $entry ) {
					if ( ! isset( $record[ $entry_key ] ) ) {
						return null;
					}
					unset( $record[ $entry_key ] );
					return $record;
				}
				$record[ $entry_key ] = $entry;
				return $record;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function claim_recipe_log_meta_entry( int $recipe_id, int $recipe_log_id, string $meta_key, string $entry_key, string $holder, ?string $previous_holder ): bool {
		return $this->update_recipe_log_meta_record(
			$recipe_id,
			$recipe_log_id,
			$meta_key,
			$entry_key,
			static function ( array $record ) use ( $entry_key, $holder, $previous_holder ): ?array {
				if ( ! isset( $record[ $entry_key ] ) || ! is_array( $record[ $entry_key ] ) ) {
					return null;
				}
				if ( ( $record[ $entry_key ]['claimed_by'] ?? null ) !== $previous_holder ) {
					return null;
				}
				$record[ $entry_key ]['claimed_by'] = $holder;
				return $record;
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 8.0.3
	 */
	public function find_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key ): ?string {

		$wpdb = $this->wpdb;

		// get_row, not get_var: get_var reads an empty value as no row.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_id = %d AND recipe_log_id = %d AND meta_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_id,
				$recipe_log_id,
				$meta_key
			)
		);

		if ( '' !== $wpdb->last_error ) {
			throw new Store_Exception( sprintf( 'Recipe log %d: %s could not be read: %s', absint( $recipe_log_id ), esc_html( $meta_key ), esc_html( $wpdb->last_error ) ) );
		}

		return null === $row ? null : (string) $row->meta_value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 8.0.3
	 */
	public function claim_recipe_log_meta( int $recipe_id, int $recipe_log_id, string $meta_key, string $holder ): string {

		$lock = 'automator_run_meta_' . $recipe_log_id;
		if ( ! $this->acquire_lock( $lock, 5 ) ) {
			throw new Store_Exception( sprintf( 'Recipe log %d: %s was not claimed because the run-meta lock was not acquired.', absint( $recipe_log_id ), esc_html( $meta_key ) ) );
		}

		try {
			$current = $this->find_recipe_log_meta( $recipe_id, $recipe_log_id, $meta_key );
			if ( null !== $current ) {
				return $current;
			}

			$wpdb = $this->wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$written = $wpdb->insert(
				$wpdb->prefix . 'uap_recipe_log_meta',
				array(
					'recipe_id'     => $recipe_id,
					'recipe_log_id' => $recipe_log_id,
					'meta_key'      => $meta_key,
					'meta_value'    => $holder,
				),
				array( '%d', '%d', '%s', '%s' )
			);
			if ( false === $written ) {
				throw new Store_Exception( sprintf( 'Recipe log %d: %s could not be claimed: %s', absint( $recipe_log_id ), esc_html( $meta_key ), esc_html( $wpdb->last_error ) ) );
			}

			return $holder;
		} finally {
			$this->release_lock( $lock );
		}
	}

	/**
	 * @inheritDoc
	 *
	 * @since 8.0.0
	 */
	public function scan_error_messages( int $after_id, int $rows, array $fragments ): array {

		$wpdb = $this->wpdb;
		$like = $this->error_message_like( $rows, $fragments );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A bounded walk over a log table; nothing to cache.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, error_message LIKE %s AS matched FROM {$wpdb->prefix}uap_action_log WHERE ID > %d ORDER BY ID ASC LIMIT %d",
				$like,
				$after_id,
				$rows
			),
			ARRAY_A
		);

		if ( '' !== $wpdb->last_error || ! is_array( $results ) ) {
			throw new Store_Exception( 'The action log could not be scanned: ' . esc_html( $wpdb->last_error ) );
		}

		$scanned = array();

		foreach ( $results as $row ) {
			$scanned[ (int) $row['ID'] ] = '1' === (string) $row['matched'];
		}

		return $scanned;
	}

	/**
	 * @inheritDoc
	 *
	 * @since 8.0.0
	 */
	public function find_error_message( int $id ): ?string {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One row by primary key, read just before it is rewritten.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT error_message FROM {$wpdb->prefix}uap_action_log WHERE ID = %d", $id ),
			ARRAY_A
		);

		if ( '' !== $wpdb->last_error ) {
			throw new Store_Exception( 'The action log row could not be read: ' . esc_html( $wpdb->last_error ) );
		}

		return is_array( $row ) ? (string) $row['error_message'] : null;
	}

	/**
	 * @inheritDoc
	 *
	 * @since 8.0.0
	 */
	public function replace_error_message( int $id, string $expected, string $replacement ): bool {

		$wpdb = $this->wpdb;

		// The row keeps its time: date_time is set to itself, so no server setting can update it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-set on one row of a log table.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_action_log SET error_message = %s, date_time = date_time WHERE ID = %d AND CAST(error_message AS BINARY) = CAST(%s AS BINARY)",
				$replacement,
				$id,
				$expected
			)
		);

		if ( false === $updated ) {
			throw new Store_Exception( 'The action log row could not be rewritten: ' . esc_html( $wpdb->last_error ) );
		}

		return 1 === (int) $updated;
	}

	/**
	 * Read, change and write back a JSON-object recipe-log meta under the run-meta advisory lock.
	 *
	 * @param int      $recipe_id     The recipe.
	 * @param int      $recipe_log_id The run.
	 * @param string   $meta_key      The meta key holding a JSON object.
	 * @param string   $entry_key     The entry the change concerns, for the log line.
	 * @param callable $change        `( array $record ): ?array`; the record to write, or null to write nothing.
	 *
	 * @return bool Whether the record was written.
	 */
	private function update_recipe_log_meta_record( int $recipe_id, int $recipe_log_id, string $meta_key, string $entry_key, callable $change ): bool {

		$lock = 'automator_run_meta_' . $recipe_log_id;
		if ( ! $this->acquire_lock( $lock, 5 ) ) {
			automator_log( sprintf( 'Recipe log %d: run-meta lock not acquired; entry %s of %s written unlocked.', $recipe_log_id, $entry_key, $meta_key ), 'Recipe_Runner' );
		}

		try {
			$existing = json_decode( (string) $this->get_recipe_log_meta( $recipe_log_id, $meta_key ), true );
			$record   = $change( is_array( $existing ) ? $existing : array() );
			if ( null === $record ) {
				return false;
			}

			$this->set_recipe_log_meta( $recipe_id, $recipe_log_id, $meta_key, (string) wp_json_encode( (object) $record ) );

			return true;
		} finally {
			$this->release_lock( $lock );
		}
	}
}
