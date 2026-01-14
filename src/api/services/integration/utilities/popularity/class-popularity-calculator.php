<?php
/**
 * Popularity Calculator
 *
 * Calculates popularity scores for integrations based on plugin status and recipe usage.
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Popularity
 * @since 7.0.0
 */
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Integration\Utilities\Popularity;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Utility for calculating integration popularity scores.
 *
 * Popularity is determined by:
 * 1. Integration availability (plugin active, app connected, built-in) - 50% weight
 * 2. Recipe usage frequency (50% weight)
 *
 * Scores range from 0.0 to 1.0, with higher scores indicating more relevance.
 *
 * @since 7.0.0
 */
class Popularity_Calculator {

	/**
	 * Weight for integration availability in popularity calculation.
	 *
	 * @var float
	 */
	private const AVAILABILITY_WEIGHT = 0.5;

	/**
	 * Weight for recipe usage in popularity calculation.
	 *
	 * @var float
	 */
	private const RECIPE_USAGE_WEIGHT = 0.5;

	/**
	 * In-memory cache for recipe usage scores.
	 *
	 * @var array|null
	 */
	private $recipe_usage_scores = null;

	/**
	 * Calculate popularity score for an integration.
	 *
	 * @param Integration $integration Integration object
	 * @param string $scope Optional scope for context-specific scoring
	 *
	 * @return float Popularity score (0.0 to 1.0)
	 */
	public function calculate_popularity( Integration $integration, $scope = '' ) {

		$availability_score = $this->calculate_availability_score( $integration );
		$usage_score        = $this->calculate_recipe_usage_score( $integration, $scope );

		// Weighted average
		$popularity = ( $availability_score * self::AVAILABILITY_WEIGHT ) + ( $usage_score * self::RECIPE_USAGE_WEIGHT );

		return (float) $popularity;
	}

	/**
	 * Calculate integration availability score.
	 *
	 * Returns 1.0 if:
	 * - Integration is built-in (always available)
	 * - Integration is app type and connected
	 * - Integration's plugin is installed and active
	 *
	 * Returns 0.0 if:
	 * - Integration is plugin type and not active
	 * - Integration is app type and not connected
	 *
	 * @param Integration $integration Integration object
	 *
	 * @return float Score (0.0 or 1.0)
	 */
	private function calculate_availability_score( Integration $integration ) {

		// Built-in integrations are always available
		if ( $integration->is_built_in() ) {
			return 1.0;
		}

		// App integrations: check if connected
		if ( $integration->is_app() ) {
			return $integration->is_connected() ? 1.0 : 0.0;
		}

		// Check if plugin integration is active
		return $this->is_integration_active( $integration->get_code()->get_value() )
			? 1.0
			: 0.0;
	}

	/**
	 * Calculate recipe usage score.
	 *
	 * Queries published recipes to determine how frequently an integration
	 * is used in the specified scope (trigger, action, condition, loop-filter).
	 *
	 * @param Integration $integration Integration object
	 * @param string $scope Scope context (trigger, action, condition, loop-filter)
	 *
	 * @return float Score (0.0 to 1.0)
	 */
	private function calculate_recipe_usage_score( Integration $integration, $scope = '' ) {

		if ( empty( $scope ) ) {
			return 0.5;
		}

		$usage_scores = $this->get_recipe_usage_scores( $scope );

		if ( empty( $usage_scores ) ) {
			return 0.0;
		}

		$integration_code = $integration->get_code()->get_value();

		return isset( $usage_scores[ $integration_code ] )
			? $usage_scores[ $integration_code ]
			: 0.0;
	}

	/**
	 * Check if integration is active.
	 *
	 * @param string $integration_code Integration code
	 *
	 * @return bool True if integration is active
	 */
	private function is_integration_active( $integration_code ) {
		return Integration_Registry_Service::get_instance()->has_integration( $integration_code );
	}

	/**
	 * Get recipe usage scores for all integrations.
	 *
	 * Queries usage counts and normalizes them to 0.0-1.0 range.
	 * Uses in-memory caching for same-request efficiency.
	 *
	 * @param string $scope Scope context (trigger, action, condition, loop-filter)
	 *
	 * @return array Array of integration code => usage score (0.0 to 1.0)
	 */
	private function get_recipe_usage_scores( $scope ) {

		$cache_key = 'scope_' . $scope;

		// Check in-memory cache first (for same request)
		if ( isset( $this->recipe_usage_scores[ $cache_key ] ) ) {
			return $this->recipe_usage_scores[ $cache_key ];
		}

		// Get usage counts (from condition service or direct query)
		$usage_counts = $this->query_recipe_usage( $scope );

		if ( empty( $usage_counts ) ) {
			$scores = array();
		} else {
			// Normalize scores (0.0 to 1.0) based on max usage
			$max_usage = max( $usage_counts );
			$scores    = array();

			foreach ( $usage_counts as $integration_code => $count ) {
				$scores[ $integration_code ] = $max_usage > 0
					? (float) ( $count / $max_usage )
					: 0.0;
			}
		}

		// Store in memory cache
		$this->recipe_usage_scores[ $cache_key ] = $scores;

		return $scores;
	}

	/**
	 * Query recipe usage counts for integrations.
	 *
	 * Performs a single performant query to count how many times each
	 * integration is used in published recipes for the specified scope.
	 *
	 * @param string $scope Scope context (trigger, action, condition, loop-filter)
	 *
	 * @return array Array of integration code => count
	 */
	private function query_recipe_usage( $scope ) {

		// Conditions are stored differently (as JSON in recipe meta)
		if ( Integration_Item_Types::FILTER_CONDITION === $scope ) {
			return $this->query_filter_condition_usage();
		}

		global $wpdb;

		$post_type = $this->scope_to_post_type( $scope );

		if ( empty( $post_type ) ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value as integration_code, COUNT(DISTINCT p.ID) as usage_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->posts} r ON p.post_parent = r.ID
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE r.post_type = %s
					AND r.post_status = %s
					AND p.post_type = %s
					AND p.post_status = %s
					AND pm.meta_key = %s
					AND pm.meta_value != ''
				GROUP BY pm.meta_value
				ORDER BY usage_count DESC",
				'uo-recipe',
				Recipe_Status::PUBLISH,
				$post_type,
				Recipe_Status::PUBLISH,
				'integration'
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		$usage_counts = array();
		foreach ( $results as $row ) {
			$usage_counts[ $row['integration_code'] ] = absint( $row['usage_count'] );
		}

		return $usage_counts;
	}

	/**
	 * Query condition usage counts for integrations.
	 *
	 * Delegates to the condition popularity tracker which handles caching and parsing.
	 *
	 * @return array Array of integration code => count
	 */
	private function query_filter_condition_usage() {
		return Filter_Condition_Popularity_Tracker::get_instance()->get_usage_counts();
	}

	/**
	 * Convert scope to post type.
	 *
	 * @param string $scope Scope context (trigger, action, condition, loop-filter)
	 *
	 * @return string Post type or empty string
	 */
	private function scope_to_post_type( $scope ) {
		$map = array(
			Integration_Item_Types::TRIGGER     => 'uo-trigger',
			Integration_Item_Types::ACTION      => 'uo-action',
			Integration_Item_Types::LOOP_FILTER => 'uo-loop-filter',
			Integration_Item_Types::CLOSURE     => 'uo-closure',
		);

		return isset( $map[ $scope ] ) ? $map[ $scope ] : '';
	}

	/**
	 * Clear all cached data.
	 *
	 * Clears both persistent (condition popularity tracker) and in-memory caches.
	 *
	 * @return void
	 */
	public function clear_cache() {
		Filter_Condition_Popularity_Tracker::get_instance()->clear_cache();
		$this->recipe_usage_scores = null;
	}
}
