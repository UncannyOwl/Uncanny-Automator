<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Site;

use Uncanny_Automator\App\Plan\Services\Plugin\Plugin_Status_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Admin_Permissions;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Site REST registration hub.
 *
 * Static-only. Provides the read-only `GET /uap/v2/site/get-status` endpoint
 * the Dashboard Status card reads. Single endpoint — no `Routes/` split.
 */
final class Site_Rest_Controller {

	use Restful_Admin_Permissions;
	use Rest_Responses;

	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	private const REST_BASE = 'site';

	/**
	 * Register the site routes. Called from `Restful_Bootstrap::register_rest_routes()`.
	 */
	public static function register_routes(): void {

		// GET /uap/v2/site/get-status
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get-status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_status' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * GET handler. Each active Automator plugin and its pending update.
	 */
	public static function get_status( WP_REST_Request $request ): WP_REST_Response {
		return self::success( array( 'plugins' => ( new Plugin_Status_Service() )->get_statuses() ) );
	}
}
