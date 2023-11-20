<?php

namespace Uncanny_Automator\Rest\Endpoint;

use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Automator_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Logs_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;
use WP_REST_Request;

/**
 * The controller class for the log endpoint.
 *
 * @since 4.12
 * @see <src/core/rest/rest-routes.php>
 *
 */
class Log_Endpoint {

	/**
	 * @var Logs_Factory
	 */
	protected $logs_factory = null;

	/**
	 * @var Automator_Factory
	 */
	protected $automator_factory = null;

	/**
	 * @var Formatters_Utils
	 */
	protected $formatter = null;

	/**
	 * @var mixed[]
	 */
	protected $params = null;

	/**
	 * @param Automator_Factory $automator_factory
	 * @param Logs_Factory $logs_factory
	 */
	public function __construct(
		Automator_Factory $automator_factory,
		Logs_Factory $logs_factory
	) {

		$this->logs_factory      = $logs_factory;
		$this->automator_factory = $automator_factory;

	}

	/**
	 * @param mixed[] $params
	 *
	 * @return void
	 */
	public function set_params( $params = array() ) {
		$this->params = $params;
	}

	/**
	 * @return static
	 */
	public function set_utils( Formatters_Utils $formatter ) {
		$this->formatter = $formatter;

		return $this;
	}

	public function get_formatter() {
		return $this->formatter;
	}

	/**
	 * Retrieve the specific log.
	 *
	 * @template Adapter_Of_Array_Access_Port of \WP_REST_Request
	 * @param Adapter_Of_Array_Access_Port $request The instance of request object from WordPress itself.
	 *
	 * @return mixed[]
	 */
	public function get_log( WP_REST_Request $request ) {

		$params = array(
			'recipe_id'        => absint( $request->get_param( 'recipe_id' ) ),
			'recipe_log_id'    => absint( $request->get_param( 'recipe_log_id' ) ),
			'run_number'       => absint( $request->get_param( 'run_number' ) ),
			'enable_profiling' => absint( $request->get_param( 'enable_profiling' ) ),
		);

		$this->set_params( $params );

		// Cast the parameters with absint.
		$params = array_map( 'absint', $params );

		if ( 1 === $params['enable_profiling'] ) {
			$time_start           = microtime( true );
			$memory_usage_initial = memory_get_usage();
		}

		// Return the response.
		$data = array(
			'success' => true,
			'data'    => $this->build_response( $params ),
		);

		if ( 1 === $params['enable_profiling'] ) {
			$data['_rendering']['memory_usage']   = round( ( memory_get_usage() - $memory_usage_initial ) / 1048576, 4 ) . ' MiB';
			$data['_rendering']['execution_time'] = number_format( microtime( true ) - $time_start, 5 ) . ' seconds';
		}

		return $data;

	}

	/**
	 * @param array{recipe_id:int, recipe_log_id: int, run_number:int} $params
	 *
	 * @return mixed[]
	 */
	public function build_response(

		$params = array(
			'recipe_id'     => 0,
			'recipe_log_id' => 0,
			'run_number'    => 0,
		)
	) {

		$recipe = $this->logs_factory->recipe()->get_log( $params );

		// Pass the user id to the field resolver.
		add_filter(
			'automator_field_resolver_condition_result_user_id',
			function () use ( $recipe ) {
				return isset( $recipe['user_id'] ) ? absint( $recipe['user_id'] ) : 0;
			}
		);

		if ( empty( $recipe ) ) {
			return array();
		}

		// Fetch the triggers items.
		$triggers_items = $this->logs_factory->trigger()->get_log( $params );
		// Fetch the actions items.
		$actions_items = $this->logs_factory->action()->get_log( $params );

		return $this->serve_json( (array) $recipe, (array) $triggers_items, (array) $actions_items );

	}

	/**
	 * @param array<string>|object|void|null $recipe
	 * @param mixed[] $triggers_items
	 * @param mixed[] $flow_items
	 *
	 * @return array<mixed[]>
	 */
	protected function serve_json( $recipe = array(), $triggers_items = array(), $flow_items = array() ) {

		if ( ! is_array( $recipe ) || ! is_array( $triggers_items ) ) {
			return array();
		}

		$formatter = $this->get_formatter();

		$flow_items = $this->flow_items_rearrange_by_date( $flow_items );
		usort( $flow_items, array( $this, 'sort_action_items_by_date' ) );

		$triggered_by_user = absint( $recipe['user_id'] );
		$run_number        = absint( $recipe['run_number'] );
		$start_date        = isset( $triggers_items[0]['start_date'] ) ? $triggers_items[0]['start_date'] : null;
		$status_id         = $this->determine_status_id( $this->automator_factory->status(), $recipe['completed'], $flow_items );
		$recipe_id         = absint( $recipe['automator_recipe_id'] );
		$end_date          = $this->resolve_end_date( $flow_items );
		$title             = trim( $recipe['recipe_title'] );
		$logic             = strtoupper( $this->logs_factory->trigger()->get_logic( $recipe ) );

		$triggers_statuses = array_column( $triggers_items, 'status_id' );

		$has_triggers_not_completed       = in_array( 'not-completed', $triggers_statuses, true );
		$triggers_num_times_not_completed = in_array( 'in-progress', $triggers_statuses, true );

		if ( $has_triggers_not_completed || $triggers_num_times_not_completed ) {
			$end_date = null;
		}

		$json = array(
			'recipe_id'         => $recipe_id,
			'run_number'        => $run_number,
			'title'             => ! empty( $title ) ? $title : sprintf( 'ID: %d (no title)', $recipe_id ),
			'status_id'         => $status_id,
			'start_date'        => $start_date,
			'end_date'          => $end_date,
			'date_elapsed'      => $formatter::get_date_elapsed( $start_date, $end_date ),
			'triggered_by_user' => $triggered_by_user,
			'triggers'          => array(
				'logic' => $logic,
				'items' => $triggers_items,
			),
			'actions'           => array(
				'items' => $flow_items,
			),
			'recipe_edit_url'   => get_edit_post_link( $recipe_id, '&' ),
			'log_delete_url'    => $this->get_delete_url(),
		);

		return apply_filters( 'uncanny_automator_log_serve_json', $json, $recipe, $triggers_items, $flow_items );

	}

	/**
	 * Determines status ID.
	 *
	 * @param \Uncanny_Automator\Automator_Status $status
	 * @param int $recipe_status
	 * @param mixed[] $flow_items
	 *
	 * @return string The status ID.
	 */
	public function determine_status_id( $status, $recipe_status, $flow_items ) {

		$formatter = $this->formatter;

		// The original recipe status.
		$status = $formatter::status_class_name( $status, intval( $recipe_status ) );

		return apply_filters( 'automator_logs_recipe_status', $status, $flow_items );

	}

	/**
	 * @param mixed[] $flow_items
	 *
	 * @return mixed[]
	 */
	protected function flow_items_rearrange_by_date( $flow_items ) {

		$flow_items_rearranged_by_date = array();

		foreach ( $flow_items as $i => $flow_item ) {
			if ( isset( $flow_item['type'] ) && 'filter' === $flow_item['type'] ) { // @phpstan-ignore-line
				$flow_item['_timestamp'] = $flow_item['items'][0]['_timestamp']; // @phpstan-ignore-line False positive for indexed array.
			}
			$flow_items_rearranged_by_date[ $i ] = $flow_item;
		}

		return $flow_items_rearranged_by_date;

	}

	/**
	 * @param mixed[] $flow_items
	 *
	 * @return string|null|false
	 */
	protected function resolve_end_date( $flow_items ) {

		if ( ! isset( $flow_items[ count( $flow_items ) - 1 ]['_timestamp'] ) ) {
			return null;
		}

		// Get the maximum timestamp.
		$max  = $flow_items[ count( $flow_items ) - 1 ]['_timestamp']; // @phpstan-ignore-line False positive for indexed array.
		$date = gmdate( 'Y-m-d H:i:s', absint( $max ) );

		try {

			$dt = new \DateTime( $date );
			$dt->setTimezone( new \DateTimeZone( Automator()->get_timezone_string() ) );

			require_once __DIR__ . '/log-endpoint/utils/formatters-utils.php';

			return Formatters_Utils::date_time_format( $dt->format( 'Y-m-d H:i:s' ) );

		} catch ( \Exception $e ) {
			return 'Cannot identify date from the given timestamp.';
		}

	}

	/**
	 * @param mixed[] $a
	 * @param mixed[] $b
	 *
	 * @return int
	 */
	protected function sort_action_items_by_date( $a, $b ) {

		if ( ! isset( $a['_timestamp'] ) || ! isset( $b['_timestamp'] ) ) {
			return 0;
		}

		if ( $a['_timestamp'] > $b['_timestamp'] ) { // @phpstan-ignore-line Assume correct when sorting already.
			return 1;
		} elseif ( $a['_timestamp'] < $b['_timestamp'] ) { // @phpstan-ignore-line Assume correct when sorting already.
			return - 1;
		}

		return 0;

	}

	/**
	 * @return string The delete URL
	 */
	public function get_delete_url() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$delete_url = sprintf(
			'%s?post_type=%s&page=%s&recipe_id=%d&run_number=%d&recipe_log_id=%d&delete_specific_activity=1&wpnonce='
			. wp_create_nonce( AUTOMATOR_FREE_ITEM_NAME ),
			admin_url( 'edit.php' ),
			'uo-recipe',
			'uncanny-automator-admin-logs',
			absint( $this->params['recipe_id'] ),
			absint( $this->params['run_number'] ),
			absint( $this->params['recipe_log_id'] )
		);

		return apply_filters( 'uncanny_automator_log_get_delete_url', $delete_url, $this->params );

	}

	/**
	 * @return string The download url.
	 */
	public function get_download_url() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$curr_rest_url = strtr(
			get_rest_url( null, 'automator/v1/log/recipe_id/<recipe_id>/run_number/<run_number>/recipe_log_id/<recipe_log_id>' ),
			array(
				'<recipe_id>'     => $this->params['recipe_id'],
				'<run_number>'    => $this->params['run_number'],
				'<recipe_log_id>' => $this->params['recipe_log_id'],
			)
		);

		$download_url = add_query_arg(
			array(
				'as_attachment' => 'yes',
			),
			$curr_rest_url
		);

		return apply_filters( 'uncanny_automator_log_get_download_url', $download_url, $this->params );

	}

}
