<?php
/**
 * MCP tool for listing loops in a recipe.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Loop List Tool.
 *
 * Lists all loops in a recipe.
 *
 * @since 7.0.0
 */
class Loop_List_Tool extends Abstract_MCP_Tool {

	/**
	 * Loop CRUD service.
	 *
	 * @var Loop_CRUD_Service
	 */
	private Loop_CRUD_Service $loop_service;

	/**
	 * Constructor.
	 *
	 * @param Loop_CRUD_Service|null $loop_service Optional loop service instance.
	 */
	public function __construct( ?Loop_CRUD_Service $loop_service = null ) {
		$this->loop_service = $loop_service ?? Loop_CRUD_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'loop_list';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'List all loops in a recipe. Returns loop IDs, types, statuses, and filter counts.';
	}

	/**
	 * Define the input schema.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Recipe ID to list loops from.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters.
	 * @return array MCP response.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$result = $this->loop_service->get_recipe_loops( (int) $params['recipe_id'] );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use list_recipes to verify the recipe exists.'
			);
		}

		$recipe_id = (int) $params['recipe_id'];

		return Json_Rpc_Response::create_success_response(
			'Loops retrieved successfully',
			array(
				'recipe_id'  => $recipe_id,
				'loop_count' => $result['loop_count'] ?? 0,
				'loops'      => $result['loops'] ?? array(),
				'links'      => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
}
