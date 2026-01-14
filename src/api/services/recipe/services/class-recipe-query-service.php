<?php
/**
 * Recipe Query Service
 *
 * Handles all recipe query and retrieval operations.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Recipe\Services;

use Uncanny_Automator\Api\Components\Recipe\Recipe;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Formatter;
use WP_Error;

/**
 * Recipe_Query_Service Class
 *
 * Handles recipe retrieval and filtering operations.
 */
class Recipe_Query_Service {

	/**
	 * Recipe store instance.
	 *
	 * @var WP_Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Recipe formatter.
	 *
	 * @var Recipe_Formatter
	 */
	private $formatter;


	/**
	 * Constructor.
	 *
	 * @param WP_Recipe_Store|null  $recipe_store Recipe storage implementation.
	 * @param Recipe_Formatter|null $formatter    Recipe formatter.
	 */
	public function __construct( $recipe_store = null, $formatter = null ) {

		$this->recipe_store = $recipe_store ?? new WP_Recipe_Store();
		$this->formatter    = $formatter ?? new Recipe_Formatter();
	}


	/**
	 * Get a recipe by ID.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Recipe data on success, WP_Error on failure.
	 */
	public function get_recipe( int $recipe_id ) {

		$recipe = $this->recipe_store->get( $recipe_id );

		if ( null === $recipe ) {
			return $this->formatter->error_response( 'recipe_not_found', 'Get recipe failed: Recipe not found with ID: ' . $recipe_id, array( 'recipe_id' => $recipe_id ) );
		}

		return array(
			'success' => true,
			'recipe'  => $this->formatter->format_recipe_response( $recipe->to_array() ),
		);
	}


	/**
	 * List recipes with basic information only (for efficient listing).
	 *
	 * @param array $filters Optional filters (status, type, title, limit, offset).
	 * @return array|\WP_Error Array of basic recipe info on success, WP_Error on failure.
	 */
	public function list_recipes( array $filters = array() ) {

		try {

			$recipes = $this->recipe_store->all( $filters );

			// Filter out any null values and convert to Recipe objects with domain validation
			$valid_recipes = array_filter(
				$recipes,
				function ( $recipe ) {
					return $recipe instanceof Recipe;
				}
			);

			// Use Recipe domain objects to get basic information - handles validation automatically
			$basic_recipes = array_map(
				function ( Recipe $recipe ) {
					return $recipe->to_basic_array();
				},
				$valid_recipes
			);

			return array(
				'success'      => true,
				'message'      => 'Recipes retrieved successfully',
				'recipe_count' => count( $basic_recipes ),
				'recipes'      => $basic_recipes,
			);

		} catch ( \Exception $e ) {
			return $this->formatter->error_response( 'recipe_list_failed', 'Failed to list recipes: ' . $e->getMessage() );
		}
	}


	/**
	 * Get recipes by integration.
	 *
	 * @param string $integration         Integration code (e.g., 'WP', 'WC', 'LEARNDASH').
	 * @param array  $additional_filters Optional additional filters.
	 * @return array|\WP_Error Array of recipes or error.
	 */
	public function get_recipes_by_integration( string $integration, array $additional_filters = array() ) {

		try {
			$filters = array_merge(
				$additional_filters,
				array( 'integration' => $integration )
			);

			$result = $this->list_recipes( $filters );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'success'      => true,
				'message'      => sprintf( 'Found %d recipes using integration "%s"', $result['recipe_count'], $integration ),
				'integration'  => $integration,
				'recipe_count' => $result['recipe_count'],
				'recipes'      => $result['recipes'],
			);

		} catch ( \Exception $e ) {
			return $this->formatter->error_response(
				'recipe_integration_query_failed',
				'Failed to query recipes by integration: ' . $e->getMessage()
			);
		}
	}


	/**
	 * Get recipes by meta key and value.
	 *
	 * @param string $meta_key           Meta key to search for.
	 * @param mixed  $meta_value         Meta value to match.
	 * @param string $meta_compare       Comparison operator (=, !=, LIKE, etc.). Default '='.
	 * @param array  $additional_filters Optional additional filters.
	 * @return array|\WP_Error Array of recipes or error.
	 */
	public function get_recipes_by_meta( string $meta_key, $meta_value, string $meta_compare = '=', array $additional_filters = array() ) {

		try {
			$filters = array_merge(
				$additional_filters,
				array(
					'meta_key'     => $meta_key,
					'meta_value'   => $meta_value,
					'meta_compare' => $meta_compare,
				)
			);

			$result = $this->list_recipes( $filters );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'success'      => true,
				'message'      => sprintf( 'Found %d recipes with meta "%s" %s "%s"', $result['recipe_count'], $meta_key, $meta_compare, $meta_value ),
				'meta_query'   => array(
					'key'     => $meta_key,
					'value'   => $meta_value,
					'compare' => $meta_compare,
				),
				'recipe_count' => $result['recipe_count'],
				'recipes'      => $result['recipes'],
			);

		} catch ( \Exception $e ) {
			return $this->formatter->error_response( 'recipe_meta_query_failed', 'Failed to query recipes by meta: ' . $e->getMessage() );
		}
	}


	/**
	 * Get recipes by field value.
	 *
	 * Finds recipes where triggers/actions have specific field values,
	 * including both exact matches and "any" (-1) values.
	 *
	 * @param mixed $field_value        The field value to search for.
	 * @param array $additional_filters Additional filters to apply.
	 * @return array|\WP_Error Success response with recipes or error.
	 */
	public function get_recipes_from_field_value( $field_value, array $additional_filters = array() ) {

		try {
			// Get recipe IDs from database layer
			$recipe_ids = $this->recipe_store->get_recipe_ids_from_field_value( $field_value );

			if ( empty( $recipe_ids ) ) {
				return array(
					'success'      => true,
					'message'      => sprintf( 'No recipes found with field value "%s"', $field_value ),
					'field_value'  => $field_value,
					'recipe_count' => 0,
					'recipes'      => array(),
				);
			}

			// Convert recipe IDs to recipe objects with additional filtering
			$recipes = array();

			foreach ( $recipe_ids as $recipe_id ) {
				$recipe = $this->recipe_store->get( $recipe_id );

				if ( null !== $recipe ) {
					$recipes[] = $recipe;
				}
			}

			// Apply additional filters if provided
			if ( ! empty( $additional_filters ) ) {
				$filtered_recipes    = $this->recipe_store->all( $additional_filters );
				$filtered_recipe_ids = array_map(
					function ( $recipe ) {
						return $recipe->get_recipe_id()->get_value();
					},
					$filtered_recipes
				);

				// Keep only recipes that match both field value and additional filters
				$recipes = array_filter(
					$recipes,
					function ( $recipe ) use ( $filtered_recipe_ids ) {
						return in_array( $recipe->get_recipe_id()->get_value(), $filtered_recipe_ids, true );
					}
				);
			}

			return array(
				'success'      => true,
				'message'      => sprintf( 'Found %d recipes with field value "%s"', count( $recipes ), $field_value ),
				'field_value'  => $field_value,
				'recipe_count' => count( $recipes ),
				'recipes'      => array_map(
					function ( $recipe ) {
						return $this->formatter->format_recipe_response( $recipe->to_array() );
					},
					$recipes
				),
			);

		} catch ( \Exception $e ) {
			return $this->formatter->error_response( 'recipe_field_value_query_failed', 'Failed to query recipes by field value: ' . $e->getMessage() );
		}
	}


	/**
	 * Get recipe count by status or type.
	 *
	 * @param array $filters Filters to count by.
	 * @return int|\WP_Error Recipe count or error.
	 */
	public function get_recipe_count( array $filters = array() ) {

		$recipes = $this->list_recipes( $filters );

		if ( is_wp_error( $recipes ) ) {
			return $recipes;
		}

		return $recipes['recipe_count'];
	}
}
