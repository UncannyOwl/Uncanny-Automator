<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

/**
 * JSON-RPC Response.
 *
 * Helper class for creating JSON-RPC responses.
 *
 * @since 7.0.0
 */
class Json_Rpc_Response {

	/**
	 * Create a success response.
	 *
	 * @param string     $message Success message.
	 * @param array|null $data    Optional response data.
	 * @return array MCP success response.
	 */
	public static function create_success_response( string $message, ?array $data = null ): array {

		$content = array(
			'type' => 'text',
			'text' => wp_json_encode(
				array(
					'message' => $message,
				)
			),
		);

		if ( $data ) {
			$content['text'] = wp_json_encode(
				array(
					'message' => $message,
					'data'    => $data,
				)
			);
		}

		return array(
			'content' => array(
				$content,
			),
		);
	}

	/**
	 * Create an error response.
	 *
	 * @param string     $message Error message.
	 * @param array|null $data    Optional error data.
	 * @return array MCP error response.
	 */
	public static function create_error_response( string $message, ?array $data = null ): array {

		$content = array(
			'type' => 'text',
			'text' => wp_json_encode(
				array(
					'message' => $message,
				)
			),
		);

		if ( $data ) {
			$content['text'] = wp_json_encode(
				array(
					'message' => $message,
					'data'    => $data,
				)
			);
		}

		return array(
			'isError' => true,
			'content' => array(
				$content,
			),
		);
	}
}
