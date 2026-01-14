<?php
/**
 * MCP catalog tool that creates an empty condition group for a recipe.
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
 * Add Condition Group to Recipe MCP Tool.
 *
 * MCP tool for creating an empty condition group in a recipe.
 * This provides granular control for AI agents to build condition logic step by step.
 *
 * @since 7.0.0
 */
class Create_Condition_Group_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'create_condition_group';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Create a new condition group in a recipe. Returns group_id for adding conditions. WORKFLOW: 1) Create group 2) Add conditions with add_condition 3) Add actions with add_action 4) Link actions to group with add_action_to_condition_group. Step 4 is REQUIRED or actions run unconditionally.';
	}

	/**
	 * Define the input schema for the add condition group tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for add condition group parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Recipe ID to add the condition group to. Must be an existing recipe.',
					'minimum'     => 1,
				),
				'mode'      => array(
					'type'        => 'string',
					'enum'        => array( 'any', 'all' ),
					'default'     => 'any',
					'description' => 'Condition evaluation mode. "any" = OR logic (any condition passes), "all" = AND logic (all conditions must pass). Defaults to "any".',
				),
				'priority'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 100,
					'default'     => 20,
					'description' => 'Group priority for execution order. Higher numbers execute first. Defaults to 20.',
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * Execute the add condition group tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		// Require authenticated executor for recipe modification
		$this->require_authenticated_executor( $user_context );

		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$mode      = isset( $params['mode'] ) ? (string) $params['mode'] : 'any';
		$priority  = isset( $params['priority'] ) ? (int) $params['priority'] : 20;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter recipe_id must be a positive integer.' );
		}

		if ( ! in_array( $mode, array( 'any', 'all' ), true ) ) {
			return Json_Rpc_Response::create_error_response( 'Parameter mode must be either "any" or "all".' );
		}

		if ( $priority < 1 ) {
			$priority = 1;
		}

		try {

			// Use Recipe Condition Service for business logic
			$condition_service = Recipe_Condition_Service::instance();
			$result            = $condition_service->add_empty_condition_group( $recipe_id, $mode, $priority );

			// Transform service result to MCP response
			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}
			$group_id  = (string) ( $result['group_id'] ?? '' );
			$recipe_id = isset( $result['recipe_id'] ) ? (int) $result['recipe_id'] : $recipe_id;

			$payload = array(
				'recipe_id'  => $recipe_id,
				'group_id'   => $group_id,
				'mode'       => $result['mode'] ?? $mode,
				'priority'   => isset( $result['priority'] ) ? (int) $result['priority'] : $priority,
				'links'      => $this->build_recipe_links( $recipe_id ),
				'next_steps' => $this->build_recipe_next_steps( $recipe_id, $group_id ),
			);

			if ( isset( $result['message'] ) && '' !== $result['message'] ) {
				$payload['notes'] = array( $result['message'] );
			}

			return Json_Rpc_Response::create_success_response(
				'Condition group created successfully',
				$payload
			);

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response(
				'Failed to create condition group: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Provide backend edit link for the parent recipe.
	 */
	protected function build_recipe_links( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$edit_link = get_edit_post_link( $recipe_id, 'raw' );
		if ( ! is_string( $edit_link ) || '' === $edit_link ) {
			return array();
		}

		return array( 'edit_recipe' => $edit_link );
	}

	/**
	 * Suggest follow-up actions after creating a condition group.
	 */
	protected function build_recipe_next_steps( int $recipe_id, string $group_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$steps = array();

		$edit_link            = get_edit_post_link( $recipe_id, 'raw' );
		$steps['edit_recipe'] = array(
			'admin_url' => is_string( $edit_link ) ? $edit_link : '',
			'hint'      => 'Open the recipe editor to review the new condition group.',
		);

		if ( '' !== $group_id ) {
			$steps['add_condition'] = array(
				'tool'   => 'add_condition',
				'params' => array(
					'recipe_id' => $recipe_id,
					'group_id'  => $group_id,
				),
				'hint'   => 'Next, add a condition to this group. Call get_component_schema first to understand required fields.',
			);
		}

		return $steps;
	}
}
