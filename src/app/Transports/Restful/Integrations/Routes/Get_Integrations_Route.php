<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Integrations\Routes;

use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET /uap/v2/integrations/get` — full unscoped integrations catalog.
 *
 * Registered by `Integrations_Rest_Controller::register_routes()`.
 * Returns each integration's static metadata (name, icon, available
 * triggers / actions, block-modifier rows, etc.) — no
 * user / license / context-dependent data.
 */
final class Get_Integrations_Route {

	/**
	 * GET handler. Returns the unscoped integrations catalog payload.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$query_service = Integration_Query_Service::get_instance();

		return new WP_REST_Response( $query_service->get_all_integrations_to_rest(), 200 );
	}
}
