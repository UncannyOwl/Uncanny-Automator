<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe_Log;

use Uncanny_Automator\App\Transports\Restful\Recipe_Log\Routes\Control_Recipe_Log_Block_Route;
use Uncanny_Automator\App\Transports\Restful\Recipe_Log\Routes\Get_Recipe_Log_Iterations_Route;
use Uncanny_Automator\App\Transports\Restful\Recipe_Log\Routes\Get_Recipe_Log_Route;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Arg;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;

/**
 * Recipe-log REST registration hub. Static-only; thin (Rule 11).
 */
final class Recipe_Log_Rest_Controller {

	use Restful_Permissions;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Register the recipe-log routes on rest_api_init.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_ID,
			'/recipe-log/(?P<recipe_id>\d+)/(?P<run_number>\d+)/(?P<log_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Recipe_Log_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id'  => Rest_Arg::positive_int(),
						'run_number' => Rest_Arg::positive_int(),
						'log_id'     => Rest_Arg::positive_int(),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_ID,
			'/recipe-log/(?P<recipe_id>\d+)/(?P<run_number>\d+)/(?P<log_id>\d+)/node/(?P<node_id>[^/]+)/iterations',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Recipe_Log_Iterations_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id'  => Rest_Arg::positive_int(),
						'run_number' => Rest_Arg::positive_int(),
						'log_id'     => Rest_Arg::positive_int(),
						'node_id'    => Rest_Arg::string(),
						'page'       => Rest_Arg::integer( null, false ),
						'per_page'   => Rest_Arg::integer( null, false ),
						'search'     => Rest_Arg::string( null, false ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_ID,
			'/recipe-log/block/process/(?P<process_code>[^/]+)/(?P<action>run_now|pause|resume|cancel)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( Control_Recipe_Log_Block_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'process_code' => Rest_Arg::string(),
						'action'       => Rest_Arg::string(),
					),
				),
			)
		);
	}
}
