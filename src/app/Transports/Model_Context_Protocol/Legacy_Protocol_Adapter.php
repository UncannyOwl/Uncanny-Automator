<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Authenticated_Token_Context;
use WP_Error;
use WP_REST_Request;

/**
 * Stateless MCP 2025-11-25 compatibility adapter.
 */
final class Legacy_Protocol_Adapter {

	private Message_Router $message_router;

	/**
	 * Build the adapter around the shared MCP tool router.
	 */
	public function __construct( ?Message_Router $message_router = null ) {
		$this->message_router = $message_router ?? new Message_Router();
	}

	/**
	 * Route one initialize-era request through the shared tool core.
	 *
	 * @return array|WP_Error
	 */
	public function route_message( array $message, WP_REST_Request $request, ?Authenticated_Token_Context $context = null ) {
		if ( ! $this->is_valid_json_rpc_message( $message ) ) {
			return new WP_Error( 'mcp_invalid_json_rpc', 'Invalid JSON-RPC request.', array( 'status' => 400 ) );
		}

		$id     = $message['id'] ?? null;
		$method = $message['method'];

		if ( 'initialize' === $method ) {
			return $this->initialize( $message );
		}

		if ( null === $id ) {
			return array( 'status' => 'accepted' );
		}

		if ( 'ping' === $method ) {
			return $this->success_response( $id, new \stdClass() );
		}

		if ( ! in_array( $method, array( 'tools/list', 'tools/call' ), true ) ) {
			return Json_Rpc_Envelope::create_error_response( $id, -32601, 'Method not found: ' . $method );
		}

		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();
		if ( 'tools/call' === $method && ( isset( $params['requestState'] ) || isset( $params['inputResponses'] ) ) ) {
			return Json_Rpc_Envelope::create_error_response( $id, -32602, 'Modern multi-round-trip fields are not supported by this protocol version.' );
		}

		$response = $this->message_router->route_message( $message, $request, $context );
		if ( ! is_array( $response ) || isset( $response['error'] ) ) {
			return $response;
		}

		return $this->remove_modern_result_fields( $response );
	}

	/**
	 * Validate and answer the legacy initialization handshake.
	 *
	 * @return array|WP_Error
	 */
	private function initialize( array $message ) {
		if ( ! array_key_exists( 'id', $message ) ) {
			return new WP_Error( 'mcp_invalid_json_rpc', 'The initialize request requires an id.', array( 'status' => 400 ) );
		}

		$params       = $message['params'] ?? null;
		$capabilities = is_array( $params ) ? ( $params['capabilities'] ?? null ) : null;
		$client_info  = is_array( $params ) ? ( $params['clientInfo'] ?? null ) : null;
		$version      = is_array( $params ) ? ( $params['protocolVersion'] ?? null ) : null;

		if (
			! is_array( $params )
			|| $this->is_list( $params )
			|| ! is_string( $version )
			|| '' === trim( $version )
			|| ! is_array( $capabilities )
			|| $this->is_list( $capabilities )
			|| ! $this->is_valid_client_info( $client_info )
		) {
			return new WP_Error( 'mcp_invalid_initialize', 'Invalid initialize parameters.', array( 'status' => 400 ) );
		}

		return $this->success_response(
			$message['id'],
			array(
				'protocolVersion' => Mcp_Version_Manager::negotiate_initialize( trim( $version ) ),
				'capabilities'    => array( 'tools' => new \stdClass() ),
				'serverInfo'      => $this->server_info(),
				'instructions'    => Server_Instructions::TEXT,
			)
		);
	}

	/**
	 * Validate the required implementation identity fields.
	 */
	private function is_valid_client_info( $client_info ): bool {
		return is_array( $client_info )
			&& ! $this->is_list( $client_info )
			&& isset( $client_info['name'], $client_info['version'] )
			&& is_string( $client_info['name'] )
			&& '' !== trim( $client_info['name'] )
			&& is_string( $client_info['version'] )
			&& '' !== trim( $client_info['version'] );
	}

	/**
	 * Remove fields that exist only in the 2026 result codec.
	 */
	private function remove_modern_result_fields( array $response ): array {
		if ( ! array_key_exists( 'result', $response ) ) {
			return $response;
		}

		if ( is_array( $response['result'] ) ) {
			unset(
				$response['result']['resultType'],
				$response['result']['ttlMs'],
				$response['result']['cacheScope'],
				$response['result']['requestState']
			);

			if ( isset( $response['result']['_meta'] ) ) {
				$response['result']['_meta'] = $this->remove_modern_server_metadata( $response['result']['_meta'] );
				if ( empty( $response['result']['_meta'] ) ) {
					unset( $response['result']['_meta'] );
				}
			}

			return $response;
		}

		if ( is_object( $response['result'] ) ) {
			// The modern envelope returns a detached object, but copy again here so
			// this compatibility projection remains safe for direct adapter callers.
			$legacy_result = new \stdClass();
			foreach ( get_object_vars( $response['result'] ) as $key => $value ) {
				$legacy_result->{$key} = $value;
			}

			foreach ( array( 'resultType', 'ttlMs', 'cacheScope', 'requestState' ) as $modern_field ) {
				unset( $legacy_result->{$modern_field} );
			}

			if ( isset( $legacy_result->_meta ) ) {
				$legacy_result->_meta = $this->remove_modern_server_metadata( $legacy_result->_meta );
				if ( empty( $legacy_result->_meta ) ) {
					unset( $legacy_result->_meta );
				}
			}

			$response['result'] = $legacy_result;
		}

		return $response;
	}

	/**
	 * Remove the modern server marker without mutating caller-owned metadata.
	 *
	 * @param mixed $meta Result metadata.
	 * @return array
	 */
	private function remove_modern_server_metadata( $meta ): array {
		$legacy_meta = is_array( $meta ) ? $meta : ( is_object( $meta ) ? get_object_vars( $meta ) : array() );
		unset( $legacy_meta['io.modelcontextprotocol/serverInfo'] );

		return $legacy_meta;
	}

	/**
	 * Build a legacy JSON-RPC success response without modern envelope fields.
	 *
	 * @param mixed $id Request identifier.
	 * @param mixed $result Result payload.
	 */
	private function success_response( $id, $result ): array {
		return array(
			'jsonrpc' => Json_Rpc_Envelope::JSON_RPC_VERSION,
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Return the server identity shared by initialization responses.
	 */
	private function server_info(): array {
		return array(
			'name'    => 'Uncanny Automator',
			'version' => defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : 'development',
		);
	}

	/**
	 * Validate the common JSON-RPC request shape.
	 */
	private function is_valid_json_rpc_message( array $message ): bool {
		return '2.0' === ( $message['jsonrpc'] ?? null )
			&& isset( $message['method'] )
			&& is_string( $message['method'] )
			&& '' !== $message['method']
			&& ( ! array_key_exists( 'id', $message ) || is_string( $message['id'] ) || is_int( $message['id'] ) );
	}

	/**
	 * Check whether an array represents a JSON list rather than object.
	 */
	private function is_list( array $value ): bool {
		return array() !== $value && array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
