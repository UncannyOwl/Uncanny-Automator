<?php
/**
 * MCP catalog tool that updates metadata for a condition group.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal errors, not user-facing output.

/**
 * Update Condition Group MCP Tool.
 *
 * MCP tool for updating settings of an existing condition group.
 * Allows modification of mode (any/all) and priority without affecting actions or conditions.
 *
 * @since 7.0.0
 */
class Update_Condition_Group_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'update_condition_group';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Update a condition group. Change evaluation mode or priority.';
	}

	/**
	 * Define the input schema for the update condition group tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for update condition group parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Recipe ID containing the condition group. Must be an existing recipe.',
					'minimum'     => 1,
				),
				'group_id'  => array(
					'type'        => 'string',
					'description' => 'Condition group ID to update. Must be an existing group in the recipe.',
					'minLength'   => 1,
				),
				'mode'      => array(
					'type'        => 'string',
					'enum'        => array( 'any', 'all' ),
					'description' => 'Condition evaluation mode. "any" = OR logic (any condition passes), "all" = AND logic (all conditions must pass). Optional - only update if provided.',
				),
				'priority'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 100,
					'description' => 'Group priority for execution order. Higher numbers execute first. Optional - only update if provided.',
				),
			),
			'required'   => array( 'recipe_id', 'group_id' ),
		);
	}

	/**
	 * Validate update parameters.
	 *
	 * @since 7.0.0
	 * @param array $params Tool parameters from MCP client.
	 * @return array Validation result with 'success', 'params', or 'error' keys.
	 */
	public function validate_update_params( array $params ): array {
		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$group_id  = isset( $params['group_id'] ) ? (string) $params['group_id'] : '';
		$mode      = isset( $params['mode'] ) ? (string) $params['mode'] : null;
		$priority  = isset( $params['priority'] ) ? (int) $params['priority'] : null;

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

		if ( null === $mode && null === $priority ) {
			return array(
				'success' => false,
				'error'   => 'Provide at least one update parameter: mode or priority.',
			);
		}

		if ( null !== $mode && ! in_array( $mode, array( 'any', 'all' ), true ) ) {
			return array(
				'success' => false,
				'error'   => 'Parameter mode must be either "any" or "all".',
			);
		}

		// Normalize priority to minimum of 1
		if ( null !== $priority && $priority < 1 ) {
			$priority = 1;
		}

		return array(
			'success' => true,
			'params'  => array(
				'recipe_id' => $recipe_id,
				'group_id'  => $group_id,
				'mode'      => $mode,
				'priority'  => $priority,
			),
		);
	}

	/**
	 * Execute service update for condition group.
	 *
	 * @since 7.0.0
	 * @param int         $recipe_id Recipe ID.
	 * @param string      $group_id  Group ID.
	 * @param string|null $mode      Mode (any/all) or null.
	 * @param int|null    $priority  Priority or null.
	 * @return array Service result.
	 * @throws \Exception If service call fails or returns WP_Error.
	 */
	public function execute_service_update( int $recipe_id, string $group_id, $mode, $priority ): array {
		$condition_service = Recipe_Condition_Service::instance();
		$result            = $condition_service->update_condition_group( $recipe_id, $group_id, $mode, $priority );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		return $result;
	}

	/**
	 * Build response payload for update operation.
	 *
	 * @since 7.0.0
	 * @param array       $result    Service result.
	 * @param int         $recipe_id Recipe ID.
	 * @param string      $group_id  Group ID.
	 * @param string|null $mode      Mode value.
	 * @param int|null    $priority  Priority value.
	 * @return array Response payload.
	 */
	public function build_update_response_payload( array $result, int $recipe_id, string $group_id, $mode, $priority ): array {
		$payload = array(
			'recipe_id'  => isset( $result['recipe_id'] ) ? (int) $result['recipe_id'] : $recipe_id,
			'group_id'   => $result['group_id'] ?? $group_id,
			'mode'       => $result['mode'] ?? $mode,
			'priority'   => isset( $result['priority'] ) ? (int) $result['priority'] : $priority,
			'updated'    => $result['updated_fields'] ?? array(),
			'links'      => $this->build_recipe_links( $recipe_id ),
			'next_steps' => $this->build_recipe_next_steps( $recipe_id, $group_id ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return $payload;
	}

	/**
	 * Execute the update condition group tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$validation = $this->validate_update_params( $params );
		if ( ! $validation['success'] ) {
			return Json_Rpc_Response::create_error_response( $validation['error'] );
		}

		$validated_params = $validation['params'];

		try {
			$result = $this->execute_service_update(
				$validated_params['recipe_id'],
				$validated_params['group_id'],
				$validated_params['mode'],
				$validated_params['priority']
			);

			$payload = $this->build_update_response_payload(
				$result,
				$validated_params['recipe_id'],
				$validated_params['group_id'],
				$validated_params['mode'],
				$validated_params['priority']
			);

			return Json_Rpc_Response::create_success_response(
				'Condition group updated successfully',
				$payload
			);

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response(
				'Failed to update condition group: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Build recipe links for response.
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
	 * Build next steps suggestions for response.
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
			'hint'      => 'Open the recipe editor to confirm the group updates.',
		);

		if ( '' !== $group_id ) {
			$steps['list_conditions'] = array(
				'tool'   => 'list_conditions',
				'params' => array(
					'recipe_id' => $recipe_id,
				),
				'hint'   => 'List condition groups to verify the new mode/priority.',
			);
		}

		return $steps;
	}
}
