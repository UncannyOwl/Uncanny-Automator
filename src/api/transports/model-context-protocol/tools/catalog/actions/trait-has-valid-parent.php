<?php
/**
 * Shared parent validation for action tools.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;

/**
 * Trait HasValidParent
 *
 * Validates that parent_type and parent_id match the expected post types
 * and that loops belong to the correct recipe.
 */
trait HasValidParent {

	/**
	 * Validate parent_type and parent_id match.
	 *
	 * Ensures the declared intent (parent_type) matches the actual post type of parent_id.
	 *
	 * @param string $parent_type The declared parent type (recipe or loop).
	 * @param int    $parent_id   The parent ID to validate.
	 * @param int    $recipe_id   The recipe ID for context.
	 * @return array|null Error response if validation fails, null if valid.
	 */
	private function validate_parent( string $parent_type, int $parent_id, int $recipe_id ): ?array {
		$post = get_post( $parent_id );

		if ( ! $post ) {
			return Json_Rpc_Response::create_error_response(
				sprintf(
					'parent_id %d not found. Use list_recipes to find recipes or loop_list with recipe_id to find loops.',
					$parent_id
				)
			);
		}

		$expected_post_type = 'recipe' === $parent_type ? 'uo-recipe' : 'uo-loop';
		$actual_post_type   = $post->post_type;

		if ( $actual_post_type !== $expected_post_type ) {
			$type_label = 'recipe' === $parent_type ? 'recipe' : 'loop';
			return Json_Rpc_Response::create_error_response(
				sprintf(
					'parent_id %d is not a valid %s (found post_type: %s). Verify parent_type matches the actual parent. Use list_recipes for recipes or loop_list for loops.',
					$parent_id,
					$type_label,
					$actual_post_type
				)
			);
		}

		// For loops, verify the loop belongs to the specified recipe.
		if ( 'loop' === $parent_type ) {
			$loop_recipe_id = (int) $post->post_parent;
			if ( $loop_recipe_id !== $recipe_id ) {
				return Json_Rpc_Response::create_error_response(
					sprintf(
						'Loop %d belongs to recipe %d, not recipe %d. Use loop_list with recipe_id=%d to find loops in the correct recipe.',
						$parent_id,
						$loop_recipe_id,
						$recipe_id,
						$recipe_id
					)
				);
			}
		}

		return null;
	}
}
