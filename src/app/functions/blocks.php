<?php
/**
 * Block-storage helper functions.
 *
 * Public API for the unified-blocks rollout. Today this exposes a single
 * kill switch the block frontend team can flip to bypass the legacy
 * compatibility layer entirely so they can verify how `entity_type='block'`
 * items render natively in the new builder.
 *
 * @package Uncanny_Automator\App\Functions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'automator_blocks_legacy_translators_disabled' ) ) {
	/**
	 * Kill switch for the legacy-frontend compat layer the bundled
	 * recipe-builder UI depends on. When enabled:
	 *
	 *   1. {@see \Uncanny_Automator\App\Recipe_Builder\Block\Resolvers\Legacy_Frontend_Translator}
	 *      does NOT register on `automator_recipe_main_object_action_items`.
	 *      The recipe object emits native `entity_type='block'` items with
	 *      their `paths` / `modifiers` / `fields`. The bundled main.js will
	 *      crash — that's the point: this flag is for the new builder team.
	 *
	 *   2. {@see \Uncanny_Automator\App\Recipe_Builder\Block\Migration\Migration_Hooks::inject_actions_conditions_into_recipes_data}
	 *      does NOT register on `automator_get_recipes_data` /
	 *      `automator_get_recipe_data_by_recipe_id`. `recipes_object[recipe_id].actions_conditions`
	 *      stays empty when the source post-meta has been migrated, so the
	 *      bundled UI's `parseConditionsInMeta` finds nothing.
	 *
	 * Block_Resolver itself stays on. Migrators and write-side sync stay on.
	 * The runtime pipeline reads uo-block storage directly and is unaffected.
	 *
	 * Two independent triggers — either flips the switch:
	 *
	 *  - PHP constant (set in `wp-config.php`):
	 *      define( 'AUTOMATOR_DISABLE_BLOCKS_LEGACY_TRANSLATORS', true );
	 *
	 *  - WordPress filter (per-request override, e.g. in a mu-plugin):
	 *      add_filter( 'automator_disable_blocks_legacy_translators', '__return_true' );
	 *
	 * Constant + filter combine via OR so a long-lived constant can be
	 * forced on/off in tests via the filter.
	 *
	 * @return bool
	 */
	function automator_blocks_legacy_translators_disabled(): bool {
		$by_constant = defined( 'AUTOMATOR_DISABLE_BLOCKS_LEGACY_TRANSLATORS' ) && AUTOMATOR_DISABLE_BLOCKS_LEGACY_TRANSLATORS;

		return (bool) apply_filters( 'automator_disable_blocks_legacy_translators', $by_constant );
	}
}
