<?php
/**
 * Recipe Cache Invalidator.
 *
 * Single point of contact between the Core API layer and the legacy
 * Automator recipe cache. Every store that writes recipe-related data
 * calls this class instead of reaching into the legacy cache directly.
 *
 * @package Uncanny_Automator\Api\Database
 * @since   7.2.0
 */

declare( strict_types=1 );

namespace Uncanny_Automator\Api\Database;

/**
 * Invalidates the legacy recipe builder cache after writes.
 *
 * The recipe builder caches the full recipe object. If a write
 * happens outside the legacy REST controller (e.g. via the MCP API),
 * the cache must be cleared or the builder will show stale data.
 */
class Recipe_Cache {

	/**
	 * Invalidate the cached recipe object for a given recipe.
	 *
	 * Safe to call even when the legacy Automator class is not loaded —
	 * the call is silently skipped.
	 *
	 * @param int $recipe_id The recipe post ID.
	 *
	 * @return void
	 */
	public static function invalidate( int $recipe_id ): void {

		if ( $recipe_id <= 0 ) {
			return;
		}

		if ( ! function_exists( 'Automator' ) ) {
			return;
		}

		$automator = Automator();

		if ( isset( $automator->cache ) && method_exists( $automator->cache, 'clear_automator_recipe_part_cache' ) ) {
			$automator->cache->clear_automator_recipe_part_cache( $recipe_id );
		}
	}
}
