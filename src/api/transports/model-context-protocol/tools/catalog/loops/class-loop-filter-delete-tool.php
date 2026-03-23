<?php
/**
 * MCP tool for deleting a loop filter.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Loop Filter Delete Tool.
 *
 * Deletes a single filter from a loop.
 *
 * @since 7.0.0
 */
class Loop_Filter_Delete_Tool extends Abstract_MCP_Tool {

	/**
	 * Filter CRUD service.
	 *
	 * @var Filter_CRUD_Service
	 */
	private Filter_CRUD_Service $filter_service;

	/**
	 * Constructor.
	 *
	 * @param Filter_CRUD_Service|null $filter_service Optional filter service instance.
	 */
	public function __construct( ?Filter_CRUD_Service $filter_service = null ) {
		$this->filter_service = $filter_service ?? Filter_CRUD_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'loop_filter_delete';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Delete a filter from a loop. Requires confirmation.';
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
				'filter_id' => array(
					'type'        => 'integer',
					'description' => 'Filter ID to delete.',
					'minimum'     => 1,
				),
				'confirm'   => array(
					'type'        => 'boolean',
					'description' => 'Must be true to confirm deletion.',
				),
			),
			'required'   => array( 'filter_id', 'confirm' ),
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

		$filter_id = (int) $params['filter_id'];

		if ( empty( $params['confirm'] ) || true !== $params['confirm'] ) {
			return Json_Rpc_Response::create_error_response(
				'confirm=true is required to delete a filter. Set confirm: true to proceed with deletion.'
			);
		}

		// Get loop_id before deletion for response context.
		$filter_post = get_post( $filter_id );
		$loop_id     = $filter_post ? (int) $filter_post->post_parent : 0;

		// Get recipe_id for links before deletion.
		$recipe_id = 0;
		if ( $loop_id > 0 ) {
			$loop_service = Loop_CRUD_Service::instance();
			$loop_result  = $loop_service->get_loop( $loop_id );
			if ( ! is_wp_error( $loop_result ) && isset( $loop_result['loop']['recipe_id'] ) ) {
				$recipe_id = (int) $loop_result['loop']['recipe_id'];
			}
		}

		$result = $this->filter_service->delete_filter( $filter_id, true );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use loop_filter_get with filter_id to verify the filter exists.'
			);
		}

		return Json_Rpc_Response::create_success_response(
			'Filter deleted successfully',
			array(
				'filter_id'   => $result['deleted_filter_id'] ?? $filter_id,
				'filter_code' => $result['filter_code'] ?? '',
				'loop_id'     => $loop_id,
				'links'       => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
}
