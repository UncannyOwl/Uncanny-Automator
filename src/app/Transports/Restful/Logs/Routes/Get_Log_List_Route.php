<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Logs\Routes;

use Uncanny_Automator\App\Recipe_Logs\Lists\Services\Log_List_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;

/**
 * Thin handler for `GET /uap/v2/logs/{recipe-runs|trigger-logs|action-logs|failed-actions}`.
 * Delegates to Log_List_Service; wraps `{ entries, total }` in the envelope.
 *
 * @since 8.1.0
 */
final class Get_Log_List_Route {

	use Rest_Responses;

	/**
	 * One page of the list the route names.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( \WP_REST_Request $request ) {
		$page = Log_List_Service::instance()->get_page( (string) $request->get_param( 'kind' ), $request->get_query_params() );

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		return self::success( $page );
	}
}
