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

}
