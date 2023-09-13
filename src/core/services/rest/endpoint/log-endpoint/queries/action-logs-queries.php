<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries;

/**
 * The fat repository class for logs endpoint.
 *
 * @since 4.12
 */
class Action_Logs_Queries {

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
	 * Ensures array data.
	 *
	 * @param mixed $mixed
	 *
	 * @return mixed[]
	 */
	private function to_array( $mixed = null ) {

		// Return blank array for falsy variables.
		if ( empty( $mixed ) ) {
			return array();
		}

		// Return blank array for non-arrays.
		if ( ! is_array( $mixed ) ) {
			return array();
		}

		return $mixed;

	}

	/**
	 * Retrieves the action logs for a specific action run.
	 *
	 * @param int[] $params An array of parameters for the query. Accepts ['recipe_log_id','recipe_id', 'run_number'].
	 *
	 * @return mixed[] The query results.
	 */
	public function action_runs_query( $params ) {

		$args = wp_parse_args(
			$params,
			array(
				'recipe_id'     => 0,
				'run_number'    => 0,
				'recipe_log_id' => 0,
				'action_id'     => 0,
			)
		);

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->db->prefix}uap_action_log 
					WHERE automator_action_id = %d 
						AND automator_recipe_id = %d 
						AND automator_recipe_log_id = %d
					",
				$args['action_id'],
				$args['recipe_id'],
				$args['recipe_log_id']
			),
			ARRAY_A
		);

		return $this->to_array( $results );

	}

	/**
	 * Retrieves the raw action logs for a recipe run.
	 *
	 * @param int[] $params An array of parameters for the query. Accepts ['recipe_log_id','recipe_id', 'run_number'].
	 *
	 * @return mixed[] The query results.
	 */
	public function get_recipe_actions_logs_raw( $params ) {

		$args = wp_parse_args(
			$params,
			array(
				'recipe_id'     => 0,
				'run_number'    => 0,
				'recipe_log_id' => 0,
			)
		);

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * 
					FROM {$this->db->prefix}uap_action_log
					WHERE automator_recipe_id = %d
						AND automator_recipe_log_id = %d
				",
				$args['recipe_id'],
				$args['recipe_log_id']
			),
			ARRAY_A
		);

		return $this->to_array( $results );

	}

	/**
	 * Retrieves the action flow.
	 *
	 * @param int[] $params
	 *
	 * @return mixed[]
	 */
	public function get_recipe_actions_flow( $params ) {

		$json_string = $this->db->get_var(
			$this->db->prepare(
				"SELECT meta_value 
					FROM {$this->db->prefix}uap_recipe_log_meta 
					WHERE recipe_id = %d
					AND recipe_log_id = %d
				AND meta_key = %s
				",
				$params['recipe_id'],
				$params['recipe_log_id'],
				'actions_flow'
			)
		);

		$json_string = ! empty( $json_string ) ? $json_string : '';

		return Automator()->json_decode_parse_args(
			$json_string,
			array(
				'actions_conditions' => array(),
				'flow'               => array(),
			)
		);

	}

	/**
	 * Retrieves the field values for an action log.
	 *
	 * @param int $action_id The ID of the action.
	 * @param int $action_log_id The ID of the action log.
	 *
	 * @return string The field values for the action log.
	 */
	public function field_values_query( $action_id, $action_log_id ) {

		$meta_val = $this->db->get_var(
			$this->db->prepare(
				"SELECT meta_value 
					FROM {$this->db->prefix}uap_action_log_meta
					WHERE meta_key = %s
					AND automator_action_id = %d
					AND automator_action_log_id = %d
				",
				'action_fields',
				$action_id,
				$action_log_id
			)
		);

		return ! empty( $meta_val ) ? $meta_val : '';

	}

	/**
	 * Query Closures log.
	 *
	 * @param int $recipe_id
	 * @param int $recipe_log_id
	 *
	 * @return mixed[] The rows retrieved from the query.
	 */
	public function get_closures_as_action_query( $recipe_id = 0, $recipe_log_id = 0 ) {

		$result = $this->db->get_row(
			$this->db->prepare(
				"SELECT 
					log.ID as log_id, 
					log.user_id,
					log.automator_closure_id as closure_id,
					log.date_time, 
					log_meta.meta_value as log_entry_value
					-- SELECT FROM closure_log table.
					FROM {$this->db->prefix}uap_closure_log AS log
					-- JOIN
					INNER JOIN {$this->db->prefix}uap_closure_log_meta as log_meta
					ON log_meta.automator_closure_log_id = log.ID
					-- CONDITIONS
					WHERE log_meta.meta_key = 'closure_data'
					AND log.automator_recipe_id = %d
					AND log.automator_recipe_log_id = %d
				",
				$recipe_id,
				$recipe_log_id
			),
			self::QUERY_RESULTS_FORMAT
		);

		if ( empty( $result ) ) {
			// Check the recipe meta
			$result = $this->try_fetching_from_log( $recipe_id, $recipe_log_id );
		}

		return $this->to_array( $result );

	}

	/**
	 * The query for fetching parsed tokens records from the _uap_tokens_log
	 *
	 * @param int[] $params
	 *
	 * @return mixed[]
	 */
	public function tokens_log_queries( $params ) {
		return $this->to_array( self::find_replace_pairs( $params ) );
	}

	/**
	 * We need to define static method here since the token record
	 * really belongs to the action. However, there are cases where it needs
	 * to be re-used without instantiating the class.
	 *
	 * @todo Create a separate class for tokens record and have the Actions, and Loop Actions consume it.
	 *
	 * @param int[] $params
	 *
	 * @return mixed[]
	 */
	public static function find_replace_pairs( $params ) {

		global $wpdb; // We're using $wpdb here since static methods dont contain the reference to the current instance of the class.

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tokens_record 
					FROM {$wpdb->prefix}uap_tokens_log 
					WHERE recipe_id=%d 
					AND recipe_log_id=%d 
					AND run_number=%d",
				$params['recipe_id'],
				$params['recipe_log_id'],
				$params['run_number']
			),
			self::QUERY_RESULTS_FORMAT
		);

		return (array) $results;

	}

	/**
	 * The query for fetching the 'actions_conditions_evaluated' from _uap_recipe_log_meta
	 *
	 * @param int[] $params
	 *
	 * @return array<mixed[]>
	 */
	public function get_actions_conditions_evaluated( $params = array() ) {

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT meta_value 
					FROM {$this->db->prefix}uap_recipe_log_meta 
					WHERE recipe_id = %d 
					AND recipe_log_id = %d 
					AND meta_key = 'actions_conditions_evaluated'",
				$params['recipe_id'],
				$params['recipe_log_id']
			),
			self::QUERY_RESULTS_FORMAT
		);

		return $this->to_array( $results ); // @phpstan-ignore-line The result is array<mixed[]>, but we cannot force to_array to be a flat array.

	}

	/**
	 * Retrieve the retries.
	 *
	 * @param int[] $params
	 *
	 * @return mixed[]
	 */
	public function get_retries( $params ) {

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT log.ID, log.item_log_id, log.date_time, log.endpoint, log_response.result, log_response.message
					FROM {$this->db->prefix}uap_api_log AS log
					INNER JOIN {$this->db->prefix}uap_api_log_response AS log_response
					ON log_response.api_log_id = log.ID
					WHERE type = 'action'
					AND log.recipe_log_id = %d
					AND log.item_log_id = %d
					ORDER BY log_response.ID ASC
				",
				$params['recipe_log_id'],
				$params['action_log_id']
			),
			self::QUERY_RESULTS_FORMAT
		);

		return $this->to_array( $results );

	}

	/**
	 * Retrieve the delays.
	 *
	 * @param mixed[] $params
	 *
	 * @return mixed[]
	 */
	public function get_delays( $params = array() ) {

		$result = $this->db->get_var(
			$this->db->prepare(
				"SELECT meta_value FROM {$this->db->prefix}uap_recipe_log_meta 
					WHERE recipe_id = %d 
					AND recipe_log_id = %d
					AND meta_key = %s
					",
				$params['recipe_id'],
				$params['recipe_log_id'],
				'action_delays'
			)
		);

		if ( empty( $result ) ) {
			return array();
		}

		return (array) json_decode( $result, true );

	}

	/**
	 * @param int $recipe_id
	 * @param int $recipe_log_id
	 *
	 * @return mixed[]
	 */
	private function try_fetching_from_log( $recipe_id, $recipe_log_id ) {

		$result = $this->db->get_var(
			$this->db->prepare(
				"SELECT meta_value
				FROM {$this->db->prefix}uap_recipe_log_meta
				WHERE meta_key = 'closures'
				AND recipe_log_id = %d
				AND recipe_id = %d
				",
				$recipe_log_id,
				$recipe_id
			)
		);

		if ( empty( $result ) ) {
			return array();
		}

		$result = wp_parse_args(
			(array) json_decode( $result, true ),
			array(
				'meta' => array(
					'sentence_human_readable_html' => '',
				),
			)
		);

		$meta = array(
			'meta' => array(
				'code'                         => 'REDIRECT',
				'integration'                  => 'WP',
				'sentence_human_readable_html' => $result['meta']['sentence_human_readable_html'],
				'REDIRECTURL'                  => $result,
			),
		);
		// Mock the response.
		$result = array(
			'mock'            => true,
			'user_id'         => null,
			'closure_id'      => $result['log']['ID'],
			'log_id'          => 0,
			'date_time'       => null,
			'log_entry_value' => wp_json_encode( $meta ),
		);

		return $result;

	}

}
