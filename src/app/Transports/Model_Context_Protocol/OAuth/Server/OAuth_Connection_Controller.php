<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Readiness check for the fixed Automator MCP site connection contract.
 */
final class OAuth_Connection_Controller {

	/** Legacy metadata cache duration; live connection availability is not cached. */
	public const CACHE_MAX_AGE = 300;

	private OAuth_Server_Configuration $configuration;

	private OAuth_Scope_Policy $scope_policy;

	/**
	 * Constructor.
	 */
	public function __construct(
		?OAuth_Server_Configuration $configuration = null,
		?OAuth_Scope_Policy $scope_policy = null
	) {
		$this->configuration = $configuration ?? new OAuth_Server_Configuration();
		$this->scope_policy  = $scope_policy ?? new OAuth_Scope_Policy();
	}

	/**
	 * Register the fixed Automator connection check.
	 */
	public function register_routes(): void {
		register_rest_route(
			'automator/v1',
			'/oauth/connection',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_connection_info' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Public connection readiness response.
	 */
	public function get_connection_info( WP_REST_Request $request ): WP_REST_Response {
		OAuth_Cache_Policy::no_cache();

		return new WP_REST_Response( $this->connection_info(), 200, $this->metadata_headers() );
	}

	/**
	 * Public readiness facts for the fixed Automator MCP connection contract.
	 *
	 * Automator MCP derives OAuth URLs from the verified WordPress REST root.
	 * This check verifies the installed version and supported flow; it does not
	 * require WordPress hosts to serve OAuth .well-known discovery paths.
	 */
	public function connection_info(): array {
		$metadata                                   = array(
			'issuer'                                     => $this->configuration->get_issuer(),
			'authorization_endpoint'                     => $this->configuration->get_authorization_endpoint(),
			'token_endpoint'                             => $this->configuration->get_token_endpoint(),
			'revocation_endpoint'                        => $this->configuration->get_revocation_endpoint(),
			'revocation_endpoint_auth_methods_supported' => array( 'none', 'client_secret_basic' ),
			'registration_endpoint'                      => $this->configuration->get_registration_endpoint(),
			'response_types_supported'                   => array( 'code' ),
			'grant_types_supported'                      => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'           => array( 'S256' ),
			'token_endpoint_auth_methods_supported'      => array( 'none', 'client_secret_basic' ),
			'scopes_supported'                           => $this->scope_policy->get_supported_scopes(),
			'authorization_response_iss_parameter_supported' => true,
			'client_id_metadata_document_supported'      => true,
			'automator_hosted_agent_access_supported'    => true,
			'automator_shared_site_access_supported'     => true,
			'automator_version'                          => defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : '',
		);
		$metadata['automator_metadata_fingerprint'] = $this->configuration->fingerprint( $metadata );

		return $metadata;
	}

	/**
	 * HTTP headers for live connection readiness.
	 */
	public function metadata_headers(): array {
		return array(
			'Cache-Control'          => 'no-store',
			'Pragma'                 => 'no-cache',
			'X-Content-Type-Options' => 'nosniff',
			'X-Robots-Tag'           => 'noindex',
		);
	}
}
