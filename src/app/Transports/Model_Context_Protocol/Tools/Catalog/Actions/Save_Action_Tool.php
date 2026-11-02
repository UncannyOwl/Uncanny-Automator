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

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\App\Recipe_Builder\Field\Services\Custom_Item_Name_Field;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Delay_Unit;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Mcp_Input_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Validation\Token_Validator;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Action_Availability_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Tool_Availability_Settings;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\User_Selector_Advisor;

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
	 * Site-owner switches and the banned list.
	 *
	 * @var Action_Availability_Policy
	 */
	private Action_Availability_Policy $availability;

	/**
	 * Constructor.
	 *
	 * @param Action_CRUD_Service|null        $action_service Action CRUD service.
	 * @param Action_Availability_Policy|null $availability   Availability policy; defaults to the stored switches.
	 */
	public function __construct( ?Action_CRUD_Service $action_service = null, ?Action_Availability_Policy $availability = null ) {
		$this->action_service = $action_service ?? Action_CRUD_Service::instance();
		$this->availability   = $availability ?? new Action_Availability_Policy();
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
			. 'Update requires action_id and recipe_id; send only changed fields, which are merged with existing values. '
			. 'Get field definitions from get_component_schema first. '
			. 'CRITICAL: For dropdown fields, always pass BOTH the value AND the _readable suffix '
			. '(e.g., "SLACKCHANNEL": "C123", "SLACKCHANNEL_readable": "#general"). '
			. 'To make the action conditional or delayed, pass conditions/delay here (wraps the action '
			. 'in a filter/delay_schedule block for you) or use save_block + save_filter_condition directly.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
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
					'enum'        => array( 'recipe', 'loop', 'block' ),
					'description' => 'Where to place the action. Required for create. "recipe" = direct child (runs once). "loop" = inside a loop (runs per iteration). "block" = inside a filter/delay_schedule block from save_block. Optional for update (defaults to current parent).',
				),
				'parent_id'    => array(
					'type'        => 'integer',
					'description' => 'Parent ID matching parent_type. Required for create. If parent_type=recipe, use recipe_id. If parent_type=loop, use loop_id. If parent_type=block, use block_id. Optional for update.',
					'minimum'     => 1,
				),
				'path_code'    => array(
					'type'        => 'string',
					'description' => 'Branch of the parent block to place the action in (e.g. FILTER-CONDITIONS-MET, FILTER-CONDITIONS-NOT-MET), on create or on update (a move, or a change of branch within the same block). Only valid with a block/loop parent; omit for the block\'s default branch.',
				),
				'fields'       => array(
					'type'                 => 'object',
					'description'          => 'Editable option-code values from get_component_schema. Use each field\'s declared type, e.g. {"WPCPOSTTITLE":"New title"}; do not wrap values in {value, _readable} objects. Dropdown labels are sibling keys, e.g. {"SLACKCHANNEL":"C123","SLACKCHANNEL_readable":"#general"}. On update, send only changed values; omitted fields are preserved. Do not copy display metadata such as sentence from recipe readback into fields.',
					'additionalProperties' => true,
				),
				'delay'        => array(
					'type'                 => 'object',
					'description'          => 'Optional: wrap the action in a new delay_schedule (Queue) block. DELAY_MODE is required: delay requires DELAY_VALUE (non-negative integer) and DELAY_UNIT (' . implode( '|', Delay_Unit::accepted() ) . '); schedule requires SCHEDULE_DATE (Y-m-d) and SCHEDULE_TIME (h:i A, site timezone); token requires DELAY_TOKEN. Omit delay to add no timing; delay: {} is invalid. Invalid timing is rejected before saving the action. Omitting delay does not remove existing timing. To reuse an existing delay block, save with parent_type=block instead.',
					'additionalProperties' => true,
				),
				'conditions'   => array(
					'type'        => 'array',
					'description' => 'Optional: wrap the action in a new filter block carrying these conditions. Omit conditions or pass conditions: [] to add no filter; neither removes existing conditions. Discover codes with search type=condition; field schemas with get_component_schema type=condition. To add to an existing filter block, save with parent_type=block instead.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'integration'    => array( 'type' => 'string' ),
							'condition_code' => array( 'type' => 'string' ),
							'fields'         => array(
								'type'                 => 'object',
								'additionalProperties' => true,
							),
						),
						'required'   => array( 'integration', 'condition_code' ),
					),
				),
				'status'       => array(
					'type'        => 'string',
					'description' => 'Action publication status. Update-only. "publish" = active, "draft" = disabled. Omit it and a valid save (an add with fields, or an update) switches the action on while the recipe is a draft.',
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
					'type'                 => 'object',
					'properties'           => array(
						'action_id'                    => array( 'type' => 'integer' ),
						'action_code'                  => array( 'type' => 'string' ),
						'integration'                  => array( 'type' => 'string' ),
						'sentence_human_readable'      => array( 'type' => 'string' ),
						'sentence_human_readable_html' => array( 'type' => 'string' ),
						'config'                       => array( 'type' => 'object' ),
					),
					'required'             => array(
						'action_id',
						'action_code',
						'integration',
						'sentence_human_readable',
						'sentence_human_readable_html',
						'config',
					),
					'additionalProperties' => false,
				),
				'wrapped'     => array(
					'type'        => 'object',
					'description' => 'Present when delay/conditions inputs created a wrapping block; the action now lives inside it.',
					'properties'  => array(
						'delay_block_id'  => array( 'type' => 'integer' ),
						'filter_block_id' => array( 'type' => 'integer' ),
						'condition_ids'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
				'links'       => array( 'type' => 'object' ),
				'notes'       => array(
					'type'  => 'array',
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

		if ( ! empty( $params['conditions'] ) && ! $user_context->is_first_party_caller() ) {
			$settings = new Tool_Availability_Settings();
			foreach ( array( 'save_block', 'save_filter_condition' ) as $required_tool ) {
				if ( $settings->is_disabled( $required_tool ) ) {
					return Json_Rpc_Response::create_error_response(
						sprintf( 'Cannot save an action with conditions because %s is switched off for connected AI apps. No changes were saved.', $required_tool )
					);
				}
			}
		}

		// Covers create, and update when the caller asks for a different action.
		$requested_code = trim( (string) ( $params['action_code'] ?? '' ) );

		if ( '' !== $requested_code && ! $this->availability->is_available( $requested_code, $user_context ) ) {
			return Json_Rpc_Response::create_error_response( $this->availability->get_refusal_message( $requested_code ) );
		}

		$blocks = Block_Crud_Service::instance();
		if ( array_key_exists( 'delay', $params ) ) {
			$valid = $blocks->validate_delay_fields( $this->parse_json_param( $params['delay'] ) );
			if ( is_wp_error( $valid ) ) {
				return Json_Rpc_Response::create_error_response( $valid->get_error_message() . ' No changes were saved.' );
			}
		}
		if ( ! empty( $params['conditions'] ) ) {
			$valid = $blocks->validate_filter_conditions( $this->parse_json_param( $params['conditions'] ) );
			if ( is_wp_error( $valid ) ) {
				return Json_Rpc_Response::create_error_response( $valid->get_error_message() . ' No changes were saved.' );
			}
		}

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

		$refusal = $this->availability->field_refusal( (string) $action_code, $fields );
		if ( null !== $refusal ) {
			return Json_Rpc_Response::create_error_response( $refusal );
		}

		// Validate tokens in fields.
		$validation = ( new Token_Validator() )->validate( $recipe_id, $fields );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		// Add custom label to fields if provided.
		if ( ! empty( $label ) ) {
			$fields[ Custom_Item_Name_Field::FIELD_CODE ] = sanitize_text_field( $label );
		}

		$path_code = (string) ( $params['path_code'] ?? '' );

		if ( '' !== $path_code ) {
			// Branch placement rides the path-aware service entry, which
			// validates the path against the block type before any write.
			if ( 'recipe' === $parent_type ) {
				return Json_Rpc_Response::create_error_response( 'path_code is only valid with a block or loop parent — branches belong to blocks.' );
			}
			$result = $this->action_service->add_to_block_path( $recipe_id, $action_code, $fields, $parent_id, $path_code );
		} else {
			$result = $this->action_service->add_to_recipe(
				$recipe_id,
				$action_code,
				$fields,
				$parent_id
			);
		}

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use search to find valid action codes, or get_component_schema to verify field requirements.'
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

		$wrap_error = $this->apply_wrappers( (int) $action_payload['action_id'], $params, $payload, $notes );
		if ( null !== $wrap_error ) {
			return $wrap_error;
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
	 * An update error for the caller: a refusal with its reasons, so the caller
	 * knows what to fix; any other error with where to check the action.
	 *
	 * @param \WP_Error $error Service error.
	 *
	 * @return string
	 */
	private function update_error_message( \WP_Error $error ): string {
		$data = $error->get_error_data();
		if ( is_array( $data ) && ! empty( $data['violations'] ) ) {
			return $this->error_with_reasons( $error );
		}

		return $error->get_error_message() . ' Use get_recipe to verify the action exists.';
	}

	/**
	 * Update an existing action.
	 *
	 * @param int   $action_id Action post ID.
	 * @param array $params    Tool parameters.
	 * @return array JSON-RPC response.
	 */
	private function update_action( int $action_id, array $params ): array {

		$recipe_id           = (int) $params['recipe_id'];
		$parent_type         = $params['parent_type'] ?? null;
		$parent_id           = isset( $params['parent_id'] ) ? (int) $params['parent_id'] : 0;
		$fields              = $this->parse_json_param( $params['fields'] ?? array() );
		$status              = $params['status'] ?? null;
		$label               = $params['custom_label'] ?? null;
		$has_explicit_parent = is_string( $parent_type ) && '' !== $parent_type && $parent_id > 0;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'recipe_id is required. Use list_recipes to find recipe IDs.' );
		}

		// Always resolve the existing parent first. Besides supplying omitted
		// parent values, this proves the action already belongs to recipe_id so an
		// explicit target parent cannot be used to move another recipe's action.
		$resolved = $this->resolve_existing_parent( $action_id, $recipe_id, $has_explicit_parent );
		if ( is_array( $resolved ) && ! empty( $resolved['isError'] ) ) {
			return $resolved;
		}

		if ( empty( $parent_type ) || $parent_id <= 0 ) {
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

		// The requested code was checked earlier; a saved action's own code was not.
		$refusal = $this->availability->field_refusal( (string) ( $requested_code ? $requested_code : $action_code ), $fields, $this->saved_fields( $action_id ) );
		if ( null !== $refusal ) {
			return Json_Rpc_Response::create_error_response( $refusal );
		}

		// Validate tokens in fields.
		$validation = ( new Token_Validator() )->validate( $recipe_id, $fields );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		// Add custom label to fields if provided (including empty string to clear).
		if ( null !== $label ) {
			$fields[ Custom_Item_Name_Field::FIELD_CODE ] = sanitize_text_field( $label );
		}

		$path_code          = (string) ( $params['path_code'] ?? '' );
		$original_parent_id = null;
		if ( ! empty( $resolved['repair_parent'] ) ) {
			// The domain store cannot hydrate an action whose WordPress parent is
			// missing. Temporarily attach this already-validated orphan so the normal
			// CRUD service can perform the update; restore it if that service rejects
			// the request. This path is never used for an action with known ownership.
			$action_post        = get_post( $action_id );
			$original_parent_id = $action_post instanceof \WP_Post ? (int) $action_post->post_parent : 0;
			$reparented         = wp_update_post(
				array(
					'ID'          => $action_id,
					'post_parent' => $parent_id,
				),
				true
			);
			if ( is_wp_error( $reparented ) ) {
				return Json_Rpc_Response::create_error_response( 'Automator could not attach the orphaned action to its validated parent.' );
			}
		}

		try {
			$result = $this->action_service->update_action( $action_id, $fields, $status, $parent_id, $requested_code, '' === $path_code ? null : $path_code );
		} catch ( \Throwable $error ) {
			if ( null !== $original_parent_id ) {
				wp_update_post(
					array(
						'ID'          => $action_id,
						'post_parent' => $original_parent_id,
					)
				);
			}
			throw $error;
		}

		if ( is_wp_error( $result ) ) {
			if ( null !== $original_parent_id ) {
				wp_update_post(
					array(
						'ID'          => $action_id,
						'post_parent' => $original_parent_id,
					)
				);
			}

			return Json_Rpc_Response::create_error_response( $this->update_error_message( $result ) );
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

		$wrap_error = $this->apply_wrappers( $action_id, $params, $payload, $notes );
		if ( null !== $wrap_error ) {
			return $wrap_error;
		}

		// Warn if anonymous recipe needs user selector (fields may now contain user tokens).
		$action_code      = get_post_meta( $action_id, 'code', true );
		$advisor          = new User_Selector_Advisor();
		$action_code      = is_string( $action_code ) ? $action_code : '';
		$selector_warning = $advisor->check_after_action_add( $recipe_id, $action_code, $fields );
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
	 * An orphan can be repaired only when the caller supplies a complete target.
	 * Existing ownership is still checked before any non-orphan action can move.
	 *
	 * @param int  $action_id          Action post ID.
	 * @param int  $recipe_id          Recipe ID for context.
	 * @param bool $has_target_parent  Whether the caller supplied both parent fields.
	 * @return array Resolved parent or error response.
	 */
	private function resolve_existing_parent( int $action_id, int $recipe_id, bool $has_target_parent ): array {
		$action_post = get_post( $action_id );

		if ( ! $action_post || AUTOMATOR_POST_TYPE_ACTION !== $action_post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d not found. Use get_recipe to find action IDs.', $action_id )
			);
		}

		$current_parent_id = (int) $action_post->post_parent;

		if ( $current_parent_id <= 0 ) {
			if ( $has_target_parent ) {
				return array(
					'parent_type'   => null,
					'parent_id'     => 0,
					'repair_parent' => true,
				);
			}

			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d has no parent. Provide parent_type and parent_id explicitly.', $action_id )
			);
		}

		$parent_post = get_post( $current_parent_id );

		if ( ! $parent_post ) {
			if ( $has_target_parent ) {
				return array(
					'parent_type'   => null,
					'parent_id'     => 0,
					'repair_parent' => true,
				);
			}

			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d parent post %d not found. Provide parent_type and parent_id explicitly.', $action_id, $current_parent_id )
			);
		}

		// Resolve the owning recipe. Blocks nest arbitrarily, so a block
		// parent resolves through the store's recipe_id link (any depth).
		if ( AUTOMATOR_POST_TYPE_BLOCK === $parent_post->post_type ) {
			$block           = Block_Crud_Service::instance()->get_block( $current_parent_id );
			$owner_recipe_id = is_wp_error( $block ) ? 0 : (int) ( $block['recipe_id'] ?? 0 );
			$parent_type     = ! is_wp_error( $block ) && 'loop' === (string) ( $block['block_type'] ?? '' ) ? 'loop' : 'block';
		} else {
			$owner_recipe_id = $current_parent_id;
			$parent_type     = 'recipe';
		}

		if ( $owner_recipe_id !== $recipe_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Action %d does not belong to recipe %d.', $action_id, $recipe_id )
			);
		}

		return array(
			'parent_type' => $parent_type,
			'parent_id'   => $current_parent_id,
		);
	}

	/**
	 * The field values an action has saved.
	 *
	 * @param int $action_id Action ID.
	 * @return array
	 */
	private function saved_fields( int $action_id ): array {
		$action = $this->action_service->get_action( $action_id );

		return is_array( $action ) && is_array( $action['action']['config'] ?? null ) ? $action['action']['config'] : array();
	}

	/**
	 * Format action response data.
	 *
	 * @param array $action_data Action data from service.
	 * @return array Formatted response.
	 */
	private function format_action_response( array $action_data ): array {
		$config = $action_data['config'] ?? $action_data['fields'] ?? array();

		return array(
			'action_id'                    => (int) ( $action_data['action_id'] ?? $action_data['id'] ?? 0 ),
			'action_code'                  => (string) ( $action_data['action_code'] ?? $action_data['code'] ?? '' ),
			'integration'                  => (string) ( $action_data['integration'] ?? '' ),
			'sentence_human_readable'      => (string) ( $action_data['sentence_human_readable'] ?? '' ),
			'sentence_human_readable_html' => (string) ( $action_data['sentence_human_readable_html'] ?? '' ),
			'config'                       => $this->ensure_object( $config ),
		);
	}

	/**
	 * Expand the compound delay/conditions inputs into wrapping blocks.
	 *
	 * Runs after a successful save; the expansion itself is service-level
	 * sugar on Block_Crud_Service (the tool stays thin). On wrap failure
	 * the saved action stands. Error data identifies completed writes so
	 * callers can inspect and repair them instead of repeating the save.
	 *
	 * @param int   $action_id Saved action post ID.
	 * @param array $params    Tool parameters (reads delay + conditions).
	 * @param array $payload   Response payload, extended with `wrapped` in place.
	 * @param array $notes     Response notes, extended in place.
	 * @return array|null Error response on wrap failure, null on success/no-op.
	 */
	private function apply_wrappers( int $action_id, array $params, array &$payload, array &$notes ): ?array {
		$delay      = $this->parse_json_param( $params['delay'] ?? array() );
		$conditions = $this->parse_json_param( $params['conditions'] ?? array() );

		if ( empty( $delay ) && empty( $conditions ) ) {
			return null;
		}

		$wrapped = array();

		if ( ! empty( $delay ) ) {
			$result = Block_Crud_Service::instance()->wrap_action_in_delay_block( $action_id, $delay );
			if ( is_wp_error( $result ) ) {
				$partial = $result->get_error_data();
				if ( is_array( $partial ) && isset( $partial['block_id'] ) ) {
					$wrapped['delay_block_id'] = $partial['block_id'];
				}
				return Json_Rpc_Response::create_error_response(
					sprintf( 'Action %d saved, but wrapping it in a Queue failed: %s Inspect the saved action and reported block before repairing its placement. Do not repeat save_action.', $action_id, $result->get_error_message() ),
					array(
						'action_id' => $action_id,
						'action_saved' => true,
						'wrapped' => $this->ensure_object( $wrapped ),
					)
				);
			}
			$wrapped['delay_block_id'] = $result['block_id'];
			$notes[]                   = sprintf( 'Action wrapped in Queue block %d.', $result['block_id'] );
		}

		if ( ! empty( $conditions ) ) {
			$result = Block_Crud_Service::instance()->wrap_action_in_filter_block( $action_id, $conditions );
			if ( is_wp_error( $result ) ) {
				$partial    = $result->get_error_data();
				$error_data = array(
					'action_id' => $action_id,
					'action_saved' => true,
				);
				if ( is_array( $partial ) && isset( $partial['block_id'] ) ) {
					$wrapped['filter_block_id'] = $partial['block_id'];
					$wrapped['condition_ids']   = $partial['condition_ids'] ?? array();
				}
				if ( is_array( $partial ) && isset( $partial['failed_condition_index'] ) ) {
					$error_data['failed_condition_index'] = $partial['failed_condition_index'];
				}
				$error_data['wrapped'] = $this->ensure_object( $wrapped );
				return Json_Rpc_Response::create_error_response(
					sprintf( 'Action %d saved, but the conditions wrap failed: %s Inspect the saved action and reported blocks and conditions before repairing placement or missing conditions. Do not repeat save_action or create another filter blindly.', $action_id, $result->get_error_message() ),
					$error_data
				);
			}
			$wrapped['filter_block_id'] = $result['block_id'];
			$wrapped['condition_ids']   = $result['condition_ids'];
			$notes[]                    = sprintf( 'Action wrapped in filter block %d with %d condition(s).', $result['block_id'], count( $result['condition_ids'] ) );
		}

		$payload['wrapped'] = $wrapped;

		return null;
	}
}
