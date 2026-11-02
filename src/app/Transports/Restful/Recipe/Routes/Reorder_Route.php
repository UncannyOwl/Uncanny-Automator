<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Reorder_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Transports\Restful\Recipe\Legacy_Rest_Hooks;
use Uncanny_Automator\App\Transports\Restful\Recipe\Recipe_Response;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * `POST /uap/v2/recipe/{recipe_id}/reorder` — tree-aware reorder.
 *
 * Registered by `Recipe_Rest_Controller::register_routes()`. Consumed by
 * the recipe-builder UI's reorder mutation. Delegates the heavy lifting
 * (tree validation + per-slot menu_order writes) to
 * `Recipe_Reorder_Service`; wraps the result in the canonical
 * `{ recipe }` response envelope via `Recipe_Response::body()`. A reorder
 * of the recipe's actions fires the legacy REST API's
 * `automator_recipe_actions_order_updated` through `Legacy_Rest_Hooks`.
 */
final class Reorder_Route {

	use Rest_Responses;

	/**
	 * Reorder handler. Validates the tree-shaped body and applies per-slot
	 * menu_order writes via `Recipe_Reorder_Service`.
	 *
	 * @param WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$recipe_id = (int) $request['recipe_id'];

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$result = Recipe_Reorder_Service::instance()->reorder( $recipe_id, $body );
		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;

			return self::failure(
				$result->get_error_message(),
				$status,
				$result->get_error_code(),
				is_array( $error_data ) ? $error_data : array()
			);
		}

		$data = Recipe_Response::body( $recipe_id );
		if ( array_key_exists( 'actions', $body ) ) {
			Legacy_Rest_Hooks::action( 'automator_recipe_actions_order_updated', array( $recipe_id, self::top_level_action_ids( $data['recipe'] ), array_merge( array( 'success' => true ), $data ) ) );
		}

		return self::success(
			$data,
			esc_html_x( 'Recipe reordered', 'Recipe reorder success', 'uncanny-automator' )
		);
	}

	/**
	 * The recipe's top-level action ids in their new order.
	 *
	 * The legacy hook passed the builder's flat list of action ids. Blocks now sit
	 * between actions at the top level and have no place in that list, so only the
	 * actions are listed.
	 *
	 * @param array $recipe The recipe wire.
	 *
	 * @return int[]
	 */
	private static function top_level_action_ids( array $recipe ): array {
		$ids = array();
		foreach ( $recipe[ Wire_Key::FLOW ][ Wire_Key::CONTENT ] as $node ) {
			if ( Wire_Node_Type::ITEM === $node[ Wire_Key::TYPE ] ) {
				$ids[] = (int) $node[ Wire_Key::ID ];
			}
		}

		return $ids;
	}
}
