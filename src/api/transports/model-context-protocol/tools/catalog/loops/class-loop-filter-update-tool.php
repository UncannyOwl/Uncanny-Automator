<?php
/**
 * MCP tool for updating a loop filter.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_CRUD_Service;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_Registry_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Loop\Filter\Services\Field_Normalizer;
use Uncanny_Automator\Api\Presentation\Loop\Filters\Loop_Filter_Sentence_Composer;

/**
 * Loop Filter Update Tool.
 *
 * Updates a filter's field values.
 * Extends Abstract_Filter_Tool for shared field handling logic.
 *
 * @since 7.0.0
 */
class Loop_Filter_Update_Tool extends Abstract_Filter_Tool {

	/**
	 * Filter CRUD service.
	 *
	 * @var Filter_CRUD_Service
	 */
	private $filter_service;

	/**
	 * Loop CRUD service.
	 *
	 * @var Loop_CRUD_Service
	 */
	private $loop_service;

	/**
	 * Constructor.
	 *
	 * @param Filter_CRUD_Service|null            $filter_service     Optional filter service instance.
	 * @param Loop_CRUD_Service|null              $loop_service       Optional loop service instance.
	 * @param Filter_Registry_Service|null        $registry_service   Optional registry service instance.
	 * @param Field_Normalizer|null               $field_normalizer   Optional field normalizer instance.
	 * @param Loop_Filter_Sentence_Composer|null  $sentence_composer  Optional sentence composer instance.
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
	 * Get tool name.
	 *
	 * @since 7.0.0
	 * @return string Tool name.
	 */
	public function get_name() {
		return 'loop_filter_update';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Update a loop filter\'s field values. CRITICAL: Each field must be an object with {value: "raw_value", label: "Human readable label"}. For dropdowns, provide the option label. For text fields, value and label can be the same.';
	}

	/**
	 * Define the input schema.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema for parameters.
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Recipe ID the loop filter belongs to.',
					'minimum'     => 1,
				),
				'filter_id' => array(
					'type'        => 'integer',
					'description' => 'Filter ID to update.',
					'minimum'     => 1,
				),
				'fields'    => array(
					'type'        => 'object',
					'description' => 'New field values. Each field must be an object with {value: string, label: string}. Example: {"ROLE_FIELD": {value: "subscriber", label: "Subscriber"}}. For dropdowns, provide human-readable labels since registry does not have options.',
					'patternProperties' => array(
						'^[A-Z_0-9]+$' => array(
							'type'       => 'object',
							'properties' => array(
								'value' => array(
									'type'        => 'string',
									'description' => 'The raw field value.',
								),
								'label' => array(
									'type'        => 'string',
									'description' => 'The human-readable label for display.',
								),
							),
							'required'   => array( 'value', 'label' ),
						),
					),
					'additionalProperties' => false,
				),
			),
			'required'   => array( 'recipe_id', 'filter_id' ),
		);
	}

	/**
	 * Execute the tool.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The user context.
	 * @param array        $params       Tool parameters.
	 * @return array MCP response.
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		// Enforce authentication.
		$this->require_authenticated_executor( $user_context );

		// Extract and validate parameters.
		$recipe_id    = (int) $params['recipe_id'];
		$filter_id    = (int) $params['filter_id'];
		$fields_input = $this->parse_json_param( $params['fields'] ?? array() );

		// Validate MCP field structure (delegates to base class).
		$validation_error = $this->validate_mcp_field_structure( $fields_input );
		if ( null !== $validation_error ) {
			return $validation_error;
		}

		// Convert MCP format to flat structure for CRUD service (delegates to base class).
		$fields_flat   = $this->convert_mcp_to_flat( $fields_input );
		$fields_values = $this->extract_values_for_validation( $fields_input );

		// Validate tokens in field values before proceeding.
		$validation = Token_Validator::validate( $recipe_id, $fields_values );
		if ( ! $validation['valid'] ) {
			return Json_Rpc_Response::create_error_response( $validation['message'] );
		}

		// Get existing filter to determine filter code.
		$filter_post = get_post( $filter_id );
		if ( ! $filter_post || 'uo-loop-filter' !== $filter_post->post_type ) {
			return Json_Rpc_Response::create_error_response(
				"Filter not found with ID: {$filter_id}. Use loop_filter_list to find valid filter IDs."
			);
		}

		// Validate filter belongs to a loop in the specified recipe.
		$loop_id   = (int) $filter_post->post_parent;
		$loop_post = get_post( $loop_id );

		if ( ! $loop_post || 'uo-loop' !== $loop_post->post_type || (int) $loop_post->post_parent !== $recipe_id ) {
			return Json_Rpc_Response::create_error_response(
				sprintf(
					'Filter %d does not belong to recipe %d. Use loop_filter_list with the correct recipe_id.',
					$filter_id,
					$recipe_id
				)
			);
		}

		// Get filter code from post meta to fetch definition.
		$filter_code = get_post_meta( $filter_id, 'code', true );
		if ( empty( $filter_code ) ) {
			return Json_Rpc_Response::create_error_response(
				"Filter code not found for filter ID: {$filter_id}."
			);
		}

		// Get filter definition to extract sentence template and field labels.
		$definition = $this->registry_service->validate_filter_code_and_get_definition( $filter_code );
		if ( is_wp_error( $definition ) ) {
			return Json_Rpc_Response::create_error_response(
				$definition->get_error_message()
			);
		}

		// Build backup with sentence HTML (delegates to base class).
		$backup = $this->build_filter_backup( $fields_input, $definition );

		$result = $this->filter_service->update_filter(
			$filter_id,
			$fields_flat,
			$backup
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use loop_filter_get with filter_id to verify the filter exists.'
			);
		}

		// Get loop_id from filter's post_parent.
		$loop_id = $filter_post ? (int) $filter_post->post_parent : 0;

		// Get recipe_id for links.
		$recipe_id = 0;
		if ( $loop_id > 0 ) {
			$loop_result = $this->loop_service->get_loop( $loop_id );
			if ( ! is_wp_error( $loop_result ) && isset( $loop_result['loop']['recipe_id'] ) ) {
				$recipe_id = (int) $loop_result['loop']['recipe_id'];
			}
		}

		return Json_Rpc_Response::create_success_response(
			'Filter updated successfully',
			array(
				'filter_id' => $filter_id,
				'filter'    => $result['filter'] ?? array(),
				'loop_id'   => $loop_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
}
