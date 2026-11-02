<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Integrations\Routes;

use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET /uap/v2/integrations/get/{scope}` — scope-filtered catalog.
 *
 * Registered by `Integrations_Rest_Controller::register_routes()`.
 * Scoped means certain properties of an integration are dynamically
 * resolved based on the requested scope:
 *
 * - Usage — how often and how recently the site's recipes used the
 *   integration in this scope (`usage.count`, `usage.last_used`).
 * - Dependencies — Pro-license requirements can vary by scope.
 * - Visibility — integrations with no items in the requested scope are
 *   excluded entirely.
 *
 * Allowed scope values: `trigger`, `action`,
 * `filter_condition`, `loop_filter` (validated by
 * `Integrations_Rest_Controller::validate_scope`).
 */
final class Get_Scoped_Integrations_Route {

	/**
	 * GET handler. Returns the scoped integrations payload.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$scope         = (string) $request->get_param( 'scope' );
		$query_service = Integration_Query_Service::get_instance();

		return new WP_REST_Response( $query_service->get_scoped_integrations( $scope, true ), 200 );
	}
}
