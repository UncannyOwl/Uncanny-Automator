<?php
/**
 * MCP catalog tool that appends a condition to an existing condition group.
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
 * Add Condition to Group MCP Tool.
 *
 * MCP tool for adding an individual condition to an existing condition group.
 * Provides granular control for building condition logic one condition at a time.
 *
 * @since 7.0.0
 */
class Add_Condition_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'add_condition';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Add a condition to a group. Get schema with get_component_schema first, then provide required fields. Returns condition_id.';
	}

	/**
	 * Define the input schema for the add condition to group tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for add condition to group parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'        => array(
					'type'        => 'integer',
					'description' => 'Recipe ID containing the condition group. Must be an existing recipe.',
					'minimum'     => 1,
				),
				'group_id'         => array(
					'type'        => 'string',
					'description' => 'Condition group ID to add the condition to. Must be an existing group in the recipe.',
					'minLength'   => 1,
				),
				'integration_code' => array(
					'type'        => 'string',
					'description' => 'Integration code (e.g., "WP", "GEN", "LD"). Get from find_action_conditions.',
					'minLength'   => 2,
				),
				'condition_code'   => array(
					'type'        => 'string',
					'description' => 'Condition type code (e.g., "TOKEN_MEETS_CONDITION"). Get from find_action_conditions.',
					'minLength'   => 2,
				),
				'fields'           => array(
					'type'                 => 'object',
					'description'          => 'Field configuration for the condition. Use get_action_condition to see required fields.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'recipe_id', 'group_id', 'integration_code', 'condition_code', 'fields' ),
		);
	}

	/**
	 * Validate add condition parameters.
	 *
	 * @since 7.0.0
	 * @param array $params Tool parameters from MCP client.
	 * @return array Validation result with 'success', 'params', or 'error' keys.
	 */
	public function validate_add_params( array $params ): array {
		$recipe_id        = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$group_id         = isset( $params['group_id'] ) ? (string) $params['group_id'] : '';
		$integration_code = isset( $params['integration_code'] ) ? (string) $params['integration_code'] : '';
		$condition_code   = isset( $params['condition_code'] ) ? (string) $params['condition_code'] : '';
		$fields           = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();

		if ( $recipe_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => 'Parameter recipe_id must be a positive integer.',
			);
		}

		if ( '' === $group_id || '' === $integration_code || '' === $condition_code ) {
			return array(
				'success' => false,
				'error'   => 'Parameters group_id, integration_code, and condition_code are required.',
			);
		}

		if ( empty( $fields ) ) {
			return array(
				'success' => false,
				'error'   => 'Provide field configuration for the new condition.',
			);
		}

		return array(
			'success' => true,
			'params'  => array(
				'recipe_id'        => $recipe_id,
				'group_id'         => $group_id,
				'integration_code' => $integration_code,
				'condition_code'   => $condition_code,
				'fields'           => $fields,
			),
		);
	}

	/**
	 * Execute service add condition operation.
	 *
	 * @since 7.0.0
	 * @param int    $recipe_id        Recipe ID.
	 * @param string $group_id         Group ID.
	 * @param string $integration_code Integration code.
	 * @param string $condition_code   Condition code.
	 * @param array  $fields           Field configuration.
	 * @return array Service result.
	 * @throws \Exception If service call fails or returns WP_Error.
	 */
	public function execute_service_add( int $recipe_id, string $group_id, string $integration_code, string $condition_code, array $fields ): array {
		$condition_service = Recipe_Condition_Service::instance();
		$result            = $condition_service->add_condition_to_group( $recipe_id, $group_id, $integration_code, $condition_code, $fields );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( $result->get_error_message() );
		}

		return $result;
	}

	/**
	 * Build response payload for add operation.
	 *
	 * @since 7.0.0
	 * @param array  $result           Service result.
	 * @param int    $recipe_id        Recipe ID.
	 * @param string $group_id         Group ID.
	 * @param string $integration_code Integration code.
	 * @param string $condition_code   Condition code.
	 * @return array Response payload.
	 */
	public function build_add_response_payload( array $result, int $recipe_id, string $group_id, string $integration_code, string $condition_code ): array {
		$payload = array(
			'recipe_id'        => isset( $result['recipe_id'] ) ? (int) $result['recipe_id'] : $recipe_id,
			'group_id'         => $result['group_id'] ?? $group_id,
			'condition_id'     => $result['condition_id'] ?? '',
			'integration'      => $result['integration'] ?? $integration_code,
			'condition_code'   => $result['condition_code'] ?? $condition_code,
			'total_conditions' => isset( $result['total_conditions'] ) ? (int) $result['total_conditions'] : 0,
			'links'            => $this->build_recipe_links( $recipe_id ),
			'next_steps'       => $this->build_recipe_next_steps( $recipe_id, $group_id, $result['condition_id'] ?? '' ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return $payload;
	}

	/**
	 * Execute the add condition to group tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$validation = $this->validate_add_params( $params );
		if ( ! $validation['success'] ) {
			return Json_Rpc_Response::create_error_response( $validation['error'] );
		}

		$validated_params = $validation['params'];

		try {
			$result = $this->execute_service_add(
				$validated_params['recipe_id'],
				$validated_params['group_id'],
				$validated_params['integration_code'],
				$validated_params['condition_code'],
				$validated_params['fields']
			);

			$payload = $this->build_add_response_payload(
				$result,
				$validated_params['recipe_id'],
				$validated_params['group_id'],
				$validated_params['integration_code'],
				$validated_params['condition_code']
			);

			return Json_Rpc_Response::create_success_response(
				'Condition added to group successfully',
				$payload
			);

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response(
				'Failed to add condition to group: ' . $e->getMessage()
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
	 * Suggest follow-up steps after adding a condition.
	 *
	 * @since 7.0.0
	 * @param int    $recipe_id    Recipe ID.
	 * @param string $group_id     Group ID.
	 * @param string $condition_id Condition ID.
	 * @return array Next steps array.
	 */
	public function build_recipe_next_steps( int $recipe_id, string $group_id, string $condition_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$steps                = array();
		$edit                 = get_edit_post_link( $recipe_id, 'raw' );
		$steps['edit_recipe'] = array(
			'admin_url' => is_string( $edit ) ? $edit : '',
			'hint'      => 'Open the recipe editor to review the updated condition group.',
		);

		if ( '' !== $group_id ) {
			$steps['list_conditions'] = array(
				'tool'   => 'list_conditions',
				'params' => array(
					'recipe_id' => $recipe_id,
				),
				'hint'   => 'Fetch condition groups to confirm the new rule is present.',
			);
		}

		if ( '' !== $condition_id ) {
			$steps['update_condition'] = array(
				'tool'   => 'update_condition',
				'params' => array(
					'recipe_id'    => $recipe_id,
					'group_id'     => $group_id,
					'condition_id' => $condition_id,
				),
				'hint'   => 'Call this if you need to adjust field values after inspection.',
			);
		}

		return $steps;
	}
}
