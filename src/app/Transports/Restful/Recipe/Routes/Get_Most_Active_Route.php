<?php

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Most_Active_Recipes_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET /uap/v2/recipe/get-most-active` — the Dashboard's Most active recipes.
 *
 * Registered by `Recipe_Rest_Controller::register_routes()`. Returns up to
 * five recipes under `data.recipes`, or a 500 when the run counts cannot be
 * read, so the card shows its error instead of an empty site.
 */
final class Get_Most_Active_Route {

	use Rest_Responses;

	/**
	 * GET handler.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		try {
			$recipes = ( new Most_Active_Recipes_Service() )->get_most_active();
		} catch ( Store_Exception $e ) {
			return self::error_reply( new WP_Error( 'automator_most_active_recipes_unavailable', esc_html_x( "Your most active recipes couldn't be loaded.", 'Most active recipes REST error', 'uncanny-automator' ), array( 'status' => 500 ) ) );
		}

		return self::success( array( 'recipes' => $recipes ) );
	}
}
