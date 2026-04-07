<?php
/**
 * Consolidated loop filter upsert tool.
 *
 * Replaces: loop_filter_add, loop_filter_update.
 * filter_id absent = create, filter_id present = update.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Components\Loop\Filter\Services\Field_Normalizer;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Presentation\Loop\Filters\Loop_Filter_Sentence_Composer;
use Uncanny_Automator\Api\Services\Field\Field_Mcp_Input_Resolver;
use Uncanny_Automator\Api\Services\Field\Utilities\Field_Validator;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_Registry_Service;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;

/**
 * Save Loop Filter Tool — upsert.
 *
 * Create: omit filter_id. Requires recipe_id, loop_id, filter_code, integration_code.
 * Update: include filter_id. Requires recipe_id, filter_id. Fields optional.
 *
 * @since 7.1.0
 */
class Save_Loop_Filter_Tool extends Abstract_Filter_Tool {

	/**
	 * @var Filter_CRUD_Service
	 */
	private Filter_CRUD_Service $filter_service;

	/**
	 * @var Loop_CRUD_Service
	 */
	private Loop_CRUD_Service $loop_service;

	/**
	 * Constructor.
	 *
	 * @param Filter_CRUD_Service|null           $filter_service    Filter CRUD service.
	 * @param Loop_CRUD_Service|null             $loop_service      Loop CRUD service.
	 * @param Filter_Registry_Service|null       $registry_service  Filter registry service.
	 * @param Field_Normalizer|null              $field_normalizer  Field normalizer.
	 * @param Loop_Filter_Sentence_Composer|null $sentence_composer Sentence composer.
	 */
	public function __construct(
		?Filter_CRUD_Service $filter_service = null,
		?Loop_CRUD_Service $loop_service = null,
		?Filter_Registry_Service $registry_service = null,
		?Field_Normalizer $field_normalizer = null,
		?Loop_Filter_Sentence_Composer $sentence_composer = null
	) {
		$this->filter_service    = $filter_service ?? Filter_CRUD_Service::instance();
		$this->loop_service      = $loop_service ?? Loop_CRUD_Service::instance();
		$this->registry_service  = $registry_service ?? Filter_Registry_Service::instance();
		$this->field_normalizer  = $field_normalizer ?? new Field_Normalizer();
		$this->sentence_composer = $sentence_composer ?? new Loop_Filter_Sentence_Composer();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'save_loop_filter';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Create or update a loop filter. Omit filter_id to create, include filter_id to update. '
			. 'Create requires recipe_id, loop_id, filter_code, and integration_code. '
			. 'Update requires recipe_id and filter_id. '
			. 'CRITICAL: Each field must be an object with {value: "raw_value", label: "Human readable label"}.';
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
				'recipe_id'        => array(
					'type'        => 'integer',
					'description' => 'Recipe ID. Required for both create and update.',
					'minimum'     => 1,
				),
				'loop_id'          => array(
					'type'        => 'integer',
					'description' => 'Loop ID to add the filter to. Required for create.',
					'minimum'     => 1,
				),
				'filter_id'        => array(
					'type'        => 'integer',
					'description' => 'Existing filter ID to update. Omit to create a new filter.',
					'minimum'     => 1,
				),
				'filter_code'      => array(
					'type'        => 'string',
					'description' => 'Filter code from registry (e.g., USER_HAS_ROLE). Required for create. Use search with type=loop_filter to find codes.',
				),
				'integration_code' => array(
					'type'        => 'string',
					'description' => 'Integration code (e.g., WP, WOOCOMMERCE). Required for create.',
				),
				'fields'           => array(
					'type'              => 'object',
					'description'       => 'Filter field values. Each field is {value: string, label: string}.',
					'patternProperties' => array(
						'^[A-Z_0-9]+$' => array(
							'type'       => 'object',
							'properties' => array(
								'value' => array(
									'type' => 'string',
									'description' => 'Raw field value.',
								),
								'label' => array(
									'type' => 'string',
									'description' => 'Human-readable label.',
								),
							),
							'required'   => array( 'value', 'label' ),
						),
					),
					'additionalProperties' => false,
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
				'filter_id' => array( 'type' => 'integer' ),
				'filter'    => array(
					'type'       => 'object',
					'properties' => array(
						'id'               => array( 'type' => 'integer' ),
						'code'             => array( 'type' => 'string' ),
						'integration_code' => array( 'type' => 'string' ),
						'loop_id'          => array( 'type' => 'integer' ),
					),
				),
				'loop_id'   => array( 'type' => 'integer' ),
				'links'     => array( 'type' => 'object' ),
			),
			'required'   => array( 'filter_id', 'filter', 'loop_id', 'links' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		$this->require_authenticated_executor( $user_context );

		$filter_id = isset( $params['filter_id'] ) ? (int) $params['filter_id'] : null;

		if ( null !== $filter_id && $filter_id > 0 ) {
			return $this->update_filter( $filter_id, $params );
		}

		return $this->create_filter( $params );
	}
	// ──────────────────────────────────────────────────────────────────
	// CREATE PATH — port of Loop_Filter_Add_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────
	/**
	 * Create filter.
	 *
	 * @param array $params The parameters.
	 * @return array
	 */
	private function create_filter( array $params ): array {

		$recipe_id        = (int) $params['recipe_id'];
		$loop_id          = isset( $params['loop_id'] ) ? (int) $params['loop_id'] : 0;
		$filter_code      = $params['filter_code'] ?? '';
		$integration_code = $params['integration_code'] ?? '';

		if ( $loop_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'loop_id is required for creating a filter.' );
		}

		if ( empty( $filter_code ) ) {
			return Json_Rpc_Response::create_error_response( 'filter_code is required for creating a filter. Use search with type=loop_filter.' );
		}

		if ( empty( $integration_code ) ) {
			return Json_Rpc_Response::create_error_response( 'integration_code is required for creating a filter.' );
		}

		$fields_input = $this->parse_json_param( $params['fields'] ?? array() );

		$validation_error = $this->validate_mcp_field_structure( $fields_input );
		if ( null !== $validation_error ) {
			return $validation_error;
		}

		$fields_flat   = Field_Mcp_Input_Resolver::flatten( $fields_input );
		$fields_values = $this->extract_values_for_validation( $fields_input );

		$validation = Token_Validator::validate( $recipe_id, $fields_values );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		$definition = $this->registry_service->validate_filter_code_and_get_definition( $filter_code );
		if ( is_wp_error( $definition ) ) {
			return Json_Rpc_Response::create_error_response( $definition->get_error_message() );
		}

		// Validate field values against filter schema.
		$field_validation = $this->validate_filter_fields( $definition, $fields_values );
		if ( is_wp_error( $field_validation ) ) {
			return Json_Rpc_Response::create_error_response( $field_validation->get_error_message() );
		}

		$backup = $this->build_filter_backup( $fields_input, $definition );

		$result = $this->filter_service->add_to_loop( $loop_id, $filter_code, $integration_code, $fields_flat, $backup );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$link_recipe_id = $this->resolve_recipe_id_from_loop( $loop_id );

		return Json_Rpc_Response::create_success_response(
			'Filter added to loop',
			array(
				'filter_id' => $result['filter_id'] ?? 0,
				'filter'    => $result['filter'] ?? array(),
				'loop_id'   => $loop_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $link_recipe_id ),
			)
		);
	}
	// ──────────────────────────────────────────────────────────────────
	// UPDATE PATH — port of Loop_Filter_Update_Tool::execute_tool()
	// ──────────────────────────────────────────────────────────────────
	/**
	 * Update filter.
	 *
	 * @param int $filter_id The ID.
	 * @param array $params The parameters.
	 * @return array
	 */
	private function update_filter( int $filter_id, array $params ): array {

		$recipe_id    = (int) $params['recipe_id'];
		$fields_input = $this->parse_json_param( $params['fields'] ?? array() );

		$validation_error = $this->validate_mcp_field_structure( $fields_input );
		if ( null !== $validation_error ) {
			return $validation_error;
		}

		$fields_flat   = Field_Mcp_Input_Resolver::flatten( $fields_input );
		$fields_values = $this->extract_values_for_validation( $fields_input );

		$validation = Token_Validator::validate( $recipe_id, $fields_values );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		// Validate filter exists and belongs to recipe.
		$filter_post = get_post( $filter_id );
		if ( ! $filter_post || AUTOMATOR_POST_TYPE_LOOP_FILTER !== $filter_post->post_type ) {
			return Json_Rpc_Response::create_error_response( "Filter not found: {$filter_id}." );
		}

		$loop_id   = (int) $filter_post->post_parent;
		$loop_post = get_post( $loop_id );

		if ( ! $loop_post || AUTOMATOR_POST_TYPE_LOOP !== $loop_post->post_type || (int) $loop_post->post_parent !== $recipe_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Filter %d does not belong to recipe %d.', $filter_id, $recipe_id )
			);
		}

		// Get filter code for definition lookup.
		$filter_code = get_post_meta( $filter_id, 'code', true );
		if ( empty( $filter_code ) ) {
			return Json_Rpc_Response::create_error_response( "Filter code not found for filter ID: {$filter_id}." );
		}

		$definition = $this->registry_service->validate_filter_code_and_get_definition( $filter_code );
		if ( is_wp_error( $definition ) ) {
			return Json_Rpc_Response::create_error_response( $definition->get_error_message() );
		}

		// Validate field values against filter schema.
		$field_validation = $this->validate_filter_fields( $definition, $fields_values );
		if ( is_wp_error( $field_validation ) ) {
			return Json_Rpc_Response::create_error_response( $field_validation->get_error_message() );
		}

		$backup = $this->build_filter_backup( $fields_input, $definition );

		$result = $this->filter_service->update_filter( $filter_id, $fields_flat, $backup );

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		$link_recipe_id = $this->resolve_recipe_id_from_loop( $loop_id );

		return Json_Rpc_Response::create_success_response(
			'Filter updated successfully',
			array(
				'filter_id' => $filter_id,
				'filter'    => $result['filter'] ?? array(),
				'loop_id'   => $loop_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $link_recipe_id ),
			)
		);
	}

	/**
	 * Resolve recipe_id from a loop's post_parent.
	 *
	 * @param int $loop_id Loop ID.
	 * @return int Recipe ID or 0.
	 */
	private function resolve_recipe_id_from_loop( int $loop_id ): int {
		$loop_result = $this->loop_service->get_loop( $loop_id );
		if ( ! is_wp_error( $loop_result ) && isset( $loop_result['loop']['recipe_id'] ) ) {
			return (int) $loop_result['loop']['recipe_id'];
		}
		return 0;
	}
}
