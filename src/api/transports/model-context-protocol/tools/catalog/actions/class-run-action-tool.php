<?php
/**
 * MCP tool that executes ANY Automator action via reflection.
 *
 * Uses Action_Executor (Mother Agent) to run all 337+ actions without
 * requiring manual agentification. First to market.
 *
 * @package Uncanny_Automator
 * @since   7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Security\Security;
use Uncanny_Automator\Api\Application\Sub_Tooling\Action_Executor;

/**
 * Class Run_Action_Tool
 *
 * Executes any registered Automator action via the Action_Executor.
 * Supports all 4 action styles: App_Action, Abstract Action, Trait-based, Legacy.
 *
 * @since 7.0.0
 */
class Run_Action_Tool extends Abstract_MCP_Tool {

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'run_action';
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return 'Execute any Automator action immediately. Supports all 337+ actions across all integrations. Use search_components to find actions, get_component_schema for required fields, then call with action_code and fields. Returns success/error status plus any action tokens (output values).';
	}

	/**
	 * Schema definition.
	 *
	 * @return array
	 */
	public function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action_code' => array(
					'type'        => 'string',
					'description' => 'Action code to execute (e.g., SENDEMAIL, SLACKSENDMESSAGE). Use search_components to discover actions.',
				),
				'user_id'     => array(
					'type'        => 'integer',
					'description' => 'User ID context for the action. Defaults to current authenticated user.',
					'minimum'     => 0,
				),
				'fields'      => array(
					'type'                 => 'object',
					'description'          => 'Field values for the action (most actions require these). Keys must match option_code from get_component_schema.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'action_code' ),
		);
	}

	/**
	 * Execute the tool with User_Context.
	 *
	 * @param User_Context $user_context The user context from MCP auth layer.
	 * @param array        $params       Tool parameters.
	 *
	 * @return array Tool execution result.
	 */
	public function execute( User_Context $user_context, array $params ) {

		try {
			$sanitized_params = $this->sanitize_params( $params );
		} catch ( \InvalidArgumentException $e ) {
			return Json_Rpc_Response::create_error_response( $e->getMessage() );
		}

		// Determine user context - prefer explicit user_id, fallback to executee, then executor.
		$user_id = intval( $sanitized_params['user_id'] ?? $user_context->get_executee() ?? $user_context->get_executor() );

		// Create tool-specific context.
		$tool_context = new User_Context( $user_context->get_executor(), $user_id );

		return $this->execute_tool( $tool_context, $sanitized_params );
	}

	/**
	 * Sanitize tool parameters.
	 *
	 * @param array $params Raw parameters.
	 *
	 * @return array Sanitized parameters.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	private function sanitize_params( array $params ): array {

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
				'min'      => 0,
			),
			'fields'      => array(
				'type'     => 'array',
				'required' => false,
			),
		);

		if ( ! Security::validate_schema( $params, $basic_schema ) ) {
			Security::log_security_event(
				'Run Action Tool: Invalid parameter structure',
				array( 'provided_keys' => array_keys( $params ) )
			);
			throw new \InvalidArgumentException( 'Invalid parameters for action execution' );
		}

		$sanitized = array();

		if ( isset( $params['action_code'] ) ) {
			$sanitized['action_code'] = sanitize_text_field( $params['action_code'] );
		}

		if ( isset( $params['user_id'] ) ) {
			$sanitized['user_id'] = absint( $params['user_id'] );
		}

		// Preserve fields - action executor handles validation.
		if ( isset( $params['fields'] ) && is_array( $params['fields'] ) ) {
			$sanitized['fields'] = Security::sanitize( $params['fields'], Security::PRESERVE_RAW );
		}

		return $sanitized;
	}

	/**
	 * Execute tool via Action_Executor.
	 *
	 * @param User_Context $user_context The user context.
	 * @param array        $params       The input parameters.
	 *
	 * @return array
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$action_code = trim( $params['action_code'] ?? '' );
		$fields      = $params['fields'] ?? array();
		$user_id     = $user_context->get_executee();

		if ( empty( $action_code ) ) {
			return Json_Rpc_Response::create_error_response( 'Action code is required' );
		}

		if ( ! is_array( $fields ) ) {
			return Json_Rpc_Response::create_error_response( 'Fields must be an object' );
		}

		// Validate Automator is initialized before execution.
		if ( ! function_exists( 'Automator' ) ) {
			return Json_Rpc_Response::create_error_response( 'Automator not initialized' );
		}

		// Execute via Action_Executor (Mother Agent).
		$executor = new Action_Executor();
		$result   = $executor->run( $action_code, $fields, $user_id );

		// Handle WP_Error from executor validation.
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		// Build response.
		if ( ! empty( $result['success'] ) ) {
			$response_data = array(
				'success'     => true,
				'action_code' => $action_code,
				'executor'    => $user_context->get_executor(),
				'executee'    => $user_id,
				'data'        => $result['data'] ?? array(),
			);

			// Include action tokens if present.
			if ( ! empty( $result['tokens'] ) ) {
				$response_data['tokens'] = $result['tokens'];
			}

			return Json_Rpc_Response::create_success_response(
				'Action executed successfully',
				$response_data
			);
		}

		// Action failed - return error.
		$fallback_error = sprintf( "Action '%s' failed to run. Please check that the action's integration is active and that any required apps or plugins are connected, authorized to work with Automator, and enabled.", $action_code );
		$error_message  = $result['error'] ?? $fallback_error;

		return Json_Rpc_Response::create_error_response( $error_message );
	}
}
