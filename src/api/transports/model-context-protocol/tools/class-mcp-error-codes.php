<?php
/**
 * MCP Error Codes
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

/**
 * MCP Error Codes Class.
 *
 * Contains standard Model Context Protocol error codes as defined in the specification.
 *
 * @since 7.0.0
 */
class MCP_Error_Codes {

	/**
	 * Parse error - Invalid JSON was received.
	 */
	const PARSE_ERROR = -32700;

	/**
	 * Invalid request - The JSON sent is not a valid request object.
	 */
	const INVALID_REQUEST = -32600;

	/**
	 * Method not found - The method does not exist or is not available.
	 */
	const METHOD_NOT_FOUND = -32601;

	/**
	 * Invalid params - Invalid method parameter(s).
	 */
	const INVALID_PARAMS = -32602;

	/**
	 * Internal error - Internal server error.
	 */
	const INTERNAL_ERROR = -32603;

	/**
	 * Custom application error codes start from -32000.
	 * Use this range for application-specific errors.
	 */
	const CUSTOM_ERROR_START = -32000;

	/**
	 * Conflict error - Resource conflict.
	 */
	const CONFLICT = -32008;

	/**
	 * Common application-specific error codes.
	 */
	const AUTH_ERROR         = -32001;
	const PERMISSION_DENIED  = -32002;
	const RESOURCE_NOT_FOUND = -32003;
	const VALIDATION_ERROR   = -32004;
	const EXECUTION_ERROR    = -32005;
	const TIMEOUT_ERROR      = -32006;
	const RATE_LIMIT_ERROR   = -32007;

	/**
	 * Get error code description.
	 *
	 * @since 7.0.0
	 * @param int $code Error code.
	 * @return string Error description.
	 */
	public static function get_description( int $code ): string {
		$descriptions = array(
			self::PARSE_ERROR        => 'Parse error - Invalid JSON was received',
			self::INVALID_REQUEST    => 'Invalid request - The JSON sent is not a valid request object',
			self::METHOD_NOT_FOUND   => 'Method not found - The method does not exist or is not available',
			self::INVALID_PARAMS     => 'Invalid params - Invalid method parameter(s)',
			self::INTERNAL_ERROR     => 'Internal error - Internal server error',
			self::AUTH_ERROR         => 'Authentication error - Invalid or missing credentials',
			self::PERMISSION_DENIED  => 'Permission denied - Insufficient permissions',
			self::RESOURCE_NOT_FOUND => 'Resource not found - Requested resource does not exist',
			self::VALIDATION_ERROR   => 'Validation error - Input validation failed',
			self::EXECUTION_ERROR    => 'Execution error - Action execution failed',
			self::TIMEOUT_ERROR      => 'Timeout error - Operation timed out',
			self::RATE_LIMIT_ERROR   => 'Rate limit error - Too many requests',
		);

		return $descriptions[ $code ] ?? 'Unknown error code';
	}
}
