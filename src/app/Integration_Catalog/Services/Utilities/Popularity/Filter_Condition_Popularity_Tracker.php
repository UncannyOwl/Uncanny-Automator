<?php
/**
 * Condition Popularity Tracker
 *
 * Tracks legacy filter-condition usage for the stored usage census.
 * Handles parsing, caching, and querying of recipe conditions.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity
 * @since 7.0.0
 */
namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity;

use Uncanny_Automator\Traits\Singleton;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;

/**
 * Tracks condition usage data for popularity calculation.
 *
 * Conditions are stored as JSON in recipe postmeta (actions_conditions) —
 * read-only legacy storage draining via fix-on-request migration. Counts
 * rebuild lazily from a full published-recipes scan; invalidation lives in
 * Automator_Cache_Handler (entity saves + recipe status changes),
 * so there is no incremental per-recipe maintenance here. The handle_*()
 * methods at the end only answer 7.x's calls during the upgrade request.
 *
 * @since 7.0.0
 */
class Filter_Condition_Popularity_Tracker {

	use Singleton;

	/**
	 * Legacy 7.x integration-level cache key. Nothing reads or writes it
	 * anymore — integration counts derive from the entity counts — but
	 * clear_cache() keeps deleting it so upgraded sites shed the residue.
	 *
	 * @var string
	 */
	const UAP_OPTION_KEY = 'uap_condition_usage_counts';

	/**
	 * Cache key for per-recipe entity-level (integration:code) counts.
	 *
	 * @var string
	 */
	const UAP_ENTITY_OPTION_KEY = 'uap_condition_entity_usage_counts';

	/**
	 * Action meta_key.
	 *
	 * @var string
	 */
	const ACTION_META_KEY = 'actions_conditions';

	/**
	 * Entity-level usage counts ("INTEGRATION:CODE" => count) over the residual
	 * actions_conditions JSON.
	 *
	 * @return array<string,int>
	 */
	public function get_entity_usage_counts() {
		// Option existence is the built marker: a stored empty array means
		// "built, legitimately zero legacy conditions" and must not retrigger
		// the full published-recipes rescan (clear_cache deletes the option).
		$cached = automator_get_option( self::UAP_ENTITY_OPTION_KEY, false );

		if ( ! is_array( $cached ) ) {
			$this->rebuild_entity_cache();
			$cached = automator_get_option( self::UAP_ENTITY_OPTION_KEY, array() );
		}

		return $this->aggregate_counts( $cached );
	}

	/**
	 * Fetch raw (recipe_id, actions_conditions JSON) rows for all published recipes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function fetch_published_recipe_conditions() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as recipe_id, pm.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
					AND p.post_status = %s
					AND pm.meta_key = %s
					AND pm.meta_value != ''",
				AUTOMATOR_POST_TYPE_RECIPE,
				Recipe_Status::PUBLISH,
				self::ACTION_META_KEY
			),
			ARRAY_A
		);
	}

	/**
	 * Rebuild the per-recipe entity cache from published recipes' actions_conditions.
	 *
	 * @return void
	 */
	private function rebuild_entity_cache() {

		$per_recipe = array();
		foreach ( $this->fetch_published_recipe_conditions() as $row ) {
			$entity = $this->parse_conditions_entities( $row['meta_value'] );
			if ( ! empty( $entity ) ) {
				$per_recipe[ 'recipe_' . absint( $row['recipe_id'] ) ] = $entity;
			}
		}

		// Unbounded per-recipe map, read at most once per rebuild — never autoload.
		automator_update_option( self::UAP_ENTITY_OPTION_KEY, $per_recipe, false );
	}

	/**
	 * Parse one recipe's conditions JSON into "INTEGRATION:CODE" => count.
	 *
	 * @param string|array $conditions_data
	 * @return array<string,int>
	 */
	private function parse_conditions_entities( $conditions_data ) {
		if ( ! is_string( $conditions_data ) && ! is_array( $conditions_data ) ) {
			return array();
		}
		$groups = is_string( $conditions_data ) ? json_decode( $conditions_data, true ) : $conditions_data;
		if ( ! is_array( $groups ) ) {
			return array();
		}

		$counts = array();
		foreach ( $groups as $group ) {
			if ( empty( $group['conditions'] ) || ! is_array( $group['conditions'] ) ) {
				continue;
			}
			foreach ( $group['conditions'] as $condition ) {
				$integration = is_string( $condition['integration'] ?? null ) ? $condition['integration'] : '';
				$code        = is_string( $condition['condition'] ?? null ) ? $condition['condition'] : '';
				if ( '' === $integration || '' === $code ) {
					continue;
				}
				$key            = $integration . ':' . $code;
				$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
			}
		}
		return $counts;
	}

	/**
	 * Aggregate per-recipe condition counts into totals.
	 *
	 * @param array $per_recipe_data Per-recipe condition counts
	 *
	 * @return array Total counts per integration
	 */
	private function aggregate_counts( $per_recipe_data ) {

		$totals = array();

		foreach ( $per_recipe_data as $recipe_id => $integrations ) {
			// Safety check - skip if not an array
			if ( ! is_array( $integrations ) ) {
				continue;
			}

			foreach ( $integrations as $integration_code => $count ) {
				if ( ! isset( $totals[ $integration_code ] ) ) {
					$totals[ $integration_code ] = 0;
				}
				$totals[ $integration_code ] += $count;
			}
		}

		return $totals;
	}

	/**
	 * Clear entire cache.
	 *
	 * Deletes the entity option (the built marker) and the legacy 7.x
	 * integration-level option so upgraded sites shed the residue.
	 *
	 * @return void
	 */
	public function clear_cache() {
		automator_delete_option( self::UAP_OPTION_KEY );
		automator_delete_option( self::UAP_ENTITY_OPTION_KEY );
	}

	/**
	 * Answer 7.x's postmeta add and update call. Does nothing.
	 *
	 * Exists only for the request that upgrades from 7.x. When Upload Plugin
	 * replaces 7.x with this version, 7.x's Automator_Cache_Handler stays
	 * hooked on added_post_meta and updated_post_meta for the rest of that
	 * request, and this class autoloads from the new files. The 7.x option
	 * that call maintained is never read here, and from the next request
	 * Automator_Cache_Handler invalidates the counts itself. Do not remove
	 * this method until upgrades from 7.x are no longer supported.
	 *
	 * @since 8.0.0
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return void
	 */
	public function handle_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
	}

	/**
	 * Answer 7.x's postmeta delete call. Does nothing.
	 *
	 * Exists only for the request that upgrades from 7.x. When Upload Plugin
	 * replaces 7.x with this version, 7.x's Automator_Cache_Handler stays
	 * hooked on deleted_post_meta for the rest of that request, and WordPress
	 * then deletes the uploaded zip's attachment meta. The 7.x option that
	 * call maintained is never read here, and from the next request
	 * Automator_Cache_Handler invalidates the counts itself. Do not remove
	 * this method until upgrades from 7.x are no longer supported.
	 *
	 * @since 8.0.0
	 *
	 * @param int[]  $meta_ids   Meta IDs.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return void
	 */
	public function handle_meta_delete( $meta_ids, $post_id, $meta_key, $meta_value ) {
	}

	/**
	 * Answer 7.x's recipe status change call. Does nothing.
	 *
	 * Exists only for the request that upgrades from 7.x. When Upload Plugin
	 * replaces 7.x with this version, 7.x's Automator_Cache_Handler stays
	 * hooked on automator_recipe_status_updated and
	 * automator_cache_recipe_post_status_changed for the rest of that
	 * request. The 7.x option that call maintained is never read here, and
	 * from the next request Automator_Cache_Handler invalidates the counts
	 * itself. Do not remove this method until upgrades from 7.x are no
	 * longer supported.
	 *
	 * @since 8.0.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param int    $recipe_id   Recipe ID.
	 * @param string $post_status New post status.
	 *
	 * @return void
	 */
	public function handle_status_change( $post_id, $recipe_id, $post_status ) {
	}
}
