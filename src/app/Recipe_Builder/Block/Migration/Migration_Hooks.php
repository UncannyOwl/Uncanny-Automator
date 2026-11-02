<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Migration;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Value_Objects\Trigger_Id;

/**
 * Migrates legacy meta at the entry points that bypass recipe rendering.
 *
 * Wires the one-time loop fold, the legacy `recipes_object` projection and
 * the cascade cleanup when a recipe is permanently deleted. Neither a save nor an import
 * runs a migration here: the tree read migrates (vintage-gated) and the
 * portability engine migrates what it imports before `automator_recipe_imported`
 * fires.
 *
 * The Block_Resolver handles migration during the legacy structure read via
 * the `automator_recipe_main_object_action_items` filter.
 */
class Migration_Hooks {

	/**
	 * Legacy conditions migrator.
	 *
	 * @var Action_Conditions_Meta_Migrator
	 */
	private Action_Conditions_Meta_Migrator $conditions_migrator;

	/**
	 * Loops fold-in migrator.
	 *
	 * @var Loop_To_Block_Migrator
	 */
	private Loop_To_Block_Migrator $loop_migrator;

	/**
	 * Block-modifier store — used by the recipe-trash cleanup listener to
	 * cascade-delete `uo-block-modifier` rows scoped to the trashed recipe.
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Block store — used by the recipe-trash listener to cascade-delete
	 * every `uo-block` post under the trashed recipe (filter, delay,
	 * async_action, loop). The store's own `delete()` recursively
	 * cleans nested blocks and their modifiers.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Action store — used by the recipe-delete listener to delete the
	 * recipe's actions, inside its blocks too.
	 *
	 * @var Action_Store
	 */
	private Action_Store $action_store;

	/**
	 * Trigger store — used by the recipe-delete listener to delete the
	 * recipe's triggers.
	 *
	 * @var Recipe_Trigger_Store
	 */
	private Recipe_Trigger_Store $trigger_store;

	/**
	 * Constructor.
	 *
	 * @since 8.0.0 Takes the action and trigger stores the delete cascade uses.
	 *
	 * @param Action_Conditions_Meta_Migrator $conditions_migrator Conditions migrator (its legacy projection).
	 * @param Loop_To_Block_Migrator          $loop_migrator       Loops fold-in migrator.
	 * @param Block_Modifier_Store            $modifier_store      Block-modifier store for cascade cleanup.
	 * @param Block_Store                     $block_store         Block store for cascade cleanup.
	 * @param Action_Store|null               $action_store        Action store for cascade cleanup; the site's store when null.
	 * @param Recipe_Trigger_Store|null       $trigger_store       Trigger store for cascade cleanup; the site's store when null.
	 */
	public function __construct(
		Action_Conditions_Meta_Migrator $conditions_migrator,
		Loop_To_Block_Migrator $loop_migrator,
		Block_Modifier_Store $modifier_store,
		Block_Store $block_store,
		?Action_Store $action_store = null,
		?Recipe_Trigger_Store $trigger_store = null
	) {
		$this->conditions_migrator = $conditions_migrator;
		$this->loop_migrator       = $loop_migrator;
		$this->modifier_store      = $modifier_store;
		$this->block_store         = $block_store;
		$this->action_store        = $action_store ?? Database::get_action_store();
		$this->trigger_store       = $trigger_store ?? Database::get_recipe_trigger_store();
	}

	/**
	 * Create instance with default dependencies.
	 *
	 * @return self
	 */
	public static function create_with_defaults(): self {
		$block_store    = Database::get_block_store();
		$modifier_store = Database::get_block_modifier_store();

		return new self(
			new Action_Conditions_Meta_Migrator( $block_store, $modifier_store ),
			new Loop_To_Block_Migrator( $block_store, $modifier_store ),
			$modifier_store,
			$block_store
		);
	}

	/**
	 * Subscribe to the init, recipes-data and trash events this class covers.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// The 8.0.0 atomic loop fold-in: one option-gated site-wide pass on
		// the first request after the update (per-site on multisite). The
		// gate is a single cached option read on every subsequent request.
		add_action( 'init', array( $this, 'maybe_run_loop_fold_bulk_migration' ) );

		// Read-side projection: the bundled recipe-builder JS reads
		// `recipe.actions_conditions` off `recipes_object` to render filter
		// groups (`parseConditionsInMeta`). After migration that meta is
		// empty (moved to `_legacy_actions_conditions`) so the bundled UI
		// renders nothing. Project it back from uo-block storage on every
		// `get_recipes_data` call so the legacy reader stays whole.
		//
		// Priority 20: Pro's `Actions_Conditions::add_to_recipes_object` used to
		// fill this key at 10 from post-meta and this projection overwrote it.
		// That class is gone with the block walker; this is now the only
		// writer of `actions_conditions` on `recipes_object`.
		//
		// Kill switch — same flag as the translator. When disabled, the
		// new builder team gets the raw `recipes_object` shape (empty
		// `actions_conditions`) so they can wire their reader against
		// uo-block storage natively.
		if ( ! automator_blocks_legacy_translators_disabled() ) {
			add_filter( 'automator_get_recipes_data', array( $this, 'inject_actions_conditions_into_recipes_data' ), 20, 2 );
			// Same hook for the per-recipe path: when the REST endpoint passes a
			// specific recipe_id, `Automator_Functions::get_recipes_data()`
			// delegates to `get_recipe_data_by_recipe_id()` which fires a
			// different filter. The bundled recipe-builder edit page hits this
			// path (REST `/uap/v2/recipe/<id>` and friends).
			add_filter( 'automator_get_recipe_data_by_recipe_id', array( $this, 'inject_actions_conditions_into_recipes_data' ), 20, 2 );
		}

		// Recipe-delete cascade: when a `uo-recipe` is permanently deleted,
		// cascade-remove its triggers, its actions, every `uo-block` post AND
		// every `uo-block-modifier` row scoped to that recipe. WordPress
		// doesn't auto-cascade `post_parent` for non-attachment post types,
		// so without this listener the recipe's steps accumulate as orphans.
		// The CPT-backed Store translates this to wp_delete_post per row;
		// a future `Block_Modifier_Store_Db` impl translates the same call
		// into a single SQL DELETE. Caller signature is paradigm-agnostic.
		add_action( 'before_delete_post', array( $this, 'cascade_delete_block_storage' ), 10, 2 );
	}

	/**
	 * Inject `actions_conditions` JSON projected from uo-block storage
	 * into the `recipes_object` array on every `get_recipes_data` call.
	 *
	 * Filter callback for `automator_get_recipes_data`. Bundled recipe
	 * builder JS (`parseConditionsInMeta`) reads `recipe.actions_conditions`
	 * to render filter groups; after migration that post-meta is moved to
	 * `_legacy_actions_conditions` so the bundled UI sees nothing without
	 * this projection.
	 *
	 * Idempotent — only injects when uo-block filter blocks exist for the
	 * recipe AND the field is currently empty/missing. Non-empty existing
	 * `actions_conditions` (legacy un-migrated recipes) is left untouched.
	 *
	 * @param array      $recipes_data Recipes data array keyed by recipe id.
	 * @param int|null   $recipe_id    Recipe id filter scope (unused — we walk all).
	 *
	 * @return array
	 */
	public function inject_actions_conditions_into_recipes_data( $recipes_data, $recipe_id = null ) {
		if ( ! is_array( $recipes_data ) || empty( $recipes_data ) ) {
			return $recipes_data;
		}

		foreach ( $recipes_data as $rid => &$recipe_row ) {
			if ( ! is_array( $recipe_row ) ) {
				continue;
			}

			$projected = $this->conditions_migrator->project_to_legacy_meta( (int) $rid );

			// uo-block has the authoritative filter state — when the
			// projection is non-empty, overwrite whatever shape lives in
			// the post-meta. The post-meta value can be "[]" (legacy
			// "user has zero conditions" sentinel) which `! empty()`
			// treats as truthy (it's a 2-byte string) — that's how the
			// bundled UI's empty-save round-tripped past us last time
			// and round-tripped the user's filter back to wiped storage.
			if ( ! empty( $projected ) ) {
				$recipe_row['actions_conditions'] = wp_json_encode( $projected );
				continue;
			}

			// Projection is empty — leave whatever is on disk alone.
			// `[]` / `null` / `''` are the bundled UI's "no conditions"
			// signal and a non-empty legacy JSON is an un-migrated
			// recipe; neither wants a synthesized value written over it.
		}
		unset( $recipe_row );

		return $recipes_data;
	}

	/**
	 * Run the one-time atomic loop fold-in for this site (option-gated).
	 *
	 * @return void
	 */
	public function maybe_run_loop_fold_bulk_migration(): void {
		( new Loop_Fold_Bulk_Migration( $this->loop_migrator ) )->maybe_run();
	}

	/**
	 * Cascade-delete a recipe's triggers, its actions, every uo-block post
	 * AND every uo-block-modifier row scoped to it when the recipe is
	 * permanently deleted. Listener for `before_delete_post`.
	 *
	 * Deletes the recipe's actions first, at every depth: an action inside
	 * a block is found through that block, so the blocks must still exist.
	 * Then the recipe's triggers. Then walks top-level uo-block children of
	 * the recipe and calls `Block_Store::delete()` on each — that
	 * recursively cleans nested blocks and their modifiers in one pass.
	 * Then sweeps any modifier row still tagged with the recipe id
	 * (defensive — catches modifiers whose parent block was already gone
	 * for some reason). A step that fails to delete is logged and does not
	 * stop the others.
	 *
	 * @since 8.0.0 Deletes the recipe's triggers and actions too.
	 *
	 * @param int           $post_id Post being deleted.
	 * @param \WP_Post|null $post    Full post object (WP 5.5+).
	 *
	 * @return void
	 */
	public function cascade_delete_block_storage( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		$post_type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( AUTOMATOR_POST_TYPE_RECIPE !== $post_type ) {
			return;
		}

		// The recipe is the root parent, so this collects its own actions and those inside its blocks.
		foreach ( $this->block_store->collect_descendant_action_ids( $post_id ) as $action_id ) {
			try {
				$this->action_store->delete_by_id( $action_id, $post_id );
			} catch ( \Throwable $e ) {
				automator_log( sprintf( 'cascade_delete_block_storage: failed to delete action %d for recipe %d: %s', $action_id, $post_id, $e->getMessage() ) );
			}
		}

		foreach ( $this->trigger_store->get_recipe_trigger_posts( $post_id ) as $trigger_post ) {
			$trigger_id = (int) $trigger_post->ID;
			try {
				$this->trigger_store->remove_trigger_from_recipe( new Recipe_Id( $post_id ), new Trigger_Id( $trigger_id ) );
			} catch ( \Throwable $e ) {
				automator_log( sprintf( 'cascade_delete_block_storage: failed to delete trigger %d for recipe %d: %s', $trigger_id, $post_id, $e->getMessage() ) );
			}
		}

		// Top-level blocks under the recipe — `delete()` recursively
		// removes nested blocks and their modifiers, so iterating only
		// the immediate children is enough.
		foreach ( $this->block_store->get_by_parent( $post_id ) as $block ) {
			$block_id = (int) ( $block['id'] ?? 0 );
			if ( $block_id <= 0 ) {
				continue;
			}
			try {
				$this->block_store->delete( $block_id );
			} catch ( \Throwable $e ) {
				automator_log( sprintf( 'cascade_delete_block_storage: failed to delete block %d for recipe %d: %s', $block_id, $post_id, $e->getMessage() ) );
			}
		}

		// Defensive sweep — any uo-block-modifier still tagged with the
		// recipe id after the per-block recursive delete shouldn't exist,
		// but if it does, this catches it in one call.
		$this->modifier_store->delete_by_recipe( $post_id );
	}
}
