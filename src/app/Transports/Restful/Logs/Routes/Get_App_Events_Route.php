<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Logs\Routes;

use Uncanny_Automator\App\Recipe_Logs\App_Events\Services\App_Event_List_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;

/**
 * Thin handler for `GET /uap/v2/logs/app-events`. Delegates to
 * App_Event_List_Service; wraps the page in the envelope.
 *
 * @since 8.1.0
 */
final class Get_App_Events_Route {

	use Rest_Responses;

	/**
	 * One page of the app events the site received.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( \WP_REST_Request $request ) {
		$page = App_Event_List_Service::instance()->get_page( $request->get_query_params() );

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		return self::success( $page );
	}
}
