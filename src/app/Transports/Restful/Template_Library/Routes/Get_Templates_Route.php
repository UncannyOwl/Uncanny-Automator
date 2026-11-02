<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Template_Library\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library\Template_Feed_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_Error;
use WP_REST_Request;

/**
 * GET /uap/v2/recipe-template/get — the enriched template-library feed.
 */
final class Get_Templates_Route {

	use Rest_Responses;

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$feed = Template_Feed_Service::instance()->get_feed();

		if ( is_wp_error( $feed ) ) {
			return self::failure( $feed->get_error_message(), Route_Error_Status::for_error( $feed ), (string) $feed->get_error_code() );
		}

		return self::success( $feed );
	}
}
