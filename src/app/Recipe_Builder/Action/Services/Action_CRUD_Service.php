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

namespace Uncanny_Automator\App\Recipe_Builder\Action\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Runnability_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Placement_Rule_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Item_Publish_On_Save_Policy;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Action\Action;
use Uncanny_Automator\App\Recipe_Builder\Action\Enums\Action_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Code;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities\Action_Builder;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities\Action_Response_Formatter;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities\Action_Token_Dependency_Tracker;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Validation_Service;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
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
	 * @var Action_Store
	 */
	private Action_Store $action_store;

	/**
	 * Recipe store instance.
	 *
	 * @var Recipe_Store
	 */
	private Recipe_Store $recipe_store;

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
	 * Field validation service (single validation entry point).
	 *
	 * @var Field_Validation_Service
	 */
	private Field_Validation_Service $validation_service;

	/**
	 * Action response formatter helper.
	 *
	 * @var Action_Response_Formatter
	 */
	private Action_Response_Formatter $formatter;

	/**
	 * Action token dependency tracker.
	 *
	 * @var Action_Token_Dependency_Tracker
	 */
	private Action_Token_Dependency_Tracker $dependency_tracker;

	/**
	 * Set-status validation rule engine (the item→on gate).
	 *
	 * @var Recipe_Violation_Service
	 */
	private Recipe_Violation_Service $violation_service;

	/**
	 * Refuses a create or move where the placement rules reject the action.
	 *
	 * @var Placement_Rule_Service
	 */
	private Placement_Rule_Service $placement;

	/**
	 * Whether to switch an action on after an add or a save.
	 *
	 * @var Item_Publish_On_Save_Policy
	 */
	private Item_Publish_On_Save_Policy $publish_policy;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 */
	private function __construct(
		?Action_Store $action_store = null,
		?Recipe_Store $recipe_store = null,
		?WP_Action_Registry $action_registry = null,
		?Action_Builder $instance_builder = null,
		?Action_Response_Formatter $formatter = null,
		?Action_Token_Dependency_Tracker $dependency_tracker = null,
		?Field_Validation_Service $validation_service = null,
		?Recipe_Violation_Service $violation_service = null,
		?Placement_Rule_Service $placement = null,
		?Item_Publish_On_Save_Policy $publish_policy = null
	) {
		$this->action_store    = $action_store ?? Database::get_action_store();
		$this->recipe_store    = $recipe_store ?? Database::get_recipe_store();
		$this->action_registry = $action_registry ?? new WP_Action_Registry();

		$this->instance_builder   = $instance_builder ?? new Action_Builder();
		$this->formatter          = $formatter ?? new Action_Response_Formatter();
		$this->dependency_tracker = $dependency_tracker ?? new Action_Token_Dependency_Tracker();
		$this->validation_service = $validation_service ?? Field_Validation_Service::instance();
		$this->violation_service  = $violation_service ?? Recipe_Violation_Service::instance();
		$this->placement          = $placement ?? Placement_Rule_Service::instance();
		$this->publish_policy     = $publish_policy ?? Item_Publish_On_Save_Policy::instance();
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
	 * @param Action_Store|null               $action_store       Custom action store.
	 * @param Recipe_Store|null               $recipe_store       Custom recipe store.
	 * @param WP_Action_Registry|null            $action_registry    Custom action registry.
	 * @param Action_Builder|null                $instance_builder   Custom action builder.
	 * @param Action_Response_Formatter|null     $formatter          Custom formatter.
	 * @param Action_Token_Dependency_Tracker|null $dependency_tracker Custom dependency tracker.
	 * @param Field_Validation_Service|null      $validation_service Custom field validation service.
	 * @param Recipe_Violation_Service|null      $violation_service  Custom set-status validation engine.
	 * @param Placement_Rule_Service|null        $placement          Custom placement rules.
	 * @param Item_Publish_On_Save_Policy|null   $publish_policy     Custom publish-on-save policy.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Action_Store $action_store = null,
		?Recipe_Store $recipe_store = null,
		?WP_Action_Registry $action_registry = null,
		?Action_Builder $instance_builder = null,
		?Action_Response_Formatter $formatter = null,
		?Action_Token_Dependency_Tracker $dependency_tracker = null,
		?Field_Validation_Service $validation_service = null,
		?Recipe_Violation_Service $violation_service = null,
		?Placement_Rule_Service $placement = null,
		?Item_Publish_On_Save_Policy $publish_policy = null
	): self {
		return new self(
			$action_store,
			$recipe_store,
			$action_registry,
			$instance_builder,
			$formatter,
			$dependency_tracker,
			$validation_service,
			$violation_service,
			$placement,
			$publish_policy
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
	 * Validate that a recipe is writable (not trashed).
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return \WP_Error|null WP_Error if trashed, null when writable.
	 */
	private function validate_recipe_writable( int $recipe_id ) {
		$recipe = $this->recipe_store->get( $recipe_id );
		if ( $recipe && ! $recipe->get_recipe_status()->is_writable() ) {
			return new WP_Error(
				'recipe_trashed',
				sprintf(
					/* translators: %d Recipe ID. */
					esc_html_x( 'Recipe %d is trashed. Restore it to draft status before making changes.', 'Action CRUD error', 'uncanny-automator' ),
					$recipe_id
				)
			);
		}
		return null;
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
	 * Describe the action as it is stored once the outermost write has ended.
	 *
	 * The write's event runs after its response was built, and a listener can rewrite
	 * a stored value (the webhook masks its Authorization), so the response reads the
	 * action back. A nested write returns as built: its event has not run yet.
	 *
	 * @param array|WP_Error $result The write's response.
	 * @return array|WP_Error
	 */
	private function with_stored_action( $result ) {
		if ( ! is_array( $result ) || ! isset( $result['action'] ) || Recipe_Change_Events::in_operation() ) {
			return $result;
		}

		$stored = $this->action_store->get( (int) ( $result['action']['action_id'] ?? 0 ) );
		if ( null !== $stored ) {
			$result['action'] = $this->formatter->format( $stored->to_array() );
		}

		return $result;
	}

	/**
	 * The refusal for an action landing where the placement rules reject it, or null when they allow it.
	 *
	 * @param int    $parent_id        The parent it lands under (recipe or block).
	 * @param string $path_code        The parent's path it lands on; '' for the parent's default path.
	 * @param string $code             Action code.
	 * @param string $integration_code Integration code.
	 * @return WP_Error|null
	 */
	private function placement_rejection( int $parent_id, string $path_code, string $code, string $integration_code ): ?WP_Error {
		$violation = $this->placement->violation(
			$this->placement->chain_for( $parent_id, $path_code ),
			$this->placement->action_candidate( $code, $integration_code )
		);
		if ( null === $violation ) {
			return null;
		}

		return $this->placement->rejection( $violation, $this->violation_service->display_name( null, $integration_code, $code, 'action' ) );
	}

	/**
	 * Whether a parent is the recipe itself or one of its blocks, the only places an action of the recipe may sit.
	 *
	 * Fails closed: a block that reads no recipe, or a post that is no block, is no parent of the recipe.
	 *
	 * @since 8.0.0
	 *
	 * @param int $parent_id The parent the action would sit under.
	 * @param int $recipe_id The recipe.
	 *
	 * @return bool
	 */
	private function parent_is_in_recipe( int $parent_id, int $recipe_id ): bool {
		return $parent_id === $recipe_id || true === Block_Crud_Service::instance()->verify_block_recipe( $parent_id, $recipe_id );
	}

	/**
	 * The refusal for adding an action under a parent outside the recipe, or null when the parent is
	 * the recipe itself or one of its blocks.
	 *
	 * @since 8.0.0
	 *
	 * @param int $parent_id The parent the action would be added under.
	 * @param int $recipe_id The recipe the action is added to.
	 *
	 * @return WP_Error|null
	 */
	private function add_parent_outside_recipe_rejection( int $parent_id, int $recipe_id ): ?WP_Error {
		if ( $this->parent_is_in_recipe( $parent_id, $recipe_id ) ) {
			return null;
		}

		return new WP_Error(
			'action_parent_not_in_recipe',
			sprintf(
				/* translators: %1$d Parent ID, %2$d Recipe ID. */
				esc_html_x( 'An action cannot be added to %1$d, which is not part of recipe %2$d. Nothing was added.', 'Action CRUD error', 'uncanny-automator' ),
				$parent_id,
				$recipe_id
			)
		);
	}

	/**
	 * The refusal for moving an action under a parent outside its recipe, or null when the parent is
	 * the recipe itself or one of its blocks.
	 *
	 * @since 8.0.0
	 *
	 * @param int $action_id Action post ID.
	 * @param int $parent_id The parent the action would move under.
	 * @param int $recipe_id The action's recipe.
	 *
	 * @return WP_Error|null
	 */
	private function parent_outside_recipe_rejection( int $action_id, int $parent_id, int $recipe_id ): ?WP_Error {
		if ( $this->parent_is_in_recipe( $parent_id, $recipe_id ) ) {
			return null;
		}

		return new WP_Error(
			'action_parent_not_in_recipe',
			sprintf(
				/* translators: %1$d Action ID, %2$d Parent ID, %3$d Recipe ID. */
				esc_html_x( 'Action %1$d cannot move to %2$d, which is not part of recipe %3$d. Nothing in this request was saved.', 'Action CRUD error', 'uncanny-automator' ),
				$action_id,
				$parent_id,
				$recipe_id
			)
		);
	}

	/**
	 * Add action to recipe.
	 *
	 * @since 7.0.0
	 * @since 8.0.0 Refuses a parent that is neither the recipe nor one of its blocks.
	 * @param int             $recipe_id Recipe ID.
	 * @param string          $action_code Action code from registry.
	 * @param array           $config Action configuration (optional).
	 * @param int|null        $parent_id Parent ID (recipe, loop or block post ID) - defaults to recipe_id.
	 * @return array|\WP_Error Success data or error.
	 */
	public function add_to_recipe( int $recipe_id, string $action_code, array $config = array(), ?int $parent_id = null ) {
		$result = Recipe_Change_Events::operation(
			function () use ( $recipe_id, $action_code, $config, $parent_id ) {

				$trashed = $this->validate_recipe_writable( $recipe_id );
				if ( is_wp_error( $trashed ) ) {
					return $trashed;
				}

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

				if ( null !== $parent_id ) {
					$parent_error = $this->add_parent_outside_recipe_rejection( $parent_id, $recipe_id );
					if ( null !== $parent_error ) {
						return $parent_error;
					}
				}

				// Validate action code and get definition.
				$action_definition = $this->validate_action_code_and_get_definition( $action_code );
				if ( is_wp_error( $action_definition ) ) {
					return $action_definition;
				}

				// The blocks above the slot, or the action's own rules, may forbid it there; the root is a slot too.
				$rejection = $this->placement_rejection( $parent_id ?? $recipe_id, '', $action_code, (string) ( $action_definition['integration'] ?? '' ) );
				if ( null !== $rejection ) {
					return $rejection;
				}

				// Validate configuration if provided.
				if ( ! empty( $config ) ) {
					$config_validation = $this->validation_service->validate_config( $action_code, $config, 'action', false );
					if ( is_wp_error( $config_validation ) ) {
						return $config_validation;
					}
				}

				// Create action instance.
				$action = $this->instance_builder->create( $recipe_id, $action_code, $config, $action_definition, $parent_id );
				if ( is_wp_error( $action ) ) {
					return $action;
				}

				$save_check = $this->save_check_on_add( $action, $config );
				if ( is_wp_error( $save_check ) ) {
					return $save_check;
				}

				return $this->save_new_action( $action, $recipe_id, $action_code, ! empty( $config ) );
			}
		);

		return $this->with_stored_action( $result );
	}

	/**
	 * Save a freshly-built action, note the post-save event, and build the
	 * add response. Shared by add_to_recipe() and add_to_block_path().
	 *
	 * @param Action $action      Built (unsaved) action.
	 * @param int    $recipe_id   Recipe post id.
	 * @param string $action_code Action code.
	 * @param bool   $configured  Whether the add carried field values.
	 * @return array|\WP_Error
	 */
	private function save_new_action( Action $action, int $recipe_id, string $action_code, bool $configured ) {
		try {
			$saved_action = $this->action_store->save( $action );
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

		// A refusal from the item-on check leaves it draft; the add still succeeds.
		$saved_action_id = (int) $saved_action->get_action_id()->get_value();
		if ( $this->publish_policy->publishes_on_add( $recipe_id, 'action', $saved_action->get_action_integration_code()->get_value(), $action_code, $configured ) ) {
			$this->set_status( $saved_action_id, $recipe_id, true );
		}

		Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, Integration_Item_Types::ACTION, $saved_action_id, $recipe_id ) );

		return $this->build_add_action_response( $saved_action );
	}


	/**
	 * Add an action into a specific branch (path) of a block.
	 *
	 * Service-level entry replacing the old REST post-create assign. The action
	 * is built carrying its path_code VO and saved once — no default-then-
	 * correct. Path validity for the block type is checked first, so an invalid
	 * path leaves no orphaned action.
	 *
	 * @since 8.0.0 Refuses a block that is not one of the recipe's.
	 *
	 * @param int    $recipe_id   Recipe post id.
	 * @param string $action_code Action code from the registry.
	 * @param array  $config      Action field config (may be empty).
	 * @param int    $block_id    Parent block post id.
	 * @param string $path_code   Branch within the block.
	 * @return array|\WP_Error Add response or error.
	 */
	public function add_to_block_path( int $recipe_id, string $action_code, array $config, int $block_id, string $path_code ) {
		$result = Recipe_Change_Events::operation(
			function () use ( $recipe_id, $action_code, $config, $block_id, $path_code ) {

				$writable = $this->validate_recipe_writable( $recipe_id );
				if ( is_wp_error( $writable ) ) {
					return $writable;
				}
				if ( ! $this->validate_recipe_exists( $recipe_id ) ) {
					return new WP_Error( 'recipe_not_found', esc_html_x( 'Recipe not found', 'Action CRUD error', 'uncanny-automator' ) );
				}

				$parent_error = $this->add_parent_outside_recipe_rejection( $block_id, $recipe_id );
				if ( null !== $parent_error ) {
					return $parent_error;
				}

				$path_validation = Block_Crud_Service::instance()->validate_path_for_block( $block_id, $path_code );
				if ( is_wp_error( $path_validation ) ) {
					return $path_validation;
				}

				$action_definition = $this->validate_action_code_and_get_definition( $action_code );
				if ( is_wp_error( $action_definition ) ) {
					return $action_definition;
				}

				$rejection = $this->placement_rejection( $block_id, $path_code, $action_code, (string) ( $action_definition['integration'] ?? '' ) );
				if ( null !== $rejection ) {
					return $rejection;
				}

				if ( ! empty( $config ) ) {
					$config_validation = $this->validation_service->validate_config( $action_code, $config, 'action', false );
					if ( is_wp_error( $config_validation ) ) {
						return $config_validation;
					}
				}

				$action = $this->instance_builder->create( $recipe_id, $action_code, $config, $action_definition, $block_id, $path_code );
				if ( is_wp_error( $action ) ) {
					return $action;
				}

				$save_check = $this->save_check_on_add( $action, $config );
				if ( is_wp_error( $save_check ) ) {
					return $save_check;
				}

				return $this->save_new_action( $action, $recipe_id, $action_code, ! empty( $config ) );
			}
		);

		return $this->with_stored_action( $result );
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
	 * @since 8.0.0 Takes the recipe the caller named, and refuses an action of another recipe and a move outside the action's recipe.
	 * @param int|string  $action_id Action instance ID.
	 * @param array       $config New configuration values to merge.
	 * @param string|null $status Optional status to set ('draft' or 'publish').
	 * @param int|null    $parent_id Optional new parent ID (recipe or loop) to move action to.
	 * @param string|null $requested_code Optional code the caller believes it is updating (guard).
	 * @param string|null $path_code Optional branch of the target block to land on; null keeps a stamp the block declares, or its default.
	 * @param int|null    $expected_recipe_id Recipe the caller named, or null when the caller has checked ownership itself.
	 * @return array|\WP_Error Updated action data or error.
	 */
	public function update_action( $action_id, array $config = array(), ?string $status = null, ?int $parent_id = null, ?string $requested_code = null, ?string $path_code = null, ?int $expected_recipe_id = null ) {
		$result = Recipe_Change_Events::operation(
			function () use ( $action_id, $config, $status, $parent_id, $requested_code, $path_code, $expected_recipe_id ) {
				if ( null !== $status && ! Action_Status::is_valid( $status ) ) {
					return new WP_Error(
						'invalid_action_status',
						sprintf(
							/* translators: %s Requested status. */
							esc_html_x( 'An action’s status is "publish" or "draft", not "%s". Nothing in this request was saved.', 'Action CRUD error', 'uncanny-automator' ),
							esc_html( $status )
						)
					);
				}

				if ( null !== $expected_recipe_id ) {
					$ownership_error = $this->action_not_in_recipe_error( $this->coerce_action_id( $action_id ), $expected_recipe_id );
					if ( null !== $ownership_error ) {
						return $ownership_error;
					}
				}

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

				$trashed = $this->validate_recipe_writable( $existing_action->get_action_recipe_id()->get_value() );
				if ( is_wp_error( $trashed ) ) {
					return $trashed;
				}

				$code_error = $this->validate_code_unchanged( (int) $action_id, $requested_code );
				if ( is_wp_error( $code_error ) ) {
					return $code_error;
				}

				$recipe_id      = $existing_action->get_action_recipe_id()->get_value();
				$current_parent = null === $existing_action->get_parent_id() ? $recipe_id : (int) $existing_action->get_parent_id()->get_value();
				$current_path   = null === $existing_action->get_path_code() ? '' : $existing_action->get_path_code()->get_value();
				$target_parent  = $parent_id ?? $current_parent;

				// A move stays inside the action's recipe: the new parent is the recipe itself or one of its blocks.
				if ( $target_parent !== $current_parent ) {
					$parent_error = $this->parent_outside_recipe_rejection( $this->coerce_action_id( $action_id ), $target_parent, (int) $recipe_id );
					if ( null !== $parent_error ) {
						return $parent_error;
					}
				}

				// An explicit path names a branch of the target block. The recipe root has none, and a path the block
				// does not declare is refused before any write, as it is on create.
				if ( null !== $path_code ) {
					if ( $target_parent === $recipe_id ) {
						return new WP_Error( 'automator_path_code_forbidden', esc_html_x( 'path_code is not valid at the recipe root.', 'Action CRUD error', 'uncanny-automator' ) );
					}
					$path_valid = Block_Crud_Service::instance()->validate_path_for_block( $target_parent, $path_code );
					if ( is_wp_error( $path_valid ) ) {
						return $path_valid;
					}
				}

				// A move lands the action under a new parent or on another branch; the blocks above that slot must
				// allow it there. A save that resends the current placement is not a move: an action already sitting
				// where the rules reject it stays editable, and go-live is what refuses it.
				$moved = $target_parent !== $current_parent || ( null !== $path_code && $path_code !== $current_path );
				if ( $moved ) {
					$rejection = $this->placement_rejection(
						$target_parent,
						$path_code ?? $current_path,
						$existing_action->get_action_code()->get_value(),
						$existing_action->get_action_integration_code()->get_value()
					);
					if ( null !== $rejection ) {
						return $rejection;
					}
				}

				// Validate provided fields against schema (partial — skip required checks on update).
				if ( ! empty( $config ) ) {
					$action_code       = $existing_action->get_action_code()->get_value();
					$config_validation = $this->validation_service->validate_config( $action_code, $config, 'action', true );
					if ( is_wp_error( $config_validation ) ) {
						return $config_validation;
					}
				}

				$stored_before = null;
				try {
					// Update action with new configuration. The on / off switch is
					// set_status()'s, so the save keeps the current status.
					$updated_action = $this->instance_builder->update( $existing_action, $config );

					// A new parent drops the old path; the store then reconciles one the parent declares, or its
					// default, or clears it at the root. A resend of the current parent is not a move and writes no
					// path. An explicit path is stamped as given.
					if ( $target_parent !== $current_parent ) {
						$updated_action = $updated_action->with_parent_id( $target_parent );
					}
					if ( null !== $path_code ) {
						$updated_action = $updated_action->with_path_code( $path_code );
					}

					// The action refuses a value it can't use; nothing of the request is saved.
					$save_check = $this->validation_service->check_item_save( 'action', $updated_action->get_action_code()->get_value(), $updated_action->get_fields() );
					if ( is_wp_error( $save_check ) ) {
						return $save_check;
					}

					// A switch-on the item-on rules refuse saves nothing of the request.
					if ( Action_Status::PUBLISH === $status ) {
						$rejection = $this->item_on_rejection( $this->coerce_action_id( $action_id ), $updated_action );
						if ( $rejection instanceof WP_Error ) {
							return new WP_Error(
								$rejection->get_error_code(),
								$rejection->get_error_message() . ' ' . esc_html_x( 'Nothing in this request was saved.', 'Action CRUD error', 'uncanny-automator' ),
								$rejection->get_error_data()
							);
						}
					}

					// Nothing is written above this line. The action as stored now tells a failed save that wrote from one that did not.
					$stored_before = $this->stored_action( $this->coerce_action_id( $action_id ) );

					// Save updated action and get the persisted version
					$saved_action = $this->action_store->save( $updated_action );

					// Track action token dependencies for background processing.
					$this->dependency_tracker->track_dependencies(
						$this->coerce_action_id( $action_id ),
						$config
					);

					$recipe_id_vo = $saved_action->get_action_recipe_id();
					$recipe_id    = $recipe_id_vo->get_value();

					// An explicit status from the caller wins over the save rule. A refusal
					// on the save rule leaves the action as it was; the save still succeeds.
					$saved_action_id = (int) $saved_action->get_action_id()->get_value();
					$message         = 'Action successfully updated';
					$status_error    = null;
					if ( null !== $status ) {
						$recipe_was_live = $this->recipe_is_live( $recipe_id );
						$status_result   = $this->set_status( $saved_action_id, $recipe_id, Action_Status::PUBLISH === $status );
						if ( $recipe_was_live && ! is_wp_error( $status_result ) && ! $this->recipe_is_live( $recipe_id ) ) {
							$message = esc_html_x( 'Action successfully updated. The recipe was switched off because it no longer has both a live trigger and a live action.', 'Action CRUD message', 'uncanny-automator' );
						}
						if ( is_wp_error( $status_result ) ) {
							$status_error = new WP_Error(
								$status_result->get_error_code(),
								$status_result->get_error_message() . ' ' . esc_html_x( 'The field changes in this request were saved.', 'Action CRUD error', 'uncanny-automator' ),
								$status_result->get_error_data()
							);
						}
					} elseif ( $this->publish_policy->publishes_on_update( $recipe_id, 'action', $saved_action->get_action_code()->get_value() ) ) {
						$this->set_status( $saved_action_id, $recipe_id, true );
					}

					// The field changes landed even when the status change was refused, so the event fires either way.
					if ( $recipe_id > 0 ) {
						Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Integration_Item_Types::ACTION, $saved_action_id, $recipe_id ) );
					}

					if ( null !== $status_error ) {
						return $status_error;
					}

					return array(
						'success' => true,
						'message' => $message,
						'action'  => $this->formatter->format( $saved_action->to_array() ),
					);

				} catch ( \Throwable $e ) {
					// An event means a write landed: the post fields or the meta changed before the failure, in the store's save or after it.
					$stored_id = $this->coerce_action_id( $action_id );
					if ( null !== $stored_before && 0 < (int) $recipe_id && $stored_before !== $this->stored_action( $stored_id ) ) {
						Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Integration_Item_Types::ACTION, $stored_id, (int) $recipe_id ) );
					}
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
		);

		return $this->with_stored_action( $result );
	}

	/**
	 * The action as stored: its post's fields and its meta.
	 *
	 * Read before an update writes and again when the update fails, it tells a
	 * save that wrote something from one that wrote nothing.
	 *
	 * @since 8.0.1
	 *
	 * @param int $action_id Action post ID.
	 * @return array
	 */
	private function stored_action( int $action_id ): array {
		$post   = get_post( $action_id );
		$fields = $post instanceof \WP_Post ? get_object_vars( $post ) : array();

		// WordPress stamps the modified time on every update, one that changes nothing included.
		unset( $fields['post_modified'], $fields['post_modified_gmt'] );

		return array(
			'post' => $fields,
			'meta' => get_post_meta( $action_id ),
		);
	}

	/**
	 * Toggle the action's post_status between publish and draft.
	 *
	 * Validates that the action exists, belongs to $expected_recipe_id, and
	 * the recipe is writable. Going live runs the violation engine's item→on
	 * rules; a non-empty result returns the REJECTION_CODE WP_Error carrying
	 * the wire violations (REST maps it to the 422 envelope).
	 *
	 * @param int  $action_id          Action post ID.
	 * @param int  $expected_recipe_id Recipe ID from the REST URL.
	 * @param bool $is_on              true → publish, false → draft.
	 *
	 * @return array|WP_Error array{ success: true } on success; WP_Error on validation failure, or (status 500) when the status write is refused.
	 */
	public function set_status( int $action_id, int $expected_recipe_id, bool $is_on ) {
		return Recipe_Change_Events::operation(
			function () use ( $action_id, $expected_recipe_id, $is_on ) {
				$ownership_error = $this->action_not_in_recipe_error( $action_id, $expected_recipe_id );
				if ( null !== $ownership_error ) {
					return $ownership_error;
				}

				$writable = $this->validate_recipe_writable( $expected_recipe_id );
				if ( is_wp_error( $writable ) ) {
					return new WP_Error( $writable->get_error_code(), $writable->get_error_message(), array( 'status' => 409 ) );
				}

				// Going live: the item's persisted config must pass full validation
				// (fail-closed). Drafting is never gated.
				if ( $is_on ) {
					$action = $this->action_store->get( $action_id );
					if ( null === $action ) {
						return new WP_Error(
							'action_not_found',
							sprintf(
								/* translators: %1$d Action ID, %2$d Recipe ID. */
								esc_html_x( 'Action %1$d not found in recipe %2$d.', 'Action CRUD error', 'uncanny-automator' ),
								$action_id,
								$expected_recipe_id
							),
							array( 'status' => 404 )
						);
					}

					$rejection = $this->item_on_rejection( $action_id, $action );
					if ( $rejection instanceof WP_Error ) {
						return $rejection;
					}
				}

				$status = $is_on ? Action_Status::PUBLISH : Action_Status::DRAFT;
				try {
					Database::get_recipe_item_status_store()->update_post_status( $action_id, $status );
				} catch ( Store_Exception $e ) {
					// The write did not land: nothing changed, so no event.
					return new WP_Error(
						'action_status_update_failed',
						sprintf(
							/* translators: %s Error message. */
							esc_html_x( 'Failed to update action status: %s', 'Action CRUD error', 'uncanny-automator' ),
							$e->getMessage()
						),
						array( 'status' => 500 )
					);
				}
				Recipe_Cache::invalidate( $expected_recipe_id );

				Recipe_Runnability_Service::instance()->demote_if_empty( $expected_recipe_id );

				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Integration_Item_Types::ACTION, $action_id, $expected_recipe_id ) );

				return array( 'success' => true );
			}
		);
	}

	/**
	 * Whether the recipe is live right now.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return bool
	 */
	private function recipe_is_live( int $recipe_id ): bool {
		$recipe_post = $this->recipe_store->get_wp_post( $recipe_id );

		return $recipe_post instanceof \WP_Post && Recipe_Status::is_recipe_on( $recipe_post->post_status );
	}

	/**
	 * The action's own refusal of the values a new action is added with.
	 *
	 * An action added without values has nothing to refuse; the builder adds
	 * actions empty and its save is checked on update.
	 *
	 * @since 8.0.0
	 *
	 * @param Action $action The action as it would be added.
	 * @param array  $config The values the add was sent.
	 *
	 * @return true|WP_Error
	 */
	private function save_check_on_add( Action $action, array $config ) {
		if ( empty( $config ) ) {
			return true;
		}

		return $this->validation_service->check_item_save( 'action', $action->get_action_code()->get_value(), $action->get_fields() );
	}

	/**
	 * The item-on rules' refusal for an action going live, or null when it may.
	 *
	 * Placement too, not just field validity: an action switched on alone must
	 * not go live where setting the recipe live would refuse it.
	 *
	 * @param int    $action_id Action post ID.
	 * @param Action $action    The action as it would go live.
	 *
	 * @return WP_Error|null
	 */
	private function item_on_rejection( int $action_id, Action $action ): ?WP_Error {
		$code             = $action->get_action_code()->get_value();
		$integration_code = $action->get_action_integration_code()->get_value();
		$parent           = $action->get_parent_id();

		return Recipe_Violation_Service::rejection_error(
			array_merge(
				$this->violation_service->evaluate_item_on(
					$action_id,
					'action',
					$code,
					$integration_code,
					$action->get_custom_name(),
					$action->get_fields()
				),
				$this->violation_service->evaluate_action_placement(
					$action_id,
					null === $parent ? 0 : (int) $parent->get_value(),
					$code,
					$integration_code,
					$action->get_custom_name(),
					null === $action->get_path_code() ? '' : $action->get_path_code()->get_value()
				)
			),
			esc_html_x( 'This action can’t be switched on yet.', 'Action CRUD error', 'uncanny-automator' )
		);
	}

	/**
	 * Validate that the component code has not changed.
	 *
	 * @param int         $action_id      Action post ID.
	 * @param string|null $requested_code Code the caller wants to set, or null to skip.
	 *
	 * @return \WP_Error|null WP_Error when the code differs, null when OK.
	 */
	private function validate_code_unchanged( int $action_id, ?string $requested_code ) {

		if ( null === $requested_code || '' === $requested_code ) {
			return null;
		}

		$stored_code = get_post_meta( $action_id, 'code', true );

		if ( ! $stored_code || $requested_code === $stored_code ) {
			return null;
		}

		return new WP_Error(
			'action_code_change_rejected',
			sprintf(
				/* translators: %1$s Current code, %2$s Requested code. */
				esc_html_x( 'Cannot change action_code from "%1$s" to "%2$s" on an existing action. Delete and recreate instead.', 'Action CRUD error', 'uncanny-automator' ),
				$stored_code,
				$requested_code
			)
		);
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
	 * Skips the pre-delete domain-object fetch intentionally. Previously this
	 * method called `action_store->get()` before deleting, which re-queried
	 * `get_post()` and applied a `post_type === 'uo-action'` filter. Any
	 * stale WP object cache on `wp_posts` could report the wrong post_type
	 * and surface as a spurious `action_not_found` even though the delete
	 * transport had already verified ownership moments earlier. A caller that
	 * names the recipe has the action's recipe checked here; a caller that
	 * names none has checked ownership itself. Whether the id is an action is
	 * left to the store's `delete_by_id()`.
	 *
	 * @since 7.0.0
	 * @since 8.0.0 Takes the recipe the caller named, and refuses an action of another recipe.
	 * @param int|string $action_id          Action instance ID.
	 * @param bool       $confirmed          Confirmation flag for safety.
	 * @param int|null   $expected_recipe_id Recipe the caller named, or null when the caller has checked ownership itself.
	 * @return array|\WP_Error Success confirmation or error.
	 */
	public function delete_action( $action_id, bool $confirmed = false, ?int $expected_recipe_id = null ) {
		return Recipe_Change_Events::operation(
			function () use ( $action_id, $confirmed, $expected_recipe_id ) {

				$confirmation = $this->validate_deletion_confirmation( $confirmed );
				if ( is_wp_error( $confirmation ) ) {
					return $confirmation;
				}

				$coerced = $this->coerce_action_id( $action_id );

				if ( null !== $expected_recipe_id ) {
					$ownership_error = $this->action_not_in_recipe_error( $coerced, $expected_recipe_id );
					if ( null !== $ownership_error ) {
						return $ownership_error;
					}
				}

				// Snapshot what we know about the action before deletion. These calls
				// hit the postmeta cache, not the post cache, so they stay informative
				// even when the post cache is stale. Missing values fall through as
				// empty strings / 0, which the response formatter tolerates.
				$action_code = (string) get_post_meta( $coerced, 'code', true );
				$integration = (string) get_post_meta( $coerced, 'integration', true );
				$recipe_id   = $this->resolve_action_recipe_id( $coerced );

				try {
					$this->action_store->delete_by_id( $coerced, $recipe_id );
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

				if ( $recipe_id > 0 ) {
					Recipe_Runnability_Service::instance()->demote_if_empty( $recipe_id );
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::DELETED, Integration_Item_Types::ACTION, $coerced, $recipe_id ) );
				}

				return $this->build_delete_action_response(
					array(
						'action_id'   => $coerced,
						'action_code' => $action_code,
						'integration' => $integration,
						'recipe_id'   => $recipe_id,
					)
				);
			}
		);
	}

	/**
	 * The refusal for an action that does not belong to the recipe the caller named, or null when it does.
	 *
	 * Checks the recipe only, through the same owner resolution set_status() has always used: an action
	 * nested in a block belongs to the block's recipe. Whether the id is an action at all is left to the
	 * paths that read the action: the store's get() and delete_by_id().
	 *
	 * @since 8.0.0
	 *
	 * @param int $action_id          Action post ID.
	 * @param int $expected_recipe_id Recipe the caller named.
	 *
	 * @return WP_Error|null `action_not_found` (status 404), or null when the action is the recipe's.
	 */
	private function action_not_in_recipe_error( int $action_id, int $expected_recipe_id ): ?WP_Error {
		$owner_recipe_id = $this->resolve_action_recipe_id( $action_id );
		if ( 0 !== $owner_recipe_id && $owner_recipe_id === $expected_recipe_id ) {
			return null;
		}

		return new WP_Error(
			'action_not_found',
			sprintf(
				/* translators: %1$d Action ID, %2$d Recipe ID. */
				esc_html_x( 'Action %1$d not found in recipe %2$d.', 'Action CRUD error', 'uncanny-automator' ),
				$action_id,
				$expected_recipe_id
			),
			array( 'status' => 404 )
		);
	}

	/**
	 * Resolve the recipe_id that owns an action post.
	 *
	 * Container-nested actions resolve through the block store's recipe_id
	 * link (blocks nest arbitrarily). Returns 0 when the post is gone or has
	 * no resolvable parent — callers treat that as "unknown" and skip cache
	 * invalidation.
	 *
	 * @param int $action_id Action post ID.
	 * @return int Recipe ID, or 0 when unresolvable.
	 */
	private function resolve_action_recipe_id( int $action_id ): int {
		$post = get_post( $action_id );
		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}

		$parent_id = (int) $post->post_parent;
		if ( $parent_id <= 0 ) {
			return 0;
		}

		$parent = get_post( $parent_id );
		if ( $parent instanceof \WP_Post && AUTOMATOR_POST_TYPE_BLOCK === $parent->post_type ) {
			// Containers nest arbitrarily — the block store's recipe_id
			// link resolves the owner at any depth.
			$block = $this->block_lookup_recipe_id( $parent_id );
			if ( $block > 0 ) {
				return $block;
			}
		}

		return $parent_id;
	}

	/**
	 * Resolve the owning recipe of a block via the block store's recipe link.
	 *
	 * @param int $block_id Block post ID.
	 * @return int Recipe ID, or 0 when the block cannot be resolved.
	 */
	private function block_lookup_recipe_id( int $block_id ): int {
		$block = Database::get_block_store()->get( $block_id );

		return null === $block ? 0 : (int) ( $block['recipe_id'] ?? 0 );
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
}
