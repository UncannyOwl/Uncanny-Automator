<?php
/**
 * MCP catalog tool that edits an individual condition within a group.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Factory;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Locator;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Components\Recipe\Recipe;

/**
 * Update Specific Condition MCP Tool.
 *
 * MCP tool for updating an individual condition's fields within a condition group.
 * Uses dependency injection to delegate operations to existing service layer.
 *
 * @since 7.0.0
 */
class Update_Condition_Tool extends Abstract_MCP_Tool {

	/**
	 * Condition locator service for finding and updating groups.
	 *
	 * @var Condition_Locator
	 */
	private $locator;

	/**
	 * Condition factory service for creating and refreshing conditions.
	 *
	 * @var Condition_Factory
	 */
	private $factory;

	/**
	 * Recipe store for persisting recipe changes.
	 *
	 * @var WP_Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Constructor with optional dependency injection.
	 *
	 * If dependencies are not provided, they are lazily instantiated when accessed.
	 *
	 * @param Condition_Locator|null $locator      Condition locator service.
	 * @param Condition_Factory|null $factory      Condition factory service.
	 * @param WP_Recipe_Store|null   $recipe_store Recipe persistence store.
	 */
	public function __construct(
		?Condition_Locator $locator = null,
		?Condition_Factory $factory = null,
		?WP_Recipe_Store $recipe_store = null
	) {
		// Store dependencies (null if not provided, will be lazily initialized)
		$this->locator      = $locator;
		$this->factory      = $factory;
		$this->recipe_store = $recipe_store;
	}

	/**
	 * Get condition locator with lazy initialization.
	 *
	 * @return Condition_Locator Locator instance.
	 */
	private function get_locator() {
		if ( null === $this->locator ) {
			$this->locator = new Condition_Locator();
		}
		return $this->locator;
	}

	/**
	 * Get condition factory with lazy initialization.
	 *
	 * @return Condition_Factory Factory instance.
	 */
	private function get_factory() {
		if ( null === $this->factory ) {
			$registry      = new \Uncanny_Automator\Api\Components\Condition\Registry\WP_Action_Condition_Registry();
			$action_crud   = \Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service::instance();
			$validator     = new \Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Validator( $registry, $action_crud );
			$this->factory = new Condition_Factory( $validator );
		}
		return $this->factory;
	}

	/**
	 * Get recipe store with lazy initialization.
	 *
	 * @return WP_Recipe_Store Store instance.
	 */
	private function get_recipe_store() {
		if ( null === $this->recipe_store ) {
			$this->recipe_store = \Uncanny_Automator\Api\Database\Database::get_recipe_store();
		}
		return $this->recipe_store;
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'update_condition';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Update a condition. Get condition_id from list_conditions, get schema from get_component_schema, then update fields.';
	}

	/**
	 * Define the input schema for the update condition tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for update condition parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'    => array(
					'type'        => 'integer',
					'description' => 'Recipe ID containing the condition group. Must be an existing recipe.',
					'minimum'     => 1,
				),
				'group_id'     => array(
					'type'        => 'string',
					'description' => 'Condition group ID containing the condition to update. Must be an existing group in the recipe.',
					'minLength'   => 1,
				),
				'condition_id' => array(
					'type'        => 'string',
					'description' => 'Condition ID to update. Get from get_recipe_conditions tool.',
					'minLength'   => 1,
				),
				'fields'       => array(
					'type'                 => 'object',
					'description'          => 'Field updates to merge with existing condition fields. Only provided fields are updated, existing fields are preserved. Use get_action_condition to see available fields for the condition type.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'recipe_id', 'group_id', 'condition_id', 'fields' ),
		);
	}

	/**
	 * Execute the update condition tool.
	 *
	 * Delegates to service layer for all business logic operations.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		try {
			// Require authenticated executor for recipe modification
			$this->require_authenticated_executor( $user_context );

			// Validate parameters
			$validated = $this->validate_update_parameters( $params );
			if ( is_wp_error( $validated ) ) {
				return Json_Rpc_Response::create_error_response( $validated->get_error_message() );
			}

			// Fetch recipe (coerce to int for strict type compatibility)
			$recipe = $this->get_recipe_store()->get( (int) $validated['recipe_id'] );
			if ( ! $recipe ) {
				return Json_Rpc_Response::create_error_response( 'Recipe not found' );
			}

			// Get current conditions
			$current_conditions = $recipe->get_recipe_action_conditions();
			if ( ! $current_conditions ) {
				return Json_Rpc_Response::create_error_response( 'Recipe has no condition groups' );
			}

			// Find target group (using Condition_Locator service)
			$target_group = $this->get_locator()->require_group( $current_conditions, $validated['group_id'] );
			if ( is_wp_error( $target_group ) ) {
				return Json_Rpc_Response::create_error_response( $target_group->get_error_message() );
			}

			// Find condition in group
			$condition = $this->find_condition_in_group( $target_group, $validated['condition_id'] );
			if ( is_wp_error( $condition ) ) {
				return Json_Rpc_Response::create_error_response( $condition->get_error_message() );
			}

			// Merge fields
			$existing_fields = $condition->get_fields()->get_all();
			$merged_fields   = array_merge( $existing_fields, $validated['fields'] );

			// Refresh condition with new fields (using Condition_Factory service)
			$updated_condition = $this->get_factory()->refresh_condition_with_id( $condition, $merged_fields );
			if ( is_wp_error( $updated_condition ) ) {
				return Json_Rpc_Response::create_error_response( $updated_condition->get_error_message() );
			}

			// Replace condition in group (using Condition_Locator service)
			$updated_group = $this->get_locator()->replace_condition_in_group(
				$target_group,
				$validated['condition_id'],
				$updated_condition
			);

			// Replace group in conditions (using Condition_Locator service)
			$updated_conditions = $this->get_locator()->replace_group( $current_conditions, $updated_group );

			// Build updated recipe
			$config = $recipe->get_config();
			$config->action_conditions( $updated_conditions->to_array() );
			$updated_recipe = new Recipe( $config );

			// Persist to database
			$this->get_recipe_store()->save( $updated_recipe );

			// Build success response
			$payload = array(
				'condition_id'     => $validated['condition_id'],
				'group_id'         => $validated['group_id'],
				'recipe_id'        => $validated['recipe_id'],
				'provided_fields'  => $validated['fields'],
				'merged_fields'    => $merged_fields,
				'total_conditions' => count( $updated_group->get_conditions() ),
			);

			return Json_Rpc_Response::create_success_response(
				'Condition fields merged successfully',
				$payload
			);

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response(
				'Failed to update condition: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Validate required update parameters.
	 *
	 * @param array $params Raw parameters from MCP client.
	 * @return array|WP_Error Validated parameters or error.
	 */
	private function validate_update_parameters( array $params ) {
		$recipe_id    = $params['recipe_id'] ?? null;
		$group_id     = $params['group_id'] ?? '';
		$condition_id = $params['condition_id'] ?? '';
		$fields       = $params['fields'] ?? array();

		if ( ! $recipe_id || empty( $group_id ) || empty( $condition_id ) || empty( $fields ) ) {
			return new \WP_Error(
				'missing_parameters',
				'Missing required parameters: recipe_id, group_id, condition_id, and fields are required'
			);
		}

		// Validate recipe_id is numeric
		if ( ! is_numeric( $recipe_id ) ) {
			return new \WP_Error(
				'invalid_recipe_id',
				'Invalid recipe_id: must be a numeric value'
			);
		}

		return array(
			'recipe_id'    => (int) $recipe_id,
			'group_id'     => $group_id,
			'condition_id' => $condition_id,
			'fields'       => $fields,
		);
	}

	/**
	 * Find specific condition in group by condition ID.
	 *
	 * @param mixed  $group        Condition group.
	 * @param string $condition_id Condition ID to find.
	 * @return mixed|\WP_Error Found condition or error.
	 */
	private function find_condition_in_group( $group, string $condition_id ) {
		$group_conditions = $group->get_conditions();

		foreach ( $group_conditions as $condition ) {
			if ( $condition->get_condition_id()->get_value() === $condition_id ) {
				return $condition;
			}
		}

		return new \WP_Error( 'condition_not_found', 'Condition not found in group' );
	}
}
