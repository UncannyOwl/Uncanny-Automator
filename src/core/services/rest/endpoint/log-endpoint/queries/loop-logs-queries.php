<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries;

/**
 * The repository class for logs queries.
 *
 * @since 5.0
 */
class Loop_Logs_Queries {

	/**
	 * The WordPress database object.
	 *
	 * @var \wpdb
	 */
	protected $db = null;

	/**
	 * The format of the query results.
	 *
	 * @var string QUERY_RESULTS_FORMAT
	 */
	const QUERY_RESULTS_FORMAT = ARRAY_A;

	/**
	 * Constructor for the class.
	 *
	 * @param \wpdb $wpdb The WordPress database object.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->db = $wpdb;
	}

	/**
	 * @param $params
	 *
	 * @return array
	 */
	public function get_recipe_loops_logs( $params ) {
		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}uap_loop_entries
					WHERE recipe_id = %d
					AND recipe_log_id = %d
					AND run_number = %d
				",
				$params['recipe_id'],
				$params['recipe_log_id'],
				$params['run_number']
			),
			self::QUERY_RESULTS_FORMAT
		);

		return (array) $results;
	}

	/**
	 * @param $action_id
	 * @param $params
	 *
	 * @return array
	 */
	public function get_distinct_statuses( $action_id, $params ) {

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT DISTINCT(status)
				FROM {$this->db->prefix}uap_loop_entries_items
				WHERE action_id = %d
				AND recipe_id = %d
				AND recipe_log_id = %d
				AND recipe_run_number = %d
				",
				$action_id,
				$params['recipe_id'],
				$params['recipe_log_id'],
				$params['run_number']
			),
			self::QUERY_RESULTS_FORMAT
		);

		return (array) $results;
	}

	/**
	 * @return int
	 */
	public function get_action_status_count( $action_id, $status, $params ) {

		$status_result_count = $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) as `count`
				FROM {$this->db->prefix}uap_loop_entries_items
				WHERE `status` = %s
				AND action_id = %d
				AND recipe_id = %d
				AND recipe_log_id = %d
				AND recipe_run_number = %d",
				$status,
				$action_id,
				$params['recipe_id'],
				$params['recipe_log_id'],
				$params['run_number']
			)
		);

		return absint( $status_result_count );
	}

	/**
	 * @param $action_id
	 * @param $status
	 * @param $params
	 *
	 * @return array
	 */
	public function get_entry_items( $action_id, $status, $params ) {

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT *
				FROM {$this->db->prefix}uap_loop_entries_items
				WHERE `status` = %s
				AND action_id = %d
				AND recipe_id = %d
				AND recipe_log_id = %d
				AND recipe_run_number = %d",
				$status,
				$action_id,
				$params['recipe_id'],
				$params['recipe_log_id'],
				$params['run_number']
			),
			self::QUERY_RESULTS_FORMAT
		);

		return (array) $results;
	}

	/**
	 * Retrieve the action data.
	 *
	 * @param int $id The primary key ID in the deduplicated data table.
	 *
	 * @return array The unserialized action data array. Empty array if not found or on error.
	 */
	public function get_action_data( $id ) {

		$id = absint( $id );

		if ( $id <= 0 ) {
			return array();
		}

		$results = $this->db->get_var(
			$this->db->prepare(
				"SELECT data as `action_data` FROM {$this->db->prefix}uap_loop_entries_items_data WHERE id = %d",
				$id
			)
		);

		// maybe_unserialize returns false for invalid/empty serialized data. Normalize to empty array.
		$unserialized = maybe_unserialize( $results );

		return is_array( $unserialized ) ? $unserialized : array();
	}
}
