<?php
/**
 * MCP catalog tool that registers a trigger instance on an existing recipe.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Triggers;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\Trigger\Trigger;
use Uncanny_Automator\Api\Components\Trigger\Trigger_Config;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Security\Security;
use Uncanny_Automator\Api\Services\Sentence_Html\Sentence_Human_Readable_Service;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Template;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Field_Value_Text;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Field_Label;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Value_Text_Collection;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Label_Collection;
use Uncanny_Automator\Api\Services\Trigger\Utilities\Trigger_Validator;
use Uncanny_Automator\Services\Integrations\Fields;
use WP_Error;

/**
 * Add Trigger Tool.
 *
 * @since 7.0.0
 */
class Add_Trigger_Tool extends Abstract_MCP_Tool {

	/**
	 * Trigger service.
	 *
	 * @var Trigger_Service
	 */
	private $trigger_service;

	/**
	 * Trigger registry service.
	 *
	 * @var Trigger_Registry_Service
	 */
	private $trigger_registry_service;

	/**
	 * Trigger validator.
	 *
	 * @var Trigger_Validator
	 */
	private $trigger_validator;

	/**
	 * Constructor.
	 *
	 * Allows for dependency injection of services for testing.
	 */
	public function __construct(
		?Trigger_CRUD_Service $trigger_service = null,
		?Trigger_Registry_Service $trigger_registry_service = null,
		?Trigger_Validator $trigger_validator = null
	) {
		$this->trigger_service          = $trigger_service ?? Trigger_CRUD_Service::instance();
		$this->trigger_registry_service = $trigger_registry_service ?? Trigger_Registry_Service::get_instance();
		$this->trigger_validator        = $trigger_validator ?? new Trigger_Validator();
	}

	/**
	 * Get name.
	 *
	 * @return mixed
	 */
	public function get_name(): string {
		return 'add_trigger';
	}

	/**
	 * Get description.
	 *
	 * @return mixed
	 */
	public function get_description(): string {
		return 'Add a new trigger to a recipe. Get schema with get_component_schema first, populate required fields, then call with recipe_id and trigger_code. CRITICAL: For dropdown fields, always pass BOTH the value AND the _readable suffix (e.g., "LDCOURSE": "656", "LDCOURSE_readable": "Sales 101") so the UI displays names instead of IDs.';
	}

	/**
	 * Schema definition.
	 *
	 * @return mixed
	 */
	public function schema_definition(): array {

		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'    => array(
					'type'        => 'integer',
					'description' => 'Target recipe ID where the trigger will be added. Must be an existing recipe in the system. Use get_recipes or find_recipes to discover available recipe IDs.',
					'minimum'     => 1,
				),
				'trigger_code' => array(
					'type'        => 'string',
					'description' => 'Trigger code from registry. Call get_component_schema first to understand required fields.',
					'minLength'   => 2,
				),
				'fields'       => array(
					'type'                 => 'object',
					'description'          => 'Trigger field values. For dropdown fields, pass BOTH value AND _readable suffix (e.g., "LDCOURSE": "656", "LDCOURSE_readable": "Sales 101"). Optional: "_automator_custom_item_name_" for custom label.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'recipe_id', 'trigger_code', 'fields' ),
		);
	}

	/**
	 * Execute tool with User_Context.
	 *
	 * @param User_Context $user_context The user context.
	 * @param array        $params       The input parameters.
	 * @return array
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {

		// Require authenticated executor for trigger modification
		$this->require_authenticated_executor( $user_context );

		// Extract and sanitize parameters FIRST before validation
		$processed_params = $this->extract_and_sanitize_parameters( $params );

		// Validate sanitized input parameters
		$validation_result = $this->validate_input_parameters( $processed_params );
		if ( is_array( $validation_result ) ) {
			return $validation_result; // Error response
		}

		// Validate business rules for the processed parameters
		$business_validation = $this->validate_business_rules( $processed_params );
		if ( is_array( $business_validation ) ) {
			return $business_validation; // Error response
		}

		// Get and validate trigger definition from registry
		$trigger_definition = $this->get_trigger_definition( $processed_params['trigger_code'] );

		// Validate fields against trigger schema
		$config_validation = $this->validate_trigger_configuration(
			$processed_params['trigger_code'],
			$processed_params['fields']
		);

		if ( is_wp_error( $config_validation ) ) {
			return Json_Rpc_Response::create_error_response( $config_validation->get_error_message() ); // Error response
		}

		// Create and validate trigger entity
		$trigger_entity = $this->create_trigger_entity( $processed_params, $trigger_definition );
		if ( is_array( $trigger_entity ) ) {
			return $trigger_entity; // Error response
		}

		// Add trigger to recipe through service layer
		return $this->add_trigger_to_recipe( $trigger_entity );
	}

	/**
	 * Validate input parameters against schema.
	 *
	 * @param array $params The input parameters.
	 * @return array|null Returns error response array on failure, null on success.
	 */
	private function validate_input_parameters( array $params ) {

		$schema = array(
			'recipe_id'    => array(
				'type'     => 'integer',
				'required' => true,
			),
			'trigger_code' => array(
				'type'     => 'string',
				'required' => true,
			),
		);

		if ( ! Security::validate_schema( $params, $schema ) ) {
			return Json_Rpc_Response::create_error_response(
				'Invalid parameters. Parameters recipe_id, trigger_code, and fields are required. Use get_component_schema to discover required fields.'
			);
		}

		return null;
	}

	/**
	 * Extract and sanitize parameters from input.
	 *
	 * @param array $params The input parameters.
	 * @return array Sanitized parameters array.
	 */
	private function extract_and_sanitize_parameters( array $params ): array {

		// Sanitize parameters with minimal processing
		$sanitized = Security::sanitize( $params, Security::PRESERVE_RAW );

		$recipe_id    = intval( $sanitized['recipe_id'] ?? 0 );
		$trigger_code = trim( $sanitized['trigger_code'] ?? '' );
		$fields       = $params['fields'] ?? array();

		// Handle fields payload - some MCP clients send it as a JSON string
		$fields = $this->parse_fields( $fields );

		// Auto-inject added_by_llm flag for MCP-created triggers.
		$fields['added_by_llm'] = true;

		return array(
			'recipe_id'    => $recipe_id,
			'trigger_code' => $trigger_code,
			'fields'       => $fields,
		);
	}

	/**
	 * Validate business rules for processed parameters.
	 *
	 * @param array $processed_params The processed parameters.
	 * @return array|null Returns error response array on failure, null on success.
	 */
	private function validate_business_rules( array $processed_params ) {

		if ( ! $processed_params['recipe_id'] ) {
			return Json_Rpc_Response::create_error_response(
				"Parameter 'recipe_id' is required. Ask the user to provide a valid recipe ID if you dont know it."
			);
		}

		if ( empty( $processed_params['trigger_code'] ) ) {
			return Json_Rpc_Response::create_error_response(
				"Parameter 'trigger_code' is required. Use the tool get_trigger to discover the trigger code before using this tool."
			);
		}

		if ( empty( $processed_params['fields'] ) ) {
			return Json_Rpc_Response::create_error_response(
				"Parameter 'fields' is required. Use get_component_schema to discover required fields. CRITICAL: Always include 'added_by_llm': true in fields."
			);
		}

		return null;
	}

	/**
	 * Get trigger definition from registry.
	 *
	 * @param string $trigger_code The trigger code to look up.
	 * @return array|mixed Returns error response array on failure, trigger definition on success.
	 */
	private function get_trigger_definition( string $trigger_code ) {

		$trigger_definition_result = $this->trigger_registry_service->get_trigger_definition( $trigger_code, false );

		if ( is_wp_error( $trigger_definition_result ) ) {
			return Json_Rpc_Response::create_error_response(
				'Trigger code not found: '
				. $trigger_code
				. '. Use find_trigger or list_triggers to discover available triggers.'
			);
		}

		$trigger_def = $trigger_definition_result ?? array();

		if ( empty( $trigger_def ) ) {
			return Json_Rpc_Response::create_error_response(
				'Trigger definition not found: '
				. $trigger_code
			);
		}

		return $trigger_def;
	}

	/**
	 * Validate trigger fields against schema.
	 *
	 * @param string $trigger_code The trigger code.
	 * @param array  $fields       The fields to validate.
	 * @return WP_Error|true Returns error response array on failure, true on success.
	 */
	private function validate_trigger_configuration( string $trigger_code, array $fields ) {

		if ( empty( $fields ) ) {
			return true; // Empty fields are handled by business rules validation
		}

		$validation_result = $this->trigger_registry_service->validate_trigger_configuration( $trigger_code, $fields );

		if ( is_wp_error( $validation_result ) ) {
			return new WP_Error( $validation_result->get_error_code(), $validation_result->get_error_message() );
		}

		if ( ! $validation_result['valid'] ) {
			return new WP_Error( $validation_result['errors'], 'Invalid fields: ' . implode( ', ', $validation_result['errors'] ) );
		}

		return true;
	}

	/**
	 * Create trigger entity from processed parameters and definition.
	 *
	 * @param array $processed_params  The processed parameters.
	 * @param array $trigger_definition The trigger definition from registry.
	 *
	 * @return Trigger|array Returns error response array on failure, Trigger entity on success.
	 */
	private function create_trigger_entity( array $processed_params, array $trigger_definition ) {

		// Processed parameters data.
		$trigger_code = $processed_params['trigger_code'] ?? '';
		$recipe_id    = $processed_params['recipe_id'] ?? '';

		// Trigger definition data. Default to empty values if not set so Value Objects validation kicks-in.
		$integration = $trigger_definition['integration'] ?? '';
		$sentence    = $trigger_definition['sentence'] ?? '';
		$type        = $trigger_definition['trigger_type'] ?? '';
		$hook        = $trigger_definition['trigger_hook'] ?? array();
		$tokens      = $trigger_definition['tokens'] ?? array();

		// Configuration data.
		$configuration = $processed_params['fields'] ?? array();

		// Process the readable fields because Automator saves the text label in the database instead of mapping it.
		// Skip field processing if trigger definition indicates no fields to process (for testing)
		$trigger_fields = array();

		// Process readable field mappings.
		if ( ! isset( $trigger_definition['skip_field_processing'] ) || ! $trigger_definition['skip_field_processing'] ) {
			$field = new Fields();
			$field->set_config(
				array(
					'object_type' => 'triggers',
					'code'        => $trigger_code,
				)
			);
			$trigger_fields = $field->get();
		}

		$mappings     = array();
		$field_labels = array();

		foreach ( $trigger_fields as $trigger_field ) {

			if ( is_array( $trigger_field ) ) {
				foreach ( $trigger_field as $field ) {

					$field_code = $field['option_code'] ?? '';

					// Collect field labels for sentence building (e.g., 'WPFFORMS' => 'Form')
					if ( ! empty( $field_code ) && ! empty( $field['label'] ) ) {
						$field_labels[ $field_code ] = $field['label'];
					}

					if ( isset( $field['input_type'] )
					&& 'select' === $field['input_type']
					&& isset( $field['options'] ) ) {

						foreach ( (array) $field['options'] as $key => $option ) {
							// Modern format: ['value' => x, 'text' => 'Label']
							if ( is_array( $option ) && isset( $option['value'] ) ) {
								$mappings[ $field_code ][ $option['value'] ] = $option['text'] ?? '';
							} elseif ( is_string( $option ) || is_numeric( $option ) ) {
								// Legacy format: [id => 'Label'] (e.g., WPForms uses this)
								$mappings[ $field_code ][ $key ] = (string) $option;
							}
						}
					}
				}
			}
		}

		if ( ! empty( $mappings ) ) {
			foreach ( $mappings as $field_code => $field_mappings ) {
				if ( isset( $configuration[ $field_code ] ) && isset( $field_mappings[ $configuration[ $field_code ] ] ) ) {
					$configuration[ $field_code . '_readable' ] = $field_mappings[ $configuration[ $field_code ] ];
				}
			}
		}

		// Generate sentence outputs with filled-in field values.
		// - brackets: "A user submits {{Form: Simple Contact Form}} {{1}} time(s)"
		// - html: HTML version with styling spans
		// Must use $sentence (with field codes like {{decorator:CODE}}).
		// The parser needs field codes to look up values in configuration.
		$sentence_human_readable_filled = $sentence;
		$sentence_human_readable_html   = '';

		if ( ! empty( $sentence ) ) {
			$sentence_result                = $this->build_sentence_outputs( $sentence, $configuration, $field_labels );
			$sentence_human_readable_filled = $sentence_result['brackets'];
			$sentence_human_readable_html   = $sentence_result['html'];
		}

		// JSON-encode array values (repeater fields) - Automator expects JSON strings, not PHP arrays.
		// WordPress would serialize PHP arrays in post_meta, but Automator's UI and existing triggers
		// expect JSON strings like '[{"parameter":"utm_r","value":"facebook"}]'.
		$configuration = $this->encode_array_fields( $configuration );

		// Build Trigger_Config from registry data - dumb container
		$trigger_config = ( new Trigger_Config() )
			->id( null ) // New trigger, no ID yet
			->code( $trigger_code )
			->recipe_id( $recipe_id )
			->integration( $integration )
			->sentence( $sentence )
			->sentence_human_readable( $sentence_human_readable_filled )
			->sentence_human_readable_html( $sentence_human_readable_html )
			->user_type( $type )
			->meta_code( $trigger_code )
			->hook( $hook )
			->tokens( $tokens )
			->configuration( $configuration ); // Include provided configuration (arrays JSON-encoded)

		// Create Trigger domain entity - this validates all business rules
		// If any data is invalid, the Trigger constructor will throw an exception
		try {
			return new Trigger( $trigger_config );
		} catch ( \InvalidArgumentException $e ) {
			// Domain validation failed - return the specific error
			return Json_Rpc_Response::create_error_response( 'Invalid trigger data: ' . $e->getMessage() );
		}
	}

	/**
	 * Add trigger entity to recipe through service layer.
	 *
	 * @param Trigger $trigger The trigger entity to add.
	 * @return array Success or error response array.
	 */
	private function add_trigger_to_recipe( Trigger $trigger ): array {

		$trigger_configuration = $trigger->get_trigger_configuration()->get_value();
		$trigger_code          = $trigger->get_trigger_code()->get_value();

		// Validate trigger configuration.
		$validation = $this->trigger_validator->validate_trigger_configuration(
			$trigger_code,
			$trigger_configuration
		);

		// Use Trigger Service - service layer handles all business validation
		$result = $this->trigger_service->add_trigger_entity( $trigger );

		// Transform service result to MCP response - service returns WP_Error or array
		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $result->get_error_message() );
		}

		// Get trigger data from result or trigger entity
		$trigger_payload = $result['trigger'] ?? $trigger->to_array();

		// Get IDs from payload or trigger entity
		$recipe_id = $trigger_payload['recipe_id'] ?? $trigger->get_recipe_id()->get_value() ?? 0;

		// Get trigger ID from trigger entity after save
		$trigger_id_value_object = $trigger->get_trigger_id();
		$trigger_id              = $trigger_id_value_object ? $trigger_id_value_object->get_value() : 0;

		// If still no ID, try from payload
		$trigger_id = $trigger_id > 0 ? $trigger_id : ( $trigger_payload['trigger_id'] ?? 0 );

		// Ensure trigger_id is in the payload
		$trigger_payload['trigger_id'] = $trigger_id;

		$payload = array(
			'recipe_id'  => $recipe_id,
			'trigger'    => $trigger_payload,
			'links'      => $this->build_recipe_links( $recipe_id ),
			'next_steps' => $this->build_recipe_next_steps( $recipe_id ),
		);

		if ( isset( $result['message'] ) && '' !== $result['message'] ) {
			$payload['notes'] = array( $result['message'] );
		}

		return Json_Rpc_Response::create_success_response(
			'Trigger added to recipe',
			$payload
		);
	}

	/**
	 * Parse fields parameter.
	 *
	 * @param array|string $fields Fields parameter from MCP client.
	 * @return array Parsed fields array.
	 */
	private function parse_fields( $fields ) {

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
	 * Encourage agents to open the recipe editor after mutations.
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

	/**
	 * JSON-encode array values in configuration.
	 *
	 * Automator stores repeater field values as JSON strings in post_meta.
	 * Without this encoding, WordPress would serialize PHP arrays (a:1:{...}),
	 * which breaks Automator's UI and trigger execution.
	 *
	 * @param array $config Configuration array with potential array values.
	 * @return array Configuration with array values encoded as JSON strings.
	 */
	private function encode_array_fields( array $config ): array {
		foreach ( $config as $key => $value ) {
			if ( is_array( $value ) ) {
				$config[ $key ] = wp_json_encode( $value );
			}
		}
		return $config;
	}

	/**
	 * Build sentence outputs using the Sentence_Human_Readable_Service.
	 *
	 * Converts raw configuration arrays into domain objects and generates
	 * both bracket-wrapped and HTML sentence formats.
	 *
	 * @param string $sentence_template The sentence template with {{decorator:CODE}} tokens.
	 * @param array  $configuration     Field values including _readable suffixes.
	 * @param array  $field_labels      Map of field codes to labels.
	 *
	 * @return array{brackets: string, html: string} Sentence outputs.
	 */
	private function build_sentence_outputs( string $sentence_template, array $configuration, array $field_labels ): array {

		$template = new Sentence_Template( $sentence_template );

		// Build field value collection from configuration.
		$field_value_collection = new Sentence_Field_Value_Text_Collection();

		// Iterate over the field labels and build the field value collection.
		foreach ( $field_labels as $code => $label ) {

			// Skip if no value exists for this code.
			if ( ! isset( $configuration[ $code ] ) ) {
				continue;
			}

			$raw_value = $configuration[ $code ];

			// In case the model sends an object but was converted to an array, we need to encode it to a JSON string.
			if ( ! is_string( $raw_value ) && is_array( $raw_value ) ) {
				$raw_value = wp_json_encode( $raw_value );
			}

			$text = $configuration[ $code . '_readable' ] ?? (string) $raw_value;

			// Determine is_filled: has value and it's not empty/placeholder.
			// -1 typically means "Any X" selection → not filled.
			$is_filled = ! empty( $text ) && '-1' !== (string) $raw_value && -1 !== $raw_value;

			// Ensure text is a string for the Sentence_Field_Value_Text constructor.
			if ( ! is_scalar( $text ) ) {
				$text = wp_json_encode( $text );
			}

			// Cast to string if not already.
			if ( ! is_string( $text ) ) {
				$text = (string) $text;
			}

			$field_value_collection->add(
				new Sentence_Field_Value_Text( $code, $raw_value, $text, $is_filled )
			);

		}

		// Build field label collection.
		$field_label_collection = new Sentence_Field_Label_Collection();
		foreach ( $field_labels as $code => $label ) {
			$field_label_collection->add(
				new Sentence_Field_Label( $code, $label )
			);
		}

		// Generate outputs using the service.
		$service  = new Sentence_Human_Readable_Service();
		$brackets = $service->build( $template, $field_value_collection, $field_label_collection );
		$html     = $service->build_html( $template, $field_value_collection, $field_label_collection );

		return array(
			'brackets' => $brackets,
			'html'     => $html,
		);
	}
}
