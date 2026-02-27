<?php
/**
 * MCP catalog tool that lists condition groups configured on a recipe.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;

/**
 * Get Recipe Conditions MCP Tool.
 *
 * MCP tool for retrieving all condition groups in a recipe.
 * Returns complete condition configuration including individual conditions.
 *
 * @since 7.0.0
 */
class List_Conditions_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'list_conditions';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'List all condition groups in a recipe. Returns each group with its mode (any/all), priority, condition rules, and gated action IDs. Optionally filter by action_id to see only groups affecting that action.';
	}

	/**
	 * Define the input schema for the get recipe conditions tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for get recipe conditions parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Recipe ID to get conditions for. Must be an existing recipe.',
					'minimum'     => 1,
				),
				'action_id' => array(
					'type'        => 'integer',
					'description' => 'Optional. Filter conditions to only those affecting this specific action ID.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * Execute the get recipe conditions tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$action_id = isset( $params['action_id'] ) ? (int) $params['action_id'] : null;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter recipe_id must be a positive integer.' );
		}

		try {
			$condition_service = Recipe_Condition_Service::instance();
			$result            = $condition_service->get_recipe_conditions( $recipe_id );

			// Transform service result to MCP response
			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}

			// Filter by action ID if specified
			if ( null !== $action_id ) {
				$filtered_groups = array_filter(
					$result['condition_groups'],
					function ( $group ) use ( $action_id ) {
						$group_actions = array_map( 'intval', $group['actions'] ?? array() );
						return in_array( $action_id, $group_actions, true );
					}
				);

				$result['condition_groups']   = array_values( $filtered_groups );
				$result['total_groups']       = count( $filtered_groups );
				$result['filtered_by_action'] = $action_id;
			}

			$message = $result['total_groups'] > 0 ?
				sprintf( 'Found %d condition groups for recipe %d', $result['total_groups'], $recipe_id ) :
				sprintf( 'No condition groups found for recipe %d', $recipe_id );

			return Json_Rpc_Response::create_success_response( $message, $result );

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response(
				'Failed to get recipe conditions: ' . $e->getMessage()
			);
		}
	}
}
