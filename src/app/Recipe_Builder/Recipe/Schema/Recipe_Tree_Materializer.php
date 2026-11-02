<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Replay_Writer;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects\Materialization_Result;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Reference_Rewriter;
use WP_Error;

/**
 * Storage-writing INVERSE of {@see Recipe_Tree_Builder}.
 *
 * Given a canonical (sub)tree, persists every node into a target recipe with
 * FRESH ids and REWRITTEN references, in two passes:
 *
 *  - **Pass 1 (create):** walk the tree in dependency order (triggers first so
 *    token-producers exist, then content; parents before children; blocks
 *    before their path items). Every node persists through
 *    {@see Recipe_Replay_Writer} — the STORE-level replay seam. No authoring
 *    CRUD, no registry: items for inactive/uninstalled integrations persist
 *    verbatim (the Integration_Catalog renders their missing-dependency UX),
 *    skeleton/partial configs persist verbatim, statuses mirror the source.
 *    Record `old_id => new_id`.
 *
 *  - **Pass 2 (rewrite):** for every created entity, run
 *    {@see Reference_Rewriter::rewrite()} over its persisted fields with the
 *    full id map, then write the rewritten fields back through the writer.
 *    The rewriter precondition (old/new id sets disjoint) holds because new
 *    ids are freshly allocated posts.
 *
 * Returns a {@see Materialization_Result} (root id + complete id map), or a
 * {@see WP_Error} if any write fails (the public `automator_*` helpers wrap
 * it; callers branch on `is_wp_error`).
 *
 * Lazy-default constructor injection (Rule 9c): tests inject fakes, production
 * gets the defaults.
 */
final class Recipe_Tree_Materializer {

	/**
	 * The store-level replay write seam (one audited home for the replay
	 * trust posture).
	 *
	 * @var Recipe_Replay_Writer
	 */
	private Recipe_Replay_Writer $writer;

	/**
	 * Pass-2 old→new id remapper for token references and bare-id magic fields.
	 *
	 * @var Reference_Rewriter
	 */
	private Reference_Rewriter $rewriter;

	/**
	 * Supplies the target recipe's user type (triggers inherit it).
	 *
	 * @var Recipe_Store
	 */
	private Recipe_Store $recipe_store;

	/**
	 * Pass-2 work list: new_id => { type, recipe_id, node, fields }.
	 *
	 * @var array<int,array{type:string,recipe_id:int,node:array<string,mixed>,fields:array<string,mixed>}>
	 */
	private array $created = array();

	/**
	 * Old id => new id translation table built during Pass 1.
	 *
	 * @var array<int,int>
	 */
	private array $id_map = array();

	/**
	 * The target recipe's user type, resolved once per run.
	 *
	 * @var string
	 */
	private string $recipe_type = 'user';

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @param Recipe_Replay_Writer|null $writer
	 * @param Reference_Rewriter|null   $rewriter
	 * @param Recipe_Store|null         $recipe_store
	 */
	public function __construct(
		?Recipe_Replay_Writer $writer = null,
		?Reference_Rewriter $rewriter = null,
		?Recipe_Store $recipe_store = null
	) {
		$this->writer       = $writer ?? new Recipe_Replay_Writer();
		$this->rewriter     = $rewriter ?? new Reference_Rewriter();
		$this->recipe_store = $recipe_store ?? Database::get_recipe_store();
	}

	/**
	 * Materialize a whole canonical tree into a target recipe.
	 *
	 * @param array<string,mixed> $tree      Canonical tree (Recipe_Tree_Builder shape).
	 * @param int                 $recipe_id Target recipe id.
	 *
	 * @return Materialization_Result|WP_Error Result (root = the recipe id) or the first failure.
	 */
	public function materialize_tree( array $tree, int $recipe_id ) {
		$this->reset( $recipe_id );

		// PASS 1 — triggers first (token producers), then content.
		foreach ( (array) ( $tree['triggers'] ?? array() ) as $trigger_node ) {
			$error = $this->create_node( (array) $trigger_node, $recipe_id, $recipe_id, null );
			if ( $error instanceof WP_Error ) {
				return $error;
			}
		}

		foreach ( (array) ( $tree['content'] ?? array() ) as $node ) {
			$error = $this->create_node( (array) $node, $recipe_id, $recipe_id, null );
			if ( $error instanceof WP_Error ) {
				return $error;
			}
		}

		// PASS 2 — rewrite references on every created entity.
		$rewrite_error = $this->rewrite_all();
		if ( $rewrite_error instanceof WP_Error ) {
			return $rewrite_error;
		}

		return new Materialization_Result( $recipe_id, $this->id_map );
	}

	/**
	 * Materialize a single (sub)tree node — and everything beneath it — into a
	 * target recipe under an optional parent. The entity-duplicate flow reuses
	 * this for partial-recipe copy / paste.
	 *
	 * When the subtree ROOT is an action that lives inside a block path, the
	 * optional `$path_code` re-places it into the source's path bucket so a
	 * nested item duplicated on its own keeps its path.
	 *
	 * @param array<string,mixed> $node      The subtree root node.
	 * @param int                 $recipe_id Target recipe id.
	 * @param int|null            $parent_id Parent post id (defaults to the recipe).
	 * @param string|null         $path_code Block path bucket for an action root, or null.
	 *
	 * @return Materialization_Result|WP_Error Result (root = the node's new id) or the first failure.
	 */
	public function materialize_into( array $node, int $recipe_id, ?int $parent_id = null, ?string $path_code = null ) {
		$this->reset( $recipe_id );

		$parent_id = $parent_id ?? $recipe_id;

		$error = $this->create_node( $node, $recipe_id, $parent_id, $path_code );
		if ( $error instanceof WP_Error ) {
			return $this->abandon_partial( $error );
		}

		$rewrite_error = $this->rewrite_all();
		if ( $rewrite_error instanceof WP_Error ) {
			return $this->abandon_partial( $rewrite_error );
		}

		$root_id = (int) ( $this->id_map[ (int) ( $node['id'] ?? 0 ) ] ?? 0 );
		if ( $root_id <= 0 ) {
			return $this->abandon_partial(
				new WP_Error(
					'materialize_root_unmapped',
					esc_html_x( 'The item could not be duplicated.', 'Recipe replay error', 'uncanny-automator' )
				)
			);
		}

		return new Materialization_Result( $root_id, $this->id_map );
	}

	/**
	 * Delete everything this run created, then hand back the original error.
	 *
	 * Only `materialize_into()` needs this. `materialize_tree()` writes into a
	 * recipe shell its caller discards via `Recipe_Duplication_Service::abandon_replay()`,
	 * whereas `materialize_into()` writes into a live recipe the user is editing
	 * — there is no container to bin, so the nodes have to go individually.
	 *
	 * Reverse order because `$created` is insertion-ordered and parents are
	 * created before their children; unwinding backwards means no rebuild
	 * triggered by `after_delete_post` ever walks an orphan.
	 *
	 * Covers only what the writer reported back. A store that inserts and then
	 * throws (several do — the insert sits inside the store's own try) never
	 * returns its id, so that post is invisible here and survives as an orphan.
	 * Closing that needs the stores to report ids on failure; tracked separately
	 * rather than guessed at from this side.
	 *
	 * @param WP_Error $error The failure to surface once cleanup is done.
	 *
	 * @return WP_Error The same error, unchanged.
	 */
	private function abandon_partial( WP_Error $error ): WP_Error {
		foreach ( array_reverse( array_keys( $this->created ) ) as $new_id ) {
			if ( ! wp_delete_post( (int) $new_id, true ) ) {
				automator_log(
					sprintf( 'materialize rollback could not delete post %d (recipe %d)', (int) $new_id, $this->recipe_id_for_log() ),
					'Recipe_Tree_Materializer',
					true
				);
			}
		}

		$this->created = array();
		$this->id_map  = array();

		return $error;
	}

	/**
	 * The recipe this run targeted, for log context only.
	 *
	 * @return int
	 */
	private function recipe_id_for_log(): int {
		$first = reset( $this->created );

		return is_array( $first ) ? (int) ( $first['recipe_id'] ?? 0 ) : 0;
	}

	/**
	 * Reset per-run state and resolve the target recipe's user type once.
	 *
	 * @param int $recipe_id Target recipe id.
	 */
	private function reset( int $recipe_id ): void {
		$this->created = array();
		$this->id_map  = array();

		$recipe            = $this->recipe_store->get( $recipe_id );
		$this->recipe_type = $recipe ? $recipe->get_recipe_type()->get_value() : 'user';
	}

	/**
	 * Create one node (Pass 1) and recurse into its children. Returns a
	 * WP_Error to abort, or null on success.
	 *
	 * @param array<string,mixed> $node      The tree node.
	 * @param int                 $recipe_id Target recipe id.
	 * @param int                 $parent_id Parent post (recipe, loop, or block).
	 * @param string|null         $path_code Path bucket when this node is a block child; null otherwise.
	 *
	 * @return WP_Error|null
	 */
	private function create_node( array $node, int $recipe_id, int $parent_id, ?string $path_code ): ?WP_Error {
		$kind = (string) ( $node['kind'] ?? '' );

		if ( 'trigger' === $kind ) {
			return $this->persist( $node, 'trigger', $recipe_id, $this->writer->create_trigger( $recipe_id, $this->recipe_type, $node ) );
		}
		if ( 'action' === $kind ) {
			return $this->persist( $node, 'action', $recipe_id, $this->writer->create_action( $recipe_id, $parent_id, $node, $path_code ) );
		}
		if ( 'block' === $kind ) {
			return $this->create_block( $node, $recipe_id, $parent_id );
		}

		// Modifier nodes carry `block_modifier_type` instead of `kind`. When
		// duplicate_entity extracts a standalone modifier, `parent_id` is
		// already the owning block/loop id. The discriminator (loop_filter /
		// filter condition) rides the node into the unified modifier store.
		$modifier_type = (string) ( $node['block_modifier_type'] ?? '' );
		if ( '' !== $modifier_type ) {
			return $this->persist( $node, 'filter_condition', $recipe_id, $this->writer->create_block_modifier( $recipe_id, $parent_id, $node ) );
		}

		return new WP_Error(
			'materialize_unknown_node',
			/* translators: %s Tree node kind. */
			sprintf( esc_html_x( 'Unknown recipe item type: %s', 'Recipe replay error', 'uncanny-automator' ), $kind )
		);
	}

	/**
	 * Persist a unified `block` node, then its modifiers and path children.
	 *
	 * @param array<string,mixed> $node      Block node.
	 * @param int                 $recipe_id Target recipe id.
	 * @param int                 $parent_id Parent post (recipe or enclosing block).
	 *
	 * @return WP_Error|null
	 */
	private function create_block( array $node, int $recipe_id, int $parent_id ): ?WP_Error {
		$result = $this->persist( $node, 'block', $recipe_id, $this->writer->create_block( $recipe_id, $parent_id, $node ) );
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		$new_block_id = $this->id_map[ (int) ( $node['id'] ?? 0 ) ];

		foreach ( (array) ( $node['modifiers'] ?? array() ) as $modifier ) {
			$error = $this->create_node( (array) $modifier, $recipe_id, $new_block_id, null );
			if ( $error instanceof WP_Error ) {
				return $error;
			}
		}

		foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
			$bucket = (string) ( $path['path_code'] ?? '' );
			foreach ( (array) ( $path['items'] ?? array() ) as $item ) {
				$error = $this->create_node( (array) $item, $recipe_id, $new_block_id, '' !== $bucket ? $bucket : null );
				if ( $error instanceof WP_Error ) {
					return $error;
				}
			}
		}

		return null;
	}

	/**
	 * Common Pass-1 bookkeeping: surface a writer failure, or record the
	 * old→new mapping and queue Pass-2 rewrite work.
	 *
	 * Loop fields are recorded in their wire shape (token-type loops embed an
	 * entity post id in `LOOP_TOKEN`); item fields are recorded exactly as the
	 * writer persisted them.
	 *
	 * @param array<string,mixed> $node      The tree node.
	 * @param string              $type      Entity type key for Pass-2 dispatch.
	 * @param int                 $recipe_id Target recipe id.
	 * @param int|WP_Error        $new_id    Writer result.
	 * @param bool                $is_new    False when the writer reused an existing
	 *                                       entity instead of creating one.
	 *
	 * @return WP_Error|null
	 */
	private function persist( array $node, string $type, int $recipe_id, $new_id, bool $is_new = true ): ?WP_Error {
		if ( $new_id instanceof WP_Error ) {
			return $new_id;
		}

		$fields = in_array( $type, array( 'trigger', 'action' ), true )
			? $this->writer->item_fields( $node )
			: ( is_array( $node['fields'] ?? null ) ? $node['fields'] : array() );

		$this->id_map[ (int) ( $node['id'] ?? 0 ) ] = (int) $new_id;

		// `id_map` answers "where does this old id point now"; `created` answers
		// "what did this run make, and must therefore unmake". A reused entity
		// belongs in the first only — rolling it back would delete something the
		// user already had.
		if ( ! $is_new ) {
			return null;
		}

		$this->created[ (int) $new_id ] = array(
			'type'      => $type,
			'recipe_id' => $recipe_id,
			'node'      => $node,
			'fields'    => $fields,
		);

		return null;
	}

	/**
	 * Pass 2: rewrite token/condition references on every created entity and
	 * write them back through the replay writer.
	 *
	 * @return WP_Error|null
	 */
	private function rewrite_all(): ?WP_Error {
		foreach ( $this->created as $new_id => $meta ) {
			$rewritten = $this->rewriter->rewrite( $meta['fields'], $this->id_map );
			// Magic link/button triggers self-reference their own post id as a
			// bare integer field value — no token pattern to match, so those
			// keys are rewritten by name.
			if ( 'trigger' === $meta['type'] ) {
				$rewritten = $this->rewriter->rewrite_bare_id_fields( (array) $rewritten, $this->id_map );
			}
			if ( $rewritten === $meta['fields'] ) {
				continue;
			}

			$error = $this->write_back( (int) $new_id, $meta['type'], (int) $meta['recipe_id'], (array) $meta['node'], (array) $rewritten );
			if ( $error instanceof WP_Error ) {
				return $error;
			}
		}

		return null;
	}

	/**
	 * Dispatch a Pass-2 write to the writer's update method for the type.
	 *
	 * @param int                 $new_id    Persisted entity id.
	 * @param string              $type      Entity type key.
	 * @param int                 $recipe_id Target recipe id.
	 * @param array<string,mixed> $node      Original tree node.
	 * @param array<string,mixed> $fields    Rewritten field bag.
	 *
	 * @return WP_Error|null
	 */
	private function write_back( int $new_id, string $type, int $recipe_id, array $node, array $fields ): ?WP_Error {
		switch ( $type ) {
			case 'trigger':
				return $this->writer->update_trigger_fields( $new_id, $recipe_id, $this->recipe_type, $node, $fields );
			case 'action':
				return $this->writer->update_action_fields( $new_id, $recipe_id, $node, $fields );
			case 'block':
				return $this->writer->update_block_fields( $new_id, $fields );
			case 'filter_condition':
				return $this->writer->update_block_modifier_fields( $new_id, $fields );
			default:
				return null;
		}
	}
}
