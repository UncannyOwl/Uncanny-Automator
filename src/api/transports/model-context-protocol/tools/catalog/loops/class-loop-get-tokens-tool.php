<?php
/**
 * MCP tool for retrieving tokens available inside a loop.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Loop Get Tokens Tool.
 *
 * Returns tokens available for use inside a specific loop's actions.
 * Each loop type (users, posts, token) provides different tokens.
 *
 * @since 7.0.0
 */
class Loop_Get_Tokens_Tool extends Abstract_MCP_Tool {

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'loop_get_tokens';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Get tokens available inside a loop for use in action fields. Each loop type provides different tokens: user loops provide user fields, post loops provide post fields, token loops provide fields from the iterated data. Call this after adding a loop to discover available tokens.';
	}

	/**
	 * Define the input schema.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'loop_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the loop to get tokens for.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'loop_id' ),
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
		$loop_id = (int) ( $params['loop_id'] ?? 0 );

		if ( $loop_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter loop_id must be a positive integer.' );
		}

		// Verify the loop exists and is a loop post type.
		$loop_post = get_post( $loop_id );

		if ( ! $loop_post || 'uo-loop' !== $loop_post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Loop with ID %d not found. Use loop_list with recipe_id to find available loops.', $loop_id )
			);
		}

		// Get the recipe ID from the loop's parent.
		$recipe_id = (int) $loop_post->post_parent;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Could not determine recipe ID for this loop.' );
		}

		// Get the recipe object which contains loop tokens.
		$recipe_object = Automator()->get_recipe_object( $recipe_id, ARRAY_A );

		if ( empty( $recipe_object ) ) {
			return Json_Rpc_Response::create_error_response( 'Could not retrieve recipe data.' );
		}

		// Find the loop in the actions.
		$actions = $recipe_object['actions']['items'] ?? array();
		$loop_data = null;

		foreach ( $actions as $item ) {
			if ( ( $item['type'] ?? '' ) === 'loop' && ( (int) ( $item['id'] ?? 0 ) ) === $loop_id ) {
				$loop_data = $item;
				break;
			}
		}

		if ( ! $loop_data ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Loop %d not found in recipe actions. The loop may not be properly configured.', $loop_id )
			);
		}

		$loop_type   = $loop_data['iterable_expression']['type'] ?? 'unknown';
		$loop_tokens = $loop_data['tokens'] ?? array();

		// Format tokens for output.
		$formatted_tokens = array();

		foreach ( $loop_tokens as $token ) {
			$formatted_tokens[] = array(
				'name'        => $token['name'] ?? '',
				'usage'       => '{{' . ( $token['id'] ?? '' ) . '}}',
				'data_type'   => $token['data_type'] ?? 'text',
				'description' => sprintf( 'Loop token from %s loop. Use in action fields inside this loop.', $loop_type ),
			);
		}

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Found %d tokens for %s loop', count( $formatted_tokens ), $loop_type ),
			array(
				'loop_id'   => $loop_id,
				'loop_type' => $loop_type,
				'recipe_id' => $recipe_id,
				'tokens'    => $formatted_tokens,
			)
		);
	}
}
