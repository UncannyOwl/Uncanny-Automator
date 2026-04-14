<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Shared\Traits\Empty_Array_To_Object;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Schema_Validator;

/**
 * Abstract MCP Tool.
 *
 * Base class for MCP tools with User_Context integration and automatic schema generation.
 *
 * @since 7.0.0
 */
abstract class Abstract_MCP_Tool implements MCP_Tool_Interface {

	use Empty_Array_To_Object;

	/**
	 * Get tool schema (uses manual schema definition).
	 *
	 * @since 7.0.0
	 *
	 * @return array MCP tool schema.
	 */
	public function get_schema() {
		$schema = array(
			'name'        => $this->get_name(),
			'description' => $this->get_description(),
			'inputSchema' => $this->schema_definition(),
			'annotations' => $this->get_annotations(),
		);

		$output = $this->output_schema_definition();
		if ( null !== $output ) {
			$schema['outputSchema'] = array(
				'type'       => 'object',
				'properties' => array(
					'message' => array( 'type' => 'string' ),
					'data'    => $output,
				),
				'required'   => array( 'message', 'data' ),
			);
		}

		return $schema;
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
	 * Get MCP 2025-03-26 tool annotations.
	 *
	 * Default: read-only, non-destructive, idempotent. Override in tools that
	 * write data (save_*, execute, delete).
	 *
	 * @since 7.1.0
	 *
	 * @return array Tool annotations.
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'idempotentHint'  => true,
			'openWorldHint'   => true,
		);
	}

	/**
	 * Define the output schema for this tool's response data.
	 *
	 * Returns the JSON Schema for the `data` field in the response envelope.
	 * The base class wraps this in `{ message: string, data: <this schema> }`.
	 *
	 * Override in child classes. Return null to omit outputSchema from tools/list.
	 *
	 * @since 7.1.0
	 *
	 * @return array|null JSON Schema for tool output data, or null.
	 */
	protected function output_schema_definition(): ?array {
		return null;
	}

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
		$this->require_authenticated_executor( $user_context );
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
	 * Require an admin executor.
	 *
	 * The MCP server is an admin-only surface. Every tool must reject callers
	 * that either (a) have no authenticated executor or (b) are not administrators.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context.
	 * @throws \InvalidArgumentException If the executor is missing or lacks admin capability.
	 */
	protected function require_authenticated_executor( User_Context $user_context ): void {
		$executor = $user_context->get_executor();

		if ( null === $executor ) {
			throw new \InvalidArgumentException( 'Authenticated user required for this operation' );
		}

		if ( ! user_can( $executor, 'manage_options' ) ) {
			throw new \InvalidArgumentException( 'Administrator capability required for this operation' );
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

	/**
	 * Parse a JSON parameter that may arrive as a string.
	 *
	 * Some MCP clients send object/array parameters as JSON strings rather than
	 * native objects. This method normalises the value to a PHP array. When the
	 * standard decode fails it retries after replacing single quotes with double
	 * quotes — a common client mistake.
	 *
	 * @since 7.1.0
	 *
	 * @param mixed $param Parameter value (string, array, or other).
	 * @return array Parsed array, or empty array on failure.
	 */
	protected function parse_json_param( $param ): array {
		if ( is_string( $param ) ) {
			$decoded = json_decode( $param, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				// Retry with single-quote → double-quote replacement (common client mistake).
				$decoded = json_decode( str_replace( "'", '"', $param ), true );
			}
			return ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) ? $decoded : array();
		}
		return is_array( $param ) ? $param : array();
	}
}
