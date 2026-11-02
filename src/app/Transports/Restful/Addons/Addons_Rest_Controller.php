<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Addons;

use Uncanny_Automator\App\Transports\Restful\Addons\Routes\Get_Addons_List_Route;
use Uncanny_Automator\App\Transports\Restful\Addons\Routes\Plugin_Manager_Route;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;

/**
 * AddOns REST registration hub.
 *
 * Static-only. Owns the AddOns page's addon-group and plugin-manager routes.
 * Route handlers are class-string callables so they stay autoload-cold until
 * WP dispatches a matching request.
 */
final class Addons_Rest_Controller {

	use Restful_Permissions;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route segment for the addons surface.
	 *
	 * @var string
	 */
	private const REST_BASE = 'addons';

	/**
	 * Register every addons REST route. Called once per process from
	 * `Restful_Bootstrap::register_rest_routes()` on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function register_routes(): void {

		// GET /uap/v2/addons/get-list/{list_code}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get-list/(?P<list_code>\w+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Addons_List_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);

		// POST /uap/v2/addons/plugin-manager/{action}/{addon_id}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/plugin-manager/(?P<action>\w+)/(?P<addon_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( Plugin_Manager_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);
	}
}
