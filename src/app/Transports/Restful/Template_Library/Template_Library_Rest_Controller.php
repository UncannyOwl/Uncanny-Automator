<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Template_Library;

use Uncanny_Automator\App\Transports\Restful\Template_Library\Routes\Get_Template_Preview_Route;
use Uncanny_Automator\App\Transports\Restful\Template_Library\Routes\Get_Templates_Route;
use Uncanny_Automator\App\Transports\Restful\Template_Library\Routes\Import_Template_Route;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Arg;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;

/**
 * Template-library REST registration hub.
 *
 * Static-only. Owns the three `recipe-template/*` routes the new
 * template-library frontend consumes. Route handlers are class-string
 * callables so they stay autoload-cold until WP dispatches a matching
 * request.
 */
final class Template_Library_Rest_Controller {

	use Restful_Permissions;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route segment for the template-library surface.
	 *
	 * @var string
	 */
	private const REST_BASE = 'recipe-template';

	/**
	 * Register every template-library REST route. Called once per process
	 * from `Restful_Bootstrap::register_rest_routes()` on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function register_routes(): void {

		// GET /uap/v2/recipe-template/get
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Templates_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);

		// GET /uap/v2/recipe-template/{id}/preview
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<id>\d+)/preview',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Template_Preview_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'id' => Rest_Arg::positive_int(),
					),
				),
			)
		);

		// POST /uap/v2/recipe-template/import
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/import',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( Import_Template_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					/*
					 * No `args` schema for `template_id` — intentionally.
					 * WP_REST_Server::dispatch() runs has_valid_params() /
					 * sanitize_params() BEFORE permission_callback, so any
					 * registered required flag, validate_callback, or
					 * sanitize_callback would reject or mutate the param
					 * pre-permission: a bad unauthenticated request would
					 * 400 (WP's rest_invalid_param shape) instead of 403,
					 * and absint would silently turn -5 into 5. Permission
					 * runs first; Import_Template_Route::handle() then does
					 * ALL template_id validation.
					 */
				),
			)
		);
	}
}
