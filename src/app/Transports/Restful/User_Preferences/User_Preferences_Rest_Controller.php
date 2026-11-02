<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\User_Preferences;

use Uncanny_Automator\App\Transports\Restful\User_Preferences\Routes\Get_Recipe_View_Preferences_Route;
use Uncanny_Automator\App\Transports\Restful\User_Preferences\Routes\Update_Recipe_View_Preferences_Route;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;

/**
 * User preferences REST registration hub. Static-only; thin (Rule 11).
 *
 * The current user's own preferences, under the REST nonce and the Automator
 * capability: the same check as the recipe builder and recipe log routes.
 *
 * @since 8.0.0
 */
final class User_Preferences_Rest_Controller {

	use Restful_Permissions;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Register the user preference routes. Called from `Restful_Bootstrap::register_rest_routes()`.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public static function register_routes(): void {

		// GET /uap/v2/user/preferences/recipe-view
		register_rest_route(
			self::NAMESPACE_ID,
			'/user/preferences/recipe-view',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Recipe_View_Preferences_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);

		// POST /uap/v2/user/preferences/recipe-view/update
		register_rest_route(
			self::NAMESPACE_ID,
			'/user/preferences/recipe-view/update',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( Update_Recipe_View_Preferences_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);
	}
}
