<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Migration;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Reorder_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Recipe_Builder\Action\Enums\Action_Status;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Filter_Block;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\Json_String_Repair;

/**
 * Converts a recipe's legacy condition JSON into filter blocks and modifiers on load.
 *
 * Converts the legacy `actions_conditions` recipe-meta JSON into `uo-block`
 * (filter container) and `uo-block-modifier` (filter_condition leaf) posts
 * on-the-fly when a recipe is loaded.
 *
 * For each condition group in the legacy JSON:
 *  1. Creates a filter block (`uo-block`, `block_type='filter'`) in each
 *     container (the recipe, or one of its loops) that holds an action the
 *     group gates, so every action is gated where 7.x ran it.
 *  2. Creates one `uo-block-modifier` per condition, stamping `recipe_id`,
 *     `legacy_id`, `backup`, and `synthesis_key` natively via the Store.
 *  3. Reparents grouped actions into the block's `FILTER-CONDITIONS-MET` path.
 *  4. Moves the source `actions_conditions` JSON to `_legacy_actions_conditions`
 *     post-meta (non-destructive — recoverable for one release).
 *
 * Actions not assigned to any condition group remain at the recipe level.
 *
 * Storage paradigm: post + post-meta, owned by {@see Block_Store} and
 * {@see Block_Modifier_Store}. Migrator never touches `wp_postmeta` directly
 * (CLAUDE.md Rule 3) — every mutation flows through the Stores.
 */
class Action_Conditions_Meta_Migrator {

	/**
	 * Meta key for actions_conditions.
	 *
	 * @var string
	 */
	const META_KEY = 'actions_conditions';

	/**
	 * Backup meta key — receives the original `actions_conditions` JSON
	 * after a successful migration so the source data is recoverable
	 * (round-trip / rollback) instead of being deleted outright.
	 *
	 * @var string
	 */
	const META_KEY_BACKUP = '_legacy_actions_conditions';

	/**
	 * Block database store.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Block modifier database store.
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Bridge for the legacy `actions_conditions` source-meta read/move/clear.
	 *
	 * @var Recipe_Legacy_Meta_Store
	 */
	private Recipe_Legacy_Meta_Store $legacy_meta_store;

	/**
	 * Delay migrator — restores the drained delay meta of the actions teardown frees.
	 *
	 * @var Delay_Schedule_Meta_Migrator
	 */
	private Delay_Schedule_Meta_Migrator $delay_migrator;

	/**
	 * Writes the menu_order of a container's children.
	 *
	 * @var Recipe_Reorder_Store
	 */
	private Recipe_Reorder_Store $reorder_store;

	/**
	 * Constructor.
	 *
	 * @param Block_Store                       $block_store       Block database store.
	 * @param Block_Modifier_Store              $modifier_store    Block-modifier database store.
	 * @param Recipe_Legacy_Meta_Store|null     $legacy_meta_store Store for source/backup post-meta moves; defaults to the WP post-meta impl.
	 * @param Delay_Schedule_Meta_Migrator|null $delay_migrator    Delay migrator for teardown's restore; defaults to one on the same stores.
	 * @param Recipe_Reorder_Store|null         $reorder_store     Store for a child's menu_order; defaults to the WordPress one.
	 */
	public function __construct(
		Block_Store $block_store,
		Block_Modifier_Store $modifier_store,
		?Recipe_Legacy_Meta_Store $legacy_meta_store = null,
		?Delay_Schedule_Meta_Migrator $delay_migrator = null,
		?Recipe_Reorder_Store $reorder_store = null
	) {
		$this->block_store       = $block_store;
		$this->modifier_store    = $modifier_store;
		$this->legacy_meta_store = $legacy_meta_store ?? new WP_Recipe_Legacy_Meta_Store();
		$this->delay_migrator    = $delay_migrator ?? new Delay_Schedule_Meta_Migrator( $block_store, $modifier_store );
		$this->reorder_store     = $reorder_store ?? Database::get_recipe_reorder_store();
	}

	/**
	 * Migrate actions_conditions meta if present.
	 *
	 * Standalone entry point that reads the meta from the database.
	 * Prefer {@see self::maybe_migrate_from_meta()} when the recipe meta is
	 * already loaded.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return int[] Action IDs that were moved into blocks. Empty if no migration.
	 */
	public function maybe_migrate( int $recipe_id ): array {
		$raw = $this->legacy_meta_store->get_source( $recipe_id, self::META_KEY );

		return $this->do_migrate( $recipe_id, $raw );
	}

	/**
	 * Migrate actions_conditions from pre-loaded recipe meta.
	 *
	 * Optimised entry point for callers that already have the recipe meta
	 * loaded (e.g. the Block_Resolver via the Structure object), avoiding a
	 * redundant get_post_meta call.
	 *
	 * @param int   $recipe_id   Recipe post ID.
	 * @param array $recipe_meta All recipe meta (keyed by meta_key).
	 *
	 * @return int[] Action IDs that were moved into blocks. Empty if no migration.
	 */
	public function maybe_migrate_from_meta( int $recipe_id, array $recipe_meta ): array {
		$raw = $recipe_meta[ self::META_KEY ] ?? '';

		return $this->do_migrate( $recipe_id, $raw );
	}

	/**
	 * Project filter blocks under a recipe back to the legacy
	 * `actions_conditions` JSON wire shape.
	 *
	 * uo-block + uo-block-modifier are the canonical source of truth after
	 * migration. The bundled recipe-builder JS (`parseConditionsInMeta`)
	 * still reads `recipe.actions_conditions` off the legacy `recipes_object`
	 * to render filter groups — without this projection it sees an empty
	 * meta (the migrator moved the original JSON to
	 * `_legacy_actions_conditions`) and renders nothing, making the user's
	 * filter blocks "vanish" from the UI.
	 *
	 * Inverse of {@see self::do_migrate} — walks the same data the migrator
	 * created and emits the same shape it consumed. Every filter of the recipe
	 * is projected, at any depth (as teardown finds them): a filter hoisted
	 * under a delay, a loop-scoped one, and each filter copy as its own group
	 * carrying the shared legacy group id. A delay between a filter and its
	 * recipe or loop is not a legacy parent, so `parent_id` and `priority`
	 * come from the block that sits directly in that container.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return array Array of condition groups in legacy wire shape, ready
	 *               for `wp_json_encode()` and injection into recipes_object.
	 */
	public function project_to_legacy_meta( int $recipe_id ): array {
		if ( $recipe_id < 1 ) {
			return array();
		}

		$groups = array();
		$order  = array();

		foreach ( $this->block_store->get_recipe_container_ids( $recipe_id ) as $container_id ) {
			$block = $this->block_store->get( $container_id );
			if ( null === $block || Block_Type::FILTER !== $block['block_type'] ) {
				continue;
			}

			$block_id = (int) ( $block['id'] ?? 0 );
			if ( $block_id <= 0 ) {
				continue;
			}

			$action_ids = $this->gated_action_ids( $block_id );

			$conditions = array();
			foreach ( $this->modifier_store->get_by_block( $block_id, 'filter_condition' ) as $row ) {
				$conditions[] = array(
					'id'          => (string) ( $row['legacy_id'] ?? '' ),
					'integration' => (string) ( $row['integration_code'] ?? '' ),
					'condition'   => (string) ( $row['code'] ?? '' ),
					'fields'      => Fields_Bag::to_legacy_flat( is_array( $row['fields'] ?? null ) ? $row['fields'] : array() ),
					'backup'      => self::project_modifier_backup_to_legacy( is_array( $row['backup'] ?? null ) ? $row['backup'] : array() ),
				);
			}

			$fields = $block['fields'] ?? array();
			if ( $fields instanceof \stdClass ) {
				$fields = array();
			}
			$mode_raw = is_array( $fields ) ? ( $fields['CONDITION_MATCH_MODE'] ?? 'all' ) : 'all';
			$mode     = is_array( $mode_raw ) ? (string) ( $mode_raw['value'] ?? 'all' ) : (string) $mode_raw;
			$mode     = in_array( strtolower( $mode ), array( 'any', 'all' ), true ) ? strtolower( $mode ) : 'all';

			// Climb out of any delay wrapping the filter: the legacy shape knows only recipe and loop parents.
			$anchor = $block;
			$parent = $block['parent_id'] > 0 ? $this->block_store->get( $block['parent_id'] ) : null;
			while ( null !== $parent && Block_Type::DELAY_SCHEDULE === $parent['block_type'] ) {
				$anchor = $parent;
				$parent = $parent['parent_id'] > 0 ? $this->block_store->get( $parent['parent_id'] ) : null;
			}
			$parent_id = (int) $anchor['parent_id'];
			if ( $parent_id <= 0 ) {
				$parent_id = $recipe_id;
			}

			$order[]  = array( (int) $anchor['menu_order'], $block_id );
			$groups[] = array(
				'id'         => (string) ( $block['legacy_group_id'] ?? '' ),
				'priority'   => (int) $anchor['menu_order'],
				'actions'    => $action_ids,
				'mode'       => $mode,
				'parent_id'  => $parent_id,
				'conditions' => $conditions,
			);
		}

		// By priority, then block id: usort is not stable on PHP 7.4.
		array_multisort( $order, SORT_ASC, $groups );

		return $groups;
	}

	/**
	 * The actions a filter gates, in tree order: its own action children, and the actions inside any
	 * delay under it at any depth, as a mixed group's per-action delay holds one. A nested filter or
	 * loop gates its own actions and is not entered.
	 *
	 * @since 8.0.0
	 *
	 * @param int $block_id Filter or delay block ID.
	 *
	 * @return int[]
	 */
	private function gated_action_ids( int $block_id ): array {
		$action_ids = array();

		foreach ( $this->block_store->get_children( $block_id ) as $child ) {
			if ( 'block' === ( $child['entity_type'] ?? '' ) ) {
				if ( Block_Type::DELAY_SCHEDULE === ( $child['block_type'] ?? '' ) ) {
					$action_ids = array_merge( $action_ids, $this->gated_action_ids( (int) ( $child['id'] ?? 0 ) ) );
				}
				continue;
			}

			$cid = (int) ( $child['ID'] ?? 0 );
			if ( $cid > 0 ) {
				$action_ids[] = $cid;
			}
		}

		return $action_ids;
	}

	/**
	 * Project the canonical modifier `backup` payload back to the legacy
	 * `{integrationName, nameDynamic, titleHTML}` shape used inside
	 * `actions_conditions[i].conditions[j].backup` — the bundled UI keys
	 * off these specific names to render the condition chip.
	 *
	 * Tolerant of either source shape so older imports round-trip too.
	 *
	 * @param array $backup Modifier-store backup payload.
	 *
	 * @return array
	 */
	private static function project_modifier_backup_to_legacy( array $backup ): array {
		return array(
			'nameDynamic'     => (string) ( $backup['nameDynamic'] ?? $backup['sentence'] ?? '' ),
			'titleHTML'       => (string) ( $backup['titleHTML'] ?? $backup['sentence_html'] ?? '' ),
			'integrationName' => (string) ( $backup['integrationName'] ?? $backup['integration_name'] ?? '' ),
		);
	}

	/**
	 * Free every action from the filter blocks a previous pass made, then delete those blocks.
	 *
	 * A migrated filter (one with a `legacy_group_id`) is found at any depth. When its parent is a
	 * delay the delay pass made for a filter (hoisted or a copy's), that delay is the block deleted;
	 * otherwise the filter is. Every action under the deleted block gets its drained delay meta
	 * restored and moves back to the container that block sat in (the recipe or one of its loops, the
	 * recipe root when it sat anywhere else), so this pass and the delay pass rebuild both from source
	 * and each action is gated where it was again.
	 * `Block_Store::delete()` cascades blocks and modifiers, never actions — hence the explicit free.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return void
	 * @throws \RuntimeException When an action cannot be freed; the block that holds it is kept.
	 */
	private function teardown_existing_filter_blocks( int $recipe_id ): void {
		foreach ( $this->block_store->get_recipe_container_ids( $recipe_id ) as $block_id ) {
			$filter = $this->block_store->get( $block_id );
			if ( null === $filter || Block_Type::FILTER !== $filter['block_type'] || null === $filter['legacy_group_id'] ) {
				continue;
			}

			$parent  = $filter['parent_id'] > 0 ? $this->block_store->get( $filter['parent_id'] ) : null;
			$deleted = null !== $parent
				&& Block_Type::DELAY_SCHEDULE === $parent['block_type']
				&& Delay_Schedule_Meta_Migrator::is_filter_delay_key( (string) $parent['synthesis_key'], $recipe_id )
				? $parent
				: $filter;

			$delete_id = (int) $deleted['id'];
			$free_to   = $this->container_id( $recipe_id, (int) $deleted['parent_id'] ) ?? $recipe_id;

			foreach ( $this->block_store->collect_descendant_action_ids( $delete_id ) as $action_id ) {
				$this->delay_migrator->restore_async_meta( $recipe_id, $action_id );
				try {
					$this->block_store->reparent( $action_id, $free_to );
				} catch ( \Exception $e ) {
					// Deleting the block now would strand the action it still holds; fail the pass so a retry converges.
					throw new \RuntimeException( esc_html( sprintf( 'Filter block teardown could not free action %d: %s', $action_id, $e->getMessage() ) ) );
				}
				$this->block_store->delete_path_code( $action_id );
			}

			try {
				$this->block_store->delete( $delete_id );
			} catch ( \Throwable $e ) {
				automator_log( 'Filter block teardown failed for recipe ' . $recipe_id . ': ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Parse raw meta and run migration if condition groups exist.
	 *
	 * Teardown-first: a resident source means the recipe has not completed
	 * migration, so any filter blocks on disk are leftovers of a run that
	 * died mid-flight — rebuild from scratch instead of duplicating them.
	 * Crash-only semantics: every re-entry converges regardless of how the
	 * previous attempt ended.
	 *
	 * @param int   $recipe_id Recipe post ID.
	 * @param mixed $raw       Raw actions_conditions meta value.
	 *
	 * @return int[] Action IDs that were moved into blocks.
	 */
	private function do_migrate( int $recipe_id, $raw ): array {
		$condition_groups = $this->parse_condition_groups( $raw );

		if ( empty( $condition_groups ) ) {
			return array();
		}

		$this->teardown_existing_filter_blocks( $recipe_id );

		return $this->migrate( $recipe_id, $condition_groups );
	}

	/**
	 * Parse raw actions_conditions meta into condition groups.
	 *
	 * Rows saved before quotes were slashed for storage are corrupt at rest; every 7.x read path repaired
	 * them, so the migration does too. A non-empty source that still does not parse is a failed pass, not
	 * "no conditions": the wrapper leaves the vintage alone and the recipe fails closed until the meta is fixed.
	 *
	 * @param mixed $raw Raw meta value (string JSON, array, or empty).
	 *
	 * @return array Parsed condition groups, or empty array.
	 * @throws \RuntimeException When a non-empty string source is not JSON even after repair.
	 */
	private function parse_condition_groups( $raw ): array {
		if ( empty( $raw ) ) {
			return array();
		}
		if ( ! is_string( $raw ) ) {
			return is_array( $raw ) ? $raw : array();
		}

		$data = json_decode( Json_String_Repair::looks_like_structured_json( $raw ) ? Json_String_Repair::repair( $raw ) : $raw, true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( 'actions_conditions is not valid JSON and could not be repaired: ' . esc_html( json_last_error_msg() ) );
		}

		return $data;
	}

	/**
	 * Perform the migration for all condition groups, gating each action as 7.x did.
	 *
	 * 7.x read the groups at run time (Pro v7.7.0 `src/core/classes/actions-conditions.php:509-543`):
	 * for each action it took the first group, in stored order, whose parent is this recipe or one of
	 * its loops and that lists the action (:524-535, parent check :590-605); a group with any other
	 * parent was passed over. When that group had no conditions or no `mode`, the check threw and the
	 * action ran ungated (:537-543, caught at :136-140). So a group with no recipe or loop parent, no
	 * conditions or no mode makes no filter, and an action is moved only into the filter of the first
	 * group that lists it. The group's parent was never checked against the action's, so an action
	 * stayed where it was, in the recipe or in its loop; the group gets a filter in each of those
	 * containers ({@see self::placements()}). 7.x only ever looked at the running recipe's actions, so a
	 * listed id this recipe does not hold (an action stored in another recipe, a deleted one, a post that
	 * is not an action) gated nothing here and is left where it is ({@see self::held_actions()}).
	 *
	 * @param int   $recipe_id        Recipe post ID.
	 * @param array $condition_groups Parsed condition groups.
	 *
	 * @return int[] Action IDs that were moved into blocks.
	 */
	private function migrate( int $recipe_id, array $condition_groups ): array {
		$migrated_action_ids = array();
		$failed              = array();
		$claimed             = array();

		foreach ( $condition_groups as $index => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$parent_id = $this->container_id( $recipe_id, absint( $group['parent_id'] ?? 0 ) );
			if ( null === $parent_id ) {
				continue;
			}

			// Only the actions this recipe holds: a listed id stored anywhere else never ran with this recipe.
			// An action an earlier group listed was gated, or left ungated, by that group.
			$held       = $this->held_actions( $recipe_id, $this->extract_action_ids( $group ) );
			$listed     = array_keys( $held );
			$action_ids = array_values( array_diff( $listed, array_keys( $claimed ) ) );
			$claimed   += array_fill_keys( $listed, true );

			$conditions = is_array( $group['conditions'] ?? null )
				? $group['conditions']
				: array();

			if ( empty( $conditions ) || ! isset( $group['mode'] ) ) {
				continue;
			}

			$mode = $this->parse_mode( $group );

			// A group without an id gets a stable one (its position in the source), so teardown can find its filter on a retry.
			$legacy_group_id = isset( $group['id'] ) && '' !== (string) $group['id'] ? (string) $group['id'] : 'group-' . $index;

			try {
				// 7.x stopped the run on a condition that is not an array: nothing in the recipe ran, so nothing here may run either.
				foreach ( $conditions as $condition ) {
					if ( ! is_array( $condition ) ) {
						throw new \RuntimeException( esc_html( sprintf( 'Group %s holds a condition that is not an array.', $legacy_group_id ) ) );
					}
				}

				foreach ( $this->placements( $parent_id, array_intersect_key( $held, array_flip( $action_ids ) ) ) as $container_id => $placed ) {
					// The filter in the group's own container keeps the group's key; one in another container names it.
					$key_suffix = $parent_id === $container_id ? '' : ':in:' . $container_id;

					$this->separate_tied_children( $container_id );

					$block_id = $this->block_store->create(
						$container_id,
						Block_Type::FILTER,
						array( Filter_Block::FIELD_MATCH_MODE => $mode ),
						$this->calculate_block_menu_order( array() !== $placed ? $placed : $listed ),
						$recipe_id,
						$legacy_group_id,
						sprintf( 'recipe_%d:filter:%s', $recipe_id, $legacy_group_id ) . $key_suffix
					);

					$this->create_filter_conditions( $recipe_id, $block_id, $legacy_group_id, $conditions, $key_suffix );

					foreach ( $placed as $action_id ) {
						$this->block_store->reparent_to_path( $action_id, $block_id, Block_Path_Code::FILTER_CONDITIONS_MET );
					}

					$migrated_action_ids = array_merge( $migrated_action_ids, $placed );
				}
			} catch ( \Exception $e ) {
				automator_log( 'Action conditions meta migration failed for recipe ' . absint( $recipe_id ) . ': ' . $e->getMessage() );
				$failed[] = '' !== $legacy_group_id ? $legacy_group_id : 'group ' . ( count( $failed ) + 1 );
			}
		}

		// A group that could not be stored keeps the source resident and the vintage unstamped; the next pass tears down and rebuilds.
		if ( ! empty( $failed ) ) {
			throw new \RuntimeException( esc_html( sprintf( 'actions_conditions group(s) %s could not be stored.', implode( ', ', $failed ) ) ) );
		}

		$this->legacy_meta_store->move_source_to_backup( $recipe_id, self::META_KEY, self::META_KEY_BACKUP );

		return $migrated_action_ids;
	}

	/**
	 * The given post when it is this recipe or one of its loops; null for anything else.
	 *
	 * These are the only containers 7.x had for actions and condition groups. A group whose
	 * `parent_id` is neither was passed over (Pro v7.7.0 `src/core/classes/actions-conditions.php:590-605`).
	 * A loop may already be folded into a loop block when this pass runs.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @param int $post_id   Post ID to check.
	 *
	 * @return int|null
	 */
	private function container_id( int $recipe_id, int $post_id ): ?int {
		if ( $recipe_id === $post_id ) {
			return $recipe_id;
		}

		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof \WP_Post || $recipe_id !== (int) $post->post_parent ) {
			return null;
		}

		if ( AUTOMATOR_POST_TYPE_LOOP === $post->post_type ) {
			return $post_id;
		}

		$block = $this->block_store->get( $post_id );

		return null !== $block && Block_Type::LOOP === $block['block_type'] ? $post_id : null;
	}

	/**
	 * The listed actions this recipe holds, each with its container: the recipe or the loop it sits in.
	 *
	 * 7.x checked a group's list against the actions of the recipe that was running (Pro v7.7.0
	 * `src/core/classes/actions-conditions.php:524-535`), so a listed id that is not one of this recipe's
	 * actions, in the recipe or in one of its loops, never gated anything in it. That covers an action
	 * stored in another recipe (that recipe runs it under its own groups), a deleted action, and a post that
	 * is not an action. Such an id is left out, so this pass never moves it.
	 *
	 * @since 8.0.0
	 *
	 * @param int   $recipe_id  Recipe post ID.
	 * @param int[] $action_ids Ids the group lists, in source order.
	 *
	 * @return array<int, int> Action ID => its container ID, in source order.
	 */
	private function held_actions( int $recipe_id, array $action_ids ): array {
		$held = array();

		foreach ( $action_ids as $action_id ) {
			if ( AUTOMATOR_POST_TYPE_ACTION !== get_post_type( $action_id ) ) {
				continue;
			}

			$container = $this->container_id( $recipe_id, (int) get_post_field( 'post_parent', $action_id ) );
			if ( null !== $container ) {
				$held[ $action_id ] = $container;
			}
		}

		return $held;
	}

	/**
	 * Where a group's filters go: one in each container that holds an action the group gates, each
	 * holding that container's actions, the group's own container first.
	 *
	 * 7.x gated an action where it was: a recipe-level group listing an action inside a loop gated it
	 * there, once per iteration, and a loop-level group listing an action outside the loop gated it
	 * once, outside (Pro v7.7.0 `src/core/classes/actions-conditions.php:524-535`, :552, :559-563). So
	 * each action keeps its container. A group that gates no action keeps one empty filter in its own
	 * container, so its conditions stay visible.
	 *
	 * @since 8.0.0
	 *
	 * @param int             $group_container The group's own container: the recipe or one of its loops.
	 * @param array<int, int> $containers      Each action the group gates => its container, in source order ({@see self::held_actions()}).
	 *
	 * @return array<int, int[]> Container ID => the actions its filter holds.
	 */
	private function placements( int $group_container, array $containers ): array {
		$placements = array( $group_container => array() );

		foreach ( $containers as $action_id => $container ) {
			$placements[ $container ][] = $action_id;
		}

		if ( array() === $placements[ $group_container ] && 1 < count( $placements ) ) {
			unset( $placements[ $group_container ] );
		}

		return $placements;
	}

	/**
	 * Extract valid action IDs from a condition group.
	 *
	 * @param array $group Condition group data.
	 *
	 * @return int[] Positive action IDs.
	 */
	private function extract_action_ids( array $group ): array {
		$raw_ids = is_array( $group['actions'] ?? null )
			? $group['actions']
			: array();

		return array_values(
			array_filter(
				array_map( 'intval', $raw_ids ),
				function ( $id ) {
					return $id > 0;
				}
			)
		);
	}

	/**
	 * Parse the condition match mode from a group that has one, as 7.x read it.
	 *
	 * 7.x compared the stored mode with `'any' === $mode` and applied "all" to every other value
	 * (Pro v7.7.0 `src/core/classes/actions-conditions.php:560`, the evaluation at `:634`), so only the
	 * exact word `any` gates as "any"; `Any`, `ANY`, an empty value or any other word gates as "all".
	 *
	 * @param array $group Condition group data.
	 *
	 * @return string 'any' or 'all'.
	 */
	private function parse_mode( array $group ): string {
		return Filter_Block::MATCH_MODE_ANY === ( $group['mode'] ?? null )
			? Filter_Block::MATCH_MODE_ANY
			: Filter_Block::MATCH_MODE_ALL;
	}

	/**
	 * Give each child of a container its own menu_order, keeping the order the children run in.
	 *
	 * A duplicated 7.x action keeps its source's menu_order, and tied actions ran by id. A filter takes
	 * its first action's menu_order with a newer id than any action, so without this it would sort
	 * after every sibling tied with that action, and run after one 7.x ran later.
	 *
	 * A child moves only as far as it must. The writes run last child first because each one only raises
	 * a menu_order, so a pass that stops part-way leaves the children in the same order for the retry.
	 *
	 * @since 8.0.1
	 *
	 * @param int $container_id The recipe or loop about to hold a filter.
	 *
	 * @return void
	 */
	private function separate_tied_children( int $container_id ): void {
		$children = get_posts(
			array(
				'post_type'      => array( WP_Action_Store::POST_TYPE, WP_Block_Store::POST_TYPE, AUTOMATOR_POST_TYPE_LOOP ),
				'post_parent'    => $container_id,
				'post_status'    => array( Action_Status::DRAFT, Action_Status::PUBLISH ),
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
			)
		);

		$writes = array();
		$lowest = null;

		foreach ( $children as $child ) {
			$menu_order = null === $lowest ? (int) $child->menu_order : max( (int) $child->menu_order, $lowest );
			$lowest     = $menu_order + 1;

			if ( (int) $child->menu_order !== $menu_order ) {
				$writes[ (int) $child->ID ] = $menu_order;
			}
		}

		foreach ( array_reverse( $writes, true ) as $child_id => $menu_order ) {
			$this->reorder_store->set_menu_order( $child_id, $menu_order );
		}
	}

	/**
	 * Calculate the menu_order for a filter block.
	 *
	 * @param int[] $action_ids Action post IDs.
	 *
	 * @return int Menu order for the block.
	 */
	private function calculate_block_menu_order( array $action_ids ): int {
		if ( empty( $action_ids ) ) {
			return 0;
		}

		// Collect each action's current menu_order.
		$orders = array_map(
			function ( $action_id ) {
				return (int) get_post_field( 'menu_order', $action_id );
			},
			$action_ids
		);

		// Position the block where the first action was so ordering is preserved.
		return min( $orders );
	}

	/**
	 * Create modifier rows under the freshly-created filter block.
	 *
	 * Stamps `recipe_id`, `legacy_id`, `backup`, and `synthesis_key`
	 * natively via {@see Block_Modifier_Store::create()} — no direct
	 * post-meta writes (CLAUDE.md Rule 3).
	 *
	 * @param int    $recipe_id       Recipe post id.
	 * @param int    $block_id        Parent filter block ID.
	 * @param string $legacy_group_id Legacy `actions_conditions[i].id` (anchor for synthesis_key).
	 * @param array  $conditions      Conditions array from the group.
	 * @param string $key_suffix      Appended to each synthesis key: the filter's own suffix, '' for the group's own container.
	 *
	 * @return void
	 */
	private function create_filter_conditions( int $recipe_id, int $block_id, string $legacy_group_id, array $conditions, string $key_suffix ): void {
		$menu_order = 0;

		foreach ( $conditions as $condition ) {
			$integration    = (string) ( $condition['integration'] ?? '' );
			$condition_code = (string) ( $condition['condition'] ?? '' );

			// Nothing can answer a condition with no codes. 7.x counted one with an id as failed and did not
			// count one with no id (Pro v7.7.0 `src/core/classes/actions-conditions.php:578`, :677-687, :720).
			// Keeping it is what keeps the filter gating; the runner fails it and records why.
			if ( empty( $integration ) || empty( $condition_code ) ) {
				$kept = isset( $condition['id'] );

				automator_log(
					( $kept ? 'Kept' : 'Skipped' ) . ' a legacy condition with an empty integration or condition code during migration for recipe '
					. absint( $recipe_id ) . ' (legacy group ' . $legacy_group_id . ')'
				);

				if ( ! $kept ) {
					continue;
				}
			}

			$legacy_fields    = is_array( $condition['fields'] ?? null ) ? $condition['fields'] : array();
			$converted_fields = $this->convert_legacy_fields( $legacy_fields );
			$legacy_id        = isset( $condition['id'] ) ? (string) $condition['id'] : '';
			$backup           = is_array( $condition['backup'] ?? null )
				? $this->normalize_legacy_backup( $condition['backup'] )
				: array();

			$synthesis_key = '' !== $legacy_group_id && '' !== $legacy_id
				? sprintf( 'recipe_%d:filter:%s:condition:%s', $recipe_id, $legacy_group_id, $legacy_id ) . $key_suffix
				: null;

			$this->modifier_store->create(
				$recipe_id,
				$block_id,
				'filter_condition',
				$integration,
				$condition_code,
				$converted_fields,
				$backup,
				$menu_order,
				'' !== $legacy_id ? $legacy_id : null,
				$synthesis_key
			);

			++$menu_order;
		}
	}

	/**
	 * Coerce the legacy backup payload into the canonical
	 * `integration_name`/`sentence`/`sentence_html` shape the new Store
	 * expects. The legacy JSON uses `integrationName`, `nameDynamic`, and
	 * `titleHTML` keys; both shapes are accepted defensively.
	 *
	 * @param array $backup Raw backup payload from the legacy JSON.
	 *
	 * @return array Canonical backup payload.
	 */
	private function normalize_legacy_backup( array $backup ): array {
		return array(
			'integration_name' => (string) ( $backup['integrationName'] ?? $backup['integration_name'] ?? '' ),
			'sentence'         => (string) ( $backup['nameDynamic'] ?? $backup['sentence'] ?? '' ),
			'sentence_html'    => (string) ( $backup['titleHTML'] ?? $backup['sentence_html'] ?? '' ),
		);
	}

	/**
	 * Convert flat fields to structured fields format.
	 *
	 * The old format stores fields as flat key/value pairs with `_readable`,
	 * `_custom`, and `_label` suffixes. The new format groups these into
	 * structured objects per field code.
	 *
	 * @param array $fields Flat fields.
	 *
	 * @return array Structured fields keyed by option code.
	 */
	private function convert_legacy_fields( array $fields ): array {
		$converted = array();

		foreach ( $fields as $key => $value ) {
			// Skip suffix keys — they're collected with their base key.
			if ( false !== strpos( $key, '_readable' )
				|| false !== strpos( $key, '_custom' )
				|| false !== strpos( $key, '_label' )
			) {
				continue;
			}

			$custom_value = $fields[ $key . '_custom' ] ?? '';

			$converted[ $key ] = array(
				'value'    => 'automator_custom_value' === $value ? $custom_value : $value,
				'readable' => $fields[ $key . '_readable' ] ?? '',
				'custom'   => $custom_value,
			);
		}

		return $converted;
	}
}
