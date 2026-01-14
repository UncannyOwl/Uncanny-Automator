<?php
/**
 * Recipe CRUD Service
 *
 * Handles create, update, delete, and duplicate operations for recipes.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Recipe\Services;

use Uncanny_Automator\Api\Components\Recipe\Recipe;
use Uncanny_Automator\Api\Components\Recipe\Recipe_Config;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Trigger_Logic;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Validator;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Formatter;
use Uncanny_Automator\Api\Services\Closure\Services\Closure_Service;
use WP_Error;

/**
 * Recipe_CRUD_Service Class
 *
 * Handles recipe creation, updates, deletion, and duplication.
 */
class Recipe_CRUD_Service {

	/**
	 * Recipe store instance.
	 *
	 * @var WP_Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Recipe validator.
	 *
	 * @var Recipe_Validator
	 */
	private $validator;

	/**
	 * Recipe formatter.
	 *
	 * @var Recipe_Formatter
	 */
	private $formatter;

	/**
	 * Closure service instance.
	 *
	 * @var Closure_Service
	 */
	private $closure_service;


	/**
	 * Constructor.
	 *
	 * @param WP_Recipe_Store|null  $recipe_store     Recipe storage implementation.
	 * @param Recipe_Validator|null $validator        Recipe validator.
	 * @param Recipe_Formatter|null $formatter        Recipe formatter.
	 * @param Closure_Service|null  $closure_service  Closure service for handling redirects.
	 */
	public function __construct( $recipe_store = null, $validator = null, $formatter = null, $closure_service = null ) {

		$this->recipe_store    = $recipe_store ?? new WP_Recipe_Store();
		$this->validator       = $validator ?? new Recipe_Validator();
		$this->formatter       = $formatter ?? new Recipe_Formatter();
		$this->closure_service = $closure_service ?? Closure_Service::instance();
	}

	/**
	 * Coerce recipe ID to integer type.
	 *
	 * Type coercion helper for application layer boundaries.
	 * External inputs (HTTP, JSON, tests) provide strings, but domain stores require strict int types.
	 *
	 * @since 7.0.0
	 * @param int|string $recipe_id Recipe ID to coerce.
	 * @return int Coerced integer recipe ID.
	 */
	private function coerce_recipe_id( $recipe_id ): int {
		return (int) $recipe_id;
	}


	/**
	 * Create a new recipe.
	 *
	 * @param array $data Recipe data array.
	 * @return array|\WP_Error Recipe data on success, WP_Error on failure.
	 */
	public function create_recipe( array $data ) {

		try {
			// Variable declarations
			$title         = trim( $data['title'] ?? '' );
			$status        = $data['status'] ?? Recipe_Status::DRAFT;
			$type          = $data['type'] ?? 'user';
			$trigger_logic = $data['trigger_logic'] ?? 'all';
			$notes         = $data['notes'] ?? '';
			$redirect_url  = $data['redirect_url'] ?? null;
			$throttle      = $data['throttle'] ?? array(
				'enabled'  => false,
				'duration' => 1,
				'unit'     => 'hours',
				'scope'    => 'recipe',
			);

			// Validate and sanitize inputs
			$title = $this->validator->validate_title( $title );
			$notes = $this->validator->validate_notes( $notes );

			// Create recipe config with basic properties
			$recipe_config = ( new Recipe_Config() )
				->title( $title )
				->status( $status )
				->user_type( $type )
				->trigger_logic( $trigger_logic )
				->notes( $notes )
				->throttle( $throttle );

			// Add type-specific execution limits with validation
			if ( 'user' === $type ) {
				$recipe_config->times_per_user( $data['times_per_user'] ?? null );
				$recipe_config->total_times( $data['total_times'] ?? null );
			}

			// Add type-specific execution limits with validation
			if ( 'anonymous' === $type ) {
				$recipe_config->total_times( $data['total_times'] ?? null );
			}

			// Create and save recipe
			$recipe        = new Recipe( $recipe_config );
			$stored_recipe = $this->recipe_store->save( $recipe );

			// Handle redirect closure if URL provided
			if ( $redirect_url ) {
				$this->closure_service->add_closure( $stored_recipe->get_recipe_id()->get_value(), $redirect_url );
			}

			return array(
				'success'   => true,
				'recipe_id' => $stored_recipe->get_recipe_id()->get_value(),
				'title'     => $stored_recipe->get_recipe_title()->get_value(),
				'status'    => $stored_recipe->get_recipe_status()->get_value(),
				'type'      => $stored_recipe->get_recipe_type()->get_value(),
				'message'   => sprintf(
					'Successfully created recipe "%s" (ID: %d, Status: %s, Type: %s)',
					$stored_recipe->get_recipe_title()->get_value(),
					$stored_recipe->get_recipe_id()->get_value(),
					$stored_recipe->get_recipe_status()->get_value(),
					$stored_recipe->get_recipe_type()->get_value()
				),
				'recipe'    => $this->formatter->format_recipe_response( $stored_recipe->to_array() ),
			);

		} catch ( \Exception $e ) {
			return $this->formatter->error_response( 'recipe_create_failed', 'Failed to create recipe: ' . $e->getMessage() );
		}
	}


	/**
	 * Update an existing recipe.
	 *
	 * @param int|string $recipe_id Recipe ID.
	 * @param array      $data      Recipe data to update.
	 * @return array|\WP_Error Updated recipe data on success, WP_Error on failure.
	 */
	public function update_recipe( $recipe_id, array $data ) {

		// Get existing recipe
		$existing_recipe = $this->recipe_store->get( $this->coerce_recipe_id( $recipe_id ) );

		if ( null === $existing_recipe ) {
			return $this->formatter->error_response( 'recipe_not_found', 'Update recipe failed: Recipe not found with ID: ' . $recipe_id, array( 'recipe_id' => $recipe_id ) );
		}

		// Business rule: Recipe type cannot be changed after creation.
		if ( isset( $data['type'] ) && $data['type'] !== $existing_recipe->get_recipe_type()->get_value() ) {
			return $this->formatter->error_response(
				'recipe_type_immutable',
				sprintf(
					'Recipe type cannot be changed. Current type: "%s". To change recipe type, create a new recipe.',
					$existing_recipe->get_recipe_type()->get_value()
				),
				array(
					'current_type'   => $existing_recipe->get_recipe_type()->get_value(),
					'attempted_type' => $data['type'],
				)
			);
		}

		$existing_data = $this->formatter->format_recipe_response( $existing_recipe->to_array() );

		// Merge with updates (preserving ID)
		$updated_data       = array_merge( $existing_data, $data );
		$updated_data['id'] = $recipe_id;

		// Sanitize inputs
		$sanitized    = $this->validator->sanitize_recipe_data( $updated_data );
		$updated_data = array_merge( $updated_data, $sanitized );

		$config = ( new Recipe_Config() )
			->id( $updated_data['id'] )
			->title( $updated_data['title'] )
			->status( $updated_data['status'] )
			->user_type( $updated_data['type'] )
			->trigger_logic( $updated_data['trigger_logic'] ?? Recipe_Trigger_Logic::LOGIC_ALL )
			->notes( $updated_data['notes'] ?? '' );

		if ( ! empty( $existing_recipe->get_recipe_action_conditions()->to_array() ) ) {
			$config->action_conditions( $existing_recipe->get_recipe_action_conditions()->to_array() );
		}

		// Add type-specific fields if present
		if ( 'user' === $updated_data['type'] ) {
			$config->times_per_user( $updated_data['times_per_user'] );
			$config->total_times( $updated_data['total_times'] );
		}

		if ( 'anonymous' === $updated_data['type'] ) {
			$config->total_times( $updated_data['total_times'] );
		}

		if ( isset( $updated_data['throttle'] ) ) {
			$config->throttle( $updated_data['throttle'] );
		}

		// Handle redirect closure upsert: delete old, add new if provided
		$redirect_url = ! empty( $data['redirect_url'] ) ? $data['redirect_url'] : null;
		if ( null !== $redirect_url ) {
			// Delete existing closures first
			$this->closure_service->delete_recipe_closures( $recipe_id );
			// Add new closure with updated URL
			$this->closure_service->add_closure( $recipe_id, $redirect_url );
		} elseif ( isset( $data['redirect_url'] ) && '' === $data['redirect_url'] ) {
			// If explicitly set to empty string, delete closures
			$this->closure_service->delete_recipe_closures( $recipe_id );
		}

		$recipe = new Recipe( $config );

		// Save recipe.
		$stored_recipe = $this->recipe_store->save( $recipe );

		// Return recipe information using domain object
		return $stored_recipe->to_array();
	}


	/**
	 * Delete a recipe.
	 *
	 * @param int|string $recipe_id Recipe ID.
	 * @param bool       $confirmed Confirmation flag for safety.
	 * @return array|\WP_Error Success confirmation or error.
	 */
	public function delete_recipe( $recipe_id, bool $confirmed = false ) {

		if ( ! $confirmed ) {
			return $this->formatter->error_response( 'recipe_confirmation_required', 'You must confirm deletion by setting $confirmed parameter to true' );
		}

		try {
			// Get recipe first to ensure it exists and get data for response
			$recipe = $this->recipe_store->get( $this->coerce_recipe_id( $recipe_id ) );

			if ( null === $recipe ) {
				return $this->formatter->error_response( 'recipe_not_found', 'Delete recipe failed: Recipe not found with ID: ' . $recipe_id );
			}

			$recipe_data = $recipe->to_array();

			// Delete any associated closures
			$this->closure_service->delete_recipe_closures( $recipe_id );

			// Delete the recipe
			$this->recipe_store->delete( $recipe );

			return array(
				'success'           => true,
				'message'           => 'Recipe successfully deleted',
				'deleted_recipe_id' => $recipe_data['recipe_id'] ?? $recipe_id,
				'title'             => $recipe_data['title'] ?? '',
				'status'            => $recipe_data['status'] ?? '',
				'type'              => $recipe_data['type'] ?? '',
			);

		} catch ( \Exception $e ) {
			return $this->formatter->error_response( 'recipe_delete_failed', 'Failed to delete recipe: ' . $e->getMessage() );
		}
	}


	/**
	 * Duplicate a recipe.
	 *
	 * @param int|string $source_recipe_id Source recipe ID.
	 * @param string     $new_title        New title for duplicated recipe.
	 * @param string     $new_status       Status for duplicated recipe.
	 * @return array|\WP_Error Duplication result or error.
	 */
	public function duplicate_recipe( $source_recipe_id, string $new_title = '', string $new_status = Recipe_Status::DRAFT ) {

		// Get source recipe
		$source_recipe = $this->recipe_store->get( $this->coerce_recipe_id( $source_recipe_id ) );

		if ( null === $source_recipe ) {
			return $this->formatter->error_response( 'recipe_not_found', 'Source recipe not found with ID: ' . $source_recipe_id );
		}

		$source_recipe_data = $this->formatter->format_recipe_response( $source_recipe->to_array() );

		// Prepare data for new recipe
		$duplicate_data = $source_recipe_data;
		unset( $duplicate_data['id'] );
		$duplicate_data['title']  = $new_title ? $new_title : $source_recipe_data['title'] . ' (Copy)';
		$duplicate_data['status'] = $new_status;

		return $this->create_recipe( $duplicate_data );
	}
}
