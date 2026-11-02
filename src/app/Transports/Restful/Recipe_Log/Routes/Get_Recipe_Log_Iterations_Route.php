<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe_Log\Routes;

use Uncanny_Automator\App\Recipe_Logs\Services\Recipe_Log_Query_Service;

/**
 * Thin handler for GET /uap/v2/recipe-log/{recipe_id}/{run_number}/{log_id}/node/{node_id}/iterations.
 * Clamps pagination (page ≥ 1, per_page 1–100, defaults 1/20), delegates to
 * Recipe_Log_Query_Service, and wraps the result in the `{ success, data }`
 * envelope the frontend unwraps.
 */
final class Get_Recipe_Log_Iterations_Route {

	/**
	 * Default page size (matches the FE RECIPE_LOG_ITERATIONS_DEFAULT_PER_PAGE).
	 *
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 20;

	/**
	 * Hard page-size cap (matches the FE RECIPE_LOG_ITERATIONS_MAX_PER_PAGE).
	 *
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Resolve one iterations page for the requested node and wrap it in the REST envelope.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error The `{ success, data }` envelope, or the underlying WP_Error.
	 */
	public static function handle( \WP_REST_Request $request ) {
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) ( $request->get_param( 'per_page' ) ?? self::DEFAULT_PER_PAGE ) ) );

		$payload = Recipe_Log_Query_Service::instance()->get_iterations_payload(
			(int) $request->get_param( 'recipe_id' ),
			(int) $request->get_param( 'run_number' ),
			(int) $request->get_param( 'log_id' ),
			(string) $request->get_param( 'node_id' ),
			$page,
			$per_page,
			(string) ( $request->get_param( 'search' ) ?? '' )
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
