<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Reorder_Store;

/**
 * WordPress implementation of Recipe_Reorder_Store.
 *
 * Single seam that writes `menu_order` for any reorderable recipe child
 * post type. Direct `wp_update_post` — no per-aggregate logic, no meta
 * updates, no derived fields. Reorder is a column-level concern.
 *
 * Schema-cache invalidation rides the `save_post_<child>` hook that `wp_update_post` fires (Recipe_Schema_Lifecycle), so this store does not call `Recipe_Cache::invalidate()` itself.
 */
final class WP_Recipe_Reorder_Store implements Recipe_Reorder_Store {

	/**
	 * Move a single row to a new position among its siblings.
	 *
	 * No-ops when the row already sits at that position. A full-tree
	 * reconcile submits every sibling, not just the moved ones, so on a
	 * typical reorder most rows arrive with the position they already have —
	 * `wp_update_post` does not skip an identical write, and each one costs a
	 * post update plus `save_post` / `wp_after_insert_post` / `post_updated`
	 * and their listeners.
	 *
	 * The read is object-cached: the caller has just walked the persisted
	 * tree, so these posts are warm.
	 *
	 * A missing post still falls through to `wp_update_post` so its failure
	 * surfaces exactly as it did before rather than being swallowed here.
	 *
	 * @param int $post_id    Post id to move.
	 * @param int $menu_order Zero-based position.
	 *
	 * @return void
	 */
	public function set_menu_order( int $post_id, int $menu_order ): void {
		$current = get_post( $post_id );

		if ( $current instanceof \WP_Post && (int) $current->menu_order === $menu_order ) {
			return;
		}

		wp_update_post(
			array(
				'ID'         => $post_id,
				'menu_order' => $menu_order,
			)
		);
	}
}
