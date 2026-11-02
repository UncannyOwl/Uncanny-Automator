<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Ai;

use Uncanny_Automator\App\Transports\Restful\Ai\Routes\Get_Ai_Activity_Route;
use Uncanny_Automator\App\Transports\Restful\Ai\Routes\Get_Ai_Connections_Route;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Admin_Permissions;

/**
 * AI Dashboard REST registration hub: `GET /uap/v2/ai/connections` and
 * `GET /uap/v2/ai/activity`, read through the hosted bridge.
 *
 * AI data needs the REST nonce and the fixed manage_options capability. The
 * filterable Automator capability is not enough: a site can lower it so
 * editors build recipes, and editors must not read what AI apps did.
 */
final class Ai_Rest_Controller {

	use Restful_Admin_Permissions;

	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	private const REST_BASE = 'ai';

	/**
	 * Register the AI routes. Called from `Restful_Bootstrap::register_rest_routes()`.
	 */
	public static function register_routes(): void {

		// GET /uap/v2/ai/connections
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/connections',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Ai_Connections_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);

		// GET /uap/v2/ai/activity
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/activity',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Ai_Activity_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);
	}
}
