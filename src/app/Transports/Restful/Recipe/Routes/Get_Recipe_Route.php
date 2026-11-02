<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception;
use Uncanny_Automator\App\Transports\Restful\Recipe\Recipe_Response;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * `GET /uap/v2/recipe/{recipe_id}` — ModelRecipe wire read.
 *
 * Registered by `Recipe_Rest_Controller::register_routes()`. Delegates
 * to the recipe-object bridge's cache-first read; returns 404 when the
 * recipe id doesn't resolve.
 */
final class Get_Recipe_Route {

	use Rest_Responses;

	/**
	 * GET handler. Returns the ModelRecipe wire array under `data.recipe`.
	 *
	 * @param WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$recipe_id = (int) $request['recipe_id'];

		try {
			$body = Recipe_Response::body( $recipe_id );
		} catch ( Legacy_Storage_Migration_Exception $e ) {
			return self::failure( esc_html( $e->getMessage() ), 500, 'automator_recipe_legacy_migration_failed' );
		}

		if ( null === $body['recipe'] ) {
			return self::failure( esc_html_x( 'Recipe not found.', 'Get recipe REST error', 'uncanny-automator' ), 404, 'automator_recipe_not_found' );
		}

		return self::success( $body );
	}
}
