<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * MCP Tool Interface.
 *
 * Defines the contract for MCP tools with automatic schema generation.
 *
 * @since 7.0.0
 */
interface MCP_Tool_Interface {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 *
	 * @return string Tool name.
	 */
	public function get_name();

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 *
	 * @return string Tool description.
	 */
	public function get_description();

	/**
	 * Execute the tool with User_Context and parameters.
	 *
	 * @since 7.0.0
	 *
	 * @param User_Context $user_context The user context for this operation.
	 * @param array        $params       Tool parameters.
	 * @return array Tool execution result.
	 */
	public function execute( User_Context $user_context, array $params );

	/**
	 * Get tool schema (auto-generated from method reflection).
	 *
	 * @since 7.0.0
	 *
	 * @return array MCP tool schema.
	 */
	public function get_schema();
}
