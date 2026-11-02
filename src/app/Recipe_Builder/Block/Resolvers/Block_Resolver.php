<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Resolvers;

use Uncanny_Automator\App\Bridge\Action_Tokens_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Tokens_Bridge;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Action_Conditions_Meta_Migrator;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Delay_Schedule_Meta_Migrator;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\Services\Recipe\Structure;
use Uncanny_Automator\Services\Recipe\Structure\Actions\Actions;
use Uncanny_Automator\Services\Recipe\Structure\Actions\Item\Action;
use Uncanny_Automator\Services\Structure\Actions\Item\Loop\Loop_Db;

/**
 * Injects a recipe's blocks and loops into the action list the builder renders.
 *
 * Single resolver for recipe blocks AND loops. Hooks into the action items
 * filter to inject blocks and loop blocks alongside top-level actions.
 *
 * For recipes with unified blocks (uo-block post type, loops included):
 * 1. Fetches all block children of the recipe via a single store query.
 * 2. Fetches all loop posts for the recipe via Loop_Db.
 * 3. Dispatches each child by entity_type (block or item).
 * 4. Recursively resolves nested blocks to arbitrary depth.
 * 5. Returns blocks mixed with top-level actions for UI rendering.
 *
 * Legacy recipes without blocks or loops pass through unchanged.
 */
class Block_Resolver {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether loops were resolved by this resolver.
	 *
	 * When true, the legacy Loop creation in actions.php is skipped.
	 *
	 * @var bool
	 */
	private static bool $loops_resolved = false;

	/**
	 * Block database store.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Block-modifier database store (paradigm-agnostic).
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Block registry for path definitions.
	 *
	 * @var WP_Block_Registry
	 */
	private WP_Block_Registry $block_registry;

	/**
	 * Legacy conditions migrator.
	 *
	 * @var Action_Conditions_Meta_Migrator
	 */
	private Action_Conditions_Meta_Migrator $migrator;

	/**
	 * Legacy delay/schedule migrator.
	 *
	 * @var Delay_Schedule_Meta_Migrator
	 */
	private Delay_Schedule_Meta_Migrator $delay_migrator;

	/**
	 * Loop database queries.
	 *
	 * @var Loop_Db
	 */
	private Loop_Db $loop_db;

	/**
	 * Bridge to the legacy renderable-tokens computer. Populates
	 * `$action['tokens']` for actions resolved out of uo-block storage so
	 * the bundled recipe-builder's token dropdown stays whole.
	 *
	 * @var Action_Tokens_Bridge
	 */
	private Action_Tokens_Bridge $action_tokens_bridge;

	/**
	 * Constructor.
	 *
	 * @param Block_Store                     $block_store          Block database store.
	 * @param Block_Modifier_Store            $modifier_store       Block-modifier database store.
	 * @param WP_Block_Registry               $block_registry       Block registry for path definitions.
	 * @param Action_Conditions_Meta_Migrator $migrator             Legacy conditions migrator.
	 * @param Delay_Schedule_Meta_Migrator    $delay_migrator       Legacy delay/schedule migrator.
	 * @param Loop_Db                         $loop_db              Loop database queries.
	 * @param Action_Tokens_Bridge|null       $action_tokens_bridge Optional renderable-tokens bridge; defaults to the WP impl.
	 */
	public function __construct(
		Block_Store $block_store,
		Block_Modifier_Store $modifier_store,
		WP_Block_Registry $block_registry,
		Action_Conditions_Meta_Migrator $migrator,
		Delay_Schedule_Meta_Migrator $delay_migrator,
		Loop_Db $loop_db,
		?Action_Tokens_Bridge $action_tokens_bridge = null
	) {
		$this->block_store          = $block_store;
		$this->modifier_store       = $modifier_store;
		$this->block_registry       = $block_registry;
		$this->migrator             = $migrator;
		$this->delay_migrator       = $delay_migrator;
		$this->loop_db              = $loop_db;
		$this->action_tokens_bridge = $action_tokens_bridge ?? new Automator_Action_Tokens_Bridge();
	}

	/**
	 * Get singleton instance with default dependencies.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			$block_store    = Database::get_block_store();
			$modifier_store = Database::get_block_modifier_store();

			self::$instance = self::create_with_dependencies(
				$block_store,
				$modifier_store,
				new WP_Block_Registry(),
				new Action_Conditions_Meta_Migrator( $block_store, $modifier_store ),
				new Delay_Schedule_Meta_Migrator( $block_store ),
				new Loop_Db()
			);
		}
		return self::$instance;
	}

	/**
	 * Create instance with explicit dependencies.
	 *
	 * @param Block_Store                     $block_store    Block database store.
	 * @param Block_Modifier_Store            $modifier_store Block-modifier database store.
	 * @param WP_Block_Registry               $block_registry Block registry for path definitions.
	 * @param Action_Conditions_Meta_Migrator $migrator       Legacy conditions migrator.
	 * @param Delay_Schedule_Meta_Migrator    $delay_migrator Legacy delay/schedule migrator.
	 * @param Loop_Db                         $loop_db        Loop database queries.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		Block_Store $block_store,
		Block_Modifier_Store $modifier_store,
		WP_Block_Registry $block_registry,
		Action_Conditions_Meta_Migrator $migrator,
		Delay_Schedule_Meta_Migrator $delay_migrator,
		Loop_Db $loop_db
	): self {
		return new self( $block_store, $modifier_store, $block_registry, $migrator, $delay_migrator, $loop_db );
	}

	/**
	 * Whether loops were resolved by the Block_Resolver.
	 *
	 * When true, the legacy Loop creation in actions.php should be skipped
	 * to avoid duplicate loop items in the recipe object.
	 *
	 * @return bool
	 */
	public static function loops_resolved(): bool {
		return self::$loops_resolved;
	}

	/**
	 * Subscribe to the action-items filter, early enough to resolve blocks and loops in one pass.
	 *
	 * Hooks at priority 5 to resolve both blocks and loops in a single pass.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'automator_recipe_main_object_action_items', array( $this, 'resolve_action_items' ), 5, 3 );
	}

	/**
	 * Resolve action items by injecting blocks and loops into the action list.
	 *
	 * If the recipe has no uo-block children, no loops, and no legacy conditions,
	 * returns unchanged (pure legacy recipe).
	 *
	 * If legacy `actions_conditions` meta exists but no blocks, runs on-the-fly
	 * migration first, then resolves the newly created blocks.
	 *
	 * If blocks or loops exist, builds block entities and mixes them with
	 * top-level actions in a single pass.
	 *
	 * @param Action[]  $action_items      Top-level action objects.
	 * @param Structure $recipe            Recipe structure object.
	 * @param Actions   $structure_actions Actions structure instance.
	 *
	 * @return array Mixed array of Action objects and block entity arrays.
	 */
	public function resolve_action_items( $action_items, $recipe, $structure_actions ) {
		$recipe_id = $recipe->get( 'recipe_id' );

		$blocks              = $this->block_store->get_by_parent( $recipe_id );
		$loops               = $this->loop_db->find_recipe_loops( $recipe_id );
		$migrated_action_ids = array();

		if ( ! empty( $blocks ) ) {
			return $this->resolve_with_blocks( $action_items, $blocks, $loops, $recipe );
		}

		// No blocks — attempt on-the-fly migration of legacy conditions.
		// The Structure object already loaded all recipe meta, so pass it
		// directly to avoid a redundant get_post_meta call.
		$recipe_meta = $recipe->meta();
		try {
			$migrated_action_ids = $this->migrator->maybe_migrate_from_meta( $recipe_id, $recipe_meta );
		} catch ( \RuntimeException $e ) {
			// The legacy structure read keeps its old behaviour: unreadable conditions resolve as none.
			automator_log( sprintf( 'Recipe %d: legacy conditions not migrated on structure read: %s', $recipe_id, $e->getMessage() ), 'Block_Resolver' );
			$migrated_action_ids = array();
		}

		if ( ! empty( $migrated_action_ids ) ) {
			$blocks = $this->block_store->get_by_parent( $recipe_id );
		}

		// Attempt on-the-fly migration of legacy delay/schedule meta.
		// The action items are already hydrated with all meta — check in-memory
		// to avoid an expensive meta_query when no async actions exist.
		if ( $this->has_async_actions( $action_items ) ) {
			$delay_migrated = $this->delay_migrator->maybe_migrate( $recipe_id );
			if ( ! empty( $delay_migrated ) ) {
				$migrated_action_ids = array_merge( $migrated_action_ids, $delay_migrated );
				$blocks              = $this->block_store->get_by_parent( $recipe_id );
			}
		}

		// Nothing to resolve — pure legacy recipe with no blocks or loops.
		if ( empty( $blocks ) && empty( $loops ) ) {
			return $action_items;
		}

		// Filter out actions that were just migrated into blocks. These are still
		// in $action_items because the Structure layer built them before migration.
		return $this->resolve_with_blocks( $action_items, $blocks, $loops, $recipe, $migrated_action_ids );
	}

	/**
	 * Resolve action items when blocks and/or loops exist.
	 *
	 * Filters out migrated actions and builds block + loop entities
	 * alongside top-level actions.
	 *
	 * @param array $action_items        Top-level action objects.
	 * @param array $blocks              Block data arrays from store.
	 * @param array $loops               Loop post arrays from Loop_Db.
	 * @param mixed $recipe              Recipe structure object.
	 * @param int[] $migrated_action_ids Action IDs moved into blocks during migration.
	 *
	 * @return array Mixed array of Action objects and block entity arrays.
	 */
	private function resolve_with_blocks( array $action_items, array $blocks, array $loops, $recipe, array $migrated_action_ids = array() ): array {
		// Loop ownership intentionally NOT claimed here — the legacy
		// `Structure\Actions\Item\Loop` class still creates loop items in
		// actions.php so the bundled recipe builder UI can render them. When
		// the new builder lands and can render entity_type='block' loops, set
		// $loops_resolved = ! empty( $loops ) and re-enable the loop foreach
		// below.
		self::$loops_resolved = false;
		unset( $loops );

		$items = $this->filter_migrated_actions( $action_items, $migrated_action_ids );

		foreach ( $blocks as $block_data ) {
			// Loop blocks render via the legacy `Item\Loop` class — that
			// path is fed by `Loop_Db` (widened to find folded-in
			// `uo-block(loop)` posts). Emitting the loop as a block
			// entity here too would render it twice in the bundled UI.
			// Pipeline runtime sees loops via `Schema_Node_Projector` →
			// `Loop_Schema_Node` → Pro's `Loop_Block_Type` independently
			// of this resolver, so skipping here doesn't affect runtime.
			if ( Block_Type::LOOP === ( $block_data['block_type'] ?? '' ) ) {
				continue;
			}
			$items[] = $this->build_block( $block_data, $recipe );
		}

		return $items;
	}

	/**
	 * Filter out actions that were moved into blocks during migration.
	 *
	 * After on-the-fly migration, the action items list still contains actions
	 * that have been reparented to blocks. This removes them so they only appear
	 * inside their block's path.
	 *
	 * @param array $action_items        Original action items.
	 * @param int[] $migrated_action_ids Action IDs that were moved into blocks.
	 *
	 * @return array Filtered action items.
	 */
	private function filter_migrated_actions( array $action_items, array $migrated_action_ids ): array {
		if ( empty( $migrated_action_ids ) ) {
			return $action_items;
		}

		$migrated_set = array_flip( $migrated_action_ids );

		return array_values(
			array_filter(
				$action_items,
				function ( $item ) use ( $migrated_set ) {
					$action_id = $item instanceof Action ? $item->get( 'id' ) : 0;
					return ! isset( $migrated_set[ $action_id ] );
				}
			)
		);
	}

	/**
	 * Build a block entity array for the recipe object.
	 *
	 * Called recursively for nested blocks.
	 *
	 * @param array     $block_data Block data from store.
	 * @param Structure $recipe     Recipe structure object.
	 *
	 * @return array Block entity array.
	 */
	private function build_block( array $block_data, $recipe ): array {
		$block_id   = (int) $block_data['id'];
		$block_type = (string) $block_data['block_type'];

		return array(
			'entity_type'     => 'block',
			'type'            => $block_type,
			'_ui_order'       => $block_data['menu_order'],
			'id'              => $block_id,
			'fields'          => ! empty( $block_data['fields'] ) ? $block_data['fields'] : new \stdClass(),
			'modifiers'       => $this->build_modifiers( $block_id, $block_type ),
			'paths'           => $this->build_paths( $block_id, $block_type, $recipe ),
			// Expose synthesis breadcrumbs to the translator without forcing
			// it to call get_post_meta directly (CLAUDE.md Rule 3).
			'legacy_group_id' => $block_data['legacy_group_id'] ?? null,
			'synthesis_key'   => $block_data['synthesis_key'] ?? null,
		);
	}

	/**
	 * Build modifiers (filter conditions, loop filters) for a block.
	 *
	 * Filter blocks read filter_condition rows; loop blocks read loop_filter
	 * rows. Other block kinds return empty.
	 *
	 * @param int    $block_id   Block post ID.
	 * @param string $block_type Block type.
	 *
	 * @return array Array of modifier arrays (legacy wire shape).
	 */
	private function build_modifiers( int $block_id, string $block_type ): array {
		$discriminator = $this->resolve_modifier_discriminator( $block_type );
		if ( null === $discriminator ) {
			return array();
		}

		$rows      = $this->modifier_store->get_by_block( $block_id, $discriminator );
		$modifiers = array();

		foreach ( $rows as $row ) {
			$fields = $row['fields'] ?? array();
			if ( is_array( $fields ) && empty( $fields ) ) {
				$fields = new \stdClass();
			}

			$modifiers[] = array(
				'type'             => $discriminator,
				'entity_type'      => 'item',
				'id'               => (int) ( $row['id'] ?? 0 ),
				'integration_code' => (string) ( $row['integration_code'] ?? '' ),
				'code'             => (string) ( $row['code'] ?? '' ),
				'fields'           => $fields,
				'backup'           => is_array( $row['backup'] ?? null ) ? $row['backup'] : array(),
				'legacy_id'        => $row['legacy_id'] ?? null,
				'synthesis_key'    => $row['synthesis_key'] ?? null,
			);
		}

		return $modifiers;
	}

	/**
	 * Map a block_type to its modifier discriminator value.
	 *
	 * @param string $block_type Block type slug.
	 *
	 * @return string|null Discriminator value, or null when this block kind has no modifiers.
	 */
	private function resolve_modifier_discriminator( string $block_type ): ?string {
		if ( Block_Type::FILTER === $block_type ) {
			return 'filter_condition';
		}

		if ( 'loop' === $block_type ) {
			return 'loop_filter';
		}

		return null;
	}

	/**
	 * Build paths with their child items (actions and nested blocks).
	 *
	 * Uses a single store query to fetch all children of the block, then
	 * dispatches each by entity_type. Blocks are recursively resolved.
	 * Children are already ordered by menu_order from the store.
	 *
	 * @param int       $block_id   Block post ID.
	 * @param string    $block_type Block type.
	 * @param Structure $recipe     Recipe structure object.
	 *
	 * @return array Keyed by path_code, each containing an ordered array of
	 *               hydrated Action objects and/or block entity arrays.
	 */
	private function build_paths( int $block_id, string $block_type, $recipe ): array {
		$block_definition = $this->block_registry->get_block_definition( $block_type );
		$paths            = array();

		// Initialize paths from registry definitions.
		$path_definitions = $block_definition ? $block_definition->get_paths() : array();
		foreach ( $path_definitions as $path ) {
			$paths[ $path->get_path_code() ] = array();
		}

		// Single query: all children (blocks + actions) ordered by menu_order.
		$children = $this->block_store->get_children( $block_id );
		if ( empty( $children ) ) {
			return $paths;
		}

		// Dispatch each child by entity_type into the correct path.
		foreach ( $children as $child ) {
			$path_code = $this->resolve_path_code( $child, $paths, $block_type );
			if ( null === $path_code ) {
				continue;
			}

			$paths[ $path_code ][] = $this->resolve_child( $child, $recipe );
		}

		return $paths;
	}

	/**
	 * Determine which path a child belongs to.
	 *
	 * For blocks, reads path_code from the block data.
	 * For items, reads path_code from post meta.
	 * Falls back to the block type's default path when the stamp is empty or
	 * names a path the block type does not declare (a stale stamp from a
	 * previous parent), as Recipe_Tree_Builder::build_block_paths() does for
	 * the tree the runner walks: the builder shows a step where it runs.
	 *
	 * @param array  $child      Child data from store.
	 * @param array  $paths      Current paths map (keyed by path_code).
	 * @param string $block_type Parent block type, for the default-path fallback.
	 *
	 * @return string|null Path code, or null to skip.
	 */
	private function resolve_path_code( array $child, array $paths, string $block_type ): ?string {
		$path_code = 'block' === $child['entity_type']
			? $child['path_code'] ?? ''
			: $child['meta']['path_code'] ?? '';

		// Known path — use it.
		if ( ! empty( $path_code ) && isset( $paths[ $path_code ] ) ) {
			return $path_code;
		}

		// Empty or undeclared stamp: the type's default path, as Recipe_Tree_Builder does.
		$default = Block_Path_Code::default_for_block_type( $block_type );

		return isset( $paths[ $default ] ) ? $default : null;
	}

	/**
	 * Resolve a single child into a block entity or hydrated Action.
	 *
	 * Dispatches by entity_type: blocks are recursively built,
	 * items are hydrated through Item\Action.
	 *
	 * @param array     $child  Child data from store.
	 * @param Structure $recipe Recipe structure object.
	 *
	 * @return array|Action Block entity array or hydrated Action object.
	 */
	private function resolve_child( array $child, $recipe ) {
		if ( 'block' === $child['entity_type'] ) {
			return $this->build_block( $child, $recipe );
		}

		// Stamp renderable tokens on the action data BEFORE
		// hydration. `WP_Block_Store::build_item_data_from_post` returns
		// `tokens => []` because the store doesn't compute derived data;
		// without populating this here, `Item\Action`'s `Tokens` class
		// iterates an empty array and the bundled UI's token dropdown
		// loses every action-emitted token (FOLDER_PATH on
		// DROPBOX_CREATE_FOLDER, etc.) for actions inside any uo-block.
		$action_id   = (int) ( $child['ID'] ?? 0 );
		$action_meta = (array) ( $child['meta'] ?? array() );
		$recipe_id   = is_object( $recipe ) && method_exists( $recipe, 'get' ) ? (int) $recipe->get( 'recipe_id' ) : 0;

		if ( $action_id > 0 && $recipe_id > 0 && ! empty( $action_meta['code'] ) ) {
			$child['tokens'] = $this->action_tokens_bridge->get_renderable_tokens(
				$action_meta,
				$action_id,
				$recipe_id
			);
		}

		$action = new Action( $recipe );
		$action->hydrate_from( $child );

		return $action;
	}

	/**
	 * Check if any action items have async delay/schedule meta.
	 *
	 * The Structure layer already hydrates each action's meta via get_post_custom().
	 * When async_mode is present, the Action item sets miscellaneous->delay_schedule.
	 * Checking this in-memory avoids the meta_query in find_async_actions() on
	 * every recipe load after migration has already completed.
	 *
	 * @param array $action_items Hydrated Action item objects.
	 *
	 * @return bool True if any action has async delay/schedule meta.
	 */
	private function has_async_actions( array $action_items ): bool {
		foreach ( $action_items as $item ) {
			if ( ! $item instanceof Action ) {
				continue;
			}

			$misc = $item->get( 'miscellaneous' );

			if ( isset( $misc->delay_schedule ) ) {
				return true;
			}
		}

		return false;
	}
}
