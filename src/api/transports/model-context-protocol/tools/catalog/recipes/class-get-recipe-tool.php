<?php
/**
 * MCP catalog tool that returns the full representation of a recipe.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Fetch a full recipe snapshot (metadata, triggers, actions, conditions).
 *
 * This is the canonical "read" endpoint MCP agents call before cloning or editing a recipe.
 *
 * @since 7.0.0
 */
class Get_Recipe_Tool extends Abstract_MCP_Tool {


	/**
	 * Get the name of the tool.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get_recipe';
	}

	/**
	 * Get the description of the tool.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'Get a recipe by ID with complete details: triggers, actions, conditions, and settings.';
	}

	/**
	 * Get the schema definition of the tool.
	 *
	 * @return array
	 */
	public function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the recipe to get.',
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param User_Context $user_context
	 * @param array        $params
	 * @return Json_Rpc_Response
	 */
	public function execute_tool( User_Context $user_context, array $params ) {
		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter recipe_id must be a positive integer. Use list_recipes to find recipe IDs.' );
		}

		try {
			$result = Automator()->get_recipe_object( $recipe_id, ARRAY_A );
			return Json_Rpc_Response::create_success_response( 'Recipe retrieved successfully', $result );
		} catch ( \Uncanny_Automator\Automator_Exception $e ) {
			return Json_Rpc_Response::create_error_response( $e->getMessage() );
		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to retrieve recipe: ' . $e->getMessage() );
		}
	}
}
