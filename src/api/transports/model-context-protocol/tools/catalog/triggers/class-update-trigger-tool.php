<?php
/**
 * MCP catalog tool that edits stored trigger instances for a recipe.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Triggers;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Database\Database;
use Uncanny_Automator\Api\Components\Trigger\Trigger_Config;
use Uncanny_Automator\Api\Components\Trigger\Trigger;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use WP_Error;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Steps_Builder;

/**
 * Update Trigger MCP Tool.
 *
 * MCP tool for updating an existing trigger instance.
 * Updates stored trigger instance properties (not registry definitions).
 *
 * @since 7.0.0
 */
class Update_Trigger_Tool extends Abstract_MCP_Tool {

	/**
	 * Trigger service.
	 *
	 * @var Trigger_CRUD_Service
	 */
	private $trigger_service;

	/**
	 * Recipe link builder service.
	 *
	 * @var Recipe_Link_Builder
	 */
	private $recipe_link_builder;

	/**
	 * Recipe steps builder service.
	 *
	 * @var Recipe_Steps_Builder
	 */
	private $recipe_steps_builder;

	/**
	 * Constructor.
	 *
	 * Allows for dependency injection of the required services.
	 */
	public function __construct(
		?Trigger_CRUD_Service $trigger_service = null,
		?Recipe_Link_Builder $recipe_link_builder = null,
		?Recipe_Steps_Builder $recipe_steps_builder = null
	) {
		$this->trigger_service = $trigger_service ?? Trigger_CRUD_Service::instance();

		// Use lazy loading for service classes to avoid bootstrap issues
		$this->recipe_link_builder  = $recipe_link_builder;
		$this->recipe_steps_builder = $recipe_steps_builder;
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'update_trigger';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Update an existing trigger. Get trigger_id from get_recipe, get field data from get_component_schema, then merge changes while preserving other settings. CRITICAL: For dropdown fields, always pass BOTH the value AND the _readable suffix (e.g., "LDCOURSE": "656", "LDCOURSE_readable": "Sales 101") so the UI displays names instead of IDs.';
	}

	/**
	 * Define the input schema for the update trigger tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for update trigger parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'trigger_id' => array(
					'type'        => 'integer',
					'description' => 'Existing trigger instance ID to update. Get this from get_recipe_triggers which lists all triggers in a recipe with their IDs. Must be a valid, existing trigger instance in the database.',
					'minimum'     => 1,
				),
				'fields'     => array(
					'type'                 => 'object',
					'description'          => 'Field values to update (merged with existing). Only provide fields you want to change. For dropdown fields, pass BOTH value AND _readable suffix (e.g., "LDCOURSE": "656", "LDCOURSE_readable": "Sales 101"). Optional: "_automator_custom_item_name_" for custom label.',
					'properties'           => new \stdClass(),
					'additionalProperties' => true,
				),
				'status'     => array(
					'type'        => 'string',
					'description' => 'Trigger publication status. Set to "publish" to make trigger active (live), "draft" to disable it. IMPORTANT: Recipe, trigger, and action statuses are independent—setting a recipe to live does NOT automatically activate its triggers or actions.',
					'enum'        => array( 'draft', 'publish' ),
				),
			),
			'required'   => array( 'trigger_id' ),
		);
	}

	/**
	 * Execute the update trigger tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		// Require authenticated executor for trigger modification
		$this->require_authenticated_executor( $user_context );

		$trigger_id = $params['trigger_id'] ?? null;
		$fields     = $params['fields'] ?? array();
		$status     = $params['status'] ?? null;

		if ( ! $trigger_id ) {
			return Json_Rpc_Response::create_error_response( 'Trigger ID is required' );
		}

		// Map status parameter to post_status field for service layer
		if ( null !== $status ) {
			$fields['post_status'] = $status;
		}

		// Lazy instantiate services if not provided
		$recipe_link_builder  = $this->recipe_link_builder ?? new Recipe_Link_Builder();
		$recipe_steps_builder = $this->recipe_steps_builder ?? new Recipe_Steps_Builder();

		// Use Trigger Service for business logic - service handles all validation
		$result = $this->trigger_service->update_trigger( $trigger_id, $fields );

		// Transform service result to MCP response - service returns WP_Error or array
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$trigger_payload = $result['trigger'] ?? array();
		$recipe_id       = isset( $trigger_payload['recipe_id'] ) ? (int) $trigger_payload['recipe_id'] : 0;

		$payload = array(
			'recipe_id'  => $recipe_id,
			'trigger'    => $trigger_payload,
			'links'      => $recipe_link_builder->build_links( $recipe_id ),
			'next_steps' => $recipe_steps_builder->build_steps( $recipe_id ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return Json_Rpc_Response::create_success_response(
			'Trigger updated successfully',
			$payload
		);
	}


	/**
	 * Provide backend edit link for the parent recipe.
	 *
	 * @deprecated Use Recipe_Link_Builder service instead.
	 */
	protected function build_recipe_links( int $recipe_id ): array {
		$recipe_link_builder = $this->recipe_link_builder ?? new Recipe_Link_Builder();
		return $recipe_link_builder->build_links( $recipe_id );
	}

	/**
	 * Encourage agents to open the recipe editor after mutations.
	 *
	 * @deprecated Use Recipe_Steps_Builder service instead.
	 */
	protected function build_recipe_next_steps( int $recipe_id ): array {
		$recipe_steps_builder = $this->recipe_steps_builder ?? new Recipe_Steps_Builder();
		return $recipe_steps_builder->build_steps( $recipe_id );
	}
}
