<?php
/**
 * MCP catalog tool that appends a registry action to a recipe instance.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * MCP Tool Adapter: Add Action to Recipe.
 *
 * Declares the `add_action` tool for MCP clients.
 * - Provides schema so bots/agents understand how to call it.
 * - Normalizes potentially malformed input from clients.
 * - Delegates actual business logic to Action_Instance_Service.
 * - Wraps the result in Json_Rpc_Response for MCP protocol compliance.
 *
 * @since 7.0.0
 */
class Add_Action_Tool extends Abstract_MCP_Tool {

	/**
	 * Action service.
	 *
	 * @var Action_Instance_Service
	 */
	private $action_service;

	/**
	 * Constructor.
	 *
	 * Allows for dependency injection of the action service.
	 */
	public function __construct( ?Action_CRUD_Service $action_service = null ) {
		$this->action_service = $action_service ?? Action_CRUD_Service::instance();
	}

	/**
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'add_action';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Add a new action to a recipe. Get schema with get_component_schema first, build fields payload, then call with recipe_id. Returns saved action record. CRITICAL: For dropdown fields, always pass BOTH the value AND the _readable suffix (e.g., "SLACKCHANNEL": "C123", "SLACKCHANNEL_readable": "#general") so the UI displays names instead of IDs. IMPORTANT: If recipe has condition groups, you MUST call add_action_to_condition_group after adding the action to link it to the group - otherwise the action runs unconditionally.';
	}

	/**
	 * Define the input schema for the add action to recipe tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for add action to recipe parameters.
	 */
	protected function schema_definition() {

		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'   => array(
					'type'        => 'integer',
					'description' => 'Target recipe ID where the action will be added. Must be an existing recipe in the system. Use get_recipes or find_recipes to discover available recipe IDs.',
					'minimum'     => 1,
				),
				'action_code' => array(
					'type'        => 'string',
					'description' => 'Action code from registry. Call get_component_schema first to understand required fields.',
					'minLength'   => 2,
				),
				'fields'      => array(
					'type'                 => 'object',
					'description'          => 'Action field values (most actions require these). Get schema first with get_component_schema. For dropdown fields, pass BOTH value AND _readable suffix (e.g., "SLACKCHANNEL": "C123", "SLACKCHANNEL_readable": "#general").',
					'additionalProperties' => true,
				),
				'custom_label' => array(
					'type'        => 'string',
					'description' => 'Optional custom label to describe this action. Helps identify the action in complex recipes. Example: "Send welcome email to new subscriber"',
					'default'     => '',
				),
				'async'       => array(
					'type'        => 'object',
					'description' => 'Optional async execution configuration for delayed or scheduled actions.',
					'properties'  => array(
						'mode'          => array(
							'type'        => 'string',
							'enum'        => array( 'delay', 'schedule', 'custom' ),
							'description' => 'Async execution mode. "delay" requires delay_number + delay_unit. "schedule" requires schedule_date + schedule_time. "custom" requires custom value.',
						),
						'delay_number'  => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'Number of time units to delay (required if mode is "delay").',
						),
						'delay_unit'    => array(
							'type'        => 'string',
							'enum'        => array( 'seconds', 'minutes', 'hours', 'days', 'years' ),
							'description' => 'Time unit for delay (required if mode is "delay").',
						),
						'schedule_date' => array(
							'type'        => 'string',
							'pattern'     => '^\\d{4}-\\d{2}-\\d{2}$',
							'description' => 'Schedule date in Y-m-d format (required if mode is "schedule").',
						),
						'schedule_time' => array(
							'type'        => 'string',
							'pattern'     => '^\\d{1,2}:\\d{2} (AM|PM)$',
							'description' => 'Schedule time in h:i A format, e.g., "12:01 PM" (required if mode is "schedule").',
						),
						'custom'        => array(
							'type'        => 'string',
							'description' => 'Custom delay/schedule value - can be a token like {{TOKEN}} or strtotime-compatible string like "+1 day" (required if mode is "custom").',
						),
					),
				),
			),
			'required'   => array( 'recipe_id', 'action_code' ),
		);
	}

	/**
	 * Execute the add action to recipe tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		// Require authenticated executor for recipe modification.
		$this->require_authenticated_executor( $user_context );

		$fields       = $params['fields'] ?? array();
		$async_config = $params['async'] ?? array();
		$custom_label = $params['custom_label'] ?? '';

		// Validate required parameters.
		if ( ! isset( $params['recipe_id'] ) ) {
			return Json_Rpc_Response::create_error_response( 'Missing required parameter: recipe_id' );
		}

		if ( ! isset( $params['action_code'] ) ) {
			return Json_Rpc_Response::create_error_response( 'Missing required parameter: action_code' );
		}

		// Handle fields payload - some MCP clients send it as a JSON string.
		$fields       = $this->parse_fields( $fields );
		$async_config = $this->parse_fields( $async_config );

		// Add custom label to fields if provided.
		if ( ! empty( $custom_label ) ) {
			$fields['_automator_custom_item_name_'] = sanitize_text_field( $custom_label );
		}

		// Use Action Service for business logic
		$result = $this->action_service->add_to_recipe(
			$params['recipe_id'],
			$params['action_code'],
			$fields,
			$async_config
		);

		// Transform service result to MCP response
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message()
			);
		}

		$action_data = $result['action'] ?? array();
		$action_id   = isset( $action_data['action_id'] ) ? (int) $action_data['action_id'] : 0;
		$recipe_id   = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : (int) ( $action_data['recipe_id'] ?? 0 );

		$payload = array(
			'recipe_id'  => $recipe_id,
			'action'     => $action_data,
			'links'      => $this->build_recipe_links( $recipe_id ),
			'next_steps' => $this->build_recipe_next_steps( $recipe_id ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return Json_Rpc_Response::create_success_response(
			'Action added to recipe',
			$payload
		);
	}

	/**
	 * Parse fields payload.
	 *
	 * @param array|string $fields Fields payload.
	 * @return array Parsed fields array.
	 */
	private function parse_fields( $fields ) {

		// If fields is a string, try to parse it as JSON
		if ( is_string( $fields ) ) {

			// First try to decode as JSON.
			$decoded = json_decode( $fields, true );

			// If JSON decode failed, try to handle Python-style dict format
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				// Replace Python-style single quotes with double quotes for JSON
				$json_formatted = str_replace( "'", '"', $fields );
				// Try again with formatted string
				$decoded = json_decode( $json_formatted, true );
			}

			// Use decoded fields if successful, otherwise empty array
			$fields = ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) ? $decoded : array();

		}

		// Ensure fields is an array (handle empty arrays that might be sent as [])
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		return $fields;
	}

	/**
	 * Provide backend edit link for the parent recipe.
	 */
	private function build_recipe_links( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$edit_link = get_edit_post_link( $recipe_id, 'raw' );
		if ( ! is_string( $edit_link ) || '' === $edit_link ) {
			return array();
		}

		return array( 'edit_recipe' => $edit_link );
	}

	/**
	 * Encourage agents to open the recipe editor after mutating actions.
	 */
	private function build_recipe_next_steps( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$edit_link = get_edit_post_link( $recipe_id, 'raw' );

		return array(
			'edit_recipe' => array(
				'admin_url' => is_string( $edit_link ) ? $edit_link : '',
				'hint'      => 'Open the recipe editor to manage triggers, actions, and conditions.',
			),
		);
	}
}
