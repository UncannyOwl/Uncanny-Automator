<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Migration;

use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;

/**
 * Loops fold-in migrator (Hybrid E).
 *
 * Renames legacy `uo-loop` posts to `uo-block` (`block_type='loop'`) and
 * legacy `uo-loop-filter` posts to `uo-block-modifier`
 * (`block_modifier_type='loop_filter'`) in place — post ids are preserved
 * so children's `post_parent` FKs keep working without a re-walk.
 *
 * **Order-independent.** The migrator runs in two passes — fold loops,
 * then fold filters — but filter discovery is scoped to the recipe
 * (not to a single loop_id), so filters insert-able mid-flight (after
 * their loop has already folded, e.g. via incremental save_post hooks)
 * are caught the next time `migrate()` runs. Both passes are idempotent:
 * post-type-restricted lookups skip already-folded posts.
 *
 * No recipe-level watermark — earlier versions stamped one, but that
 * prevented loops created in the legacy builder AFTER migration from
 * being folded in (caught by the `recipe-tree` CLI on a real recipe
 * with a stray uo-loop).
 *
 * Trigger point: only {@see Loop_Fold_Bulk_Migration}, the site-wide pass
 * {@see Migration_Hooks::maybe_run_loop_fold_bulk_migration()} runs on `init`.
 * It is option-gated, recorded as done only when every recipe folded, and
 * forgotten by a database upgrade (`Automator_DB::activation()`). Neither the
 * tree builder nor a recipe read folds a loop: a `uo-loop` the pass has not
 * folded yet is not in the recipe tree.
 *
 * Reversibility: inverse SQL within the deprecation window restores the
 * original post types. See
 * `docs/superpowers/plans/2026-05-04-block-modifier-storage-table.md`.
 */
class Loop_To_Block_Migrator {

	/**
	 * Legacy watermark key. Retained as a constant so existing recipes
	 * with the flag stamped don't carry stale meta forever — the
	 * cleanup pass in {@see self::migrate()} clears it on first run.
	 *
	 * @var string
	 */
	const WATERMARK_KEY = '_loops_migrated_to_blocks';

	/**
	 * Block database store — owns the post-type rename + meta stamping for
	 * `uo-loop` → `uo-block`.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Block-modifier database store — owns the post-type rename + meta
	 * stamping for `uo-loop-filter` → `uo-block-modifier`.
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Bridge for the per-recipe watermark flag.
	 *
	 * @var Recipe_Legacy_Meta_Store
	 */
	private Recipe_Legacy_Meta_Store $legacy_meta_store;

	/**
	 * Constructor.
	 *
	 * @param Block_Store                    $block_store        Block database store.
	 * @param Block_Modifier_Store           $modifier_store     Block-modifier database store.
	 * @param Recipe_Legacy_Meta_Store|null $legacy_meta_store Store for the recipe-level watermark; defaults to the WP post-meta impl.
	 */
	public function __construct(
		Block_Store $block_store,
		Block_Modifier_Store $modifier_store,
		?Recipe_Legacy_Meta_Store $legacy_meta_store = null
	) {
		$this->block_store       = $block_store;
		$this->modifier_store    = $modifier_store;
		$this->legacy_meta_store = $legacy_meta_store ?? new WP_Recipe_Legacy_Meta_Store();
	}

	/**
	 * Migrate a single recipe's loops + loop filters in place.
	 *
	 * No-op when the watermark is set or the recipe has no loops.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return int[] IDs of posts that were renamed (loops + loop filters).
	 * @throws \Exception When a post's rows or rename are not stored; the posts not yet renamed keep their legacy type.
	 */
	public function migrate( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$migrated = array();

		// Pass 1: fold every unfolded `uo-loop` directly under this
		// recipe into a `uo-block(loop)`. Idempotent — the lookup only
		// returns post_type=uo-loop, so already-folded loops are skipped.
		foreach ( $this->find_recipe_loop_ids( $recipe_id ) as $loop_id ) {
			$this->block_store->adopt_post(
				$loop_id,
				Block_Type::LOOP,
				$recipe_id,
				sprintf( 'recipe_%d:loop:%d', $recipe_id, $loop_id )
			);
			$migrated[] = $loop_id;
		}

		// Pass 2: fold every `uo-loop-filter` whose parent is a loop
		// (folded or unfolded) under this recipe. Recipe-scoped, not
		// per-loop — that's why the migrator is order-independent: a
		// filter inserted AFTER its loop folded is still reachable on the
		// next migrate() pass. Idempotent: only matches
		// posts still typed `uo-loop-filter`.
		foreach ( $this->find_recipe_loop_filter_ids( $recipe_id ) as $filter_id => $parent_block_id ) {
			$this->modifier_store->adopt_post(
				$filter_id,
				'loop_filter',
				$recipe_id,
				$parent_block_id,
				sprintf( 'recipe_%d:loop:%d:filter:%d', $recipe_id, $parent_block_id, $filter_id )
			);
			$migrated[] = $filter_id;
		}

		$this->legacy_meta_store->clear_flag( $recipe_id, self::WATERMARK_KEY );

		return $migrated;
	}

	/**
	 * Find every legacy `uo-loop` post under the given recipe.
	 *
	 * Direct WP query — `Loop_Db` exposes filtered helpers but not a "find
	 * loops by post_parent only" method. The migrator stays lean.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return int[]
	 */
	private function find_recipe_loop_ids( int $recipe_id ): array {
		$ids = get_posts(
			array(
				'post_type'      => AUTOMATOR_POST_TYPE_LOOP,
				'post_parent'    => $recipe_id,
				'post_status'    => 'any',
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'fields'         => 'ids',
			)
		);

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Find every `uo-loop-filter` post whose parent is a loop directly
	 * under the given recipe, regardless of whether the parent has been
	 * folded yet.
	 *
	 * The `OR` in the WHERE clause matches both shapes:
	 *
	 *  - `p.post_type = 'uo-loop'` — the legacy pre-fold state.
	 *  - `p.post_type = 'uo-block' AND block_type meta = 'loop'` — the
	 *    folded state.
	 *
	 * This is what makes the migrator order-independent. In a single-shot
	 * migrate() call, Pass 1 folds the loops first and Pass 2 catches
	 * filters under their now-folded parents (the second branch matches).
	 * When `migrate()` is called incrementally (per save_post hook, with
	 * filters inserted after their loops have already been folded by a
	 * previous call), Pass 1 is a no-op but Pass 2 still finds the new
	 * filters under the folded parents.
	 *
	 * @return array<int,int> Map of filter_id → parent_block_id (the loop's post id).
	 */
	private function find_recipe_loop_filter_ids( int $recipe_id ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; joins posts and postmeta, and must read live rows.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.ID AS filter_id, f.post_parent AS block_id
				 FROM {$wpdb->posts} f
				 INNER JOIN {$wpdb->posts} p ON f.post_parent = p.ID
				 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'block_type'
				 WHERE f.post_type = %s
				   AND p.post_parent = %d
				   AND (
				        p.post_type = %s
				     OR ( p.post_type = 'uo-block' AND pm.meta_value = 'loop' )
				   )",
				AUTOMATOR_POST_TYPE_LOOP_FILTER,
				$recipe_id,
				AUTOMATOR_POST_TYPE_LOOP
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['filter_id'] ] = (int) $row['block_id'];
		}

		return $out;
	}
}
