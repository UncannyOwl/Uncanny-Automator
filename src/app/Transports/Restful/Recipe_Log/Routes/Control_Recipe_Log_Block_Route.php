<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe_Log\Routes;

use Uncanny_Automator\App\Bridge\Automator_Recipe_Runner_Bridge;

/**
 * Thin handler for POST /uap/v2/recipe-log/block/process/{process_code}/{action}.
 * Dispatches to the runner's block-control registry; wraps the result in the
 * WP-REST `{ success, data }` envelope the frontend unwraps.
 */
final class Control_Recipe_Log_Block_Route {

	/**
	 * Apply one control action to the process the code names and wrap the result in the REST envelope.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error The `{ success, data }` envelope, or the registry's WP_Error.
	 */
	public static function handle( \WP_REST_Request $request ) {
		$result = ( new Automator_Recipe_Runner_Bridge() )->block_controls()->handle(
			rawurldecode( (string) $request->get_param( 'process_code' ) ),
			(string) $request->get_param( 'action' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}
}
