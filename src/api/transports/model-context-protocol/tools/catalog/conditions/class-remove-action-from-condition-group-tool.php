<?php
/**
 * MCP catalog tool that detaches actions from a condition group.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal errors, not user-facing output.


use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;

/**
 * Remove Action from Condition Group MCP Tool.
 *
 * MCP tool for removing action(s) from an existing condition group.
 * Provides granular control for modifying condition logic.
 *
 * @since 7.0.0
 */
class Remove_Action_From_Condition_Group_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'remove_action_from_condition_group';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Remove actions from a condition group. Actions will run on every recipe execution after removal.';
	}

	/**
	 * Define the input schema for the remove action from condition group tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for remove action from condition group parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'  => array(
					'type'        => 'integer',
					'description' => 'Recipe ID containing the condition group. Must be an existing recipe.',
					'minimum'     => 1,
				),
				'group_id'   => array(
					'type'        => 'string',
					'description' => 'Condition group ID to remove actions from. Must be an existing group in the recipe.',
					'minLength'   => 1,
				),
				'action_ids' => array(
					'type'        => 'array',
					'description' => 'Array of action IDs to remove from the condition group. Actions must currently be in the group.',
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'minItems'    => 1,
					'uniqueItems' => true,
				),
			),
			'required'   => array( 'recipe_id', 'group_id', 'action_ids' ),
		);
	}

	/**
	 * Validate remove action parameters.
	 *
	 * @since 7.0.0
	 * @param array $params Tool parameters from MCP client.
	 * @return array Validation result with 'success', 'params', or 'error' keys.
	 */
	public function validate_remove_action_params( array $params ): array {
		$recipe_id  = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$group_id   = isset( $params['group_id'] ) ? (string) $params['group_id'] : '';
		$action_ids = isset( $params['action_ids'] ) && is_array( $params['action_ids'] ) ? array_map( 'intval', $params['action_ids'] ) : array();

		if ( $recipe_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'Parameter recipe_id must be a positive integer.',
			);
		}

		if ( '' === $group_id ) {
			return array(
				'success' => false,
				'error'   => 'Parameter group_id is required.',
			);
		}

		if ( empty( $action_ids ) ) {
			return array(
				'success' => false,
				'error'   => 'Provide at least one action_id to remove from the condition group.',
			);
		}

		return array(
			'success' => true,
			'params'  => array(
				'recipe_id'  => $recipe_id,
				'group_id'   => $group_id,
				'action_ids' => $action_ids,
			),
		);
	}

	/**
	 * Execute service remove actions operation.
	 *
	 * @since 7.0.0
	 * @param int    $recipe_id  Recipe ID.
	 * @param string $group_id   Group ID.
	 * @param array  $action_ids Array of action IDs.
	 * @return array Service result.
	 * @throws \Exception If service call fails or returns WP_Error.
	 */
	public function execute_service_remove_actions( int $recipe_id, string $group_id, array $action_ids ): array {
		$condition_service = Recipe_Condition_Service::instance();
		$result            = $condition_service->remove_actions_from_condition_group( $recipe_id, $group_id, $action_ids );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		return $result;
	}

	/**
	 * Build response payload for remove action operation.
	 *
	 * @since 7.0.0
	 * @param array  $result     Service result.
	 * @param int    $recipe_id  Recipe ID.
	 * @param string $group_id   Group ID.
	 * @param array  $action_ids Array of action IDs.
	 * @return array Response payload.
	 */
	public function build_remove_action_response_payload( array $result, int $recipe_id, string $group_id, array $action_ids ): array {
		$payload = array(
			'recipe_id'         => isset( $result['recipe_id'] ) ? (int) $result['recipe_id'] : $recipe_id,
			'group_id'          => $result['group_id'] ?? $group_id,
			'removed_actions'   => $result['removed_actions'] ?? $action_ids,
			'remaining_actions' => $result['remaining_actions'] ?? array(),
			'links'             => $this->build_recipe_links( $recipe_id ),
			'next_steps'        => $this->build_recipe_next_steps( $recipe_id, $group_id ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return $payload;
	}

	/**
	 * Execute the remove action from condition group tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$validation = $this->validate_remove_action_params( $params );
		if ( ! $validation['success'] ) {
			return Json_Rpc_Response::create_error_response( $validation['error'] );
		}

		$validated_params = $validation['params'];

		try {
			$result = $this->execute_service_remove_actions(
				$validated_params['recipe_id'],
				$validated_params['group_id'],
				$validated_params['action_ids']
			);

			$payload = $this->build_remove_action_response_payload(
				$result,
				$validated_params['recipe_id'],
				$validated_params['group_id'],
				$validated_params['action_ids']
			);

			return Json_Rpc_Response::create_success_response(
				sprintf( 'Removed %d action(s) from the condition group', count( $validated_params['action_ids'] ) ),
				$payload
			);

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response(
				'Failed to remove actions from condition group: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Provide backend edit link for the parent recipe.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id Recipe ID.
	 * @return array Recipe links array.
	 */
	public function build_recipe_links( int $recipe_id ): array {
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
	 * Suggest follow-up steps after removing actions from a condition group.
	 *
	 * @since 7.0.0
	 * @param int    $recipe_id Recipe ID.
	 * @param string $group_id  Group ID.
	 * @return array Next steps array.
	 */
	public function build_recipe_next_steps( int $recipe_id, string $group_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$steps                = array();
		$edit                 = get_edit_post_link( $recipe_id, 'raw' );
		$steps['edit_recipe'] = array(
			'admin_url' => is_string( $edit ) ? $edit : '',
			'hint'      => 'Open the recipe editor to ensure the group now contains the desired actions.',
		);

		if ( '' !== $group_id ) {
			$steps['list_conditions'] = array(
				'tool'   => 'list_conditions',
				'params' => array(
					'recipe_id' => $recipe_id,
				),
				'hint'   => 'List condition groups to confirm the remaining actions in this group.',
			);
		}

		return $steps;
	}
}
