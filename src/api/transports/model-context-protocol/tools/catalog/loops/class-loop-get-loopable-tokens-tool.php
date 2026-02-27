<?php
/**
 * MCP tool for discovering loopable tokens.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services\Loopable_Token_Collector;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Loop Get Loopable Tokens Tool.
 *
 * Lists all tokens that can be used as loop sources:
 * 1. Universal loopable tokens - Always available (e.g., WC subscriptions)
 * 2. Trigger loopable tokens - From triggers in a recipe (e.g., RSS feed items)
 * 3. Action loopable tokens - From actions in a recipe
 *
 * Use this BEFORE creating a loop to find available data sources.
 * After creating a loop, use loop_get_tokens to see tokens available INSIDE the loop.
 *
 * @since 7.0.0
 */
class Loop_Get_Loopable_Tokens_Tool extends Abstract_MCP_Tool {

	/**
	 * Loopable token collector.
	 *
	 * @var Loopable_Token_Collector
	 */
	private Loopable_Token_Collector $collector;

	/**
	 * Constructor.
	 *
	 * @param Loopable_Token_Collector|null $collector Optional collector instance.
	 */
	public function __construct( ?Loopable_Token_Collector $collector = null ) {
		$this->collector = $collector ?? new Loopable_Token_Collector();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'loop_get_loopable_tokens';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'List all tokens that can be used as loop sources. Without recipe_id: returns universal loopable tokens (always available). With recipe_id: also includes trigger and action loopable tokens from that recipe (e.g., RSS feed items, order line items). Call BEFORE creating a loop with loop_add. IMPORTANT: Check requires_user field - if true, anonymous recipes need a user selector configured to provide user context.';
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
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Optional. Recipe ID to also fetch trigger/action loopable tokens from that recipe.',
					'minimum'     => 1,
				),
			),
			'required'   => array(),
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
		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;

		$all_loopable_tokens = array();

		// 1. Collect universal loopable tokens (always available).
		$universal_collection = $this->collector->collect_loopable_tokens( '' );
		if ( ! $universal_collection->is_empty() ) {
			foreach ( $universal_collection->to_array() as $token ) {
				$all_loopable_tokens[] = array(
					'id'            => $token['id'] ?? '',
					'name'          => $token['name'] ?? '',
					'integration'   => $token['integration_name'] ?? $token['integration'] ?? '',
					'source'        => 'universal',
					'requires_user' => $token['requires_user'] ?? false,
				);
			}
		}

		// 2. If recipe_id provided, also get trigger and action loopable tokens.
		if ( $recipe_id > 0 ) {
			$recipe_tokens = $this->get_recipe_loopable_tokens( $recipe_id );
			$all_loopable_tokens = array_merge( $all_loopable_tokens, $recipe_tokens );
		}

		if ( empty( $all_loopable_tokens ) ) {
			$message = 'No loopable tokens available.';
			if ( $recipe_id > 0 ) {
				$message = sprintf( 'No loopable tokens found for recipe #%d.', $recipe_id );
			}

			return Json_Rpc_Response::create_success_response(
				$message,
				array(
					'tokens' => array(),
					'hint'   => 'Loopable tokens come from: (1) Universal sources like WC subscriptions, (2) Triggers that provide array data like RSS feeds, (3) Actions that return array data. Add a trigger first, then check again.',
				)
			);
		}

		// Add usage example to each token.
		$formatted_tokens = array_map(
			function ( $token ) {
				$token['usage'] = sprintf( 'loop_add(recipe_id=X, type="token", loopable_token="%s")', $token['id'] );
				return $token;
			},
			$all_loopable_tokens
		);

		$message = sprintf( 'Found %d loopable token(s)', count( $formatted_tokens ) );
		if ( $recipe_id > 0 ) {
			$message .= sprintf( ' for recipe #%d', $recipe_id );
		}

		return Json_Rpc_Response::create_success_response(
			$message,
			array(
				'tokens' => $formatted_tokens,
			)
		);
	}

	/**
	 * Get loopable tokens from a recipe's triggers and actions.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array Array of loopable tokens from triggers and actions.
	 */
	private function get_recipe_loopable_tokens( int $recipe_id ): array {
		$loopable_tokens = array();

		// Get recipe object.
		$recipe_object = Automator()->get_recipe_object( $recipe_id, ARRAY_A );

		if ( empty( $recipe_object ) ) {
			return $loopable_tokens;
		}

		// Extract trigger loopable tokens.
		$triggers = $recipe_object['triggers']['items'] ?? array();
		foreach ( $triggers as $trigger ) {
			$trigger_tokens = $trigger['tokens'] ?? array();
			foreach ( $trigger_tokens as $token ) {
				if ( ( $token['token_type'] ?? '' ) === 'loopable' ) {
					$loopable_tokens[] = array(
						'id'            => $token['id'] ?? '',
						'name'          => $token['name'] ?? '',
						'integration'   => $trigger['integration_name'] ?? $trigger['integration_code'] ?? '',
						'source'        => 'trigger',
						'trigger_id'    => $trigger['id'] ?? 0,
						'requires_user' => $token['requiresUser'] ?? false,
					);
				}
			}
		}

		// Extract action loopable tokens.
		$actions = $recipe_object['actions']['items'] ?? array();
		foreach ( $actions as $action ) {
			// Skip loops.
			if ( ( $action['type'] ?? '' ) === 'loop' ) {
				continue;
			}

			$action_tokens = $action['tokens'] ?? array();
			foreach ( $action_tokens as $token ) {
				if ( ( $token['token_type'] ?? '' ) === 'loopable' ) {
					$loopable_tokens[] = array(
						'id'            => $token['id'] ?? '',
						'name'          => $token['name'] ?? '',
						'integration'   => $action['integration_name'] ?? $action['integration_code'] ?? '',
						'source'        => 'action',
						'action_id'     => $action['id'] ?? 0,
						'requires_user' => $token['requiresUser'] ?? false,
					);
				}
			}
		}

		return $loopable_tokens;
	}
}
