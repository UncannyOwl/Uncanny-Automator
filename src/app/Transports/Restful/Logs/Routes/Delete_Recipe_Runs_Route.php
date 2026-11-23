<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Logs\Routes;

use Uncanny_Automator\App\Recipe_Logs\Lists\Services\Recipe_Run_Delete_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;

/**
 * Thin handler for `DELETE /uap/v2/logs/recipe-runs` (body `log_ids`). Delegates to
 * Recipe_Run_Delete_Service; answers with the runs it really deleted.
 *
 * @since 8.1.0
 */
final class Delete_Recipe_Runs_Route {

	use Rest_Responses;

	/**
	 * Deletes the runs the current user may delete.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle( \WP_REST_Request $request ) {
		$deleted = Recipe_Run_Delete_Service::instance()->delete( (array) $request->get_param( 'log_ids' ) );

		return self::success( array( 'deleted_log_ids' => $deleted ) );
	}
}
