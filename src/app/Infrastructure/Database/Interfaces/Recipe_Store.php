<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe;

interface Recipe_Store {

	/**
	 * Persist a Recipe (insert or update).
	 */
	public function save( Recipe $recipe ): Recipe;

	/**
	 * Load a Recipe by its ID.
	 */
	public function get( int $id ): ?Recipe;

	/**
	 * Delete a Recipe.
	 */
	public function delete( Recipe $recipe ): void;

	/**
	 * Targeted writer — set one editable recipe-level setting.
	 *
	 * Resolves the meta key via `Recipe_Setting_Id::meta_key()`, converts
	 * the wire-shape FieldsOutput to the legacy on-disk format using the
	 * store's existing formatters, writes the meta, and flushes the
	 * schema cache + Recipe_Cache.
	 *
	 * @param int    $recipe_id   The recipe post id.
	 * @param string $setting_id  One of `Recipe_Setting_Id::editable_ids()`.
	 * @param array  $fields      Wire FieldsOutput map for the setting.
	 *
	 * @throws \InvalidArgumentException If the setting_id is not editable.
	 * @throws \RuntimeException         If the meta write fails.
	 */
	public function update_setting( int $recipe_id, string $setting_id, array $fields ): void;

	/**
	 * Targeted writer — set the recipe's post status.
	 *
	 * Wraps `wp_update_post` and flushes the schema cache + Recipe_Cache.
	 * Idempotent with the `transition_post_status` lifecycle rebuild but
	 * applies the cache-flush invariant uniformly.
	 *
	 * @since 8.0.0 Refuses an id that is not a recipe post.
	 *
	 * @param int    $recipe_id The recipe post id.
	 * @param string $status    'publish' or 'draft'.
	 *
	 * @throws \InvalidArgumentException If $status isn't 'publish' or 'draft'.
	 * @throws \RuntimeException         If $recipe_id is not a recipe post, or wp_update_post fails.
	 */
	public function update_status( int $recipe_id, string $status ): void;

	/**
	 * Targeted writer — set the recipe's free-text notes.
	 *
	 * Empty string deletes the meta; otherwise writes it. Flushes the
	 * schema cache + Recipe_Cache so the next read rebuilds the tree.
	 *
	 * @since 8.0.0 Refuses an id that is not a recipe post.
	 *
	 * @param int    $recipe_id The recipe post id.
	 * @param string $notes     Sanitized notes value ('' to clear).
	 *
	 * @throws \RuntimeException If $recipe_id is not a recipe post.
	 */
	public function update_notes( int $recipe_id, string $notes ): void;

	/**
	 * Targeted reader — the recipe's free-text notes ('' when unset).
	 *
	 * Narrow meta read so the wire build never hydrates the full aggregate.
	 *
	 * @param int $recipe_id The recipe post id.
	 * @return string
	 */
	public function get_notes( int $recipe_id ): string;

	/**
	 * Raw stored recipe type, or null when no type has been chosen.
	 *
	 * Returns 'user'/'anonymous' for a valid `uap_recipe_type` meta; null when
	 * empty/absent/invalid. Unlike the aggregate getter (which collapses unset
	 * to 'user' for execution), this preserves the unset state for the wire's
	 * top-level `recipe_type` (FE type-chooser gate).
	 *
	 * @param int $recipe_id The recipe post id.
	 * @return string|null
	 */
	public function get_recipe_type_raw( int $recipe_id ): ?string;

	/**
	 * Targeted writer — set the recipe's post title.
	 *
	 * Wraps `wp_update_post` and flushes the schema cache + Recipe_Cache.
	 *
	 * @since 8.0.0 Refuses an id that is not a recipe post.
	 *
	 * @param int    $recipe_id The recipe post id.
	 * @param string $title     The new post title.
	 *
	 * @throws \RuntimeException If $recipe_id is not a recipe post, or wp_update_post fails.
	 */
	public function update_title( int $recipe_id, string $title ): void;

	/**
	 * Targeted writer — set the recipe's user type.
	 *
	 * Writes the `uap_recipe_type` meta and flushes the caches. The caller
	 * validates the value ('user' / 'anonymous').
	 *
	 * @param int    $recipe_id   The recipe post id.
	 * @param string $recipe_type 'user' | 'anonymous'.
	 * @return bool True when the meta write succeeded.
	 */
	public function set_recipe_type( int $recipe_id, string $recipe_type ): bool;

	/**
	 * Targeted writer — replace the recipe's `recipe_category` terms.
	 *
	 * Slugs are sanitized with `sanitize_title`; the set replaces any existing
	 * terms (empty array clears). Flushes the schema cache + Recipe_Cache.
	 *
	 * @param int   $recipe_id The recipe post id.
	 * @param array $slugs     Category slugs (empty set clears all).
	 */
	public function update_categories( int $recipe_id, array $slugs ): void;

	/**
	 * Targeted writer — replace the recipe's `recipe_tag` terms.
	 *
	 * @param int   $recipe_id The recipe post id.
	 * @param array $slugs     Tag slugs (empty set clears all).
	 */
	public function update_tags( int $recipe_id, array $slugs ): void;

	/**
	 * The recipe's assigned terms in a taxonomy.
	 *
	 * @param int    $recipe_id The recipe post id.
	 * @param string $taxonomy  'recipe_category' or 'recipe_tag'.
	 * @return \WP_Term[]
	 */
	public function get_assigned_terms( int $recipe_id, string $taxonomy ): array;

	/**
	 * Every term in a taxonomy (incl. unused — `hide_empty` off).
	 *
	 * @param string $taxonomy 'recipe_category' or 'recipe_tag'.
	 * @return \WP_Term[]
	 */
	public function get_all_terms( string $taxonomy ): array;

	/**
	 * Replace the recipe's terms in a taxonomy by term id (empty clears).
	 * Flushes the schema cache + Recipe_Cache.
	 *
	 * @param int    $recipe_id The recipe post id.
	 * @param int[]  $term_ids  Term ids to assign (replaces existing).
	 * @param string $taxonomy  'recipe_category' or 'recipe_tag'.
	 */
	public function assign_terms( int $recipe_id, array $term_ids, string $taxonomy ): void;

	/**
	 * Create a term by name (auto-uniquifies the slug). On `term_exists`
	 * resolves to the existing id rather than erroring.
	 *
	 * @param string $name     Display name.
	 * @param string $taxonomy 'recipe_category' or 'recipe_tag'.
	 * @param int    $parent   Parent term id (categories only; 0 = top-level).
	 * @return array{term_id:int}|\WP_Error
	 */
	public function create_term( string $name, string $taxonomy, int $parent = 0 );

	/**
	 * Fetch multiple Recipes (optionally filtered).
	 *
	 * @return Recipe[]
	 */
	public function all( array $filters = array() ): array;

	/**
	 * Count recipe posts by status without loading recipes or their metadata.
	 *
	 * @return array<string, int> Site-wide counts, independent of list filters.
	 */
	public function count_by_status(): array;

	/**
	 * Whether an author holds a recipe in one of the given statuses, leaving
	 * out the given recipes.
	 *
	 * @since 8.0.0
	 *
	 * @param int      $user_id       The author; 0 or less holds none.
	 * @param string[] $statuses      Recipe statuses that count (`Recipe_Status`: publish, draft, trash); at least one.
	 * @param int[]    $excluding_ids Recipes to leave out.
	 *
	 * @return bool
	 *
	 * @throws \InvalidArgumentException When no status is given, or one is not a recipe status.
	 */
	public function author_has_recipe( int $user_id, array $statuses, array $excluding_ids = array() ): bool;

	/**
	 * Get the underlying WP_Post for a recipe.
	 *
	 * @param int $id The recipe post ID.
	 *
	 * @return \WP_Post|null
	 */
	public function get_wp_post( int $id ): ?\WP_Post;

	/**
	 * The plugin version a recipe was last written by.
	 *
	 * Used to gate one-time on-demand migrations: a recipe whose vintage
	 * predates a storage change still needs converting on first read.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return string Version string, or empty when never stamped.
	 */
	public function get_version( int $recipe_id ): string;

	/**
	 * Read the recipe vintage straight from the table, bypassing the per-request meta cache.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return string The stored version, '' when none.
	 */
	public function get_version_uncached( int $recipe_id ): string;

	/**
	 * Stamp the recipe with a plugin version, exactly as a save would.
	 *
	 * @param int    $recipe_id Recipe post ID.
	 * @param string $version   Version to record.
	 *
	 * @return void
	 */
	public function set_version( int $recipe_id, string $version ): void;

	/**
	 * Every recipe that is not trashed, with the facts a site-wide census needs.
	 *
	 * `recipe_type` is `user` when the meta is missing or empty, matching the legacy default;
	 * `version` is the stored `uap_recipe_version`, '' when never stamped. Ordered by id.
	 * Exactly one row per recipe; a duplicated meta row yields the first value by meta_id.
	 *
	 * @return list<array{id: int, post_status: string, recipe_type: string, version: string}>
	 */
	public function census_rows(): array;

	/**
	 * Deprecated one-line accessor retained for 8.0.0.
	 *
	 * Its callers (the recipe-item REST classes) move off it in a later PR;
	 * deleting it here would break them in the interim. Removed in the
	 * post-8.0.0 cleanup pass together with those callers.
	 *
	 * @deprecated 8.0.0
	 */
	public function get_post_type(): string;
}
