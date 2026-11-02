<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Integrations\Routes;

use Uncanny_Automator\App\Integration_Catalog\Services\Entity_Suggestion_Service;
use Uncanny_Automator\App\Integration_Catalog\Services\Entity_Suggestion_Exception;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /uap/v2/integrations/ai-entity-suggestions/{recipe_id}/{kind}/{scope}/{parent_id}/{path_code}?
 *
 * Thin pass-through to Entity_Suggestion_Service. Resolves recipe existence
 * (404) before the edit-capability check (403) — `current_user_can('edit_post',
 * <nonexistent_id>)` returns false even for admins, so checking capability first
 * would wrongly 403 on a missing recipe. Response is raw (no envelope):
 * { "suggested_entities": [ { "integration_code", "code" }, ... ] }.
 */
final class Ai_Entity_Suggestions_Route {

	/**
	 * GET handler.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$recipe_id = (int) $request->get_param( 'recipe_id' );
		$kind      = (string) $request->get_param( 'kind' );
		$scope     = (string) $request->get_param( 'scope' );
		$parent_id = (string) $request->get_param( 'parent_id' );
		$path_code = $request->get_param( 'path_code' );
		$path_code = ( null === $path_code || '' === $path_code ) ? null : (string) $path_code;

		// Lightweight existence probe — the service hydrates the full Recipe
		// itself; hydrating twice per request just to 404 is wasted work.
		if ( null === Database::get_recipe_store()->get_wp_post( $recipe_id ) ) {
			return new WP_Error(
				'recipe_not_found',
				/* translators: REST API error message for a missing recipe. */
				esc_html_x( 'Recipe not found.', 'Automator', 'uncanny-automator' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $recipe_id ) ) {
			return new WP_Error(
				'cannot_edit_recipe',
				/* translators: REST API error message when the user lacks recipe edit capability. */
				esc_html_x( 'You cannot edit this recipe.', 'Automator', 'uncanny-automator' ),
				array( 'status' => 403 )
			);
		}

		try {
			$entities = ( new Entity_Suggestion_Service() )->suggest( $recipe_id, $kind, $scope, $parent_id, $path_code );
		} catch ( Entity_Suggestion_Exception $e ) {
			return new WP_Error(
				$e->get_error_code(),
				$e->getMessage(),
				array( 'status' => $e->get_status() )
			);
		}

		return new WP_REST_Response( array( 'suggested_entities' => $entities ), 200 );
	}
}
