<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Blocks;

use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Store;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Blocks REST registration hub.
 *
 * Static-only. Provides the read-only `GET /uap/v2/blocks/get/` endpoint
 * that returns the registered blocks (delay, filter, loop) used by the
 * recipe-builder UI. Single endpoint — no `Routes/` split.
 */
final class Blocks_Rest_Controller {

	use Restful_Permissions;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route segment for the blocks surface.
	 *
	 * @var string
	 */
	private const REST_BASE = 'blocks';

	/**
	 * Register every blocks REST route. Called once per process from
	 * `Restful_Bootstrap::register_rest_routes()` on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function register_routes(): void {

		// GET /uap/v2/blocks/get/
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get/',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_blocks' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * GET handler. Returns all registered blocks (delay, filter, loop).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function get_blocks( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( ( new Block_Store() )->get_all(), 200 );
	}
}
