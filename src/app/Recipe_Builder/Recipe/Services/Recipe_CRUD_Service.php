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

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe_Config;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Trigger_Logic;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Throttle;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Utilities\Recipe_Validator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Utilities\Recipe_Formatter;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
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
	 * @var Recipe_Store
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
	 * The rules a recipe must pass to go live.
	 *
	 * @var Recipe_Violation_Service
	 */
	private $violation_service;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param Recipe_Store|null       $recipe_store Recipe storage.
	 * @param Recipe_Validator|null   $validator    Recipe validator.
	 * @param Recipe_Formatter|null   $formatter    Recipe formatter.
	 * @param Recipe_Violation_Service|null $violation_service The rules a recipe must pass to go live.
	 */
	public function __construct( $recipe_store = null, $validator = null, $formatter = null, ?Recipe_Violation_Service $violation_service = null ) {
		$this->recipe_store      = $recipe_store ?? new WP_Recipe_Store();
		$this->validator         = $validator ?? new Recipe_Validator();
		$this->formatter         = $formatter ?? new Recipe_Formatter();
		$this->violation_service = $violation_service ?? Recipe_Violation_Service::instance();
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
		return Recipe_Change_Events::operation(
			function () use ( $data ) {

				try {
					// Variable declarations
					$title         = trim( $data['title'] ?? '' );
					$status        = $data['status'] ?? Recipe_Status::DRAFT;
					$type          = $data['type'] ?? 'user';
					$trigger_logic = $data['trigger_logic'] ?? 'all';
					$notes         = $data['notes'] ?? '';
					$throttle      = $data['throttle'] ?? Recipe_Throttle::disabled_shape();

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

					$recipe = new Recipe( $recipe_config );
				} catch ( \Exception $e ) {
					return $this->formatter->error_response( 'recipe_create_failed', 'Failed to create recipe: ' . $e->getMessage() );
				}

				try {
					$stored_recipe = $this->recipe_store->save( $recipe );
					$this->complete_recipe_write( Recipe_Change_Operation::CREATED, (int) $stored_recipe->get_recipe_id()->get_value() );

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
					return $this->formatter->error_response(
						'recipe_create_failed',
						'The recipe could not be confirmed. Check recipes on the site before retrying.',
						array(
							'status'  => 500,
							'outcome' => 'unconfirmed',
						)
					);
				}
			}
		);
	}


	/**
	 * Update an existing recipe.
	 *
	 * @param int|string $recipe_id Recipe ID.
	 * @param array      $data      Recipe data to update.
	 * @return array|\WP_Error Updated recipe data on success, WP_Error on failure.
	 */
	public function update_recipe( $recipe_id, array $data ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $data ) {

				// Get existing recipe
				$existing_recipe = $this->recipe_store->get( $this->coerce_recipe_id( $recipe_id ) );

				if ( null === $existing_recipe ) {
					return $this->formatter->error_response( 'recipe_not_found', 'Update recipe failed: Recipe not found with ID: ' . $recipe_id, array( 'recipe_id' => $recipe_id ) );
				}

				// Trashed recipes may only be untrashed via a status change. Reject every
				// other mutation until the recipe is restored. The `id` key is transport
				// plumbing, not a user-facing field, so ignore it when deciding whether
				// this is a pure status change.
				if ( ! $existing_recipe->get_recipe_status()->is_writable() ) {
					$mutating_keys = array_diff( array_keys( $data ), array( 'id' ) );
					$is_untrash    = array( 'status' ) === $mutating_keys
						&& in_array( $data['status'] ?? null, array( Recipe_Status::DRAFT, Recipe_Status::PUBLISH ), true );
					if ( ! $is_untrash ) {
						return $this->formatter->error_response(
							'recipe_trashed',
							sprintf( 'Recipe %1$d is trashed. Restore it by setting status to "%2$s" before making other changes.', $this->coerce_recipe_id( $recipe_id ), Recipe_Status::DRAFT ),
							array( 'recipe_id' => $recipe_id )
						);
					}
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

				// Going live passes the builder switch's rules, before anything is written.
				$goes_live = Recipe_Status::PUBLISH === ( $data['status'] ?? null )
					&& ! Recipe_Status::is_recipe_on( $existing_recipe->get_recipe_status()->get_value() );
				if ( $goes_live ) {
					$rejection = Recipe_Violation_Service::rejection_error(
						$this->violation_service->evaluate_recipe_publish( $this->coerce_recipe_id( $recipe_id ) ),
						esc_html_x( 'This recipe can’t be set live yet.', 'Recipe CRUD error', 'uncanny-automator' ) . ' ' . esc_html_x( 'Nothing in this request was saved.', 'Recipe CRUD error', 'uncanny-automator' )
					);
					if ( $rejection instanceof WP_Error ) {
						return $rejection;
					}
				}

				$existing_data = $this->formatter->format_recipe_response( $existing_recipe->to_array() );

				// Merge with updates (preserving ID)
				$updated_data       = array_merge( $existing_data, $data );
				$updated_data['id'] = $recipe_id;

				// Partial edits must preserve the saved behavior, including nested settings.
				$existing_logic                = $existing_recipe->get_recipe_trigger_logic();
				$updated_data['trigger_logic'] = $data['trigger_logic'] ?? ( $existing_logic ? $existing_logic->get_value() : Recipe_Trigger_Logic::LOGIC_ALL );
				if ( isset( $data['throttle'] ) && is_array( $data['throttle'] ) ) {
					$updated_data['throttle'] = array_merge( $existing_recipe->get_recipe_throttle()->to_array(), $data['throttle'] );
				}

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

				$recipe = new Recipe( $config );

				try {
					$stored_recipe = $this->recipe_store->save( $recipe );

					$this->complete_recipe_write( Recipe_Change_Operation::UPDATED, $this->coerce_recipe_id( $recipe_id ) );

					return $stored_recipe->to_array();
				} catch ( \Exception $e ) {
					return $this->formatter->error_response(
						'recipe_update_unconfirmed',
						'The recipe update could not be confirmed. Check the recipe on the site before retrying.',
						array(
							'status'    => 500,
							'outcome'   => 'unconfirmed',
							'recipe_id' => $this->coerce_recipe_id( $recipe_id ),
						)
					);
				}
			}
		);
	}

	/**
	 * Delete a recipe.
	 *
	 * @param int|string $recipe_id Recipe ID.
	 * @param bool       $confirmed Confirmation flag for safety.
	 * @return array|\WP_Error Success confirmation or error.
	 */
	public function delete_recipe( $recipe_id, bool $confirmed = false ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $confirmed ) {

				if ( ! $confirmed ) {
					return $this->formatter->error_response( 'recipe_confirmation_required', 'You must confirm deletion by setting $confirmed parameter to true' );
				}

				try {
					// Get recipe first to ensure it exists and get data for response
					$recipe = $this->recipe_store->get( $this->coerce_recipe_id( $recipe_id ) );

					if ( null === $recipe ) {
						return $this->formatter->error_response( 'recipe_not_found', 'Delete recipe failed: Recipe not found with ID: ' . $recipe_id );
					}

					// Read the response fields before the delete, from the recipe's own getters.
					$deleted_id = $recipe->get_recipe_id();
					$title      = $recipe->get_recipe_title()->get_value();
					$status     = $recipe->get_recipe_status()->get_value();
					$type       = $recipe->get_recipe_type()->get_value();

					// Delete the recipe
					$this->recipe_store->delete( $recipe );
					$this->complete_recipe_write( Recipe_Change_Operation::DELETED, $this->coerce_recipe_id( $recipe_id ) );

					return array(
						'success'           => true,
						'message'           => 'Recipe successfully deleted',
						'deleted_recipe_id' => null === $deleted_id ? $recipe_id : $deleted_id->get_value(),
						'title'             => $title,
						'status'            => $status,
						'type'              => $type,
					);

				} catch ( \Exception $e ) {
					return $this->formatter->error_response( 'recipe_delete_failed', 'Failed to delete recipe: ' . $e->getMessage() );
				}
			}
		);
	}

	/**
	 * Fire the recipe_changed event for a recipe write.
	 *
	 * @param string $operation A Recipe_Change_Operation value.
	 * @param int    $recipe_id Id of the recipe written; no event without one.
	 *
	 * @return void
	 */
	private function complete_recipe_write( string $operation, int $recipe_id ): void {
		if ( $recipe_id <= 0 ) {
			return;
		}

		Recipe_Change_Events::changed( new Recipe_Changed( $operation, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );
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
