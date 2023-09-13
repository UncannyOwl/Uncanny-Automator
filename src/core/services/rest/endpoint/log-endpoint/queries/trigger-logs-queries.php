<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries;

class Trigger_Logs_Queries {

	/**
	 * @var \wpdb $db
	 */
	protected $db = null;

	const QUERY_RESULTS_FORMAT = ARRAY_A;

	/**
	 * @param \wpdb $db
	 */
	public function __construct( \wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * Retrieves the recipe trigger logs.
	 *
	 * @param int[] $params Accepts ['recipe_log_id','recipe_id', 'run_number'].
	 *
	 * @return mixed[]|object|null
	 */
	public function get_recipe_trigger_logs_raw( $params = array() ) {

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT 
                -- Trigger logs table.
                trigger_log.ID as trigger_log_id,
                trigger_log.date_time as trigger_log_datetime,
                trigger_log.user_id as trigger_log_user_id,
                trigger_log.automator_trigger_id as trigger_log_trigger_id,
                trigger_log.completed as trigger_log_status
                -- Select data from uap_trigger_log
                FROM {$this->db->prefix}uap_trigger_log AS trigger_log
                -- Inner join with uap_recipe_log
                INNER JOIN {$this->db->prefix}uap_recipe_log AS recipe_log
                -- On ID
                ON recipe_log.ID = trigger_log.automator_recipe_log_id
                -- Target specific log id from params
                WHERE recipe_log.ID = %d
                -- Target specific recipe id from params
                AND recipe_log.automator_recipe_id = %d
                -- Target specific run_number from params
                AND recipe_log.run_number = %d
                ",
				$params['recipe_log_id'],
				$params['recipe_id'],
				$params['run_number']
			),
			self::QUERY_RESULTS_FORMAT
		);

		return $results;

	}

	/**
	 * @param int[] $params
	 *
	 * @return mixed[]|object|null
	 */
	public function trigger_runs_query( $params = array() ) {

		if ( automator_db_view_exists( 'trigger' ) ) {
			return $this->db->get_results(
				$this->db->prepare(
					"SELECT * FROM {$this->db->prefix}uap_trigger_logs_view 
					WHERE automator_recipe_id = %d 
					AND recipe_run_number = %d
					AND recipe_log_id = %d
					AND automator_trigger_id = %d
					ORDER BY trigger_date DESC LIMIT 0,100",
					$params['recipe_id'],
					$params['run_number'],
					$params['recipe_log_id'],
					$params['trigger_id']
				),
				self::QUERY_RESULTS_FORMAT
			);
		}

		return require __DIR__ . '/view-queries/trigger.php';

	}


	/**
	 * @param int[] $params
	 *
	 * @return mixed[]
	 */
	public function recorded_triggers_query( $params = array() ) {

		$recorded_triggers = $this->db->get_var(
			$this->db->prepare(
				"SELECT meta_value FROM {$this->db->prefix}uap_trigger_log_meta 
				WHERE automator_trigger_log_id = %d 
				AND automator_trigger_id = %d
				AND meta_key = %s
				ORDER BY ID DESC LIMIT 0,100
				",
				$params['trigger_log_id'],
				$params['trigger_id'],
				'recipe_current_triggers'
			)
		);

		if ( ! empty( $recorded_triggers ) ) {
			$recorded_triggers = json_decode( $recorded_triggers, true );
			if ( is_array( $recorded_triggers ) ) {
				return $recorded_triggers;
			}
		}

		return array();

	}

	/**
	 * @param int[] $params
	 *
	 * @return string
	 */
	public function trigger_fields_query( $params = array() ) {

		$fields = $this->db->get_var(
			$this->db->prepare(
				"SELECT meta_value
				FROM {$this->db->prefix}uap_trigger_log_meta
				WHERE automator_trigger_log_id = %d
				AND automator_trigger_id = %d
				AND meta_key = %s
				",
				$params['trigger_log_id'],
				$params['trigger_id'],
				'trigger_fields'
			)
		);

		if ( null !== $fields && is_string( $fields ) ) {
			return $fields;
		}

		return '';

	}

	/**
	 * Retrieves the trigger logic from recipe log meta.
	 *
	 * @param mixed[] $params
	 *
	 * @return null|string 'ALL' or 'ANY'. Otherwise, null.
	 */
	public function get_trigger_logic( $params = array() ) {

		$result = $this->db->get_var(
			$this->db->prepare(
				"SELECT meta_value 
					FROM {$this->db->prefix}uap_recipe_log_meta AS recipe_log_meta
					INNER JOIN {$this->db->prefix}uap_recipe_log AS recipe_log
						ON recipe_log.ID = recipe_log_meta.recipe_log_id
					WHERE recipe_log_meta.recipe_log_id = %d
						AND recipe_log_meta.recipe_id = %d
						AND recipe_log_meta.meta_key = 'triggers_logic'
						AND recipe_log.run_number = %d
					",
				$params['recipe_log_id'],
				$params['automator_recipe_id'],
				$params['run_number']
			)
		);

		if ( empty( $result ) ) {
			return null;
		}

		$result = (array) json_decode( $result, ARRAY_A );

		if ( isset( $result['logic'] ) ) {
			return $result['logic'];
		}

		return null;

	}

}
