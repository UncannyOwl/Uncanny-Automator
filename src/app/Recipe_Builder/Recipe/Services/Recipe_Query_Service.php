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

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Utilities\Recipe_Formatter;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
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
	 * @var Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Recipe formatter.
	 *
	 * @var Recipe_Formatter
	 */
	private $formatter;

	/**
	 * Tree projections the integration filter reads.
	 *
	 * @var Recipe_Integrations_Service
	 */
	private $integrations;


	/**
	 * Constructor.
	 *
	 * @param Recipe_Store|null                $recipe_store Recipe storage implementation.
	 * @param Recipe_Formatter|null            $formatter    Recipe formatter.
	 * @param Recipe_Integrations_Service|null $integrations Tree projections for the integration filter.
	 */
	public function __construct( $recipe_store = null, $formatter = null, ?Recipe_Integrations_Service $integrations = null ) {

		$this->recipe_store = $recipe_store ?? new WP_Recipe_Store();
		$this->formatter    = $formatter ?? new Recipe_Formatter();
		$this->integrations = $integrations ?? new Recipe_Integrations_Service();
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
	 * @param array $filters Optional filters (status, type, title, integration, limit, offset).
	 * @return array|\WP_Error Array of basic recipe info on success, WP_Error on failure.
	 */
	public function list_recipes( array $filters = array() ) {

		try {

			$integration = (string) ( $filters['integration'] ?? '' );
			unset( $filters['integration'] );
			$limit  = isset( $filters['limit'] ) && is_numeric( $filters['limit'] ) && $filters['limit'] > 0 ? (int) $filters['limit'] : null;
			$offset = max( 0, (int) ( $filters['offset'] ?? 0 ) );
			if ( '' !== $integration ) {
				// Integration membership comes from the block tree. Page the matches,
				// otherwise unrelated recipes consume slots and hide later matches.
				unset( $filters['limit'], $filters['offset'] );
			}

			$recipes = $this->recipe_store->all( $filters );

			// Filter out any null values and convert to Recipe objects with domain validation
			$valid_recipes = array_filter(
				$recipes,
				function ( $recipe ) {
					return $recipe instanceof Recipe;
				}
			);

			if ( '' !== $integration ) {
				$valid_recipes = array_filter(
					$valid_recipes,
					function ( Recipe $recipe ) use ( $integration ) {
						$recipe_id = $recipe->get_recipe_id();
						if ( null === $recipe_id ) {
							return false;
						}
						try {
							return in_array( $integration, $this->integrations->integration_codes( (int) $recipe_id->get_value() ), true );
						} catch ( Legacy_Storage_Migration_Exception $e ) {
							return false;
						}
					}
				);
				$valid_recipes = array_slice( $valid_recipes, $offset, $limit );
			}

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
	 * Site-wide inventory for count questions, independent of list filters.
	 *
	 * @return array<string, int> Published and drafted total; trash is separate.
	 */
	public function get_recipe_totals(): array {
		$counts    = $this->recipe_store->count_by_status();
		$published = $counts['publish'] ?? 0;
		$drafted   = $counts['draft'] ?? 0;

		return array(
			'total_recipes'           => $published + $drafted,
			'total_published_recipes' => $published,
			'total_drafted_recipes'   => $drafted,
			'total_trashed_recipes'   => $counts['trash'] ?? 0,
		);
	}

	/**
	 * Whether a person has created a recipe: one they authored, published or
	 * draft. Trash and auto-drafts do not count. The one answer every feature
	 * asks; nothing else carries its own copy of this query.
	 *
	 * @since 8.0.0
	 *
	 * @param int $user_id             The person.
	 * @param int $excluding_recipe_id A recipe to leave out, such as one being created; 0 leaves out none.
	 *
	 * @return bool
	 */
	public function user_has_recipes( int $user_id, int $excluding_recipe_id = 0 ): bool {

		return $this->recipe_store->author_has_recipe(
			$user_id,
			array( Recipe_Status::PUBLISH, Recipe_Status::DRAFT ),
			0 === $excluding_recipe_id ? array() : array( $excluding_recipe_id )
		);
	}

	/**
	 * Whether a person has published a recipe they authored. Drafts do not
	 * count, so the draft "Add new" creates leaves a new person without one.
	 *
	 * @since 8.0.0
	 *
	 * @param int $user_id The person.
	 *
	 * @return bool
	 */
	public function user_has_published_recipe( int $user_id ): bool {

		return $this->recipe_store->author_has_recipe( $user_id, array( Recipe_Status::PUBLISH ) );
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
