<?php
/**
 * MCP tool for adding a loop to a recipe.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;
use Uncanny_Automator\Api\Components\Loop\Enums\Loop_Status;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Loop Add Tool.
 *
 * Adds a new loop to a recipe. Loops iterate over users, posts, or tokens.
 * Extends Abstract_Loop_Tool for shared logic.
 *
 * @since 7.0.0
 */
class Loop_Add_Tool extends Abstract_Loop_Tool {

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
		return 'loop_add';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Add a new loop to a recipe. Loops iterate over users, posts, or tokens to perform actions on each item. Loops are always published and will execute when the recipe runs. If a loop has no actions inside it, it simply won\'t run any actions.';
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
			'description' => 'Required when type=token. Select a loopable token to iterate over.',
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
					'description' => 'Recipe ID to add the loop to.',
					'minimum'     => 1,
				),
				'type'           => array(
					'type'        => 'string',
					'description' => 'Iteration type: users (iterate over WordPress users), posts (iterate over posts/CPTs), or token (iterate over array from loopable_token field).',
					'enum'        => array( 'users', 'posts', 'token' ),
				),
				'loopable_token' => $loopable_token_schema,
				'fields'         => array(
					'type'                 => 'object',
					'description'          => 'Additional configuration fields. For users: user_roles, user_ids. For posts: post_type, post_status.',
					'additionalProperties' => true,
				),
				'filters'        => array(
					'type'        => 'array',
					'description' => 'Initial filters to apply. Each filter needs code, integration_code, and fields.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'code'             => array( 'type' => 'string' ),
							'integration_code' => array( 'type' => 'string' ),
							'fields'           => array( 'type' => 'object' ),
						),
					),
				),
			),
			'required'   => array( 'recipe_id', 'type' ),
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

		// Validate: loopable_token is required when type=token.
		if ( 'token' === $params['type'] && empty( $params['loopable_token'] ) ) {
			return Json_Rpc_Response::create_error_response(
				'loopable_token is required when type=token. Select a loopable token from the enum.'
			);
		}

		$fields = $this->parse_json_param( $params['fields'] ?? array() );

		// For token type, set the TOKEN field from loopable_token param.
		if ( 'token' === $params['type'] && ! empty( $params['loopable_token'] ) ) {
			$token_id    = $params['loopable_token'];
			$token_value = '{{' . $token_id . '}}';

			$fields['TOKEN'] = array(
				'type'   => 'text',
				'value'  => $token_value,
				'backup' => array(
					'label'                => 'Token',
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

		// Legacy code expects 'fields' as a JSON string, not an array.
		$iterable_expression = array(
			'type'   => $params['type'],
			'fields' => wp_json_encode( $fields ),
		);

		// For token loops, add backup with sentence_html for UI display.
		if ( 'token' === $params['type'] && ! empty( $params['loopable_token'] ) ) {
			$backup = $this->build_token_backup( $params['loopable_token'] );
			if ( ! empty( $backup ) ) {
				$iterable_expression['backup'] = wp_json_encode( $backup );
			}
		}

		// Loops are always published. Empty loops simply won't run any actions.
		$status  = Loop_Status::PUBLISH;
		$filters = $this->parse_json_param( $params['filters'] ?? array() );

		$result = $this->loop_service->add_to_recipe(
			(int) $params['recipe_id'],
			$iterable_expression,
			$status,
			$filters
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use list_recipes to verify the recipe exists and get_recipe to check its current loops.'
			);
		}

		$recipe_id = (int) $params['recipe_id'];

		return Json_Rpc_Response::create_success_response(
			'Loop added successfully',
			array(
				'loop_id'   => $result['loop_id'] ?? 0,
				'loop'      => $result['loop'] ?? array(),
				'recipe_id' => $recipe_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
}
