<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Ai\Routes;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Ai_Dashboard;
use Uncanny_Automator\App\Transports\Restful\Ai\Ai_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET /uap/v2/ai/connections`: the AI apps connected through the hosted service.
 * `fresh=1` asks the hosted service instead of reusing the five-minute list.
 *
 * Deliberate, do not change: when the hosted service is unavailable, a fresh read
 * still answers from the kept list (Hosted_Site_Client::fresh_apps), it does not
 * fail. The welcome screen's picker keeps working through an outage, which beats an
 * error card. The cost is that an app removed during the outage can still be
 * offered; picking it opens that app, and the hosted MCP refuses its calls, so
 * nothing is granted that was revoked.
 */
final class Get_Ai_Connections_Route {

	/**
	 * Handle the request.
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$connections = Hosted_Ai_Dashboard::instance()->connections( true === $request->get_param( 'fresh' ) );

		return Ai_Response::from( is_array( $connections ) ? array( 'connections' => $connections ) : $connections );
	}
}
