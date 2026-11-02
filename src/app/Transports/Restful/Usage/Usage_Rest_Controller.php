<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Usage;

use Uncanny_Automator\App\Plan\Services\Account\App_Credits_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Ai_Dashboard;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Usage REST registration hub.
 *
 * Static-only. Provides the read-only `GET /uap/v2/usage/get-summary` endpoint
 * the Dashboard Usage card reads. Single endpoint — no `Routes/` split.
 *
 * App credits follow the Automator capability. The AI meter is AI data: it
 * comes from `Hosted_Ai_Dashboard::ai_tool_uses()`, which applies the fixed
 * manage_options check itself and answers null to anyone else.
 */
final class Usage_Rest_Controller {

	use Restful_Permissions;
	use Rest_Responses;

	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	private const REST_BASE = 'usage';

	/**
	 * Register the usage routes. Called from `Restful_Bootstrap::register_rest_routes()`.
	 */
	public static function register_routes(): void {

		// GET /uap/v2/usage/get-summary
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
	 * GET handler. Both meters, each null where it does not apply, or the app
	 * credits error with its status. A 503 carries `Retry-After`.
	 */
	public static function get_summary( WP_REST_Request $request ): WP_REST_Response {
		$app_credits = ( new App_Credits_Service() )->get_meter();

		if ( $app_credits instanceof WP_Error ) {
			return self::error_reply( $app_credits );
		}

		return self::success(
			array(
				'app_credits'  => $app_credits,
				'ai_tool_uses' => Hosted_Ai_Dashboard::instance()->ai_tool_uses(),
			)
		);
	}
}
