<?php
/**
 * MCP catalog tool that lists all action instances attached to a recipe.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Get Recipe Actions MCP Tool.
 *
 * MCP tool for retrieving all actions belonging to a specific recipe.
 * Returns complete action instances with their configurations.
 *
 * @since 7.0.0
 */
class List_Actions_Tool extends Abstract_MCP_Tool {

	/**
	 * Action service.
	 *
	 * @var Action_Instance_Service
	 */
	private $action_service;

	/**
	 * Constructor.
	 *
	 * Allows for dependency injection of the action service.
	 */
	public function __construct( ?Action_CRUD_Service $action_service = null ) {
		$this->action_service = $action_service ?? Action_CRUD_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'list_actions';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'List all actions in a recipe. Returns action IDs, codes, fields, and settings. Use to get action_id before calling update_action.';
	}

	/**
	 * Define the input schema for the get recipe actions tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for get recipe actions parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Target recipe ID to retrieve actions from. Must be an existing recipe in the system. Use get_recipes or find_recipes to discover available recipe IDs. This will return all action instances currently configured for this recipe, including their action IDs which can be used with get_action or update_action tools.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * Execute the get recipe actions tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter recipe_id must be a positive integer.' );
		}

		$result = $this->action_service->get_recipe_actions( $recipe_id );

		// Transform service result to MCP response
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response( 'Recipe actions retrieved successfully', $result );
	}
}
