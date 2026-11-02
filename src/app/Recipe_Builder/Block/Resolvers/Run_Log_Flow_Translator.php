<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Block\Resolvers;

use Uncanny_Automator\App\Bridge\Automator_Recipe_Log_Meta_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Log_Meta_Bridge;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;

/**
 * Translates the uo-block tree into the legacy `actions_flow` shape that
 * `Log_Endpoint::get_log` (and the bundled run-details modal) consumes.
 *
 * The legacy `Recipe_Actions_Resolver` builds the flow by querying
 * `post_parent = recipe_id` with `post_type = uo-action`, which sees only the
 * top-level uo-actions. Actions reparented into uo-blocks (delay/filter) are
 * invisible to it. This translator walks the full block tree and writes the
 * `actions_flow` meta itself BEFORE the legacy logger fires; the legacy
 * logger short-circuits when the meta already exists.
 *
 * Wire-shape produced (matching the legacy resolver):
 *  - flow: dict keyed by action_id (string) → comma-separated action_ids
 *    string. Top-level actions get `'<id>' => ''`. A filter block becomes
 *    `'<filter_block_id>' => '<inner_action_id_1>, <inner_action_id_2>'`
 *    (the comma+space matches `flip_ordered_action_conditions::implode`).
 *    Delay blocks emit their inner action(s) flat — the legacy UI has no
 *    delay-grouping container at the run-details level.
 *  - actions_conditions: JSON string in legacy `parseConditionsInMeta`
 *    shape — one entry per filter block. Pulled from
 *    `_legacy_actions_conditions` post-meta when present (preserves the
 *    original group-id hash); otherwise synthesised from uo-block-modifier
 *    rows so brand-new block-builder recipes still render filter wrappers.
 *
 * Hooks (priority 25, before the legacy logger at 30):
 *  - `automator_trigger_completed`
 *  - `automator_recipe_process_user_trigger_num_times_insufficient`
 */
final class Run_Log_Flow_Translator {

	/**
	 * Block store — used to walk uo-block children of the recipe.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Block-modifier store — used to read filter_condition rows when projecting
	 * `actions_conditions` from blocks (when no legacy backup exists).
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Recipe-log meta bridge — the flow payload's `uap_recipe_log_meta`
	 * upsert routes through it (Rule 1).
	 *
	 * @var Recipe_Log_Meta_Bridge
	 */
	private Recipe_Log_Meta_Bridge $log_meta_bridge;

	/**
	 * Reader for the legacy recipe meta this translator projects from.
	 *
	 * @var Recipe_Legacy_Meta_Store
	 */
	private Recipe_Legacy_Meta_Store $legacy_meta_store;

	/**
	 * Reads the run's existing `actions_flow` meta.
	 *
	 * @var Execution_Log_Store
	 */
	private Execution_Log_Store $execution_log_store;

	/**
	 * Constructor.
	 *
	 * @param Block_Store|null            $block_store     Block store impl.
	 * @param Block_Modifier_Store|null   $modifier_store  Block-modifier store impl.
	 * @param Recipe_Log_Meta_Bridge|null   $log_meta_bridge   Recipe-log meta writer; defaults to the Automator impl.
	 * @param Recipe_Legacy_Meta_Store|null $legacy_meta_store Legacy recipe-meta reader.
	 * @param Execution_Log_Store|null      $execution_log_store Recipe-log meta reader.
	 */
	public function __construct(
		?Block_Store $block_store = null,
		?Block_Modifier_Store $modifier_store = null,
		?Recipe_Log_Meta_Bridge $log_meta_bridge = null,
		?Recipe_Legacy_Meta_Store $legacy_meta_store = null,
		?Execution_Log_Store $execution_log_store = null
	) {
		$this->block_store         = $block_store ?? Database::get_block_store();
		$this->modifier_store      = $modifier_store ?? Database::get_block_modifier_store();
		$this->log_meta_bridge     = $log_meta_bridge ?? new Automator_Recipe_Log_Meta_Bridge();
		$this->legacy_meta_store   = $legacy_meta_store ?? Database::get_recipe_legacy_meta_store();
		$this->execution_log_store = $execution_log_store ?? Database::get_execution_log_store();
	}

	/**
	 * Register hook subscribers. Priority 25 ensures we run before the legacy
	 * logger (priority 30) so the meta is already in place when it checks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'automator_trigger_completed', array( $this, 'maybe_log_flow' ), 25, 1 );
		add_action( 'automator_recipe_process_user_trigger_num_times_insufficient', array( $this, 'maybe_log_flow' ), 25, 1 );
	}

	/**
	 * Build the legacy `actions_flow` payload from uo-block storage and store
	 * it on the run's `uap_recipe_log_meta`. No-op when the recipe has no
	 * uo-block children (pure legacy recipe — let the legacy resolver run).
	 *
	 * @param array $hook_args Hook payload — expects `recipe_id`, `recipe_log_id`.
	 *
	 * @return void
	 */
	public function maybe_log_flow( $hook_args = array() ): void {
		$recipe_id     = isset( $hook_args['recipe_id'] ) ? (int) $hook_args['recipe_id'] : 0;
		$recipe_log_id = isset( $hook_args['recipe_log_id'] ) ? (int) $hook_args['recipe_log_id'] : 0;

		if ( $recipe_id <= 0 || $recipe_log_id <= 0 ) {
			return;
		}

		$blocks = $this->block_store->get_by_parent( $recipe_id );
		if ( empty( $blocks ) ) {
			return;
		}

		// Already logged — defer to whatever wrote it (concurrent trigger fires).
		if ( ! empty( $this->existing_flow_meta( $recipe_log_id ) ) ) {
			return;
		}

		$flow               = $this->build_flow( $recipe_id );
		$actions_conditions = $this->resolve_actions_conditions_json( $recipe_id );
		$payload            = array(
			'actions_conditions' => $actions_conditions,
			'flow'               => $flow,
		);

		$this->upsert_flow_meta( $recipe_id, $recipe_log_id, $payload );
	}

	/**
	 * Walk recipe → uo-blocks → uo-actions and produce the flow dict the
	 * legacy resolver would have built.
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return array<string, string> Flow dict keyed by string id.
	 */
	private function build_flow( int $recipe_id ): array {
		$flow = array();

		foreach ( $this->block_store->get_children( $recipe_id ) as $child ) {
			if ( 'block' === ( $child['entity_type'] ?? '' ) ) {
				$this->add_block_to_flow( $child, $flow );
				continue;
			}

			$action_id = (int) ( $child['ID'] ?? 0 );
			if ( $action_id > 0 ) {
				$flow[ (string) $action_id ] = '';
			}
		}

		return $flow;
	}

	/**
	 * Add a block + its inner actions to the flow dict.
	 *
	 * Filter blocks become a single entry: `<block_id> => '<a1>, <a2>'`.
	 * Delay/other blocks flatten — each inner action becomes its own
	 * top-level entry so the run-details modal shows them in order.
	 *
	 * @param array $block Block row from store.
	 * @param array $flow  Flow dict (by reference).
	 *
	 * @return void
	 */
	private function add_block_to_flow( array $block, array &$flow ): void {
		$block_id   = (int) ( $block['id'] ?? 0 );
		$block_type = (string) ( $block['block_type'] ?? '' );

		$inner_action_ids = $this->block_store->collect_descendant_action_ids( $block_id );

		if ( 'filter' === $block_type && ! empty( $inner_action_ids ) ) {
			// Run-details rendering (`Action_Logs_Resources::get_log`) treats
			// the flow key as a filter wrapper ONLY when `is_numeric($key)`
			// is false. The block post id is numeric so we can't use it —
			// use the legacy hash group id (e.g. `mot70kkkzm8sn42eaua`) the
			// migrator preserved on the block, falling back to a stable
			// synthesised hash for block-first recipes that never carried a
			// legacy id.
			$group_key          = $this->resolve_filter_group_key( $block_id, $block );
			$flow[ $group_key ] = implode( ', ', array_map( 'strval', $inner_action_ids ) );
			return;
		}

		foreach ( $inner_action_ids as $inner_id ) {
			$flow[ (string) $inner_id ] = '';
		}
	}

	/**
	 * Resolve a non-numeric flow key for a filter block. Prefers the
	 * `_legacy_group_id` breadcrumb the migrator stamps on the uo-block;
	 * falls back to a synthesised `block_<id>` string so the key stays
	 * non-numeric (so `is_numeric()` returns false and the renderer routes
	 * it through the filter-wrapper branch).
	 *
	 * @param int   $block_id Filter block post id.
	 * @param array $block    Block row (carries `legacy_group_id` when present).
	 *
	 * @return string Non-numeric key suitable for use in `actions_flow.flow`.
	 */
	private function resolve_filter_group_key( int $block_id, array $block ): string {
		$legacy = isset( $block['legacy_group_id'] ) ? (string) $block['legacy_group_id'] : '';
		if ( '' === $legacy ) {
			$legacy = (string) $this->legacy_meta_store->get_source( $block_id, '_legacy_group_id' );
		}
		if ( '' !== $legacy && ! is_numeric( $legacy ) ) {
			return $legacy;
		}
		return 'block_' . $block_id;
	}

	/**
	 * Resolve the legacy `actions_conditions` JSON used by the run-details
	 * modal to render filter wrappers. Three sources, in order:
	 *
	 *  1. `_legacy_actions_conditions` post-meta — the migrator's untouched
	 *     backup of the original JSON. Round-trip stable.
	 *  2. `actions_conditions` post-meta — recipes that were never migrated
	 *     (pure legacy) keep their JSON here.
	 *  3. Synthesised from `uo-block-modifier(filter_condition)` rows — for
	 *     recipes that were authored block-first and have no legacy backup.
	 *
	 * Returns an empty string when there are no filter blocks (legacy
	 * resolver behaviour for recipes without conditions).
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return string JSON string (or empty).
	 */
	private function resolve_actions_conditions_json( int $recipe_id ): string {
		$backup = (string) $this->legacy_meta_store->get_source( $recipe_id, '_legacy_actions_conditions' );
		if ( '' !== $backup && '[]' !== $backup ) {
			return $backup;
		}

		$current = (string) $this->legacy_meta_store->get_source( $recipe_id, 'actions_conditions' );
		if ( '' !== $current && '[]' !== $current ) {
			return $current;
		}

		return $this->synthesise_actions_conditions_json( $recipe_id );
	}

	/**
	 * Synthesise the `actions_conditions` JSON from filter blocks under the
	 * recipe — used when neither the legacy backup nor the live legacy meta
	 * are populated (block-first recipes).
	 *
	 * @param int $recipe_id Recipe post id.
	 *
	 * @return string JSON string (or empty when there are no filters).
	 */
	private function synthesise_actions_conditions_json( int $recipe_id ): string {
		$groups = array();

		foreach ( $this->block_store->get_by_parent( $recipe_id ) as $block ) {
			if ( 'filter' !== ( $block['block_type'] ?? '' ) ) {
				continue;
			}

			$block_id        = (int) ( $block['id'] ?? 0 );
			$inner_actions   = $this->block_store->collect_descendant_action_ids( $block_id );
			$mode            = $this->extract_match_mode( (array) ( $block['fields'] ?? array() ) );
			$conditions_rows = $this->modifier_store->get_by_block( $block_id, 'filter_condition' );

			$conditions = array();
			foreach ( $conditions_rows as $row ) {
				$conditions[] = array(
					'id'             => (string) ( $row['id'] ?? '' ),
					'integration'    => (string) ( $row['integration_code'] ?? '' ),
					'condition_code' => (string) ( $row['code'] ?? '' ),
					'fields'         => (array) ( $row['fields'] ?? array() ),
				);
			}

			$groups[] = array(
				'id'         => $this->resolve_filter_group_key( $block_id, $block ),
				'logic'      => $mode,
				'actions'    => $inner_actions,
				'conditions' => $conditions,
			);
		}

		if ( empty( $groups ) ) {
			return '';
		}

		return (string) wp_json_encode( $groups );
	}

	/**
	 * Pull the match mode out of a filter block's fields.
	 *
	 * @param array $fields Fields dict.
	 *
	 * @return string Match mode (`'all'`/`'any'`).
	 */
	private function extract_match_mode( array $fields ): string {
		$raw = $fields['CONDITION_MATCH_MODE'] ?? 'all';
		if ( is_array( $raw ) ) {
			return (string) ( $raw['value'] ?? 'all' );
		}
		return (string) $raw;
	}

	/**
	 * Read the existing `actions_flow` meta for a run, if any.
	 *
	 * @param int $recipe_log_id Recipe log id.
	 *
	 * @return string Raw meta value (empty when missing).
	 */
	private function existing_flow_meta( int $recipe_log_id ): string {
		// Must stay user-agnostic: this translator writes with user_id 0 while the
		// legacy logger writes with the run's user, and either row counts as present.
		return (string) ( $this->execution_log_store->get_recipe_log_meta( $recipe_log_id, 'actions_flow' ) ?? '' );
	}

	/**
	 * Upsert the `actions_flow` meta for the run.
	 *
	 * Routed through the {@see Recipe_Log_Meta_Bridge} (which wraps
	 * Automator's recipe DB facade) so this code stays in lockstep with
	 * the rest of the legacy logger machinery — no direct `INSERT` here,
	 * no raw facade call (Rule 1).
	 *
	 * @param int   $recipe_id     Recipe post id.
	 * @param int   $recipe_log_id Recipe log id.
	 * @param array $payload       Flow payload dict.
	 *
	 * @return void
	 */
	private function upsert_flow_meta( int $recipe_id, int $recipe_log_id, array $payload ): void {
		$this->log_meta_bridge->upsert( $recipe_id, $recipe_log_id, 'actions_flow', $payload );
	}
}
