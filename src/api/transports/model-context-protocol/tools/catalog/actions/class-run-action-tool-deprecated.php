<?php
/**
 * DEPRECATED: MCP catalog tool that executes a single action via agent classes.
 *
 * This implementation requires actions to have an 'agent_class' registered.
 * Use Run_Action_Tool instead, which uses the Action_Executor (Mother Agent)
 * to execute ANY action via reflection without requiring manual agentification.
 *
 * @package Uncanny_Automator
 * @deprecated 7.0.0 Use Run_Action_Tool with Action_Executor instead.
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Agent_Tools;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Security\Security;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Fields;
use Uncanny_Automator\Api\Components\Action\Factories\Context_Builder;

/**
 * Class Run_Action_Tool_Deprecated
 *
 * @deprecated 7.0.0 Use Run_Action_Tool with Action_Executor instead.
 */
class Run_Action_Tool_Deprecated extends Abstract_MCP_Tool {

	/**
	 * Get name.
	 *
	 * @return mixed
	 */
	public function get_name() {
		return 'run_action_legacy';
	}

	/**
	 * Get description.
	 *
	 * @return mixed
	 */
	public function get_description() {
		return '[DEPRECATED] Execute an action via agent class. Use run_action instead which supports all 500+ actions.';
	}

	/**
	 * Schema definition.
	 *
	 * @return mixed
	 */
	public function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action_code' => array(
					'type'        => 'string',
					'description' => 'Action code to execute. Use search_components to discover actions, then get_component_schema for field requirements.',
				),
				'user_id'     => array(
					'type'        => 'integer',
					'description' => 'User ID to execute the action for. If not provided, uses current logged-in user.',
					'minimum'     => 1,
				),
				'fields'      => array(
					'type'                 => 'object',
					'description'          => 'Field values for the action. Use get_action tool first to see what fields are required/available.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'action_code' ),
		);
	}

	/**
	 * Execute the tool with User_Context.
	 *
	 * Determines the executee based on user_id parameter.
	 * Applies pragmatic security validation.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context from MCP auth layer.
	 * @param array        $params       Tool parameters.
	 * @return array Tool execution result.
	 */
	public function execute( User_Context $user_context, array $params ) {
		// Apply basic sanitization without breaking functionality
		try {
			$sanitized_params = $this->sanitize_params( $params );
		} catch ( \InvalidArgumentException $e ) {
			return Json_Rpc_Response::create_error_response( $e->getMessage() );
		}

		// Determine executee based on tool-specific logic
		$executee = intval( $sanitized_params['user_id'] ?? $user_context->get_executor() );

		// Create new User_Context with tool-specific executee
		$tool_context = new User_Context( $user_context->get_executor(), $executee );

		return $this->execute_tool( $tool_context, $sanitized_params );
	}

	/**
	 * Sanitize tool parameters using Security class.
	 *
	 * Uses pragmatic security without breaking functionality.
	 * Action agents are responsible for their own field validation.
	 *
	 * @since 7.0.0
	 *
	 * @param array $params Raw tool parameters.
	 * @return array Sanitized parameters.
	 */
	private function sanitize_params( array $params ): array {
		// Basic schema validation for required structure
		$basic_schema = array(
			'action_code' => array(
				'type'        => 'string',
				'required'    => true,
				'allow_empty' => false,
				'min_length'  => 1,
				'max_length'  => 200,
			),
			'user_id'     => array(
				'type'     => 'int',
				'required' => false,
				'min'      => 1,
			),
			'fields'      => array(
				'type'     => 'array',
				'required' => false,
			),
		);

		// Validate basic structure
		if ( ! Security::validate_schema( $params, $basic_schema ) ) {
			Security::log_security_event(
				'Run Action Tool: Invalid parameter structure',
				array( 'provided_keys' => array_keys( $params ) )
			);
			throw new \InvalidArgumentException( 'Invalid parameters for action execution' );
		}

		$sanitized = array();

		// Sanitize action_code with basic text sanitization
		if ( isset( $params['action_code'] ) ) {
			$sanitized['action_code'] = sanitize_text_field( $params['action_code'] );
		}

		// Sanitize user_id
		if ( isset( $params['user_id'] ) ) {
			$sanitized['user_id'] = absint( $params['user_id'] );
		}

		// Preserve fields for action agents - use Security::PRESERVE_RAW to avoid modification
		if ( isset( $params['fields'] ) && is_array( $params['fields'] ) ) {
			$sanitized['fields'] = Security::sanitize( $params['fields'], Security::PRESERVE_RAW );
		}

		return $sanitized;
	}

	/**
	 * Execute tool with User_Context.
	 *
	 * @param User_Context $user_context The user context.
	 * @param array        $params       The input parameters.
	 * @return array
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );
		$this->require_user_target( $user_context );

		$action_code = trim( $params['action_code'] ?? '' );
		$fields      = $params['fields'] ?? array();

		if ( ! is_array( $fields ) ) {
			return Json_Rpc_Response::create_error_response( 'Fields must be an array' );
		}

		if ( empty( $action_code ) ) {
			return Json_Rpc_Response::create_error_response( 'Action code is required' );
		}

		// Get action from Automator registry.
		$action = Automator()->get_action( $action_code );

		// Action must exist.
		if ( empty( $action ) ) {
			return Json_Rpc_Response::create_error_response( 'Action not found: ' . $action_code );
		}

		// Action must be agent-compatible.
		if ( empty( $action['agent_class'] ) ) {
			return Json_Rpc_Response::create_error_response( 'Action is not agent-compatible: ' . $action_code );
		}

		// Agent class must exist.
		if ( ! class_exists( $action['agent_class'] ) ) {
			return Json_Rpc_Response::create_error_response( 'Agent class does not exist: ' . $action['agent_class'] );
		}

		// Try to create agent instance.
		try {
			$agent = new $action['agent_class']();
		} catch ( \Exception $e ) {
			// Agent instantiation failed.
			return Json_Rpc_Response::create_error_response( 'Failed to create agent: ' . $e->getMessage() );
		}

		// Agent must implement Agent_Tools interface.
		if ( ! $agent instanceof Agent_Tools ) {
			return Json_Rpc_Response::create_error_response( 'Agent must implement Agent_Tools interface: ' . $action_code );
		}

		// Build execution context using Context_Builder.
		$context = ( new Context_Builder() )
			->with_user( $user_context )
			->with_fields( new Action_Fields( $fields ) )
			->build();

		// Execute the agent.
		$result = $agent->execute( $context );

		// Agent executed successfully.
		if ( $result->is_success() ) {
			$response_data = array(
				'success'     => true,
				'action_code' => $action_code,
				'executor'    => $user_context->get_executor(),
				'executee'    => $user_context->get_executee(),
				'data'        => $result->get_data(),
				'message'     => $result->get_message(),
			);

			return Json_Rpc_Response::create_success_response(
				'Action executed successfully',
				$response_data
			);
		}

		if ( $result->is_failure() ) {
			// Agent executed with failure.
			return Json_Rpc_Response::create_error_response( $result->get_message() );
		}

		// Agent executed with unknown result.
		return Json_Rpc_Response::create_error_response( 'The action agent did not return a success or failure response.' );
	}
}
