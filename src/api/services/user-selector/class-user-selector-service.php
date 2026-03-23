<?php
/**
 * User Selector Service.
 *
 * Application service that orchestrates user selector operations.
 * Bridges the gap between MCP tools and domain layer.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\User_Selector;

use Uncanny_Automator\Api\Components\User_Selector\User_Selector;
use Uncanny_Automator\Api\Components\User_Selector\User_Selector_Config;
use Uncanny_Automator\Api\Database\Interfaces\User_Selector_Store;
use Uncanny_Automator\Api\Database\Stores\WP_User_Selector_Store;

/**
 * User_Selector_Service Class.
 *
 * Provides application-level operations for user selector management.
 */
class User_Selector_Service {

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var User_Selector_Service|null
	 */
	private static $instance = null;

	/**
	 * User selector store.
	 *
	 * @var User_Selector_Store
	 */
	private $store;

	/**
	 * Constructor.
	 *
	 * Allows dependency injection for testing while maintaining
	 * lazy loading for production use.
	 *
	 * @param User_Selector_Store|null $store Optional store for testing.
	 */
	public function __construct( ?User_Selector_Store $store = null ) {
		$this->store = $store ?? new WP_User_Selector_Store();
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @return User_Selector_Service
	 */
	public static function instance(): User_Selector_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create or update a user selector for a recipe.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param array $data      User selector data.
	 * @return array|\WP_Error User selector data on success, WP_Error on failure.
	 */
	public function save_user_selector( int $recipe_id, array $data ) {
		try {
			// Validate recipe exists.
			$recipe = get_post( $recipe_id );
			if ( ! $recipe || 'uo-recipe' !== $recipe->post_type ) {
				return new \WP_Error(
					'invalid_recipe',
					sprintf( 'Recipe with ID %d not found', $recipe_id ),
					array( 'status' => 404 )
				);
			}

			// Build config from data.
			$config = $this->build_config_from_data( $recipe_id, $data );

			// Create domain object (validates invariants).
			$user_selector = new User_Selector( $config );

			// Persist.
			$saved = $this->store->save( $user_selector );

			return array(
				'message' => 'User selector saved successfully',
				'data'    => $saved->to_array(),
			);

		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error(
				'validation_error',
				$e->getMessage(),
				array( 'status' => 400 )
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'save_error',
				'Failed to save user selector: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get user selector for a recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error User selector data on success, WP_Error on failure.
	 */
	public function get_user_selector( int $recipe_id ) {
		try {
			// Validate recipe exists.
			$recipe = get_post( $recipe_id );
			if ( ! $recipe || 'uo-recipe' !== $recipe->post_type ) {
				return new \WP_Error(
					'invalid_recipe',
					sprintf( 'Recipe with ID %d not found', $recipe_id ),
					array( 'status' => 404 )
				);
			}

			$user_selector = $this->store->get_by_recipe_id( $recipe_id );

			if ( null === $user_selector ) {
				return array(
					'message' => 'No user selector configured for this recipe',
					'data'    => null,
				);
			}

			return array(
				'message' => 'User selector retrieved successfully',
				'data'    => $user_selector->to_array(),
			);

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'get_error',
				'Failed to get user selector: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Delete user selector for a recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Success message on success, WP_Error on failure.
	 */
	public function delete_user_selector( int $recipe_id ) {
		try {
			// Validate recipe exists.
			$recipe = get_post( $recipe_id );
			if ( ! $recipe || 'uo-recipe' !== $recipe->post_type ) {
				return new \WP_Error(
					'invalid_recipe',
					sprintf( 'Recipe with ID %d not found', $recipe_id ),
					array( 'status' => 404 )
				);
			}

			// Check if user selector exists.
			if ( ! $this->store->exists_for_recipe( $recipe_id ) ) {
				return new \WP_Error(
					'not_found',
					'No user selector configured for this recipe',
					array( 'status' => 404 )
				);
			}

			$this->store->delete_by_recipe_id( $recipe_id );

			return array(
				'message' => 'User selector deleted successfully',
			);

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'delete_error',
				'Failed to delete user selector: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Check if a recipe has a user selector configured.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return bool True if user selector exists.
	 */
	public function has_user_selector( int $recipe_id ): bool {
		return $this->store->exists_for_recipe( $recipe_id );
	}

	/**
	 * Build User_Selector_Config from raw data array.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param array $data      Raw data array.
	 * @return User_Selector_Config Configuration object.
	 */
	private function build_config_from_data( int $recipe_id, array $data ): User_Selector_Config {
		$config = new User_Selector_Config();
		$config->recipe_id( $recipe_id );

		if ( isset( $data['source'] ) ) {
			$config->source( $data['source'] );
		}

		if ( isset( $data['unique_field'] ) ) {
			$config->unique_field( $data['unique_field'] );
		}

		if ( isset( $data['unique_field_value'] ) ) {
			$config->unique_field_value( $data['unique_field_value'] );
		}

		if ( isset( $data['fallback'] ) ) {
			$config->fallback( $data['fallback'] );
		}

		if ( isset( $data['prioritized_field'] ) ) {
			$config->prioritized_field( $data['prioritized_field'] );
		}

		if ( isset( $data['user_data'] ) && is_array( $data['user_data'] ) ) {
			$config->user_data( $data['user_data'] );
		}

		return $config;
	}
}
