<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Logs\Routes;

use Uncanny_Automator\App\Recipe_Logs\Lists\Services\Log_Filters_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;

/**
 * Thin handler for `GET /uap/v2/logs/filters`. Delegates to
 * Log_Filters_Service; wraps the options in the envelope.
 *
 * @since 8.1.0
 */
final class Get_Log_Filters_Route {

	use Rest_Responses;

	/**
	 * The filters' options.
	 *
	 * @param \WP_REST_Request $request REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle( \WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- The REST callback signature.
		return self::success( Log_Filters_Service::instance()->get_filters() );
	}
}
