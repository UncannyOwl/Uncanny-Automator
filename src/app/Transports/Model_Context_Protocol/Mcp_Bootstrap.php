<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use Uncanny_Automator\App\Application\Mcp\Mcp_Client;
use Uncanny_Automator\App\Feature_State\Infrastructure\Mcp_Allocation_Facts_Refresh;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Plan\Services\License\License_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication\Key_Binding_Challenge_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication\Site_Signing_Key_Monitor;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Feature_State_Presentation_Gate;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Rest_Bearer_Authenticator;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Rest_Bearer_Route_Authorizer;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Internal_Token_Configuration_Monitor;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Standalone\Dropdown_Controller;

use function Uncanny_Automator\App\Infrastructure\automator_feature_state_query;
use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

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
		$this->allocation_facts_refresh = null;

		// Allocation warming is optional presentation infrastructure. A malformed
		// third-party URL filter or any setup failure must not disable MCP routes,
		// authentication, bearer tokens, or the client itself.
		try {
			$licenses = automator_license_manager();

			if ( $licenses instanceof License_Manager ) {
				$this->allocation_facts_refresh = new Mcp_Allocation_Facts_Refresh(
					$licenses,
					Mcp_Client::get_inference_url()
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

		// Initialize REST controller.
		$this->rest_controller = new Mcp_Rest_Controller();
		// Initialize dropdown controller.
		$this->dropdown_controller                  = new Dropdown_Controller();
		$this->rest_bearer_authenticator            = new Rest_Bearer_Authenticator();
		$this->rest_bearer_route_authorizer         = new Rest_Bearer_Route_Authorizer();
		$this->internal_token_configuration_monitor = new Internal_Token_Configuration_Monitor();
		$this->site_signing_key_monitor             = new Site_Signing_Key_Monitor();
		$this->key_binding_challenge_controller     = new Key_Binding_Challenge_Controller();

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
	}
}
