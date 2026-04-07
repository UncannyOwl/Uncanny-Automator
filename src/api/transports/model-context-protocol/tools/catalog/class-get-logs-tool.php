<?php
/**
 * Consolidated log retrieval tool.
 *
 * Replaces: get_log, list_logs.
 * When recipe_id + run_number + recipe_log_id are all provided, returns single log detail.
 * Otherwise, returns filtered list of logs.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Automator_Status;

/**
 * Get Logs Tool.
 *
 * Single log detail: provide recipe_id + run_number + recipe_log_id.
 * Log list: provide optional filters (recipe_id, user_id, date range, pagination).
 *
 * @since 7.1.0
 */
class Get_Logs_Tool extends Abstract_MCP_Tool {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'get_logs';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Retrieve recipe execution logs. '
			. 'For a single detailed log: provide recipe_id, run_number, and recipe_log_id. '
			. 'For a filtered list: provide optional filters (recipe_id, user_id, date_from, date_to). '
			. 'Use the list to find log identifiers, then retrieve details with all three identity fields.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'        => array(
					'type'        => 'integer',
					'description' => 'Filter by recipe ID, or identify a specific log (with run_number + recipe_log_id).',
					'minimum'     => 1,
				),
				'run_number'       => array(
					'type'        => 'integer',
					'description' => 'Run number for single log detail. Required with recipe_id + recipe_log_id.',
					'minimum'     => 1,
				),
				'recipe_log_id'    => array(
					'type'        => 'integer',
					'description' => 'Recipe log ID for single log detail. Required with recipe_id + run_number.',
					'minimum'     => 1,
				),
				'enable_profiling' => array(
					'type'        => 'boolean',
					'description' => 'Include performance profiling data in single log detail.',
					'default'     => false,
				),
				'user_id'          => array(
					'type'        => 'integer',
					'description' => 'Filter logs by user ID.',
					'minimum'     => 1,
				),
				'include_meta'     => array(
					'type'        => 'boolean',
					'description' => 'Include detailed execution metadata in list results.',
					'default'     => false,
				),
				'date_from'        => array(
					'type'        => 'string',
					'description' => 'Filter logs from this date (YYYY-MM-DD).',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				),
				'date_to'          => array(
					'type'        => 'string',
					'description' => 'Filter logs up to this date (YYYY-MM-DD).',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				),
				'limit'            => array(
					'type'        => 'integer',
					'description' => 'Max logs to return in list mode.',
					'minimum'     => 1,
					'maximum'     => 100,
					'default'     => 20,
				),
				'offset'           => array(
					'type'        => 'integer',
					'description' => 'Logs to skip (pagination).',
					'minimum'     => 0,
					'default'     => 0,
				),
			),
			'required'   => array(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				// Single log detail mode (recipe_id + run_number + recipe_log_id all present).
				'success'    => array( 'type' => 'boolean' ),
				'log'        => array( 'type' => 'object' ),
				// List mode.
				'items'      => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'recipe_log_id' => array( 'type' => 'integer' ),
							'recipe_id'     => array( 'type' => 'integer' ),
							'run_number'    => array( 'type' => 'integer' ),
							'user_id'       => array( 'type' => 'integer' ),
							'status'        => array( 'type' => 'string' ),
							'date_time'     => array( 'type' => 'string' ),
						),
					),
				),
				'total'      => array( 'type' => 'integer' ),
				'pagination' => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$recipe_id     = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$run_number    = isset( $params['run_number'] ) ? (int) $params['run_number'] : 0;
		$recipe_log_id = isset( $params['recipe_log_id'] ) ? (int) $params['recipe_log_id'] : 0;

		// Single log detail: all three identity fields provided.
		if ( $recipe_id > 0 && $run_number > 0 && $recipe_log_id > 0 ) {
			return $this->get_single_log( $recipe_id, $run_number, $recipe_log_id, $params );
		}

		// List mode.
		return $this->list_logs( $params );
	}

	// ──────────────────────────────────────────────────────────────────
	// SINGLE LOG — port of Get_Log_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Retrieve a single detailed log entry.
	 *
	 * @param int   $recipe_id     Recipe ID.
	 * @param int   $run_number    Run number.
	 * @param int   $recipe_log_id Recipe log ID.
	 * @param array $params        Full params for profiling flag.
	 * @return array JSON-RPC response.
	 */
	private function get_single_log( int $recipe_id, int $run_number, int $recipe_log_id, array $params ): array {

		$service = Recipe_Service::instance();

		$result = $service->get_log(
			$recipe_id,
			$run_number,
			$recipe_log_id,
			! empty( $params['enable_profiling'] )
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		// Strip sensitive HTTP headers (cookies, auth tokens) from log detail.
		$result = $this->redact_sensitive_log_data( $result );

		return Json_Rpc_Response::create_success_response( 'Recipe log retrieved successfully', $result );
	}

	// ──────────────────────────────────────────────────────────────────
	// LOG LIST — port of Get_Recipe_Logs_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────

	/**
	 * List logs with filters.
	 *
	 * @param array $params Tool parameters.
	 * @return array JSON-RPC response.
	 */
	private function list_logs( array $params ): array {

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

		$result = $service->get_recipe_logs( $filters );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$logs       = $result['logs'] ?? array();
		$total_logs = $result['total_logs'] ?? count( $logs );
		$pagination = $result['pagination'] ?? null;

		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Transform numeric status codes to human-readable strings.
		$logs = array_map(
			function ( $entry ) {
				if ( ! is_array( $entry ) ) {
					return array( 'status' => 'unknown' );
				}
				$status          = $entry['completed'] ?? '';
				$entry['status'] = Automator_Status::get_class_name( $status );
				return $entry;
			},
			$logs
		);

		$response_data = array(
			'items' => $logs,
			'total' => (int) $total_logs,
		);

		if ( null !== $pagination ) {
			$response_data['pagination'] = $pagination;
		}

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Retrieved %d of %d recipe log(s)', count( $logs ), (int) $total_logs ),
			$response_data
		);
	}

	/**
	 * Recursively redact sensitive HTTP data from log entries.
	 *
	 * Trigger logs can capture raw request headers including auth cookies and
	 * session tokens. These are useful for server-side debugging but must not
	 * be exposed through the MCP transport to AI agents.
	 *
	 * @param mixed $data Log data (array or scalar).
	 *
	 * @return mixed Data with sensitive values replaced by '[REDACTED]'.
	 */
	private function redact_sensitive_log_data( $data ) {

		if ( ! is_array( $data ) ) {
			return $data;
		}

		$sensitive_keys = array( 'cookie', 'cookies', 'set-cookie', 'authorization', 'x-automator-creds' );

		foreach ( $data as $key => &$value ) {
			if ( in_array( strtolower( (string) $key ), $sensitive_keys, true ) ) {
				$value = '[REDACTED]';
				continue;
			}

			if ( is_array( $value ) ) {
				$value = $this->redact_sensitive_log_data( $value );
			}
		}

		return $data;
	}
}
