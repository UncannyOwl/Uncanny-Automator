<?php
/**
 * MCP catalog tool that lists recipe execution logs with filters.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Logs;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Service;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Get Recipe Logs MCP Tool.
 *
 * Retrieves Automator recipe execution logs with flexible filtering options.
 * Designed for AI-friendly consumption with consistent structure.
 *
 * Key behaviors:
 * - Always returns an array of logs (even for recent_only = true)
 * - Transforms numeric status codes to human-readable strings
 * - Supports comprehensive filtering: recipe, user, status, date range
 * - Provides pagination for large result sets
 *
 * @since 7.0.0
 */
class Get_Recipe_Logs_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @return string MCP tool identifier.
	 */
	public function get_name() {
		return 'list_logs';
	}

	/**
	 * Get tool description.
	 *
	 * @return string Human-readable tool description.
	 */
	public function get_description() {
		return 'Query recent recipe runs with optional filters for recipe_id, user_id, status, and date range. Use it to build dashboards or locate the log identifiers you\'ll inspect further with ua_get_log.';
	}

	/**
	 * Define input schema for log retrieval.
	 *
	 * @return array JSON Schema for tool parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'    => array(
					'type'        => 'integer',
					'description' => 'Filter logs by recipe ID.',
					'minimum'     => 1,
				),
				'user_id'      => array(
					'type'        => 'integer',
					'description' => 'Filter logs by user ID.',
					'minimum'     => 1,
				),
				'include_meta' => array(
					'type'        => 'boolean',
					'description' => 'Include detailed execution metadata.',
					'default'     => false,
				),
				'date_from'    => array(
					'type'        => 'string',
					'description' => 'Filter logs from this date (YYYY-MM-DD).',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				),
				'date_to'      => array(
					'type'        => 'string',
					'description' => 'Filter logs up to this date (YYYY-MM-DD).',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				),
				'limit'        => array(
					'type'        => 'integer',
					'description' => 'Max number of logs to return.',
					'minimum'     => 1,
					'maximum'     => 100,
					'default'     => 20,
				),
				'offset'       => array(
					'type'        => 'integer',
					'description' => 'Number of logs to skip (for pagination).',
					'minimum'     => 0,
					'default'     => 0,
				),
				'recent_only'  => array(
					'type'        => 'boolean',
					'description' => 'If true, return only the most recent log (still wrapped in array).',
					'default'     => false,
				),
			),
		);
	}

	/**
	 * Execute log retrieval tool.
	 *
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array MCP response with log data.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$service = Recipe_Service::instance();

		$limit  = isset( $params['limit'] ) ? (int) $params['limit'] : 20;
		$offset = isset( $params['offset'] ) ? (int) $params['offset'] : 0;

		$filters = array(
			'limit'        => max( 1, min( 100, $limit ) ),
			'offset'       => max( 0, $offset ),
			'include_meta' => ! empty( $params['include_meta'] ),
		);

		if ( ! empty( $params['recipe_id'] ) ) {
			$filters['recipe_id'] = (int) $params['recipe_id'];
		}
		if ( ! empty( $params['user_id'] ) ) {
			$filters['user_id'] = (int) $params['user_id'];
		}
		if ( ! empty( $params['date_from'] ) ) {
			$filters['date_from'] = $params['date_from'];
		}
		if ( ! empty( $params['date_to'] ) ) {
			$filters['date_to'] = $params['date_to'];
		}

		// Execute query based on recent_only flag
		if ( ! empty( $params['recent_only'] ) ) {
			$log = $service->get_most_recent_recipe_log( $filters );

			if ( is_wp_error( $log ) ) {
				return Json_Rpc_Response::create_error_response( $log->get_error_message() );
			}

			$result = isset( $log['log'] ) && is_array( $log['log'] ) ? array( $log['log'] ) : array();
		} else {
			// Get full log list based on filters
			$result = $service->get_recipe_logs( $filters );

			// Check for WP_Error before accessing array keys
			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}

			$result = $result['logs'] ?? array();
		}

		// Ensure $result is an array before processing
		if ( ! is_array( $result ) ) {
			$result = array();
		}

		// Transform numeric status codes to human-readable strings
		$logs = array_map(
			function ( $entry ) {
				// Ensure $entry is an array
				if ( ! is_array( $entry ) ) {
					return array( 'status' => 'unknown' );
				}
				$status          = $entry['completed'] ?? '';
				$entry['status'] = Automator_Status::get_class_name( $status );
				return $entry;
			},
			$result
		);

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Retrieved %d recipe log(s)', count( $logs ) ),
			$logs
		);
	}
}
