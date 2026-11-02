<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\License;

use Uncanny_Automator\App\Plan\Addons\Services\License_Summary_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;
use WP_REST_Request;
use WP_REST_Response;

/**
 * License REST registration hub.
 *
 * Static-only. Provides the read-only `GET /uap/v2/license/get-summary`
 * endpoint the AddOns page and the upgrade banners read. Single endpoint —
 * no `Routes/` split.
 */
final class License_Rest_Controller {

	use Restful_Permissions;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route segment for the license surface.
	 *
	 * @var string
	 */
	private const REST_BASE = 'license';

	/**
	 * Register every license REST route. Called once per process from
	 * `Restful_Bootstrap::register_rest_routes()` on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function register_routes(): void {

		// GET /uap/v2/license/get-summary
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get-summary',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_summary' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * GET handler. Returns the current license summary.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_summary( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			License_Summary_Response::body( License_Summary_Service::instance()->get_snapshot() ),
			200
		);
	}
}
