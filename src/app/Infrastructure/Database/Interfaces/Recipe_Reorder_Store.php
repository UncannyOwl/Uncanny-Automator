<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Persistence boundary for recipe-child sequencing writes.
 *
 * Reorder is the one cross-cutting concern that touches every reorderable
 * recipe child (uo-action, uo-block, uo-block-modifier) the
 * same way: a `menu_order` column update on a single post id. Per-domain
 * stores stay aggregate-shaped; this store owns the cross-domain
 * sequencing surface so reorder doesn't fan out per-store
 * `update_menu_order` boilerplate.
 *
 * Reads come from the canonical schema tree (Recipe_Tree_Builder /
 * Recipe_Schema_Store), not this store — sequencing writes have no
 * per-domain semantics, so there's no read counterpart here.
 */
interface Recipe_Reorder_Store {

	/**
	 * Set the `menu_order` on a recipe-child post.
	 *
	 * @param int $post_id    Post ID of any reorderable recipe child.
	 * @param int $menu_order New `menu_order` value (ordinal sequencing within siblings).
	 */
	public function set_menu_order( int $post_id, int $menu_order ): void;
}
