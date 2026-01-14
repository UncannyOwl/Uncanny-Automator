<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Application;

use Uncanny_Automator\Api\Services\Services_Bootstrap;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Mcp_Bootstrap;
use Uncanny_Automator\Api\Transports\Restful\Restful_Bootstrap;

/**
 * Application Bootstrap.
 *
 * Central bootstrap class for all API applications.
 * Initializes services, MCP and RESTful transport layers.
 *
 * @since 7.0
 */
class Application_Bootstrap {

	/**
	 * Initialize all API applications.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function init() {
		// Initialize services layer (hooks, legacy compatibility).
		( new Services_Bootstrap() )->init();

		// Initialize transport layers.
		( new Mcp_Bootstrap() )->init();
		( new Restful_Bootstrap() )->init();
	}
}
