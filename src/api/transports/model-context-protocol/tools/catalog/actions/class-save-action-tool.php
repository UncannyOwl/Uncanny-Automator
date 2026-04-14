<?php
/**
 * Consolidated action upsert tool.
 *
 * Replaces: add_action, update_action.
 * action_id absent = create, action_id present = update.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Services\Field\Field_Mcp_Input_Resolver;
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Services\User_Selector\User_Selector_Advisor;

/**
 * Save Action Tool — upsert.
 *
 * Create: omit action_id. Requires recipe_id, action_code, parent_type, parent_id.
 * Update: include action_id. Requires action_id, recipe_id. Other fields optional.
 *
 * @since 7.1.0
 */
class Save_Action_Tool extends Abstract_MCP_Tool {

	use HasValidParent;

	/**
	 * @var Action_CRUD_Service
	 */
	private Action_CRUD_Service $action_service;

	/**
	 * Constructor.
	 *
	 * @param Action_CRUD_Service|null $action_service Action CRUD service.
	 */
	public function __construct( ?Action_CRUD_Service $action_service = null ) {
		$this->action_service = $action_service ?? Action_CRUD_Service::instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'save_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Create or update an action. Omit action_id to create, include action_id to update. '
			. 'Create requires recipe_id, action_code, parent_type, and parent_id. '
			. 'Update requires action_id and recipe_id; other fields are optional. '
			. 'Get field definitions from get_component_schema first. '
			. 'CRITICAL: For dropdown fields, always pass BOTH the value AND the _readable suffix '
			. '(e.g., "SLACKCHANNEL": "C123", "SLACKCHANNEL_readable": "#general"). '
			. 'IMPORTANT: If using condition groups, call save_condition_group after to link the action.';
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
				'recipe_id'    => array(
					'type'        => 'integer',
					'description' => 'Recipe ID. Required for both create and update.',
					'minimum'     => 1,
				),
				'action_id'    => array(
					'type'        => 'integer',
					'description' => 'Existing action ID to update. Omit to create a new action.',
					'minimum'     => 1,
				),
				'action_code'  => array(
					'type'        => 'string',
					'description' => 'Action code from registry (e.g., "WP_SEND_EMAIL"). Required for create. Use search or get_component_schema to find codes.',
					'minLength'   => 2,
				),
				'parent_type'  => array(
					'type'        => 'string',
					'enum'        => array( 'recipe', 'loop' ),
					'description' => 'Where to place the action. Required for create. "recipe" = direct child (runs once). "loop" = inside a loop (runs per iteration). Optional for update (defaults to current parent).',
				),
				'parent_id'    => array(
					'type'        => 'integer',
					'description' => 'Parent ID matching parent_type. Required for create. If parent_type=recipe, use recipe_id. If parent_type=loop, use loop_id. Optional for update.',
					'minimum'     => 1,
				),
				'fields'       => array(
					'type'                 => 'object',
					'description'          => 'Action field values. Get schema from get_component_schema first. For dropdowns, pass BOTH value AND _readable suffix.',
					'additionalProperties' => true,
				),
				'async'        => array(
					'type'        => 'object',
					'description' => 'Optional async execution configuration.',
					'properties'  => array(
						'mode'          => array(
							'type'        => 'string',
							'enum'        => array( 'delay', 'schedule', 'custom' ),
							'description' => 'Async mode. "delay" needs delay_number + delay_unit. "schedule" needs schedule_date + schedule_time. "custom" needs custom value.',
						),
						'delay_number'  => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => 'Delay amount (required if mode=delay).',
						),
						'delay_unit'    => array(
							'type'        => 'string',
							'enum'        => array( 'seconds', 'minutes', 'hours', 'days', 'years' ),
							'description' => 'Delay unit (required if mode=delay).',
						),
						'schedule_date' => array(
							'type'        => 'string',
							'pattern'     => '^\\d{4}-\\d{2}-\\d{2}$',
							'description' => 'Schedule date Y-m-d (required if mode=schedule).',
						),
						'schedule_time' => array(
							'type'        => 'string',
							'pattern'     => '^\\d{1,2}:\\d{2} (AM|PM)$',
							'description' => 'Schedule time h:i A (required if mode=schedule).',
						),
						'custom'        => array(
							'type'        => 'string',
							'description' => 'Custom value — token or strtotime string (required if mode=custom).',
						),
					),
				),
				'status'       => array(
					'type'        => 'string',
					'description' => 'Action publication status. Update-only. "publish" = active, "draft" = disabled.',
					'enum'        => array( 'draft', 'publish' ),
				),
				'custom_label' => array(
					'type'        => 'string',
					'description' => 'Optional custom label. Pass empty string to clear.',
					'default'     => '',
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
				'recipe_id'   => array( 'type' => 'integer' ),
				'parent_type' => array( 'type' => 'string' ),
				'parent_id'   => array( 'type' => 'integer' ),
				'action'      => array(
					'type'       => 'object',
					'properties' => array(
						'action_id'                    => array( 'type' => 'integer' ),
						'action_code'                  => array( 'type' => 'string' ),
						'integration'                  => array( 'type' => 'string' ),
						'sentence_human_readable'      => array( 'type' => 'string' ),
						'sentence_human_readable_html' => array( 'type' => 'string' ),
						'config'                       => array( 'type' => 'object' ),
						'async'                        => array( 'type' => array( 'object', 'null' ) ),
					),
				),
				'links'       => array( 'type' => 'object' ),
				'notes'       => array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'recipe_id', 'parent_type', 'parent_id', 'action' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$action_id = isset( $params['action_id'] ) ? (int) $params['action_id'] : null;

		if ( null !== $action_id && $action_id > 0 ) {
			return $this->update_action( $action_id, $params );
		}

		return $this->create_action( $params );
	}

	// ──────────────────────────────────────────────────────────────────
	// CREATE PATH — port of Add_Action_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Create a new action on a recipe or loop.
	 *
	 * @param array $params Tool parameters.
	 * @return array JSON-RPC response.
	 */
	private function create_action( array $params ): array {

		$recipe_id   = (int) $params['recipe_id'];
		$action_code = $params['action_code'] ?? '';
		$parent_type = $params['parent_type'] ?? null;
		$parent_id   = isset( $params['parent_id'] ) ? (int) $params['parent_id'] : 0;
		$fields      = $this->parse_json_param( $params['fields'] ?? array() );
		$async       = $this->parse_json_param( $params['async'] ?? array() );
		$label       = $params['custom_label'] ?? '';

		// Validate create-specific requirements.
		if ( empty( $action_code ) ) {
			return Json_Rpc_Response::create_error_response( 'action_code is required for creating an action. Use search to find action codes.' );
		}

		if ( empty( $parent_type ) ) {
			return Json_Rpc_Response::create_error_response( 'parent_type is required for creating an action. Use "recipe" for recipe-level or "loop" for loop actions.' );
		}

		if ( $parent_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'parent_id is required for creating an action. Must match parent_type (recipe_id or loop_id).' );
		}

		// Validate parent matches.
		$parent_error = $this->validate_parent( $parent_type, $parent_id, $recipe_id );
		if ( null !== $parent_error ) {
			return $parent_error;
		}

		// Normalize select/multi-select fields: JSON-encode arrays, generate _readable.
		$normalizer = new Field_Mcp_Input_Resolver();
		$fields     = $normalizer->normalize( 'actions', $action_code, $fields );

		// Check for repeater garbage detected during normalization.
		if ( ! empty( $fields['__validation_error'] ) ) {
			$error_msg = $fields['__validation_error'];
			unset( $fields['__validation_error'] );
			return Json_Rpc_Response::create_error_response( $error_msg );
		}

		// Validate tokens in fields.
		$validation = Token_Validator::validate( $recipe_id, $fields );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		// Add custom label to fields if provided.
		if ( ! empty( $label ) ) {
			$fields['_automator_custom_item_name_'] = sanitize_text_field( $label );
		}

		$result = $this->action_service->add_to_recipe(
			$recipe_id,
			$action_code,
			$fields,
			$async,
			$parent_id
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use search to find valid action codes, or get_component_schema to verify field requirements.'
			);
		}

		$action_data = $result['action'] ?? array();

		// Ensure config/async serialize as JSON objects, not empty arrays.
		$action_data['config'] = $this->ensure_object( $action_data['config'] ?? array() );
		if ( isset( $action_data['async'] ) && is_array( $action_data['async'] ) && empty( $action_data['async'] ) ) {
			$action_data['async'] = null;
		}

		$payload = array(
			'recipe_id'   => $recipe_id,
			'parent_type' => $parent_type,
			'parent_id'   => $parent_id,
			'action'      => $action_data,
			'links'       => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),

		);

		$notes = array();
		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$notes[] = $result['message'];
		}

		// Warn if anonymous recipe needs user selector.
		$advisor          = new User_Selector_Advisor();
		$selector_warning = $advisor->check_after_action_add( $recipe_id, $action_code, $fields );
		if ( $selector_warning ) {
			$notes[] = $selector_warning;
		}

		if ( ! empty( $notes ) ) {
			$payload['notes'] = $notes;
		}

		return Json_Rpc_Response::create_success_response( 'Action added to recipe', $payload );
	}

	// ──────────────────────────────────────────────────────────────────
	// UPDATE PATH — port of Update_Action_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Update an existing action.
	 *
	 * @param int   $action_id Action post ID.
	 * @param array $params    Tool parameters.
	 * @return array JSON-RPC response.
	 */
	private function update_action( int $action_id, array $params ): array {

		$recipe_id   = (int) $params['recipe_id'];
		$parent_type = $params['parent_type'] ?? null;
		$parent_id   = isset( $params['parent_id'] ) ? (int) $params['parent_id'] : 0;
		$fields      = $this->parse_json_param( $params['fields'] ?? array() );
		$async       = $this->parse_json_param( $params['async'] ?? array() );
		$status      = $params['status'] ?? null;
		$label       = $params['custom_label'] ?? null;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'recipe_id is required. Use list_recipes to find recipe IDs.' );
		}

		// Resolve parent from existing action when not explicitly provided.
		if ( empty( $parent_type ) || $parent_id <= 0 ) {
			$resolved = $this->resolve_existing_parent( $action_id, $recipe_id );
			if ( is_array( $resolved ) && ! empty( $resolved['isError'] ) ) {
				return $resolved;
			}
			$parent_type = ! empty( $parent_type ) ? $parent_type : $resolved['parent_type'];
			$parent_id   = $parent_id > 0 ? $parent_id : $resolved['parent_id'];
		}

		// Validate parent matches.
		$parent_error = $this->validate_parent( $parent_type, $parent_id, $recipe_id );
		if ( null !== $parent_error ) {
			return $parent_error;
		}

		// Normalize select/multi-select fields for update path.
		$action_code    = get_post_meta( $action_id, 'code', true );
		$requested_code = $params['action_code'] ?? null;
		if ( ! empty( $action_code ) && ! empty( $fields ) ) {
			$normalizer = new Field_Mcp_Input_Resolver();
			$fields     = $normalizer->normalize( 'actions', $action_code, $fields );
		}

		// Check for repeater garbage detected during normalization.
		if ( ! empty( $fields['__validation_error'] ) ) {
			$error_msg = $fields['__validation_error'];
			unset( $fields['__validation_error'] );
			return Json_Rpc_Response::create_error_response( $error_msg );
		}

		// Validate tokens in fields.
		$validation = Token_Validator::validate( $recipe_id, $fields );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		// Add custom label to fields if provided (including empty string to clear).
		if ( null !== $label ) {
			$fields['_automator_custom_item_name_'] = sanitize_text_field( $label );
		}

		$result = $this->action_service->update_action( $action_id, $fields, $async, $status, $parent_id, $requested_code );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use get_recipe to verify the action exists.'
			);
		}

		$action_payload = $this->format_action_response( $result['action'] ?? array() );

		$payload = array(
			'recipe_id'   => $recipe_id,
			'parent_type' => $parent_type,
			'parent_id'   => $parent_id,
			'action'      => $action_payload,
			'links'       => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),

		);

		$notes = array();
		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$notes[] = $result['message'];
		}

		// Warn if anonymous recipe needs user selector (fields may now contain user tokens).
		$action_code      = get_post_meta( $action_id, 'code', true );
		$advisor          = new User_Selector_Advisor();
		$selector_warning = $advisor->check_after_action_add( $recipe_id, $action_code ?: '', $fields );
		if ( $selector_warning ) {
			$notes[] = $selector_warning;
		}

		if ( ! empty( $notes ) ) {
			$payload['notes'] = $notes;
		}

		return Json_Rpc_Response::create_success_response( 'Action updated successfully', $payload );
	}

	// ──────────────────────────────────────────────────────────────────
	// SHARED HELPERS — ported from Add_Action_Tool / Update_Action_Tool
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Resolve parent_type and parent_id from the existing action post.
	 *
	 * When the caller omits parent_type/parent_id on update, derive them from the
	 * action's current post_parent so the update keeps the action in place.
	 *
	 * @param int $action_id Action post ID.
	 * @param int $recipe_id Recipe ID for context.
	 * @return array Resolved parent or error response.
	 */
	private function resolve_existing_parent( int $action_id, int $recipe_id ): array {
		$action_post = get_post( $action_id );

		if ( ! $action_post || AUTOMATOR_POST_TYPE_ACTION !== $action_post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d not found. Use get_recipe to find action IDs.', $action_id )
			);
		}

		// Verify action belongs to the specified recipe (direct child or via loop).
		$current_parent_id = (int) $action_post->post_parent;
		$owner_recipe_id   = $current_parent_id;
		$parent_post       = get_post( $current_parent_id );
		if ( $parent_post && AUTOMATOR_POST_TYPE_LOOP === $parent_post->post_type ) {
			$owner_recipe_id = (int) $parent_post->post_parent;
		}
		if ( $owner_recipe_id !== $recipe_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d does not belong to recipe %d.', $action_id, $recipe_id )
			);
		}

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

		$parent_type = AUTOMATOR_POST_TYPE_LOOP === $parent_post->post_type ? 'loop' : 'recipe';

		return array(
			'parent_type' => $parent_type,
			'parent_id'   => $current_parent_id,
		);
	}

	/**
	 * Format action response data.
	 *
	 * @param array $action_data Action data from service.
	 * @return array Formatted response.
	 */
	private function format_action_response( array $action_data ): array {
		return array(
			'action_id'                    => $action_data['action_id'] ?? 0,
			'action_code'                  => $action_data['action_code'] ?? '',
			'integration'                  => $action_data['integration'] ?? '',
			'type'                         => $action_data['user_type'] ?? '',
			'recipe_id'                    => $action_data['recipe_id'] ?? 0,
			'sentence_human_readable'      => $action_data['sentence_human_readable'] ?? '',
			'sentence_human_readable_html' => $action_data['sentence_human_readable_html'] ?? '',
			'fields'                       => $this->ensure_object( $action_data['config'] ?? array() ),
			'async'                        => ! empty( $action_data['async'] ) ? $action_data['async'] : null,
		);
	}

}
