<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Logs;

use Uncanny_Automator\App\Transports\Restful\Logs\Routes\Delete_Recipe_Runs_Route;
use Uncanny_Automator\App\Transports\Restful\Logs\Routes\Get_App_Events_Route;
use Uncanny_Automator\App\Transports\Restful\Logs\Routes\Get_Log_Filters_Route;
use Uncanny_Automator\App\Transports\Restful\Logs\Routes\Get_Log_List_Route;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Arg;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;

/**
 * Logs page REST registration hub: the four list reads, the filters read,
 * the recipe-run delete and the App events read the Logs viewer calls. Static-only; thin (Rule 11).
 *
 * The routes take the Logs page's own capability (`automator_capability`)
 * and the REST nonce. Deleting a run also needs the admin capability, which
 * `Recipe_Run_Delete_Policy` checks per run.
 *
 * @since 8.1.0
 */
final class Logs_Rest_Controller {

	use Restful_Permissions;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * The App events parameters, all optional at the WordPress layer:
	 * App_Event_Query validates them and names a refused one.
	 *
	 * @var string[]
	 */
	private const APP_EVENT_PARAMS = array( 'page', 'per_page', 'integration', 'status', 'date_from', 'date_to', 'search', 'orderby', 'order' );

	/**
	 * The list parameters, all optional at the WordPress layer: the list's
	 * own query object validates them and names a refused one.
	 *
	 * @var string[]
	 */
	private const LIST_PARAMS = array( 'page', 'per_page', 'recipe_id', 'user_id', 'status', 'integration', 'endpoint', 'item_code', 'item_log_id', 'date_from', 'date_to', 'search', 'orderby', 'order' );

	/**
	 * Register the Logs page routes on rest_api_init.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		$list_args = array();

		foreach ( self::LIST_PARAMS as $param ) {
			$list_args[ $param ] = Rest_Arg::string( null, false );
		}

		register_rest_route(
			self::NAMESPACE_ID,
			'/logs/(?P<kind>recipe-runs|trigger-logs|action-logs|failed-actions)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Log_List_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => $list_args,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_ID,
			'/logs/filters',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Log_Filters_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_ID,
			'/logs/recipe-runs',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( Delete_Recipe_Runs_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'log_ids' => array(
							'required'          => true,
							'type'              => 'array',
							'minItems'          => 1,
							'items'             => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		$app_event_args = array();

		foreach ( self::APP_EVENT_PARAMS as $param ) {
			$app_event_args[ $param ] = Rest_Arg::string( null, false );
		}

		register_rest_route(
			self::NAMESPACE_ID,
			'/logs/app-events',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_App_Events_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'admin_permissions_check' ),
					'args'                => $app_event_args,
				),
			)
		);
	}

	/**
	 * The Logs page's own check, then the admin capability: an app event
	 * carries what the app sent (file names and paths), which only an
	 * administrator of Automator sees.
	 *
	 * @since 8.1.0
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return true|\WP_Error
	 */
	public static function admin_permissions_check( \WP_REST_Request $request ) {
		$allowed = self::permissions_check( $request );

		if ( true !== $allowed ) {
			return $allowed;
		}

		if ( ! current_user_can( automator_get_admin_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The admin capability is filterable.
			return new \WP_Error( 'rest_forbidden', esc_html_x( 'Only an administrator can see the app events.', 'Logs REST error', 'uncanny-automator' ), array( 'status' => 403 ) );
		}

		return true;
	}
}
