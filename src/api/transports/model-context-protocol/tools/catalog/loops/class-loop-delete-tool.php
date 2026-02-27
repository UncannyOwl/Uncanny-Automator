<?php
/**
 * MCP tool for deleting a loop.
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
 * Loop Delete Tool.
 *
 * Deletes a loop and its filters from a recipe.
 *
 * @since 7.0.0
 */
class Loop_Delete_Tool extends Abstract_MCP_Tool {

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
		return 'loop_delete';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Delete a loop and all its filters from a recipe. Actions inside the loop are automatically moved back to the recipe (not deleted). Requires confirmation.';
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
				'loop_id' => array(
					'type'        => 'integer',
					'description' => 'Loop ID to delete.',
					'minimum'     => 1,
				),
				'confirm' => array(
					'type'        => 'boolean',
					'description' => 'Must be true to confirm deletion.',
				),
			),
			'required'   => array( 'loop_id', 'confirm' ),
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

		if ( true !== ( $params['confirm'] ?? null ) ) {
			return Json_Rpc_Response::create_error_response(
				'confirm=true is required to delete a loop. Set confirm: true to proceed with deletion.'
			);
		}

		$result = $this->loop_service->delete_loop( (int) $params['loop_id'], true );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use loop_get with loop_id to verify the loop exists, or loop_list with recipe_id to see available loops.'
			);
		}

		$recipe_id = (int) ( $result['recipe_id'] ?? 0 );

		return Json_Rpc_Response::create_success_response(
			'Loop deleted successfully',
			array(
				'loop_id'   => $result['deleted_loop_id'] ?? (int) $params['loop_id'],
				'recipe_id' => $recipe_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
}
