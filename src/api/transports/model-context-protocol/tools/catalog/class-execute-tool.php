<?php
/**
 * Consolidated execute tool.
 *
 * Replaces: run_recipe, run_action.
 * target=recipe: execute a recipe with a manual trigger.
 * target=action: execute any action immediately via Action_Executor.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\Api\Application\Sub_Tooling\Action_Executor;
use Uncanny_Automator\Api\Components\Security\Security;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Recipe\Services\Recipe_Execution_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Execute Tool.
 *
 * target=recipe: requires recipe_id. Recipe must have a manual trigger.
 * target=action: requires action_code. Executes via Action_Executor.
 *
 * @since 7.1.0
 */
class Execute_Tool extends Abstract_MCP_Tool {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'execute';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Run a recipe or action immediately. '
			. 'target=recipe: executes a recipe with a manual trigger. Requires recipe_id. '
			. 'target=action: executes any action outside a recipe. Requires action_code. '
			. 'Use search to find action codes, get_component_schema for required fields.';
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
				'target'      => array(
					'type'        => 'string',
					'enum'        => array( 'recipe', 'action' ),
					'description' => '"recipe" to run a recipe, "action" to run a standalone action.',
				),
				'recipe_id'   => array(
					'type'        => 'integer',
					'description' => 'Recipe ID to execute. Required when target=recipe. Recipe must have a manual trigger.',
					'minimum'     => 1,
				),
				'action_code' => array(
					'type'        => 'string',
					'description' => 'Action code to execute. Required when target=action.',
				),
				'user_id'     => array(
					'type'        => 'integer',
					'description' => 'User ID context. Defaults to current authenticated user.',
					'minimum'     => 0,
				),
				'fields'      => array(
					'type'                 => 'object',
					'description'          => 'Field values for standalone action execution. Keys must match option_code from get_component_schema.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'target' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'     => array( 'type' => 'integer' ),
				'recipe_title'  => array( 'type' => 'string' ),
				'recipe_status' => array( 'type' => 'string' ),
				'executor'      => array( 'type' => array( 'integer', 'string', 'null' ) ),
				'executee'      => array( 'type' => array( 'integer', 'string', 'null' ) ),
				'success'       => array( 'type' => 'boolean' ),
				'action_code'   => array( 'type' => 'string' ),
				'data'          => array( 'type' => 'object' ),
				'tokens'        => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$target = $params['target'] ?? '';

		if ( 'recipe' === $target ) {
			return $this->run_recipe( $user_context, $params );
		}

		if ( 'action' === $target ) {
			return $this->run_action( $user_context, $params );
		}

		return Json_Rpc_Response::create_error_response( 'target must be "recipe" or "action".' );
	}

	// ──────────────────────────────────────────────────────────────────
	// RECIPE — port of Run_Recipe_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Execute a recipe with a manual trigger.
	 *
	 * @param User_Context $user_context User context.
	 * @param array        $params       Tool parameters.
	 * @return array JSON-RPC response.
	 */
	private function run_recipe( User_Context $user_context, array $params ): array {

		$recipe_id = (int) ( $params['recipe_id'] ?? 0 );

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'recipe_id is required when target=recipe.' );
		}

		// Pre-check recipe state for clearer error messages.
		$recipe = get_post( $recipe_id );
		if ( ! $recipe || AUTOMATOR_POST_TYPE_RECIPE !== $recipe->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe %d not found.', $recipe_id )
			);
		}
		if ( 'trash' === $recipe->post_status ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe %d is trashed and cannot be executed.', $recipe_id )
			);
		}
		if ( 'publish' !== $recipe->post_status ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe %d is in "%s" status. Only published recipes can be executed.', $recipe_id, $recipe->post_status )
			);
		}

		$service = Recipe_Execution_Service::instance();
		$result  = $service->execute_recipe( $recipe_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$result['executor'] = $user_context->get_executor();
		$result['executee'] = $user_context->get_executor();

		return Json_Rpc_Response::create_success_response( 'Recipe execution initiated', $result );
	}

	// ──────────────────────────────────────────────────────────────────
	// ACTION — port of Run_Action_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Execute a standalone action via Action_Executor.
	 *
	 * @param User_Context $user_context User context.
	 * @param array        $params       Tool parameters.
	 * @return array JSON-RPC response.
	 */
	private function run_action( User_Context $user_context, array $params ): array {

		$action_code = trim( $params['action_code'] ?? '' );
		$fields      = $params['fields'] ?? array();
		$user_id     = intval( $params['user_id'] ?? $user_context->get_executee() ?? $user_context->get_executor() );

		if ( empty( $action_code ) ) {
			return Json_Rpc_Response::create_error_response( 'action_code is required when target=action.' );
		}

		if ( ! is_array( $fields ) ) {
			return Json_Rpc_Response::create_error_response( 'fields must be an object.' );
		}

		// Sanitize fields.
		if ( ! empty( $fields ) ) {
			$fields = Security::sanitize( $fields, Security::PRESERVE_RAW );
		}

		$executor = new Action_Executor();
		$result   = $executor->run( $action_code, $fields, $user_id );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		if ( ! empty( $result['success'] ) ) {
			$response_data = array(
				'success'     => true,
				'action_code' => $action_code,
				'executor'    => $user_context->get_executor(),
				'executee'    => $user_id,
				'data'        => $this->ensure_object( $result['data'] ?? array() ),
			);

			if ( ! empty( $result['tokens'] ) ) {
				$response_data['tokens'] = $result['tokens'];
			}

			return Json_Rpc_Response::create_success_response( 'Action executed successfully', $response_data );
		}

		$fallback_error = sprintf(
			"Action '%s' failed. Check that the integration is active and any required apps are connected.",
			$action_code
		);

		return Json_Rpc_Response::create_error_response( $result['error'] ?? $fallback_error );
	}
}
