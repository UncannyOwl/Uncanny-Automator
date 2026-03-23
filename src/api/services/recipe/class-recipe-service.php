<?php
/**
 * Recipe Service (Facade)
 *
 * Facade that delegates to specialized services.
 * Maintains backward compatibility with existing code.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Recipe;

use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_Query_Service;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_Log_Service;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;

/**
 * Recipe_Service Class
 *
 * Facade that delegates to specialized CRUD, Query, and Log services.
 */
class Recipe_Service {

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var Recipe_Service|null
	 */
	private static $instance = null;

	/**
	 * Recipe store.
	 *
	 * @var WP_Recipe_Store
	 */
	private $recipe_store;

	/**
	 * CRUD service.
	 *
	 * @var Recipe_CRUD_Service
	 */
	private $crud_service;

	/**
	 * Query service.
	 *
	 * @var Recipe_Query_Service
	 */
	private $query_service;

	/**
	 * Log service.
	 *
	 * @var Recipe_Log_Service
	 */
	private $log_service;


	/**
	 * Constructor.
	 *
	 * Allows dependency injection for testing while maintaining
	 * lazy loading for production use.
	 *
	 * @param WP_Recipe_Store|null $recipe_store Optional recipe store for testing.
	 */
	public function __construct( $recipe_store = null ) {
		$this->recipe_store = $recipe_store;
	}


	/**
	 * Get service instance (singleton).
	 *
	 * @return Recipe_Service
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Get CRUD service (lazy-loaded).
	 *
	 * @return Recipe_CRUD_Service
	 */
	private function get_crud_service() {

		if ( null === $this->crud_service ) {
			$this->crud_service = new Recipe_CRUD_Service( $this->recipe_store );
		}

		return $this->crud_service;
	}


	/**
	 * Get Query service (lazy-loaded).
	 *
	 * @return Recipe_Query_Service
	 */
	private function get_query_service() {

		if ( null === $this->query_service ) {
			$this->query_service = new Recipe_Query_Service( $this->recipe_store );
		}

		return $this->query_service;
	}


	/**
	 * Get Log service (lazy-loaded).
	 *
	 * @return Recipe_Log_Service
	 */
	private function get_log_service() {

		if ( null === $this->log_service ) {
			$this->log_service = new Recipe_Log_Service( $this->recipe_store );
		}

		return $this->log_service;
	}


	// ========================================
	// CRUD Methods (delegate to CRUD service)
	// ========================================

	/**
	 * Create a new recipe.
	 *
	 * @param array $data Recipe data array.
	 * @return array|\WP_Error Recipe data on success, WP_Error on failure.
	 */
	public function create_recipe( array $data ) {

		return $this->get_crud_service()->create_recipe( $data );
	}


	/**
	 * Update an existing recipe.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param array $data      Recipe data to update.
	 * @return array|\WP_Error Updated recipe data on success, WP_Error on failure.
	 */
	public function update_recipe( int $recipe_id, array $data ) {

		return $this->get_crud_service()->update_recipe( $recipe_id, $data );
	}


	/**
	 * Delete a recipe.
	 *
	 * @param int  $recipe_id Recipe ID.
	 * @param bool $confirmed Confirmation flag for safety.
	 * @return array|\WP_Error Success confirmation or error.
	 */
	public function delete_recipe( int $recipe_id, bool $confirmed = false ) {

		return $this->get_crud_service()->delete_recipe( $recipe_id, $confirmed );
	}


	/**
	 * Duplicate a recipe.
	 *
	 * @param int    $source_recipe_id Source recipe ID.
	 * @param string $new_title        New title for duplicated recipe.
	 * @param string $new_status       Status for duplicated recipe.
	 * @return array|\WP_Error Duplication result or error.
	 */
	public function duplicate_recipe( int $source_recipe_id, string $new_title = '', string $new_status = Recipe_Status::DRAFT ) {

		return $this->get_crud_service()->duplicate_recipe( $source_recipe_id, $new_title, $new_status );
	}


	// =========================================
	// Query Methods (delegate to Query service)
	// =========================================

	/**
	 * Get a recipe by ID.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Recipe data on success, WP_Error on failure.
	 */
	public function get_recipe( int $recipe_id ) {

		return $this->get_query_service()->get_recipe( $recipe_id );
	}


	/**
	 * List recipes with basic information only (for efficient listing).
	 *
	 * @param array $filters Optional filters (status, type, title, limit, offset).
	 * @return array|\WP_Error Array of basic recipe info on success, WP_Error on failure.
	 */
	public function list_recipes( array $filters = array() ) {

		return $this->get_query_service()->list_recipes( $filters );
	}


	/**
	 * Get recipes by integration.
	 *
	 * @param string $integration         Integration code (e.g., 'WP', 'WC', 'LEARNDASH').
	 * @param array  $additional_filters Optional additional filters.
	 * @return array|\WP_Error Array of recipes or error.
	 */
	public function get_recipes_by_integration( string $integration, array $additional_filters = array() ) {

		return $this->get_query_service()->get_recipes_by_integration( $integration, $additional_filters );
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

		return $this->get_query_service()->get_recipes_by_meta( $meta_key, $meta_value, $meta_compare, $additional_filters );
	}


	/**
	 * Get recipes by field value.
	 *
	 * @param mixed $field_value        The field value to search for.
	 * @param array $additional_filters Additional filters to apply.
	 * @return array|\WP_Error Success response with recipes or error.
	 */
	public function get_recipes_from_field_value( $field_value, array $additional_filters = array() ) {

		return $this->get_query_service()->get_recipes_from_field_value( $field_value, $additional_filters );
	}


	/**
	 * Get recipe count by status or type.
	 *
	 * @param array $filters Filters to count by.
	 * @return int|\WP_Error Recipe count or error.
	 */
	public function get_recipe_count( array $filters = array() ) {

		return $this->get_query_service()->get_recipe_count( $filters );
	}


	// =======================================
	// Log Methods (delegate to Log service)
	// =======================================

	/**
	 * Get recipe execution logs using sophisticated logging system.
	 *
	 * @param array $filters Optional filters (recipe_id, user_id, completed, limit, offset, include_meta).
	 * @return array|\WP_Error Array of logs on success, WP_Error on failure.
	 */
	public function get_recipe_logs( array $filters = array() ) {

		return $this->get_log_service()->get_recipe_logs( $filters );
	}


	/**
	 * Get recipe execution logs by recipe ID.
	 *
	 * @param int   $recipe_id          Recipe ID to get logs for.
	 * @param array $additional_filters Additional filters to apply.
	 * @return array|\WP_Error Array of logs on success, WP_Error on failure.
	 */
	public function get_recipe_logs_by_recipe_id( int $recipe_id, array $additional_filters = array() ) {

		return $this->get_log_service()->get_recipe_logs_by_recipe_id( $recipe_id, $additional_filters );
	}


	/**
	 * Get the most recent recipe log.
	 *
	 * @param array $filters Optional filters to apply before getting most recent.
	 * @return array|\WP_Error Most recent log or error.
	 */
	public function get_most_recent_recipe_log( array $filters = array() ) {

		return $this->get_log_service()->get_most_recent_recipe_log( $filters );
	}


	/**
	 * Get detailed recipe log entry using sophisticated logging system.
	 *
	 * @param int  $recipe_id        Recipe ID.
	 * @param int  $run_number       Run number.
	 * @param int  $recipe_log_id    Recipe log ID.
	 * @param bool $enable_profiling Enable performance profiling.
	 * @return array|\WP_Error Detailed log data or error.
	 */
	public function get_log( int $recipe_id, int $run_number, int $recipe_log_id, bool $enable_profiling = false ) {

		return $this->get_log_service()->get_log( $recipe_id, $run_number, $recipe_log_id, $enable_profiling );
	}
}
