<?php
/**
 * MCP catalog tool that retrieves a detailed recipe log entry.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Logs;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Service;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Get Log MCP Tool.
 *
 * MCP tool for retrieving detailed individual recipe execution log entries.
 * Requires specific recipe_id, run_number, and recipe_log_id parameters.
 *
 * @since 7.0.0
 */
class Get_Log_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 *
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'get_log';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 *
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Retrieve a single recipe execution record by recipe_id, run_number, and recipe_log_id. The response includes trigger firings, action outcomes, tokens, and optional profiling data—ideal for debugging failed or unexpected runs.';
	}

	/**
	 * Define the input schema for the get log tool.
	 *
	 * @since 7.0.0
	 *
	 * @return array JSON Schema for get log parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'        => array(
					'type'        => 'integer',
					'description' => 'The recipe ID for the log entry. This identifies which recipe was executed.',
					'minimum'     => 1,
				),
				'run_number'       => array(
					'type'        => 'integer',
					'description' => 'The run number for the log entry. Each recipe execution gets a unique run number.',
					'minimum'     => 1,
				),
				'recipe_log_id'    => array(
					'type'        => 'integer',
					'description' => 'The unique recipe log ID. This is the primary identifier for the specific log entry.',
					'minimum'     => 1,
				),
				'enable_profiling' => array(
					'type'        => 'boolean',
					'description' => 'Enable performance profiling data in the response. Shows execution timing, memory usage, and performance metrics.',
					'default'     => false,
				),
			),
			'required'   => array( 'recipe_id', 'run_number', 'recipe_log_id' ),
		);
	}

	/**
	 * Execute the get log tool.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$recipe_id     = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$run_number    = isset( $params['run_number'] ) ? (int) $params['run_number'] : 0;
		$recipe_log_id = isset( $params['recipe_log_id'] ) ? (int) $params['recipe_log_id'] : 0;

		if ( $recipe_id <= 0 || $run_number <= 0 || $recipe_log_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameters recipe_id, run_number, and recipe_log_id must be positive integers.' );
		}

		$recipe_service = Recipe_Service::instance();

		$result = $recipe_service->get_log(
			$recipe_id,
			$run_number,
			$recipe_log_id,
			! empty( $params['enable_profiling'] )
		);

		// Transform service result to MCP response
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response( 'Recipe log retrieved successfully', $result );
	}
}
