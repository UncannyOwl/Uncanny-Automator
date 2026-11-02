<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Portability;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Events\Recipe_Change_Record;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Action_Conditions_Meta_Migrator;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Delay_Schedule_Meta_Migrator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Tree_Builder;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Tree_Materializer;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Materialization_Result;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Reorder_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Taxonomy_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Reference_Rewriter;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\User_Selector_Service;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use WP_Error;

/**
 * Whole-recipe deep duplication — the thin composer over the portability
 * engine (tree builder + materializer + CRUD/settings/taxonomy services).
 *
 * `duplicate_recipe()` deep-copies an ENTIRE recipe by:
 *
 *  1. **Guaranteeing the source is canonical.** Runs the legacy
 *     `actions_conditions` + `async_*` migrators directly on the source before
 *     reading. {@see Recipe_Tree_Builder::build()} is a pure read and does NOT
 *     migrate — a source whose conditions are still legacy `actions_conditions`
 *     post-meta (not yet `uo-block-modifier` rows) would build an incomplete
 *     tree and the copy would silently drop the filter. The migrate call is
 *     explicit, unconditional, and idempotent (each migrator short-circuits on
 *     an already-canonical recipe). This is the same pair {@see Recipe_Importer}
 *     runs on import and the {@see \Uncanny_Automator\App\Recipe_Builder\Block\Resolvers\Block_Resolver}
 *     runs on read — invoked directly here, with no watermark gating.
 *  2. **Building the canonical tree** of the source.
 *  3. **Creating a fresh recipe shell** via {@see Recipe_CRUD_Service::create_recipe()},
 *     seeded with the recipe-level scalar settings the tree carries (type,
 *     trigger_logic, throttle, total_times, times_per_user, notes) so they are
 *     correct at construction and survive the materialize.
 *  4. **Rebuilding the whole graph** into the new shell via
 *     {@see Recipe_Tree_Materializer::materialize_tree()} — fresh ids +
 *     rewritten intra-recipe references.
 *  5. **Copying recipe-level taxonomy** (categories + tags) the create path
 *     cannot accept.
 *
 * Returns a {@see Materialization_Result} whose `root_id` is the NEW RECIPE id,
 * or a {@see WP_Error} on any failure (Rule 10 — the public
 * `automator_duplicate_recipe()` helper wraps it in the never-throw seam).
 *
 * `duplicate_entity()` duplicates ONE entity/subtree into the SAME recipe under
 * the SAME parent, positioned immediately AFTER the source: migrate source →
 * build tree → {@see Recipe_Subtree_Extractor::extract()} the subtree →
 * {@see Recipe_Tree_Materializer::materialize_into()} it back under the source's
 * parent → reorder the new root right after the source via
 * {@see Recipe_Reorder_Service::reorder()}. This is what the REST duplicate
 * endpoints call.
 *
 * Lazy-default constructor injection (Rule 9c): tests inject fakes; production
 * gets the singletons / `new` defaults.
 */
final class Recipe_Duplication_Service {

	/**
	 * Projects the source recipe's storage into the canonical tree (pure read).
	 *
	 * @var Recipe_Tree_Builder
	 */
	private Recipe_Tree_Builder $tree_builder;

	/**
	 * Replays a (sub)tree into the target with fresh ids and rewritten
	 * references.
	 *
	 * @var Recipe_Tree_Materializer
	 */
	private Recipe_Tree_Materializer $materializer;

	/**
	 * Creates the fresh recipe shell seeded with the source's scalar settings.
	 *
	 * @var Recipe_CRUD_Service
	 */
	private Recipe_CRUD_Service $recipe_crud;

	/**
	 * Copies the recipe-level categories + tags the create path cannot accept.
	 *
	 * @var Recipe_Taxonomy_Service
	 */
	private Recipe_Taxonomy_Service $taxonomy;

	/**
	 * Migrates legacy `actions_conditions` post-meta to `uo-block-modifier`
	 * rows before the tree read (part of the migration guarantee).
	 *
	 * @var Action_Conditions_Meta_Migrator
	 */
	private Action_Conditions_Meta_Migrator $conditions_migrator;

	/**
	 * Migrates legacy `async_*` action meta to delay/schedule blocks before
	 * the tree read (part of the migration guarantee).
	 *
	 * @var Delay_Schedule_Meta_Migrator
	 */
	private Delay_Schedule_Meta_Migrator $delay_migrator;

	/**
	 * Isolates one entity's subtree from the full tree for `duplicate_entity`.
	 *
	 * @var Recipe_Subtree_Extractor
	 */
	private Recipe_Subtree_Extractor $subtree_extractor;

	/**
	 * Re-sequences the copy immediately after its source (the only
	 * Rule-12-clean `menu_order` write seam).
	 *
	 * @var Recipe_Reorder_Service
	 */
	private Recipe_Reorder_Service $reorder;

	/**
	 * Round-trips the anonymous-recipe user selector onto the copy. The tree's
	 * `run_as` projection is intentionally lossy (`fields` empty), so the
	 * selector is copied service-to-service, not through the tree.
	 *
	 * @var User_Selector_Service
	 */
	private User_Selector_Service $user_selector;

	/**
	 * Rewrites trigger/action ids inside copied selector values (selector
	 * fields can carry `{{<id>:...}}` tokens that must land on the new ids).
	 *
	 * @var Reference_Rewriter
	 */
	private Reference_Rewriter $rewriter;

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * The recipe-level scalar settings + notes ride in on `create_recipe()`
	 * (Recipe_CRUD_Service composes them at construction), so no separate
	 * Recipe_Settings_Service / Recipe_Details_Service write is needed here —
	 * only taxonomy (categories + tags) has no create-path slot and is copied
	 * via Recipe_Taxonomy_Service after the shell exists.
	 *
	 * @param Recipe_Tree_Builder|null             $tree_builder
	 * @param Recipe_Tree_Materializer|null        $materializer
	 * @param Recipe_CRUD_Service|null             $recipe_crud
	 * @param Recipe_Taxonomy_Service|null         $taxonomy
	 * @param Action_Conditions_Meta_Migrator|null $conditions_migrator
	 * @param Delay_Schedule_Meta_Migrator|null    $delay_migrator
	 * @param Recipe_Subtree_Extractor|null        $subtree_extractor
	 * @param Recipe_Reorder_Service|null          $reorder
	 * @param User_Selector_Service|null           $user_selector
	 * @param Reference_Rewriter|null              $rewriter
	 */
	public function __construct(
		?Recipe_Tree_Builder $tree_builder = null,
		?Recipe_Tree_Materializer $materializer = null,
		?Recipe_CRUD_Service $recipe_crud = null,
		?Recipe_Taxonomy_Service $taxonomy = null,
		?Action_Conditions_Meta_Migrator $conditions_migrator = null,
		?Delay_Schedule_Meta_Migrator $delay_migrator = null,
		?Recipe_Subtree_Extractor $subtree_extractor = null,
		?Recipe_Reorder_Service $reorder = null,
		?User_Selector_Service $user_selector = null,
		?Reference_Rewriter $rewriter = null
	) {
		$this->tree_builder      = $tree_builder ?? Recipe_Tree_Builder::instance();
		$this->materializer      = $materializer ?? new Recipe_Tree_Materializer();
		$this->recipe_crud       = $recipe_crud ?? Recipe_CRUD_Service::instance();
		$this->taxonomy          = $taxonomy ?? Recipe_Taxonomy_Service::instance();
		$this->subtree_extractor = $subtree_extractor ?? new Recipe_Subtree_Extractor();
		$this->reorder           = $reorder ?? Recipe_Reorder_Service::instance();
		$this->user_selector     = $user_selector ?? User_Selector_Service::instance();
		$this->rewriter          = $rewriter ?? new Reference_Rewriter();

		$block_store    = Database::get_block_store();
		$modifier_store = Database::get_block_modifier_store();

		$this->conditions_migrator = $conditions_migrator
			?? new Action_Conditions_Meta_Migrator( $block_store, $modifier_store );
		$this->delay_migrator      = $delay_migrator
			?? new Delay_Schedule_Meta_Migrator( $block_store );
	}

	/**
	 * Deep-duplicate a whole recipe into a fresh draft.
	 *
	 * @param int    $source_recipe_id Recipe to copy.
	 * @param string $new_title        New title; defaults to source title + " (Copy)".
	 * @param string $new_status       New status; defaults to draft.
	 *
	 * @return Materialization_Result|WP_Error Result (root = the new recipe id) or the first failure.
	 */
	public function duplicate_recipe( int $source_recipe_id, string $new_title = '', string $new_status = Recipe_Status::DRAFT ) {
		// 1. Guarantee the source is canonical before the pure-read tree build.
		$this->ensure_source_migrated( $source_recipe_id );

		// 2. Build the canonical tree of the (now-migrated) source.
		$tree = $this->tree_builder->build( $source_recipe_id );
		if ( null === $tree ) {
			return new WP_Error(
				'duplicate_source_not_found',
				/* translators: %d Recipe ID. */
				sprintf( esc_html_x( 'Recipe not found with ID: %d', 'Recipe duplication error', 'uncanny-automator' ), $source_recipe_id )
			);
		}

		// 3. Replay the tree as a new recipe, carrying the source's full user
		//    selector (the tree's run_as projection is intentionally lossy).
		$result = $this->replay_tree( $tree, $new_title, $new_status, $this->source_user_selector_data( $source_recipe_id ) );

		// 4. Structural meta a copy keeps. Here, not in replay_tree(), which an import shares.
		if ( $result instanceof Materialization_Result ) {
			$this->carry_copy_meta( $result );
		}

		return $result;
	}

	/**
	 * Replay a canonical tree (from local storage or a portable document) as a
	 * NEW recipe: shell seeded with the tree's scalar settings → materialize
	 * the graph → taxonomy → optional explicit user-selector data, with
	 * selector-value token ids rewritten through the materialization id map.
	 *
	 * @param array<string,mixed> $tree          Canonical tree (root keys per Recipe_Tree_Builder).
	 * @param string              $title         New recipe title ('' → tree title + " (Copy)").
	 * @param string              $status        New recipe post status.
	 * @param array|null          $selector_data Full selector data bag, or null for none.
	 *
	 * @return Materialization_Result|WP_Error Result (root = the new recipe id) or the first failure.
	 */
	public function replay_tree( array $tree, string $title = '', string $status = Recipe_Status::DRAFT, ?array $selector_data = null ) {
		return Recipe_Change_Events::operation(
			function () use ( $tree, $title, $status, $selector_data ) {
				// The shell, graph, taxonomy and selector writes are one operation: only the finished copy fires an event,
				// and an abandoned replay (its shell deleted again) fires none.
				$result = $this->replay( $tree, $title, $status, $selector_data );

				if ( $result instanceof Materialization_Result ) {
					$new_recipe_id = (int) $result->get_root_id();
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, Recipe_Changed::RECIPE, $new_recipe_id, $new_recipe_id ) );
				}

				return $result;
			}
		);
	}

	/**
	 * Replay a tree as a new recipe: shell, graph, taxonomy, user selector.
	 *
	 * @param array      $tree          Canonical recipe tree.
	 * @param string     $title         Title for the copy; empty derives one from the tree.
	 * @param string     $status        Status for the copy.
	 * @param array|null $selector_data User selector to persist, or null.
	 *
	 * @return Materialization_Result|WP_Error
	 */
	private function replay( array $tree, string $title, string $status, ?array $selector_data ) {
		// 1. Create the fresh recipe shell, seeded with the recipe-level scalar
		//    settings the tree carries so they are correct at construction.
		$title       = '' !== $title ? $title : $this->copy_title( (string) ( $tree['title'] ?? '' ) );
		$create_data = $this->build_create_data( $tree, $title, $status );

		$created = $this->recipe_crud->create_recipe( $create_data );
		if ( $created instanceof WP_Error ) {
			return $created;
		}
		if ( empty( $created['success'] ) || empty( $created['recipe_id'] ) ) {
			return new WP_Error(
				'duplicate_create_failed',
				esc_html_x( 'The recipe copy could not be created.', 'Recipe duplication error', 'uncanny-automator' )
			);
		}
		$new_recipe_id = (int) $created['recipe_id'];

		// 2. Rebuild the whole graph into the new shell (fresh ids + rewritten refs).
		$result = $this->materializer->materialize_tree( $tree, $new_recipe_id );
		if ( $result instanceof WP_Error ) {
			return $this->abandon_replay( $new_recipe_id, $result );
		}

		// 3. Copy recipe-level taxonomy the create path cannot accept.
		$taxonomy_error = $this->copy_taxonomy( $tree, $new_recipe_id );
		if ( $taxonomy_error instanceof WP_Error ) {
			return $this->abandon_replay( $new_recipe_id, $taxonomy_error );
		}

		// 4. Copy the user selector (anonymous recipes), rewriting any
		//    entity-id tokens its values hold onto the fresh ids. A copy keeps
		//    the source's setting whether or not this site can use it.
		if ( is_array( $selector_data ) && ! empty( $selector_data ) ) {
			$saved = $this->user_selector->copy_user_selector(
				$new_recipe_id,
				(array) $this->rewriter->rewrite( $selector_data, $result->get_id_map() )
			);
			if ( $saved instanceof WP_Error ) {
				return $this->abandon_replay( $new_recipe_id, $saved );
			}
		}

		// 5. Return a result whose root_id is the NEW RECIPE id (the materializer
		//    already roots whole-tree results at the target recipe id, but rebuild
		//    explicitly so the contract holds regardless of its root choice).
		return new Materialization_Result( $new_recipe_id, $result->get_id_map() );
	}

	/**
	 * Duplicate ONE entity / subtree into the SAME recipe, under the SAME
	 * parent, positioned immediately AFTER the source.
	 *
	 *  1. Guarantee the source is canonical (same guarantee as duplicate_recipe).
	 *  2. Build the canonical tree.
	 *  3. Isolate the subtree rooted at $entity_id.
	 *  4. Resolve the source's parent from its tree position (recipe root → the
	 *     recipe id; inside a block → that block id).
	 *  5. Replay the subtree under that parent (fresh ids + rewritten refs).
	 *  6. Reorder the new root immediately after the source.
	 *
	 * @param int $recipe_id Recipe the source lives in (and the copy lands in).
	 * @param int $entity_id Entity post id to duplicate.
	 *
	 * @return Materialization_Result|WP_Error Result (root = the copy's new id) or the first failure.
	 */
	public function duplicate_entity( int $recipe_id, int $entity_id ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $entity_id ) {
				$this->ensure_source_migrated( $recipe_id );

				$tree = $this->tree_builder->build( $recipe_id );
				if ( null === $tree ) {
					return new WP_Error(
						'duplicate_source_not_found',
						/* translators: %d Recipe ID. */
						sprintf( esc_html_x( 'Recipe not found with ID: %d', 'Recipe duplication error', 'uncanny-automator' ), $recipe_id )
					);
				}

				$node = $this->subtree_extractor->extract( $tree, $entity_id );
				if ( null === $node ) {
					return new WP_Error(
						'automator_duplicate_source_not_found',
						/* translators: %1$d Item post ID, %2$d Recipe ID. */
						sprintf( esc_html_x( 'Item %1$d was not found in recipe %2$d.', 'Recipe duplication error', 'uncanny-automator' ), $entity_id, $recipe_id )
					);
				}

				// Structural invariant (not field validation): anonymous recipes hold
				// exactly one trigger, so duplicating a trigger within one always
				// violates the runtime contract.
				if ( 'trigger' === ( $node['kind'] ?? '' ) && 'anonymous' === ( $tree['recipe_type'] ?? '' ) ) {
					return new WP_Error(
						'automator_duplicate_anonymous_trigger',
						esc_html_x( 'Anonymous recipes support a single trigger; it cannot be duplicated.', 'Recipe duplication error', 'uncanny-automator' )
					);
				}

				// Contract: duplicated items are born off — including items nested
				// inside a duplicated block. The copy never runs until the user
				// switches it on.
				$node = ( new Live_Flag_Neutralizer() )->neutralize_subtree( $node );

				list( $source_parent_id, $source_path_code ) = $this->resolve_source_placement( $tree, $entity_id, $recipe_id );

				$result = $this->materializer->materialize_into( $node, $recipe_id, $source_parent_id, $source_path_code );
				if ( $result instanceof WP_Error ) {
					return $result;
				}
				$this->carry_copy_meta( $result );

				$position_error = $this->position_after_source( $recipe_id, $entity_id, $result->get_root_id() );

				// The copy landed even when positioning it failed, so the event fires either way.
				$copy_id   = (int) $result->get_root_id();
				$copy_type = Recipe_Change_Record::item_type_of( (string) get_post_type( $copy_id ) );
				if ( null !== $copy_type ) {
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, $copy_type, $copy_id, $recipe_id ) );
				}

				if ( $position_error instanceof WP_Error ) {
					return $position_error;
				}

				return $result;
			}
		);
	}

	/**
	 * Copy the structural meta a same-site copy keeps onto each copied trigger and action.
	 *
	 * Fields travel through the tree, which leaves structural meta out. An integration
	 * names the structural keys a copy still needs (the webhook action's stored secret)
	 * through `automator_item_copy_meta_keys`; an export carries none of them.
	 *
	 * @since 8.0.0
	 *
	 * @param Materialization_Result $result The copy, with its source id => copy id map.
	 *
	 * @return void
	 */
	private function carry_copy_meta( Materialization_Result $result ): void {
		$keys = array_filter( (array) Dispatcher::filter( 'automator_item_copy_meta_keys', array() ), 'is_string' );
		if ( empty( $keys ) ) {
			return;
		}

		// Each store reads and writes only its own post type, so a block or modifier id is skipped.
		$stores = array( Database::get_action_store(), Database::get_recipe_trigger_store() );
		foreach ( $result->get_id_map() as $source_id => $copy_id ) {
			foreach ( $stores as $store ) {
				foreach ( $keys as $key ) {
					$value = $store->get_meta( (int) $source_id, $key, null );
					if ( null !== $value ) {
						$store->update_meta( (int) $copy_id, $key, wp_slash( $value ) );
					}
				}
			}
		}
	}

	/**
	 * Resolve the source's placement (parent id + block path code) from its
	 * position in the canonical tree, so the copy lands in the SAME slot.
	 *
	 * Recipe-root entries (top-level actions / blocks) parent to the
	 * recipe with no path code; a block path item parents to its enclosing block
	 * and carries that path's `path_code` so the materializer assigns the copy
	 * to the same branch; a block modifier parents to its block with no path
	 * code. Falls back to `[recipe_id, null]` when the source can't be located
	 * in a block (it is then a recipe-root entry by elimination).
	 *
	 * @param array<string,mixed> $tree
	 * @return array{0:int,1:string|null} [parent_id, path_code].
	 */
	private function resolve_source_placement( array $tree, int $entity_id, int $recipe_id ): array {
		foreach ( (array) ( $tree['content'] ?? array() ) as $node ) {
			$placement = $this->find_block_placement_of( $node, $entity_id );
			if ( null !== $placement ) {
				return $placement;
			}
		}
		return array( $recipe_id, null );
	}

	/**
	 * Return `[block_id, path_code]` for the block that directly contains
	 * $entity_id (a path item carries its path's code; a modifier carries null),
	 * recursing into nested blocks; null if $entity_id is not a descendant of
	 * $node.
	 *
	 * @param array<string,mixed> $node
	 * @return array{0:int,1:string|null}|null
	 */
	private function find_block_placement_of( array $node, int $entity_id ): ?array {
		$block_id = (int) ( $node['id'] ?? 0 );

		foreach ( (array) ( $node['modifiers'] ?? array() ) as $modifier ) {
			if ( (int) ( $modifier['id'] ?? 0 ) === $entity_id ) {
				return array( $block_id, null );
			}
		}

		foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
			$path_code = (string) ( $path['path_code'] ?? '' );
			foreach ( (array) ( $path['items'] ?? array() ) as $item ) {
				if ( (int) ( $item['id'] ?? 0 ) === $entity_id ) {
					return array( $block_id, '' !== $path_code ? $path_code : null );
				}
				$nested = $this->find_block_placement_of( $item, $entity_id );
				if ( null !== $nested ) {
					return $nested;
				}
			}
		}

		return null;
	}

	/**
	 * Position the freshly-materialized root immediately after the source within
	 * its sibling slot, via {@see Recipe_Reorder_Service::reorder()} — the only
	 * Rule-12-clean seam for `menu_order` writes (it routes through
	 * Recipe_Reorder_Store; this service never touches wp_update_post / $wpdb).
	 *
	 * Rebuilds the tree (now carrying both source and copy), finds the slot that
	 * holds the source, and submits a permutation body that lists every persisted
	 * sibling in order with the new root inserted right after the source.
	 */
	private function position_after_source( int $recipe_id, int $source_id, int $new_root_id ): ?WP_Error {
		$tree = $this->tree_builder->build( $recipe_id );
		if ( null === $tree ) {
			return null;
		}

		$body = $this->build_insert_after_body( $tree, $source_id, $new_root_id );
		if ( null === $body ) {
			// Source slot not found post-materialize — leave the CRUD-append order
			// (the copy is still a sibling under the same parent). Nothing to write.
			return null;
		}

		$applied = $this->reorder->reorder( $recipe_id, $body );
		return $applied instanceof WP_Error ? $applied : null;
	}

	/**
	 * Build a {@see Recipe_Reorder_Service} body that re-sequences the slot
	 * holding $source_id so $new_root_id sits immediately after it. Returns null
	 * when the source isn't found at the recipe root or inside any block slot.
	 *
	 * @param array<string,mixed> $tree
	 * @return array<string,mixed>|null
	 */
	private function build_insert_after_body( array $tree, int $source_id, int $new_root_id ): ?array {
		$content = (array) ( $tree['content'] ?? array() );

		// Recipe-root slot.
		$root_ids = $this->ordered_ids( $content );
		if ( in_array( $source_id, $root_ids, true ) ) {
			return array( 'actions' => $this->slot_entries( $this->insert_after( $root_ids, $source_id, $new_root_id ) ) );
		}

		// Inside a block: find the block + slot (modifiers / a path bucket).
		foreach ( $content as $node ) {
			$block_body = $this->build_block_insert_after_body( $node, $source_id, $new_root_id );
			if ( null !== $block_body ) {
				return array( 'actions' => array( $block_body ) );
			}
		}

		return null;
	}

	/**
	 * Build the nested reorder entry for the block that holds $source_id (in a
	 * modifiers slot or a path bucket), recursing into nested blocks. The
	 * returned entry is a path-marker for ancestor blocks and carries the
	 * fully-permuted leaf slot. Null when $source_id isn't under $node.
	 *
	 * @param array<string,mixed> $node
	 * @return array<string,mixed>|null
	 */
	private function build_block_insert_after_body( array $node, int $source_id, int $new_root_id ): ?array {
		if ( 'block' !== ( $node['kind'] ?? '' ) ) {
			return null;
		}
		$block_id = (int) ( $node['id'] ?? 0 );

		$modifier_ids = $this->ordered_ids( (array) ( $node['modifiers'] ?? array() ) );
		if ( in_array( $source_id, $modifier_ids, true ) ) {
			return array(
				'id'        => $block_id,
				'kind'      => 'block',
				'modifiers' => $this->slot_entries( $this->insert_after( $modifier_ids, $source_id, $new_root_id ) ),
			);
		}

		foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
			$path_code = (string) ( $path['path_code'] ?? '' );
			$items     = (array) ( $path['items'] ?? array() );
			$item_ids  = $this->ordered_ids( $items );

			if ( '' !== $path_code && in_array( $source_id, $item_ids, true ) ) {
				return array(
					'id'    => $block_id,
					'kind'  => 'block',
					'paths' => array(
						array(
							'path_code' => $path_code,
							'items'     => $this->slot_entries( $this->insert_after( $item_ids, $source_id, $new_root_id ) ),
						),
					),
				);
			}

			// Ancestor path codes are always non-empty (block registry guarantees FILTER-CONDITIONS-MET etc.), so no empty-guard needed here.
			foreach ( $items as $item ) {
				$nested = $this->build_block_insert_after_body( $item, $source_id, $new_root_id );
				if ( null !== $nested ) {
					return array(
						'id'    => $block_id,
						'kind'  => 'block',
						'paths' => array(
							array(
								'path_code' => $path_code,
								'items'     => array( $nested ),
							),
						),
					);
				}
			}
		}

		return null;
	}

	/**
	 * Ordered list of ids from a slot's nodes (preserving tree order).
	 *
	 * @param list<array<string,mixed>> $nodes
	 * @return list<int>
	 */
	private function ordered_ids( array $nodes ): array {
		$ids = array();
		foreach ( $nodes as $entry ) {
			$id = (int) ( $entry['id'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Return $ids re-sequenced so $new_id sits immediately after $source_id.
	 *
	 * $new_id is first removed from its current (CRUD-append) position so the
	 * result stays a permutation of the persisted slot — listing it twice would
	 * trip the reorder service's duplicate-id guard.
	 *
	 * @param list<int> $ids
	 * @return list<int>
	 */
	private function insert_after( array $ids, int $source_id, int $new_id ): array {
		$out = array();
		foreach ( $ids as $id ) {
			if ( $id === $new_id ) {
				continue;
			}
			$out[] = $id;
			if ( $id === $source_id ) {
				$out[] = $new_id;
			}
		}
		return $out;
	}

	/**
	 * Wrap an ordered id list into reorder-body slot entries `{ id }`.
	 *
	 * @param list<int> $ids
	 * @return list<array{id:int}>
	 */
	private function slot_entries( array $ids ): array {
		return array_map( static fn( int $id ): array => array( 'id' => $id ), $ids );
	}

	/**
	 * Run the legacy → block migrators on the source so the tree build reads a
	 * canonical recipe. Idempotent: each migrator short-circuits on an
	 * already-canonical recipe. No loop fold here: imports write unified rows
	 * via the Portability engine and the init-time bulk pass covers pre-8.0
	 * databases, so no legacy loop rows can exist at duplicate time.
	 */
	private function ensure_source_migrated( int $source_recipe_id ): void {
		if ( $source_recipe_id < 1 ) {
			return;
		}

		$this->conditions_migrator->maybe_migrate( $source_recipe_id );
		$this->delay_migrator->maybe_migrate( $source_recipe_id );
	}

	/**
	 * Compose the `create_recipe` data bag from the recipe-level scalar values
	 * the tree carries. Taxonomy (categories/tags) is copied separately after
	 * create because `create_recipe` does not accept it.
	 *
	 * @param array<string,mixed> $tree
	 * @return array<string,mixed>
	 */
	private function build_create_data( array $tree, string $title, string $new_status ): array {
		$type = (string) ( $tree['recipe_type'] ?? 'user' );

		$data = array(
			'title'         => $title,
			'status'        => $new_status,
			'type'          => $type,
			'trigger_logic' => (string) ( $tree['trigger_logic'] ?? 'all' ),
			'notes'         => (string) ( $tree['notes'] ?? '' ),
			'throttle'      => is_array( $tree['throttle'] ?? null ) ? $tree['throttle'] : array(),
			'total_times'   => $this->normalize_limit( $tree['total_times'] ?? null ),
		);

		// times_per_user is meaningful for user recipes only; create_recipe
		// ignores it for anonymous recipes anyway.
		if ( 'user' === $type ) {
			$data['times_per_user'] = $this->normalize_limit( $tree['times_per_user'] ?? null );
		}

		return $data;
	}

	/**
	 * Translate the tree reader's UNLIMITED sentinel (-1) into the `null` the
	 * `Recipe_Total_Times` / `Recipe_Times_Per_User` VOs expect for "no limit".
	 * Those VOs accept `null` or a non-negative integer and throw on a negative
	 * value, so the -1 sentinel cannot be forwarded verbatim.
	 *
	 * @param mixed $value
	 * @return int|null
	 */
	private function normalize_limit( $value ): ?int {
		if ( null === $value || ! is_numeric( $value ) || (int) $value < 0 ) {
			return null;
		}
		return (int) $value;
	}

	/**
	 * Copy the recipe's categories + tags onto the new recipe via
	 * {@see Recipe_Taxonomy_Service::set_terms()}. Each tree term is a wire
	 * object `{id, name, slug, parent}`; passing `id` assigns the existing term
	 * (terms are global, shared across recipes), so the copy points at the same
	 * terms as the source.
	 *
	 * @param array<string,mixed> $tree
	 */
	private function copy_taxonomy( array $tree, int $new_recipe_id ): ?WP_Error {
		$map = array(
			'recipe_category' => (array) ( $tree['categories'] ?? array() ),
			'recipe_tag'      => (array) ( $tree['tags'] ?? array() ),
		);

		foreach ( $map as $taxonomy => $terms ) {
			if ( empty( $terms ) ) {
				continue;
			}
			$set = $this->taxonomy->set_terms( $new_recipe_id, $taxonomy, $terms );
			if ( $set instanceof WP_Error ) {
				return $set;
			}
		}

		return null;
	}

	/**
	 * Tear down a half-created replay target and surface the failure. A
	 * replay that aborts after the shell exists must not leave an orphan
	 * draft polluting the recipes list (live bug: failed admin duplicates /
	 * imports left empty shells behind).
	 *
	 * @param int      $new_recipe_id The shell created before the failure.
	 * @param WP_Error $error         The failure to surface.
	 * @return WP_Error The same failure, after cleanup.
	 */
	private function abandon_replay( int $new_recipe_id, WP_Error $error ): WP_Error {
		$this->recipe_crud->delete_recipe( $new_recipe_id, true );

		return $error;
	}

	/**
	 * Read the source recipe's full user-selector data for replay. A source
	 * without a selector (every user-type recipe) returns null — a clean
	 * no-op for {@see self::replay_tree()}.
	 *
	 * @param int $source_recipe_id Source recipe id.
	 * @return array<string,mixed>|null Selector data bag, or null when absent.
	 */
	private function source_user_selector_data( int $source_recipe_id ): ?array {
		$existing = $this->user_selector->get_user_selector( $source_recipe_id );
		if ( ! is_array( $existing ) || ! is_array( $existing['data'] ?? null ) ) {
			return null;
		}

		$data = $existing['data'];
		unset( $data['id'], $data['recipe_id'] );

		return $data;
	}

	/**
	 * Build the default copy title: source title + " (Copy)".
	 */
	private function copy_title( string $source_title ): string {
		$source_title = '' !== $source_title ? $source_title : 'Recipe';
		return $source_title . ' (Copy)';
	}
}
