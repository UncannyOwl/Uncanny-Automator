<?php
/**
 * MCP catalog tool that manually executes recipes with manual triggers.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Security\Security;

class Run_Recipe_Tool extends Abstract_MCP_Tool {
	/**
	 * Get name.
	 *
	 * @return mixed
	 */
	public function get_name() {
		return 'run_recipe';
	}
	/**
	 * Get description.
	 *
	 * @return mixed
	 */
	public function get_description() {
		return 'Execute a recipe immediately. Requires a recipe with a manual trigger. Returns execution log.';
	}
	/**
	 * Schema definition.
	 *
	 * @return mixed
	 */
	public function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the recipe to execute manually. Recipe must have a manual trigger.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}


	/**
	 * Execute tool with User_Context.
	 *
	 * @param User_Context $user_context The user context.
	 * @param array        $params       The input parameters.
	 * @return array
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		// Require authenticated executor for manual workflow execution
		$this->require_authenticated_executor( $user_context );

		// Basic structure validation
		$schema = array(
			'recipe_id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);

		if ( ! Security::validate_schema( $params, $schema ) ) {
			return Json_Rpc_Response::create_error_response( 'Invalid parameters' );
		}

		// Sanitize parameters with minimal processing
		$sanitized = Security::sanitize( $params, Security::PRESERVE_RAW );
		$recipe_id = intval( $sanitized['recipe_id'] ?? 0 );

		if ( ! $recipe_id ) {
			return Json_Rpc_Response::create_error_response( 'Recipe ID is required' );
		}

		// For workflow execution, the executee is typically the executor
		$executee     = $user_context->get_executor();
		$tool_context = new User_Context( $user_context->get_executor(), $executee );

		// First check if the recipe exists
		$recipe_post = get_post( $recipe_id );
		if ( ! $recipe_post || 'uo-recipe' !== $recipe_post->post_type ) {
			return Json_Rpc_Response::create_error_response( 'Failed to execute recipe: Recipe not found or invalid type' );
		}

		// Recipe exists, now check for manual trigger at domain level
		global $wpdb;
		$trigger_store = new WP_Recipe_Trigger_Store( $wpdb );
		$recipe_id_vo  = new Recipe_Id( $recipe_id );

		$has_manual_trigger = $trigger_store->recipe_has_manual_trigger( $recipe_id_vo );
		if ( ! $has_manual_trigger ) {
			return Json_Rpc_Response::create_error_response(
				'Recipe does not have a manual trigger. The recipe must contain either "RECIPE_MANUAL_TRIGGER_ANON" or "RECIPE_MANUAL_TRIGGER" (Recipe manual Trigger - Run now) to be executed manually.'
			);
		}

		// Trigger the recipe manually - this is the core action that runs the recipe
		do_action( 'automator_pro_run_now_recipe', $recipe_id );

		// For async operations triggered via do_action, the recipe won't be completed immediately
		// The do_action triggers a background process, so we should report it as running
		// Only report as completed if we have explicit confirmation
		$recipe_is_running    = true; // Async execution is now running
		$recipe_run_completed = false; // Cannot be completed immediately after async trigger

		// Format response for MCP
		$result = array(
			'executor'             => $tool_context->get_executor(),
			'executee'             => $tool_context->get_executee(),
			'recipe_id'            => $recipe_id,
			'recipe_run_completed' => $recipe_run_completed,
			'recipe_is_running'    => $recipe_is_running,
			'recipe_status'        => 'initiated', // Async execution triggered, real-time status not available
			'recipe_title'         => get_the_title( $recipe_id ),
		);

		return Json_Rpc_Response::create_success_response( 'Recipe execution initiated', $result );
	}
}
