<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Tool_Registry;
use WP_Error;

/**
 * MCP Message Router.
 *
 * Routes JSON-RPC messages to appropriate handlers.
 *
 * @since 7.0.0
 */
class Message_Router {

	/**
	 * Lifecycle manager instance.
	 *
	 * @since 7.0.0
	 * @var Lifecycle_Manager
	 */
	private $lifecycle_manager;

	/**
	 * Tool registry instance.
	 *
	 * @since 7.0.0
	 * @var Tool_Registry
	 */
	private $tool_registry;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	public function __construct() {
		$this->lifecycle_manager = new Lifecycle_Manager();
		$this->tool_registry     = new Tool_Registry();
		$this->tool_registry->auto_register_tools();
	}

	/**
	 * Route JSON-RPC message to appropriate handler.
	 *
	 * @since 7.0.0
	 *
	 * @param array            $message JSON-RPC message.
	 * @param \WP_REST_Request $request REST request object.
	 * @return array|\WP_Error Response array on success, WP_Error on failure.
	 */
	public function route_message( $message, $request ) {

		// Validate JSON-RPC structure.
		if ( ! $this->is_valid_json_rpc( $message ) ) {
			return new WP_Error(
				'mcp_invalid_json_rpc',
				'Invalid JSON-RPC message format',
				array( 'status' => 400 )
			);
		}

		$method = $message['method'] ?? '';
		$id     = $message['id'] ?? null;
		$params = $message['params'] ?? array();

		// Method routing map: method => [handler_method, requires_params]
		$method_map = array(
			'initialize'       => array( 'handle_initialize', true ),
			'ping'             => array( 'handle_ping', false ),
			'logging/setLevel' => array( 'handle_logging_set_level', true ),
			'tools/list'       => array( 'handle_tools_list', false ),
			'tools/call'       => array( 'handle_tools_call', true ),
			'resources/list'   => array( 'handle_resources_list', false ),
			'resources/read'   => array( 'handle_resources_read', true ),
			'prompts/list'     => array( 'handle_prompts_list', false ),
			'prompts/get'      => array( 'handle_prompts_get', true ),
		);

		if ( isset( $method_map[ $method ] ) ) {
			$handler_config  = $method_map[ $method ];
			$handler_method  = $handler_config[0];
			$requires_params = $handler_config[1];

			return $requires_params
				? $this->$handler_method( $id, $params )
				: $this->$handler_method( $id );
		}

		// Handle unknown methods
		if ( null !== $id ) {
			return Json_Rpc_Envelope::create_error_response(
				$id,
				-32601,
				'Method not found: ' . $method
			);
		}

		return array( 'status' => 'ignored' );
	}

	/**
	 * Handle initialize request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id     Request ID.
	 * @param array      $params Request parameters.
	 * @return array Response.
	 */
	private function handle_initialize( $id, $params ) {
		$response = $this->lifecycle_manager->initialize( $id, $params );
		return $response;
	}

	/**
	 * Handle ping request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id Request ID.
	 * @return array Response.
	 */
	private function handle_ping( $id ) {
		return $this->lifecycle_manager->ping( $id );
	}

	/**
	 * Handle logging/setLevel request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id     Request ID.
	 * @param array      $params Request parameters.
	 * @return array Response.
	 */
	private function handle_logging_set_level( $id, $params ) {
		$level = $params['level'] ?? 'info';

		// MCP logging/setLevel should return empty result per spec.
		$result = (object) array(); // Empty object
		return Json_Rpc_Envelope::create_success_response( $id, $result );
	}

	/**
	 * Handle tools/list request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id Request ID.
	 * @return array Response.
	 */
	private function handle_tools_list( $id ) {
		// Get all tool schemas from registry
		$result = array( 'tools' => $this->tool_registry->get_all_schemas() );

		return Json_Rpc_Envelope::create_success_response( $id, $result );
	}

	/**
	 * Handle tools/call request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id     Request ID.
	 * @param array      $params Request parameters.
	 * @return array Response.
	 */
	private function handle_tools_call( $id, $params ) {

		$tool_name = $params['name'] ?? '';
		$arguments = $params['arguments'] ?? array();

		// Get tool from registry
		$registry_tool = $this->tool_registry->get_tool( $tool_name );
		if ( ! $registry_tool ) {
			return Json_Rpc_Envelope::create_error_response(
				$id,
				-32602,
				'Unknown tool: ' . $tool_name
			);
		}

		$result = $this->tool_registry->execute_tool( $tool_name, $arguments );

		// Give capability for 3rd party to extend our toolset.
		do_action(
			'automator_mcp_tools_handle',
			array(
				'id'     => $id,
				'params' => $params,
			)
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Envelope::create_error_response(
				$id,
				$result->get_error_code(),
				$result->get_error_message()
			);
		}

		return Json_Rpc_Envelope::create_success_response( $id, $result );
	}

	/**
	 * Handle resources/list request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id Request ID.
	 * @return array Response.
	 */
	private function handle_resources_list( $id ) {

		$resources = array(
			array(
				'uri'         => 'automator://recipes',
				'name'        => 'Automator Recipes',
				'description' => 'List of all Automator recipes',
				'mimeType'    => 'application/json',
			),
			array(
				'uri'         => 'automator://logs',
				'name'        => 'Automator Logs',
				'description' => 'Recent Automator execution logs',
				'mimeType'    => 'application/json',
			),
		);

		$result = array( 'resources' => $resources );

		return Json_Rpc_Envelope::create_success_response( $id, $result );
	}

	/**
	 * Handle resources/read request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id     Request ID.
	 * @param array      $params Request parameters.
	 * @return array Response.
	 */
	private function handle_resources_read( $id, $params ) {
		$uri = $params['uri'] ?? '';

		$content = array(
			'uri'      => $uri,
			'mimeType' => 'application/json',
			'text'     => wp_json_encode( array( 'message' => 'Resource content for: ' . $uri ) ),
		);

		$result = array( 'contents' => array( $content ) );
		return Json_Rpc_Envelope::create_success_response( $id, $result );
	}

	/**
	 * Handle prompts/list request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id Request ID.
	 * @return array Response.
	 */
	private function handle_prompts_list( $id ) {
		$prompts = array(
			array(
				'name'        => 'recipe_helper',
				'description' => 'Help create Automator recipes',
				'arguments'   => array(
					array(
						'name'        => 'goal',
						'description' => 'What you want to achieve',
						'required'    => true,
					),
				),
			),
		);

		$result = array( 'prompts' => $prompts );
		return Json_Rpc_Envelope::create_success_response( $id, $result );
	}

	/**
	 * Handle prompts/get request.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $id     Request ID.
	 * @param array      $params Request parameters.
	 * @return array Response.
	 */
	private function handle_prompts_get( $id, $params ) {
		$arguments = $params['arguments'] ?? array();

		$messages = array(
			array(
				'role'    => 'user',
				'content' => array(
					'type' => 'text',
					'text' => 'Help me create an Automator recipe for: ' . ( $arguments['goal'] ?? 'general automation' ),
				),
			),
		);

		$result = array(
			'description' => 'Recipe creation assistance',
			'messages'    => $messages,
		);

		return Json_Rpc_Envelope::create_success_response( $id, $result );
	}

	/**
	 * Validate JSON-RPC message structure.
	 *
	 * @since 7.0.0
	 *
	 * @param array $message Message to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_json_rpc( $message ) {
		// Must have jsonrpc: "2.0".
		if ( ! isset( $message['jsonrpc'] ) || '2.0' !== $message['jsonrpc'] ) {
			return false;
		}

		// Must have method (for requests/notifications) OR id (for responses).
		if ( ! isset( $message['method'] ) && ! isset( $message['id'] ) ) {
			return false;
		}

		return true;
	}
}
