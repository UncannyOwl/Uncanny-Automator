<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;

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
	 * Insert an error log row.
	 *
	 * @param int          $recipe_log_id The recipe log ID.
	 * @param int          $action_log_id The action log ID (0 for system errors).
	 * @param string       $item_type     The item type ('action' or 'system').
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT 1 FROM {$this->wpdb->prefix}uap_error_log WHERE recipe_log_id = %d AND is_actionable = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return null !== $result;
	}

	/**
	 * @inheritDoc
	 */
	public function get_by_recipe_log( int $recipe_log_id ): array {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}uap_error_log WHERE recipe_log_id = %d ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$recipe_log_id
			)
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function get_by_action_log( int $action_log_id ): array {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}uap_error_log WHERE action_log_id = %d ORDER BY ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$action_log_id
			)
		);

		return is_array( $result ) ? $result : array();
	}

	/**
	 * @inheritDoc
	 */
	public function migrate_legacy_errors( int $batch_size = 500 ): int {

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT a.ID AS action_log_id, a.automator_recipe_log_id, a.error_message
				FROM {$this->wpdb->prefix}uap_action_log a
				LEFT JOIN {$this->wpdb->prefix}uap_error_log e ON e.action_log_id = a.ID
				WHERE a.error_message != '' AND a.error_message IS NOT NULL AND e.ID IS NULL
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$batch_size
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$migrated = 0;

		foreach ( $rows as $row ) {
			$error = Action_Error::from_legacy_message( $row->error_message );
			$this->store( (int) $row->automator_recipe_log_id, (int) $row->action_log_id, $error );
			++$migrated;
		}

		return $migrated;
	}
}
