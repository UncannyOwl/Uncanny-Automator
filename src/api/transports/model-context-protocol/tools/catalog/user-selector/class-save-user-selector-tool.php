<?php
/**
 * Save User Selector MCP Tool.
 *
 * Creates or updates a user selector configuration for a recipe.
 * User selectors determine which user actions execute on in anonymous recipes.
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
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;

/**
 * Save_User_Selector_Tool Class.
 *
 * MCP tool for creating or updating user selector configuration.
 */
class Save_User_Selector_Tool extends Abstract_MCP_Tool {

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
		return 'save_user_selector';
	}

	/**
	 * Get tool description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Configure which user actions execute on in a recipe. Required for anonymous recipes needing user context.

TWO MODES AVAILABLE:

MODE 1: EXISTING USER (find user by field)
Required params: recipe_id, source="existingUser", unique_field, unique_field_value
Optional: fallback ("create-new-user" or "do-nothing"), user_data (if fallback=create-new-user)

Example - Find user by email from trigger:
{
  "recipe_id": 123,
  "source": "existingUser",
  "unique_field": "email",
  "unique_field_value": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here.",
}

Example - Find user, create if not found:
{
  "recipe_id": 123,
  "source": "existingUser",
  "unique_field": "email",
  "unique_field_value": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here.",
  "fallback": "create-new-user",
  "user_data": {
    "email": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here.",
    "username": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here.",
    "role": "subscriber"
  }
}

MODE 2: NEW USER (create user)
Required params: recipe_id, source="newUser", user_data (with email OR username)
Optional: fallback ("select-existing-user" or "do-nothing"), prioritized_field (required if fallback=select-existing-user)

Example - Create new user:
{
  "recipe_id": 123,
  "source": "newUser",
  "user_data": {
    "email": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here.",
    "username": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here.",
    "firstName": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here.",
    "role": "subscriber"
  }
}

Example - Create user, use existing if duplicate:
{
  "recipe_id": 123,
  "source": "newUser",
  "user_data": {
    "email": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here.",
    "username": "hint: use get_recipe_tokens to check available tokens or you may also use literal values here."
  },
  "fallback": "select-existing-user",
  "prioritized_field": "email"
}

TOKEN FORMAT: Use the correct token format for your use case. Use get_recipe_tokens tool to see available tokens.';
	}

	/**
	 * Define input schema.
	 *
	 * Uses flat schema for Anthropic API compatibility (doesn't support oneOf at top level).
	 * Runtime validation in validate_params() handles source-specific requirements.
	 *
	 * @return array
	 */
	protected function schema_definition(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'recipe_id'          => array(
					'type'        => 'integer',
					'description' => 'Recipe ID to configure.',
					'minimum'     => 1,
				),
				'source'             => array(
					'type'        => 'string',
					'description' => 'User source mode: "existingUser" (find by field) or "newUser" (create user).',
					'enum'        => array( 'existingUser', 'newUser' ),
				),
				'unique_field'       => array(
					'type'        => 'string',
					'description' => 'For existingUser: Field to identify user.',
					'enum'        => array( 'email', 'id', 'username' ),
				),
				'unique_field_value' => array(
					'type'        => 'string',
					'description' => 'For existingUser: Value to match. Use the tool get_recipe_tokens to check available tokens or you may also use literal values here.',
				),
				'fallback'           => array(
					'type'        => 'string',
					'description' => 'Fallback behavior. existingUser: "create-new-user" or "do-nothing". newUser: "select-existing-user" or "do-nothing".',
					'enum'        => array( 'create-new-user', 'do-nothing', 'select-existing-user' ),
				),
				'prioritized_field'  => array(
					'type'        => 'string',
					'description' => 'For newUser with fallback=select-existing-user: Which field to check first.',
					'enum'        => array( 'email', 'username' ),
				),
				'user_data'          => $this->get_user_data_schema( 'User data object. Required for newUser, optional for existingUser with create-new-user fallback.' ),
			),
			'required'             => array( 'recipe_id', 'source' ),
		);
	}

	/**
	 * Get user data schema.
	 *
	 * @param string $description Description for the user_data object.
	 * @return array
	 */
	private function get_user_data_schema( string $description ): array {
		return array(
			'type'        => 'object',
			'description' => $description,
			'properties'  => array(
				'email'       => array(
					'type'        => 'string',
					'description' => 'User email. Use the tool get_recipe_tokens to check available tokens or you may also use literal values here.',
				),
				'username'    => array(
					'type'        => 'string',
					'description' => 'User login name. Use the tool get_recipe_tokens to check available tokens or you may also use literal values here.',
				),
				'firstName'   => array(
					'type'        => 'string',
					'description' => 'User first name.',
				),
				'lastName'    => array(
					'type'        => 'string',
					'description' => 'User last name.',
				),
				'displayName' => array(
					'type'        => 'string',
					'description' => 'User display name.',
				),
				'password'    => array(
					'type'        => 'string',
					'description' => 'User password. Leave empty to auto-generate.',
				),
				'role'        => array(
					'type'        => 'string',
					'description' => 'WordPress role: subscriber, contributor, author, editor, administrator.',
					'default'     => 'subscriber',
				),
				'logUserIn'   => array(
					'type'        => 'boolean',
					'description' => 'Log user in after creation.',
					'default'     => false,
				),
			),
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

		// Pre-validation with helpful error messages.
		$validation_error = $this->validate_params( $params );
		if ( null !== $validation_error ) {
			return $validation_error;
		}

		$recipe_id = (int) $params['recipe_id'];

		// Validate tokens in user selector fields before proceeding.
		$fields_to_validate = $this->extract_token_fields( $params );
		$validation         = Token_Validator::validate( $recipe_id, $fields_to_validate );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		// Build data array from params.
		$data = $this->build_data_from_params( $params );

		$result = $this->service->save_user_selector( $recipe_id, $data );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		return Json_Rpc_Response::create_success_response(
			$result['message'],
			array_merge(
				$result['data'],
				array(
					'next_steps' => $this->get_next_steps( $params['source'] ),
				)
			)
		);
	}

	/**
	 * Validate parameters with helpful error messages.
	 *
	 * @param array $params Tool parameters.
	 * @return array|null Error response or null if valid.
	 */
	private function validate_params( array $params ): ?array {
		// Validate recipe_id.
		$recipe_id = $params['recipe_id'] ?? null;
		if ( empty( $recipe_id ) || (int) $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response(
				'recipe_id is required and must be a positive integer.'
			);
		}

		// Validate source.
		$source = $params['source'] ?? null;
		if ( empty( $source ) ) {
			return Json_Rpc_Response::create_error_response(
				'source is required. Use "existingUser" to find a user, or "newUser" to create one.'
			);
		}

		if ( ! in_array( $source, array( 'existingUser', 'newUser' ), true ) ) {
			return Json_Rpc_Response::create_error_response(
				'source must be exactly "existingUser" or "newUser" (case-sensitive). Got: "' . $source . '"'
			);
		}

		// Source-specific validation.
		if ( 'existingUser' === $source ) {
			return $this->validate_existing_user_params( $params );
		}

		return $this->validate_new_user_params( $params );
	}

	/**
	 * Validate existingUser params.
	 *
	 * @param array $params Tool parameters.
	 * @return array|null Error response or null if valid.
	 */
	private function validate_existing_user_params( array $params ): ?array {
		// unique_field is required.
		$unique_field = $params['unique_field'] ?? null;
		if ( empty( $unique_field ) ) {
			return Json_Rpc_Response::create_error_response(
				'existingUser mode requires unique_field. Use "email", "id", or "username".'
			);
		}

		if ( ! in_array( $unique_field, array( 'email', 'id', 'username' ), true ) ) {
			return Json_Rpc_Response::create_error_response(
				'unique_field must be "email", "id", or "username" (lowercase). Got: "' . $unique_field . '"'
			);
		}

		// unique_field_value is required.
		$unique_field_value = $params['unique_field_value'] ?? null;
		if ( empty( $unique_field_value ) ) {
			return Json_Rpc_Response::create_error_response(
				'existingUser mode requires unique_field_value. Use the tool get_recipe_tokens to check available tokens or you may also use literal values here.'
			);
		}

		// Validate fallback if provided.
		$fallback = $params['fallback'] ?? null;
		if ( null !== $fallback && ! in_array( $fallback, array( 'create-new-user', 'do-nothing' ), true ) ) {
			return Json_Rpc_Response::create_error_response(
				'For existingUser, fallback must be "create-new-user" or "do-nothing". Got: "' . $fallback . '". ' .
				'Note: "select-existing-user" is only valid for newUser mode.'
			);
		}

		// If fallback is create-new-user, user_data is required.
		if ( 'create-new-user' === $fallback ) {
			$user_data = $params['user_data'] ?? array();
			if ( empty( $user_data['email'] ) && empty( $user_data['username'] ) ) {
				return Json_Rpc_Response::create_error_response(
					'When fallback="create-new-user", user_data must include email or username for creating the new user.'
				);
			}
		}

		// Warn about ignored params.
		if ( isset( $params['prioritized_field'] ) ) {
			// Don't fail, but this will be ignored.
			// Could log a warning or include in response.
		}

		return null;
	}

	/**
	 * Validate newUser params.
	 *
	 * @param array $params Tool parameters.
	 * @return array|null Error response or null if valid.
	 */
	private function validate_new_user_params( array $params ): ?array {
		// user_data is required.
		$user_data = $params['user_data'] ?? null;
		if ( empty( $user_data ) || ! is_array( $user_data ) ) {
			return Json_Rpc_Response::create_error_response(
				'newUser mode requires user_data object with at least email or username.'
			);
		}

		// Must have email or username.
		if ( empty( $user_data['email'] ) && empty( $user_data['username'] ) ) {
			return Json_Rpc_Response::create_error_response(
				'newUser user_data must include email or username (or both). Use the tool get_recipe_tokens to check available tokens or you may also use literal values here.'
			);
		}

		// Validate fallback if provided.
		$fallback = $params['fallback'] ?? null;
		if ( null !== $fallback && ! in_array( $fallback, array( 'select-existing-user', 'do-nothing' ), true ) ) {
			return Json_Rpc_Response::create_error_response(
				'For newUser, fallback must be "select-existing-user" or "do-nothing". Got: "' . $fallback . '". ' .
				'Note: "create-new-user" is only valid for existingUser mode.'
			);
		}

		// If fallback is select-existing-user, prioritized_field is required.
		if ( 'select-existing-user' === $fallback ) {
			$prioritized_field = $params['prioritized_field'] ?? null;
			if ( empty( $prioritized_field ) ) {
				return Json_Rpc_Response::create_error_response(
					'When fallback="select-existing-user", prioritized_field is required. Use "email" or "username".'
				);
			}

			if ( ! in_array( $prioritized_field, array( 'email', 'username' ), true ) ) {
				return Json_Rpc_Response::create_error_response(
					'prioritized_field must be "email" or "username" (lowercase). Got: "' . $prioritized_field . '"'
				);
			}
		}

		return null;
	}

	/**
	 * Build data array from params.
	 *
	 * @param array $params Tool parameters.
	 * @return array Data array for service.
	 */
	private function build_data_from_params( array $params ): array {
		$data = array(
			'source' => $params['source'],
		);

		if ( isset( $params['unique_field'] ) ) {
			$data['unique_field'] = $params['unique_field'];
		}

		if ( isset( $params['unique_field_value'] ) ) {
			$data['unique_field_value'] = $params['unique_field_value'];
		}

		if ( isset( $params['fallback'] ) ) {
			$data['fallback'] = $params['fallback'];
		}

		if ( isset( $params['prioritized_field'] ) ) {
			$data['prioritized_field'] = $params['prioritized_field'];
		}

		if ( isset( $params['user_data'] ) && is_array( $params['user_data'] ) ) {
			$data['user_data'] = $params['user_data'];
		}

		return $data;
	}

	/**
	 * Extract fields that may contain tokens for validation.
	 *
	 * @param array $params Tool parameters.
	 * @return array Fields to validate for tokens.
	 */
	private function extract_token_fields( array $params ): array {
		$fields = array();

		if ( isset( $params['unique_field_value'] ) ) {
			$fields['unique_field_value'] = $params['unique_field_value'];
		}

		// user_data fields can contain tokens.
		if ( isset( $params['user_data'] ) && is_array( $params['user_data'] ) ) {
			foreach ( $params['user_data'] as $key => $value ) {
				// Only validate string values that could contain tokens.
				if ( is_string( $value ) && ! empty( $value ) ) {
					$fields[ 'user_data_' . $key ] = $value;
				}
			}
		}

		return $fields;
	}

	/**
	 * Get next steps guidance.
	 *
	 * @param string $source Source type.
	 * @return array Next steps.
	 */
	private function get_next_steps( string $source ): array {
		$steps = array(
			'verify' => array(
				'tool'   => 'get_user_selector',
				'hint'   => 'Verify the user selector was saved correctly.',
			),
		);

		if ( 'existingUser' === $source ) {
			$steps['test_user_lookup'] = array(
				'hint' => 'Test with a real email/id/username to ensure user matching works.',
			);
		} else {
			$steps['test_user_creation'] = array(
				'hint' => 'Test with real data to ensure user creation works.',
			);
		}

		return $steps;
	}
}
