<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Persistence gateway for the legacy post-meta keys stored on `uo-recipe`
 * posts — the pre-canonical payload the block-migration pipeline reads from
 * and the old-format importer writes to.
 *
 * These keys (`actions_conditions`, `_legacy_actions_conditions`, `source`,
 * `fields`, the per-recipe migration breadcrumbs, and an imported action's
 * `async_*` delay keys) live outside the canonical tree. Routing them through
 * one store keeps `get_post_meta` / `update_post_meta` / `delete_post_meta`
 * out of the migrators, the importer and the export adapter.
 *
 * This is a Store, not a Bridge: it owns WordPress post-meta persistence and
 * reaches nothing in `src/core/`. Bridges are reserved for the legacy
 * `src/core/` seam.
 */
interface Recipe_Legacy_Meta_Store {

	/**
	 * Read a legacy source meta value from a recipe.
	 *
	 * Returns the raw stored value (string JSON, array, or empty) so the
	 * caller can parse it. Implementations must NOT decode the value —
	 * decoding belongs to the migrator that knows the source shape.
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $meta_key  Source meta key (e.g. `actions_conditions`).
	 *
	 * @return mixed Raw stored value, or empty string when absent.
	 */
	public function get_source( int $recipe_id, string $meta_key );

	/**
	 * Move the legacy source meta from `$source_key` to `$backup_key` on a
	 * single recipe. No-op when the source is empty.
	 *
	 * Idempotent: the source is deleted only after the backup write succeeds.
	 *
	 * @param int    $recipe_id  Recipe post id.
	 * @param string $source_key Source meta key (e.g. `actions_conditions`).
	 * @param string $backup_key Backup meta key (e.g. `_legacy_actions_conditions`).
	 *
	 * @return void
	 */
	public function move_source_to_backup( int $recipe_id, string $source_key, string $backup_key ): void;

	/**
	 * Whether a migration-breadcrumb flag is set on a recipe.
	 *
	 * Stored as a truthy post-meta value at `$flag_key`. Used by per-recipe
	 * idempotency guards (e.g. `_loops_migrated_to_blocks`) so a migrator
	 * skips recipes already processed.
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $flag_key  Breadcrumb meta key.
	 *
	 * @return bool
	 */
	public function has_flag( int $recipe_id, string $flag_key ): bool;

	/**
	 * Stamp a migration-breadcrumb flag on a recipe.
	 *
	 * Idempotent — re-stamping is a no-op write.
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $flag_key  Breadcrumb meta key.
	 *
	 * @return void
	 */
	public function set_flag( int $recipe_id, string $flag_key ): void;

	/**
	 * Clear a migration-breadcrumb flag from a recipe.
	 *
	 * Idempotent — clearing an absent flag is a no-op. Used to drop
	 * legacy watermarks that older versions stamped but the current
	 * migrator no longer relies on, so recipe meta stays tidy.
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $flag_key  Breadcrumb meta key.
	 *
	 * @return void
	 */
	public function clear_flag( int $recipe_id, string $flag_key ): void;

	/**
	 * Stash the legacy `actions_conditions` JSON on a recipe for the
	 * conditions migrator to consume.
	 *
	 * Old export files (raw post+meta dumps) carry this outside the canonical
	 * tree; the importer writes it here and `Action_Conditions_Meta_Migrator`
	 * canonicalizes it, so the import engine never reimplements legacy-meta
	 * semantics.
	 *
	 * @param int    $recipe_id New recipe post id.
	 * @param string $json      Condition groups JSON (action ids already remapped).
	 *
	 * @return void
	 */
	public function stash_actions_conditions( int $recipe_id, string $json ): void;

	/**
	 * Stash an old export's legacy delay meta on a new action for the delay migrator to consume.
	 *
	 * The adapter strips the `async_*` keys out of the action's field bag, so no 8.0.0 write path
	 * carries them; the importer writes them here and `Delay_Schedule_Meta_Migrator` turns them
	 * into delay blocks.
	 *
	 * A meta-only write fires no hook the schema lifecycle subscribes to, so the recipe's tree is
	 * invalidated here ({@see \Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache}).
	 *
	 * @param int                  $recipe_id Recipe the action belongs to.
	 * @param int                  $action_id New action post id.
	 * @param array<string,string> $meta      `async_*` key => stored value.
	 *
	 * @return void
	 */
	public function stash_action_async_meta( int $recipe_id, int $action_id, array $meta ): void;

	/**
	 * Stash the legacy anonymous-selector meta pair on a recipe. The
	 * `WP_User_Selector_Store` reads these exact keys (`fields` is the
	 * legacy serialized-array shape).
	 *
	 * @param int                 $recipe_id New recipe post id.
	 * @param string              $source    Selector source, written verbatim as the
	 *                                       legacy meta value. Legacy dumps carry the
	 *                                       camelCase shape (`existingUser`/`newUser`);
	 *                                       `User_Selector_Source` normalises either
	 *                                       shape to canonical kebab on read.
	 * @param array<string,mixed> $fields    Selector fields (token ids already remapped).
	 *
	 * @return void
	 */
	public function stash_user_selector_meta( int $recipe_id, string $source, array $fields ): void;
}
