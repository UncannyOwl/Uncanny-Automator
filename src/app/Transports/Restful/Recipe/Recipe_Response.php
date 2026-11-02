<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Wire_Service;

/**
 * The recipe REST surface's wire retrieval + response shape.
 *
 * `wire()` is the single place the schema-v3 recipe wire array is retrieved;
 * `body()` wraps it in the `{ recipe }` envelope every endpoint returns (reads,
 * reorder, item/block/block-modifier add/update/delete, set-status). One shape;
 * the legacy `recipes_object` blob is gone.
 *
 * `recipe` is `null` only when the id doesn't resolve to a `uo-recipe` post.
 * Read routes translate a null `recipe` into a 404; mutation responses return
 * it as-is (a 200 carrying post-mutation state) so the FE reconciles without a
 * refetch.
 */
final class Recipe_Response {

	/**
	 * Retrieve the recipe's schema-v3 wire array — the single wire-retrieval
	 * point for the recipe REST surface. `null` when the id doesn't resolve.
	 *
	 * @param int $recipe_id
	 * @return array<string, mixed>|null
	 */
	public static function wire( int $recipe_id ): ?array {
		return Recipe_Wire_Service::instance()->get_wire_array( $recipe_id );
	}

	/**
	 * Wrap the wire in the `{ recipe }` response envelope.
	 *
	 * @param int $recipe_id
	 * @return array{recipe: array<string, mixed>|null}
	 */
	public static function body( int $recipe_id ): array {
		return array( 'recipe' => self::wire( $recipe_id ) );
	}
}
