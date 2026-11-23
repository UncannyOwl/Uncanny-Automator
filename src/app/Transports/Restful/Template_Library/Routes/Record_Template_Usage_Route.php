<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Template_Library\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library\Template_Usage_Service;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /uap/v2/recipe-template/usage-event — records a library search, or a
 * picked category or integration, for the usage report.
 *
 * @since 8.1.0
 */
final class Record_Template_Usage_Route {

	/**
	 * Handle the request, body `{ type, value }`. Always answers 201 with an
	 * empty body: the library sends this without waiting, and whether the
	 * event was kept (reporting on, a known type, a value worth keeping,
	 * bucket not full) is not the caller's concern.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$type  = $request->get_param( 'type' );
		$value = $request->get_param( 'value' );

		if ( is_string( $type ) && is_string( $value ) ) {
			Template_Usage_Service::instance()->record( $type, $value );
		}

		return new WP_REST_Response( null, 201 );
	}
}
