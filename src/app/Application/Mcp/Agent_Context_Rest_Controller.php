<?php
/**
 * Agent Context REST endpoint.
 *
 * GET /wp-json/automator/v1/agent-context
 *
 * Returns the ModelContext payload for the standalone app.
 * Authenticated via Bearer token (Token_Manager).
 *
 * @since 7.1.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Mcp;

use Uncanny_Automator\App\Application\Mcp\Agent\Agent_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Context_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Agent_Context_Rest_Controller {

	const REST_NAMESPACE = 'automator/v1';

	/**
	 * @var Token_Manager
	 */
	private Token_Manager $token_manager;

	/**
	 * @var Client_Context_Service
	 */
	private Client_Context_Service $context_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token_manager   = new Token_Manager();
		$this->context_service = new Client_Context_Service();
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/agent-context',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Authenticate via Bearer token.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions( WP_REST_Request $request ) {
		$auth_header = (string) $request->get_header( 'authorization' );
		$creds       = (string) $request->get_header( 'x-automator-creds' );

		if ( false === strpos( strtolower( $auth_header ), 'bearer' ) ) {
			$auth_header = $creds;
		}

		if ( $auth_header && preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			$token      = $matches[1];
			$user       = $this->token_manager->get_user_from_token( $token );
			$capability = $this->context_service->get_client_access_capability();

			if ( $user && user_can( $user, $capability ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Uncanny Agent uses a dedicated capability constant.
				wp_set_current_user( $user->ID );
				return true;
			}

			return new WP_Error(
				'agent_context_invalid_token',
				'Invalid or expired Bearer token.',
				array( 'status' => 401 )
			);
		}

		return new WP_Error(
			'agent_context_missing_token',
			'Bearer token required.',
			array( 'status' => 401 )
		);
	}

	/**
	 * Return the agent context.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$context = new Agent_Context();

		return new WP_REST_Response( $context->build(), 200 );
	}
}
