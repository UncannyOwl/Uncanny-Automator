<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources;

use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Automator_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Trigger_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;

class Trigger_Logs_Resources {

	/**
	 * @var Trigger_Logs_Queries
	 */
	protected $trigger_logs_queries = null;

	/**
	 * @var Automator_Factory
	 */
	protected $automator_factory = null;

	/**
	 * @var Formatters_Utils
	 */
	protected $utils = null;

	public function __construct(
		Trigger_Logs_Queries $trigger_logs_queries,
		Formatters_Utils $utils,
		Automator_Factory $automator_factory
		) {

		$this->utils                = $utils;
		$this->trigger_logs_queries = $trigger_logs_queries;
		$this->automator_factory    = $automator_factory;

	}

	public function get_utils() {
		return $this->utils;
	}

	/**
	 * @param mixed[] $recipe
	 *
	 * @return string The trigger logic.
	 */
	public function get_logic( $recipe ) {

		$logic = $this->trigger_logs_queries->get_trigger_logic( $recipe );

		// Fallbacks to live record in case the logic from recipe log meta is missing.
		if ( empty( $logic ) ) {
			$logic = get_post_meta( $recipe['automator_recipe_id'], 'automator_trigger_logic', true );
		}

		if ( ! is_string( $logic ) || empty( $logic ) ) {
			return 'all';
		}

		return $logic;

	}

	/**
	 * @param int[] $params
	 *
	 * @return array<mixed[]>
	 */
	public function get_trigger_runs( $params ) {

		$trigger_runs = array();
		$results      = (array) $this->trigger_logs_queries->trigger_runs_query( $params );

		$utils = $this->get_utils();

		foreach ( $results as $result ) {

			$status_id = $utils::status_class_name(
				$this->automator_factory->status(),
				$result['trigger_completed']
			);

			// Format the trigger run sentence.
			$has_api_log = null !== $this->automator_factory->db_api()->get_by_log_id( 'trigger', $params['recipe_log_id'] );

			$properties = Automator()->db->trigger->get_meta( 'properties', $params['trigger_id'], $params['trigger_log_id'], $result['user_id'] );

			$trigger_runs[] = array(
				'date'        => $utils::date_time_format( $result['trigger_run_time'] ),
				'used_credit' => $has_api_log,
				'status_id'   => $status_id,
				'properties'  => (array) $properties,
			);

		}

		return $trigger_runs;
	}

	/**
	 * Retrieve recipe triggers logs.
	 *
	 * @param int[] $params
	 *
	 * @return mixed[]
	*/
	public function get_log( $params ) {

		$results_formatted   = array();
		$recipe_trigger_logs = (array) $this->trigger_logs_queries->get_recipe_trigger_logs_raw( $params );
		$trigger_fired       = array();

		$utils = $this->get_utils();

		foreach ( $recipe_trigger_logs as $trigger_log_item ) {

			$trigger_id = absint( $trigger_log_item['trigger_log_trigger_id'] );

			$trigger_fired[] = $trigger_id;

			// Get the last element of the array.
			$trigger_meta = $utils::flatten_post_meta( (array) get_post_meta( $trigger_id ) );

			$is_deleted = empty( $trigger_meta );

			$trigger_runs = $this->get_trigger_runs(
				array(
					'recipe_id'      => $params['recipe_id'],
					'run_number'     => $params['run_number'],
					'recipe_log_id'  => $params['recipe_log_id'],
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_item['trigger_log_id'],
				)
			);

			if ( ! empty( $trigger_runs ) ) {
				$last_array_index  = count( $trigger_runs ) - 1;
				$trigger_runs_last = isset( $trigger_runs[ $last_array_index ] )
					? $trigger_runs[ $last_array_index ] :
					null; // Defaults to null.
			}

			$status_id = $utils->status_class_name(
				$this->automator_factory->status(),
				$trigger_log_item['trigger_log_status']
			);

			$fields = $this->trigger_logs_queries->trigger_fields_query(
				array(
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_item['trigger_log_id'],
				)
			);

			// The original $fields has its value separated by option type (e.g. option, options_group).
			$fields = (array) apply_filters( 'automator_log_trigger_items_fields', json_decode( $fields, true ), $params );

			if ( $utils::fields_has_combination_of_options_and_options_group( $fields ) ) {
				$fields = array_unique( array_merge( ...$fields ), SORT_REGULAR );
			}

			$recorded_triggers = $this->trigger_logs_queries->recorded_triggers_query(
				// @phpstan-ignore-next-line The following supplied parameter is ok.
				array(
					'trigger_id'     => $trigger_id,
					'trigger_log_id' => $trigger_log_item['trigger_log_id'],
				)
			);

			$start_date = isset( $trigger_runs[0]['date'] ) ? $trigger_runs[0]['date'] : null;
			$end_date   = isset( $trigger_runs_last['date'] ) ? $trigger_runs_last['date'] : null;
			// Retrieve the user ID from recipe log.
			$user_id = apply_filters( 'automator_field_resolver_condition_result_user_id', null );

			$trigger_object = Automator()->db->trigger->get_meta(
				'trigger_object',
				$trigger_id,
				$trigger_log_item['trigger_log_id'],
				$user_id
			);

			if ( is_array( $trigger_object ) && ! empty( $trigger_object ) ) {
				$trigger_meta = $trigger_object['meta'];
			}

			$trigger_item = array(
				'type'             => 'trigger',
				'id'               => $trigger_id,
				'integration_code' => $trigger_meta['integration'],
				'is_deleted'       => $is_deleted,
				'status_id'        => $status_id,
				'start_date'       => $start_date,
				'end_date'         => $end_date,
				'date_elapsed'     => $utils::get_date_elapsed( $start_date, $end_date ),
				'code'             => $trigger_meta['code'],
				'title_html'       => htmlspecialchars( $this->resolve_trigger_title( $trigger_meta ), ENT_QUOTES ),
				'fields'           => $fields,
				'runs'             => $trigger_runs,
			);

			// No recorded triggers means its from the legacy log.
			if ( empty( $recorded_triggers ) ) {
				$trigger_item['from_legacy_log'] = true;
			}

			$results_formatted[] = $trigger_item;

		} // End for each.

		// The $trigger_id and the $trigger_log_item is the last iteration of the above for loop.
		if ( isset( $trigger_id ) && isset( $trigger_log_item ) ) {
			$this->append_triggers_not_completed( $results_formatted, $trigger_id, $trigger_log_item, $trigger_fired );
		}

		return $results_formatted;

	}

	/**
	 * Resolves the trigger title from human readable html and human readable.
	 *
	 * @param string[] $trigger_meta
	 * @param int $trigger_id
	 *
	 * @return string The trigger title. Would return a blank string if both human_readable and human_redable_html is not found.
	 */
	private function resolve_trigger_title( $trigger_meta = array(), $trigger_id = 0 ) {

		// Use the sentence human readable html if available.
		if ( isset( $trigger_meta['sentence_human_readable_html'] ) ) {
			return $trigger_meta['sentence_human_readable_html'];
		} else {
			// Otherwise, use the non-html format.
			// E.g. "Magic button" trigger does not have 'sentence_human_readable_html'.
			if ( isset( $trigger_meta['sentence_human_readable'] ) ) {
				return $trigger_meta['sentence_human_readable'];
			}
		}

		return sprintf( 'ID: %d (not found)', $trigger_id );

	}

	/**
	 * @param mixed[] $results_formatted
	 * @param int $trigger_id
	 * @param mixed[] $trigger_log_item
	 * @param mixed[] $trigger_fired
	 *
	 * @return mixed[]
	 */
	private function append_triggers_not_completed( &$results_formatted, $trigger_id, $trigger_log_item, $trigger_fired ) {

		$recorded_triggers = $this->trigger_logs_queries->recorded_triggers_query(
			// @phpstan-ignore-next-line The following supplied parameter is ok.
			array(
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_item['trigger_log_id'],
			)
		);

		$utils = $this->get_utils();

		foreach ( $recorded_triggers as $recipe_trigger ) {

			// If recipe trigger is not completed (yet (ALL) or not the one thats fired (ANY)).
			if ( ! in_array( $recipe_trigger, $trigger_fired, true ) ) {

				$trigger_meta = (array) get_post_meta( absint( $recipe_trigger ) );
				$trigger_meta = $utils->flatten_post_meta( $trigger_meta );

				$results_formatted[] = array(
					'type'             => 'trigger',
					'id'               => $recipe_trigger,
					'integration_code' => isset( $trigger_meta['integration'] ) ? $trigger_meta['integration'] : 'NOT_FOUND',
					'is_deleted'       => null, // Trigger didn't even run yet.
					'status_id'        => 'not-completed',
					'start_date'       => null,
					'end_date'         => null,
					'code'             => isset( $trigger_meta['code'] ) ? $trigger_meta['code'] : 'NOT_FOUND',
					'title_html'       => $this->resolve_trigger_title( $trigger_meta, absint( $recipe_trigger ) ),
					'runs'             => array(),
				);
			}
		}

		return $results_formatted;

	}

}
