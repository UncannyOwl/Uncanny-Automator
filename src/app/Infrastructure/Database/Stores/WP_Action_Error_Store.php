<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Recipe_Runner\Services\Gemini_Key_Redactor;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\Traits\Error_Message_Like;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;

/**
 * WordPress implementation of the action error store.
 *
 * Persists structured {@see Action_Error} value objects into the
 * `uap_error_log` table. Phase 5 of the api-layer refactor moved this
 * from `application/recipe_runner/services/Action_Error_Store` to its
 * correct home in `database/stores/` and added constructor `$wpdb`
 * injection.
 *
 * @package Uncanny_Automator\App\Infrastructure\Database\Stores
 * @since   7.4.0
 */
final class WP_Action_Error_Store implements Action_Error_Store {

	use Error_Message_Like;

	/**
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * @param \wpdb $wpdb wpdb instance.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * @inheritDoc
	 */
	public function store( int $recipe_log_id, int $action_log_id, Action_Error $error ): int {
		return $this->insert_error( $recipe_log_id, $action_log_id, 'action', $error );
	}

	/**
	 * Store a system-level error (e.g. stuck recipe recovery).
	 *
	 * Uses `item_type='system'` instead of `'action'` so the error
	 * is attributed to the system, not a specific action.
	 *
	 * @param int          $recipe_log_id The recipe log ID.
	 * @param Action_Error $error         The structured error.
	 *
	 * @return int The inserted error log ID.
	 */
	public function store_system_error( int $recipe_log_id, Action_Error $error ): int {
		return $this->insert_error( $recipe_log_id, 0, 'system', $error );
	}

	/**
	 * @inheritDoc
	 */
	public function store_block_error( int $recipe_log_id, int $block_id, Action_Error $error ): int {

		foreach ( $this->get_block_errors( $recipe_log_id ) as $row ) {
			$context = json_decode( (string) $row->error_context, true );
			if ( $error->get_code() === $row->error_code && (int) ( $context['block_id'] ?? 0 ) === $block_id ) {
				return 0;
			}
		}

		$block_context = array_merge( $error->get_context(), array( 'block_id' => $block_id ) );
		$block_error   = $error->is_actionable()
			? new Action_Error( $error->get_code(), $error->get_message(), $block_context )
			: Action_Error::without_escalation( $error->get_code(), $error->get_message(), $block_context );

		return $this->insert_error( $recipe_log_id, 0, 'block', $block_error );
	}

	/**
	 * @inheritDoc
	 */
	public function get_block_errors( int $recipe_log_id ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_error_log WHERE recipe_log_id = %d AND item_type = 'block' ORDER BY ID ASC",
				$recipe_log_id
			)
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * Insert an error log row.
	 *
	 * @param int          $recipe_log_id The recipe log ID.
	 * @param int          $action_log_id The action log ID (0 for system and block errors).
	 * @param string       $item_type     The item type ('action', 'system' or 'block').
	 * @param Action_Error $error         The structured error.
	 *
	 * @return int The inserted error log ID.
	 */
	private function insert_error( int $recipe_log_id, int $action_log_id, string $item_type, Action_Error $error ): int {

		$context       = $error->get_context();
		$error_context = empty( $context ) ? null : wp_json_encode( $context );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert(
			$this->wpdb->prefix . 'uap_error_log',
			array(
				'recipe_log_id' => $recipe_log_id,
				'action_log_id' => $action_log_id,
				'item_type'     => $item_type,
				'error_code'    => $error->get_code(),
				'error_message' => wp_specialchars_decode( $error->get_message(), ENT_QUOTES ),
				'is_actionable' => $error->is_actionable() ? 1 : 0,
				'error_context' => $error_context,
				'date_time'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			automator_log( 'Failed to insert error log row. Recipe log: ' . $recipe_log_id . ', Action log: ' . $action_log_id, 'WP_Action_Error_Store' );
		}

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * @inheritDoc
	 */
	public function has_actionable_errors( int $recipe_log_id ): bool {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}uap_error_log WHERE recipe_log_id = %d AND is_actionable = 1 LIMIT 1",
				$recipe_log_id
			)
		);

		return null !== $result;
	}

	/**
	 * @inheritDoc
	 */
	public function has_system_errors( int $recipe_log_id ): bool {

		// The webhook router stores its failures against recipe log 0: no run owns them.
		if ( 0 >= $recipe_log_id ) {
			return false;
		}

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Resolve against the authoritative run error rows; a cached result can hide an error recorded after the action rows.
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}uap_error_log WHERE recipe_log_id = %d AND item_type = %s AND is_actionable = 1 LIMIT 1",
				$recipe_log_id,
				'system'
			)
		);

		return null !== $result;
	}

	/**
	 * @inheritDoc
	 */
	public function get_by_recipe_log( int $recipe_log_id ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_error_log WHERE recipe_log_id = %d ORDER BY ID ASC",
				$recipe_log_id
			)
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_by_action_log( int $action_log_id ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_error_log WHERE action_log_id = %d ORDER BY ID ASC",
				$action_log_id
			)
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function migrate_legacy_errors( int $batch_size = 500 ): int {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.ID AS action_log_id, a.automator_recipe_log_id, a.error_message
				FROM {$wpdb->prefix}uap_action_log a
				LEFT JOIN {$wpdb->prefix}uap_error_log e ON e.action_log_id = a.ID
				WHERE a.error_message != '' AND a.error_message IS NOT NULL AND e.ID IS NULL
				LIMIT %d",
				$batch_size
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$migrated = 0;
		$failed   = 0;

		foreach ( $rows as $row ) {
			// The backfill can cache a row before the scrub completes and insert it afterward.
			$error = Action_Error::from_legacy_message( Gemini_Key_Redactor::scrub( (string) $row->error_message ) );

			// store() answers 0 when the database refused the row: it is not migrated, and the next batch selects it again.
			if ( 0 < $this->store( (int) $row->automator_recipe_log_id, (int) $row->action_log_id, $error ) ) {
				++$migrated;
				continue;
			}

			++$failed;
		}

		if ( 0 < $failed ) {
			throw new Store_Exception(
				sprintf(
					/* translators: 1: rows not copied, 2: rows in the batch. */
					esc_html_x( 'Could not copy %1$d of %2$d legacy action errors into the error log.', 'Action error store migration error', 'uncanny-automator' ),
					absint( $failed ),
					absint( count( $rows ) )
				)
			);
		}

		return $migrated;
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
				"SELECT ID, error_message LIKE %s AS matched FROM {$wpdb->prefix}uap_error_log WHERE ID > %d ORDER BY ID ASC LIMIT %d",
				$like,
				$after_id,
				$rows
			),
			ARRAY_A
		);

		if ( '' !== $wpdb->last_error || ! is_array( $results ) ) {
			throw new Store_Exception( 'The error log could not be scanned: ' . esc_html( $wpdb->last_error ) );
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
			$wpdb->prepare( "SELECT error_message FROM {$wpdb->prefix}uap_error_log WHERE ID = %d", $id ),
			ARRAY_A
		);

		if ( '' !== $wpdb->last_error ) {
			throw new Store_Exception( 'The error log row could not be read: ' . esc_html( $wpdb->last_error ) );
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
				"UPDATE {$wpdb->prefix}uap_error_log SET error_message = %s, date_time = date_time WHERE ID = %d AND CAST(error_message AS BINARY) = CAST(%s AS BINARY)",
				$replacement,
				$id,
				$expected
			)
		);

		if ( false === $updated ) {
			throw new Store_Exception( 'The error log row could not be rewritten: ' . esc_html( $wpdb->last_error ) );
		}

		return 1 === (int) $updated;
	}
}
