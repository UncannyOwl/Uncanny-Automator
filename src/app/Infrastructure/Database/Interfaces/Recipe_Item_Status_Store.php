<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Persistence boundary for recipe-child item post_status writes.
 *
 * Status is the second cross-cutting column-level concern (alongside
 * Recipe_Reorder_Store's menu_order) that touches every recipe item post
 * type the same way: a `post_status` column update. Per-domain stores
 * stay aggregate-shaped; this store owns the cross-domain status surface
 * so set-status doesn't fan out per-store `update_post_status` boilerplate.
 */
interface Recipe_Item_Status_Store {

	/**
	 * Set the `post_status` on a recipe-child item post.
	 *
	 * @since 8.0.0 Refuses a post that is not a trigger or action.
	 *
	 * @param int    $post_id Post ID of a recipe trigger or action (uo-trigger, uo-action).
	 * @param string $status  New post_status ('publish' or 'draft').
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception If $post_id is not a trigger or action post, or wp_update_post fails.
	 */
	public function update_post_status( int $post_id, string $status ): void;
}
