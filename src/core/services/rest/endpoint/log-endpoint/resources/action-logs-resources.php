<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources;

use Uncanny_Automator\Resolver\Fields_Conditions_Resolver;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Automator_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Action_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Loop_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Action_Logs_Helpers\Conditions_Helper;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;

class Action_Logs_Resources {

	/**
	 * @var Action_Logs_Queries
	 */
	protected $action_logs_queries = null;

	/**
	 * Requires by this action log to append the loops at the end.
	 *
	 * @var Loop_Logs_Resources
	 */
	protected $loop_log_resources = null;

	/**
	 * @var Automator_Factory
	 */
	protected $automator_factory = null;

	/**
	 * @var Formatters_Utils
	 */
	protected $utils = null;

	/**
	 * @var Fields_Conditions_Resolver
	 */
	protected $field_conditions_resolver = null;

	/**
	 * @var Conditions_Helper
	 */
	protected $conditions = null;

	/**
	 * @param Action_Logs_Queries $action_logs_queries
	 * @param Formatters_Utils $utils
	 * @param Automator_Factory $automator_factory
	 * @param Loop_Logs_Resources $loop_logs_resources Needs by the action to append the logs.
	 */
	public function __construct(
		Action_Logs_Queries $action_logs_queries,
		Formatters_Utils $utils,
		Automator_Factory $automator_factory,
		Loop_Logs_Resources $loop_log_resources
		) {

		$this->utils               = $utils;
		$this->action_logs_queries = $action_logs_queries;
		$this->automator_factory   = $automator_factory;
		$this->loop_log_resources  = $loop_log_resources;

	}

	public function get_utils() {
		return $this->utils;
	}

	/**
	 * Set the field conditions resolver.
	 *
	 * @return self
	 */
	public function set_field_conditions_resolver( Fields_Conditions_Resolver $field_conditions_resolver ) {
		$this->field_conditions_resolver = $field_conditions_resolver;
		return $this;
	}

	/**
	 * Set the field conditions resolver.
	 *
	 * @return self
	 */
	public function set_conditions( Conditions_Helper $conditions ) {
		$this->conditions = $conditions;
		return $this;
	}

	/**
	 * Determine if the log result is already served to avoid duplicate queries.
	 *
	 * @param int $action_log_id
	 *
	 * @return boolean True if the action has a log in the api table. Returns false, otherwise.
	 */
	protected function has_api_log( $action_log_id = 0 ) {

		$key = 'automator_action_log_resources_has_api_log_' . $action_log_id;
		$log = wp_cache_get( $key );

		if ( false !== $log ) {
			return ! is_null( $log );
		}

		$log = $this->automator_factory->db_api()->get_by_log_id( 'action', $action_log_id );

		wp_cache_set( $key, true );

		return ! is_null( $log );

	}

	/**
	 * @param int[] $params
	 *
	 * @return mixed[]
	 */
	protected function get_recipe_actions_logs_raw( $params ) {
		$key           = 'get_recipe_action_logs_raw_' . maybe_serialize( $params );
		$cached_result = wp_cache_get( $key );
		if ( false !== $cached_result ) {
			return (array) $cached_result;
		}
		$result = $this->action_logs_queries->get_recipe_actions_logs_raw( $params );
		wp_cache_set( $key, $result );
		return $result;
	}

	/**
	 * Retrieves fields of a specific action id and action log id.
	 *
	 * @param int $action_id
	 * @param int $action_log_id
	 *
	 * @return mixed[] $fields
	 */
	protected function retrieve_fields( $action_id, $action_log_id ) {

		$fields = json_decode(
			$this->action_logs_queries->field_values_query( $action_id, $action_log_id ),
			true
		);

		if ( ! is_array( $fields ) ) {
			return array();
		}

		$utils = $this->get_utils();

		if ( $utils::fields_has_combination_of_options_and_options_group( $fields ) ) {
			$fields = array_unique( array_merge( ...$fields ), SORT_REGULAR );
		}

		return $fields;

	}

	/**
	 * Get the fields values using the action id and the action log id.
	 *
	 * @param int[] $params
	 * @param int $action_id The action id.
	 * @param int $action_log_id The action log id.
	 *
	 * @return mixed
	 */
	protected function get_fields_values( $params = array(), $action_id = 0, $action_log_id = 0 ) {

		$fields = $this->retrieve_fields( $action_id, $action_log_id );

		$replace_pairs = $this->get_parsed_token_record( $params );

		foreach ( $fields as $key => $field ) {

			// Interpolate the token based on the parsed value that was stored in the token log.
			$replaced_values = Automator()->parsed_token_records()->interpolate( /** @phpstan-ignore-line */
				$field['value']['raw'], /** @phpstan-ignore-line */
				$replace_pairs
			);

			// Nested tokens alert! 😱
			preg_match_all( '/{{\s*(.*?)\s*}}/', $replaced_values, $matches );

			if ( ! empty( $matches[0] ) ) { // The interpolated values still contains tokens.
				$replaced_values = Automator()->parsed_token_records()->interpolate(
					$replaced_values,
					$replace_pairs
				);
			}

			// Repeater is a special field which already contains JSON.
			// A token can break a JSON so make sure its handled.
			if ( 'repeater' === $field['type'] ) {

				$individual_repeater = array();

				$repeater_fields_array = (array) json_decode( $fields[ $key ]['value']['raw'], true );

				foreach ( $repeater_fields_array as $index => $repeater_fields ) {

					foreach ( $repeater_fields as $code => $value ) {
						$parsed_value = Automator()->parsed_token_records()->interpolate(
							$value,
							$replace_pairs
						);

						// Fix the JSON!
						$parsed_value = strtr(
							htmlentities( $parsed_value, ENT_QUOTES ),
							array(
								"\n" => "\\n",
								"\r" => "\\r",
							)
						);

						$individual_repeater[ $index ][ $code ] = $parsed_value;
					}
				}

				$fields[ $key ]['value']['parsed'] = wp_json_encode( $individual_repeater );

			} else {
				// Otherwise, proceed as normal.
				// Ignore the following errors in phpstan, as we have to inject the array values into keys. No need for 'key' checking.
				$fields[ $key ]['value']['parsed'] = $replaced_values; /** @phpstan-ignore-line */
			}
		}

		return $fields;

	}

	/**
	 * @param int[] $params
	 *
	 * @return mixed[]
	 */
	public function get_action_runs( $params ) {

		$action_runs = array();

		$results = $this->action_logs_queries->action_runs_query( $params );

		$status = $this->automator_factory->status();

		foreach ( $results as $action_log ) {

			$action_log = wp_parse_args(
				(array) $action_log,
				array(
					'completed' => null,
					'date_time' => null,
				)
			);

			$status_id = $status::get_class_name( $action_log['completed'] );

			$properties = (array) maybe_unserialize( Automator()->db->action->get_meta( $action_log['ID'], 'properties' ) );

			$action_runs[] = array(
				'date'           => $this->utils->date_time_format( $action_log['date_time'] ),
				'_timestamp'     => $this->utils->strtotime( $action_log['date_time'] ),
				'used_credit'    => $this->has_api_log( $params['action_log_id'] ),
				'status_id'      => $status_id,
				'result_message' => $action_log['error_message'],
				'properties'     => $properties,
			);

		}

		// Append retries.
		$retries = $this->action_logs_queries->get_retries( $params );

		foreach ( $retries as $item ) {

			$item = wp_parse_args(
				(array) $item,
				array(
					'date_time' => null,
					'result'    => '',
					'message'   => '',
				)
			);

			$action_runs[] = array(
				'date'           => $this->utils->date_time_format( $item['date_time'] ),
				'_timestamp'     => $this->utils->strtotime( $item['date_time'] ),
				'used_credit'    => true,
				'status_id'      => $item['result'],
				'result_message' => $item['message'],
				'properties'     => array(),
			);

		}

		return $action_runs;

	}

	/**
	 * Fetch the tokens record.
	 *
	 * @param int[] $params
	 *
	 * @return mixed[] Returns the interpolated token values from the record.
	 */
	public function get_parsed_token_record( $params ) {

		$results = $this->action_logs_queries->tokens_log_queries( $params );

		$results = wp_parse_args(
			(array) $results,
			array(
				'tokens_record' => array(),
			)
		);

		$actions_parsed_tokens = array();
		$interpolated          = array();

		foreach ( $results as $result ) {
			$actions_parsed_tokens[] = (array) json_decode( isset( $result['tokens_record'] ) ? $result['tokens_record'] : '', true );
		}

		foreach ( $actions_parsed_tokens as $action_parsed_tokens ) {
			foreach ( $action_parsed_tokens as $key => $val ) {
				$interpolated[ $key ] = $val;
			}
		}

		return $interpolated;

	}

	/**
	 * @param int[] $params
	 * @param mixed[] $actions_flattened
	 * @param int $action_id
	 *
	 * @return mixed[]
	 */
	private function resolve_action_item( $params, $actions_flattened, $action_id ) {

		// Its a simple action.
		$action_id = absint( $action_id );

		$utils = $this->get_utils();

		$action_meta = $utils::flatten_post_meta( (array) get_post_meta( $action_id ) );

		// Default values for action meta.
		$action_meta = wp_parse_args(
			$action_meta,
			array(
				'integration'                  => 'INTEGRATION_CODE_NOT_FOUND',
				'code'                         => 'ACTION_CODE_NOT_FOUND',
				'sentence_human_readable_html' => 'SENTENCE_NOT_FOUND',
			)
		);

		// Retrieve the user ID from recipe log.
		$user_id = apply_filters( 'automator_field_resolver_condition_result_user_id', null );

		$action_log_record = Automator()->db->action->get_log(
			$params['recipe_id'],
			$params['recipe_log_id'],
			$action_id,
			$user_id
		);

		// Determine whether to use the live recipe action meta or the one from the log.
		$action_meta = $this->resolve_action_meta( $action_meta, $params, $action_id, $action_log_record );

		$action_log = array(
			'action_log_id'    => $action_log_record['ID'],
			'action_completed' => $action_log_record['completed'],
		);

		$status = $this->automator_factory->status();

		$status_id = $status::get_class_name( $action_log['action_completed'] );

		// Null status ID means the action was not invoked yet.
		if ( null === $status_id ) {
			$status_id = 'not-completed';
		}

		$can_rerun = null !== $this->automator_factory->db_api()->get_by_log_id( 'action', $action_log['action_log_id'] );

		if ( 'INTEGRATION_CODE_NOT_FOUND' === $action_meta['integration'] ) {
			$can_rerun = 'CANNOT_RESOLVE_CAN_RERUN';
		}

		$action_runs = $this->get_action_runs(
			array(
				'recipe_id'     => $params['recipe_id'],
				'run_number'    => $params['run_number'],
				'recipe_log_id' => $params['recipe_log_id'],
				'action_id'     => $action_id,
				'action_log_id' => $action_log['action_log_id'],
			)
		);

		$end_date = isset( $action_runs[ count( $action_runs ) - 1 ]['date'] ) /** @phpstan-ignore-line False positive. */
					? $action_runs[ count( $action_runs ) - 1 ]['date'] /** @phpstan-ignore-line False positive. */
					: null;

		$start_date = isset( $action_runs[0]['date'] ) ? $action_runs[0]['date'] : null; /** @phpstan-ignore-line False positive. */
		$_ts        = isset( $action_runs[0]['_timestamp'] ) ? $action_runs[0]['_timestamp'] : null;

		$action_sentence_html = Automator()->db->action->get_meta( $action_log['action_log_id'], 'sentence_human_readable_html' );

		// Fallback to postmeta if the sentence HTML is not saved.
		if ( empty( $action_sentence_html ) ) {
			$action_sentence_html = $action_meta['sentence_human_readable_html'];
		}

		$item = array(
			'type'             => 'action',
			'id'               => $action_id,
			'integration_code' => $action_meta['integration'],
			'code'             => $action_meta['code'],
			'status_id'        => $status_id,
			'is_deleted'       => isset( $action_meta['is_deleted'] ),
			'title_html'       => htmlspecialchars( $action_sentence_html, ENT_QUOTES ),
			'can_rerun'        => $can_rerun,
			'item_log_id'      => $action_log['action_log_id'],
			'fields'           => $this->get_fields_values( $params, $action_id, $action_log['action_log_id'] ),
			'start_date'       => $start_date,
			'end_date'         => $end_date, // Defaults to null.
			'date_elapsed'     => $utils::get_date_elapsed( $start_date, $end_date ),
			'_timestamp'       => $_ts,
			'runs'             => $action_runs,
		);

		if ( isset( $action_runs[ count( $action_runs ) - 1 ] ) ) {
			$last_action_run = $action_runs[ count( $action_runs ) - 1 ];
			if ( ! empty( $last_action_run['result_message'] ) ) {
				$item['result_message'] = $last_action_run['result_message'];
			}
		}

		// Determine if the item has delay/schedule set.
		$fabricated_item = $this->fabricate_item_with_delay( $action_id, $params, $action_runs, $action_meta );

		if ( false !== $fabricated_item ) {
			$item['status_id']             = $fabricated_item['status_id'];
			$item['start_date']            = $fabricated_item['start_date'];
			$item['_timestamp']            = $fabricated_item['_timestamp'];
			$item['runs'][0]['_timestamp'] = $fabricated_item['_timestamp'];
			$item['end_date']              = $fabricated_item['end_date'];
		}

		return $item;

	}

	/**
	 * Fabricates action items that has delay but has not started yet because the user has yet to finish the trigger runs\
	 * or there are multiple Triggers with "All" option selected and not all Triggers are fired yet.
	 *
	 * @param mixed[] $action_meta
	 *
	 * @return mixed[]|false
	 */
	protected function fabricate_item_with_delay_not_started( $action_meta = array() ) {

		$utils = $this->get_utils();

		if ( 'delay' === $action_meta['async_mode'] ) {

			$time = isset( $action_meta['async_delay_number'] ) ? $action_meta['async_delay_number'] : null;
			$unit = isset( $action_meta['async_delay_unit'] ) ? $action_meta['async_delay_unit'] : null;

			$time_units = $utils::time_units( $time );

			return array(
				'status_id'  => 'not-completed',
				'start_date' => sprintf( $time_units[ $unit ], $time ),
				'_timestamp' => strtotime( sprintf( $time_units[ $unit ], $time ) ),
				'end_date'   => null,
			);

		}

		if ( 'schedule' === $action_meta['async_mode'] ) {

			return array(
				'status_id'  => 'not-completed',
				'start_date' => $action_meta['async_sentence'],
				'_timestamp' => strtotime( $action_meta['async_sentence'] ),
				'end_date'   => null,
			);

		}

		return false;

	}

	/**
	 * Fabricate the item delays. The 'Delayed' and 'Scheduled' are not official statuses.\
	 * refer to Automator_Status class for official status codes.
	 *
	 * ⚬ The status 'delayed' or 'scheduled' does not exists as an official status.\
	 * ⚬ The 'in-progress' has no start_date and end_date. It only has 'date'\
	 *  ⚡ Which is updated when the action has been processed from async.\
	 *  ⚡ And is hydrated when the action is first invoked.\
	 *
	 * @param int $action_id
	 * @param int[] $params
	 * @param mixed[] $action_runs
	 * @param mixed[] $action_meta
	 *
	 * @return mixed[]|false
	 */
	public function fabricate_item_with_delay( $action_id, $params, $action_runs, $action_meta ) {

		$delays = $this->action_logs_queries->get_delays( $params );

		$item_has_delay = ! empty( $delays[ $action_id ] );

		$status = $this->automator_factory->status();

		$item_is_in_progressed = isset( $action_runs[0]['status_id'] ) /** @phpstan-ignore-line False positive. */
			&& $action_runs[0]['status_id'] === $this->automator_factory->status()->get_class_name( /** @phpstan-ignore-line False positive. */
				$status::IN_PROGRESS
			);

		$trigger_is_not_yet_completed = empty( $this->get_recipe_actions_logs_raw( $params ) );

		// Delayed and scheduled action from multiple triggers that are not yet completed.
		if ( $trigger_is_not_yet_completed && isset( $action_meta['async_mode'] ) ) {
			return $this->fabricate_item_with_delay_not_started( $action_meta );
		}

		if ( $item_has_delay && $item_is_in_progressed ) {

			$delay_props = (array) json_decode( is_string( $delays[ $action_id ] ) ? $delays[ $action_id ] : '', true );
			$status_id   = 'delayed';

			if ( 'schedule' === $delay_props['type'] ) {
				$status_id = 'scheduled';
			}

			$item['status_id'] = $status_id;

			$time_string = null;
			$date_time   = new \DateTime( 'now', new \DateTimeZone( Automator()->get_timezone_string() ) );

			if ( false !== $date_time ) {
				$date_time->setTimestamp( $delay_props['time'] );
				$time_string = $date_time->format( 'Y-m-d H:i:s' );
				$time_ts     = $date_time->format( 'U' );
			}

			return array(
				'status_id'  => $status_id,
				'start_date' => $this->utils->date_time_format( $time_string ),
				'_timestamp' => intval( $time_ts ),
				'end_date'   => null,
			);

		}

		return false;

	}

	/**
	 * @param int[] $params
	 * @param mixed[] $actions_flattened
	 * @param int $action_id
	 *
	 * @return mixed[]
	 */
	public function resolve_action_item_legacy( $params, $actions_flattened, $action_id ) {

		$utils = $this->get_utils();

		if ( empty( $actions_flattened ) ) {
			$actions_flattened = array();
		}

		$action_meta = $utils::flatten_post_meta( (array) get_post_meta( $action_id ) );

		$action_log = array();

		if ( isset( $actions_flattened[ $action_id ] ) ) {
			$action_log = $actions_flattened[ $action_id ];
		}

		$action_log = wp_parse_args(
			(array) $action_log,
			array(
				'action_completed' => 'not-completed',
				'action_log_id'    => 0,
			)
		);

		$status = $this->automator_factory->status();

		$status_id = $status::get_class_name( $action_log['action_completed'] );

		$runs = $this->get_action_runs(
			array(
				'recipe_id'     => $params['recipe_id'],
				'run_number'    => $params['run_number'],
				'recipe_log_id' => $params['recipe_log_id'],
				'action_id'     => $action_id,
				'action_log_id' => $action_log['action_log_id'],
			)
		);

		$timestamp = null;
		if ( isset( $runs[0]['_timestamp'] ) ) { /** @phpstan-ignore-line False positive. */
			$timestamp = $runs[0]['_timestamp']; /** @phpstan-ignore-line False positive. */
		}

		$result_message = '';
		if ( isset( $runs[ count( $runs ) - 1 ]['result_message'] ) ) { /** @phpstan-ignore-line False positive from dynamic array. */
			$result_message = $runs[ count( $runs ) - 1 ]['result_message'];
		}

		return array(
			'from_legacy_log'  => true,
			'type'             => 'action',
			'id'               => $action_id,
			'integration_code' => $action_meta['integration'],
			'code'             => $action_meta['code'],
			'status_id'        => $status_id,
			'_timestamp'       => $timestamp,
			'title_html'       => htmlspecialchars( $action_meta['sentence_human_readable_html'], ENT_QUOTES ),
			'can_rerun'        => null !== $this->automator_factory->db_api()->get_by_log_id(
				'action',
				$action_log['action_log_id']
			),
			'fields'           => array(),
			'result_message'   => $result_message,
			'runs'             => $runs,
		);
	}

	/**
	 * Append closures results to the reference of $results_formatted
	 *
	 * @param mixed[] $results_formatted
	 * @param int[] $params
	 *
	 * @return mixed[]
	 */
	private function append_closures_results( &$results_formatted, $params ) {

		$closure_log = $this->action_logs_queries->get_closures_as_action_query( $params['recipe_id'], $params['recipe_log_id'] );

		if ( isset( $closure_log['log_entry_value'] ) ) {

			$closure_log = wp_parse_args(
				$closure_log,
				array(
					'mock'            => false,
					'user_id'         => null,
					'closure_id'      => 0,
					'log_id'          => 0,
					'date_time'       => false,
					'log_entry_value' => '',
				)
			);

			$log_entry = wp_parse_args(
				(array) json_decode( $closure_log['log_entry_value'], true ),
				array(
					'meta' => array(
						'code'                         => 'CLOSURE_CODE_NOT_FOUND',
						'integration'                  => 'INTEGRATION_CODE_NOT_FOUND',
						'sentence_human_readable_html' => 'SENTENCE_NOT_FOUND',
						'REDIRECTURL'                  => 'REDIRECTURL_NOT_FOUND',
					),
				)
			);

			$date_time      = $this->utils->date_time_format( $closure_log['date_time'] );
			$timestamp      = $this->utils->strtotime( $closure_log['date_time'] );
			$status_id      = 'completed';
			$root_status_id = 'completed';

			if ( true === $closure_log['mock'] ) {
				$date_time      = 'When triggers are completed';
				$timestamp      = time();
				$status_id      = 'not-completed';
				$root_status_id = 'not-completed';
			}

			$field_values = $this->automator_factory->db()->closure->get_entry_meta(
				array(
					'user_id'                  => $closure_log['user_id'],
					'automator_closure_id'     => $closure_log['closure_id'],
					'automator_closure_log_id' => $closure_log['log_id'],
				),
				'field_values'
			);

			if ( false === $field_values ) {
				$field_values = '';
			}

			$field_values = $this->automator_factory->root()->json_decode_parse_args(
				$field_values,
				array(
					'raw'    => '',
					'parsed' => '',
				)
			);

			$results_formatted[] = array(
				'type'             => 'action',
				'id'               => $closure_log['closure_id'],
				'integration_code' => $log_entry['meta']['integration'],
				'code'             => $log_entry['meta']['code'],
				'status_id'        => $root_status_id,
				'start_date'       => $date_time,
				'_timestamp'       => $timestamp,
				'end_date'         => $date_time,
				'title_html'       => htmlentities( $log_entry['meta']['sentence_human_readable_html'], ENT_QUOTES ),
				'can_rerun'        => false,
				'fields'           => array(
					array(
						'field_code' => 'REDIRECTURL',
						'type'       => 'url',
						'label'      => esc_html__( 'Redirect URL', 'uncanny-automator' ),
						'value'      => array(
							'readable' => null,
							'raw'      => $field_values['raw'],
							'parsed'   => $field_values['parsed'],
						),
					),
				),
				'runs'             => array(
					array(
						'date'           => $date_time,
						'_timestamp'     => $this->utils->strtotime( $closure_log['date_time'] ),
						'status_id'      => $status_id,
						'result_message' => '',
						'used_credit'    => false,
						'properties'     => array(),
					),
				),
			);
		}

		return $results_formatted;

	}

	/**
	 * Appends the results to the result formatted.
	 *
	 * @param mixed[] $results_formatted Reference array to the $results_formatted.
	 * @param mixed[] $recipe_log_actions
	 * @param int[] $params
	 * @param mixed[] $log_actions
	 *
	 * @return mixed[]
	 */
	public function append_legacy_results( &$results_formatted, $recipe_log_actions, $params, $log_actions ) {

		// If the formatted results is empty, but the original actions log are not. It means, its from the legacy.
		foreach ( $recipe_log_actions as $recipe_log_action ) {

			$recipe_log_action = wp_parse_args(
				(array) $recipe_log_action,
				array(
					'automator_action_id' => 0,
				)
			);

			$results_formatted[] = $this->resolve_action_item_legacy(
				$params,
				$log_actions,
				$recipe_log_action['automator_action_id']
			);
		}

		return $results_formatted;

	}

	/**
	 * @param mixed[] $recipe_log_actions
	 *
	 * @return mixed[]
	 */
	protected function flatten_recipe_log_actions( $recipe_log_actions = array() ) {

		$actions_flattened = array();

		foreach ( $recipe_log_actions as $action_log ) {

			$action_log = wp_parse_args(
				(array) $action_log,
				array(
					'automator_action_id' => null,
					'action_completed'    => null,
					'action_log_id'       => null,
				)
			);

			$action_id = $action_log['automator_action_id'];

			$actions_flattened[ $action_id ] = array(
				'action_completed' => $action_log['action_completed'],
				'action_log_id'    => $action_log['action_log_id'],
			);

		}

		return $actions_flattened;

	}

	/**
	 * Retrieve recipe triggers logs.
	 *
	 * @param array{recipe_id:int, recipe_log_id: int, run_number:int} $params
	 *
	 * @return mixed[]
	*/
	public function get_log( $params ) {

		$actions_flow    = $this->action_logs_queries->get_recipe_actions_flow( $params );
		$flow_conditions = $actions_flow['actions_conditions'];
		$flow            = (array) $actions_flow['flow'];

		// Retrieves the action logs 'raw'. Used for legacy.
		$recipe_log_actions = $this->get_recipe_actions_logs_raw( $params );

		$actions_flattened = $this->flatten_recipe_log_actions( $recipe_log_actions );

		// Set the conditions.
		$this->conditions->set_conditions( is_string( $flow_conditions ) ? $flow_conditions : '' );

		// Hashed conditions is simply an array of conditions with condition ID as key.
		$hashed_conditions = $this->conditions->get_hash_conditions();

		// Serving the action with original flow.
		$item_index = 0;

		foreach ( $flow as $action_flow_key => $action ) {

			if ( ! is_string( $action ) ) {
				$action = '';
			}

			// Check if the current node is simple action or a filter.
			if ( is_string( $action_flow_key ) && ! is_numeric( $action_flow_key ) ) {

				$actions      = explode( ',', $action );
				$action_items = array();

				foreach ( $actions as $action_id ) {

					$action_id            = absint( trim( $action_id ) );
					$resolved_action_item = $this->resolve_action_item( $params, $actions_flattened, $action_id );
					$action_items[]       = $resolved_action_item;

					$this->conditions->set_action_item( $resolved_action_item );
					$predermined_result = $this->conditions->get_conditions_result();

				}

				// Start resolving the fields.
				$this->field_conditions_resolver->set_recipe_id( $params['recipe_id'] );
				// Set the conditions raw props.
				$this->field_conditions_resolver->set_recipe_actions_conditions_raw( $this->conditions->get_conditions() );
				// Set the hashed conditions.
				$this->field_conditions_resolver->set_recipe_actions_conditions(
					array_values(
						$hashed_conditions[ $action_flow_key ]['hashed_conditions'] // // @phpstan-ignore-line Cannot resolve.
					)
				);

				$passed = false;
				if ( is_array( $predermined_result ) && isset( $predermined_result[ $action_flow_key ] ) ) {
					$passed = 'skipped' === $predermined_result[ $action_flow_key ]['status_id'] ? false : true;
				}

				// Resolve conditions.
				$conditions = $this->field_conditions_resolver->resolve( $params, $this->get_parsed_token_record( $params ) );

				// -- Conditions result end.
				$results_formatted[] = array(
					'type'                  => 'filter',
					'id'                    => $action_flow_key,
					'logic'                 => $hashed_conditions[ $action_flow_key ]['mode'], // @phpstan-ignore-line Any or All.
					'conditions'            => $conditions,
					'conditions_fullfilled' => $passed,
					'items'                 => $action_items,
				);

			} else {
				$results_formatted[] = $this->resolve_action_item( $params, $actions_flattened, $action_flow_key );
			}
			$item_index++;
		}

		// Handle legacy.
		if ( empty( $results_formatted ) && ! empty( $recipe_log_actions ) ) {
			$this->append_legacy_results( $results_formatted, $recipe_log_actions, $params, $actions_flattened );
		}

		// Append closures if there are any.
		$this->append_closures_results( $results_formatted, $params );

		// Fetch the loops. Loops are appended at the tail of the actions_items array.
		$loops = $this->loop_log_resources->get_log( $params );
		if ( ! empty( $loops ) ) {
			foreach ( $loops as $loop ) {
				$results_formatted[] = $loop;
			}
		}

		// Actions cannot be empty, unless a fatal error has occured somewhere.
		if ( empty( $results_formatted ) && empty( $recipe_log_actions ) ) {

			// In this case, both legacy and flow is empty. Which could not happen unless there is some sort of error.
			$results_formatted[] = array(
				'from_legacy_log' => empty( $flow ), // Empty flow? Its legacy log.
				'error'           => true,
				'message'         => 'Cannot find any actions. This may be because there are multiple triggers that have not yet been completed, or it could be the result of an unknown error. If you think this is an error, please delete this recipe log and try again.',
				'code'            => 500,
			);
		}

		return $results_formatted;

	}

	/**
	 * Replaces action meta value with the one from uap_action_log_meta if its found.
	 *
	 * @param mixed[] $action_meta
	 * @param int[] $params
	 * @param int $action_id
	 * @param mixed[] $action_log_record
	 *
	 * @return mixed[]
	 */
	private function resolve_action_meta( $action_meta, $params, $action_id, $action_log_record ) {

		// If the action was not found, it means its deleted.
		if ( 'INTEGRATION_CODE_NOT_FOUND' === $action_meta['integration'] ) {
			// Grab the ction log id.
			$action_log_id = isset( $action_log_record['ID'] ) ? $action_log_record['ID'] : null;
			// The existing record is serialize. Unserialized it.
			$action_meta_record = maybe_unserialize( Automator()->db->action->get_meta( $action_log_id, 'metas' ) );
			// Flatten the record to make it compatible with the post meta structure.
			$action_meta_record = $this->utils->flatten_action_log_meta( $action_meta_record );
			// Pass the value of the meta record to action meta if its a valid record.
			if ( is_array( $action_meta_record ) && ! empty( $action_meta_record ) ) {
				// Flag as deleted.
				$action_meta_record['is_deleted'] = true;
				$action_meta                      = $action_meta_record;
			}
		}

		return $action_meta;

	}

}
