<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources;

use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Automator_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Loop_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;

class Loop_Logs_Resources {

	protected $utils;
	protected $loop_logs_queries;
	protected $automator_factory;

	/**
	 * @param Loop_Logs_Queries $loop_logs_queries
	 * @param Formatters_Utils $utils
	 * @param Automator_Factory $automator_factory
	 */
	public function __construct(
		Loop_Logs_Queries $loop_logs_queries,
		Formatters_Utils $utils,
		Automator_Factory $automator_factory
		) {

		$this->utils             = $utils;
		$this->loop_logs_queries = $loop_logs_queries;
		$this->automator_factory = $automator_factory;

	}

	/**
	 * Retrieves the fields
	 *
	 * @param mixed[] $filter
	 *
	 * @return mixed[]
	 */
	public function get_filter_fields( $filter ) {

		$fields_item = array();

		foreach ( (array) $filter['fields'] as $code => $field ) {

			$structure = array(
				'field_code' => $code,
				'type'       => $field['type'],
				'label'      => $field['backup']['label'],
				'attributes' => array(),
				'value'      => array(
					'readable' => isset( $field['readable'] ) ? $field['readable'] : '',
					'raw'      => $field['value'],
				),
			);

			$fields_item[] = $structure;

		}

		return $fields_item;

	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $flow
	 * @return void
	 */
	private function find_loop_by_id( $flow, $loop_id ) {

		$loop_index = array_search( absint( $loop_id ), array_column( $flow['items'], 'id' ), true );

		if ( false === $loop_index ) {
			return false;
		}

		$loop = $flow['items'][ $loop_index ];

		return $loop;
	}

	/**
	 * Retrieves loops filters.
	 *
	 * @param mixed[] $loop
	 *
	 * @return mixed[]
	 */
	public function get_loop_filters( $loop ) {

		if ( ! isset( $loop['filters'] ) ) {
			return array();
		}

		$loop_filters = array();

		foreach ( $loop['filters'] as $filter ) {

			$loop_filters[] = array(
				'id'               => $filter['id'],
				'integration_code' => $filter['integration_code'],
				'code'             => $filter['code'],
				'title_html'       => $filter['backup']['sentence_html'],
				'fields'           => $this->get_filter_fields( $filter ),
			);

		}

		return $loop_filters;
	}

	public function get_log( $params ) {

		$utils = $this->utils;

		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			return array();
		}

		if ( version_compare( AUTOMATOR_PRO_PLUGIN_VERSION, '5.0', '<' ) ) {
			return array();
		}

		$loops_log = $this->loop_logs_queries->get_recipe_loops_logs( $params );

		$loops = array();

		$loops_db = Automator()->loop_db();

		foreach ( $loops_log as $log ) {
			$flow = (array) maybe_unserialize( $log['flow'] );

			$loop = $this->find_loop_by_id( $flow, $log['loop_id'] );

			$elements_completed = $loops_db->find_loop_items_completed_count( $log['loop_id'], $params );

			$datetime_started = $log['process_date_started'];
			$datetime_ended   = $log['process_date_ended'];

			if ( null === $datetime_ended ) { // It means the loop is still in progress.
				$datetime_ended = $utils::unix_timestamp_to_date( time() );
			}

			$date_next_process = $this->get_next_process( $log['process_id'] );

			$loop_id = absint( $log['loop_id'] );

			$status = apply_filters(
				'automator_loop_logs_resources_status',
				array(
					'status_id'          => $log['status'],
					'message'            => $log['message'],
					'elements_total'     => absint( $log['num_entities'] ),
					'elements_completed' => $elements_completed,
				),
				$params,
				$loop_id,
				$flow
			);

			$type = isset( $loop['iterable_expression']['type'] ) ? $loop['iterable_expression']['type'] : null;

			$structure = array(
				'type'                => 'loop',
				'logic'               => 'intersection',
				'id'                  => $loop_id,
				'status'              => $status,
				'date_next_process'   => $date_next_process,
				'start_date'          => $utils::date_time_format( $datetime_started ),
				'end_date'            => $utils::date_time_format( $datetime_ended ),
				'date_elapsed'        => $utils::get_date_elapsed( $datetime_started, $datetime_ended ),
				'_timestamp'          => $utils::strtotime( $datetime_ended ),
				'iterable_expression' => array(
					'type'       => $type,
					'value'      => '',
					'value_html' => '',
				),
				'run_on'              => null,
				'loops_filters'       => $this->get_loop_filters( $loop ),
				'items'               => $this->get_items( $loop, $params ),
			);

			$loops[] = $structure;

		}

		return $loops;
	}

	private function get_next_process( $process_id ) {

		$health_check = (array) wp_get_scheduled_event( 'uap_loops_' . $process_id . '_cron' );

		$utils = $this->utils;

		if ( isset( $health_check['timestamp'] ) ) {

			$ts = $health_check['timestamp'];

			// Get local TS.
			$formatted_date = $utils::unix_timestamp_to_date( $ts );

			return array(
				'human_time_diff'  => human_time_diff( time(), $ts ),
				'delay_in_seconds' => $ts - time(),
				'timestamp'        => $ts,
				'formatted'        => $formatted_date,
			);
		}

		return false;

	}

	/**
	 * Get items
	 *
	 * @param mixed[] $loop
	 * @param mixed[] $params
	 *
	 * @return mixed[]
	 */
	public function get_items( $loop, $params ) {

		$flow_items = array();

		foreach ( (array) $loop['items'] as $item ) {

			// Normal actions
			if ( 'action' === $item['type'] ) {

				$structure = array(
					'type'             => 'action',
					'uses_credit'      => $item['miscellaneous']['uses_credit'],
					'id'               => $item['id'],
					'integration_code' => $item['integration_code'],
					'code'             => $item['code'],
					'title_html'       => $item['backup']['sentence_html'],
					'fields'           => $this->restructure_fields( $params, $item['fields'], false ),
					'status'           => $this->get_statuses( $params, $item['id'] ),
					'runs'             => $this->get_runs( $loop, $item, $params, $item['id'] ),
				);
			}

			// Filter here refers to action conditions.
			if ( 'filter' === $item['type'] ) {

				$filter_items = array();

				$conditions = $this->restructure_conditions( $item['conditions'] );

				foreach ( $item['items'] as $filter_item ) {

					$filter_item_structure = array(
						'type'             => 'action',
						'uses_credit'      => $filter_item['miscellaneous']['uses_credit'],
						'id'               => $filter_item['id'],
						'integration_code' => $filter_item['integration_code'],
						'code'             => $filter_item['code'],
						'title_html'       => $filter_item['backup']['sentence_html'],
						'fields'           => $this->restructure_fields( $params, $filter_item['fields'], false ),
						'status'           => $this->get_statuses( $params, $filter_item['id'] ),
						'runs'             => $this->get_runs( $loop, $item, $params, $filter_item['id'] ),
					);

					$filter_items[] = $filter_item_structure;

				}

				$structure = array(
					'type'       => 'filter',
					'conditions' => $conditions,
					'logic'      => $item['logic'],
					'id'         => $item['id'],
					'items'      => $filter_items,
				);

			}

			$flow_items[] = $structure;
		}

		// Individual action summary.
		return $flow_items;

	}

	private function get_statuses( $params, $action_id ) {

		$distinct_statuses = $this->loop_logs_queries->get_distinct_statuses( $action_id, $params );

		$statuses = array();

		foreach ( $distinct_statuses as $status ) {

			$status_result_count = $this->loop_logs_queries->get_action_status_count( $action_id, $status['status'], $params );

			$statuses[] = array(
				'status_id' => $status['status'],
				'count'     => $status_result_count,
			);

		}

		return $statuses;

	}

	/**
	 * Retrieves the specific action run
	 *
	 * @param mixed[] $loop
	 * @param mixed[] $item The loop item.
	 * @param mixed[] $params
	 * @param int $action_id
	 *
	 * @return mixed[]
	 */
	public function get_runs( $loop, $item, $params, $action_id ) {

		// Ability to disable to loop runs.
		if ( true === apply_filters( 'automator_rest_endpoint_loops_logs_resources_disable_run', false ) ) {
			return array();
		}

		// Retrieve the distinct statuses collection for the current action.
		$distinct_statuses = $this->loop_logs_queries->get_distinct_statuses( $action_id, $params );

		$type = isset( $loop['iterable_expression']['type'] ) ? $loop['iterable_expression']['type'] : null;

		$statuses = array();

		foreach ( $distinct_statuses as $status ) {

			$runs_items = array();

			// Retieve those entries that are in the specific status.
			$entries = $this->loop_logs_queries->get_entry_items( $action_id, $status['status'], $params );

			foreach ( $entries as $entry ) {

				$entity_id = $entry['entity_id'];

				if ( 'posts' === $type ) {
					$identifier = sprintf( '#%d %s', $entity_id, get_the_title( $entity_id ) );
				} else {
					$identifier = sprintf( '#%d %s', $entity_id, get_userdata( $entity_id )->display_name );
				}

				$structure = array(
					'run_identifier' => $identifier,
					'date'           => Formatters_Utils::date_time_format( $entry['date_added'] ),
					'result_message' => $entry['error_message'],
				);

				$runs_items[] = $structure;
			}

			$statuses[] = array(
				'status_id' => $status['status'],
				'runs'      => $runs_items,
			);

		}

		return $statuses;

	}

	private function restructure_fields( $params, $fields, $show_parsed = false ) {

		$fields_item = array();

		$options       = isset( $fields['options'] ) ? $fields['options'] : array();
		$options_group = isset( $fields['options_group'] ) ? $fields['options_group'] : array();

		$merged_fields = array_merge( $options, $options_group );

		foreach ( $merged_fields as $field ) {

			$structure = array(
				'field_code' => $field['field_code'],
				'type'       => $field['type'],
				'label'      => $field['label'],
				'attributes' => $field['attributes'],
				'value'      => array(
					'readable' => $field['value']['readable'],
					'raw'      => $field['value']['raw'],
				),
			);

			$fields_item[] = $structure;

		}

		return $fields_item;

	}


	private function restructure_conditions( $recipe_main_object_filter_conditions ) {

		$conditions_restructured = array();

		foreach ( (array) $recipe_main_object_filter_conditions as $condition ) {

			$condition_fields = array();

			$structure = array(
				'integration_code' => $condition['integration_code'],
				'code'             => $condition['code'],
				'id'               => $condition['id'],
				'title_html'       => $condition['backup']['sentence_html'],
				'fields'           => $condition_fields,
			);

			$conditions_restructured[] = $structure;

		}

		return $conditions_restructured;

	}

}
