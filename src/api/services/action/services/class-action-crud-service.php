<?php
/**
 * Action Instance Service
 *
 * Core business logic service for recipe-action instance operations.
 * Manages CRUD operations for action instances within recipes.
 * Single source of truth for action management used by both MCP tools and functions.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Services;

use Uncanny_Automator\Api\Database\Database;
use Uncanny_Automator\Api\Database\Stores\WP_Action_Store;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Components\Action\Action;
use Uncanny_Automator\Api\Components\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Code;
use Uncanny_Automator\Api\Services\Action\Utilities\Action_Builder;
use Uncanny_Automator\Api\Services\Action\Utilities\Action_Validator;
use Uncanny_Automator\Api\Services\Action\Utilities\Action_Response_Formatter;
use Uncanny_Automator\Api\Services\Action\Utilities\Async_Config_Converter;
use Uncanny_Automator\Api\Services\Action\Utilities\Action_Token_Dependency_Tracker;
use WP_Error;

/**
 * Action Instance Service Class
 *
 * Handles CRUD operations for action instances within recipes.
 * Manages recipe-action operations with clean OOP architecture.
 */
class Action_CRUD_Service {

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var Action_CRUD_Service|null
	 */
	private static ?Action_CRUD_Service $instance = null;

	/**
	 * Action store instance.
	 *
	 * @var WP_Action_Store
	 */
	private WP_Action_Store $action_store;

	/**
	 * Recipe store instance.
	 *
	 * @var WP_Recipe_Store
	 */
	private WP_Recipe_Store $recipe_store;

	/**
	 * Action registry instance.
	 *
	 * @var WP_Action_Registry
	 */
	private WP_Action_Registry $action_registry;

	/**
	 * Action instance builder helper.
	 *
	 * @var Action_Builder
	 */
	private Action_Builder $instance_builder;

	/**
	 * Action config validator helper.
	 *
	 * @var Action_Validator
	 */
	private Action_Validator $validator;

	/**
	 * Action response formatter helper.
	 *
	 * @var Action_Response_Formatter
	 */
	private Action_Response_Formatter $formatter;

	/**
	 * Async config converter helper.
	 *
	 * @var Async_Config_Converter
	 */
	private Async_Config_Converter $async_converter;

	/**
	 * Action token dependency tracker.
	 *
	 * @var Action_Token_Dependency_Tracker
	 */
	private Action_Token_Dependency_Tracker $dependency_tracker;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	private function __construct(
		?WP_Action_Store $action_store = null,
		?WP_Recipe_Store $recipe_store = null,
		?WP_Action_Registry $action_registry = null,
		?Action_Builder $instance_builder = null,
		?Action_Validator $validator = null,
		?Action_Response_Formatter $formatter = null,
		?Async_Config_Converter $async_converter = null,
		?Action_Token_Dependency_Tracker $dependency_tracker = null
	) {
		$this->action_store    = $action_store ?? Database::get_action_store();
		$this->recipe_store    = $recipe_store ?? Database::get_recipe_store();
		$this->action_registry = $action_registry ?? new WP_Action_Registry();

		$this->async_converter    = $async_converter ?? new Async_Config_Converter();
		$this->instance_builder   = $instance_builder ?? new Action_Builder( $this->async_converter );
		$this->validator          = $validator ?? new Action_Validator();
		$this->formatter          = $formatter ?? new Action_Response_Formatter();
		$this->dependency_tracker = $dependency_tracker ?? new Action_Token_Dependency_Tracker();
	}

	/**
	 * Coerce action ID to integer type.
	 *
	 * Type coercion helper for application layer boundaries.
	 * External inputs (HTTP, JSON, tests) provide strings, but domain stores require strict int types.
	 *
	 * @since 7.0.0
	 * @param int|string $action_id Action ID to coerce.
	 * @return int Coerced integer action ID.
	 */
	private function coerce_action_id( $action_id ): int {
		return (int) $action_id;
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @since 7.0.0
	 * @return Action_CRUD_Service
	 */
	public static function instance(): Action_CRUD_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create a service instance with explicit dependencies.
	 *
	 * Primarily useful for testing where infrastructure services
	 * such as database stores should be replaced with mocks.
	 *
	 * @since 7.0.0
	 *
	 * @param WP_Action_Store|null               $action_store       Custom action store.
	 * @param WP_Recipe_Store|null               $recipe_store       Custom recipe store.
	 * @param WP_Action_Registry|null            $action_registry    Custom action registry.
	 * @param Action_Builder|null                $instance_builder   Custom action builder.
	 * @param Action_Validator|null              $validator          Custom validator.
	 * @param Action_Response_Formatter|null     $formatter          Custom formatter.
	 * @param Async_Config_Converter|null        $async_converter    Custom async converter.
	 * @param Action_Token_Dependency_Tracker|null $dependency_tracker Custom dependency tracker.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?WP_Action_Store $action_store = null,
		?WP_Recipe_Store $recipe_store = null,
		?WP_Action_Registry $action_registry = null,
		?Action_Builder $instance_builder = null,
		?Action_Validator $validator = null,
		?Action_Response_Formatter $formatter = null,
		?Async_Config_Converter $async_converter = null,
		?Action_Token_Dependency_Tracker $dependency_tracker = null
	): self {
		return new self(
			$action_store,
			$recipe_store,
			$action_registry,
			$instance_builder,
			$validator,
			$formatter,
			$async_converter,
			$dependency_tracker
		);
	}

	/**
	 * Validate recipe exists.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return mixed Recipe object or null if not found.
	 */
	public function validate_recipe_exists( int $recipe_id ) {
		return $this->recipe_store->get( $recipe_id );
	}

	/**
	 * Validate action code and get definition from registry.
	 *
	 * @param string $action_code Action code to validate.
	 * @return array|\WP_Error Action definition or WP_Error if invalid.
	 */
	public function validate_action_code_and_get_definition( string $action_code ) {
		try {
			$action_code_vo    = new Action_Code( $action_code );
			$action_definition = $this->action_registry->get_action_definition( $action_code_vo );

			if ( ! $action_definition ) {
				return new WP_Error(
					'action_not_found',
					sprintf(
						/* translators: %s Action code. */
						esc_html_x( "Action '%s' not found in registry. Use the explorer tool to discover available actions.", 'Action CRUD error', 'uncanny-automator' ),
						$action_code
					)
				);
			}

			return $action_definition;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_action_code',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Invalid action code: %s', 'Action CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Build add action success response.
	 *
	 * @param Action $action Action instance.
	 * @return array Success response.
	 */
	public function build_add_action_response( Action $action ): array {
		return array(
			'success'   => true,
			'message'   => esc_html_x( 'Action successfully added to recipe.', 'Action CRUD success message', 'uncanny-automator' ),
			'action_id' => $action->get_action_id()->get_value(),
			'action'    => $this->formatter->format( $action->to_array() ),
		);
	}

	/**
	 * Add action to recipe.
	 *
	 * @since 7.0.0
	 * @param int      $recipe_id Recipe ID.
	 * @param string   $action_code Action code from registry.
	 * @param array    $config Action configuration (optional).
	 * @param array    $async_config Async configuration (optional).
	 * @param int|null $parent_id Parent ID - defaults to recipe_id (optional).
	 * @return array|\WP_Error Success data or error.
	 */
	public function add_to_recipe( int $recipe_id, string $action_code, array $config = array(), array $async_config = array(), ?int $parent_id = null ) {

		// Validate recipe exists.
		if ( ! $this->validate_recipe_exists( $recipe_id ) ) {
			return new WP_Error(
				'recipe_not_found',
				sprintf(
					/* translators: %d Recipe ID. */
					esc_html_x( 'Add action failed: Recipe not found with ID: %d', 'Action CRUD error', 'uncanny-automator' ),
					$recipe_id
				)
			);
		}

		// Validate action code and get definition.
		$action_definition = $this->validate_action_code_and_get_definition( $action_code );
		if ( is_wp_error( $action_definition ) ) {
			return $action_definition;
		}

		// Validate configuration if provided.
		if ( ! empty( $config ) ) {
			$config_validation = $this->validator->validate( $action_code, $config );
			if ( is_wp_error( $config_validation ) ) {
				return $config_validation;
			}
		}

		// Create action instance.
		$action = $this->instance_builder->create( $recipe_id, $action_code, $config, $action_definition, $async_config, $parent_id );
		if ( is_wp_error( $action ) ) {
			return $action;
		}

		// Save action instance.
		try {
			$saved_action = $this->action_store->save( $action );
			return $this->build_add_action_response( $saved_action );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'action_save_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to save action: %s', 'Action CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Build empty actions response.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array Empty actions response.
	 */
	public function build_empty_actions_response( int $recipe_id ): array {
		return array(
			'success'      => true,
			'message'      => 'No actions found for this recipe',
			'recipe_id'    => $recipe_id,
			'action_count' => 0,
			'actions'      => array(),
		);
	}

	/**
	 * Build actions list response.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param array $actions Array of Action instances.
	 * @return array Actions response.
	 */
	public function build_actions_response( int $recipe_id, array $actions ): array {
		$formatted_actions = array();
		foreach ( $actions as $action ) {
			$formatted_actions[] = $this->formatter->format( $action->to_array() );
		}

		return array(
			'success'      => true,
			'message'      => esc_html_x( 'Actions retrieved successfully.', 'Action CRUD success message', 'uncanny-automator' ),
			'recipe_id'    => $recipe_id,
			'action_count' => count( $actions ),
			'actions'      => $formatted_actions,
		);
	}

	/**
	 * Get all actions for a recipe.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Recipe actions data or error.
	 */
	public function get_recipe_actions( int $recipe_id ) {

		// Validate recipe exists.
		$recipe = $this->recipe_store->get( $recipe_id );

		if ( ! $recipe ) {
			return new WP_Error(
				'recipe_not_found',
				sprintf(
					/* translators: %d Recipe ID. */
					esc_html_x( 'Get recipe actions failed: Recipe not found with ID: %d', 'Action CRUD error', 'uncanny-automator' ),
					$recipe_id
				)
			);
		}

		try {
			// Get all actions for the recipe
			$actions = $this->action_store->get_recipe_actions( $recipe_id );

			if ( empty( $actions ) ) {
				return $this->build_empty_actions_response( $recipe_id );
			}

			return $this->build_actions_response( $recipe_id, $actions );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'recipe_actions_error',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to retrieve recipe actions: %s', 'Action CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Update an existing action instance.
	 *
	 * @since 7.0.0
	 * @param int|string  $action_id Action instance ID.
	 * @param array       $config New configuration values to merge.
	 * @param array       $async_config Async configuration.
	 * @param string|null $status Optional status to set ('draft' or 'publish').
	 * @return array|\WP_Error Updated action data or error.
	 */
	public function update_action( $action_id, array $config = array(), array $async_config = array(), ?string $status = null ) {
		// Get existing action instance
		$existing_action = $this->action_store->get( $this->coerce_action_id( $action_id ) );
		if ( ! $existing_action ) {
			return new WP_Error(
				'action_not_found',
				sprintf(
					/* translators: %d Action ID. */
					esc_html_x( 'Action instance not found with ID: %d', 'Action CRUD error', 'uncanny-automator' ),
					$action_id
				)
			);
		}

		try {
			// Update action with new configuration
			$updated_action = $this->instance_builder->update( $existing_action, $config, $async_config, $status );

			// Save updated action and get the persisted version
			$saved_action = $this->action_store->save( $updated_action );

			// Track action token dependencies for background processing.
			$this->dependency_tracker->track_dependencies(
				$this->coerce_action_id( $action_id ),
				$config
			);

			return array(
				'success' => true,
				'message' => 'Action successfully updated',
				'action'  => $this->formatter->format( $saved_action->to_array() ),
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'action_update_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to update action: %s', 'Action CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Validate deletion confirmation.
	 *
	 * @param bool $confirmed Confirmation flag.
	 * @return true|\WP_Error True if confirmed, WP_Error if not.
	 */
	public function validate_deletion_confirmation( bool $confirmed ) {
		if ( ! $confirmed ) {
			return new WP_Error(
				'confirmation_required',
				'You must confirm deletion by setting $confirmed parameter to true'
			);
		}
		return true;
	}

	/**
	 * Build delete action response.
	 *
	 * @param array $action_data Action data before deletion.
	 * @return array Delete success response.
	 */
	public function build_delete_action_response( array $action_data ): array {
		return array(
			'success'           => true,
			'message'           => esc_html_x( 'Action instance successfully deleted from recipe', 'Automator', 'uncanny-automator' ),
			'deleted_action_id' => $action_data['action_id'] ?? null,
			'action_code'       => $action_data['action_code'] ?? null,
			'integration'       => $action_data['integration'] ?? null,
			'recipe_id'         => $action_data['recipe_id'] ?? null,
		);
	}

	/**
	 * Delete an action from a recipe.
	 *
	 * @since 7.0.0
	 * @param int|string $action_id Action instance ID.
	 * @param bool       $confirmed Confirmation flag for safety.
	 * @return array|\WP_Error Success confirmation or error.
	 */
	public function delete_action( $action_id, bool $confirmed = false ) {

		$confirmation = $this->validate_deletion_confirmation( $confirmed );
		if ( is_wp_error( $confirmation ) ) {
			return $confirmation;
		}

		// Get existing action instance
		$action = $this->action_store->get( $this->coerce_action_id( $action_id ) );
		if ( ! $action ) {
			return new WP_Error(
				'action_not_found',
				'Action instance not found with ID: ' . $action_id
			);
		}

		try {
			// Get action data for response before deletion
			$action_data = $action->to_array();

			// Delete the action instance
			$this->action_store->delete( $action );

			return $this->build_delete_action_response( $action_data );

		} catch ( \Exception $e ) {
			return new WP_Error(
				'delete_action_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to delete action: %s', 'Action CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Get a specific action instance by ID.
	 *
	 * @since 7.0.0
	 * @param int|string $action_id Action instance ID.
	 * @return array|\WP_Error Action data or error.
	 */
	public function get_action( $action_id ) {

		$action = $this->action_store->get( $this->coerce_action_id( $action_id ) );

		if ( ! $action ) {
			return new WP_Error(
				'action_not_found',
				sprintf(
					/* translators: %d Action ID. */
					esc_html_x( 'Action instance not found with ID: %d', 'Action CRUD error', 'uncanny-automator' ),
					$action_id
				)
			);
		}

		return array(
			'success' => true,
			'action'  => $action->to_array(),
		);
	}

	/**
	 * Get count of actions in a recipe.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id Recipe ID.
	 * @return int|\WP_Error Action count or error.
	 */
	public function get_recipe_action_count( int $recipe_id ) {

		$actions = $this->get_recipe_actions( $recipe_id );

		if ( is_wp_error( $actions ) ) {
			return $actions;
		}

		return $actions['action_count'] ?? 0;
	}

	/**
	 * Duplicate all actions from one recipe to another.
	 *
	 * @since 7.0.0
	 * @param int $source_recipe_id Source recipe ID.
	 * @param int $target_recipe_id Target recipe ID.
	 * @return array|\WP_Error Duplication result or error.
	 */
	public function duplicate_recipe_actions( int $source_recipe_id, int $target_recipe_id ) {

		$source_actions = $this->get_recipe_actions( $source_recipe_id );

		if ( is_wp_error( $source_actions ) ) {
			return $source_actions;
		}

		$duplicated_actions = array();

		foreach ( $source_actions['actions'] as $action_data ) {

			$result = $this->add_to_recipe(
				$target_recipe_id,
				$action_data['action_code'],
				$action_data['config']
			);

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					'duplication_failed',
					sprintf(
						/* translators: %s Error message. */
						esc_html_x( 'Failed to duplicate action: %s', 'Action CRUD error', 'uncanny-automator' ),
						$result->get_error_message()
					)
				);
			}

			$duplicated_actions[] = $result['action'];
		}

		return array(
			'success'            => true,
			'message'            => esc_html_x( 'Recipe actions successfully duplicated.', 'Action CRUD success message', 'uncanny-automator' ),
			'source_recipe_id'   => $source_recipe_id,
			'target_recipe_id'   => $target_recipe_id,
			'duplicated_count'   => count( $duplicated_actions ),
			'duplicated_actions' => $duplicated_actions,
		);
	}
}
