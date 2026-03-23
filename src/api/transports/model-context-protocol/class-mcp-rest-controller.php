<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;

/**
 * MCP REST Controller.
 *
 * WordPress REST API endpoint for Model Context Protocol.
 *
 * @since 7.0.0
 */
class Mcp_Rest_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the REST API.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	protected $namespace = 'automator/v1';

	/**
	 * Rest base for the current object.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	protected $rest_base = 'mcp';

	/**
	 * Lifecycle manager instance.
	 *
	 * @since 7.0.0
	 * @var Lifecycle_Manager
	 */
	private $lifecycle_manager;

	/**
	 * Message router instance.
	 *
	 * @since 7.0.0
	 * @var Message_Router
	 */
	private $message_router;

	/**
	 * Token manager instance.
	 *
	 * @since 7.0.0
	 * @var Token_Manager
	 */
	private $token_manager;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	public function __construct() {
		$this->lifecycle_manager = new Lifecycle_Manager();
		$this->message_router    = new Message_Router();
		$this->token_manager     = new Token_Manager();
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE, // POST
					'callback'            => array( $this, 'handle_post_request' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_post_params(),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE, // GET
					'callback'            => array( $this, 'handle_get_request' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE, // DELETE
					'callback'            => array( $this, 'handle_delete_request' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Handle POST requests (send messages to server).
	 *
	 * @since 7.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function handle_post_request( $request ) {

		// Validate protocol version header.
		$version_check = $this->validate_protocol_version( $request );
		if ( is_wp_error( $version_check ) ) {
			return $version_check;
		}

		// Validate Accept header.
		$accept_header = $request->get_header( 'Accept' );
		if ( ! $this->is_valid_accept_header( $accept_header ) ) {
			return new WP_Error(
				'mcp_invalid_accept',
				'Accept header must include application/json and text/event-stream',
				array( 'status' => 400 )
			);
		}

		// Get JSON-RPC message from request body.
		$json_rpc_message = $request->get_json_params();
		if ( empty( $json_rpc_message ) ) {
			return new WP_Error(
				'mcp_invalid_json',
				'Request body must contain valid JSON-RPC message',
				array( 'status' => 400 )
			);
		}

		// Route the message.
		$response = $this->message_router->route_message( $json_rpc_message, $request );

		// Handle different response types.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// For notifications, return 202 Accepted.
		if ( ! isset( $json_rpc_message['id'] ) ) {
			return new WP_REST_Response( null, 202 );
		}

		// For requests, return JSON response.
		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle GET requests (Server-Sent Events).
	 *
	 * @since 7.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function handle_get_request( $request ) {
		// For now, return 405 Method Not Allowed (SSE not implemented yet).
		return new \WP_Error(
			'mcp_sse_not_supported',
			'Server-Sent Events not currently supported',
			array( 'status' => 405 )
		);
	}

	/**
	 * Handle DELETE requests (terminate session).
	 *
	 * @since 7.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object on success, WP_Error on failure.
	 */
	public function handle_delete_request( $request ) {
		// The DELETE method confirms MCP session termination.
		// WordPress handles session management automatically.
		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Check permissions for MCP access.
	 *
	 * @since 7.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error True if allowed, WP_Error otherwise.
	 */
	public function check_permissions( $request ) {

		// Try Bearer token authentication first.
		$auth_header = (string) $request->get_header( 'authorization' );
		// The MCP client sends two identifical tokens. There are setups where apache2 blocks the first token, so we use the second one.
		$creds = (string) $request->get_header( 'x-automator-creds' );

		// Try alternative credential if the auth_header is not available because of apache2 configuration.
		if ( false === strpos( strtolower( $auth_header ), 'bearer' ) ) {
			$auth_header = $creds;
		}

		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			$token = $matches[1];

			// Validate token using Token_Manager.
			$user = $this->token_manager->get_user_from_token( $token );

			if ( $user && user_can( $user, automator_get_capability() ) ) {
				// Set current user for the request.
				wp_set_current_user( $user->ID );
				return true;
			}

			return new WP_Error(
				'mcp_invalid_token',
				'Invalid or expired Bearer token.',
				array( 'status' => 401 )
			);
		}

		// Fall back to WordPress native authentication (Application Passwords).
		return current_user_can( automator_get_capability() );
	}

	/**
	 * Validate protocol version header.
	 *
	 * @since 7.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_protocol_version( $request ) {
		$version = $request->get_header( 'mcp-protocol-version' );

		// Default version if missing (backwards compatibility).
		if ( empty( $version ) ) {
			$version = Mcp_Version_Manager::get_default_version();
		}

		if ( ! Mcp_Version_Manager::is_supported( $version ) ) {
			return new WP_Error(
				'mcp_unsupported_version',
				'Unsupported MCP protocol version: ' . $version,
				array(
					'status'             => 400,
					'supported_versions' => Mcp_Version_Manager::get_supported_versions(),
				)
			);
		}

		return true;
	}

	/**
	 * Check if Accept header is valid.
	 *
	 * @since 7.0.0
	 *
	 * @param string $accept_header Accept header value.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_accept_header( $accept_header ) {
		if ( empty( $accept_header ) ) {
			return false;
		}
		// Must include both application/json and text/event-stream.
		return false !== strpos( $accept_header, 'application/json' ) &&
				false !== strpos( $accept_header, 'text/event-stream' );
	}

	/**
	 * Get POST request parameters schema.
	 *
	 * @since 7.0.0
	 *
	 * @return array Parameters schema.
	 */
	private function get_post_params() {
		return array(
			'jsonrpc' => array(
				'required'          => true,
				'type'              => 'string',
				'enum'              => array( '2.0' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
			'method'  => array(
				'required'          => false,
				'type'              => 'string',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'id'      => array(
				'required'          => false,
				'type'              => array( 'string', 'number' ),
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
