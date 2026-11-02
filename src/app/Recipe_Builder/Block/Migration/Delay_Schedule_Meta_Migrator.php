<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Migration;

use Uncanny_Automator\App\Recipe_Builder\Action\Enums\Action_Status;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities\Delay_Schedule_Wire_Mapper;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Legacy_Meta_Store;
use Uncanny_Automator\Json_String_Repair;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Store;

/**
 * Wraps actions carrying legacy async meta in delay_schedule blocks.
 *
 * Converts `async_mode`, `async_delay_*`, `async_schedule_*`, `async_custom` action meta into
 * `delay_schedule` blocks. Outside a filter, each span of neighbouring actions with the same delay
 * shares one block at their parent; under a filter, the delay goes above the filter or into one
 * filter copy per delay. Wrapped actions move onto the block's `DELAY-COMPLETED` path, and the
 * source keys are renamed to `_legacy_async_*` so nothing legacy fires on a migrated action. Every
 * pass undoes what an interrupted one left, so a partial pass is safe to repeat.
 *
 * The `async_*` names are wire format owned by {@see Delay_Schedule_Wire_Mapper}; this migrator is
 * one of the two places allowed to read them (Rule 17).
 */
class Delay_Schedule_Meta_Migrator {

	/**
	 * Async meta keys that define delay/schedule configuration.
	 *
	 * Sourced from {@see Delay_Schedule_Wire_Mapper::meta_keys()} so the
	 * canonical wire-key list lives in one place (Rule 17). The migrator
	 * is the legacy wire-format reader; the mapper is the single boundary.
	 *
	 * @return string[]
	 */
	public static function async_meta_keys(): array {
		return ( new Delay_Schedule_Wire_Mapper() )->meta_keys();
	}

	/**
	 * The synthesis key of the delay this migrator hoists above a filter; a copy's delay appends `:copy:{n}`.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $filter_id The original filter's block ID.
	 *
	 * @return string
	 */
	public static function filter_delay_key( int $recipe_id, int $filter_id ): string {
		return sprintf( 'recipe_%d:delay_schedule:filter:%d', $recipe_id, $filter_id );
	}

	/**
	 * Whether a synthesis key names a delay this migrator made for a filter — hoisted, or a copy's.
	 *
	 * @param string $key       Block synthesis key.
	 * @param int    $recipe_id Recipe post ID.
	 *
	 * @return bool
	 */
	public static function is_filter_delay_key( string $key, int $recipe_id ): bool {
		return 0 === strpos( $key, sprintf( 'recipe_%d:delay_schedule:filter:', $recipe_id ) );
	}

	/**
	 * The synthesis key of a delay a wrap made: a span's, or a mixed group's per-action delay, named after its first action.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $action_id The first wrapped action's ID.
	 *
	 * @return string
	 */
	private static function wrap_key( int $recipe_id, int $action_id ): string {
		return sprintf( 'recipe_%d:delay_schedule:%d', $recipe_id, $action_id );
	}

	/**
	 * Whether a synthesis key names a delay a wrap made.
	 *
	 * @param string $key       Block synthesis key.
	 * @param int    $recipe_id Recipe post ID.
	 *
	 * @return bool
	 */
	private static function is_wrap_key( string $key, int $recipe_id ): bool {
		return 1 === preg_match( sprintf( '/^recipe_%d:delay_schedule:\d+$/', $recipe_id ), $key );
	}

	/**
	 * Block database store.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Block-modifier store — copies a filter's condition rows onto its per-delay copies.
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Original condition-group membership, including actions not moved into its filters.
	 *
	 * @var Recipe_Legacy_Meta_Store
	 */
	private Recipe_Legacy_Meta_Store $legacy_meta_store;

	/**
	 * Constructor.
	 *
	 * @param Block_Store                   $block_store       Block database store.
	 * @param Block_Modifier_Store|null     $modifier_store    Block-modifier store; defaults to the shared one.
	 * @param Recipe_Legacy_Meta_Store|null $legacy_meta_store Original condition-group storage.
	 */
	public function __construct( Block_Store $block_store, ?Block_Modifier_Store $modifier_store = null, ?Recipe_Legacy_Meta_Store $legacy_meta_store = null ) {
		$this->block_store       = $block_store;
		$this->modifier_store    = $modifier_store ?? Database::get_block_modifier_store();
		$this->legacy_meta_store = $legacy_meta_store ?? Database::get_recipe_legacy_meta_store();
	}

	/**
	 * Migrate async_mode action meta if present on any actions.
	 *
	 * Scans all actions under the given recipe for async_mode meta and wraps them in
	 * delay_schedule blocks, one per span outside a filter (see {@see self::migrate()}).
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return int[] Array of action IDs that were wrapped in delay blocks. Empty if no migration.
	 */
	public function maybe_migrate( int $recipe_id ): array {
		$async_actions = $this->find_async_actions( $recipe_id );

		if ( empty( $async_actions ) ) {
			return array();
		}

		return $this->migrate( $recipe_id, $async_actions );
	}

	/**
	 * Find every action under a recipe that has `async_mode` meta — across
	 * the *entire* recipe tree, not just direct children.
	 *
	 * Action_Conditions migration runs first and reparents gated actions
	 * under their filter blocks; the descendants walk catches those.
	 * Implementation walks `post_parent` level-by-level through the
	 * `uo-block` / `uo-loop` containers (same shape as
	 * {@see \Uncanny_Automator\Automator_Functions::find_all_recipe_action_descendants})
	 * so the search works on legacy data without the `recipe_id` post-meta
	 * backfill — it does not depend on `WP_Action_Store`'s stamping.
	 *
	 * Rows are a snapshot; the pass mutates the tree, so every consumer re-validates before acting.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return array[] Array of arrays with 'action_id' and 'post_parent'.
	 */
	private function find_async_actions( int $recipe_id ): array {
		global $wpdb;

		// Walk down the tree level-by-level collecting container IDs
		// (recipe + every uo-block / uo-loop descendant).
		$parent_ids      = array( (int) $recipe_id );
		$current_parents = array( (int) $recipe_id );
		$depth           = 0;
		$max_depth       = 20;

		while ( ! empty( $current_parents ) && $depth++ < $max_depth ) {
			$placeholders    = implode( ',', array_fill( 0, count( $current_parents ), '%d' ) );
			$current_parents = array_map(
				'intval',
				// {$placeholders} is a run of %d built from count( $current_parents );
				// the ids themselves are spread through prepare() below.
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL
				$wpdb->get_col(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is one %d per parent id; the ids are spread below.
						"SELECT ID FROM {$wpdb->posts} WHERE post_parent IN ({$placeholders}) AND post_type IN ('uo-loop','uo-block')",
						...$current_parents
					)
				)
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL
			);

			if ( ! empty( $current_parents ) ) {
				$parent_ids = array_merge( $parent_ids, $current_parents );
			}
		}

		// Final query: every uo-action posted under any of those parents,
		// narrowed to those carrying async_mode meta.
		$actions = get_posts(
			array(
				'post_type'       => WP_Action_Store::POST_TYPE,
				'post_parent__in' => $parent_ids,
				'post_status'     => array( Action_Status::DRAFT, Action_Status::PUBLISH ),
				'posts_per_page'  => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'         => 'menu_order',
				'order'           => 'ASC',
				'meta_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'async_mode',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$async_actions = array();
		foreach ( $actions as $action_post ) {
			$parent_id = (int) $action_post->post_parent;
			if ( $this->is_inside_delay_block( $parent_id ) ) {
				$container = $this->span_container( $recipe_id, $parent_id );
				// Any other delay holding live async_*, such as a mixed group's wrap, only has its move and drain finished: re-wrapping would nest a delay.
				if ( null === $container ) {
					$this->block_store->set_path_code( (int) $action_post->ID, Block_Path_Code::DELAY_COMPLETED );
					$this->drain_async_meta( $recipe_id, (int) $action_post->ID );
					continue;
				}
				// A span the pass stopped part-way is rebuilt from its container.
				$parent_id = $container;
			}

			// Self-heal: stamp `recipe_id` post-meta on legacy actions that
			// pre-date `WP_Action_Store`'s automatic stamping. After this
			// pass, indexed `recipe_id` queries find them too. Any existing
			// row blocks the stamp: postmeta keys match case-insensitively, so
			// a field coded RECIPE_ID holds that row and the stamp would
			// overwrite its value (Uoa_Recipe_Id_Field_Migration moves it).
			if ( ! $this->has_recipe_id_row( (int) $action_post->ID ) ) {
				update_post_meta( $action_post->ID, 'recipe_id', $recipe_id );
			}

			$async_actions[] = array(
				'action_id'   => (int) $action_post->ID,
				'post_parent' => $parent_id,
			);
		}

		return $async_actions;
	}

	/**
	 * The container of the span a delay block wraps, or null when the block is not a span's.
	 *
	 * A span's delay carries a wrap key and sits in a container that is not a filter; a wrap key under a
	 * filter is a mixed group's per-action delay.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $block_id  The delay block.
	 *
	 * @return int|null
	 */
	private function span_container( int $recipe_id, int $block_id ): ?int {
		$block = $this->block_store->get( $block_id );
		if ( null === $block || ! self::is_wrap_key( (string) $block['synthesis_key'], $recipe_id ) ) {
			return null;
		}

		$container = $this->block_store->get( (int) $block['parent_id'] );

		return null !== $container && Block_Type::FILTER === $container['block_type'] ? null : (int) $block['parent_id'];
	}

	/**
	 * Whether `$post_parent` is a `uo-block` post with `block_type='delay_schedule'`.
	 * Used to detect actions already wrapped — those must not get re-wrapped.
	 *
	 * @param int $post_parent Action's current post_parent.
	 *
	 * @return bool
	 */
	private function is_inside_delay_block( int $post_parent ): bool {
		if ( $post_parent <= 0 ) {
			return false;
		}
		$parent = get_post( $post_parent );
		if ( ! $parent || 'uo-block' !== $parent->post_type ) {
			return false;
		}
		return Block_Type::DELAY_SCHEDULE === (string) get_post_meta( $parent->ID, 'block_type', true );
	}

	/**
	 * Perform the migration for all async actions, one parent at a time.
	 *
	 * Actions under a filter block follow the spec's three rules (hoist identical delays, one filter
	 * copy per differing delay, mixed groups wrapped where they stand); every other parent gets one
	 * delay_schedule block per span of neighbours with the same delay.
	 *
	 * @param int     $recipe_id     Recipe post ID.
	 * @param array[] $async_actions Rows from {@see self::find_async_actions()}.
	 *
	 * @return int[] Action IDs that were migrated.
	 * @throws \RuntimeException When any parent's actions could not be migrated.
	 */
	private function migrate( int $recipe_id, array $async_actions ): array {
		$migrated_action_ids = array();
		$failed              = array();
		$by_parent           = array();

		foreach ( $async_actions as $action_data ) {
			$by_parent[ (int) $action_data['post_parent'] ][] = (int) $action_data['action_id'];
		}

		// Resolve every parent's gating filter before any write: a gated pass deletes filter copies other rows name.
		$gating  = array();
		$filters = array();
		foreach ( array_keys( $by_parent ) as $parent_id ) {
			$gating[ $parent_id ] = $this->gating_filter( $recipe_id, (int) $parent_id );
			if ( null !== $gating[ $parent_id ] ) {
				$filters[ $gating[ $parent_id ]['id'] ][] = $parent_id;
			}
		}

		foreach ( $filters as $filter_id => $parent_ids ) {
			$filter = $this->block_store->get( (int) $filter_id );
			if ( null === $filter ) {
				continue;
			}
			try {
				$migrated_action_ids = array_merge( $migrated_action_ids, $this->migrate_gated( $recipe_id, $filter ) );
			} catch ( \Exception $e ) {
				automator_log( 'Delay/schedule meta migration failed for filter ' . absint( $filter_id ) . ': ' . $e->getMessage() );
				foreach ( $parent_ids as $parent_id ) {
					$failed = array_merge( $failed, $by_parent[ $parent_id ] );
				}
			}
		}

		// After every gated pass: a split's copy delays sit among the ungated actions and end their spans.
		foreach ( $by_parent as $parent_id => $action_ids ) {
			if ( null !== $gating[ $parent_id ] || null === get_post( $parent_id ) ) {
				continue;
			}
			try {
				$migrated_action_ids = array_merge( $migrated_action_ids, $this->migrate_ungated( $recipe_id, (int) $parent_id ) );
			} catch ( \Exception $e ) {
				automator_log( 'Delay/schedule meta migration failed for parent ' . absint( $parent_id ) . ': ' . $e->getMessage() );
				$failed = array_merge( $failed, $action_ids );
			}
		}

		// Finished spans and filters keep their blocks and drained meta; the failed ones keep async_* for the next pass.
		if ( ! empty( $failed ) ) {
			throw new \RuntimeException( esc_html( sprintf( 'Delay meta for action(s) %s could not be migrated.', implode( ', ', $failed ) ) ) );
		}

		return $migrated_action_ids;
	}

	/**
	 * Wrap the delayed actions of a container that is not a filter, one delay_schedule block per span.
	 *
	 * Undo first: what an interrupted pass left in the container is unwound before the spans are read.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $parent_id The container: the recipe, a loop block or a legacy loop.
	 *
	 * @return int[] Action IDs migrated.
	 * @throws \Exception When a block cannot be stored.
	 */
	private function migrate_ungated( int $recipe_id, int $parent_id ): array {
		$this->undo_unfinished_spans( $recipe_id, $parent_id );

		$migrated = array();
		foreach ( $this->spans( $parent_id ) as $span ) {
			$this->wrap( $recipe_id, $parent_id, $span );
			$migrated = array_merge( $migrated, array_column( $span, 'action_id' ) );
		}

		return $migrated;
	}

	/**
	 * Undo the spans an interrupted pass left in a container, so they are rebuilt whole.
	 *
	 * A span's delay still holding an action with live async_* is unfinished: every action under it gets
	 * its drained meta back first, so members the stopped drain had already reached count again, then
	 * moves back to the container on the delay's own path, and the delay is deleted. An empty span delay
	 * whose key names an action still waiting here is a create the pass stopped after; it is deleted too,
	 * or it would sit between that action and its neighbours.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $parent_id The container.
	 *
	 * @return void
	 * @throws \Exception When a block cannot be moved or deleted.
	 */
	private function undo_unfinished_spans( int $recipe_id, int $parent_id ): void {
		$children = $this->block_store->get_children( $parent_id );

		$waiting = array();
		foreach ( $children as $child ) {
			if ( 'item' === $child['entity_type'] && '' !== (string) ( $child['meta']['async_mode'] ?? '' ) ) {
				$waiting[] = self::wrap_key( $recipe_id, (int) $child['ID'] );
			}
		}

		foreach ( $children as $child ) {
			if ( 'block' !== $child['entity_type'] || ! self::is_wrap_key( (string) $child['synthesis_key'], $recipe_id ) ) {
				continue;
			}

			$action_ids = $this->block_store->collect_descendant_action_ids( (int) $child['id'] );
			$unfinished = array() === $action_ids && in_array( $child['synthesis_key'], $waiting, true );
			foreach ( $action_ids as $action_id ) {
				$unfinished = $unfinished || '' !== (string) get_post_meta( $action_id, 'async_mode', true );
			}
			if ( ! $unfinished ) {
				continue;
			}

			foreach ( $action_ids as $action_id ) {
				$this->restore_async_meta( $recipe_id, $action_id );
				if ( '' !== $child['path_code'] ) {
					$this->block_store->reparent_to_path( $action_id, $parent_id, $child['path_code'] );
				} else {
					$this->block_store->reparent( $action_id, $parent_id );
					$this->block_store->delete_path_code( $action_id );
				}
			}
			$this->block_store->delete( (int) $child['id'] );
		}
	}

	/**
	 * A container's delayed actions split into spans: neighbours in tree order that carry the same delay.
	 *
	 * Children are read live, ordered by menu_order then id as the tree orders them. Any child that is
	 * not an action with live async meta (an undelayed action, a block, a legacy loop) ends a span.
	 *
	 * @param int $parent_id The container.
	 *
	 * @return array[][] Spans in tree order, each a list of members from {@see self::member()}.
	 */
	private function spans( int $parent_id ): array {
		$children = get_posts(
			array(
				'post_type'      => array( WP_Action_Store::POST_TYPE, WP_Block_Store::POST_TYPE, AUTOMATOR_POST_TYPE_LOOP ),
				'post_parent'    => $parent_id,
				'post_status'    => array( Action_Status::DRAFT, Action_Status::PUBLISH ),
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
			)
		);

		$spans    = array();
		$previous = null;
		foreach ( $children as $child ) {
			$meta = WP_Action_Store::POST_TYPE === $child->post_type ? $this->read_async_meta( (int) $child->ID ) : null;
			if ( null === $meta ) {
				$previous = null;
				continue;
			}

			$member = $this->member( (int) $child->ID, (int) $child->menu_order, $this->convert_meta_to_fields( $meta ), null, $meta );
			if ( $previous === $member['signature'] ) {
				$spans[ count( $spans ) - 1 ][] = $member;
			} else {
				$spans[] = array( $member );
			}
			$previous = $member['signature'];
		}

		return $spans;
	}

	/**
	 * Wrap members in one delay_schedule block at their parent, configured and keyed by the first.
	 *
	 * A span's members share one delay; a mixed group's wrap passes a single member. The drain is the
	 * last write, so a wrap that stops part-way leaves its members' async_* live.
	 *
	 * @param int     $recipe_id Recipe post ID.
	 * @param int     $parent_id The members' parent.
	 * @param array[] $members   Members from {@see self::member()} in tree order, each with its live meta.
	 *
	 * @return void
	 * @throws \Exception When a block cannot be stored.
	 */
	private function wrap( int $recipe_id, int $parent_id, array $members ): void {
		$synthesis_key = self::wrap_key( $recipe_id, $members[0]['action_id'] );

		// A wrap interrupted after its create left this block empty; replace it rather than add a second.
		foreach ( $this->block_store->get_recipe_container_ids( $recipe_id ) as $existing_id ) {
			$existing = $this->block_store->get( $existing_id );
			if ( null !== $existing && $synthesis_key === $existing['synthesis_key'] && array() === $this->block_store->collect_descendant_action_ids( $existing_id ) ) {
				$this->block_store->delete( $existing_id );
			}
		}

		$block_id = $this->block_store->create(
			$parent_id,
			Block_Type::DELAY_SCHEDULE,
			$this->convert_meta_to_fields( $members[0]['meta'] ),
			$members[0]['menu_order'],
			$recipe_id,
			null,
			$synthesis_key
		);

		foreach ( $members as $member ) {
			$this->block_store->reparent_to_path( $member['action_id'], $block_id, Block_Path_Code::DELAY_COMPLETED );
		}

		foreach ( $members as $member ) {
			$this->drain_async_meta( $recipe_id, $member['action_id'] );
		}
	}

	/**
	 * The filter block whose gated actions this parent holds, or null when the parent is not a filter.
	 *
	 * A filter copy maps to its original, so a group interrupted mid-split is migrated as one.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $parent_id The actions' post_parent.
	 *
	 * @return array|null Filter block row.
	 */
	private function gating_filter( int $recipe_id, int $parent_id ): ?array {
		$block = $parent_id > 0 ? $this->block_store->get( $parent_id ) : null;
		if ( null === $block || Block_Type::FILTER !== $block['block_type'] ) {
			return null;
		}

		$copy_delay = $block['parent_id'] > 0 ? $this->block_store->get( $block['parent_id'] ) : null;
		$pattern    = sprintf( '/^recipe_%d:delay_schedule:filter:(\d+):copy:\d+$/', $recipe_id );
		if ( null !== $copy_delay && preg_match( $pattern, (string) $copy_delay['synthesis_key'], $matches ) ) {
			$original = $this->block_store->get( (int) $matches[1] );
			if ( null !== $original && Block_Type::FILTER === $original['block_type'] ) {
				return $original;
			}
		}

		return $block;
	}

	/**
	 * Migrate the delays of every action a filter gates.
	 *
	 * Identical delays hoist one delay above the filter; differing delays get one filter copy per
	 * delay; a group with any undelayed action is wrapped action by action, as 7.x evaluated it once at
	 * trigger time. The group is the whole condition group: when it gates actions in the recipe and in a
	 * loop, each container has its own filter, and an undelayed action in any of them makes every one of
	 * them mixed ({@see self::group_has_undelayed_action_elsewhere()}).
	 *
	 * @param int   $recipe_id Recipe post ID.
	 * @param array $filter    Filter block row.
	 *
	 * @return int[] Action IDs migrated.
	 * @throws \Exception When a block cannot be stored.
	 */
	private function migrate_gated( int $recipe_id, array $filter ): array {
		$filter  = $this->undo_partial_pass( $recipe_id, $filter );
		$members = $this->filter_members( (int) $filter['id'] );

		if ( in_array( '', array_column( $members, 'signature' ), true ) || $this->group_has_undelayed_action_elsewhere( $recipe_id, $filter ) ) {
			$wrapped = array();
			foreach ( $members as $member ) {
				if ( null !== $member['meta'] ) {
					$this->wrap( $recipe_id, (int) $filter['id'], array( $member ) );
					$wrapped[] = $member['action_id'];
				}
			}
			return $wrapped;
		}

		$groups = array();
		foreach ( $members as $member ) {
			$groups[ $member['signature'] ][] = $member;
		}

		$filter_id   = (int) $filter['id'];
		$delay_key   = self::filter_delay_key( $recipe_id, $filter_id );
		$filter_path = '' !== $filter['path_code'] ? $filter['path_code'] : null;
		$index       = 0;

		// Members are in menu_order, so groups are ordered by their lowest action; the first keeps the original filter.
		foreach ( $groups as $group ) {
			++$index;

			if ( 1 === $index ) {
				$delay_id = $this->block_store->create( (int) $filter['parent_id'], Block_Type::DELAY_SCHEDULE, $group[0]['fields'], (int) $filter['menu_order'], $recipe_id, null, $delay_key, $filter_path );
				$this->block_store->reparent_to_path( $filter_id, $delay_id, Block_Path_Code::DELAY_COMPLETED );
				$this->move_members( $group, $filter_id );
				continue;
			}

			$delay_id = $this->block_store->create( (int) $filter['parent_id'], Block_Type::DELAY_SCHEDULE, $group[0]['fields'], $group[0]['menu_order'], $recipe_id, null, $delay_key . ':copy:' . $index, $filter_path );
			$copy_id  = $this->copy_filter( $recipe_id, $filter, $delay_id, $index );
			$this->move_members( $group, $copy_id );
		}

		// Drain last: a filter whose actions still carry async_* is unfinished and is undone on the next pass.
		foreach ( $members as $member ) {
			$this->drain_async_meta( $recipe_id, $member['action_id'] );
		}

		return array_column( $members, 'action_id' );
	}

	/**
	 * Whether the filter's condition group gates an undelayed action through another of its filters.
	 *
	 * A group that gates actions in the recipe and in a loop gets a filter in each container, all with the
	 * group's `legacy_group_id`. 7.x decided the timing once for the whole group: when any action it listed
	 * had no delay, the conditions were checked at dispatch for every action, a delayed one in a loop
	 * included (Pro v7.7.0 `src/core/classes/actions-conditions.php:259`, the undelayed check at :292 and
	 * :401-414, read at :546). So one undelayed action in another of the group's filters makes this one
	 * mixed. A delay is read from the live `async_*` meta or from the drained `_legacy_async_*` copy, so a
	 * filter this pass has already finished still counts its delayed actions as delayed.
	 *
	 * @since 8.0.0
	 *
	 * @param int   $recipe_id Recipe post ID.
	 * @param array $filter    Filter block row, after this pass undid its own copies.
	 *
	 * @return bool
	 */
	private function group_has_undelayed_action_elsewhere( int $recipe_id, array $filter ): bool {
		$group_id = $filter['legacy_group_id'] ?? null;
		if ( null === $group_id || '' === $group_id ) {
			return false;
		}

		// Timing in 7.x used the group's entire list, including an id an earlier group claimed
		// or an action outside this recipe. Those ids affect timing without being moved.
		$raw = $this->legacy_meta_store->get_source( $recipe_id, Action_Conditions_Meta_Migrator::META_KEY_BACKUP );
		if ( '' === $raw || array() === $raw ) {
			$raw = $this->legacy_meta_store->get_source( $recipe_id, Action_Conditions_Meta_Migrator::META_KEY );
		}
		$groups = is_array( $raw ) ? $raw : json_decode( Json_String_Repair::repair( (string) $raw ), true );
		foreach ( is_array( $groups ) ? $groups : array() as $index => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$id = isset( $group['id'] ) && '' !== (string) $group['id'] ? (string) $group['id'] : 'group-' . $index;
			if ( (string) $group_id !== $id ) {
				continue;
			}
			foreach ( (array) ( $group['actions'] ?? array() ) as $action_id ) {
				if ( ! $this->is_delayed( absint( $action_id ) ) ) {
					return true;
				}
			}
		}

		foreach ( $this->block_store->get_recipe_container_ids( $recipe_id ) as $block_id ) {
			if ( (int) $filter['id'] === $block_id ) {
				continue;
			}

			$block = $this->block_store->get( $block_id );
			if ( null === $block || Block_Type::FILTER !== $block['block_type'] || $group_id !== $block['legacy_group_id'] ) {
				continue;
			}

			foreach ( $this->block_store->collect_descendant_action_ids( $block_id ) as $action_id ) {
				if ( ! $this->is_delayed( $action_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether an action carries a delay, live (`async_*`) or already drained (`_legacy_async_*`).
	 *
	 * @since 8.0.0
	 *
	 * @param int $action_id Action post ID.
	 *
	 * @return bool
	 */
	private function is_delayed( int $action_id ): bool {
		$meta = $this->read_async_meta( $action_id ) ?? $this->read_drained_async_meta( $action_id );

		return null !== $meta && array() !== $this->convert_meta_to_fields( $meta );
	}

	/**
	 * Undo what an interrupted pass left for this filter: copy delays and the hoisted delay.
	 *
	 * Actions under a copy move back under the filter; the filter moves back to the hoisted delay's
	 * parent and path. A hoisted delay the filter never moved into is deleted too. Every action under
	 * an undone block gets its drained meta back first, so members the stopped drain had already
	 * reached count again. Only blocks this migrator made for the filter are touched.
	 *
	 * @param int   $recipe_id Recipe post ID.
	 * @param array $filter    Filter block row.
	 *
	 * @return array The filter row as it now stands.
	 * @throws \Exception When a block cannot be moved or deleted.
	 */
	private function undo_partial_pass( int $recipe_id, array $filter ): array {
		$filter_id   = (int) $filter['id'];
		$hoisted_key = self::filter_delay_key( $recipe_id, $filter_id );

		foreach ( $this->block_store->get_recipe_container_ids( $recipe_id ) as $block_id ) {
			$block = $this->block_store->get( $block_id );
			if ( null === $block ) {
				continue;
			}
			$key      = (string) $block['synthesis_key'];
			$is_copy  = 0 === strpos( $key, $hoisted_key . ':copy:' );
			$is_stray = $hoisted_key === $key && (int) $filter['parent_id'] !== $block_id;
			if ( ! $is_copy && ! $is_stray ) {
				continue;
			}
			foreach ( $this->block_store->collect_descendant_action_ids( $block_id ) as $action_id ) {
				$this->restore_async_meta( $recipe_id, $action_id );
				$this->block_store->reparent_to_path( $action_id, $filter_id, Block_Path_Code::FILTER_CONDITIONS_MET );
			}
			$this->block_store->delete( $block_id );
		}

		$hoisted = $this->block_store->get( (int) $filter['parent_id'] );
		if ( null !== $hoisted && $hoisted_key === $hoisted['synthesis_key'] ) {
			foreach ( $this->block_store->collect_descendant_action_ids( (int) $hoisted['id'] ) as $action_id ) {
				$this->restore_async_meta( $recipe_id, $action_id );
			}
			if ( '' !== $hoisted['path_code'] ) {
				$this->block_store->reparent_to_path( $filter_id, (int) $hoisted['parent_id'], $hoisted['path_code'] );
			} else {
				$this->block_store->reparent( $filter_id, (int) $hoisted['parent_id'] );
				$this->block_store->delete_path_code( $filter_id );
			}
			$this->block_store->delete( (int) $hoisted['id'] );
		}

		return $this->block_store->get( $filter_id );
	}

	/**
	 * Every action a filter gates, directly or through a delay block directly under it, by menu_order.
	 *
	 * Each member carries the canonical delay fields and their signature; the signature is '' for an
	 * action with no delay and for any other child block, which makes the group mixed.
	 *
	 * @param int $filter_id Filter block ID.
	 *
	 * @return array[] Members: action_id, menu_order, fields, signature, inner_block_id, meta.
	 */
	private function filter_members( int $filter_id ): array {
		$members = array();

		foreach ( $this->block_store->get_children( $filter_id ) as $child ) {
			if ( 'block' === $child['entity_type'] ) {
				$is_delay = Block_Type::DELAY_SCHEDULE === $child['block_type'];
				$fields   = $is_delay ? $child['fields'] : array();
				$actions  = $is_delay ? $this->block_store->collect_inner_action_ids( (int) $child['id'] ) : array();
				if ( ! $is_delay ) {
					$members[] = $this->member( 0, (int) $child['menu_order'], array(), null, null );
				}
				foreach ( $actions as $action_id ) {
					$members[] = $this->member( $action_id, (int) get_post_field( 'menu_order', $action_id ), $fields, (int) $child['id'], null );
				}
				continue;
			}

			$action_id = (int) $child['ID'];
			$meta      = $this->read_async_meta( $action_id );
			$members[] = $this->member( $action_id, (int) $child['menu_order'], null === $meta ? array() : $this->convert_meta_to_fields( $meta ), null, $meta );
		}

		usort(
			$members,
			static function ( array $a, array $b ): int {
				return array( $a['menu_order'], $a['action_id'] ) <=> array( $b['menu_order'], $b['action_id'] );
			}
		);

		return $members;
	}

	/**
	 * One filter member row.
	 *
	 * @param int        $action_id      Action post ID (0 for a non-delay child block).
	 * @param int        $menu_order     Action menu_order.
	 * @param array      $fields         Canonical delay fields; empty when undelayed.
	 * @param int|null   $inner_block_id Delay block the action already sits in, if any.
	 * @param array|null $meta           The action's live async meta, if any.
	 *
	 * @return array
	 */
	private function member( int $action_id, int $menu_order, array $fields, ?int $inner_block_id, ?array $meta ): array {
		ksort( $fields );

		return array(
			'action_id'      => $action_id,
			'menu_order'     => $menu_order,
			'fields'         => $fields,
			'signature'      => empty( $fields ) ? '' : (string) wp_json_encode( $fields ),
			'inner_block_id' => $inner_block_id,
			'meta'           => $meta,
		);
	}

	/**
	 * Create a copy of a filter under a delay block, with the same fields, group id and condition rows.
	 *
	 * @param int   $recipe_id Recipe post ID.
	 * @param array $filter    Original filter block row.
	 * @param int   $delay_id  Delay block the copy goes under.
	 * @param int   $index     Copy number (2 for the first copy).
	 *
	 * @return int The copy's block ID.
	 * @throws \Exception When a block or row cannot be stored.
	 */
	private function copy_filter( int $recipe_id, array $filter, int $delay_id, int $index ): int {
		$suffix  = ':copy:' . $index;
		$copy_id = $this->block_store->create(
			$delay_id,
			Block_Type::FILTER,
			$filter['fields'],
			0,
			$recipe_id,
			$filter['legacy_group_id'],
			( $filter['synthesis_key'] ?? '' ) . $suffix,
			Block_Path_Code::DELAY_COMPLETED
		);

		foreach ( $this->modifier_store->get_by_block( (int) $filter['id'], 'filter_condition' ) as $row ) {
			$this->modifier_store->create(
				$recipe_id,
				$copy_id,
				'filter_condition',
				$row['integration_code'],
				$row['code'],
				$row['fields'],
				$row['backup'],
				(int) $row['menu_order'],
				$row['legacy_id'],
				( $row['synthesis_key'] ?? '' ) . $suffix
			);
		}

		return $copy_id;
	}

	/**
	 * Move members onto a filter's conditions-met path, deleting each inner delay block once emptied.
	 *
	 * @param array[] $members   Members from {@see self::filter_members()}.
	 * @param int     $filter_id Target filter block ID.
	 *
	 * @return void
	 * @throws \Exception When an emptied inner block cannot be deleted.
	 */
	private function move_members( array $members, int $filter_id ): void {
		$inner_blocks = array();

		foreach ( $members as $member ) {
			$this->block_store->reparent_to_path( $member['action_id'], $filter_id, Block_Path_Code::FILTER_CONDITIONS_MET );
			if ( null !== $member['inner_block_id'] ) {
				$inner_blocks[ $member['inner_block_id'] ] = true;
			}
		}

		foreach ( array_keys( $inner_blocks ) as $inner_block_id ) {
			if ( array() === $this->block_store->collect_descendant_action_ids( $inner_block_id ) ) {
				$this->block_store->delete( $inner_block_id );
			}
		}
	}

	/**
	 * An action's live async meta, or null when it carries none.
	 *
	 * @param int $action_id Action post ID.
	 *
	 * @return array|null
	 */
	private function read_async_meta( int $action_id ): ?array {
		$mode = get_post_meta( $action_id, 'async_mode', true );
		if ( '' === (string) $mode ) {
			return null;
		}

		$meta = array( 'async_mode' => $mode );
		foreach ( self::async_meta_keys() as $key ) {
			$value = get_post_meta( $action_id, $key, true );
			if ( 'async_mode' !== $key && '' !== $value ) {
				$meta[ $key ] = $value;
			}
		}

		return $meta;
	}

	/**
	 * An action's drained async meta under its `async_*` names, or null when it carries none.
	 *
	 * @since 8.0.0
	 *
	 * @param int $action_id Action post ID.
	 *
	 * @return array|null
	 */
	private function read_drained_async_meta( int $action_id ): ?array {
		$mode = get_post_meta( $action_id, '_legacy_async_mode', true );
		if ( '' === (string) $mode ) {
			return null;
		}

		$meta = array( 'async_mode' => $mode );
		foreach ( self::async_meta_keys() as $key ) {
			$value = get_post_meta( $action_id, '_legacy_' . $key, true );
			if ( 'async_mode' !== $key && '' !== $value ) {
				$meta[ $key ] = $value;
			}
		}

		return $meta;
	}

	/**
	 * Rename the action's `async_*` meta to `_legacy_async_*` so no legacy reader or listener fires on it.
	 *
	 * @param int $recipe_id Recipe the action belongs to.
	 * @param int $action_id Action post id.
	 *
	 * @return void
	 */
	private function drain_async_meta( int $recipe_id, int $action_id ): void {
		// async_mode goes last: until it does, the find still sees the action as unfinished.
		$keys  = array_merge( array_diff( self::async_meta_keys(), array( 'async_mode' ) ), array( 'async_mode' ) );
		$moved = false;
		foreach ( $keys as $key ) {
			$value = get_post_meta( $action_id, $key, true );
			if ( '' === (string) $value ) {
				continue;
			}
			update_post_meta( $action_id, '_legacy_' . $key, $value );
			delete_post_meta( $action_id, $key );
			$moved = true;
		}

		// Meta-only writes: no schema-lifecycle hook hears them.
		if ( $moved ) {
			Recipe_Cache::invalidate( $recipe_id );
		}
	}

	/**
	 * Put an action's drained `_legacy_async_*` meta back under its `async_*` names — the inverse of the drain.
	 *
	 * Conditions teardown calls this for every action it frees from a block it deletes, so this pass
	 * rebuilds their delays. The find reads `async_*` only; nothing else turns drained meta back into a delay.
	 *
	 * @param int $recipe_id Recipe the action belongs to.
	 * @param int $action_id Action post id.
	 *
	 * @return void
	 */
	public function restore_async_meta( int $recipe_id, int $action_id ): void {
		$moved = false;
		foreach ( self::async_meta_keys() as $key ) {
			$value = get_post_meta( $action_id, '_legacy_' . $key, true );
			if ( '' === (string) $value ) {
				continue;
			}
			update_post_meta( $action_id, $key, $value );
			delete_post_meta( $action_id, '_legacy_' . $key );
			$moved = true;
		}

		// Meta-only writes: no schema-lifecycle hook hears them.
		if ( $moved ) {
			Recipe_Cache::invalidate( $recipe_id );
		}
	}

	/**
	 * Convert flat async meta to block fields format.
	 *
	 * Translates the action's wire-format `async_*` post-meta into the
	 * canonical block field names (`DELAY_MODE`, `DELAY_VALUE`, …,
	 * `DELAY_TOKEN`) defined by `Delay_Block`. Routes through the wire
	 * mapper for the wire ↔ block-vocab translation (Rule 17).
	 *
	 * @param array $meta Flat async meta from the action.
	 *
	 * @return array Block fields keyed by canonical option codes.
	 */
	private function convert_meta_to_fields( array $meta ): array {
		$config = ( new Delay_Schedule_Wire_Mapper() )->meta_to_config( $meta );
		$fields = array();

		if ( empty( $config['mode'] ) ) {
			return $fields;
		}

		$fields['DELAY_MODE'] = $config['mode'];

		if ( isset( $config['delay_value'] ) ) {
			$fields['DELAY_VALUE'] = $config['delay_value'];
		}
		if ( isset( $config['delay_unit'] ) ) {
			$fields['DELAY_UNIT'] = $config['delay_unit'];
		}
		if ( isset( $config['schedule_date'] ) ) {
			$fields['SCHEDULE_DATE'] = $config['schedule_date'];
		}
		if ( isset( $config['schedule_time'] ) ) {
			$fields['SCHEDULE_TIME'] = $config['schedule_time'];
		}
		if ( isset( $config['delay_token'] ) ) {
			$fields['DELAY_TOKEN'] = $config['delay_token'];
		}

		return $fields;
	}

	/**
	 * Whether the action has any `recipe_id` row, matched as the table's
	 * collation matches keys, so a field coded RECIPE_ID counts.
	 *
	 * @since 8.0.0
	 *
	 * @param int $action_id Action post ID.
	 *
	 * @return bool
	 */
	private function has_recipe_id_row( int $action_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; matches meta keys by the table collation (a RECIPE_ID key counts), which the exact-key meta API does not.
		return 0 < (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'recipe_id'", $action_id ) );
	}
}
