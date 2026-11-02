<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Template_Library\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library\Template_Import_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_Error;
use WP_REST_Request;

/**
 * POST /uap/v2/recipe-template/import — imports a template into a new
 * draft recipe and returns the redirect URL for the recipe builder.
 */
final class Import_Template_Route {

	use Rest_Responses;

	/**
	 * Handle the request.
	 *
	 * `template_id` has no registered args schema (see
	 * `Template_Library_Rest_Controller::register_routes()`): WP core runs
	 * args validation/sanitization before the permission callback, so any
	 * schema entry would reject or mutate the param pre-permission. All
	 * validation therefore happens here, after the permission gate —
	 * missing, non-numeric, zero, and negative values all fail with the
	 * app's `invalid_template_id` 400, never WP's `rest_invalid_param`.
	 * No absint anywhere: absint( -5 ) is 5, which would silently import
	 * the wrong template.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$template_id = $request->get_param( 'template_id' );

		if ( ! is_numeric( $template_id ) || 0 >= (int) $template_id ) {
			return self::failure( 'A positive template_id is required.', 400, 'invalid_template_id' );
		}

		$result = Template_Import_Service::instance()->import( (int) $template_id );

		if ( is_wp_error( $result ) ) {
			return self::failure( $result->get_error_message(), Route_Error_Status::for_error( $result ), (string) $result->get_error_code() );
		}

		return self::success( $result );
	}
}
