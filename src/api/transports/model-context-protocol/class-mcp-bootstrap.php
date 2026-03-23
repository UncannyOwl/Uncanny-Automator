<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol;

use Uncanny_Automator\Api\Application\Mcp\Mcp_Client;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Standalone\Dropdown_Controller;

// Include tool framework classes
require_once __DIR__ . '/tools/interface-mcp-tool.php';
require_once __DIR__ . '/tools/abstract-mcp-tool.php';
require_once __DIR__ . '/tools/class-tool-registry.php';
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
	 * Initialize MCP transport layer.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function init() {

		// Initialize REST controller.
		$this->rest_controller = new Mcp_Rest_Controller();
		// Initialize dropdown controller.
		$this->dropdown_controller = new Dropdown_Controller();

		// Initialize the chat client.
		$this->client = Mcp_Client::get_instance();

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
	}
}
