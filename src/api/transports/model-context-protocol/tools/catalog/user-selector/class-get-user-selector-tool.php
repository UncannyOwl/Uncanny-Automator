<?php
/**
 * Get User Selector MCP Tool.
 *
 * Retrieves the user selector configuration for a recipe.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\User_Selector;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\User_Selector\User_Selector_Service;

/**
 * Get_User_Selector_Tool Class.
 *
 * MCP tool for retrieving user selector configuration.
 */
class Get_User_Selector_Tool extends Abstract_MCP_Tool {

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
		return 'get_user_selector';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Get the user selector configuration for a recipe.

Returns null if no user selector is configured.

Response structure for existingUser:
{
  "source": "existingUser",
  "unique_field": "email|id|username",
  "unique_field_value": "Use get_recipe_tokens to check available tokens or you may also use literal values here.",
  "fallback": "create-new-user|do-nothing|null",
  "user_data": {...} // only if fallback=create-new-user
}

Response structure for newUser:
{
  "source": "newUser",
  "user_data": { "email": "...", "username": "...", ... },
  "fallback": "select-existing-user|do-nothing|null",
  "prioritized_field": "email|username|null"
}';
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
					'description' => 'The recipe ID to get user selector for.',
					'minimum'     => 1,
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

		$result = $this->service->get_user_selector( $recipe_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		// Add helpful context if no user selector configured.
		if ( null === $result['data'] ) {
			return Json_Rpc_Response::create_success_response(
				$result['message'],
				array(
					'user_selector' => null,
					'hint'          => 'Use save_user_selector to configure which user actions execute on.',
					'example'       => array(
						'existingUser' => array(
							'recipe_id'          => $recipe_id,
							'source'             => 'existingUser',
							'unique_field'       => 'email',
							'unique_field_value' => 'hint: use get_recipe_tokens to check available tokens or you may also use literal values here.',
						),
						'newUser'      => array(
							'recipe_id' => $recipe_id,
							'source'    => 'newUser',
							'user_data' => array(
								'email'    => 'hint: use get_recipe_tokens to check available tokens or you may also use literal values here.',
								'username' => 'hint: use get_recipe_tokens to check available tokens or you may also use literal values here.',
							),
						),
					),
				)
			);
		}

		return Json_Rpc_Response::create_success_response(
			$result['message'],
			array(
				'user_selector' => $result['data'],
			)
		);
	}
}
