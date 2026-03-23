<?php
/**
 * MCP tool for getting a single loop filter.
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
 * Loop Filter Get Tool.
 *
 * Gets details of a single filter by ID.
 *
 * @since 7.0.0
 */
class Loop_Filter_Get_Tool extends Abstract_MCP_Tool {

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
		return 'loop_filter_get';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Get details of a single loop filter including its code, integration, and field values.';
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
					'description' => 'Filter ID to retrieve.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'filter_id' ),
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
		$result    = $this->filter_service->get_filter( $filter_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use loop_filter_list with loop_id to see available filters.'
			);
		}

		// Get loop_id from filter's post_parent.
		$filter_post = get_post( $filter_id );
		$loop_id     = $filter_post ? (int) $filter_post->post_parent : 0;

		// Get recipe_id for links.
		$recipe_id = 0;
		if ( $loop_id > 0 ) {
			$loop_service = Loop_CRUD_Service::instance();
			$loop_result  = $loop_service->get_loop( $loop_id );
			if ( ! is_wp_error( $loop_result ) && isset( $loop_result['loop']['recipe_id'] ) ) {
				$recipe_id = (int) $loop_result['loop']['recipe_id'];
			}
		}

		return Json_Rpc_Response::create_success_response(
			'Filter retrieved successfully',
			array(
				'filter_id' => $filter_id,
				'filter'    => $result['filter'] ?? array(),
				'loop_id'   => $loop_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
}
