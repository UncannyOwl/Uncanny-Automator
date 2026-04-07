<?php
/**
 * Consolidated trigger upsert tool.
 *
 * Replaces: add_trigger, update_trigger.
 * trigger_id absent = create, trigger_id present = update.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Triggers;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;

use Uncanny_Automator\Api\Services\Field\Field_Mcp_Input_Resolver;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * Save Trigger Tool — upsert.
 *
 * Create: omit trigger_id. Requires recipe_id, trigger_code, fields.
 * Update: include trigger_id. Requires trigger_id. fields and status are optional.
 *
 * @since 7.1.0
 */
class Save_Trigger_Tool extends Abstract_MCP_Tool {

	/**
	 * @var Trigger_CRUD_Service
	 */
	private Trigger_CRUD_Service $trigger_service;

	/**
	 * @var Trigger_Registry_Service
	 */
	private Trigger_Registry_Service $trigger_registry_service;

	/**
	 * Constructor.
	 *
	 * @param Trigger_CRUD_Service|null     $trigger_service          Trigger CRUD service.
	 * @param Trigger_Registry_Service|null $trigger_registry_service Trigger registry service.
	 */
	public function __construct(
		?Trigger_CRUD_Service $trigger_service = null,
		?Trigger_Registry_Service $trigger_registry_service = null
	) {
		$this->trigger_service          = $trigger_service ?? Trigger_CRUD_Service::instance();
		$this->trigger_registry_service = $trigger_registry_service ?? Trigger_Registry_Service::instance();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'save_trigger';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Create or update a trigger. Omit trigger_id to create, include trigger_id to update. '
			. 'Create requires recipe_id and trigger_code. Fields may be empty for triggers with no configurable fields. '
			. 'Update requires trigger_id; fields and status are optional. '
			. 'Get field definitions from get_component_schema first. '
			. 'CRITICAL: For dropdown fields, always pass BOTH the value AND the _readable suffix '
			. '(e.g., "LDCOURSE": "656", "LDCOURSE_readable": "Sales 101").';
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
					'description' => 'Recipe ID. Required for create.',
					'minimum'     => 1,
				),
				'trigger_id'   => array(
					'type'        => 'integer',
					'description' => 'Existing trigger ID to update. Omit to create a new trigger.',
					'minimum'     => 1,
				),
				'trigger_code' => array(
					'type'        => 'string',
					'description' => 'Trigger code from registry (e.g., "WP_USER_REGISTERED"). Required for create. Use search or get_component_schema to find codes.',
					'minLength'   => 2,
				),
				'fields'       => array(
					'type'                 => 'object',
					'description'          => 'Trigger field values. May be empty for triggers with no configurable fields. Optional for update (only provide changed fields). For dropdowns, pass BOTH value AND _readable suffix.',
					'additionalProperties' => true,
				),
				'status'       => array(
					'type'        => 'string',
					'description' => 'Trigger publication status. Update-only. "publish" = active, "draft" = disabled. Recipe/trigger/action statuses are independent.',
					'enum'        => array( 'draft', 'publish' ),
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
				'recipe_id'  => array( 'type' => 'integer' ),
				'trigger'    => array(
					'type'       => 'object',
					'properties' => array(
						'trigger_id'                   => array( 'type' => 'integer' ),
						'trigger_code'                 => array( 'type' => 'string' ),
						'integration'                  => array( 'type' => 'string' ),
						'sentence_human_readable'      => array( 'type' => 'string' ),
						'sentence_human_readable_html' => array( 'type' => 'string' ),
						'config'                       => array( 'type' => 'object' ),
						'recipe_id'                    => array( 'type' => 'integer' ),
					),
				),
				'links'      => array( 'type' => 'object' ),
				'notes'      => array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'recipe_id', 'trigger' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$trigger_id = isset( $params['trigger_id'] ) ? (int) $params['trigger_id'] : null;

		// Route: update if trigger_id is present, create if absent.
		if ( null !== $trigger_id && $trigger_id > 0 ) {
			return $this->update_trigger( $trigger_id, $params );
		}

		return $this->create_trigger( $params );
	}

	// ──────────────────────────────────────────────────────────────────
	// UPDATE PATH
	// Same logic as Update_Trigger_Tool::execute_tool().
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Update an existing trigger.
	 *
	 * @param int   $trigger_id Trigger post ID.
	 * @param array $params     Tool parameters.
	 *
	 * @return array JSON-RPC response.
	 */
	private function update_trigger( int $trigger_id, array $params ): array {

		$fields         = $this->parse_json_param( $params['fields'] ?? array() );
		$status         = $params['status'] ?? null;
		$recipe_id      = (int) ( $params['recipe_id'] ?? 0 );
		$requested_code = $params['trigger_code'] ?? null;

		// Normalize MCP field input (multi-select JSON encoding, _readable suffixes, etc.).
		$trigger_code = get_post_meta( $trigger_id, 'code', true );
		if ( ! empty( $trigger_code ) && ! empty( $fields ) ) {
			$resolver = new Field_Mcp_Input_Resolver();
			$fields   = $resolver->normalize( 'triggers', $trigger_code, $fields );
		}

		// Map status to post_status for the service layer.
		if ( null !== $status ) {
			$fields['post_status'] = $status;
		}

		$result = $this->trigger_service->update_trigger(
			$trigger_id,
			$fields,
			$recipe_id > 0 ? $recipe_id : null,
			$requested_code
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$trigger_payload = $result['trigger'] ?? array();
		$recipe_id       = isset( $trigger_payload['recipe_id'] ) ? (int) $trigger_payload['recipe_id'] : 0;

		$payload = array(
			'recipe_id' => $recipe_id,
			'trigger'   => $trigger_payload,
			'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return Json_Rpc_Response::create_success_response( 'Trigger updated successfully', $payload );
	}

	// ──────────────────────────────────────────────────────────────────
	// CREATE PATH
	// Same logic as Add_Trigger_Tool::execute_tool().
	// ──────────────────────────────────────────────────────────────────

	/**
	 * Create a new trigger on a recipe.
	 *
	 * Validates transport-level parameters, then delegates to the service layer
	 * for entity construction, domain validation, persistence, and any
	 * post-save extensions (Pro enriches the response via the
	 * `automator_trigger_response` filter).
	 *
	 * @param array $params Tool parameters.
	 *
	 * @return array JSON-RPC response.
	 */
	private function create_trigger( array $params ): array {

		$recipe_id    = (int) ( $params['recipe_id'] ?? 0 );
		$trigger_code = trim( $params['trigger_code'] ?? '' );
		$fields       = $this->parse_json_param( $params['fields'] ?? array() );

		// Validate create-specific requirements.
		if ( ! $recipe_id ) {
			return Json_Rpc_Response::create_error_response( "Parameter 'recipe_id' is required for creating a trigger." );
		}

		if ( empty( $trigger_code ) ) {
			return Json_Rpc_Response::create_error_response( "Parameter 'trigger_code' is required. Use search to discover trigger codes." );
		}

		// Get trigger definition from registry (transport-level validation of code existence).
		$trigger_def = $this->trigger_registry_service->get_trigger_definition( $trigger_code, false );

		if ( is_wp_error( $trigger_def ) ) {
			return Json_Rpc_Response::create_error_response(
				'Trigger code not found: ' . $trigger_code . '. Use search to discover available triggers.'
			);
		}

		if ( empty( $trigger_def ) ) {
			return Json_Rpc_Response::create_error_response( 'Trigger definition not found: ' . $trigger_code );
		}

		// Normalize MCP field input (multi-select JSON encoding, _readable suffixes, etc.).
		$resolver = new Field_Mcp_Input_Resolver();
		$fields   = $resolver->normalize( 'triggers', $trigger_code, $fields );
		$fields   = $this->strip_sentence_artifacts( $fields );

		$fields['added_by_llm'] = true;

		// Build config array for the service layer.
		$config = array(
			'integration'   => $trigger_def['integration'] ?? '',
			'sentence'      => $trigger_def['sentence'] ?? '',
			'meta_code'     => $trigger_code,
			'type'          => $trigger_def['trigger_type'] ?? '',
			'hook'          => $trigger_def['trigger_hook'] ?? array(),
			'tokens'        => $trigger_def['tokens'] ?? array(),
			'configuration' => $fields,
		);

		$result = $this->trigger_service->add_to_recipe( $recipe_id, $trigger_code, $config );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$trigger_payload = $result['trigger'] ?? array();
		$trigger_id      = $result['trigger_id'] ?? ( $trigger_payload['trigger_id'] ?? 0 );

		$payload = array(
			'recipe_id' => $recipe_id,
			'trigger'   => $trigger_payload,
			'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return Json_Rpc_Response::create_success_response( 'Trigger added to recipe', $payload );
	}

	/**
	 * Strip client-provided sentence artifacts from incoming fields.
	 *
	 * @param array $fields Incoming fields.
	 * @return array Cleaned fields.
	 */
	private function strip_sentence_artifacts( array $fields ): array {
		unset( $fields['sentence'], $fields['sentence_human_readable'], $fields['sentence_human_readable_html'] );
		return $fields;
	}

}
