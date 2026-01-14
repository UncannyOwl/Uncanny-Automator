<?php
/**
 * Recipe Log Service
 *
 * Handles all recipe log retrieval and querying operations.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Recipe\Services;

use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Formatter;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Automator_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Recipe_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Trigger_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Action_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Loop_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Recipe_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Trigger_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Loop_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Action_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Action_Logs_Helpers\Conditions_Helper;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Logs_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint;
use Uncanny_Automator\Resolver\Fields_Conditions_Resolver;
use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Status;
use WP_Error;

/**
 * Recipe_Log_Service Class
 *
 * Handles recipe log retrieval using sophisticated logging system.
 */
class Recipe_Log_Service {

	/**
	 * Recipe formatter.
	 *
	 * @var Recipe_Formatter
	 */
	private $formatter;


	/**
	 * Constructor.
	 *
	 * @param Recipe_Formatter|null $formatter Recipe formatter.
	 */
	public function __construct( $formatter = null ) {

		$this->formatter = $formatter ?? new Recipe_Formatter();
	}


	/**
	 * Merge filters with defaults.
	 *
	 * @param array $filters User-provided filters.
	 * @return array Merged filters with defaults.
	 */
	public function merge_filter_defaults( array $filters ): array {
		$defaults = array(
			'limit'        => 20,
			'offset'       => 0,
			'include_meta' => false,
		);
		return array_merge( $defaults, $filters );
	}

	/**
	 * Build WHERE conditions from filters.
	 *
	 * @param array $filters Filters to apply.
	 * @return array Array with 'conditions' and 'values' keys.
	 */
	public function build_where_conditions( array $filters ): array {
		$where_conditions = array( '1=1' );
		$where_values     = array();

		if ( ! empty( $filters['recipe_id'] ) ) {
			$where_conditions[] = 'automator_recipe_id = %d';
			$where_values[]     = (int) $filters['recipe_id'];
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where_conditions[] = 'user_id = %d';
			$where_values[]     = (int) $filters['user_id'];
		}

		if ( isset( $filters['completed'] ) ) {
			$where_conditions[] = 'recipe_completed = %d';
			$where_values[]     = (int) $filters['completed'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where_conditions[] = 'recipe_date_time >= %s';
			$where_values[]     = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where_conditions[] = 'recipe_date_time <= %s';
			$where_values[]     = $filters['date_to'];
		}

		return array(
			'conditions' => $where_conditions,
			'values'     => $where_values,
		);
	}

	/**
	 * Initialize sophisticated logging infrastructure.
	 *
	 * Sets up the complex logging system with queries, resources, and factories.
	 *
	 * @return Log_Endpoint Configured log endpoint instance.
	 */
	public function initialize_logging_infrastructure(): Log_Endpoint {
		global $wpdb;

		// Use the same sophisticated logging system from rest-routes.php
		$utils = new Formatters_Utils();

		$automator_factory = new Automator_Factory(
			Automator_Functions::get_instance(),
			new Automator_Status()
		);

		// Initialize the sophisticated query classes
		$recipe_logs_queries  = new Recipe_Logs_Queries( $wpdb );
		$trigger_logs_queries = new Trigger_Logs_Queries( $wpdb );
		$action_logs_queries  = new Action_Logs_Queries( $wpdb );
		$loop_logs_queries    = new Loop_Logs_Queries( $wpdb );

		// Initialize the sophisticated resource classes
		$recipe_logs_resources  = new Recipe_Logs_Resources( $recipe_logs_queries, $utils, $automator_factory );
		$trigger_logs_resources = new Trigger_Logs_Resources( $trigger_logs_queries, $utils, $automator_factory );
		$loops_logs_resources   = new Loop_Logs_Resources( $loop_logs_queries, $utils, $automator_factory );
		$action_logs_resources  = new Action_Logs_Resources( $action_logs_queries, $utils, $automator_factory, $loops_logs_resources );

		// Set up field conditions resolver
		$fcr = new Fields_Conditions_Resolver();
		$action_logs_resources->set_field_conditions_resolver( $fcr );

		// Set up conditions helper
		$conditions = new Conditions_Helper();
		$action_logs_resources->set_conditions( $conditions );

		// Initialize logs factory
		$logs_factory = new Logs_Factory(
			$recipe_logs_resources,
			$trigger_logs_resources,
			$action_logs_resources,
			$loops_logs_resources
		);

		// Initialize the main log endpoint
		$log_endpoint = new Log_Endpoint( $automator_factory, $logs_factory );
		$log_endpoint->set_utils( $utils );

		return $log_endpoint;
	}

	/**
	 * Create REST request for log retrieval.
	 *
	 * @param int  $recipe_id        Recipe ID.
	 * @param int  $run_number       Run number.
	 * @param int  $recipe_log_id    Recipe log ID.
	 * @param bool $enable_profiling Enable profiling flag.
	 * @return \WP_REST_Request Configured REST request.
	 */
	public function create_log_request( int $recipe_id, int $run_number, int $recipe_log_id, bool $enable_profiling ): \WP_REST_Request {
		$mock_request = new \WP_REST_Request( 'GET' );
		$mock_request->set_param( 'recipe_id', $recipe_id );
		$mock_request->set_param( 'run_number', $run_number );
		$mock_request->set_param( 'recipe_log_id', $recipe_log_id );
		$mock_request->set_param( 'enable_profiling', $enable_profiling ? 1 : 0 );
		return $mock_request;
	}

	/**
	 * Build detailed log response.
	 *
	 * @param array $detailed_log Log data from endpoint.
	 * @return array Formatted response or error.
	 */
	public function build_detailed_log_response( array $detailed_log ) {
		if ( ! empty( $detailed_log['success'] ) ) {
			return array(
				'success' => true,
				'message' => 'Detailed recipe log retrieved successfully',
				'log'     => $detailed_log,
			);
		} else {
			return $this->formatter->error_response( 'recipe_log_not_found', $detailed_log['error']['message'] ?? 'Log not found' );
		}
	}

	/**
	 * Enhance logs with metadata.
	 *
	 * @param array $logs Array of log entries.
	 * @return array Logs enhanced with metadata.
	 */
	public function enhance_logs_with_metadata( array $logs ): array {
		if ( empty( $logs ) ) {
			return $logs;
		}

		foreach ( $logs as &$log ) {
			$log['meta'] = $this->get_log_metadata( intval( $log['recipe_log_id'] ?? 0 ) );
		}

		return $logs;
	}

	/**
	 * Build recipe logs response.
	 *
	 * @param array $logs Array of log entries.
	 * @param int   $total_count Total count of logs.
	 * @param array $filters Applied filters.
	 * @return array Response array.
	 */
	public function build_logs_response( array $logs, int $total_count, array $filters ): array {
		return array(
			'success'    => true,
			'message'    => sprintf( 'Found %d recipe logs', count( $logs ) ),
			'logs'       => $logs,
			'log_count'  => count( $logs ),
			'total_logs' => $total_count,
			'pagination' => array(
				'limit'  => $filters['limit'],
				'offset' => $filters['offset'],
				'total'  => $total_count,
			),
		);
	}

	/**
	 * Get recipe execution logs using sophisticated logging system.
	 *
	 * @param array $filters Optional filters (recipe_id, user_id, completed, limit, offset, include_meta).
	 * @return array|\WP_Error Array of logs on success, WP_Error on failure.
	 */
	public function get_recipe_logs( array $filters = array() ) {

		try {
			// Merge filters with defaults
			$filters = $this->merge_filter_defaults( $filters );

			// Query logs
			$logs = $this->query_recipe_logs_with_filters( $filters );

			// Enhance logs with metadata if requested
			if ( $filters['include_meta'] ) {
				$logs = $this->enhance_logs_with_metadata( $logs );
			}

			// Get total count for pagination
			$total_count = $this->count_recipe_logs_with_filters( $filters );

			return $this->build_logs_response( $logs, $total_count, $filters );

		} catch ( \Exception $e ) {
			return $this->formatter->error_response( 'recipe_logs_query_failed', 'Failed to query recipe logs: ' . $e->getMessage() );
		}
	}


	/**
	 * Get recipe execution logs by recipe ID.
	 *
	 * @param int   $recipe_id          Recipe ID to get logs for.
	 * @param array $additional_filters Additional filters to apply.
	 * @return array|\WP_Error Array of logs on success, WP_Error on failure.
	 */
	public function get_recipe_logs_by_recipe_id( int $recipe_id, array $additional_filters = array() ) {

		$filters = array_merge( $additional_filters, array( 'recipe_id' => $recipe_id ) );

		return $this->get_recipe_logs( $filters );
	}


	/**
	 * Get the most recent recipe log.
	 *
	 * @param array $filters Optional filters to apply before getting most recent.
	 * @return array|\WP_Error Most recent log or error.
	 */
	public function get_most_recent_recipe_log( array $filters = array() ) {

		$filters['limit']  = 1;
		$filters['offset'] = 0;

		$result = $this->get_recipe_logs( $filters );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['logs'] ) ) {
			return $this->formatter->error_response( 'recipe_no_logs_found', 'No recipe logs found' );
		}

		return array(
			'success' => true,
			'message' => 'Most recent recipe log retrieved',
			'log'     => $result['logs'][0],
		);
	}


	/**
	 * Get detailed recipe log entry using sophisticated logging system.
	 *
	 * This method replicates the sophisticated log endpoint functionality
	 * from rest-routes.php for retrieving detailed individual log entries.
	 *
	 * @param int  $recipe_id        Recipe ID.
	 * @param int  $run_number       Run number.
	 * @param int  $recipe_log_id    Recipe log ID.
	 * @param bool $enable_profiling Enable performance profiling.
	 * @return array|\WP_Error Detailed log data or error.
	 */
	public function get_log( int $recipe_id, int $run_number, int $recipe_log_id, bool $enable_profiling = false ) {

		try {
			// Initialize sophisticated logging infrastructure
			$log_endpoint = $this->initialize_logging_infrastructure();

			// Create REST request
			$mock_request = $this->create_log_request( $recipe_id, $run_number, $recipe_log_id, $enable_profiling );

			// Get the detailed log using the sophisticated system
			$detailed_log = $log_endpoint->get_log( $mock_request );

			// Build and return response
			return $this->build_detailed_log_response( $detailed_log );

		} catch ( \Exception $e ) {
			return $this->formatter->error_response( 'recipe_get_log_failed', 'Failed to retrieve detailed log: ' . $e->getMessage() );
		}
	}


	/**
	 * Query recipe logs with filtering using the sophisticated view.
	 *
	 * @param array $filters Filters to apply.
	 * @return array Array of log entries.
	 */
	public function query_recipe_logs_with_filters( array $filters ) {

		global $wpdb;

		// Build WHERE conditions using extracted method
		$where_data       = $this->build_where_conditions( $filters );
		$where_conditions = $where_data['conditions'];
		$where_values     = $where_data['values'];

		$where_clause = implode( ' AND ', $where_conditions );

		// Use the sophisticated view with rich data (user_email, display_name, recipe_title)
		$query          = "SELECT * FROM {$wpdb->prefix}uap_recipe_logs_view WHERE {$where_clause} ORDER BY recipe_date_time DESC LIMIT %d OFFSET %d";
		$where_values[] = (int) $filters['limit'];
		$where_values[] = (int) $filters['offset'];

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * Count recipe logs with filtering for pagination.
	 *
	 * @param array $filters Filters to apply.
	 * @return int Total count of matching logs.
	 */
	private function count_recipe_logs_with_filters( array $filters ) {

		global $wpdb;

		// Build WHERE conditions using extracted method
		$where_data       = $this->build_where_conditions( $filters );
		$where_conditions = $where_data['conditions'];
		$where_values     = $where_data['values'];

		$where_clause = implode( ' AND ', $where_conditions );

		$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}uap_recipe_logs_view WHERE {$where_clause}";

		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * Get metadata for a specific log entry.
	 *
	 * @param int $log_id Log ID to get metadata for.
	 * @return array Array of metadata key-value pairs.
	 */
	private function get_log_metadata( int $log_id ) {

		global $wpdb;

		$meta_query = $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d",
			$log_id
		);

		$meta_results = $wpdb->get_results( $meta_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$metadata     = array();

		foreach ( $meta_results as $meta ) {
			$value = $meta['meta_value'];

			// Try to decode JSON values
			$decoded = json_decode( $value, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				$value = $decoded;
			}

			$metadata[ $meta['meta_key'] ] = $value;
		}

		return $metadata;
	}
}
