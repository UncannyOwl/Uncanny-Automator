<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Ai\Routes;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Ai_Dashboard;
use Uncanny_Automator\App\Transports\Restful\Ai\Ai_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET /uap/v2/ai/connections`: the AI apps connected through the hosted service.
 */
final class Get_Ai_Connections_Route {

	/**
	 * Handle the request.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$connections = Hosted_Ai_Dashboard::instance()->connections();

		return Ai_Response::from( is_array( $connections ) ? array( 'connections' => $connections ) : $connections );
	}
}
