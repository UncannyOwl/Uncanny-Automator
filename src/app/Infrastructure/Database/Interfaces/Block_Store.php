<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Block Store Interface.
 *
 * Contract for persisting uo-block posts.
 *
 * Positional/meta mutators return void. Parent moves throw on a failed post
 * update so callers cannot report a move that was never saved.
 */
interface Block_Store {

	/**
	 * Create a new block, whole or not at all.
	 *
	 * `$recipe_id`, `$legacy_group_id`, and `$synthesis_key` are optional —
	 * the implementation backfills `recipe_id` from the parent chain when
	 * not provided so legacy callers continue to work.
	 *
	 * A block whose rows were not all stored is not created: the create throws and leaves
	 * nothing behind, or, when the request ends part-way, a block with no type, which
	 * {@see self::delete_unfinished()} removes.
	 *
	 * @param int         $parent_id        Parent ID (recipe or parent block).
	 * @param string      $block_type       Block type (filter, delay_schedule, loop).
	 * @param array       $fields           Block fields using the standard fields object pattern.
	 * @param int         $menu_order       Execution order within parent.
	 * @param int|null    $recipe_id        Owning recipe id for indexed cross-recipe lookup.
	 * @param string|null $legacy_group_id  Legacy `actions_conditions[i].id` (filter blocks only).
	 * @param string|null $synthesis_key    Universal backup breadcrumb captured at migration time.
	 * @param string|null $path_code        Target path of the parent block, stamped at insert.
	 *
	 * @return int New block post ID.
	 * @throws \Exception If creation fails.
	 */
	public function create(
		int $parent_id,
		string $block_type,
		array $fields = array(),
		int $menu_order = 0,
		?int $recipe_id = null,
		?string $legacy_group_id = null,
		?string $synthesis_key = null,
		?string $path_code = null
	): int;

	/**
	 * Delete the blocks under a recipe that a create left unfinished.
	 *
	 * A block anywhere under the recipe (found through its parents, so a block that never got its
	 * `recipe_id` row counts) is unfinished when it has no type and holds no post. A typed block is
	 * finished whether or not it has a `recipe_id` row, as the tree reads it. A block that holds
	 * anything is never deleted here: a create moves nothing into its block.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return int[] IDs of the deleted blocks.
	 * @throws \Exception When an unfinished block cannot be deleted.
	 */
	public function delete_unfinished( int $recipe_id ): array;

	/**
	 * Get a block by ID.
	 *
	 * @param int $block_id Block post ID.
	 *
	 * @return array|null Block data array or null if not found.
	 */
	public function get( int $block_id ): ?array;

	/**
	 * Get all blocks for a given parent, ordered by menu_order.
	 *
	 * @param int $parent_id Parent ID (recipe or parent block).
	 *
	 * @return array Array of block data arrays.
	 */
	public function get_by_parent( int $parent_id ): array;

	/**
	 * Update a block.
	 *
	 * @param int   $block_id Block post ID.
	 * @param array $data     Data to update (fields, menu_order).
	 *
	 * @return bool True on success.
	 * @throws \Exception If update fails.
	 */
	public function update( int $block_id, array $data ): bool;

	/**
	 * Delete a block and cascade delete all children.
	 *
	 * @param int $block_id Block post ID.
	 *
	 * @return bool True on success.
	 * @throws \Exception If delete fails.
	 */
	public function delete( int $block_id ): bool;

	/**
	 * Check if a block exists.
	 *
	 * @param int $block_id Block post ID.
	 *
	 * @return bool True if exists.
	 */
	public function exists( int $block_id ): bool;

	/**
	 * Get all child posts of a parent, ordered by menu_order.
	 *
	 * Retrieves both block children (uo-block) and item children (uo-action)
	 * in a single query, returning each with an `entity_type` discriminator.
	 * This enables the Block_Resolver to process all children uniformly
	 * and supports arbitrary nesting.
	 *
	 * Block rows carry: `entity_type='block'`, `id`, `parent_id`, `recipe_id`,
	 * `block_type`, `path_code`, `fields`, `menu_order`, `post_status`,
	 * `legacy_group_id`, `synthesis_key`, `post_type`, `iterable_expression`
	 * (the legacy loop-meta blob, empty array for non-loop blocks — kept
	 * on the row so tree-shape consumers don't need a Rule-3 post-meta read
	 * for folded `uo-block(loop)` entries).
	 *
	 * Item rows carry: `entity_type='item'`, `ID`, `post_status`, `post_parent`,
	 * `menu_order`, `meta`, `tokens`, `path_code`, `post_type`. The `path_code`
	 * and `post_type` keys let downstream consumers (notably
	 * `Recipe_Tree_Builder`) avoid `get_post()` / `get_post_meta()` calls
	 * on child rows — Rule 3 + Rule 12 boundary.
	 *
	 * @param int $parent_id Parent post ID (recipe or block).
	 *
	 * @return array Ordered array of child data arrays, each with `entity_type`
	 *               ('block' or 'item') for dispatch.
	 */
	public function get_children( int $parent_id ): array;

	/**
	 * Collect action ids that are direct (non-block) children of a block,
	 * in storage order.
	 *
	 * Used by `delay_schedule` wrappers — the actions a delay block wraps
	 * are its immediate children, never nested deeper.
	 *
	 * @param int $block_id Block post id.
	 * @return int[]
	 */
	public function collect_inner_action_ids( int $block_id ): array;

	/**
	 * Recursively collect every action id under a block's subtree.
	 *
	 * Walks nested `uo-block` children depth-first; returns each non-block
	 * descendant action id in traversal order. Used by consumers that
	 * need every executable action under a block regardless of depth
	 * (e.g. the run-log flow translator).
	 *
	 * @param int $block_id Block post id.
	 * @return int[]
	 */
	public function collect_descendant_action_ids( int $block_id ): array;

	/**
	 * Set the path_code meta on a child post.
	 *
	 * Assigns a child (action or nested block) to a specific execution path
	 * within its parent block. Blocks can define multiple paths representing
	 * branching logic (e.g. conditions met vs. not met). Currently only a
	 * single path per block type is used, but the mapping is in place
	 * to support multi-branch flows once the frontend UI supports it.
	 *
	 * @param int    $post_id   Child post ID (action or block).
	 * @param string $path_code Path code (e.g. FILTER-CONDITIONS-MET).
	 *
	 * @return void
	 */
	public function set_path_code( int $post_id, string $path_code ): void;

	/**
	 * Reparent a child post to a new parent.
	 *
	 * Changes the child's post_parent without modifying path_code.
	 *
	 * @param int $child_id      Child post ID (action or block).
	 * @param int $new_parent_id New parent post ID (recipe or block).
	 *
	 * @return void
	 * @throws \Exception If the parent update fails.
	 */
	public function reparent( int $child_id, int $new_parent_id ): void;

	/**
	 * Reparent a child post into a block and assign it to a path.
	 *
	 * Moves the child before assigning the destination path, so a refused
	 * parent update leaves the original path unchanged. These are separate writes.
	 *
	 * @param int    $child_id  Child post ID (action or block).
	 * @param int    $block_id  Target block post ID.
	 * @param string $path_code Path code (e.g. FILTER-CONDITIONS-MET, DELAY-COMPLETED).
	 *
	 * @return void
	 * @throws \Exception If the parent update fails.
	 */
	public function reparent_to_path( int $child_id, int $block_id, string $path_code ): void;

	/**
	 * Remove the path_code meta from a child post.
	 *
	 * @param int $post_id Child post ID (action or block).
	 *
	 * @return void
	 */
	public function delete_path_code( int $post_id ): void;

	/**
	 * All action-container ids for a recipe: every `uo-block` at any nesting
	 * depth (via the recipe_id meta link). The universe for post_parent__in
	 * queries.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return array<int> Container post IDs.
	 */
	public function get_recipe_container_ids( int $recipe_id ): array;

	/**
	 * Adopt an existing post as a `uo-block` of `$block_type`.
	 *
	 * Used by the loops fold-in to rename `uo-loop` posts in place: the
	 * post's `post_type` becomes `uo-block`, the discriminator post-meta
	 * (`block_type`) is stamped, and `recipe_id` / `_synthesis_key` are
	 * recorded for the indexed lookup + breadcrumb. Post id is preserved
	 * so children's `post_parent` keeps pointing at the same row.
	 *
	 * Idempotent — repeated calls with the same arguments are a no-op write.
	 *
	 * Each row and the rename are read back. The post keeps its legacy type until every row is
	 * stored, and a row or rename that was not stored throws, so the caller can try again.
	 *
	 * @param int         $post_id       Post id to adopt (legacy CPT row).
	 * @param string      $block_type    Block type to stamp.
	 * @param int|null    $recipe_id     Owning recipe id for indexed cross-recipe lookup.
	 * @param string|null $synthesis_key Universal backup breadcrumb.
	 *
	 * @return void
	 * @throws \Exception When a row or the rename is not stored.
	 */
	public function adopt_post( int $post_id, string $block_type, ?int $recipe_id = null, ?string $synthesis_key = null ): void;

	/**
	 * Published blocks of one type stamped to a published recipe, with their decoded fields.
	 *
	 * `fields` is the stored JSON decoded (empty when absent or invalid); `iterable_expression`
	 * is the stored loop expression as an array (empty for other block types). Ordered by
	 * recipe id then block id.
	 *
	 * @param string $block_type A Block_Type constant.
	 *
	 * `legacy_group_id` is the `actions_conditions[i].id` the condition migrator stamped on a filter block it made, '' on every other block. Exactly one row per block; a duplicated meta row yields the first value by meta_id.
	 *
	 * @return list<array{block_id: int, recipe_id: int, legacy_group_id: string, fields: array<string, mixed>, iterable_expression: array<string, mixed>}>
	 */
	public function rows_in_published_recipes( string $block_type ): array;
}
