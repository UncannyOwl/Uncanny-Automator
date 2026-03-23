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
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;
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

	use HasValidParent;

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
		return 'Update an existing action. Optionally specify parent_type and parent_id to move actions between recipe and loops (defaults to current parent). Get action_id from list_actions, get schema from get_component_schema. CRITICAL: For dropdown fields, always pass BOTH the value AND the _readable suffix (e.g., "SLACKCHANNEL": "C123", "SLACKCHANNEL_readable": "#general").';
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
				'action_id'   => array(
					'type'        => 'integer',
					'description' => 'Existing action instance ID to update. Get this from list_actions which lists all actions in a recipe.',
					'minimum'     => 1,
				),
				'recipe_id'   => array(
					'type'        => 'integer',
					'description' => 'Recipe ID that contains this action. Required for validation.',
					'minimum'     => 1,
				),
				'parent_type' => array(
					'type'        => 'string',
					'enum'        => array( 'recipe', 'loop' ),
					'description' => 'Where to place the action. "recipe" = direct child of recipe (runs once). "loop" = inside a loop (runs for each iteration).',
				),
				'parent_id'   => array(
					'type'        => 'integer',
					'description' => 'The parent ID matching parent_type. If parent_type=recipe, this should be the recipe_id. If parent_type=loop, this should be a loop_id.',
					'minimum'     => 1,
				),
				'fields'      => array(
					'type'                 => 'object',
					'description'          => 'Field values to update (merged with existing). Only provide fields you want to change. For dropdown fields, pass BOTH value AND _readable suffix.',
					'properties'           => new \stdClass(),
					'additionalProperties' => true,
				),
				'async'       => array(
					'type'        => 'object',
					'description' => 'Optional async execution configuration for delayed or scheduled actions.',
					'properties'  => array(
						'mode'          => array(
							'type'        => 'string',
							'enum'        => array( 'delay', 'schedule', 'custom' ),
							'description' => 'Async execution mode.',
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
							'description' => 'Schedule time in h:i A format (required if mode is "schedule").',
						),
						'custom'        => array(
							'type'        => 'string',
							'description' => 'Custom delay/schedule value (required if mode is "custom").',
						),
					),
				),
				'status'      => array(
					'type'        => 'string',
					'description' => 'Action publication status.',
					'enum'        => array( 'draft', 'publish' ),
				),
				'custom_label' => array(
					'type'        => 'string',
					'description' => 'Optional custom label. Pass empty string to clear.',
				),
			),
			'required'   => array( 'action_id', 'recipe_id' ),
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
		$recipe_id    = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		$parent_type  = $params['parent_type'] ?? null;
		$parent_id    = isset( $params['parent_id'] ) ? (int) $params['parent_id'] : 0;
		$fields       = $params['fields'] ?? array();
		$async_config = $params['async'] ?? array();
		$status       = $params['status'] ?? null;
		$custom_label = $params['custom_label'] ?? null;

		if ( ! $action_id ) {
			return Json_Rpc_Response::create_error_response( 'action_id is required. Use list_actions with recipe_id to find action IDs.' );
		}

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'recipe_id is required. Use list_recipes to find recipe IDs.' );
		}

		// Resolve parent from existing action when not explicitly provided.
		if ( empty( $parent_type ) || $parent_id <= 0 ) {
			$resolved = $this->resolve_existing_parent( (int) $action_id, $recipe_id );
			if ( is_array( $resolved ) && ! empty( $resolved['isError'] ) ) {
				return $resolved;
			}
			$parent_type = ! empty( $parent_type ) ? $parent_type : $resolved['parent_type'];
			$parent_id   = $parent_id > 0 ? $parent_id : $resolved['parent_id'];
		}

		// Validate parent_type and parent_id match.
		$validation_error = $this->validate_parent( $parent_type, $parent_id, $recipe_id );
		if ( null !== $validation_error ) {
			return $validation_error;
		}

		// Parse field parameters - some MCP clients send as JSON strings
		$fields       = $this->parse_fields( $fields );
		$async_config = $this->parse_fields( $async_config );

		// Validate tokens in fields before proceeding.
		$validation = Token_Validator::validate( $recipe_id, $fields );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		// Add custom label to fields if provided (including empty string to clear).
		if ( null !== $custom_label ) {
			$fields['_automator_custom_item_name_'] = sanitize_text_field( $custom_label );
		}

		// Use Action Service for business logic - pass parent_id to support moving actions
		$result = $this->action_service->update_action( $action_id, $fields, $async_config, $status, $parent_id );

		// Transform service result to MCP response - service returns WP_Error or array
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use list_actions to verify the action exists.'
			);
		}

		$action_payload = $this->format_action_response( $result['action'] );

		$payload = array(
			'recipe_id'   => $recipe_id,
			'parent_type' => $parent_type,
			'parent_id'   => $parent_id,
			'action'      => $action_payload,
			'links'       => $this->build_recipe_links( $recipe_id ),
			'next_steps'  => $this->build_recipe_next_steps( $recipe_id ),
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

	/**
	 * Resolve parent_type and parent_id from the existing action post.
	 *
	 * When the caller omits parent_type/parent_id, we derive them from the
	 * action's current post_parent so the update keeps the action in place.
	 *
	 * @param int $action_id The action post ID.
	 * @param int $recipe_id The recipe ID for context in error messages.
	 * @return array{parent_type: string, parent_id: int}|array{error: true} Resolved parent or error response.
	 */
	private function resolve_existing_parent( int $action_id, int $recipe_id ): array {
		$action_post = get_post( $action_id );

		if ( ! $action_post || 'uo-action' !== $action_post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d not found. Use list_actions with recipe_id to find action IDs.', $action_id )
			);
		}

		$current_parent_id = (int) $action_post->post_parent;

		if ( $current_parent_id <= 0 ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d has no parent. Provide parent_type and parent_id explicitly.', $action_id )
			);
		}

		$parent_post = get_post( $current_parent_id );

		if ( ! $parent_post ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d parent post %d not found. Provide parent_type and parent_id explicitly.', $action_id, $current_parent_id )
			);
		}

		$parent_type = 'uo-loop' === $parent_post->post_type ? 'loop' : 'recipe';

		return array(
			'parent_type' => $parent_type,
			'parent_id'   => $current_parent_id,
		);
	}
}
