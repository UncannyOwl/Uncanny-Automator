<?php
/**
 * Consolidated condition upsert tool.
 *
 * Replaces: add_condition, update_condition.
 * condition_id absent = create, condition_id present = update.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Conditions;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are internal errors, not user-facing output.

use Uncanny_Automator\Api\Components\Condition\Registry\WP_Action_Condition_Registry;
use Uncanny_Automator\Api\Components\Shared\Polyfill\Str;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Field\Field_Mcp_Input_Resolver;
use Uncanny_Automator\Api\Services\Field\Utilities\Field_Validator;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Condition_Service;
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Save Condition Tool — upsert.
 *
 * Create: omit condition_id. Requires recipe_id, group_id, integration_code, condition_code, fields (non-empty).
 * Update: include condition_id. Requires recipe_id, group_id, condition_id, fields (non-empty).
 *
 * @since 7.1.0
 */
class Save_Condition_Tool extends Abstract_MCP_Tool {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'save_condition';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Create or update a condition within a group. Omit condition_id to create, include condition_id to update. '
			. 'Create requires recipe_id, group_id, integration_code, condition_code, and non-empty fields. '
			. 'Update requires recipe_id, group_id, condition_id, and fields (merged with existing). '
			. 'Get field definitions from get_component_schema with component_type="condition".';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'idempotentHint'  => false,
			'openWorldHint'   => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'        => array(
					'type'        => 'integer',
					'description' => 'Recipe ID. Required for both create and update.',
					'minimum'     => 1,
				),
				'group_id'         => array(
					'type'        => 'string',
					'description' => 'Condition group ID. Required for both create and update.',
					'minLength'   => 1,
				),
				'condition_id'     => array(
					'type'        => 'string',
					'description' => 'Existing condition ID to update. Omit to create a new condition.',
					'minLength'   => 1,
				),
				'integration_code' => array(
					'type'        => 'string',
					'description' => 'Integration code (e.g., "WP", "GEN", "LD"). Required for create. Use search to discover condition types.',
					'minLength'   => 2,
				),
				'condition_code'   => array(
					'type'        => 'string',
					'description' => 'Condition type code (e.g., "TOKEN_MEETS_CONDITION"). Required for create.',
					'minLength'   => 2,
				),
				'fields'           => array(
					'type'                 => 'object',
					'description'          => 'Condition field values. Required and must be non-empty for both create and update. On update, merged with existing fields.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'recipe_id', 'group_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'        => array( 'type' => 'integer' ),
				'group_id'         => array( 'type' => 'string' ),
				'condition_id'     => array( 'type' => 'string' ),
				'integration'      => array( 'type' => 'string' ),
				'condition_code'   => array( 'type' => 'string' ),
				'total_conditions' => array( 'type' => 'integer' ),
				'provided_fields'  => array( 'type' => 'object' ),
				'merged_fields'    => array( 'type' => 'object' ),
			),
			'required'   => array( 'recipe_id', 'group_id', 'condition_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$condition_id = isset( $params['condition_id'] ) ? (string) $params['condition_id'] : null;

		if ( null !== $condition_id && '' !== $condition_id ) {
			return $this->update_condition( $params );
		}

		return $this->create_condition( $params );
	}

	// ──────────────────────────────────────────────────────────────────
	// CREATE PATH — port of Add_Condition_Tool
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Create a new condition in a group.
	 *
	 * @param array $params Tool parameters.
	 * @return array JSON-RPC response.
	 */
	private function create_condition( array $params ): array {

		$recipe_id        = (int) ( $params['recipe_id'] ?? 0 );
		$group_id         = (string) ( $params['group_id'] ?? '' );
		$integration_code = (string) ( $params['integration_code'] ?? '' );
		$condition_code   = (string) ( $params['condition_code'] ?? '' );
		$fields           = is_array( $params['fields'] ?? null ) ? $params['fields'] : array();

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'recipe_id is required.' );
		}

		if ( '' === $group_id ) {
			return Json_Rpc_Response::create_error_response( 'group_id is required.' );
		}

		if ( '' === $integration_code || '' === $condition_code ) {
			return Json_Rpc_Response::create_error_response( 'integration_code and condition_code are required for creating a condition.' );
		}

		if ( empty( $fields ) ) {
			return Json_Rpc_Response::create_error_response( 'fields is required and must be non-empty for creating a condition. Use get_component_schema to discover required fields.' );
		}

		// Flatten {value, readable} objects to flat strings.
		// Conditions expect flat string values (e.g. "TOKEN": "{{token}}"),
		// not object values (e.g. "TOKEN": {"value": "{{token}}", "readable": "..."}).
		$fields = Field_Mcp_Input_Resolver::flatten( $fields );

		// Validate field values against condition schema.
		$field_validation = $this->validate_condition_fields( $integration_code, $condition_code, $fields );
		if ( is_wp_error( $field_validation ) ) {
			return Json_Rpc_Response::create_error_response( $field_validation->get_error_message() );
		}

		// Validate tokens in fields.
		$token_validation = Token_Validator::validate( $recipe_id, $fields );
		if ( ! $token_validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $token_validation['message'] );
		}

		try {
			$service = Recipe_Condition_Service::instance();
			$result  = $service->add_condition_to_group( $recipe_id, $group_id, $integration_code, $condition_code, $fields );

			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}

			$payload = array(
				'recipe_id'        => $recipe_id,
				'group_id'         => $result['group_id'] ?? $group_id,
				'condition_id'     => $result['condition_id'] ?? '',
				'integration'      => $result['integration'] ?? $integration_code,
				'condition_code'   => $result['condition_code'] ?? $condition_code,
				'total_conditions' => isset( $result['total_conditions'] ) ? (int) $result['total_conditions'] : 0,
			);

			return Json_Rpc_Response::create_success_response( 'Condition added to group', $payload );

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to add condition: ' . $e->getMessage() );
		}
	}

	// ──────────────────────────────────────────────────────────────────
	// UPDATE PATH — port of Update_Condition_Tool
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Update an existing condition's fields.
	 *
	 * Normalizes MCP field input, merges with existing condition fields,
	 * validates tokens, and delegates persistence to Recipe_Condition_Service.
	 *
	 * @param array $params Tool parameters.
	 * @return array JSON-RPC response.
	 */
	private function update_condition( array $params ): array {

		$recipe_id    = (int) ( $params['recipe_id'] ?? 0 );
		$group_id     = (string) ( $params['group_id'] ?? '' );
		$condition_id = (string) ( $params['condition_id'] ?? '' );
		$fields       = is_array( $params['fields'] ?? null ) ? $params['fields'] : array();

		if ( $recipe_id <= 0 || '' === $group_id || '' === $condition_id ) {
			return Json_Rpc_Response::create_error_response( 'recipe_id, group_id, and condition_id are required for updating a condition.' );
		}

		if ( empty( $fields ) ) {
			return Json_Rpc_Response::create_error_response( 'fields is required and must be non-empty.' );
		}

		// Flatten {value, readable} objects to flat strings.
		$fields = Field_Mcp_Input_Resolver::flatten( $fields );

		try {
			$service = Recipe_Condition_Service::instance();

			// Retrieve existing condition data for merging and validation.
			$existing_condition = $this->find_existing_condition( $service, $recipe_id, $group_id, $condition_id );
			if ( is_wp_error( $existing_condition ) ) {
				return Json_Rpc_Response::create_error_response( $existing_condition->get_error_message() );
			}

			$existing_fields = is_array( $existing_condition['fields'] ?? null ) ? $existing_condition['fields'] : array();

			// Merge: new fields override existing fields.
			$merged_fields = array_merge( $existing_fields, $fields );

			// Validate field values against condition schema.
			$integration_code = (string) ( $existing_condition['integration_code'] ?? $params['integration_code'] ?? '' );
			$condition_code   = (string) ( $existing_condition['condition_code'] ?? $params['condition_code'] ?? '' );

			if ( '' !== $integration_code && '' !== $condition_code ) {
				$field_validation = $this->validate_condition_fields( $integration_code, $condition_code, $merged_fields );
				if ( is_wp_error( $field_validation ) ) {
					return Json_Rpc_Response::create_error_response( $field_validation->get_error_message() );
				}
			}

			// Validate tokens in merged fields.
			$token_validation = Token_Validator::validate( $recipe_id, $merged_fields );
			if ( ! $token_validation['valid'] ) {
				return Json_Rpc_Response::create_error_response( $token_validation['message'] );
			}

			// Delegate to service for persistence.
			$result = $service->update_condition( $condition_id, $group_id, $recipe_id, $merged_fields );

			if ( is_wp_error( $result ) ) {
				return Json_Rpc_Response::create_error_response( $result->get_error_message() );
			}

			$payload = array(
				'recipe_id'       => $recipe_id,
				'group_id'        => $group_id,
				'condition_id'    => $condition_id,
				'provided_fields' => $fields,
				'merged_fields'   => $merged_fields,
			);

			return Json_Rpc_Response::create_success_response( 'Condition updated', $payload );

		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to update condition: ' . $e->getMessage() );
		}
	}

	/**
	 * Retrieve existing condition fields from the recipe condition data.
	 *
	 * Uses the query service to fetch condition groups, then locates the
	 * target condition's fields by group_id and condition_id.
	 *
	 * @param Recipe_Condition_Service $service      Condition service instance.
	 * @param int                      $recipe_id    Recipe ID.
	 * @param string                   $group_id     Condition group ID.
	 * @param string                   $condition_id Condition ID.
	 * @return array|\WP_Error Existing fields array or WP_Error on failure.
	 */
	private function get_existing_condition_fields( Recipe_Condition_Service $service, int $recipe_id, string $group_id, string $condition_id ) {
		$condition = $this->find_existing_condition( $service, $recipe_id, $group_id, $condition_id );

		if ( is_wp_error( $condition ) ) {
			return $condition;
		}

		return is_array( $condition['fields'] ?? null ) ? $condition['fields'] : array();
	}

	/**
	 * Find an existing condition in a recipe's condition groups.
	 *
	 * @param Recipe_Condition_Service $service      Condition service instance.
	 * @param int                      $recipe_id    Recipe ID.
	 * @param string                   $group_id     Condition group ID.
	 * @param string                   $condition_id Condition ID.
	 * @return array|\WP_Error Full condition data array or WP_Error on failure.
	 */
	private function find_existing_condition( Recipe_Condition_Service $service, int $recipe_id, string $group_id, string $condition_id ) {
		$conditions_data = $service->get_recipe_conditions( $recipe_id );

		if ( is_wp_error( $conditions_data ) ) {
			return $conditions_data;
		}

		$groups = $conditions_data['condition_groups'] ?? array();

		foreach ( $groups as $group ) {
			$gid = (string) ( $group['id'] ?? '' );
			if ( $gid !== $group_id ) {
				continue;
			}

			foreach ( $group['conditions'] ?? array() as $condition ) {
				$cid = (string) ( $condition['id'] ?? '' );
				if ( $cid === $condition_id ) {
					return $condition;
				}
			}

			return new \WP_Error( 'condition_not_found', 'Condition not found in group.' );
		}

		return new \WP_Error( 'group_not_found', 'Condition group not found.' );
	}

	/**
	 * Validate condition field values against the condition's field schema.
	 *
	 * Retrieves field definitions from the condition registry and delegates
	 * to Field_Validator for type, enum, and required-field validation.
	 * Tokens and custom values are handled by Field_Validator's built-in rules.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $condition_code   Condition code.
	 * @param array  $fields           Normalized flat fields to validate.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_condition_fields( string $integration_code, string $condition_code, array $fields ) {

		$registry          = new WP_Action_Condition_Registry();
		$field_definitions = $registry->get_raw_condition_fields( $integration_code, $condition_code );

		if ( empty( $field_definitions ) ) {
			return true;
		}

		// Strip _readable and _label suffixes from the config before validation —
		// these are presentation metadata, not real fields.
		$fields_for_validation = array_filter(
			$fields,
			function ( $key ) {
				return ! Str::ends_with( $key, '_readable' ) && ! Str::ends_with( $key, '_label' );
			},
			ARRAY_FILTER_USE_KEY
		);

		$validator = new Field_Validator();

		return $validator->validate_fields( $field_definitions, $fields_for_validation, $condition_code, 'condition' );
	}

	/**
	 * Normalize condition fields — flatten {value, readable} objects to flat strings.
	 *
	 * Conditions expect flat string values:
	 *   "TOKEN": "{{user_role}}", "CRITERIA": "is", "VALUE": "customer"
	 *
	 * AI agents may send {value, readable} objects (same format as loop filters):
	 *   "TOKEN": {"value": "{{user_role}}", "readable": "User role"}
	 *
	 * This method flattens objects to their .value string and generates _readable
	 * and _label suffixes automatically.
	 *
	 * @param array $fields Raw fields from the tool params.
	 * @return array Normalized fields with flat string values.
	 */
}
