<?php
/**
 * MCP catalog tool that removes a specific condition from a group.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Management_Service;
use Uncanny_Automator\Api\Database\Stores\Action_Condition_Store;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Factory;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Locator;

/**
 * Remove Condition from Group MCP Tool.
 *
 * MCP tool for removing an individual condition from an existing condition group.
 * Uses dependency injection to delegate operations to existing service layer.
 *
 * @since 7.0.0
 */
class Remove_Condition_Tool extends Abstract_MCP_Tool {

	/**
	 * Condition management service for orchestrating operations.
	 *
	 * @var Condition_Management_Service
	 */
	private $management_service;

	/**
	 * Constructor with optional dependency injection.
	 *
	 * If dependency is not provided, it is lazily instantiated when accessed.
	 *
	 * @param Condition_Management_Service|null $management_service Condition management service.
	 */
	public function __construct(
		Condition_Management_Service $management_service = null
	) {
		// Store dependency (null if not provided, will be lazily initialized)
		$this->management_service = $management_service;
	}

	/**
	 * Get condition management service with lazy initialization.
	 *
	 * @return Condition_Management_Service Service instance.
	 */
	private function get_management_service() {
		if ( null === $this->management_service ) {
			$recipe_store = \Uncanny_Automator\Api\Database\Database::get_recipe_store();
			$repository   = new Action_Condition_Store( $recipe_store );
			$registry     = new \Uncanny_Automator\Api\Components\Condition\Registry\WP_Action_Condition_Registry();
			$action_crud  = \Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service::instance();
			$validator    = new \Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Validator( $registry, $action_crud );
			$factory      = new Condition_Factory( $validator );
			$locator      = new Condition_Locator();

			$this->management_service = new Condition_Management_Service(
				$repository,
				$factory,
				$locator
			);
		}
		return $this->management_service;
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'remove_condition';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Remove a condition from a group. Get condition_id from list_conditions.';
	}

	/**
	 * Define the input schema for the remove condition from group tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for remove condition from group parameters.
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
					'description' => 'Condition group ID containing the condition to remove. Must be an existing group in the recipe.',
					'minLength'   => 1,
				),
				'condition_id' => array(
					'type'        => 'string',
					'description' => 'Condition ID to remove from the group. Get from get_recipe_conditions tool.',
					'minLength'   => 1,
				),
			),
			'required'   => array( 'recipe_id', 'group_id', 'condition_id' ),
		);
	}

	/**
	 * Execute the remove condition from group tool.
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
			$validated = $this->validate_remove_parameters( $params );
			if ( is_wp_error( $validated ) ) {
				return Json_Rpc_Response::create_error_response( $validated->get_error_message() );
			}

			// Delegate to service layer
			$result = $this->get_management_service()->remove_condition_from_group(
				$validated['condition_id'],
				$validated['group_id'],
				$validated['recipe_id']
			);

			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}

			// Enhance result with MCP-specific data
			$payload = array_merge(
				$result,
				array(
					'links'      => $this->build_recipe_links( $validated['recipe_id'] ),
					'next_steps' => $this->build_recipe_next_steps( $validated['recipe_id'], $validated['group_id'] ),
				)
			);

			return Json_Rpc_Response::create_success_response(
				$result['message'] ?? 'Condition removed from group successfully',
				$payload
			);

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response(
				'Failed to remove condition from group: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Validate required remove parameters.
	 *
	 * @param array $params Raw parameters from MCP client.
	 * @return array|\WP_Error Validated parameters or error.
	 */
	private function validate_remove_parameters( array $params ) {
		$recipe_id    = $params['recipe_id'] ?? null;
		$group_id     = $params['group_id'] ?? '';
		$condition_id = $params['condition_id'] ?? '';

		if ( ! $recipe_id || empty( $group_id ) || empty( $condition_id ) ) {
			return new \WP_Error(
				'missing_parameters',
				'Missing required parameters: recipe_id, group_id, and condition_id are required'
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
		);
	}
	/**
	 * Build recipe links.
	 *
	 * @param int $recipe_id The ID.
	 * @return array
	 */
	private function build_recipe_links( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$edit_link = get_edit_post_link( $recipe_id, 'raw' );
		if ( ! is_string( $edit_link ) || '' === $edit_link ) {
			return array();
		}

		return array( 'edit_recipe' => $edit_link );
	}
	/**
	 * Build recipe next steps.
	 *
	 * @param int $recipe_id The ID.
	 * @param string $group_id The ID.
	 * @return array
	 */
	private function build_recipe_next_steps( int $recipe_id, string $group_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$steps                = array();
		$edit                 = get_edit_post_link( $recipe_id, 'raw' );
		$steps['edit_recipe'] = array(
			'admin_url' => is_string( $edit ) ? $edit : '',
			'hint'      => 'Open the recipe editor to confirm the condition group after removal.',
		);

		if ( '' !== $group_id ) {
			$steps['list_conditions'] = array(
				'tool'   => 'list_conditions',
				'params' => array(
					'recipe_id' => $recipe_id,
				),
				'hint'   => 'List condition groups to verify the current set of rules for this group.',
			);
		}

		return $steps;
	}
}
