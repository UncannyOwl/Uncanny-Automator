<?php
/**
 * Recipe Registry Service
 *
 * Core business logic service for recipe discovery and search operations.
 * Single source of truth for recipe search, filtering, and metadata operations.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Recipe;

use Uncanny_Automator\Api\Components\Recipe\Recipe;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Services\Traits\Service_Response_Formatter;
use WP_Error;

/**
 * Recipe Registry Service Class
 *
 * Handles all recipe discovery operations with clean OOP architecture.
 */
class Recipe_Registry_Service {

	use Service_Response_Formatter;

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var Recipe_Registry_Service|null
	 */
	private static $instance = null;

	/**
	 * Recipe store instance.
	 *
	 * @var WP_Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 * @param WP_Recipe_Store|null $recipe_store Recipe storage implementation.
	 */
	public function __construct( $recipe_store = null ) {
		$this->recipe_store = $recipe_store ?? new WP_Recipe_Store();
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @since 7.0.0
	 * @return Recipe_Registry_Service
	 */
	public static function instance(): Recipe_Registry_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Search for recipes using fuzzy matching.
	 *
	 * @since 7.0.0
	 * @param string $query Search query.
	 * @param array  $filters Optional filters (status, type).
	 * @param int    $limit Optional. Maximum results to return. Default 10, max 50.
	 * @return array|\WP_Error Array of matching recipes or error.
	 */
	public function find_recipes( string $query, array $filters = array(), int $limit = 10 ) {
		if ( empty( $query ) ) {
			return $this->error_response( 'recipe_missing_query', 'Search query is required' );
		}

		$limit = min( $limit, 50 );

		try {
			// Get all recipes with basic filters
			$all_recipes = $this->recipe_store->all( $filters );

			if ( empty( $all_recipes ) ) {
				return array(
					'success'      => true,
					'message'      => 'No recipes found matching the criteria',
					'query'        => $query,
					'recipe_count' => 0,
					'recipes'      => array(),
				);
			}

			// Filter out null values and convert to arrays for search
			$valid_recipes = array_filter(
				$all_recipes,
				function ( $recipe ) {
					return $recipe instanceof Recipe;
				}
			);

			$recipe_arrays = array_map(
				function ( Recipe $recipe ) {
					return $recipe->to_array();
				},
				$valid_recipes
			);

			// Perform fuzzy search
			$results = $this->search_recipes_with_similarity( $query, $recipe_arrays, $limit );

			return array(
				'success'      => true,
				'message'      => 'Recipe search completed successfully',
				'query'        => $query,
				'recipe_count' => count( $results ),
				'recipes'      => $results,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'recipe_search_failed', 'Recipe search failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Get recipes by status.
	 *
	 * @since 7.0.0
	 * @param string $status Recipe status (draft, publish, trash).
	 * @param int    $limit  Optional. Maximum results to return.
	 * @return array|\WP_Error Array of recipes or error.
	 */
	public function get_recipes_by_status( string $status, int $limit = 0 ) {
		if ( ! in_array( $status, array( Recipe_Status::DRAFT, Recipe_Status::PUBLISH, 'trash' ), true ) ) {
			return $this->error_response( 'recipe_invalid_status_filter', 'Invalid recipe status. Must be: draft, publish, or trash', array( 'status' => $status ) );
		}

		$filters = array( 'status' => $status );
		if ( $limit > 0 ) {
			$filters['limit'] = $limit;
		}

		try {
			$recipes = $this->recipe_store->all( $filters );

			// Filter out null values before processing
			$valid_recipes = array_filter(
				$recipes,
				function ( $recipe ) {
					return $recipe instanceof Recipe;
				}
			);

			$formatted_recipes = array_map(
				function ( Recipe $recipe ) {
					return $this->format_recipe_summary( $recipe->to_array() );
				},
				$valid_recipes
			);

			return array(
				'success'      => true,
				'message'      => "Retrieved {$status} recipes successfully",
				'status'       => $status,
				'recipe_count' => count( $formatted_recipes ),
				'recipes'      => $formatted_recipes,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'recipe_status_query_failed', 'Failed to get recipes by status: ' . $e->getMessage() );
		}
	}

	/**
	 * Get recipes by type.
	 *
	 * @since 7.0.0
	 * @param string $type Recipe type (user, anonymous).
	 * @param int    $limit Optional. Maximum results to return.
	 * @return array|\WP_Error Array of recipes or error.
	 */
	public function get_recipes_by_type( string $type, int $limit = 0 ) {
		if ( ! in_array( $type, array( 'user', 'anonymous' ), true ) ) {
			return $this->error_response( 'recipe_invalid_type_filter', 'Invalid recipe type. Must be: user or anonymous', array( 'type' => $type ) );
		}

		$filters = array( 'type' => $type );
		if ( $limit > 0 ) {
			$filters['limit'] = $limit;
		}

		try {
			$recipes = $this->recipe_store->all( $filters );

			// Filter out null values before processing
			$valid_recipes = array_filter(
				$recipes,
				function ( $recipe ) {
					return $recipe instanceof Recipe;
				}
			);

			$formatted_recipes = array_map(
				function ( Recipe $recipe ) {
					return $this->format_recipe_summary( $recipe->to_array() );
				},
				$valid_recipes
			);

			return array(
				'success'      => true,
				'message'      => "Retrieved {$type} recipes successfully",
				'type'         => $type,
				'recipe_count' => count( $formatted_recipes ),
				'recipes'      => $formatted_recipes,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'recipe_type_query_failed', 'Failed to get recipes by type: ' . $e->getMessage() );
		}
	}

	/**
	 * Get recipe statistics.
	 *
	 * @since 7.0.0
	 * @return array|\WP_Error Recipe statistics or error.
	 */
	public function get_recipe_statistics() {
		try {
			$all_recipes = $this->recipe_store->all();

			$stats = array(
				'total'     => count( $all_recipes ),
				'by_status' => array(
					Recipe_Status::DRAFT   => 0,
					Recipe_Status::PUBLISH => 0,
					'trash'                => 0,
				),
				'by_type'   => array(
					'user'      => 0,
					'anonymous' => 0,
				),
			);

			foreach ( $all_recipes as $recipe ) {
				$recipe_data = $recipe->to_array();
				$status      = $recipe_data['status'] ?? Recipe_Status::DRAFT;
				$type        = $recipe_data['type'] ?? 'user';

				if ( isset( $stats['by_status'][ $status ] ) ) {
					++$stats['by_status'][ $status ];
				}
				if ( isset( $stats['by_type'][ $type ] ) ) {
					++$stats['by_type'][ $type ];
				}
			}

			return array(
				'success' => true,
				'message' => 'Recipe statistics retrieved successfully',
				'stats'   => $stats,
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'recipe_stats_failed', 'Failed to get recipe statistics: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if a recipe exists.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id Recipe ID to check.
	 * @return bool True if recipe exists, false otherwise.
	 */
	public function recipe_exists( int $recipe_id ): bool {
		$recipe = $this->recipe_store->get( $recipe_id );
		return null !== $recipe;
	}

	/**
	 * Search recipes using smart keyword matching.
	 *
	 * @since 7.0.0
	 * @param string $query Search query.
	 * @param array  $recipes All available recipes.
	 * @param int    $limit Maximum results to return.
	 * @return array Matching recipes with relevance scores.
	 */
	private function search_recipes_with_similarity( string $query, array $recipes, int $limit ): array {
		$query_lower = strtolower( $query );
		$query_words = array_filter(
			explode( ' ', $query_lower ),
			function ( $word ) {
				return strlen( trim( $word ) ) > 2; // Ignore short words
			}
		);

		$results = array();

		foreach ( $recipes as $recipe ) {
			$title_lower     = strtolower( $recipe['title'] ?? '' );
			$notes_lower     = strtolower( $recipe['notes'] ?? '' );
			$searchable_text = $title_lower . ' ' . $notes_lower;

			$score = 0;

			// Exact phrase match gets highest score
			if ( strpos( $searchable_text, $query_lower ) !== false ) {
				$score += 100;
			}

			// Title matches get higher score than notes
			if ( strpos( $title_lower, $query_lower ) !== false ) {
				$score += 75;
			}

			// Partial phrase matches
			if ( 0 === $score ) {
				$phrases = $this->extract_phrases( $query_lower );
				foreach ( $phrases as $phrase ) {
					if ( strpos( $searchable_text, $phrase ) !== false ) {
						$score += 50;
					}
					if ( strpos( $title_lower, $phrase ) !== false ) {
						$score += 35;
					}
				}
			}

			// Individual word matches
			foreach ( $query_words as $word ) {
				if ( strpos( $searchable_text, $word ) !== false ) {
					$score += 10;

					// Bonus for title matches
					if ( strpos( $title_lower, $word ) !== false ) {
						$score += 15;
					}
				}
			}

			// Only include results with some relevance
			if ( $score >= 10 ) {
				$results[] = array(
					'recipe'     => $this->format_recipe_summary( $recipe ),
					'similarity' => $score,
				);
			}
		}

		// Sort by relevance score (descending)
		usort(
			$results,
			function ( $a, $b ) {
				return $b['similarity'] <=> $a['similarity'];
			}
		);

		// Return top results (just the recipe data, not the scores)
		$top_results = array_slice( $results, 0, $limit );
		return array_map(
			function ( $result ) {
				return $result['recipe'];
			},
			$top_results
		);
	}

	/**
	 * Extract meaningful phrases from query.
	 *
	 * @since 7.0.0
	 * @param string $query Search query.
	 * @return array Array of phrases to search for.
	 */
	private function extract_phrases( string $query ): array {
		$phrases    = array();
		$words      = explode( ' ', $query );
		$word_count = count( $words );

		// Extract 2-3 word phrases
		for ( $i = 0; $i < $word_count - 1; $i++ ) {
			// 2-word phrases
			if ( $i < $word_count - 1 ) {
				$phrase = $words[ $i ] . ' ' . $words[ $i + 1 ];
				if ( strlen( $phrase ) > 5 ) {
					$phrases[] = $phrase;
				}
			}

			// 3-word phrases
			if ( $i < $word_count - 2 ) {
				$phrase = $words[ $i ] . ' ' . $words[ $i + 1 ] . ' ' . $words[ $i + 2 ];
				if ( strlen( $phrase ) > 8 ) {
					$phrases[] = $phrase;
				}
			}
		}

		return $phrases;
	}

	/**
	 * Format recipe summary for search results.
	 *
	 * @since 7.0.0
	 * @param array $recipe_data Recipe data array.
	 * @return array Formatted summary data.
	 */
	private function format_recipe_summary( array $recipe_data ): array {
		return array(
			'id'            => $recipe_data['recipe_id'] ?? $recipe_data['id'] ?? null,
			'title'         => $recipe_data['title'] ?? '',
			'status'        => $recipe_data['status'] ?? Recipe_Status::DRAFT,
			'type'          => $recipe_data['type'] ?? 'user',
			'notes'         => $recipe_data['notes'] ?? '',
			'trigger_count' => count( $recipe_data['triggers'] ?? array() ),
			'action_count'  => count( $recipe_data['actions'] ?? array() ),
		);
	}
}
