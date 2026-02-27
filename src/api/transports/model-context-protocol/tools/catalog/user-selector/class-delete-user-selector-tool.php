<?php
/**
 * Delete User Selector MCP Tool.
 *
 * Removes the user selector configuration from a recipe.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\User_Selector;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\User_Selector\User_Selector_Service;

/**
 * Delete_User_Selector_Tool Class.
 *
 * MCP tool for removing user selector configuration.
 */
class Delete_User_Selector_Tool extends Abstract_MCP_Tool {

	/**
	 * User selector service.
	 *
	 * @var User_Selector_Service
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param User_Selector_Service|null $service Optional service for testing.
	 */
	public function __construct( ?User_Selector_Service $service = null ) {
		$this->service = $service ?? User_Selector_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'delete_user_selector';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Remove the user selector configuration from a recipe.

WARNING: This will clear all user selector settings:
- Source type (existingUser/newUser)
- Matching criteria (unique_field, unique_field_value)
- Fallback behavior
- User data for creation

After deletion, actions in anonymous recipes will have no user context.

Use get_user_selector first to verify what will be deleted.';
	}

	/**
	 * Define input schema.
	 *
	 * @return array
	 */
	protected function schema_definition(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'The recipe ID to delete user selector from.',
					'minimum'     => 1,
				),
				'confirm'   => array(
					'type'        => 'boolean',
					'description' => 'Set to true to confirm deletion. Required to prevent accidental deletions.',
					'default'     => false,
				),
			),
			'required'             => array( 'recipe_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @param User_Context $user_context User context.
	 * @param array        $params       Tool parameters.
	 * @return array Response array.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$recipe_id = $params['recipe_id'] ?? null;

		if ( empty( $recipe_id ) || (int) $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response(
				'recipe_id is required and must be a positive integer.'
			);
		}

		$recipe_id = (int) $recipe_id;
		$confirm   = $params['confirm'] ?? false;

		// If not confirmed, return what would be deleted as an error so the LLM retries with confirm=true.
		if ( ! $confirm ) {
			$current = $this->service->get_user_selector( $recipe_id );

			if ( is_wp_error( $current ) ) {
				return Json_Rpc_Response::create_error_response( $current->get_error_message() );
			}

			if ( null === $current['data'] ) {
				return Json_Rpc_Response::create_error_response(
					'No user selector configured for this recipe. Nothing to delete.'
				);
			}

			return Json_Rpc_Response::create_error_response(
				'Deletion requires confirmation. Call delete_user_selector again with confirm=true to proceed.',
				array(
					'will_delete' => $current['data'],
					'example'     => array(
						'recipe_id' => $recipe_id,
						'confirm'   => true,
					),
				)
			);
		}

		$result = $this->service->delete_user_selector( $recipe_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			$result['message'],
			array(
				'recipe_id' => $recipe_id,
				'hint'      => 'User selector removed. Actions in this recipe will have no user context until you configure a new user selector.',
			)
		);
	}
}
