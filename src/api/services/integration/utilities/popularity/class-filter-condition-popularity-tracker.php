<?php
/**
 * Condition Popularity Tracker
 *
 * Tracks condition usage for integration popularity scoring.
 * Handles parsing, caching, and querying of recipe conditions.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Popularity
 * @since 7.0.0
 */
namespace Uncanny_Automator\Api\Services\Integration\Utilities\Popularity;

use Uncanny_Automator\Traits\Singleton;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;

/**
 * Tracks condition usage data for popularity calculation.
 *
 * Conditions are stored as JSON in recipe postmeta (actions_conditions).
 * This utility provides a clean interface for tracking which integrations
 * are used in conditions, with per-recipe caching for performance.
 *
 * Handles both data management and cache invalidation through WordPress hooks.
 *
 * @since 7.0.0
 */
class Filter_Condition_Popularity_Tracker {

	use Singleton;

	/**
	 * Cache key for storing per-recipe condition counts.
	 *
	 * @var string
	 */
	const UAP_OPTION_KEY = 'uap_condition_usage_counts';

	/**
	 * Action meta_key.
	 *
	 * @var string
	 */
	const ACTION_META_KEY = 'actions_conditions';

	/**
	 * Get usage counts for all integrations used in conditions.
	 *
	 * Returns aggregated counts from cached per-recipe data.
	 *
	 * @return array Array of integration code => count
	 */
	public function get_usage_counts() {

		$cached_data = $this->get_cache();

		if ( empty( $cached_data ) ) {
			// No cache, build it from scratch.
			$this->rebuild_cache();
			$cached_data = $this->get_cache();
		}

		// Aggregate per-recipe data into total counts.
		return $this->aggregate_counts( $cached_data );
	}

	/**
	 * Update cache for a single recipe.
	 *
	 * Called when a recipe's conditions are modified.
	 *
	 * @param int $recipe_id Recipe ID
	 * @param string $conditions_json JSON string from actions_conditions meta
	 *
	 * @return void
	 */
	public function update_recipe_cache( $recipe_id, $conditions_json ) {

		$cached_data = $this->get_cache();
		$recipe_key  = 'recipe_' . $recipe_id;

		// Parse this recipe's conditions
		$recipe_data = $this->parse_conditions( $conditions_json );

		if ( empty( $recipe_data ) ) {
			// No conditions, remove from cache.
			unset( $cached_data[ $recipe_key ] );
		} else {
			// Update cache with this recipe's data.
			$cached_data[ $recipe_key ] = $recipe_data;
		}

		$this->save_cache( $cached_data );
	}

	/**
	 * Remove recipe from cache.
	 *
	 * Called when a recipe is deleted or unpublished.
	 *
	 * @param int $recipe_id Recipe ID
	 *
	 * @return void
	 */
	public function remove_recipe_from_cache( $recipe_id ) {

		$cached_data = $this->get_cache();
		$recipe_key  = 'recipe_' . $recipe_id;

		if ( isset( $cached_data[ $recipe_key ] ) ) {
			unset( $cached_data[ $recipe_key ] );
			$this->save_cache( $cached_data );
		}
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
	 * Parse recipe conditions and count integrations.
	 *
	 * Handles both array (from tests/direct access) and JSON string (from meta hooks).
	 *
	 * @param string|array $conditions_data Conditions data from actions_conditions meta.
	 *
	 * @return array Integration counts for this recipe.
	 */
	private function parse_conditions( $conditions_data ) {

		// Guard: reject unexpected types (null, object, etc).
		if ( ! is_string( $conditions_data ) && ! is_array( $conditions_data ) ) {
			return array();
		}

		// Normalize to array format.
		$condition_groups = is_string( $conditions_data )
			? json_decode( $conditions_data, true )
			: $conditions_data;

		// Guard: handle malformed JSON or invalid data.
		if ( ! is_array( $condition_groups ) || empty( $condition_groups ) ) {
			return array();
		}

		$counts = array();

		foreach ( $condition_groups as $condition_group ) {

			// Skip groups without conditions array.
			if ( empty( $condition_group['conditions'] ) || ! is_array( $condition_group['conditions'] ) ) {
				continue;
			}

			foreach ( $condition_group['conditions'] as $condition ) {

				// Skip conditions without integration code.
				if ( empty( $condition['integration'] ) || ! is_string( $condition['integration'] ) ) {
					continue;
				}

				$integration_code = $condition['integration'];

				if ( ! isset( $counts[ $integration_code ] ) ) {
					$counts[ $integration_code ] = 0;
				}

				++$counts[ $integration_code ];
			}
		}

		return $counts;
	}

	/**
	 * Rebuild entire cache from database.
	 *
	 * Queries all published recipes and parses their conditions.
	 *
	 * @return void
	 */
	private function rebuild_cache() {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as recipe_id, pm.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
					AND p.post_status = %s
					AND pm.meta_key = %s
					AND pm.meta_value != ''",
				'uo-recipe',
				Recipe_Status::PUBLISH,
				self::ACTION_META_KEY
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			$this->save_cache( array() );
			return;
		}

		$per_recipe_data = array();

		foreach ( $results as $row ) {
			$recipe_id   = absint( $row['recipe_id'] );
			$recipe_data = $this->parse_conditions( $row['meta_value'] );

			if ( ! empty( $recipe_data ) ) {
				$per_recipe_data[ 'recipe_' . $recipe_id ] = $recipe_data;
			}
		}

		$this->save_cache( $per_recipe_data );
	}

	/**
	 * Get cached data.
	 *
	 * @return array Per-recipe condition counts
	 */
	private function get_cache() {
		return automator_get_option( self::UAP_OPTION_KEY, array() );
	}

	/**
	 * Save cache data.
	 *
	 * @param array $data Per-recipe condition counts
	 *
	 * @return void
	 */
	private function save_cache( $data ) {
		automator_update_option( self::UAP_OPTION_KEY, $data );
	}

	/**
	 * Clear entire cache.
	 *
	 * Used for testing or manual cache invalidation.
	 *
	 * @return void
	 */
	public function clear_cache() {
		automator_delete_option( self::UAP_OPTION_KEY );
	}

	/**
	 * Handle postmeta update/add hook.
	 *
	 * Updates cache for the specific recipe when conditions are modified.
	 *
	 * @param int $meta_id Meta ID
	 * @param int $post_id Post ID (recipe ID)
	 * @param string $meta_key Meta key
	 * @param mixed $meta_value Meta value (JSON string)
	 *
	 * @return void
	 */
	public function handle_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {

		if ( ! $this->is_condition_meta( $meta_key ) ) {
			return;
		}

		if ( ! $this->is_recipe_post_type( $post_id ) ) {
			return;
		}

		// Only cache published recipes
		$post = get_post( $post_id );

		if ( ! $post || Recipe_Status::PUBLISH !== $post->post_status ) {
			$this->remove_recipe_from_cache( $post_id );
			return;
		}

		// Update cache for just this recipe
		$this->update_recipe_cache( $post_id, $meta_value );
	}

	/**
	 * Handle postmeta deletion hook.
	 *
	 * Removes the specific recipe from cache when conditions are deleted.
	 *
	 * @param array $meta_ids Meta IDs
	 * @param int $post_id Post ID (recipe ID)
	 * @param string $meta_key Meta key
	 * @param mixed $meta_value Meta value
	 *
	 * @return void
	 */
	public function handle_meta_delete( $meta_ids, $post_id, $meta_key, $meta_value ) {

		if ( ! $this->is_condition_meta( $meta_key ) ) {
			return;
		}

		if ( ! $this->is_recipe_post_type( $post_id ) ) {
			return;
		}

		// Remove this recipe from cache
		$this->remove_recipe_from_cache( $post_id );
	}

	/**
	 * Handle recipe status change hook.
	 *
	 * Updates or removes recipe from cache based on new status.
	 * Only processes when the recipe post itself changes status, not child items.
	 *
	 * @param int $post_id Post ID (could be recipe or recipe item)
	 * @param int $recipe_id Recipe ID (parent recipe)
	 * @param string $post_status New status
	 *
	 * @return void
	 */
	public function handle_status_change( $post_id, $recipe_id, $post_status ) {

		// Only process if the recipe itself is changing status (not triggers/actions/etc)
		if ( ! $this->is_recipe_post_type( $post_id ) ) {
			return;
		}

		if ( Recipe_Status::PUBLISH === $post_status ) {
			// Recipe published - update cache with its conditions
			$conditions_json = get_post_meta( $recipe_id, self::ACTION_META_KEY, true );

			if ( ! empty( $conditions_json ) ) {
				$this->update_recipe_cache( $recipe_id, $conditions_json );
			}

			return;
		}

		// Recipe unpublished - remove from cache
		$this->remove_recipe_from_cache( $recipe_id );
	}

	/**
	 * Check if meta key is for conditions.
	 *
	 * @param string $meta_key Meta key to check
	 *
	 * @return bool True if meta key is for conditions
	 */
	private function is_condition_meta( $meta_key ) {
		return self::ACTION_META_KEY === $meta_key;
	}

	/**
	 * Check if post is a recipe.
	 *
	 * @param int $post_id Post ID to check
	 *
	 * @return bool True if post is a recipe
	 */
	private function is_recipe_post_type( $post_id ) {
		return 'uo-recipe' === get_post_type( $post_id );
	}
}
