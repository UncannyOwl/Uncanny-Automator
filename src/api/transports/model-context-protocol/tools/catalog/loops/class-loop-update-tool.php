<?php
/**
 * MCP tool for updating a loop.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Loop Update Tool.
 *
 * Updates an existing loop's configuration.
 * Extends Abstract_Loop_Tool for shared logic.
 *
 * @since 7.0.0
 */
class Loop_Update_Tool extends Abstract_Loop_Tool {

	/**
	 * Loop CRUD service.
	 *
	 * @var Loop_CRUD_Service
	 */
	private Loop_CRUD_Service $loop_service;

	/**
	 * Constructor.
	 *
	 * @param Loop_CRUD_Service|null $loop_service Optional loop service instance.
	 */
	public function __construct( ?Loop_CRUD_Service $loop_service = null ) {
		$this->loop_service = $loop_service ?? Loop_CRUD_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'loop_update';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Update a loop\'s configuration fields or loopable token. The loop type (users/posts/token) cannot be changed after creation — delete and recreate the loop to change type. The TOKEN field is reserved for token-type loops and will be overridden if provided.';
	}

	/**
	 * Define the input schema.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for parameters.
	 */
	protected function schema_definition() {
		$loopable_token_schema = array(
			'type'        => 'string',
			'description' => 'Required when changing type to token. Select a loopable token to iterate over.',
		);

		// Add enum with available loopable tokens.
		$loopable_tokens = $this->get_loopable_token_enum();
		if ( ! empty( $loopable_tokens ) ) {
			$loopable_token_schema['enum'] = $loopable_tokens;
		}

		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'      => array(
					'type'        => 'integer',
					'description' => 'Recipe ID the loop belongs to.',
					'minimum'     => 1,
				),
				'loop_id'        => array(
					'type'        => 'integer',
					'description' => 'Loop ID to update.',
					'minimum'     => 1,
				),
				'loopable_token' => $loopable_token_schema,
				'fields'         => array(
					'type'                 => 'object',
					'description'          => 'Additional configuration fields. Merged with existing fields. Do NOT include TOKEN — it is reserved and managed automatically via loopable_token.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'recipe_id', 'loop_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters.
	 * @return array MCP response.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		// 1. Fetch existing state.
		$existing_loop  = $this->get_existing_loop( (int) $params['loop_id'] );
		$existing_type  = $existing_loop['type'] ?? '';
		$effective_type = $existing_type;

		// 2. Determine loopable_token (single source of truth for token-type loops).
		$loopable_token = $params['loopable_token'] ?? '';
		if ( empty( $loopable_token ) && 'token' === $effective_type ) {
			$loopable_token = $this->extract_loopable_token_from_fields( $existing_loop['fields'] ?? '' );
		}

		// 3. Validate state transition: token type MUST have loopable_token.
		if ( 'token' === $effective_type && empty( $loopable_token ) ) {
			return Json_Rpc_Response::create_error_response(
				'loopable_token is required for token-type loops. Provide loopable_token or change type to users/posts.'
			);
		}

		// 4. Build fields: preserve existing, merge passed, enforce TOKEN invariant.
		$existing_fields = $this->parse_json_param( $existing_loop['fields'] ?? '' );
		$passed_fields   = $this->parse_json_param( $params['fields'] ?? array() );
		$token_warning   = '';

		// Warn if TOKEN field was explicitly provided — it will be overridden.
		if ( 'token' === $effective_type && array_key_exists( 'TOKEN', $passed_fields ) ) {
			$token_warning = 'TOKEN field is reserved and was overridden by the loopable_token value. Do not include TOKEN in fields.';
			unset( $passed_fields['TOKEN'] );
		}

		$fields = array_merge( $existing_fields, $passed_fields );

		// For token type: ALWAYS set TOKEN from loopable_token (enforces invariant).
		if ( 'token' === $effective_type ) {
			$token_value = '{{' . $loopable_token . '}}';

			$fields['TOKEN'] = array(
				'type'   => 'text',
				'value'  => $token_value,
				'backup' => array(
					'label'                  => 'Token',
					'show_label_in_sentence' => false,
				),
			);

			// Validate the loopable token exists in the recipe context.
			// Defense in depth: AI may compose arbitrary values despite enum constraint.
			$recipe_id  = (int) $params['recipe_id'];
			$validation = Token_Validator::validate( $recipe_id, array( 'TOKEN' => $token_value ) );
			if ( ! $validation['valid'] ) {
				return Json_Rpc_Response::create_error_response( $validation['message'] );
			}
		}

		// 5. Build iterable_expression.
		$iterable_expression = array();

		// Always update fields for token type (ensures TOKEN stays in sync).
		// Also update if user passed fields explicitly.
		if ( 'token' === $effective_type || array_key_exists( 'fields', $params ) ) {
			$iterable_expression['fields'] = wp_json_encode( $fields );
		}

		// 6. Handle backup for token-type loops.
		if ( 'token' === $effective_type ) {
			$backup                        = $this->build_token_backup( $loopable_token );
			$iterable_expression['backup'] = wp_json_encode( $backup );
		}

		$result = $this->loop_service->update_loop(
			(int) $params['loop_id'],
			$iterable_expression,
			null // Status is not changeable - loops are always published.
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use loop_get with loop_id to verify the loop exists.'
			);
		}

		$loop      = $result['loop'] ?? array();
		$recipe_id = (int) ( $loop['recipe_id'] ?? 0 );

		$payload = array(
			'loop_id'   => (int) $params['loop_id'],
			'loop'      => $loop,
			'recipe_id' => $recipe_id,
			'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
		);

		if ( ! empty( $token_warning ) ) {
			$payload['warnings'] = array( $token_warning );
		}

		return Json_Rpc_Response::create_success_response( 'Loop updated successfully', $payload );
	}

	/**
	 * Get existing loop data.
	 *
	 * @param int $loop_id Loop ID.
	 * @return array Loop data with type and fields.
	 */
	private function get_existing_loop( int $loop_id ): array {
		$iterable_expression = get_post_meta( $loop_id, 'iterable_expression', true );

		if ( ! is_array( $iterable_expression ) ) {
			return array();
		}

		return array(
			'type'   => $iterable_expression['type'] ?? '',
			'fields' => $iterable_expression['fields'] ?? '',
		);
	}

	/**
	 * Extract loopable token ID from existing fields.
	 *
	 * @param string $fields_json JSON string of fields.
	 * @return string Loopable token ID or empty string.
	 */
	private function extract_loopable_token_from_fields( string $fields_json ): string {
		if ( empty( $fields_json ) ) {
			return '';
		}

		$fields = json_decode( $fields_json, true );

		if ( ! is_array( $fields ) || empty( $fields['TOKEN']['value'] ) ) {
			return '';
		}

		// Extract token ID from {{TOKEN_EXTENDED:...}} format.
		$token_value = $fields['TOKEN']['value'];

		if ( preg_match( '/\{\{(.+?)\}\}/', $token_value, $matches ) ) {
			return $matches[1];
		}

		return '';
	}
}
