<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Restful\Blocks;

use Uncanny_Automator\Api\Services\Block\Block_Store;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Traits\Restful_Permissions;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Blocks REST Controller.
 *
 * Provides REST API endpoints for Automator blocks (delay, filter, loop).
 *
 * @since 7.0
 */
class Blocks_Rest_Controller {

	use Restful_Permissions;
	use Rest_Responses;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	protected $namespace = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'blocks';

	/**
	 * Registers the routes for blocks.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function register_routes() {
		// GET /wp-json/uap/v2/blocks/get/
		// Returns scoped blocks (delay, filter, loop) for integrations.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_blocks' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get scoped blocks.
	 *
	 * Returns all available blocks including delay, filter, and loop types.
	 *
	 * @since 7.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_blocks( WP_REST_Request $request ): WP_REST_Response {
		$blocks = new Block_Store();
		return new WP_REST_Response( $blocks->get_all(), 200 );
	}
}
