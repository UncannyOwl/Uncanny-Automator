<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe_Log\Routes;

use Uncanny_Automator\App\Recipe_Logs\Services\Recipe_Log_Query_Service;

/**
 * Thin handler for GET /uap/v2/recipe-log/{recipe_id}/{run_number}/{log_id}.
 * Delegates to Recipe_Log_Query_Service; wraps the array in the WP-REST
 * `{ success, data }` envelope the frontend unwraps.
 */
final class Get_Recipe_Log_Route {

	/**
	 * Resolve the recipe-log payload for the requested run and wrap it in the REST envelope.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error The `{ success, data }` envelope, or the underlying WP_Error.
	 */
	public static function handle( \WP_REST_Request $request ) {
		$payload = Recipe_Log_Query_Service::instance()->get_log_payload(
			(int) $request->get_param( 'recipe_id' ),
			(int) $request->get_param( 'run_number' ),
			(int) $request->get_param( 'log_id' )
		);

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $payload,
			)
		);
	}
}
