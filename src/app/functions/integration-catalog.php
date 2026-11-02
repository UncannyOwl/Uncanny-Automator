<?php
/**
 * Integration Catalog helper functions.
 *
 * Public API for integration-catalog operations (popularity caches,
 * scope tags, entity discovery). These are convenience wrappers that
 * let non-app callers (legacy core, hooks, migrators) interact with
 * Integration_Catalog services without instantiating PSR-4 classes
 * directly.
 *
 * @package Uncanny_Automator\App\Functions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'automator_clear_entity_popularity_cache' ) ) {
	/**
	 * Hard-reset the entity-level popularity caches (stored counts, dirty
	 * flags, and the filter-condition tracker). Manual flush surface — runtime
	 * invalidation should use automator_refresh_entity_popularity_cache() so
	 * stored counts keep serving while the rebuild runs. Never throws.
	 *
	 * @return void
	 */
	function automator_clear_entity_popularity_cache() {
		try {
			// instance(), not a throwaway: clear_cache() resets the calculator's
			// in-request memo, and on a new object that leaves the singleton's
			// memo intact — so a manual flush would keep serving the counts it
			// just deleted for the rest of the request.
			\Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity\Entity_Popularity_Calculator::instance()->clear_cache();
		} catch ( \Throwable $e ) {
			automator_log( 'Failed to clear entity popularity cache: ' . $e->getMessage() );
		}
	}
}

if ( ! function_exists( 'automator_refresh_entity_popularity_cache' ) ) {
	/**
	 * Mark entity-level popularity counts stale and schedule their rebuild on
	 * this request's shutdown. Stored counts keep serving until the rebuild
	 * swaps them — readers never pay the aggregate queries. Safe convenience
	 * wrapper for non-app callers (legacy core, hooks, migrators). Never throws.
	 *
	 * Pass a kind+scope pair to refresh a single bucket (e.g. 'item'/'action');
	 * omit both to refresh everything.
	 *
	 * @param string|null $kind  Optional bucket identifier ('item' or 'block_modifier').
	 * @param string|null $scope Optional scope constant within the kind.
	 * @return void
	 */
	function automator_refresh_entity_popularity_cache( $kind = null, $scope = null ) {
		try {
			$kind  = is_string( $kind ) && '' !== $kind ? $kind : null;
			$scope = is_string( $scope ) && '' !== $scope ? $scope : null;
			\Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity\Entity_Popularity_Calculator::instance()->mark_dirty( $kind, $scope );
		} catch ( \Throwable $e ) {
			automator_log( 'Failed to refresh entity popularity cache: ' . $e->getMessage() );
		}
	}
}

if ( ! function_exists( 'automator_clear_integration_discovery_cache' ) ) {
	/**
	 * Drop the integration discovery cache so the next catalog build
	 * re-discovers every integration the feed does not carry. Entries already
	 * invalidate themselves when an integration's items, manifest or version
	 * change; this is the manual eviction path for anything else. Never throws.
	 *
	 * @return void
	 */
	function automator_clear_integration_discovery_cache() {
		try {
			\Uncanny_Automator\App\Integration_Catalog\Services\Integration_Discovery_Service::get_instance()->clear_all_caches();
		} catch ( \Throwable $e ) {
			automator_log( 'Failed to clear integration discovery cache: ' . $e->getMessage() );
		}
	}
}

if ( ! function_exists( 'automator_clear_integration_feed_cache' ) ) {
	/**
	 * Drop every cached copy of the integration catalog feed - English and each
	 * language - so the next catalog build fetches fresh. Never throws.
	 *
	 * @return void
	 */
	function automator_clear_integration_feed_cache() {
		try {
			\Uncanny_Automator\App\Integration_Catalog\Services\Integration_Feed_Service::get_instance()->clear_cache();
		} catch ( \Throwable $e ) {
			automator_log( 'Failed to clear integration feed cache: ' . $e->getMessage() );
		}
	}
}
