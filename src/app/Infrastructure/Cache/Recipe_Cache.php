<?php

declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Cache;

use Uncanny_Automator\App\Infrastructure\Database\Stores\Recipe_Schema_Store;

/**
 * Drops everything derived from one recipe after a write to it or to one of its children.
 *
 * It invalidates the canonical schema tree and bumps its write generation
 * (`Recipe_Schema_Store::invalidate()`), then clears the legacy recipes_object
 * entries the core cache handler keeps in the `automator` object-cache group
 * (`automator_recipes_data`, `automator_recipe_data_of_{id}`, `get_recipe_type`).
 * The legacy half goes when the recipes_object surface is retired; the name stays.
 *
 * ## When a store calls this
 *
 * Only for writes no WordPress hook covers:
 *
 * - **Meta-only writes.** A bare `update_post_meta()` / `delete_post_meta()`
 *   fires nothing `Recipe_Schema_Lifecycle` subscribes to.
 * - **Any method whose LAST write is meta.** The hook fired at the earlier post
 *   write, against pre-edit values; a reader arriving in that window persists a
 *   tree built from half-old meta and nothing bumps again. See
 *   `Store_Write_Ordering_Test`.
 * - **Any `uo-recipe` write.** The lifecycle subscribes `save_post_<type>` for
 *   the five CHILD post types only; recipes are covered by
 *   `transition_post_status`, which fires solely on an actual status change. A
 *   title or settings update therefore invalidates nothing on its own, which is
 *   why `WP_Recipe_Store` calls this on every write it performs.
 *
 * A post-level write (`wp_insert_post` / `wp_update_post` / `wp_delete_post`) on
 * a child post type needs no call: `Recipe_Schema_Lifecycle`'s
 * `save_post_<type>` and `after_delete_post` subscriptions already invalidate.
 * Adding one there is harmless but redundant — it costs an uncached generation
 * read plus two meta writes per call.
 *
 * Both halves are pinned by
 * `tests/wpunit/app/recipe-builder/recipe/schema/Store_Write_Invalidation_Test.php`,
 * including the two negative cases. Check there before adding or removing a call.
 *
 * Watch for a write that only CONDITIONALLY touches the post — `adopt_post()` in
 * the block and block-modifier stores writes meta unconditionally but flips
 * `post_type` only when it is not already correct, so on an idempotent re-adopt
 * no hook fires and the explicit call is load-bearing.
 */
class Recipe_Cache {

	/**
	 * Invalidate every cache derived from a recipe.
	 *
	 * @param int $recipe_id Recipe post id. Non-positive ids are ignored.
	 *
	 * @return void
	 */
	public static function invalidate( int $recipe_id ): void {
		if ( $recipe_id <= 0 ) {
			return;
		}

		Recipe_Schema_Store::instance()->invalidate( $recipe_id );

		if ( ! function_exists( 'Automator' ) ) {
			return;
		}

		$automator = Automator();

		if ( isset( $automator->cache ) && method_exists( $automator->cache, 'clear_automator_recipe_part_cache' ) ) {
			$automator->cache->clear_automator_recipe_part_cache( $recipe_id );
		}
	}
}
