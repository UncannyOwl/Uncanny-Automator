<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Template_Library\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library\Template_Preview_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_Error;
use WP_REST_Request;

/**
 * GET /uap/v2/recipe-template/{id}/preview — single-recipe wire preview
 * of a template, resolved from the upstream library feed or its legacy
 * export.
 */
final class Get_Template_Preview_Route {

	use Rest_Responses;

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$body = Template_Preview_Service::instance()->get_preview( (int) $request['id'] );

		if ( is_wp_error( $body ) ) {
			return self::failure( $body->get_error_message(), Route_Error_Status::for_error( $body ), (string) $body->get_error_code() );
		}

		return self::success( $body );
	}
}
