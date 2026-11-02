<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Administrator controls for the optional site-hosted OAuth doorway.
 *
 * These are authenticated WordPress administration routes. They are not an
 * OAuth Dynamic Client Registration endpoint.
 */
final class OAuth_Admin_Controller {

	private OAuth_Server_Configuration $configuration;

	private OAuth_Client_Registry $clients;

	/**
	 * Constructor.
	 */
	public function __construct(
		?OAuth_Server_Configuration $configuration = null,
		?OAuth_Client_Registry $clients = null
	) {
		$this->configuration = $configuration ?? new OAuth_Server_Configuration();
		$this->clients       = $clients ?? new OAuth_Client_Registry();
	}

	/**
	 * Register administrator-only REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'automator/v1',
			'/oauth/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			'automator/v1',
			'/oauth/clients',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_clients' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'register_client' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			'automator/v1',
			'/oauth/clients/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'revoke_client' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Require a native WordPress administrator session.
	 *
	 * @return true|WP_Error
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' )
			? true
			: new WP_Error( 'rest_forbidden', 'Administrator access is required.', array( 'status' => 403 ) );
	}

	/**
	 * Return public settings and the latest discovery result.
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->settings_payload(), 200 );
	}

	/**
	 * Update the operational OAuth setting.
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$enabled = rest_sanitize_boolean( $request->get_param( 'enabled' ) );

		$stored = $this->configuration->set_enabled( $enabled );
		if ( ! $stored && $this->configuration->is_enabled() !== $enabled ) {
			return new WP_REST_Response(
				array(
					'code'    => 'oauth_settings_not_saved',
					'message' => 'The OAuth setting could not be saved.',
				),
				500
			);
		}
		OAuth_Telemetry::record( 'server_enablement', $enabled ? 'enabled' : 'disabled', '', get_current_user_id() );

		return new WP_REST_Response( $this->settings_payload(), 200 );
	}

	/**
	 * List safe administrative client metadata.
	 */
	public function list_clients( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'clients' => $this->clients->list_clients() ), 200 );
	}

	/**
	 * Pre-register a client; confidential secrets are returned once.
	 */
	public function register_client( WP_REST_Request $request ): WP_REST_Response {
		$name          = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$type          = sanitize_key( (string) $request->get_param( 'type' ) );
		$redirect_uris = $request->get_param( 'redirect_uris' );
		if ( '' === $name || strlen( $name ) > 100 || ! is_array( $redirect_uris ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_client_metadata',
					'message' => 'A short name, client type, and valid redirect URI list are required.',
				),
				400
			);
		}

		$client = $this->clients->register( $name, $type, $redirect_uris );
		if ( ! is_array( $client ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_client_metadata',
					'message' => 'The client could not be registered. Redirect addresses must match the supported HTTPS or loopback forms.',
				),
				400
			);
		}
		OAuth_Telemetry::record( 'client_registered', 'success', (string) $client['client_id'], get_current_user_id() );

		return new WP_REST_Response( $client, 201, array( 'Cache-Control' => 'no-store' ) );
	}

	/**
	 * Revoke a pre-registered or CIMD client by exact identifier.
	 */
	public function revoke_client( WP_REST_Request $request ): WP_REST_Response {
		$client_id = (string) $request->get_param( 'client_id' );
		if ( '' === $client_id || strlen( $client_id ) > 2048 || ! $this->clients->revoke( $client_id ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'client_not_revoked',
					'message' => 'The exact client identifier could not be revoked.',
				),
				400
			);
		}
		OAuth_Telemetry::record( 'client_revoked', 'success', $client_id, get_current_user_id() );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Build a secret-free settings payload for Automator-owned OAuth endpoints.
	 */
	private function settings_payload(): array {
		return array(
			'enabled'                       => $this->configuration->is_enabled(),
			'https_ready'                   => $this->configuration->is_https(),
			'authorization_available'       => $this->configuration->can_authorize(),
			'resource'                      => $this->configuration->get_resource(),
			'issuer'                        => $this->configuration->get_issuer(),
			'authorization_endpoint'        => $this->configuration->get_authorization_endpoint(),
			'token_endpoint'                => $this->configuration->get_token_endpoint(),
			'connection_check'              => $this->configuration->get_connection_check_url(),
		);
	}
}
