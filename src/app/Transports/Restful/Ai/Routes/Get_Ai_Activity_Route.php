<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Ai\Routes;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Ai_Dashboard;
use Uncanny_Automator\App\Transports\Restful\Ai\Ai_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET /uap/v2/ai/activity`: one filtered, sorted page of AI activity.
 */
final class Get_Ai_Activity_Route {

	/** The Dashboard's query parameters; anything else is ignored. */
	private const PARAMS = array( 'page', 'per_page', 'client_id', 'type', 'result', 'date_from', 'date_to', 'search', 'orderby', 'order' );

	/**
	 * Handle the request.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$filters = array_intersect_key( $request->get_query_params(), array_flip( self::PARAMS ) );

		return Ai_Response::from( Hosted_Ai_Dashboard::instance()->activity( $filters ) );
	}
}
