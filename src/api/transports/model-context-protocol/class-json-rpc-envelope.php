<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

/**
 * JSON-RPC 2.0 Envelope.
 *
 * Helper class for creating JSON-RPC 2.0 messages.
 *
 * @since 7.0.0
 */
class Json_Rpc_Envelope {

	/**
	 * JSON-RPC version.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	const JSON_RPC_VERSION = '2.0';

	/**
	 * Create a JSON-RPC request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id     Request ID.
	 * @param string     $method Method name.
	 * @param array      $params Parameters (optional).
	 * @return array JSON-RPC request array.
	 */
	public static function create_request( $id, $method, $params = array() ) {
		$request = array(
			'jsonrpc' => self::JSON_RPC_VERSION,
			'id'      => $id,
			'method'  => $method,
		);

		if ( ! empty( $params ) ) {
			$request['params'] = $params;
		}

		return $request;
	}

	/**
	 * Create a successful JSON-RPC response.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id     Request ID.
	 * @param mixed      $result Result data.
	 * @return array JSON-RPC success response array.
	 */
	public static function create_success_response( $id, $result ) {
		return array(
			'jsonrpc' => self::JSON_RPC_VERSION,
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Create an error JSON-RPC response.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id      Request ID.
	 * @param int|string $code    Error code (will be converted to int).
	 * @param string     $message Error message.
	 * @param mixed      $data    Optional error data.
	 * @return array JSON-RPC error response array.
	 */
	public static function create_error_response( $id, $code, $message, $data = null ) {
		$error_response = array(
			'jsonrpc' => self::JSON_RPC_VERSION,
			'id'      => $id,
			'error'   => array(
				'code'    => self::normalize_error_code( $code ),
				'message' => $message,
			),
		);

		if ( null !== $data ) {
			$error_response['error']['data'] = $data;
		}

		return $error_response;
	}

	/**
	 * Normalize error code to integer for JSON-RPC 2.0 compliance.
	 *
	 * @since 7.0.0
	 *
	 * @param int|string $code Error code.
	 * @return int Normalized error code.
	 */
	private static function normalize_error_code( $code ) {
		// If already an integer, return as-is
		if ( is_int( $code ) ) {
			return $code;
		}

		// Convert string to integer
		if ( is_numeric( $code ) ) {
			return (int) $code;
		}

		// Map common WordPress error codes to JSON-RPC error codes
		$error_code_map = array(
			'mcp_invalid_json_rpc'    => -32600,
			'mcp_invalid_json'        => -32700,
			'mcp_invalid_accept'      => -32602,
			'mcp_invalid_token'       => -32001,
			'mcp_unsupported_version' => -32602,
			'mcp_sse_not_supported'   => -32601,
			'tool_not_found'          => -32601,
			'tool_execution_failed'   => -32603,
		);

		// Return mapped code or default internal error
		return isset( $error_code_map[ $code ] ) ? $error_code_map[ $code ] : -32603;
	}
}
