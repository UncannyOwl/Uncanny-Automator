<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Block\Resolvers;

use Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities\Delay_Schedule_Wire_Mapper;
use Uncanny_Automator\Services\Recipe\Structure\Actions\Item\Action;

/**
 * Renders block entities back into the shape the pre-unified-blocks builder understands.
 *
 * Compatibility shim for the in-flight unified-blocks UI rollout.
 *
 * {@see Block_Resolver} injects block-entity arrays (`entity_type='block'`,
 * `paths`, `modifiers`, etc.) into the action items list at priority 5 for the
 * new recipe builder.
 *
 * 8.0.0 no longer ships the pre-unified-blocks builder — `src/assets/_legacy`
 * carries no builder JS and `Recipe_Post_Rest_Api` is removed — so nothing in
 * this plugin consumes the shapes below by default. The translator is
 * registered from `structure.php` behind
 * `automator_blocks_legacy_translators_disabled()` so a consumer that still
 * expects the old shapes can be served, and so the new builder team can toggle
 * it off to verify their renderer against raw `uo-block` storage.
 *
 * The two shapes it produces are the ones that builder understood:
 *
 *  - {@see Action} objects — top-level actions.
 *  - Legacy filter wrappers — `[type=>'filter', id, logic, conditions, items]`,
 *    the shape the retired `Conditions_Pluggable` used to build when reading
 *    `actions_conditions` post-meta. This class is now the only producer.
 *
 * Loops are handled by the legacy {@see \Uncanny_Automator\Services\Recipe\Structure\Actions\Item\Loop}
 * class and continue to render natively as long as Block_Resolver does not
 * mark `loops_resolved`.
 *
 * This translator runs at priority 99 and turns Block_Resolver's filter
 * block-entities back into the legacy filter wrapper shape so the bundled UI
 * renders condition groups as it always did. Other block kinds
 * (delay_schedule, async_action, generic) are flattened to their child Action
 * objects — the legacy UI has no native container for them.
 *
 * Migration preserves source data, surfaced through the entity arrays the
 * resolver passes us — the translator never reads post-meta directly
 * (CLAUDE.md Rule 3):
 *  - `_legacy_actions_conditions` post-meta on the recipe holds the original
 *    `actions_conditions` JSON.
 *  - `legacy_group_id` on each block entity holds the original string-hash
 *    group id, so the rendered `id` is round-trip-stable.
 *  - `legacy_id` on each modifier entry holds the original condition id.
 *  - `backup` on each modifier entry holds the integration display payload
 *    the legacy UI shows on the chip.
 *
 * Remove this hook (or short-circuit on a feature flag) once the new builder
 * ships and can render `entity_type='block'` natively.
 */
final class Legacy_Frontend_Translator {

	/**
	 * Register the translator filter at priority 99 so it runs AFTER
	 * Block_Resolver (priority 5). `Conditions_Pluggable` used to occupy
	 * priority 10 in this chain; it is retired with the condition domain and
	 * this class produces its output instead.
	 *
	 * Idempotent — safe to call multiple times.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'automator_recipe_main_object_action_items', array( self::class, 'translate' ), 99, 3 );
	}

	/**
	 * Suspension flag — when true, translate() passes the resolver's
	 * block-native entities through untouched.
	 *
	 * @var bool
	 */
	private static bool $suspended = false;

	/**
	 * Run a callback with the legacy translation suspended.
	 *
	 * Block-native consumers (MCP get_recipe) read the same Structure the
	 * legacy consumers do, but want the resolver's path-keyed block
	 * entities — the legacy flatten loses delay containers and merges
	 * filter branches. The flag is scoped to the callback and restored on
	 * exception.
	 *
	 * @param callable $callback Zero-arg callback to run.
	 * @return mixed The callback's return value.
	 */
	public static function with_translation_suspended( callable $callback ) {
		self::$suspended = true;
		try {
			return $callback();
		} finally {
			self::$suspended = false;
		}
	}

	/**
	 * Walk the items list and convert block entities to legacy-shaped items.
	 *
	 * Also rewrites every surfaced Action's `miscellaneous->parent_id` to
	 * the recipe id (legacy semantics) so the bundled UI's filter / delay
	 * helpers — which key off `parent_id` to find the action's container
	 * in `UncannyAutomator.recipe.items` — don't dereference a uo-block id
	 * that the items map doesn't know about (which is what was crashing
	 * the page with `Cannot read properties of undefined (reading 'miscellaneous')`).
	 *
	 * @param array $items             Mixed array of Action objects + block entity arrays.
	 * @param mixed $recipe            Recipe structure object — used only for `get('recipe_id')`.
	 * @param mixed $structure_actions Actions structure instance (unused).
	 *
	 * @return array Legacy-shaped items the bundled UI can render.
	 */
	public static function translate( $items, $recipe = null, $structure_actions = null ): array {
		if ( self::$suspended ) {
			return (array) $items;
		}

		$recipe_id  = self::resolve_recipe_id( $recipe );
		$translated = array();

		foreach ( (array) $items as $item ) {

			if ( ! self::is_block_entity( $item ) ) {
				if ( $item instanceof Action && $recipe_id > 0 ) {
					self::rewrite_action_parent_id( $item, $recipe_id );
				}
				$translated[] = $item;
				continue;
			}

			$converted = self::convert_block_entity( $item, $recipe_id );

			if ( null === $converted ) {
				continue;
			}

			if ( is_array( $converted ) && self::is_action_list( $converted ) ) {
				foreach ( $converted as $action ) {
					$translated[] = $action;
				}
				continue;
			}

			$translated[] = $converted;
		}

		return $translated;
	}

	/**
	 * Pull the recipe id off the Structure object the filter passed us.
	 * Returns 0 when unresolvable so callers degrade to a no-op rewrite.
	 *
	 * @param mixed $recipe Recipe structure object or null.
	 *
	 * @return int
	 */
	private static function resolve_recipe_id( $recipe ): int {
		if ( ! is_object( $recipe ) || ! method_exists( $recipe, 'get' ) ) {
			return 0;
		}
		$rid = $recipe->get( 'recipe_id' );
		return is_numeric( $rid ) ? (int) $rid : 0;
	}

	/**
	 * Overwrite `miscellaneous->parent_id` on a hydrated Action so the
	 * legacy bundled UI sees the recipe id it expects, not the wrapping
	 * block's post id (which is what the action's actual `post_parent`
	 * holds after the migrator reparented it).
	 *
	 * @param Action $action    Hydrated Action object.
	 * @param int    $recipe_id Recipe post id.
	 *
	 * @return void
	 */
	private static function rewrite_action_parent_id( $action, int $recipe_id ): void {
		if ( ! ( $action instanceof Action ) || ! method_exists( $action, 'set' ) ) {
			return;
		}
		$misc = $action->get( 'miscellaneous' );
		if ( ! is_object( $misc ) ) {
			$misc = new \stdClass();
		}
		$misc->parent_id = $recipe_id;
		$action->set( 'miscellaneous', $misc );
	}

	/**
	 * Whether an item is a block entity array.
	 *
	 * @param mixed $item Item from the action items list.
	 *
	 * @return bool
	 */
	private static function is_block_entity( $item ): bool {
		return is_array( $item ) && 'block' === ( $item['entity_type'] ?? '' );
	}

	/**
	 * Whether an array contains only Action objects.
	 *
	 * @param array $value Possibly-mixed array.
	 *
	 * @return bool
	 */
	private static function is_action_list( array $value ): bool {
		foreach ( $value as $entry ) {
			if ( ! ( $entry instanceof Action ) ) {
				return false;
			}
		}
		return ! empty( $value );
	}

	/**
	 * Dispatch a block entity to the right legacy shape.
	 *
	 * Returns:
	 *  - array (legacy filter wrapper) for `type='filter'` blocks.
	 *  - Action[] for any other block type — flattened so child actions
	 *    surface to the legacy UI.
	 *  - null when a block of any other type holds no action: the legacy UI
	 *    has no container to draw it in, so it contributes nothing.
	 *
	 * @param array $block     Block entity array.
	 * @param int   $recipe_id Recipe id used to rewrite inner-action parent_id.
	 *
	 * @return array|null
	 */
	private static function convert_block_entity( array $block, int $recipe_id = 0 ) {
		$type = (string) ( $block['type'] ?? '' );

		if ( 'filter' === $type ) {
			return self::build_legacy_filter_wrapper( $block, $recipe_id );
		}

		$actions = self::extract_actions_recursive( $block, $recipe_id );
		if ( empty( $actions ) ) {
			return null;
		}

		if ( 'delay_schedule' === $type ) {
			// Delay block has no legacy container UI — flatten to the inner
			// action(s) AND hoist the wrapping block's fields back onto each
			// action's `miscellaneous->delay_schedule` so the bundled UI's
			// `e.meta.async_mode` lookup still triggers the delay badge.
			$fields = is_array( $block['fields'] ?? null ) ? $block['fields'] : array();
			foreach ( $actions as $action ) {
				self::hoist_delay_fields_onto_action( $action, $fields );
			}
		}

		// async_action / unknown — no legacy container UI; surface the
		// inner actions so they remain editable.
		return $actions;
	}

	/**
	 * Reattach a delay_schedule block's fields onto the wrapped Action's
	 * `miscellaneous->delay_schedule` so the bundled UI keeps showing the
	 * "delayed" badge after migration moves the meta off the action.
	 *
	 * Mirrors the shape `\Uncanny_Automator\Services\Recipe\Structure\Actions\Item\Action::restructure_delay_schedule()`
	 * builds from raw `async_*` post-meta — the legacy UI consumes either
	 * source identically.
	 *
	 * The two vocabularies differ on both sides of this hoist: the block's
	 * `fields` use the Delay_Block option codes (`DELAY_MODE`, `DELAY_TOKEN`)
	 * while the legacy bag mirrors `async_*` post-meta (`MODE`,
	 * `CUSTOM_VALUE`, and token-mode spelled `custom`). The mode value is
	 * translated by {@see Delay_Schedule_Wire_Mapper} so that vocabulary
	 * keeps a single owner per Rule 17.
	 *
	 * @param Action $action       Hydrated action whose miscellaneous to mutate.
	 * @param array  $block_fields Wrapping block's `fields` post-meta value
	 *                             (e.g. `['DELAY_MODE'=>'delay','DELAY_VALUE'=>'1','DELAY_UNIT'=>'minutes']`).
	 *
	 * @return void
	 */
	private static function hoist_delay_fields_onto_action( $action, array $block_fields ): void {
		if ( ! ( $action instanceof Action ) || ! method_exists( $action, 'set' ) ) {
			return;
		}

		$mode = self::scalar_field( $block_fields, 'DELAY_MODE' );
		if ( '' === $mode ) {
			return;
		}

		$misc = $action->get( 'miscellaneous' );
		if ( ! is_object( $misc ) ) {
			$misc = new \stdClass();
		}

		$wire_mode = ( new Delay_Schedule_Wire_Mapper() )->block_mode_to_wire_mode( $mode );

		$misc->delay_schedule = array(
			'MODE'          => array( 'value' => $wire_mode ),
			'DELAY_VALUE'   => array( 'value' => self::scalar_field( $block_fields, 'DELAY_VALUE' ) ),
			'DELAY_UNIT'    => array( 'value' => self::scalar_field( $block_fields, 'DELAY_UNIT' ) ),
			'SCHEDULE_TIME' => array( 'value' => self::scalar_field( $block_fields, 'SCHEDULE_TIME' ) ),
			'SCHEDULE_DATE' => array( 'value' => self::scalar_field( $block_fields, 'SCHEDULE_DATE' ) ),
			'CUSTOM_VALUE'  => array( 'value' => self::scalar_field( $block_fields, 'DELAY_TOKEN' ) ),
		);

		$action->set( 'miscellaneous', $misc );
	}

	/**
	 * Read a scalar field value from a block's `fields` array tolerantly,
	 * unwrapping a `['value' => …]` shape if present.
	 *
	 * @param array  $fields Block fields.
	 * @param string $key    Field code.
	 *
	 * @return string Scalar value or empty string when absent.
	 */
	private static function scalar_field( array $fields, string $key ): string {
		if ( ! array_key_exists( $key, $fields ) ) {
			return '';
		}
		$raw = $fields[ $key ];
		if ( is_array( $raw ) ) {
			$raw = $raw['value'] ?? '';
		}
		if ( null === $raw ) {
			return '';
		}
		return (string) $raw;
	}

	/**
	 * Build a legacy `type=>'filter'` wrapper from a filter block entity.
	 *
	 * Reproduces the shape the retired
	 * `Conditions_Pluggable::resolve_action_flow()` used to emit, so the
	 * legacy builder keeps rendering filters unchanged.
	 *
	 * @param array $block Filter block entity from Block_Resolver.
	 *
	 * @return array Legacy filter wrapper.
	 */
	private static function build_legacy_filter_wrapper( array $block, int $recipe_id = 0 ): array {
		$block_id   = (int) ( $block['id'] ?? 0 );
		$logic      = self::resolve_logic( $block );
		$conditions = self::translate_conditions( (array) ( $block['modifiers'] ?? array() ) );
		$items      = self::extract_actions_recursive( $block, $recipe_id );

		$ui_order = isset( $block['_ui_order'] )
			? (int) $block['_ui_order']
			: self::resolve_first_action_ui_order( $items );

		return array(
			'type'       => 'filter',
			'_ui_order'  => $ui_order,
			'id'         => self::resolve_legacy_group_id( $block, $block_id ),
			'logic'      => $logic,
			'conditions' => $conditions,
			'items'      => $items,
		);
	}

	/**
	 * Resolve the match-mode (`'all'` / `'any'`) from the block fields.
	 *
	 * @param array $block Filter block entity.
	 *
	 * @return string
	 */
	private static function resolve_logic( array $block ): string {
		$fields = $block['fields'] ?? null;

		if ( ! is_array( $fields ) ) {
			return 'all';
		}

		$mode = $fields['CONDITION_MATCH_MODE'] ?? null;

		if ( is_array( $mode ) ) {
			$mode = $mode['value'] ?? 'all';
		}

		$mode = strtolower( (string) $mode );

		return in_array( $mode, array( 'any', 'all' ), true ) ? $mode : 'all';
	}

	/**
	 * Resolve the legacy string-hash group id, preferring the value the
	 * resolver hydrated onto the entity. Falls back to parsing the
	 * `synthesis_key` breadcrumb, then to a stable pseudo-id derived from
	 * the uo-block post id.
	 *
	 * @param array $block    Block entity from the resolver.
	 * @param int   $block_id `uo-block` post id.
	 *
	 * @return string
	 */
	private static function resolve_legacy_group_id( array $block, int $block_id ): string {
		$legacy = (string) ( $block['legacy_group_id'] ?? '' );

		if ( '' !== $legacy ) {
			return $legacy;
		}

		$from_synthesis = self::extract_legacy_id_from_synthesis_key(
			(string) ( $block['synthesis_key'] ?? '' ),
			'filter'
		);

		if ( '' !== $from_synthesis ) {
			return $from_synthesis;
		}

		return $block_id > 0 ? 'uo-block-' . $block_id : '';
	}

	/**
	 * Pull the legacy id out of a `_synthesis_key` breadcrumb such as
	 * `recipe_388284:filter:morissneqvjc7i4qnj9` or
	 * `recipe_388284:filter:morissneqvjc7i4qnj9:condition:moristyc48i37bw5sy6`.
	 *
	 * The legacy id is the segment immediately following the requested
	 * anchor (`filter` for groups; `condition` for conditions). Returns
	 * empty string when the segment cannot be located.
	 *
	 * @param string $synthesis_key Breadcrumb value.
	 * @param string $anchor        Segment marker (`filter` or `condition`).
	 *
	 * @return string
	 */
	private static function extract_legacy_id_from_synthesis_key( string $synthesis_key, string $anchor ): string {
		if ( '' === $synthesis_key || '' === $anchor ) {
			return '';
		}

		$parts = explode( ':', $synthesis_key );
		foreach ( $parts as $index => $segment ) {
			if ( $segment === $anchor && isset( $parts[ $index + 1 ] ) ) {
				return (string) $parts[ $index + 1 ];
			}
		}

		return '';
	}

	/**
	 * Translate Block_Resolver's modifier array into the legacy condition
	 * payload the frontend expects.
	 *
	 * @param array $modifiers Block_Resolver modifiers (filter conditions).
	 *
	 * @return array Legacy conditions array.
	 */
	private static function translate_conditions( array $modifiers ): array {
		$conditions = array();

		foreach ( $modifiers as $modifier ) {
			if ( ! is_array( $modifier ) || 'filter_condition' !== ( $modifier['type'] ?? '' ) ) {
				continue;
			}

			$post_id     = (int) ( $modifier['id'] ?? 0 );
			$legacy_id   = (string) ( $modifier['legacy_id'] ?? '' );
			$synth_id    = self::extract_legacy_id_from_synthesis_key(
				(string) ( $modifier['synthesis_key'] ?? '' ),
				'condition'
			);
			$resolved_id = '' !== $legacy_id
				? $legacy_id
				: ( '' !== $synth_id ? $synth_id : ( $post_id > 0 ? 'uo-cond-' . $post_id : '' ) );

			$conditions[] = array(
				'type'             => 'filter_condition',
				'entity_type'      => 'item',
				'id'               => $resolved_id,
				'integration_code' => (string) ( $modifier['integration_code'] ?? '' ),
				'code'             => (string) ( $modifier['code'] ?? '' ),
				'fields'           => self::normalize_fields( $modifier['fields'] ?? array() ),
				'backup'           => self::normalize_backup( is_array( $modifier['backup'] ?? null ) ? $modifier['backup'] : array() ),
			);
		}

		return $conditions;
	}

	/**
	 * Coerce the modifier fields into the legacy `[option_code => {value, readable, custom}]`
	 * shape. Block_Resolver passes through migrator output which already matches.
	 * stdClass placeholders ({} JSON) become empty arrays.
	 *
	 * @param mixed $fields Modifier fields.
	 *
	 * @return array
	 */
	private static function normalize_fields( $fields ): array {
		if ( $fields instanceof \stdClass ) {
			return array();
		}
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Coerce the modifier `backup` payload into the canonical
	 * `integration_name`/`sentence`/`sentence_html` shape the legacy UI
	 * renders on each condition chip. Accepts both the canonical shape
	 * the new Store stores and the legacy `integrationName` / `nameDynamic`
	 * / `titleHTML` shape from older imports defensively.
	 *
	 * @param array $backup Raw backup payload from the modifier entity.
	 *
	 * @return array
	 */
	private static function normalize_backup( array $backup ): array {
		return array(
			'integration_name' => (string) ( $backup['integration_name'] ?? $backup['integrationName'] ?? '' ),
			'sentence'         => (string) ( $backup['sentence'] ?? $backup['nameDynamic'] ?? '' ),
			'sentence_html'    => (string) ( $backup['sentence_html'] ?? $backup['titleHTML'] ?? '' ),
		);
	}

	/**
	 * Recursively walk a block entity's `paths` and return the leaf Action
	 * objects in encounter order. Nested block entities are descended into.
	 *
	 * Each surfaced Action's `miscellaneous->parent_id` is rewritten to
	 * the recipe id so the bundled UI's lookups against
	 * `UncannyAutomator.recipe.items[ parent_id ]` resolve to the recipe
	 * (legacy semantics) rather than the wrapping block's post id (which
	 * is what the action's actual `post_parent` holds after migration and
	 * is not present in the items map).
	 *
	 * @param array $block     Block entity.
	 * @param int   $recipe_id Recipe id; pass 0 to skip the parent_id rewrite.
	 *
	 * @return Action[]
	 */
	private static function extract_actions_recursive( array $block, int $recipe_id = 0 ): array {
		$actions = array();

		foreach ( (array) ( $block['paths'] ?? array() ) as $path_items ) {
			foreach ( (array) $path_items as $child ) {

				if ( $child instanceof Action ) {
					if ( $recipe_id > 0 ) {
						self::rewrite_action_parent_id( $child, $recipe_id );
					}
					$actions[] = $child;
					continue;
				}

				if ( self::is_block_entity( $child ) ) {
					foreach ( self::extract_actions_recursive( $child, $recipe_id ) as $nested ) {
						$actions[] = $nested;
					}
				}
			}
		}

		return $actions;
	}

	/**
	 * Pull a `_ui_order` from the first Action object so the wrapper sorts
	 * alongside its siblings. Falls back to 0 when no actions are present.
	 *
	 * @param Action[] $actions Action objects in encounter order.
	 *
	 * @return int
	 */
	private static function resolve_first_action_ui_order( array $actions ): int {
		foreach ( $actions as $action ) {
			if ( $action instanceof Action && method_exists( $action, 'get' ) ) {
				return (int) $action->get( '_ui_order' );
			}
		}
		return 0;
	}
}
