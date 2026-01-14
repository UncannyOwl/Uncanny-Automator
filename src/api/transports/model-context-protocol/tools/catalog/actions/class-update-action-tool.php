<?php
/**
 * MCP catalog tool that edits persisted recipe action instances.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use WP_Error;

/**
 * Update Action MCP Tool.
 *
 * MCP tool for updating an existing action instance.
 * Updates stored action instance properties (not registry definitions).
 *
 * @since 7.0.0
 */
class Update_Action_Tool extends Abstract_MCP_Tool {

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
		return 'update_action';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Update an existing action. Get action_id from list_actions, get schema from get_component_schema, then update only the fields you want to change. CRITICAL: For dropdown fields, always pass BOTH the value AND the _readable suffix (e.g., "SLACKCHANNEL": "C123", "SLACKCHANNEL_readable": "#general") so the UI displays names instead of IDs.';
	}

	/**
	 * Define the input schema for the update action tool.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for update action parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action_id' => array(
					'type'        => 'integer',
					'description' => 'Existing action instance ID to update. Get this from get_recipe_actions which lists all actions in a recipe with their IDs. Must be a valid, existing action instance in the database.',
					'minimum'     => 1,
				),
				'fields'    => array(
					'type'                 => 'object',
					'description'          => 'Field values to update (merged with existing). Only provide fields you want to change. For dropdown fields, pass BOTH value AND _readable suffix (e.g., "SLACKCHANNEL": "C123", "SLACKCHANNEL_readable": "#general"). Use get_component_schema to understand available fields.',
					'properties'           => new \stdClass(),
					'additionalProperties' => true,
				),
				'async'     => array(
					'type'        => 'object',
					'description' => 'Optional async execution configuration for delayed or scheduled actions. If not provided, existing async settings are preserved. To remove async scheduling, pass an empty object.',
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
				'status'       => array(
					'type'        => 'string',
					'description' => 'Action publication status. Set to "publish" to make action active (live), "draft" to disable it. IMPORTANT: Recipe, trigger, and action statuses are independent—setting a recipe to live does NOT automatically activate its triggers or actions.',
					'enum'        => array( 'draft', 'publish' ),
				),
				'custom_label' => array(
					'type'        => 'string',
					'description' => 'Optional custom label to describe this action. Helps identify the action in complex recipes. Pass empty string to clear existing label.',
				),
			),
			'required'   => array( 'action_id' ),
		);
	}

	/**
	 * Execute the update action tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters from MCP client.
	 * @return array Tool execution result.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		// Require authenticated executor for action modification
		$this->require_authenticated_executor( $user_context );

		$action_id    = $params['action_id'] ?? null;
		$fields       = $params['fields'] ?? array();
		$async_config = $params['async'] ?? array();
		$status       = $params['status'] ?? null;
		$custom_label = $params['custom_label'] ?? null;

		if ( ! $action_id ) {
			return Json_Rpc_Response::create_error_response( 'Action ID is required' );
		}

		// Parse field parameters - some MCP clients send as JSON strings
		$fields       = $this->parse_fields( $fields );
		$async_config = $this->parse_fields( $async_config );

		// Add custom label to fields if provided (including empty string to clear).
		if ( null !== $custom_label ) {
			$fields['_automator_custom_item_name_'] = sanitize_text_field( $custom_label );
		}

		// Use Action Service for business logic - service handles all validation
		$result = $this->action_service->update_action( $action_id, $fields, $async_config, $status );

		// Transform service result to MCP response - service returns WP_Error or array
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$action_payload = $this->format_action_response( $result['action'] );
		$recipe_id      = isset( $action_payload['recipe_id'] ) ? (int) $action_payload['recipe_id'] : 0;
		$normalized_id  = $action_id > 0 ? $action_id : (int) ( $action_payload['action_id'] ?? 0 );

		$payload = array(
			'recipe_id'  => $recipe_id,
			'action'     => $action_payload,
			'links'      => $this->build_recipe_links( $recipe_id ),
			'next_steps' => $this->build_recipe_next_steps( $recipe_id ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return Json_Rpc_Response::create_success_response(
			'Action updated successfully',
			$payload
		);
	}


	/**
	 * Format action response data.
	 *
	 * @param array $action_data Action data array.
	 * @return array Formatted response data.
	 */
	protected function format_action_response( array $action_data ) {
		return array(
			'action_id'                    => $action_data['action_id'],
			'action_code'                  => $action_data['action_code'],
			'integration'                  => $action_data['integration'],
			'type'                         => $action_data['user_type'],
			'recipe_id'                    => $action_data['recipe_id'],
			'sentence_human_readable'      => $action_data['sentence_human_readable'] ?? '',
			'sentence_human_readable_html' => $action_data['sentence_human_readable_html'] ?? '',
			'fields'                       => $action_data['config'] ?? array(),
			'async'                        => $action_data['async'] ?? array(),
		);
	}

	/**
	 * Parse fields parameter.
	 *
	 * @param array|string $fields Fields payload from MCP client.
	 * @return array Parsed fields array.
	 */
	protected function parse_fields( $fields ) {
		// If fields is a string, try to parse it as JSON
		if ( is_string( $fields ) ) {
			// First try to decode as JSON
			$decoded = json_decode( $fields, true );

			// If JSON decode failed, try to handle Python-style dict format
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				// Replace Python-style single quotes with double quotes for JSON
				$json_formatted = str_replace( "'", '"', $fields );
				// Try again with formatted string
				$decoded = json_decode( $json_formatted, true );
			}

			// Use decoded fields if successful, otherwise empty array
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$fields = $decoded;
			} else {
				$fields = array();
			}
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
	protected function build_recipe_links( int $recipe_id ): array {
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
	 * Encourage agents to open the recipe editor after mutations.
	 */
	protected function build_recipe_next_steps( int $recipe_id ): array {
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
