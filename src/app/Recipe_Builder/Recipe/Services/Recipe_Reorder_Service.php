<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Reorder_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Placement_Rule_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Tree_Builder;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use WP_Error;

/**
 * Tree-aware recipe reorder.
 *
 * Walks the wire body's `actions[]` slot (and nested block `paths[].items[]`
 * / `modifiers[]` slots) and writes each addressed slot's new `menu_order`
 * sequence to storage. Absent slots are untouched.
 *
 * **Two slot kinds (see {@see is_path_marker_slot()}).** The FE's slot-variant
 * body builder (`wrapAncestors`) addresses a deep target by sending a sparse
 * path-from-root: each ancestor carries only the single child needed to
 * continue toward the target, omitting untouched siblings (the server
 * preserves them). So a present slot is either:
 *   - a **reorder target** — the complete new order for that collection,
 *     validated as a permutation of its persisted children and written to
 *     `menu_order`; or
 *   - a **path marker** — a single child whose own descendant slot is
 *     populated. Membership-validated (unknown / duplicate ids still error)
 *     but NOT required to list every persisted sibling, and its `menu_order`
 *     is left untouched (its position among preserved siblings must not move).
 *
 * **Reads via `Recipe_Tree_Builder`.** The canonical persisted tree the FE
 * consumes is the same shape we validate the body against — no duplicate
 * post-table queries in this service.
 *
 * **Writes via `Recipe_Reorder_Store`.** Single seam for `menu_order`
 * writes regardless of post type (uo-action / uo-block /
 * uo-block-modifier) — every reorderable child is just a post id at this
 * layer, so per-domain stores stay aggregate-shaped.
 *
 * Validation is structural and fail-fast: the first reorder-target slot that
 * is not a permutation of its persisted children returns a typed `WP_Error`
 * and the walk halts. Already-applied slot writes are not rolled back — last
 * writer wins, matching the legacy reorder semantics.
 *
 * **Third path: full-tree reconcile (FE `MOVE` / `FULL_TREE`).** When the
 * submitted body enumerates the *entire* recipe — its flattened item / block /
 * modifier id-set equals the persisted recipe's (see {@see is_full_tree_body()})
 * — the body is authoritative over membership, not just order. This is how a
 * cross-path drag-and-drop arrives: an action relocated from one branch to
 * another. Such a body is reconciled by writing each entry's new parent +
 * path_code + menu_order ({@see apply_full_tree_reconcile()}); modifiers carry
 * order only and are never reparented. A body that does NOT enumerate the whole
 * recipe (a sparse `SLOT` body, the duplication service's insert-after body, or
 * a duplicate-id error case) falls through to the per-slot permutation path
 * above, which preserves untouched siblings and surfaces the same typed errors.
 */
final class Recipe_Reorder_Service {

	/**
	 * Storage spacing between menu_order values, mirroring the legacy
	 * `update_actions_order` convention so the recipe-builder UI's edit
	 * affordances continue to land between siblings cleanly.
	 *
	 * Public: it is a de facto cross-service convention — other resequence
	 * sites (e.g. `Block_Crud_Service`) share it rather than duplicating the
	 * literal, so an untouched prefix already sitting on step-10 boundaries
	 * can no-op instead of every sibling being rewritten.
	 *
	 * @var int
	 */
	public const MENU_ORDER_STEP = 10;

	/**
	 * Lazy singleton instance returned by {@see instance()}.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Reads the canonical persisted recipe tree used for permutation
	 * validation. Never queried more than once per `reorder()` call.
	 *
	 * @var Recipe_Tree_Builder
	 */
	private Recipe_Tree_Builder $tree_builder;

	/**
	 * Persistence boundary for `menu_order` writes — the only seam in this
	 * service that touches the posts table.
	 *
	 * @var Recipe_Reorder_Store
	 */
	private Recipe_Reorder_Store $reorder_store;

	/**
	 * Persistence boundary for parent + path_code writes during a full-tree
	 * reconcile. `menu_order` still flows through {@see $reorder_store}; this
	 * seam handles the `reparent` / `reparent_to_path` / `delete_path_code`
	 * moves a cross-path drag-and-drop requires.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * The placement rules a full-tree move must satisfy before anything is written.
	 *
	 * @var Placement_Rule_Service
	 */
	private Placement_Rule_Service $placement;

	/**
	 * Names an action in a refusal, as the go-live gate does.
	 *
	 * @var Recipe_Violation_Service
	 */
	private Recipe_Violation_Service $violations;

	/**
	 * Whether the reorder in progress has written a row, so a refusal after that
	 * point still reports the recipe: steps written before it keep their place.
	 *
	 * @var bool
	 */
	private bool $wrote_a_row = false;

	/**
	 * Constructor with lazy-default DI per Rule 9c.
	 *
	 * Production callers can use `Recipe_Reorder_Service::instance()` and
	 * get the shared dependencies; tests inject fakes via the constructor.
	 *
	 * @param Recipe_Tree_Builder|null      $tree_builder  Read seam for the persisted recipe tree. Defaults to {@see Recipe_Tree_Builder::instance()}.
	 * @param Recipe_Reorder_Store|null     $reorder_store Write seam for `menu_order` updates. Defaults to {@see Database::get_recipe_reorder_store()}.
	 * @param Block_Store|null              $block_store   Write seam for parent + path_code moves. Defaults to {@see Database::get_block_store()}.
	 * @param Placement_Rule_Service|null   $placement     Placement rules. Defaults to {@see Placement_Rule_Service::instance()}.
	 * @param Recipe_Violation_Service|null $violations    Action display names for a refusal. Defaults to {@see Recipe_Violation_Service::instance()}.
	 */
	public function __construct(
		?Recipe_Tree_Builder $tree_builder = null,
		?Recipe_Reorder_Store $reorder_store = null,
		?Block_Store $block_store = null,
		?Placement_Rule_Service $placement = null,
		?Recipe_Violation_Service $violations = null
	) {
		$this->tree_builder  = $tree_builder ?? Recipe_Tree_Builder::instance();
		$this->reorder_store = $reorder_store ?? Database::get_recipe_reorder_store();
		$this->block_store   = $block_store ?? Database::get_block_store();
		$this->placement     = $placement ?? Placement_Rule_Service::instance();
		$this->violations    = $violations ?? Recipe_Violation_Service::instance();
	}

	/**
	 * Lazy singleton accessor for production wiring.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Apply a tree-shaped reorder body to a recipe.
	 *
	 * Body without an `actions` key is a no-op. Body whose `actions` is not
	 * an array is rejected with `automator_rest_invalid_reorder_payload`.
	 * Recipe id that resolves to nothing is rejected with
	 * `automator_rest_invalid_recipe_id`.
	 *
	 * @param int                  $recipe_id Recipe post id (already validated by the route's `validate_recipe_id`).
	 * @param array<string, mixed> $body      Decoded JSON body. May be empty; missing/invalid keys are handled.
	 *
	 * @return true|WP_Error `true` on success, typed `WP_Error` on validation failure.
	 */
	public function reorder( int $recipe_id, array $body ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $body ) {
				$this->wrote_a_row = false;

				if ( ! array_key_exists( 'actions', $body ) ) {
					return true;
				}

				$actions = $body['actions'];
				if ( ! is_array( $actions ) ) {
					return $this->invalid_payload(
						esc_html_x( '`actions` must be an array.', 'Recipe reorder error', 'uncanny-automator' )
					);
				}

				$tree = $this->tree_builder->build( $recipe_id );
				if ( null === $tree ) {
					return $this->invalid_recipe_id();
				}

				$result = $this->is_full_tree_body( $actions, $tree )
					? $this->apply_full_tree_reconcile( $recipe_id, $actions, $tree )
					: $this->apply_recipe_actions_slot( $recipe_id, $actions, $tree );

				if ( true === $result || $this->wrote_a_row ) {
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );
				}

				return $result;
			}
		);
	}

	/**
	 * Validate + write the recipe-root `actions` slot, then recurse into
	 * nested block entries.
	 *
	 * @param int                              $recipe_id Recipe post id, surfaced as `parent_id` in error data.
	 * @param list<array<string, mixed>> $entries   Body's `actions[]` in its desired order.
	 * @param array<string, mixed>             $tree      Canonical persisted tree from {@see Recipe_Tree_Builder::build()}.
	 *
	 * @return true|WP_Error
	 */
	private function apply_recipe_actions_slot( int $recipe_id, array $entries, array $tree ) {
		$persisted_entries = $tree['content'] ?? array();

		$applied = $this->apply_ordered_slot(
			$entries,
			$persisted_entries,
			array(
				'parent_id'   => $recipe_id,
				'parent_kind' => 'recipe',
				'slot'        => 'actions',
			)
		);

		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		return $this->apply_nested_block_slots( $entries, $persisted_entries );
	}

	/**
	 * Discriminate a full-tree body (FE `MOVE` / `FULL_TREE`) from a sparse
	 * slot body (FE `SLOT`, and the duplication service's insert-after bodies).
	 *
	 * A full-tree body is recipe-complete: flattening every submitted item /
	 * block id yields exactly the persisted recipe's reorderable id-set, each
	 * id appearing once. Only then is the submitted tree authoritative over
	 * membership and safe to reconcile parent + path_code against.
	 *
	 * A sparse body — a path-marker continuation, a single-slot reorder, or a
	 * duplicate-id error case — does NOT enumerate the whole recipe, so its
	 * flattened set won't match the persisted set; it falls through to the
	 * existing per-slot permutation path, which preserves untouched siblings
	 * and surfaces the same typed errors as before.
	 *
	 * @param array $actions Submitted top-level entries.
	 * @param array $tree    Persisted canonical tree.
	 * @return bool
	 */
	private function is_full_tree_body( array $actions, array $tree ): bool {
		$desired = array();
		$this->collect_desired_placements( $actions, 0, '', $desired );

		$persisted = $this->collect_persisted_ids( $tree['content'] ?? array() );

		if ( array() === $persisted || count( $desired ) !== count( $persisted ) ) {
			return false;
		}
		foreach ( $desired as $id => $_p ) {
			if ( ! isset( $persisted[ $id ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Full-tree reconcile (FE `MOVE` / `FULL_TREE`). The submitted tree is the
	 * authoritative membership + order: every reorderable id appears exactly
	 * once, placed under its new parent/path. Membership is already asserted by
	 * {@see is_full_tree_body()}; this writes parent + path_code + menu_order
	 * per item.
	 *
	 * @param int   $recipe_id Recipe post id.
	 * @param array $actions   Submitted top-level entries.
	 * @return true|WP_Error
	 */
	private function apply_full_tree_reconcile( int $recipe_id, array $actions, array $tree ) {
		$persisted = array();
		$this->collect_persisted_placements( $tree['content'] ?? array(), $recipe_id, '', $persisted );

		// A step whose placement changes, or that sits inside a block whose placement changes, is checked
		// where the body lands it before the first write, so a refused body moves nothing. A step that stays
		// put is not a placement: a stored nesting the rules reject stays reorderable around, and go-live is
		// what refuses it.
		$rejection = $this->placement_rejection(
			$actions,
			$this->persisted_candidates( (array) ( $tree['content'] ?? array() ) ),
			$persisted,
			$recipe_id,
			'',
			array(),
			false
		);
		if ( null !== $rejection ) {
			return $rejection;
		}

		$desired = array();
		$this->collect_desired_placements( $actions, $recipe_id, '', $desired );

		foreach ( $desired as $id => $placement ) {
			try {
				$this->write_placement( (int) $id, $placement, $persisted[ $id ] ?? null );
			} catch ( \Exception $e ) {
				return $this->error(
					'automator_rest_reorder_write_failed',
					esc_html_x( 'A step could not be moved. Steps placed before it keep their new position; reload the recipe before trying again.', 'Recipe reorder error', 'uncanny-automator' ),
					500,
					array( 'id' => (int) $id )
				);
			}
		}

		return true;
	}

	/**
	 * The refusal for the first submitted step a placement rule rejects where
	 * the body lands it, or null when every step is allowed. Only a step whose
	 * parent or path changes, or that sits inside a block whose placement
	 * changes, is checked. Chains come from the submitted ancestry; block
	 * types, fields and action codes from the persisted tree, since the body
	 * carries ids and shape only. A path with an empty path_code is skipped,
	 * as the writer skips it.
	 *
	 * @param array<int,mixed>        $entries     Submitted entries at one level.
	 * @param array<int|string,array> $nodes       id => persisted tree entry.
	 * @param array<int|string,array> $placements  id => persisted parent_id + path_code.
	 * @param int                     $parent_id   The parent the entries land under.
	 * @param string                  $path_code   The parent's path they land on; '' at the recipe root.
	 * @param array<int,array>        $chain       Submitted blocks above these entries, outermost first.
	 * @param bool                    $moved_above Whether a block above these entries changed placement.
	 * @return WP_Error|null
	 */
	private function placement_rejection( array $entries, array $nodes, array $placements, int $parent_id, string $path_code, array $chain, bool $moved_above ): ?WP_Error {
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'id', $entry ) || ! isset( $nodes[ $entry['id'] ] ) ) {
				continue;
			}
			$node  = $nodes[ $entry['id'] ];
			$kind  = (string) ( $node['kind'] ?? '' );
			$moved = $moved_above || ! $this->placement_matches(
				array(
					'parent_id' => $parent_id,
					'path_code' => $path_code,
				),
				$placements[ $entry['id'] ] ?? null
			);

			if ( 'block' === $kind ) {
				$block_type = (string) ( $node['block_type'] ?? '' );
				if ( $moved ) {
					$violation = $this->placement->violation( $chain, $this->placement->block_candidate( $block_type ) );
					if ( null !== $violation ) {
						return $this->placement->rejection( $violation, $block_type );
					}
				}

				$fields = is_array( $node['fields'] ?? null ) ? $node['fields'] : array();
				foreach ( (array) ( $entry['paths'] ?? array() ) as $path ) {
					$child_path = is_array( $path ) ? (string) ( $path['path_code'] ?? '' ) : '';
					if ( '' === $child_path ) {
						continue;
					}
					$deeper    = $chain;
					$deeper[]  = Placement_Rule_Service::entry( $block_type, $fields, $child_path );
					$rejection = $this->placement_rejection( (array) ( $path['items'] ?? array() ), $nodes, $placements, (int) $entry['id'], $child_path, $deeper, $moved );
					if ( null !== $rejection ) {
						return $rejection;
					}
				}
				continue;
			}

			if ( 'action' === $kind && $moved ) {
				$code        = (string) ( $node['code'] ?? '' );
				$integration = (string) ( $node['integration_code'] ?? '' );
				$violation   = $this->placement->violation( $chain, $this->placement->action_candidate( $code, $integration ) );
				if ( null !== $violation ) {
					return $this->placement->rejection( $violation, $this->violations->display_name( null, $integration, $code, 'action' ) );
				}
			}
		}

		return null;
	}

	/**
	 * id => persisted tree entry for every block and item in the content, at any depth.
	 *
	 * @param array<int,mixed> $content Persisted tree content entries.
	 * @return array<int|string,array>
	 */
	private function persisted_candidates( array $content ): array {
		$out = array();
		foreach ( $content as $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'id', $entry ) ) {
				continue;
			}
			$out[ $entry['id'] ] = $entry;
			foreach ( (array) ( $entry['paths'] ?? array() ) as $path ) {
				$items = is_array( $path ) ? ( $path['items'] ?? array() ) : array();
				$out  += $this->persisted_candidates( is_array( $items ) ? $items : array() );
			}
		}

		return $out;
	}

	/**
	 * Walk the persisted tree recording where each row currently sits, in the
	 * same shape {@see collect_desired_placements()} produces for the
	 * submitted body, so the two can be compared directly.
	 *
	 * Only `parent_id` and `path_code` are recorded. Position is deliberately
	 * left out: `menu_order` is compared against the row itself in
	 * {@see \Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Reorder_Store::set_menu_order()},
	 * which is exact, where the tree's ordering is derived.
	 *
	 * @param array  $content   Persisted entries at this level.
	 * @param int    $parent_id The post id these entries are children of.
	 * @param string $path_code The bucket these entries sit in ('' at recipe root).
	 * @param array  $out       Accumulator: id => ['parent_id'=>int,'path_code'=>string].
	 * @return void
	 */
	private function collect_persisted_placements( array $content, int $parent_id, string $path_code, array &$out ): void {
		foreach ( $content as $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'id', $entry ) ) {
				continue;
			}

			$out[ $entry['id'] ] = array(
				'parent_id' => $parent_id,
				'path_code' => $path_code,
			);

			if ( 'block' !== ( $entry['kind'] ?? '' ) ) {
				continue;
			}

			$block_id = (int) $entry['id'];

			if ( isset( $entry['paths'] ) && is_array( $entry['paths'] ) ) {
				foreach ( $entry['paths'] as $path ) {
					if ( ! is_array( $path ) ) {
						continue;
					}
					$items = $path['items'] ?? array();
					if ( is_array( $items ) ) {
						$this->collect_persisted_placements(
							$items,
							$block_id,
							(string) ( $path['path_code'] ?? '' ),
							$out
						);
					}
				}
			}

			if ( isset( $entry['modifiers'] ) && is_array( $entry['modifiers'] ) ) {
				foreach ( $entry['modifiers'] as $modifier ) {
					if ( is_array( $modifier ) && array_key_exists( 'id', $modifier ) ) {
						$out[ $modifier['id'] ] = array(
							'parent_id' => $block_id,
							'path_code' => '',
						);
					}
				}
			}
		}
	}

	/**
	 * Walk the submitted tree, recording each item/block/modifier's desired
	 * placement. Items and blocks carry a parent + path_code (they can be
	 * relocated); modifiers carry only their menu_order order (flagged
	 * `is_modifier`, never reparented). Modifiers are still recorded so the
	 * full-tree conservation check in {@see is_full_tree_body()} matches the
	 * persisted set — a real FE `MOVE` body enumerates a filter block's
	 * `modifiers[]` alongside its `paths[]`.
	 *
	 * @param array  $entries   Submitted entries at this level.
	 * @param int    $parent_id The post id these entries are children of.
	 * @param string $path_code The bucket these entries sit in ('' at recipe root).
	 * @param array  $out       Accumulator: id => ['parent_id'=>int,'path_code'=>string,'order'=>int,'is_modifier'=>bool].
	 * @return void
	 */
	private function collect_desired_placements( array $entries, int $parent_id, string $path_code, array &$out ): void {
		$index = 0;
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'id', $entry ) ) {
				continue;
			}
			++$index;
			$out[ $entry['id'] ] = array(
				'parent_id'   => $parent_id,
				'path_code'   => $path_code,
				'order'       => $index * self::MENU_ORDER_STEP,
				'is_modifier' => false,
			);

			if ( 'block' !== ( $entry['kind'] ?? '' ) ) {
				continue;
			}

			if ( isset( $entry['paths'] ) && is_array( $entry['paths'] ) ) {
				foreach ( $entry['paths'] as $path ) {
					if ( ! is_array( $path ) ) {
						continue;
					}
					$child_path = (string) ( $path['path_code'] ?? '' );
					$items      = $path['items'] ?? array();
					if ( '' === $child_path || ! is_array( $items ) ) {
						continue;
					}
					$this->collect_desired_placements( $items, (int) $entry['id'], $child_path, $out );
				}
			}

			if ( isset( $entry['modifiers'] ) && is_array( $entry['modifiers'] ) ) {
				$this->collect_desired_modifier_orders( $entry['modifiers'], $out );
			}
		}
	}

	/**
	 * Record menu_order placements for a block's `modifiers[]`. Modifiers are
	 * never reparented or re-pathed, so they carry the `is_modifier` flag and
	 * only their ordered position.
	 *
	 * @param array $modifiers Submitted `modifiers[]` in desired order.
	 * @param array $out       Accumulator shared with {@see collect_desired_placements()}.
	 * @return void
	 */
	private function collect_desired_modifier_orders( array $modifiers, array &$out ): void {
		$index = 0;
		foreach ( $modifiers as $modifier ) {
			if ( ! is_array( $modifier ) || ! array_key_exists( 'id', $modifier ) ) {
				continue;
			}
			++$index;
			$out[ $modifier['id'] ] = array(
				'parent_id'   => 0,
				'path_code'   => '',
				'order'       => $index * self::MENU_ORDER_STEP,
				'is_modifier' => true,
			);
		}
	}

	/**
	 * Flatten persisted tree content (recipe root, nested block paths, and
	 * block modifiers) into a set of reorderable ids. Modifiers are included
	 * so the conservation check sees the same id-set a real FE `MOVE` body
	 * enumerates.
	 *
	 * @param array $content Persisted `content[]` (or a block's path items).
	 * @return array<int|string, true>
	 */
	private function collect_persisted_ids( array $content ): array {
		$out = array();
		foreach ( $content as $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'id', $entry ) ) {
				continue;
			}
			$out[ $entry['id'] ] = true;
			if ( 'block' !== ( $entry['kind'] ?? '' ) ) {
				continue;
			}
			if ( isset( $entry['paths'] ) && is_array( $entry['paths'] ) ) {
				foreach ( $entry['paths'] as $path ) {
					$items = is_array( $path ) ? ( $path['items'] ?? array() ) : array();
					if ( is_array( $items ) ) {
						$out += $this->collect_persisted_ids( $items );
					}
				}
			}
			if ( isset( $entry['modifiers'] ) && is_array( $entry['modifiers'] ) ) {
				foreach ( $entry['modifiers'] as $modifier ) {
					if ( is_array( $modifier ) && array_key_exists( 'id', $modifier ) ) {
						$out[ $modifier['id'] ] = true;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * Write one entry's placement.
	 *
	 * Modifiers get menu_order only — they are never reparented or re-pathed.
	 * Items / blocks at recipe-root ('' path) reparent to the recipe and clear
	 * the path meta; a block-path placement reparents to the block on that path.
	 *
	 * A full-tree body enumerates every sibling, not only the moved ones, so
	 * most rows arrive exactly where they already are. Reparenting is skipped
	 * when both the parent and the path already match: `reparent()` costs a
	 * `wp_update_post` and every `save_post` listener on it.
	 *
	 * Both fields are compared together on purpose. Parent alone is not
	 * enough — dragging an item between two paths of the same filter block
	 * keeps the parent and changes only the path, and that still has to
	 * reparent so the next tree read re-buckets it.
	 *
	 * `menu_order` is always offered to the store, which compares it against
	 * the row and no-ops when it matches.
	 *
	 * One asymmetry worth knowing rather than "fixing": the tree substitutes a
	 * block type's default path when the `path_code` meta is empty, so a row
	 * sitting on an implied default reads as already-placed here and keeps its
	 * empty meta instead of being normalised to the explicit value. That is
	 * fine — every read path resolves the empty case to the same default, and
	 * frontend edits always write the path explicitly, so an implied default
	 * only ever comes from migrated legacy storage.
	 *
	 * @param int        $id        Item/block/modifier post id.
	 * @param array      $placement ['parent_id'=>int,'path_code'=>string,'order'=>int,'is_modifier'=>bool].
	 * @param array|null $persisted ['parent_id'=>int,'path_code'=>string] as currently stored, or null when unknown.
	 * @return void
	 */
	private function write_placement( int $id, array $placement, ?array $persisted = null ): void {
		if ( empty( $placement['is_modifier'] ) && ! $this->placement_matches( $placement, $persisted ) ) {
			if ( '' === $placement['path_code'] ) {
				$this->block_store->reparent( $id, $placement['parent_id'] );
				$this->wrote_a_row = true;
				$this->block_store->delete_path_code( $id );
			} else {
				$this->block_store->reparent_to_path( $id, $placement['parent_id'], $placement['path_code'] );
				$this->wrote_a_row = true;
			}
		}

		// The id > 0 guard its sibling write_slot_menu_orders() already has.
		// set_menu_order() deliberately lets a missing post fall through to
		// wp_update_post so the failure surfaces as before — but id 0 makes
		// wp_update_post INSERT rather than update. Unreachable today (callers
		// validate membership against persisted ids first); kept because the
		// invariant is enforced one call up rather than here.
		if ( $id > 0 ) {
			$this->reorder_store->set_menu_order( $id, $placement['order'] );
			$this->wrote_a_row = true;
		}
	}

	/**
	 * Whether a desired placement already matches what is stored.
	 *
	 * Unknown persisted state answers false so the write goes ahead — the
	 * diff is an optimisation and must never be the reason a row is skipped.
	 *
	 * @param array      $placement Desired placement.
	 * @param array|null $persisted Stored placement, or null when unknown.
	 * @return bool
	 */
	private function placement_matches( array $placement, ?array $persisted ): bool {
		if ( null === $persisted ) {
			return false;
		}

		return (int) ( $persisted['parent_id'] ?? -1 ) === (int) $placement['parent_id']
			&& (string) ( $persisted['path_code'] ?? '' ) === (string) $placement['path_code'];
	}

	/**
	 * Apply the `paths[]` and `modifiers[]` slots of a single block entry.
	 *
	 * Owns the explicit slot-key dispatch — submitted `paths` and submitted
	 * `modifiers` are handled by their own slot methods, both of which
	 * delegate to {@see apply_ordered_slot()} for validate-then-write.
	 *
	 * @param int                  $block_id        Block post id, surfaced as `parent_id` in error data.
	 * @param array<string, mixed> $entry           Wire body entry for this block (carries optional `paths` / `modifiers`).
	 * @param array<string, mixed> $persisted_block Persisted tree node for this block (carries `paths[]` and `modifiers[]`).
	 *
	 * @return true|WP_Error
	 */
	private function apply_block_slots( int $block_id, array $entry, array $persisted_block ) {
		$paths = $entry['paths'] ?? null;
		if ( is_array( $paths ) ) {
			$persisted_paths_by_code = $this->index_paths_by_code( $persisted_block['paths'] ?? array() );

			foreach ( $paths as $path_entry ) {
				if ( ! is_array( $path_entry ) ) {
					continue;
				}
				$path_code = (string) ( $path_entry['path_code'] ?? '' );
				$items     = $path_entry['items'] ?? array();
				if ( ! is_array( $items ) || '' === $path_code ) {
					continue;
				}

				$persisted_items = $persisted_paths_by_code[ $path_code ]['items'] ?? array();

				$result = $this->apply_block_path_items_slot( $block_id, $path_code, $items, $persisted_items );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		$modifiers = $entry['modifiers'] ?? null;
		if ( is_array( $modifiers ) ) {
			$persisted_modifiers = $persisted_block['modifiers'] ?? array();
			$result              = $this->apply_block_modifiers_slot( $block_id, $modifiers, $persisted_modifiers );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Validate + write the `items[]` for a single (block, path_code) bucket,
	 * then recurse into nested block entries inside the bucket.
	 *
	 * @param int                              $block_id        Owning block post id.
	 * @param string                           $path_code       Catalog path code that selects the bucket.
	 * @param list<array<string, mixed>> $entries         Body's `items[]` for this bucket, in desired order.
	 * @param list<array<string, mixed>> $persisted_items Persisted children of this bucket from the tree.
	 *
	 * @return true|WP_Error
	 */
	private function apply_block_path_items_slot( int $block_id, string $path_code, array $entries, array $persisted_items ) {
		$applied = $this->apply_ordered_slot(
			$entries,
			$persisted_items,
			array(
				'parent_id'   => $block_id,
				'parent_kind' => 'block',
				'slot'        => 'items',
				'path_code'   => $path_code,
			)
		);
		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		return $this->apply_nested_block_slots( $entries, $persisted_items );
	}

	/**
	 * Validate + write the `modifiers[]` slot for a block.
	 *
	 * Modifiers are leaves — no nested-slot recursion.
	 *
	 * @param int                              $block_id            Owning block post id.
	 * @param list<array<string, mixed>> $entries             Body's `modifiers[]` in desired order.
	 * @param list<array<string, mixed>> $persisted_modifiers Persisted modifiers of this block from the tree.
	 *
	 * @return true|WP_Error
	 */
	private function apply_block_modifiers_slot( int $block_id, array $entries, array $persisted_modifiers ) {
		return $this->apply_ordered_slot(
			$entries,
			$persisted_modifiers,
			array(
				'parent_id'   => $block_id,
				'parent_kind' => 'block',
				'slot'        => 'modifiers',
			)
		);
	}

	/**
	 * Apply one ordered slot: validate the body entries are a permutation of
	 * the persisted entries, then write each body entry's new `menu_order`.
	 *
	 * Slot-agnostic mechanics — slot-specific context (parent_id /
	 * parent_kind / slot / path_code) is supplied by the caller and
	 * surfaced in any returned `WP_Error::data()`.
	 *
	 * @param list<array<string, mixed>> $entries           Body slot in its desired order.
	 * @param list<array<string, mixed>> $persisted_entries Persisted slot from the tree (any order).
	 * @param array<string, mixed>             $context           Diagnostic context returned in error data.
	 *
	 * @return true|WP_Error
	 */
	private function apply_ordered_slot( array $entries, array $persisted_entries, array $context ) {
		$persisted_ids  = $this->extract_ids( $persisted_entries );
		$is_path_marker = $this->is_path_marker_slot( $entries );

		$validation = $this->validate_slot_permutation( $entries, $persisted_ids, $context, ! $is_path_marker );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// A path-marker slot is a single-child continuation toward a deeper
		// reorder target — the child's position among its untouched siblings
		// must be preserved, so we don't rewrite menu_order here. Only true
		// reorder targets (the leaf bucket on each branch) write.
		if ( ! $is_path_marker ) {
			$this->write_slot_menu_orders( $entries );
		}

		return true;
	}

	/**
	 * Recurse into every block entry in `$entries` whose id matches a
	 * persisted block in `$persisted_entries`, applying its nested
	 * `paths[]` / `modifiers[]` slots.
	 *
	 * Body entries whose id has no persisted block counterpart are silently
	 * skipped — permutation validation in {@see apply_ordered_slot()} has
	 * already enforced that every body id is persisted at this slot, so a
	 * missing block here means the entry was an `item` leaf,
	 * not a block.
	 *
	 * @param list<array<string, mixed>> $entries           Body entries already validated by the parent slot.
	 * @param list<array<string, mixed>> $persisted_entries Persisted siblings at the parent slot.
	 *
	 * @return true|WP_Error
	 */
	private function apply_nested_block_slots( array $entries, array $persisted_entries ) {
		$persisted_by_id = $this->index_block_entries( $persisted_entries );

		foreach ( $entries as $entry ) {
			if ( 'block' !== ( $entry['kind'] ?? '' ) ) {
				continue;
			}
			$block_id        = (int) $entry['id'];
			$persisted_block = $persisted_by_id[ $block_id ] ?? null;
			if ( null === $persisted_block ) {
				continue;
			}

			$nested = $this->apply_block_slots( $block_id, $entry, $persisted_block );
			if ( is_wp_error( $nested ) ) {
				return $nested;
			}
		}

		return true;
	}

	/**
	 * Verify `$entries` is a permutation of `$persisted_ids` (same set of
	 * ids, same count, no extras, no duplicates). Returns a typed
	 * `WP_Error` on the first mismatch.
	 *
	 * @param list<array<string, mixed>> $entries          Body entries in desired order.
	 * @param array<int|string, true>          $persisted_ids    Set of persisted ids (id => true).
	 * @param array<string, mixed>             $context          Diagnostic context returned in error data.
	 * @param bool                             $require_complete When true, the slot must list every persisted child (a reorder target). When false (a path-marker slot), only membership is checked — a single-child continuation isn't required to list untouched siblings.
	 *
	 * @return true|WP_Error
	 */
	private function validate_slot_permutation( array $entries, array $persisted_ids, array $context, bool $require_complete = true ) {
		$seen = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'id', $entry ) ) {
				return $this->invalid_payload(
					esc_html_x( 'Each reorder entry must be an object with an `id`.', 'Recipe reorder error', 'uncanny-automator' ),
					$context
				);
			}

			$id = $entry['id'];

			if ( isset( $seen[ $id ] ) ) {
				return $this->duplicate_id( $id, $context );
			}
			$seen[ $id ] = true;

			if ( ! isset( $persisted_ids[ $id ] ) ) {
				return $this->unknown_id( $id, $context );
			}
		}

		if ( $require_complete && count( $entries ) !== count( $persisted_ids ) ) {
			return $this->missing_persisted_child( $context );
		}

		return true;
	}

	/**
	 * A slot is a *path marker* — a single-child continuation toward a deeper
	 * reorder target, not a reorder of this slot — when it holds exactly one
	 * entry that itself carries a populated descendant slot (`paths[].items`
	 * or `modifiers`).
	 *
	 * The FE's slot-variant body builder (`wrapAncestors`) emits ancestors
	 * this way: each ancestor carries only the single child needed to continue
	 * the path toward the target, leaving untouched siblings out of the
	 * payload entirely (the server preserves them). Such slots are
	 * membership-validated but NOT required to list every persisted sibling,
	 * and their `menu_order` is left untouched. Only the leaf bucket on each
	 * branch — the slot actually being reordered — is a full permutation.
	 *
	 * @param list<array<string, mixed>> $entries Body slot entries.
	 *
	 * @return bool
	 */
	private function is_path_marker_slot( array $entries ): bool {
		if ( 1 !== count( $entries ) ) {
			return false;
		}
		$entry = reset( $entries );
		return is_array( $entry ) && $this->entry_has_populated_descendant_slot( $entry );
	}

	/**
	 * True when a body entry carries a non-empty descendant reorder slot — a
	 * `paths[]` bucket holding items, or a non-empty `modifiers[]`. Such an
	 * entry is a continuation toward a deeper reorder, not a leaf being
	 * reordered in place.
	 *
	 * @param array<string, mixed> $entry Body entry.
	 *
	 * @return bool
	 */
	private function entry_has_populated_descendant_slot( array $entry ): bool {
		if ( isset( $entry['paths'] ) && is_array( $entry['paths'] ) ) {
			foreach ( $entry['paths'] as $path ) {
				if ( is_array( $path ) && ! empty( $path['items'] ) && is_array( $path['items'] ) ) {
					return true;
				}
			}
		}

		return isset( $entry['modifiers'] ) && is_array( $entry['modifiers'] ) && array() !== $entry['modifiers'];
	}

	/**
	 * Apply `menu_order` writes for an ordered entry list.
	 *
	 * Each entry's index drives its new `menu_order` value via
	 * {@see MENU_ORDER_STEP} spacing. Entries without a positive id are
	 * skipped silently — permutation validation in
	 * {@see validate_slot_permutation()} has already rejected non-numeric
	 * and missing ids before this is reached.
	 *
	 * @param list<array<string, mixed>> $entries Body entries in desired order.
	 *
	 * @return void
	 */
	private function write_slot_menu_orders( array $entries ): void {
		// array_values: the position comes from the offset, and the request body
		// reaches here validated for membership but not for shape. A JSON object
		// ({"a":{"id":1}}) satisfies every check upstream, and 'a' + 1 is a
		// TypeError — a 500 where every other malformed input returns a typed
		// WP_Error.
		foreach ( array_values( $entries ) as $index => $entry ) {
			$post_id    = (int) ( $entry['id'] ?? 0 );
			$menu_order = ( $index + 1 ) * self::MENU_ORDER_STEP;
			if ( $post_id > 0 ) {
				$this->reorder_store->set_menu_order( $post_id, $menu_order );
				$this->wrote_a_row = true;
			}
		}
	}

	/**
	 * Build a set of ids from a tree slot for permutation lookups.
	 *
	 * Returns `id => true` so callers can `isset()`-check membership in
	 * O(1). Skips entries that aren't arrays or that lack an `id` key.
	 *
	 * @param list<array<string, mixed>> $entries Tree entries from a slot.
	 *
	 * @return array<int|string, true> Set of ids present in `$entries`.
	 */
	private function extract_ids( array $entries ): array {
		$out = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'id', $entry ) ) {
				continue;
			}
			$out[ $entry['id'] ] = true;
		}
		return $out;
	}

	/**
	 * Index a tree slot by id, keeping only entries with `kind: 'block'`.
	 *
	 * Used by {@see apply_nested_block_slots()} to look up the persisted
	 * sub-tree for each body block entry without re-walking the slot.
	 *
	 * @param list<array<string, mixed>> $entries Tree entries from a slot.
	 *
	 * @return array<int|string, array<string, mixed>> Map id => block entry.
	 */
	private function index_block_entries( array $entries ): array {
		$out = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || 'block' !== ( $entry['kind'] ?? '' ) ) {
				continue;
			}
			if ( array_key_exists( 'id', $entry ) ) {
				$out[ $entry['id'] ] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Index a block's persisted `paths[]` by `path_code`.
	 *
	 * Used by {@see apply_block_slots()} to pair each submitted path bucket
	 * with its persisted counterpart in O(1).
	 *
	 * @param list<array<string, mixed>> $paths Persisted `paths[]` from a block tree node.
	 *
	 * @return array<string, array<string, mixed>> Map path_code => path entry.
	 */
	private function index_paths_by_code( array $paths ): array {
		$out = array();
		foreach ( $paths as $path ) {
			if ( ! is_array( $path ) ) {
				continue;
			}
			$code = (string) ( $path['path_code'] ?? '' );
			if ( '' !== $code ) {
				$out[ $code ] = $path;
			}
		}
		return $out;
	}

	// ---------------------------------------------------------------------
	// WP_Error helpers
	// ---------------------------------------------------------------------
	//
	// All reorder errors share the same envelope: `code`, `message`, and a
	// `data` payload that always carries `status` plus any slot context the
	// caller provided. These helpers keep validation logic readable as
	// business rules instead of repeated `new WP_Error(...)` blocks.

	/**
	 * Generic `WP_Error` builder.
	 *
	 * `status` always wins over any `status` key the caller passed in
	 * `$data` — it's a transport-level fact, not a hint.
	 *
	 * @param string               $code    Error code (e.g. `automator_rest_unknown_id`).
	 * @param string               $message Already-translated user-facing message.
	 * @param int                  $status  HTTP status code carried in `data.status`.
	 * @param array<string, mixed> $data    Extra diagnostic fields merged into `data`.
	 *
	 * @return WP_Error
	 */
	private function error( string $code, string $message, int $status, array $data = array() ): WP_Error {
		return new WP_Error( $code, $message, array_replace( $data, array( 'status' => $status ) ) );
	}

	/**
	 * Build slot-error data with the offending `id` guaranteed alongside
	 * the slot context.
	 *
	 * The `id` always wins over any `id` key the caller may have passed in
	 * `$context` — it's a fact the validator just established, not a
	 * caller hint that should be overridable.
	 *
	 * @param int|string           $id      Offending id pulled from the body entry.
	 * @param array<string, mixed> $context Slot-locator fields from the caller.
	 *
	 * @return array<string, mixed> Merged data ready for {@see error()}.
	 */
	private function with_offending_id( $id, array $context ): array {
		return array_replace( $context, array( 'id' => $id ) );
	}

	/**
	 * Body-shape failure (wrong types, missing `id`, etc.).
	 *
	 * Optional `$context` carries slot-locator fields when the failure
	 * happens inside a nested slot walk.
	 *
	 * @param string               $message Already-translated user-facing message.
	 * @param array<string, mixed> $context Slot-locator fields from the caller.
	 *
	 * @return WP_Error
	 */
	private function invalid_payload( string $message, array $context = array() ): WP_Error {
		return $this->error( 'automator_rest_invalid_reorder_payload', $message, 400, $context );
	}

	/**
	 * Recipe id resolves to nothing (deleted, wrong post type, etc.).
	 *
	 * @return WP_Error
	 */
	private function invalid_recipe_id(): WP_Error {
		return $this->error(
			'automator_rest_invalid_recipe_id',
			esc_html_x( 'Recipe not found.', 'Recipe reorder error', 'uncanny-automator' ),
			404
		);
	}

	/**
	 * Body lists the same id twice in a single slot.
	 *
	 * @param int|string           $id      Duplicated id.
	 * @param array<string, mixed> $context Slot-locator fields from the caller.
	 *
	 * @return WP_Error
	 */
	private function duplicate_id( $id, array $context ): WP_Error {
		return $this->error(
			'automator_rest_slot_not_permutation',
			esc_html_x( 'Duplicate id in slot.', 'Recipe reorder error', 'uncanny-automator' ),
			422,
			$this->with_offending_id( $id, $context )
		);
	}

	/**
	 * Body references an id that isn't a persisted child of this slot.
	 *
	 * @param int|string           $id      Unknown id.
	 * @param array<string, mixed> $context Slot-locator fields from the caller.
	 *
	 * @return WP_Error
	 */
	private function unknown_id( $id, array $context ): WP_Error {
		return $this->error(
			'automator_rest_unknown_id',
			esc_html_x( 'Id is not present in the recipe at this slot.', 'Recipe reorder error', 'uncanny-automator' ),
			422,
			$this->with_offending_id( $id, $context )
		);
	}

	/**
	 * Body slot has fewer entries than the persisted slot — at least one
	 * existing child is missing from the new order.
	 *
	 * @param array<string, mixed> $context Slot-locator fields from the caller.
	 *
	 * @return WP_Error
	 */
	private function missing_persisted_child( array $context ): WP_Error {
		return $this->error(
			'automator_rest_slot_not_permutation',
			esc_html_x( 'Slot does not list every persisted child.', 'Recipe reorder error', 'uncanny-automator' ),
			422,
			$context
		);
	}
}
