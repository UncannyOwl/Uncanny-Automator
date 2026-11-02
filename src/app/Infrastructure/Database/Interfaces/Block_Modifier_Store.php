<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Block Modifier Store Interface.
 *
 * Paradigm-agnostic contract for persisting `block_modifier` rows. A row is a
 * leaf attached to a parent `uo-block` post and discriminated by
 * `block_modifier_type` ('filter_condition', 'loop_filter', future opaque).
 *
 * Two implementations are possible against this interface:
 *
 *  - {@see \Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Modifier_Store}
 *    — backs rows with `uo-block-modifier` posts + `wp_postmeta`. Ships today.
 *  - `Block_Modifier_Store_Db` (deferred) — `wpdb`-backed custom-table impl.
 *    Materialised only if/when the team commits to wholesale runtime
 *    migration off CPTs. Same interface, different storage; switching is a
 *    one-line factory swap in {@see \Uncanny_Automator\App\Infrastructure\Database\Database}.
 *
 * Callers depend on the interface, never on `wp_postmeta` (or any future
 * column layout) directly.
 */
interface Block_Modifier_Store {

	/**
	 * Create a new modifier row.
	 *
	 * @param int         $recipe_id           Recipe post id (FK → uo-recipe). Stamped explicitly
	 *                                          for indexed cross-recipe lookup, even though it is
	 *                                          recoverable by walking up post_parent.
	 * @param int         $parent_block_id     Parent block post id (FK → uo-block).
	 * @param string      $block_modifier_type Discriminator: 'filter_condition' | 'loop_filter' | future opaque.
	 * @param string      $integration_code    Integration code, e.g. 'WP', 'GEN'.
	 * @param string      $code                Modifier code, e.g. 'TOKEN_MEETS_CONDITION'.
	 * @param array       $fields              JSON-shaped FieldsOutput. Stored as JSON-encoded post-meta.
	 * @param array       $backup              Integration display payload — `integration_name`, `sentence`, `sentence_html`.
	 * @param int         $menu_order          Order within the parent block.
	 * @param string|null $legacy_id           Legacy string-hash id (filter conditions); null for loop_filter (numeric post id preserved through rename).
	 * @param string|null $synthesis_key       Universal backup breadcrumb. Null for native creates; populated during migration / legacy save resync.
	 *
	 * @return int New modifier row id.
	 * @throws \Exception If creation fails.
	 */
	public function create(
		int $recipe_id,
		int $parent_block_id,
		string $block_modifier_type,
		string $integration_code,
		string $code,
		array $fields = array(),
		array $backup = array(),
		int $menu_order = 0,
		?string $legacy_id = null,
		?string $synthesis_key = null
	): int;

	/**
	 * Get a modifier row by id.
	 *
	 * @param int $modifier_id Modifier row id.
	 *
	 * @return array|null Hydrated row data or null if not found.
	 */
	public function get( int $modifier_id ): ?array;

	/**
	 * Get all modifier rows for a parent block, ordered by menu_order.
	 *
	 * @param int         $parent_block_id     Parent block post id.
	 * @param string|null $block_modifier_type Optional discriminator narrowing. When null, returns all kinds.
	 *
	 * @return array Array of hydrated row data.
	 */
	public function get_by_block( int $parent_block_id, ?string $block_modifier_type = null ): array;

	/**
	 * Get all modifier rows scoped to a recipe, ordered by menu_order.
	 *
	 * Single-query lookup over indexed `recipe_id` postmeta — same time class
	 * as a denormalised column on a custom table.
	 *
	 * @param int         $recipe_id           Recipe post id.
	 * @param string|null $block_modifier_type Optional discriminator narrowing.
	 *
	 * @return array Array of hydrated row data.
	 */
	public function get_by_recipe( int $recipe_id, ?string $block_modifier_type = null ): array;

	/**
	 * Lookup by `_synthesis_key` breadcrumb. Returns the first match — synthesis_key
	 * is descriptive, not enforced unique. `legacy_id` is the primary lookup.
	 *
	 * @param string $synthesis_key Colon-delimited path, e.g. `recipe_388284:filter:morissneqvjc7i4qnj9:condition:moristyc48i37bw5sy6`.
	 *
	 * @return array|null Hydrated row data or null if not found.
	 */
	public function get_by_synthesis_key( string $synthesis_key ): ?array;

	/**
	 * Update a modifier row in place.
	 *
	 * Supported keys: `integration_code`, `code`, `fields`, `backup`,
	 * `legacy_id`, `synthesis_key`, `menu_order`. Discriminator
	 * (`block_modifier_type`) and parent (`parent_block_id`, `recipe_id`)
	 * are write-once; create a new row instead.
	 *
	 * @param int   $modifier_id Modifier row id.
	 * @param array $data        Fields to update.
	 *
	 * @return bool True on success.
	 * @throws \Exception If update fails or row not found.
	 */
	public function update( int $modifier_id, array $data ): bool;

	/**
	 * Delete a modifier row.
	 *
	 * @param int $modifier_id Modifier row id.
	 *
	 * @return bool True on success.
	 * @throws \Exception If delete fails or row not found.
	 */
	public function delete( int $modifier_id ): bool;

	/**
	 * Cascade-delete every modifier row under a parent block.
	 *
	 * Type-agnostic — deletes every kind of modifier under the block. Safe
	 * for filter blocks (filter_condition rows), loop blocks (loop_filter
	 * rows), and future block kinds in one pass.
	 *
	 * @param int $parent_block_id Parent block post id.
	 *
	 * @return void
	 */
	public function delete_by_block( int $parent_block_id ): void;

	/**
	 * Cascade-delete every modifier row under a recipe.
	 *
	 * Wired to `before_delete_post` for `uo-recipe` so trash/cleanup
	 * removes the entire modifier subtree along with the recipe.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return void
	 */
	public function delete_by_recipe( int $recipe_id ): void;

	/**
	 * Whether a modifier row exists.
	 *
	 * @param int $modifier_id Modifier row id.
	 *
	 * @return bool True if exists.
	 */
	public function exists( int $modifier_id ): bool;

	/**
	 * Site-wide usage counts for modifiers under parent blocks of the given
	 * type across published recipes, keyed "INTEGRATION:CODE" => count.
	 *
	 * Resolves the modifier kind via the parent uo-block's block_type — a
	 * filter block carries filter_condition modifiers, a loop block carries
	 * loop_filter modifiers (the discriminator meta is self-healed on read,
	 * so it is not trusted for a bulk aggregate). The recipe resolves via the
	 * modifier's own recipe_id meta — stamped on every row since the post
	 * type shipped — so modifiers on nested parent blocks count correctly.
	 *
	 * @param string $block_type Parent block type ('filter' | 'loop').
	 *
	 * @return array<string,int>
	 */
	public function count_usage_by_parent_block_type( string $block_type ): array;

	/**
	 * Usage per "INTEGRATION:CODE" for modifiers on blocks of the given type,
	 * in published and draft recipes: `all` (published and draft modifiers),
	 * `published` (published modifiers in published recipes) and `last_used` (newest
	 * modifier `post_date`, GMT unix). Resolved exactly as
	 * count_usage_by_parent_block_type() resolves.
	 *
	 * @since 8.0.0
	 *
	 * @param string $block_type Parent block type ('filter' | 'loop').
	 *
	 * @return array<string, array{published: int, all: int, last_used: int}>
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the query fails.
	 */
	public function usage_census_by_parent_block_type( string $block_type ): array;

	/**
	 * One row per published modifier under a published-recipe block of the given type:
	 * modifier id, recipe id, parent block id, integration code, modifier code. Same scope
	 * as count_usage_by_parent_block_type(), without the aggregation.
	 * Exactly one row per modifier; a duplicated meta row yields the first value by meta_id.
	 *
	 * @param string $block_type Parent block type ('filter' | 'loop').
	 *
	 * @return list<array{modifier_id: int, recipe_id: int, parent_block_id: int, integration_code: string, code: string}>
	 */
	public function rows_in_published_recipes( string $block_type ): array;

	/**
	 * Adopt an existing post as a modifier row.
	 *
	 * Used by the loops fold-in to rename `uo-loop-filter` posts in place:
	 * the post's `post_type` becomes `uo-block-modifier`, the discriminator
	 * is stamped, and `recipe_id` / `parent_block_id` / `_synthesis_key`
	 * are recorded. Post id is preserved so post_parent FKs keep working.
	 *
	 * Idempotent — repeated calls with the same arguments are a no-op write.
	 *
	 * Each row and the rename are read back. The post keeps its legacy type until every row is
	 * stored, and a row or rename that was not stored throws, so the caller can try again.
	 *
	 * @param int         $post_id              Post id to adopt (legacy CPT row).
	 * @param string      $block_modifier_type  Discriminator value to stamp.
	 * @param int|null    $recipe_id            Owning recipe id.
	 * @param int|null    $parent_block_id      Parent block post id (defaults to post_parent of the row).
	 * @param string|null $synthesis_key        Universal backup breadcrumb.
	 *
	 * @return void
	 * @throws \Exception When a row or the rename is not stored.
	 */
	public function adopt_post(
		int $post_id,
		string $block_modifier_type,
		?int $recipe_id = null,
		?int $parent_block_id = null,
		?string $synthesis_key = null
	): void;
}
