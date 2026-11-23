<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Template_Library\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library\Template_Import_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_Error;
use WP_REST_Request;

/**
 * POST /uap/v2/recipe-template/import — imports a template into a new
 * draft recipe, or into the blank draft `recipe_id` names, and returns the
 * redirect URL for the recipe builder and the recipe's id.
 *
 * On posts storage that can't roll a fill back, a blank draft is replaced:
 * the reply names a new recipe holding the template, and the draft is deleted.
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
	 * `recipe_id` follows the same rule. A value that is sent but invalid is
	 * refused with `invalid_recipe_id`: it must never read as absent, which
	 * would create a new recipe and leave the owner's draft empty.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$template_id = $request->get_param( 'template_id' );

		if ( ! is_numeric( $template_id ) || 0 >= (int) $template_id ) {
			return self::failure( 'A positive template_id is required.', 400, 'invalid_template_id' );
		}

		$recipe_id = $request->get_param( 'recipe_id' );

		if ( null !== $recipe_id && ! self::is_positive_integer( $recipe_id ) ) {
			return self::failure( 'recipe_id must be a positive integer.', 400, 'invalid_recipe_id' );
		}

		$result = Template_Import_Service::instance()->import( (int) $template_id, null === $recipe_id ? null : (int) $recipe_id );

		if ( is_wp_error( $result ) ) {
			return self::failure( $result->get_error_message(), Route_Error_Status::for_error( $result ), (string) $result->get_error_code() );
		}

		return self::success( $result );
	}

	/**
	 * Whether a raw request value is a positive whole number.
	 *
	 * @since 8.1.0
	 *
	 * @param mixed $value The raw value.
	 * @return bool
	 */
	private static function is_positive_integer( $value ): bool {
		return ! is_bool( $value ) && false !== filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
	}
}
