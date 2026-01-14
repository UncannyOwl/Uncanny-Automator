<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

/**
 * MCP Lifecycle Manager.
 *
 * Implements <https://modelcontextprotocol.io/specification/2025-06-18/basic/lifecycle>
 *
 * Handles connection initialization and capability negotiation.
 *
 * @since 7.0.0
 */
class Lifecycle_Manager {

	/**
	 * Server capabilities.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private $server_capabilities = array();

	/**
	 * Client capabilities.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private $client_capabilities = array();

	/**
	 * Session state.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $session_state = 'disconnected';

	/**
	 * Negotiated protocol version for this session.
	 *
	 * @since 7.0.0
	 * @var string
	 */
	private $negotiated_version = '';

	/**
	 * Initialize the MCP connection.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $request_id Request ID.
	 * @param array      $params     Initialize parameters.
	 * @return array JSON-RPC response.
	 */
	public function initialize( $request_id, $params ) {
		// Validate and negotiate protocol version.
		$client_version           = $params['protocolVersion'] ?? '';
		$this->negotiated_version = Mcp_Version_Manager::negotiate( $client_version );

		if ( null === $this->negotiated_version ) {
			return Json_Rpc_Envelope::create_error_response(
				$request_id,
				-32602,
				'Incompatible protocol version',
				array(
					'supported_versions' => Mcp_Version_Manager::get_supported_versions(),
					'client_version'     => $client_version,
				)
			);
		}

		// Update session state.
		$this->session_state = 'initialized';

		// Store client capabilities.
		$this->client_capabilities = $params['capabilities'] ?? array();

		// Set our server capabilities.
		$this->server_capabilities = array(
			'tools'     => array( 'listChanged' => true ),
			'resources' => array(
				'subscribe'   => true,
				'listChanged' => true,
			),
			'prompts'   => array( 'listChanged' => true ),
			'logging'   => (object) array(), // Must be object, not array
		);

		$result = array(
			'protocolVersion' => $this->negotiated_version,
			'capabilities'    => $this->server_capabilities,
			'serverInfo'      => array(
				'name'    => 'Uncanny Automator MCP Server',
				'version' => '0.0.9',
			),
		);

		return Json_Rpc_Envelope::create_success_response( $request_id, $result );
	}

	/**
	 * Handle ping request for keepalive.
	 *
	 * @since 7.0.0
	 *
	 * @param string|int $request_id Request ID.
	 * @return array JSON-RPC response.
	 */
	public function ping( $request_id ) {
		// The receiver MUST respond promptly with an empty response.
		// Zod expects an empty object. So, we return an empty object.
		return Json_Rpc_Envelope::create_success_response( $request_id, new \stdClass() );
	}

	/**
	 * Handle session notifications.
	 *
	 * @since 7.0.0
	 *
	 * @param string $notification_type Type of notification.
	 * @param array  $params            Notification parameters.
	 * @return void
	 */
	public function handle_notification( $notification_type, $params = array() ) {
		switch ( $notification_type ) {
			case 'notifications/initialized':
				$this->session_state = 'ready';
				break;
			case 'notifications/cancelled':
				// Handle request cancellation.
				break;
			case 'notifications/progress':
				// Handle progress updates.
				break;
		}
	}

	/**
	 * Get current session state.
	 *
	 * @since 7.0.0
	 *
	 * @return string Current session state.
	 */
	public function get_session_state() {
		return $this->session_state;
	}

	/**
	 * Get server capabilities.
	 *
	 * @since 7.0.0
	 *
	 * @return array Server capabilities.
	 */
	public function get_server_capabilities() {
		return $this->server_capabilities;
	}

	/**
	 * Get client capabilities.
	 *
	 * @since 7.0.0
	 *
	 * @return array Client capabilities.
	 */
	public function get_client_capabilities() {
		return $this->client_capabilities;
	}

	/**
	 * Get negotiated protocol version.
	 *
	 * @since 7.0.0
	 *
	 * @return string Negotiated protocol version for this session.
	 */
	public function get_negotiated_version() {
		return $this->negotiated_version;
	}
}
