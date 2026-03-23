<?php
/**
 * MCP tool for deleting all filters from a loop.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Loop Filter Delete All Tool.
 *
 * Deletes all filters from a loop.
 *
 * @since 7.0.0
 */
class Loop_Filter_Delete_All_Tool extends Abstract_MCP_Tool {

	/**
	 * Filter CRUD service.
	 *
	 * @var Filter_CRUD_Service
	 */
	private Filter_CRUD_Service $filter_service;

	/**
	 * Loop CRUD service.
	 *
	 * @var Loop_CRUD_Service
	 */
	private Loop_CRUD_Service $loop_service;

	/**
	 * Constructor.
	 *
	 * @param Filter_CRUD_Service|null $filter_service Optional filter service instance.
	 * @param Loop_CRUD_Service|null   $loop_service   Optional loop service instance.
	 */
	public function __construct(
		?Filter_CRUD_Service $filter_service = null,
		?Loop_CRUD_Service $loop_service = null
	) {
		$this->filter_service = $filter_service ?? Filter_CRUD_Service::instance();
		$this->loop_service   = $loop_service ?? Loop_CRUD_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'loop_filter_delete_all';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Delete all filters from a loop. Requires confirmation.';
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
					'description' => 'Loop ID to delete all filters from.',
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

		if ( empty( $params['confirm'] ) || true !== $params['confirm'] ) {
			return Json_Rpc_Response::create_error_response(
				'confirm=true is required to delete all filters. Set confirm: true to proceed with deletion.'
			);
		}

		$loop_id = (int) $params['loop_id'];

		$result = $this->filter_service->delete_loop_filters( $loop_id, true );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use loop_get to verify the loop exists, or loop_filter_list to see current filters.'
			);
		}

		// Get recipe_id for links.
		$loop_result = $this->loop_service->get_loop( $loop_id );
		$recipe_id   = 0;
		if ( ! is_wp_error( $loop_result ) && isset( $loop_result['loop']['recipe_id'] ) ) {
			$recipe_id = (int) $loop_result['loop']['recipe_id'];
		}

		return Json_Rpc_Response::create_success_response(
			'All filters deleted from loop successfully',
			array(
				'loop_id'      => $loop_id,
				'filter_count' => $result['deleted_count'] ?? 0,
				'links'        => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
}
