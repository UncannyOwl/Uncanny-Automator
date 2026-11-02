<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Switch_Guard;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Mcp_Settings_Guard;
use Uncanny_Automator\App\Application\Mcp\Mcp_Client;
use Uncanny_Automator\App\Feature_State\Application\Can_Refresh_Frontend_Allocation;
use Uncanny_Automator\App\Feature_State\Infrastructure\Mcp_Allocation_Facts_Refresh;
use Uncanny_Automator\App\Feature_State\Infrastructure\Feature_State_Report;
use Uncanny_Automator\App\Feature_State\Infrastructure\WP_Frontend_Feature_Request_Adapter;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Plan\Services\License\License_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication\Key_Binding_Challenge_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication\Site_Signing_Key_Monitor;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Feature_State_Presentation_Gate;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Read_Credential_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Read_Credential_Reaper;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Rest_Bearer_Authenticator;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Rest_Bearer_Route_Authorizer;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Internal_Token_Configuration_Monitor;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Admin_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Artifact_Store;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Authorization_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Client_Registry;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Connection_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Registration_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Refresh_Token_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Scope_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Server_Configuration;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Token_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_User_Credential_Revoker;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Cleanup;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Standalone\Dropdown_Controller;

use function Uncanny_Automator\App\Infrastructure\automator_feature_state_query;
use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include tool framework classes
require_once __DIR__ . '/Tools/MCP_Tool_Interface.php';
require_once __DIR__ . '/Tools/Abstract_MCP_Tool.php';
require_once __DIR__ . '/Tools/Tool_Registry.php';
// Concrete tool implementations are now in catalog/ and autoloaded via composer

/**
 * MCP Bootstrap.
 *
 * Initializes the MCP transport layer.
 *
 * @since 7.0.0
 */
class Mcp_Bootstrap {

	private ?OAuth_Registration_Controller $oauth_registration_controller = null;

	/**
	 * MCP REST Controller instance.
	 *
	 * @since 7.0.0
	 * @var Mcp_Rest_Controller
	 */
	private $rest_controller;

	/**
	 * Client instance.
	 *
	 * @since 7.0.0
	 * @var Mcp_Client
	 */
	private $client;

	/**
	 * Dropdown controller instance.
	 *
	 * @since 7.0.0
	 * @var Dropdown_Controller
	 */
	private $dropdown_controller;

	/**
	 * REST bearer authenticator.
	 *
	 * @since 7.1.0
	 * @var Rest_Bearer_Authenticator
	 */
	private $rest_bearer_authenticator;

	/**
	 * REST bearer route authorizer.
	 *
	 * @since 7.2.3
	 * @var Rest_Bearer_Route_Authorizer
	 */
	private $rest_bearer_route_authorizer;

	/**
	 * Internal token configuration diagnostics.
	 *
	 * @var Internal_Token_Configuration_Monitor
	 */
	private $internal_token_configuration_monitor;

	/**
	 * WordPress site identity diagnostics.
	 *
	 * @var Site_Signing_Key_Monitor
	 */
	private $site_signing_key_monitor;

	/**
	 * WordPress site key-binding proof route.
	 *
	 * @var Key_Binding_Challenge_Controller
	 */
	private $key_binding_challenge_controller;

	/**
	 * Routes the hosted service uses to deliver and confirm this site's read credential.
	 *
	 * @var Hosted_Read_Credential_Controller
	 */
	private $hosted_read_credential_controller;

	/** @var Token_Cleanup */
	private $token_cleanup;

	/** @var Mcp_Observability */
	private $observability;

	/** @var OAuth_Connection_Controller */
	private $oauth_connection_controller;

	/** @var OAuth_Authorization_Controller */
	private $oauth_authorization_controller;

	/** @var OAuth_Token_Controller */
	private $oauth_token_controller;

	/** @var OAuth_Admin_Controller */
	private $oauth_admin_controller;

	/** @var OAuth_User_Credential_Revoker */
	private $user_credential_revoker;

	/** @var Hosted_Read_Credential_Reaper */
	private $hosted_read_credential_reaper;

	/**
	 * MCP presentation policy gate.
	 *
	 * @var Feature_State_Presentation_Gate
	 */
	private $feature_state_presentation_gate;

	/**
	 * MCP allocation cache refresh.
	 *
	 * @var Mcp_Allocation_Facts_Refresh|null
	 */
	private $allocation_facts_refresh;

	/**
	 * Initialize MCP transport layer.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function init() {
		// First, so a fatal later in an MCP or OAuth request still explains itself.
		Mcp_Fatal_Error_Report::register();
		( new Feature_State_Report() )->register();
		// The owner's MCP switches are never changed by Automator Pro's option actions.
		Mcp_Settings_Guard::register();
		// An ability the owner switched off for AI apps stays off inside other abilities.
		Ability_Switch_Guard::register();
		$this->allocation_facts_refresh = null;

		// Allocation warming is optional presentation infrastructure. A malformed
		// third-party URL filter or any setup failure must not disable MCP routes,
		// authentication, bearer tokens, or the client itself.
		try {
			$licenses = automator_license_manager();

			if ( $licenses instanceof License_Manager ) {
				$this->allocation_facts_refresh = new Mcp_Allocation_Facts_Refresh(
					$licenses,
					Mcp_Client::get_inference_url(),
					new Can_Refresh_Frontend_Allocation( new WP_Frontend_Feature_Request_Adapter() )
				);
				$this->allocation_facts_refresh->register();
			}
		} catch ( \Throwable $error ) {
			unset( $error );
		}

		$feature_state = null;

		try {
			$feature_state = automator_feature_state_query();
		} catch ( \Throwable $error ) {
			unset( $error );
		}

		$this->feature_state_presentation_gate = new Feature_State_Presentation_Gate( $feature_state );
		$this->feature_state_presentation_gate->register();

		$token_manager = new Token_Manager();

		// Initialize REST controller.
		$this->rest_controller = new Mcp_Rest_Controller( null, $token_manager );
		// Initialize dropdown controller.
		$this->dropdown_controller                  = new Dropdown_Controller();
		$this->rest_bearer_authenticator            = new Rest_Bearer_Authenticator( $token_manager );
		$this->rest_bearer_route_authorizer         = new Rest_Bearer_Route_Authorizer( $token_manager, $this->rest_bearer_authenticator );
		$this->internal_token_configuration_monitor = new Internal_Token_Configuration_Monitor();
		$this->site_signing_key_monitor             = new Site_Signing_Key_Monitor();
		$this->key_binding_challenge_controller     = new Key_Binding_Challenge_Controller();
		$this->hosted_read_credential_controller    = new Hosted_Read_Credential_Controller( $token_manager );
		$this->token_cleanup                        = new Token_Cleanup();
		$this->observability                        = new Mcp_Observability();

		// Initialize the chat client with the license service at the edge.
		$this->client = new Mcp_Client(
			new License_Service( true ),
			null,
			null,
			null,
			null,
			null,
			null
		);

		// Let valid MCP bearer tokens authenticate standard WordPress REST requests.
		$this->rest_bearer_authenticator->init();
		// Restrict MCP bearer access to writer-required REST routes.
		$this->rest_bearer_route_authorizer->init();
		// Surface missing strong key material before Agent payload generation.
		$this->internal_token_configuration_monitor->init();
		// Surface site identity failures when payload rendering fails closed.
		$this->site_signing_key_monitor->init();
		$this->token_cleanup->init();
		$this->observability->init();
		add_filter( 'rest_pre_serve_request', array( $this->rest_controller, 'serve_sse_response' ), 10, 4 );

		// A password change, lost role, site removal or deletion ends every MCP
		// credential the user held here, OAuth families included.
		$this->user_credential_revoker = new OAuth_User_Credential_Revoker( $token_manager, new OAuth_Artifact_Store() );
		$this->user_credential_revoker->register();

		// A hosted read credential ends with the sign-in that delivered it.
		$this->hosted_read_credential_reaper = Hosted_Read_Credential_Reaper::instance();
		$this->hosted_read_credential_reaper->register_hooks();

		if ( OAuth_Server_Configuration::is_browser_flow_available() ) {
			$this->initialize_browser_oauth( $token_manager );
		}

		// Register REST routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$this->rest_controller->register_routes();
		$this->dropdown_controller->register_routes();
		$this->key_binding_challenge_controller->register_routes();

		if ( OAuth_Server_Configuration::is_browser_flow_available() ) {
			$this->oauth_connection_controller->register_routes();
			$this->oauth_registration_controller->register_routes();
			$this->oauth_authorization_controller->register_routes();
			$this->oauth_token_controller->register_routes();
			$this->oauth_admin_controller->register_routes();
			// Only the hosted service's OAuth sign-in can use these routes.
			$this->hosted_read_credential_controller->register_routes();
		}
	}

	/**
	 * Initialize the preserved browser OAuth flow when its platform dependency is ready.
	 */
	private function initialize_browser_oauth( Token_Manager $token_manager ): void {
		$oauth_configuration  = new OAuth_Server_Configuration();
		$oauth_scope_policy   = new OAuth_Scope_Policy();
		$oauth_artifact_store = new OAuth_Artifact_Store();
		$oauth_clients        = new OAuth_Client_Registry();

		$this->oauth_connection_controller    = new OAuth_Connection_Controller( $oauth_configuration, $oauth_scope_policy );
		$this->oauth_registration_controller  = new OAuth_Registration_Controller( $oauth_configuration, $oauth_clients );
		$this->oauth_authorization_controller = new OAuth_Authorization_Controller(
			$oauth_configuration,
			$oauth_clients,
			$oauth_scope_policy,
			$oauth_artifact_store
		);
		$refresh_tokens                       = new OAuth_Refresh_Token_Service( $oauth_artifact_store, $token_manager );
		$this->oauth_token_controller         = new OAuth_Token_Controller(
			$oauth_configuration,
			$oauth_clients,
			$oauth_scope_policy,
			$oauth_artifact_store,
			$refresh_tokens,
			$token_manager
		);
		$this->oauth_admin_controller         = new OAuth_Admin_Controller(
			$oauth_configuration,
			$oauth_clients
		);
		// Login-redirect plugins must not strand a pending authorization.
		add_filter( 'login_redirect', array( $this->oauth_authorization_controller, 'restore_authorization_redirect' ), PHP_INT_MAX, 3 );

		// Browser authorization uses the site's existing administrator cookie.
		// It opts in before WordPress's REST cookie nonce check logs out requests
		// that correctly arrive from a third-party OAuth redirect.
		// Priority 90 is load-bearing: core's rest_cookie_check_errors runs at
		// 100 and would demote the cookie admin to anonymous first. The consent
		// POST carries its own single-purpose nonce instead.
		add_filter( 'rest_authentication_errors', array( $this->oauth_authorization_controller, 'preserve_cookie_auth' ), 90 );
		// Run after core and third-party REST CORS callbacks so the authorization
		// route can remove credentialed cross-origin grants before serving either
		// the consent page or a consent decision response.
		add_filter( 'rest_pre_serve_request', array( $this->oauth_authorization_controller, 'serve_html' ), PHP_INT_MAX, 4 );
	}
}
