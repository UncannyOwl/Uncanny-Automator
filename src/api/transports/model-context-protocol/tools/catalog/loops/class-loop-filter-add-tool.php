<?php
/**
 * MCP tool for adding a filter to a loop.
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
 * Loop Filter Add Tool.
 *
 * Adds a filter to a loop to refine which items are processed.
 * Extends Abstract_Filter_Tool for shared field handling logic.
 *
 * @since 7.0.0
 */
class Loop_Filter_Add_Tool extends Abstract_Filter_Tool {

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
		return 'loop_filter_add';
	}

	/**
	 * Get tool description.
	 *
	 * @since 7.0.0
	 * @return string Tool description.
	 */
	public function get_description() {
		return 'Add a filter to a loop. Filters refine which items (users/posts/tokens) are processed during iteration. CRITICAL: Each field must be an object with {value: "raw_value", label: "Human readable label"}. For dropdowns, provide the option label (e.g., {value: "subscriber", label: "Subscriber"}). For text fields, value and label can be the same.';
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
				'recipe_id'        => array(
					'type'        => 'integer',
					'description' => 'Recipe ID the loop belongs to.',
					'minimum'     => 1,
				),
				'loop_id'          => array(
					'type'        => 'integer',
					'description' => 'Loop ID to add the filter to.',
					'minimum'     => 1,
				),
				'filter_code'      => array(
					'type'        => 'string',
					'description' => 'Filter code from the registry (e.g., USER_HAS_ROLE, POST_HAS_TERM). Get schema first with get_component_schema.',
				),
				'integration_code' => array(
					'type'        => 'string',
					'description' => 'Integration code the filter belongs to (e.g., WP, WOOCOMMERCE).',
				),
				'fields'           => array(
					'type'        => 'object',
					'description' => 'Filter field values. Each field must be an object with {value: string, label: string}. Example: {"ROLE_FIELD": {value: "subscriber", label: "Subscriber"}, "EMAIL": {value: "test@example.com", label: "test@example.com"}}. For dropdowns/AJAX fields, you MUST provide the human-readable label since the registry does not have the options.',
					'patternProperties' => array(
						'^[A-Z_0-9]+$' => array(
							'type'       => 'object',
							'properties' => array(
								'value' => array(
									'type'        => 'string',
									'description' => 'The raw field value (e.g., "subscriber", "123", "user@example.com").',
								),
								'label' => array(
									'type'        => 'string',
									'description' => 'The human-readable label for display (e.g., "Subscriber", "Product Name", "user@example.com"). For text fields, can be same as value.',
								),
							),
							'required'   => array( 'value', 'label' ),
						),
					),
					'additionalProperties' => false,
				),
			),
			'required'   => array( 'recipe_id', 'loop_id', 'filter_code', 'integration_code' ),
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

		// Get filter definition to extract sentence template and field labels.
		$definition = $this->registry_service->validate_filter_code_and_get_definition( $params['filter_code'] );
		if ( is_wp_error( $definition ) ) {
			return Json_Rpc_Response::create_error_response(
				$definition->get_error_message() . ' Use search_components with type=loop_filter to find valid filter codes.'
			);
		}

		// Build backup with sentence HTML (delegates to base class).
		$backup = $this->build_filter_backup( $fields_input, $definition );

		$result = $this->filter_service->add_to_loop(
			(int) $params['loop_id'],
			$params['filter_code'],
			$params['integration_code'],
			$fields_flat,
			$backup
		);

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response(
				$result->get_error_message() . ' Use loop_get to verify the loop exists.'
			);
		}

		$loop_id = (int) $params['loop_id'];

		// Get recipe_id for links.
		$loop_result = $this->loop_service->get_loop( $loop_id );
		$recipe_id   = 0;
		if ( ! is_wp_error( $loop_result ) && isset( $loop_result['loop']['recipe_id'] ) ) {
			$recipe_id = (int) $loop_result['loop']['recipe_id'];
		}

		return Json_Rpc_Response::create_success_response(
			'Filter added to loop successfully',
			array(
				'filter_id' => $result['filter_id'] ?? 0,
				'filter'    => $result['filter'] ?? array(),
				'loop_id'   => $loop_id,
				'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
			)
		);
	}
}
