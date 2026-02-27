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
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
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
	 * @var Action_CRUD_Service
	 */
	private $action_service;

	/**
	 * Loop service.
	 *
	 * @var Loop_CRUD_Service
	 */
	private $loop_service;

	/**
	 * Constructor.
	 *
	 * Allows for dependency injection of the action service.
	 *
	 * @param Action_CRUD_Service|null $action_service Action CRUD service.
	 * @param Loop_CRUD_Service|null   $loop_service   Loop CRUD service.
	 */
	public function __construct( ?Action_CRUD_Service $action_service = null, ?Loop_CRUD_Service $loop_service = null ) {
		$this->action_service = $action_service ?? Action_CRUD_Service::instance();
		$this->loop_service   = $loop_service ?? Loop_CRUD_Service::instance();
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
		return 'List all actions in a recipe, including actions inside loops. Returns action IDs, codes, fields, settings, and loop context (loop_id, loop_type) for loop actions. Use to get action_id before calling update_action.';
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

		// Get top-level recipe actions.
		$result = $this->action_service->get_recipe_actions( $recipe_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		// Add loop context to top-level actions (they have no loop context).
		$all_actions = array();
		foreach ( $result['actions'] ?? array() as $action ) {
			$action['loop_id']   = null;
			$action['loop_type'] = null;
			$all_actions[]       = $action;
		}

		// Get loops in this recipe and their actions.
		$loops_result = $this->loop_service->get_recipe_loops( $recipe_id );

		if ( ! is_wp_error( $loops_result ) && ! empty( $loops_result['loops'] ) ) {
			foreach ( $loops_result['loops'] as $loop ) {
				$loop_id   = (int) ( $loop['id'] ?? 0 );
				$loop_type = $loop['iterable_expression']['type'] ?? 'unknown';

				// Get actions inside this loop.
				$loop_actions = $this->get_loop_actions( $loop_id );

				foreach ( $loop_actions as $action ) {
					$action['loop_id']   = $loop_id;
					$action['loop_type'] = $loop_type;
					$all_actions[]       = $action;
				}
			}
		}

		$response_data = array(
			'success'      => true,
			'message'      => 'Actions retrieved successfully.',
			'recipe_id'    => $recipe_id,
			'action_count' => count( $all_actions ),
			'actions'      => $all_actions,
		);

		return Json_Rpc_Response::create_success_response( 'Recipe actions retrieved successfully', $response_data );
	}

	/**
	 * Get actions inside a loop.
	 *
	 * @param int $loop_id Loop ID.
	 * @return array Array of action data.
	 */
	private function get_loop_actions( int $loop_id ): array {
		$actions = get_posts(
			array(
				'post_type'      => 'uo-action',
				'post_parent'    => $loop_id,
				'post_status'    => array( 'draft', 'publish' ),
				'posts_per_page' => 100,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		if ( empty( $actions ) ) {
			return array();
		}

		$formatted_actions = array();

		foreach ( $actions as $action_post ) {
			$formatted_actions[] = $this->format_action_post( $action_post );
		}

		return $formatted_actions;
	}

	/**
	 * Format action post to array.
	 *
	 * @param \WP_Post $post Action post.
	 * @return array Formatted action data.
	 */
	private function format_action_post( \WP_Post $post ): array {
		$action_id   = $post->ID;
		$code        = get_post_meta( $action_id, 'code', true );
		$integration = get_post_meta( $action_id, 'integration', true );

		// Get all meta for config.
		$all_meta    = get_post_meta( $action_id );
		$core_fields = array( 'integration', 'code', 'meta_code', 'user_type', 'parent_id', 'status', 'recipe_id' );
		$config      = array();

		foreach ( $all_meta as $key => $value_array ) {
			if ( in_array( $key, $core_fields, true ) ) {
				continue;
			}
			$config[ $key ] = $value_array[0] ?? '';
		}

		return array(
			'action_id'   => $action_id,
			'action_code' => $code,
			'integration' => $integration,
			'status'      => $post->post_status,
			'config'      => $config,
		);
	}
}
