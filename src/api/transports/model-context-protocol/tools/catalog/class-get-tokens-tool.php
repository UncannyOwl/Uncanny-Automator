<?php
/**
 * Consolidated token retrieval tool.
 *
 * Replaces: get_recipe_tokens, loop_get_tokens.
 * Returns tokens for a recipe context, optionally scoped to a loop.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Token\Token_Catalog_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Get Tokens Tool.
 *
 * Returns tokens available in a recipe context. When loop_id is provided,
 * also includes loop iteration tokens.
 *
 * @since 7.1.0
 */
class Get_Tokens_Tool extends Abstract_MCP_Tool {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'get_tokens';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'List tokens available in a recipe for use in action fields. '
			. 'Returns universal tokens, common tokens, date/time tokens, trigger tokens, and action tokens. '
			. 'Pass loop_id to also include loop iteration tokens (user fields, post fields, or iterated data fields). '
			. 'IMPORTANT: Call again after adding triggers/actions to get their tokens. '
			. 'Tokens with requiresUser=true indicate that anonymous recipes need a user selector configured — '
			. 'but these tokens CAN and SHOULD be used in user_selector.unique_field_value. '
			. 'For example, {{user_email}} in the user selector is valid and is how anonymous recipes identify which user to run for.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Recipe ID to retrieve tokens for.',
					'minimum'     => 1,
				),
				'loop_id'   => array(
					'type'        => 'integer',
					'description' => 'Optional loop ID. When provided, also returns loop iteration tokens (the fields available inside that loop\'s actions).',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'advanced'       => array(
					'type'       => 'object',
					'properties' => array(
						'description' => array( 'type' => 'string' ),
						'tokens'      => array( 'type' => 'array' ),
					),
				),
				'common'         => array(
					'type'       => 'object',
					'properties' => array(
						'description' => array( 'type' => 'string' ),
						'tokens'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'  => array( 'type' => 'string' ),
									'usage' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'date-and-time'  => array(
					'type'       => 'object',
					'properties' => array(
						'description' => array( 'type' => 'string' ),
						'tokens'      => array( 'type' => 'array' ),
					),
				),
				'trigger-tokens' => array( 'type' => 'object' ),
				'action-tokens'  => array( 'type' => 'object' ),
				'loop-tokens'    => array(
					'type'       => 'object',
					'properties' => array(
						'description' => array( 'type' => 'string' ),
						'loop_id'     => array( 'type' => 'integer' ),
						'loop_type'   => array( 'type' => 'string' ),
						'tokens'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'  => array( 'type' => 'string' ),
									'usage' => array( 'type' => 'string' ),
									'type'  => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
			),
			'required'   => array( 'advanced', 'common', 'date-and-time', 'trigger-tokens', 'action-tokens' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$recipe_id = (int) ( $params['recipe_id'] ?? 0 );
		$loop_id   = isset( $params['loop_id'] ) ? (int) $params['loop_id'] : null;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter recipe_id must be a positive integer.' );
		}

		$service = new Token_Catalog_Service();
		$result  = $service->get_tokens_for_recipe( $recipe_id, $loop_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$message = 'Recipe tokens retrieved successfully';
		if ( null !== $loop_id ) {
			$message .= sprintf( ' (including loop %d tokens)', $loop_id );
		}

		return Json_Rpc_Response::create_success_response( $message, $result );
	}
}
