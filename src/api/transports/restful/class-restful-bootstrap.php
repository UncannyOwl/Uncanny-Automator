<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Restful;

use Uncanny_Automator\Api\Transports\Restful\Recipe\Recipe_Rest_Controller;
use Uncanny_Automator\Api\Transports\Restful\Integrations\Integrations_Rest_Controller;
use Uncanny_Automator\Api\Transports\Restful\Blocks\Blocks_Rest_Controller;

/**
 * RESTful API Bootstrap.
 *
 * Initializes all REST API controllers.
 *
 * @since 7.0
 */
class Restful_Bootstrap {

	/**
	 * Constructor.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * Initialize REST API transport layer.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function init() {
		// Register REST routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// Register recipe routes.
		$recipe_controller = new Recipe_Rest_Controller();
		$recipe_controller->register_routes();

		// Register integrations routes.
		$integrations_controller = new Integrations_Rest_Controller();
		$integrations_controller->register_routes();

		// Register blocks routes.
		$blocks_controller = new Blocks_Rest_Controller();
		$blocks_controller->register_routes();
	}
}
