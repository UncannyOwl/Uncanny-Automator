<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Schema_Validator;

/**
 * Abstract MCP Tool.
 *
 * Base class for MCP tools with User_Context integration and automatic schema generation.
 *
 * @since 7.0.0
 */
abstract class Abstract_MCP_Tool implements MCP_Tool_Interface {

	/**
	 * Get tool schema (uses manual schema definition).
	 *
	 * @since 7.0.0
	 *
	 * @return array MCP tool schema.
	 */
	public function get_schema() {
		return array(
			'name'        => $this->get_name(),
			'description' => $this->get_description(),
			'inputSchema' => $this->schema_definition(),
		);
	}

	/**
	 * Define the input schema for this tool.
	 *
	 * Child classes MUST override this method to provide their schema definition.
	 * The schema should match the Value Objects used in the Core API.
	 *
	 * @since 7.0.0
	 *
	 * @return array JSON Schema for tool input parameters.
	 */
	abstract protected function schema_definition();

	/**
	 * Execute the tool with User_Context.
	 *
	 * Tools can modify the executee based on their specific needs before calling execute_tool.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context from MCP auth layer.
	 * @param array        $params       Tool parameters.
	 * @return array Tool execution result.
	 */
	public function execute( User_Context $user_context, array $params ) {
		Schema_Validator::validate_mcp_params( (array) $params, (array) $this->schema_definition() );
		return $this->execute_tool( $user_context, $params );
	}

	/**
	 * Execute the tool with User_Context and typed parameters.
	 *
	 * This method should be implemented by each tool with proper parameter typing
	 * and docblock annotations for automatic schema generation.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context for this operation.
	 * @param array        $params       Tool parameters.
	 * @return array Tool execution result.
	 */
	abstract protected function execute_tool( User_Context $user_context, array $params );


	/**
	 * Require authenticated executor.
	 *
	 * Helper method for tools that require a logged-in user.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context.
	 * @throws \InvalidArgumentException If no authenticated executor.
	 */
	protected function require_authenticated_executor( User_Context $user_context ): void {
		if ( null === $user_context->get_executor() ) {
			throw new \InvalidArgumentException( 'Authenticated user required for this operation' );
		}
	}

	/**
	 * Require user target.
	 *
	 * Helper method for tools that require a target user.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context.
	 * @throws \InvalidArgumentException If no target user specified.
	 */
	protected function require_user_target( User_Context $user_context ): void {
		if ( null === $user_context->get_executee() ) {
			throw new \InvalidArgumentException( 'Target user required for this operation' );
		}
	}
}
