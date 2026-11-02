<?php
/**
 * Shared parent validation for action tools.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;

/**
 * Trait HasValidParent
 *
 * Validates an action placement target. The parent model is
 * "recipe or container": every block type (`filter`, `delay_schedule`,
 * `loop`) supports nesting, so `parent_type` `loop` and `block` share one
 * container check — the block store's recipe_id link, which holds at any
 * nesting depth.
 */
trait HasValidParent {

	/**
	 * Validate parent_type and parent_id match.
	 *
	 * @param string $parent_type The declared parent type (recipe, loop or block).
	 * @param int    $parent_id   The parent ID to validate.
	 * @param int    $recipe_id   The recipe ID for context.
	 * @return array|null Error response if validation fails, null if valid.
	 */
	private function validate_parent( string $parent_type, int $parent_id, int $recipe_id ): ?array {
		$post = get_post( $parent_id );

		if ( ! $post ) {
			return Json_Rpc_Response::create_error_response(
				sprintf(
					'parent_id %d not found. Use list_recipes to find recipes, or get_recipe to find loops and blocks.',
					$parent_id
				)
			);
		}

		if ( 'recipe' === $parent_type ) {
			if ( AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
				return Json_Rpc_Response::create_error_response(
					sprintf(
						'parent_id %d is not a valid recipe (found post_type: %s). Use list_recipes to find recipes.',
						$parent_id,
						$post->post_type
					)
				);
			}

			if ( $parent_id !== $recipe_id ) {
				return Json_Rpc_Response::create_error_response(
					sprintf( 'parent_id %d does not match recipe_id %d. Use the same recipe ID for both values.', $parent_id, $recipe_id )
				);
			}

			return null;
		}

		// Container placement (loop or block). Blocks nest arbitrarily, so
		// ownership rides the block store's recipe_id link.
		if ( AUTOMATOR_POST_TYPE_BLOCK === $post->post_type ) {
			$block = Block_Crud_Service::instance()->get_block( $parent_id );
			if ( is_wp_error( $block ) || (int) ( $block['recipe_id'] ?? 0 ) !== $recipe_id ) {
				return Json_Rpc_Response::create_error_response(
					sprintf(
						'%s %d does not belong to recipe %d. Use get_recipe to inspect the recipe structure.',
						'loop' === $parent_type ? 'Loop' : 'Block',
						$parent_id,
						$recipe_id
					)
				);
			}

			return null;
		}

		return Json_Rpc_Response::create_error_response(
			sprintf(
				'parent_id %d is not a valid %s (found post_type: %s). Verify parent_type matches the actual parent. Use list_recipes for recipes, or get_recipe for loops and blocks.',
				$parent_id,
				$parent_type,
				$post->post_type
			)
		);
	}
}
