<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Registry;

use Uncanny_Automator\Api\Components\Trigger\Trigger;
use Uncanny_Automator\Api\Components\Trigger\Trigger_Config;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Code;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_User_Type;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Integration;

/**
 * WordPress Trigger Registry.
 *
 * WordPress implementation of trigger registry using proper Value Objects.
 * Converts legacy trigger definitions to domain objects for type safety.
 *
 * @since 7.0.0
 */
class WP_Trigger_Registry implements Trigger_Registry {

	/**
	 * Registered trigger definitions as domain objects.
	 *
	 * @var array<string, Trigger> Indexed by trigger code.
	 */
	private array $triggers = array();

	/**
	 * Legacy trigger definitions (raw arrays from WordPress).
	 *
	 * @var array<string, array> Indexed by trigger code.
	 */
	private array $legacy_triggers = array();

	/**
	 * Registry initialization state.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Get all available trigger types.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of trigger definitions.
	 */
	public function get_available_triggers( array $options = array() ): array {
		$this->ensure_initialized();

		$include_schema = $options['include_schema'] ?? false;

		// Convert domain objects back to arrays for API consumers
		$trigger_arrays = array();
		foreach ( $this->triggers as $code => $trigger ) {
			$trigger_arrays[ $code ] = $trigger->to_array();
		}

		if ( ! $include_schema ) {
			return $trigger_arrays;
		}

		// Add MCP schema to each trigger definition
		return $this->add_schema_to_triggers( $trigger_arrays );
	}

	/**
	 * Get specific trigger definition.
	 *
	 * @param Trigger_Code $code Trigger code.
	 * @param array        $options Format options: ['include_schema' => bool].
	 * @return array|null Trigger definition or null if not found.
	 */
	public function get_trigger_definition( Trigger_Code $code, array $options = array() ): ?array {
		$this->ensure_initialized();

		$trigger_code = $code->get_value();
		$trigger      = $this->triggers[ $trigger_code ] ?? null;

		if ( null === $trigger ) {
			return null;
		}

		$trigger_array  = $trigger->to_array();
		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $trigger_array;
		}

		// Add MCP schema using legacy data for field definitions
		$legacy_trigger = $this->legacy_triggers[ $trigger_code ] ?? array();
		return $this->build_mcp_tool_schema( $trigger_array, $legacy_trigger );
	}

	/**
	 * Get triggers by type.
	 *
	 * @param Trigger_User_Type $type Trigger user type.
	 * @param array             $options Format options: ['include_schema' => bool].
	 * @return array Array of triggers for the specified type.
	 */
	public function get_triggers_by_type( Trigger_User_Type $type, array $options = array() ): array {
		$this->ensure_initialized();

		// Filter domain objects using proper type checking
		$filtered_triggers = array();
		foreach ( $this->triggers as $code => $trigger ) {
			if ( $trigger->get_trigger_type()->is_compatible_with( $type ) ) {
				$filtered_triggers[ $code ] = $trigger->to_array();
			}
		}

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $filtered_triggers;
		}

		return $this->add_schema_to_triggers( $filtered_triggers );
	}

	/**
	 * Register a trigger type.
	 *
	 * Converts legacy trigger definitions into type-safe domain objects.
	 * Invalid triggers are logged but don't break the registration process.
	 *
	 * @param string $code Trigger code/identifier.
	 * @param array  $definition Normalized trigger definition array.
	 */
	public function register_trigger( string $code, array $definition ): void {
		// Store legacy definition for MCP schema generation
		$this->legacy_triggers[ $code ] = $definition;

		// Convert to domain object for type safety and business rule enforcement
		try {
			$config                  = $this->create_trigger_config_from_legacy( $definition );
			$this->triggers[ $code ] = new Trigger( $config );
		} catch ( \Exception $e ) {
			// Fail gracefully - continue processing other triggers, but log for debugging.
			automator_log( sprintf( 'Failed to register trigger %s: %s', $code, $e->getMessage() ), 'Trigger Registry Error' );
			return;
		}
	}

	/**
	 * Check if trigger is registered.
	 *
	 * @param Trigger_Code $code Trigger code.
	 * @return bool True if registered.
	 */
	public function is_registered( Trigger_Code $code ): bool {
		$this->ensure_initialized();
		return array_key_exists( $code->get_value(), $this->triggers );
	}

	/**
	 * Get trigger domain object.
	 *
	 * @param Trigger_Code $code Trigger code.
	 * @return Trigger|null Domain object or null if not found.
	 */
	public function get_trigger( Trigger_Code $code ): ?Trigger {
		$this->ensure_initialized();
		return $this->triggers[ $code->get_value() ] ?? null;
	}

	/**
	 * Get triggers by integration.
	 *
	 * @param string $integration Integration name (case-insensitive).
	 * @return array Array of triggers for the integration.
	 */
	public function get_triggers_by_integration( string $integration ): array {
		$this->ensure_initialized();

		$integration_vo    = new Trigger_Integration( $integration );
		$integration_upper = strtoupper( $integration_vo->get_value() );
		$filtered_triggers = array();

		foreach ( $this->triggers as $code => $trigger ) {
			// Case-insensitive comparison for robustness
			if ( strtoupper( $trigger->get_integration()->get_value() ) === $integration_upper ) {
				$filtered_triggers[ $code ] = $trigger->to_array();
			}
		}

		return $filtered_triggers;
	}

	/**
	 * Initialize triggers from WordPress.
	 */
	private function ensure_initialized(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->load_triggers_from_wordpress();
		$this->initialized = true;
	}

	/**
	 * Load triggers from WordPress filter system.
	 *
	 * Primary entry point for loading trigger definitions from various sources:
	 * 1. Legacy Automator Functions registry
	 * 2. WordPress filter system for extensibility
	 *
	 * @uses Automator() - Legacy Automator Functions class instance.
	 * @uses apply_filters() - WordPress filter system.
	 */
	private function load_triggers_from_wordpress(): void {
		// Load triggers from existing Automator registry (legacy compatibility)
		$this->load_from_automator_functions();

		// Allow other plugins/themes to register additional triggers via filters
		$this->triggers = apply_filters( 'automator_core_api_triggers', $this->triggers );
	}

	/**
	 * Load triggers from existing Automator Functions class.
	 *
	 * Bridges the legacy Automator Functions registry with the new Core API registry.
	 * Converts legacy trigger definitions to modern Value Object based format.
	 *
	 * @uses Automator()->get_triggers() - Legacy method to get all registered triggers.
	 */
	private function load_from_automator_functions(): void {

		// Get all triggers from legacy Automator registry
		$loaded_triggers = Automator()->get_triggers();

		// Early return if no triggers found
		if ( empty( $loaded_triggers ) || ! is_array( $loaded_triggers ) ) {
			return;
		}

		// Convert and register each legacy trigger
		foreach ( $loaded_triggers as $trigger_code => $trigger_data ) {
			// Skip invalid trigger entries
			if ( ! is_array( $trigger_data ) ) {
				continue;
			}

			// Normalize legacy format and register as domain object
			$normalized = $this->convert_legacy_trigger( $trigger_data );
			$this->register_trigger( $trigger_code, $normalized );
		}
	}

	/**
	 * Convert legacy trigger format to normalized array.
	 *
	 * @param array $legacy_trigger Legacy trigger data.
	 * @return array Normalized trigger definition.
	 */
	private function convert_legacy_trigger( array $legacy_trigger ): array {
		// Extract fields from trigger options callback
		$fields = $this->extract_trigger_fields( $legacy_trigger );

		// Normalize hook name - some triggers have multiple hooks
		$hook_name = $legacy_trigger['action'] ?? '';

		// If action is an array (multiple hooks), use the first one as primary
		// and store the rest as additional hooks for future support
		if ( is_array( $hook_name ) ) {
			$primary_hook     = $hook_name[0] ?? '';
			$additional_hooks = array_slice( $hook_name, 1 );
		} else {
			$primary_hook     = $hook_name;
			$additional_hooks = array();
		}

		$hook = array(
			'name'       => $primary_hook,
			'priority'   => $legacy_trigger['priority'] ?? 10,
			'args_count' => $legacy_trigger['accepted_args'] ?? 1,
		);

		// Store additional hooks if present (for future multi-hook support)
		if ( ! empty( $additional_hooks ) ) {
			$hook['additional_hooks'] = $additional_hooks;
		}

		return array(
			'trigger_code'      => $legacy_trigger['code'] ?? '',
			'trigger_meta_code' => $legacy_trigger['meta_code'] ?? '',
			'trigger_type'      => $legacy_trigger['type'] ?? 'user',
			'integration'       => $legacy_trigger['integration'] ?? '',
			'sentence'          => $legacy_trigger['sentence'] ?? '',
			'readable_sentence' => $legacy_trigger['readable_sentence'] ?? $legacy_trigger['select_option_name'] ?? $legacy_trigger['sentence'] ?? '',
			'hook'              => $hook,
			'fields'            => $fields,
			'tokens'            => $legacy_trigger['tokens'] ?? array(),
			'is_pro'            => $legacy_trigger['is_pro'] ?? false,
			'is_elite'          => $legacy_trigger['is_elite'] ?? false,
			'is_deprecated'     => ! empty( $legacy_trigger['is_deprecated'] ),
			'manifest'          => $legacy_trigger['manifest'] ?? array(),
		);
	}

	/**
	 * Create Trigger_Config from legacy trigger definition.
	 *
	 * @param array $definition Normalized trigger definition.
	 * @return Trigger_Config
	 */
	private function create_trigger_config_from_legacy( array $definition ): Trigger_Config {

		return ( new Trigger_Config() )
			->id( null ) // Trigger registry is not bound to any persistent storage.
			->recipe_id( null ) // Trigger registry is not bound to any recipe storage.
			->code( $definition['trigger_code'] )
			->meta_code( $definition['trigger_meta_code'] )
			->user_type( $definition['trigger_type'] )
			->hook( $definition['hook'] )
			->tokens( $definition['tokens'] )
			->configuration( array() ) // Registry triggers have no configuration values - those are provided when adding to a recipe.
			->integration( $definition['integration'] )
			->sentence( $definition['sentence'] )
			->sentence_human_readable( $definition['readable_sentence'] )
			->is_deprecated( $definition['is_deprecated'] ?? false )
			->manifest( $definition['manifest'] ?? array() );
	}

	/**
	 * Extract fields from legacy trigger definition.
	 *
	 * Uses early return pattern to simplify conditional logic.
	 *
	 * @param array $legacy_trigger Legacy trigger data.
	 * @return array Extracted field definitions.
	 */
	private function extract_trigger_fields( array $legacy_trigger ): array {
		// Try to get fields from options callback first
		if ( isset( $legacy_trigger['options_callback'] ) && is_callable( $legacy_trigger['options_callback'] ) ) {
			try {
				$options_data = call_user_func( $legacy_trigger['options_callback'] );
				$fields       = $this->parse_options_to_fields( $options_data );

				if ( ! empty( $fields ) ) {
					return $fields;
				}
			} catch ( \Exception $e ) {
				// Continue to fallback, but log for debugging.
				automator_log( sprintf( 'Failed to extract fields from callback: %s', $e->getMessage() ), 'Trigger Registry Error' );
			}
		}

		// Fallback: try to get fields from existing trigger instance
		if ( isset( $legacy_trigger['code'] ) ) {
			return $this->get_fields_from_trigger_instance( $legacy_trigger['code'] );
		}

		// No fields found
		return array();
	}

	/**
	 * Parse options data to field definitions.
	 *
	 * Uses early return pattern for cleaner flow control.
	 *
	 * @param array $options_data Options data from load_options callback.
	 * @return array Field definitions.
	 */
	private function parse_options_to_fields( array $options_data ): array {
		// Check if options array exists and is valid
		if ( ! isset( $options_data['options'] ) || ! is_array( $options_data['options'] ) ) {
			return array();
		}

		$fields = array();

		foreach ( $options_data['options'] as $field_data ) {
			// Skip fields without proper option_code
			if ( ! isset( $field_data['option_code'] ) ) {
				continue;
			}

			$fields[ $field_data['option_code'] ] = array(
				'type'        => $field_data['type'] ?? 'select',
				'label'       => $field_data['label'] ?? '',
				'required'    => $field_data['required'] ?? false,
				'placeholder' => $field_data['placeholder'] ?? '',
				'description' => $field_data['description'] ?? '',
				'options'     => $field_data['options'] ?? array(),
				'default'     => $field_data['default_value'] ?? null,
			);
		}

		return $fields;
	}

	/**
	 * Get fields from existing trigger instance.
	 *
	 * Uses early return pattern to reduce nesting and improve readability.
	 *
	 * @todo Remove cross dependency with Automator Functions class.
	 * @uses Automator()->get_trigger() - Legacy method to get single trigger definition.
	 *
	 * @param string $trigger_code Trigger code.
	 * @return array Field definitions.
	 */
	private function get_fields_from_trigger_instance( string $trigger_code ): array {
		$trigger_instance = Automator()->get_trigger( $trigger_code );

		// Ensure we have valid trigger instance array
		if ( ! is_array( $trigger_instance ) ) {
			return array();
		}

		// Check for options callback
		if ( ! isset( $trigger_instance['options_callback'] ) ) {
			return array();
		}

		$callback = $trigger_instance['options_callback'];

		// Verify callback is callable
		if ( ! is_callable( $callback ) ) {
			return array();
		}

		return $this->extract_fields_from_callback( $callback );
	}

	/**
	 * Extract fields from options callback.
	 *
	 * @param callable $callback Options callback.
	 * @return array Field definitions.
	 */
	private function extract_fields_from_callback( callable $callback ): array {
		try {
			$options_data = call_user_func( $callback );
			return $this->parse_options_to_fields( $options_data );
		} catch ( \Exception $e ) {
			automator_log( sprintf( 'Failed to extract fields from callback: %s', $e->getMessage() ), 'Trigger Registry Error' );
			return array();
		}
	}

	/**
	 * Add schema to multiple triggers.
	 *
	 * Transforms trigger arrays into MCP-compatible tool schemas.
	 *
	 * @param array $triggers Array of trigger definition arrays.
	 * @return array Triggers with MCP schema added.
	 */
	private function add_schema_to_triggers( array $triggers ): array {
		// Early return for empty triggers array
		if ( empty( $triggers ) ) {
			return array();
		}

		$triggers_with_schema = array();

		foreach ( $triggers as $trigger_code => $trigger_array ) {
			$triggers_with_schema[ $trigger_code ] = $this->add_schema_to_trigger( $trigger_array );
		}

		return $triggers_with_schema;
	}

	/**
	 * Add schema to single trigger.
	 *
	 * @param array $trigger_array Trigger definition array.
	 * @return array MCP-compatible tool schema only.
	 */
	private function add_schema_to_trigger( array $trigger_array ): array {
		$trigger_code   = $trigger_array['trigger_code'] ?? '';
		$legacy_trigger = $this->legacy_triggers[ $trigger_code ] ?? array();
		return $this->build_mcp_tool_schema( $trigger_array, $legacy_trigger );
	}

	/**
	 * Build MCP-compatible tool schema.
	 *
	 * Transforms trigger definitions into Model Context Protocol (MCP) tool schemas
	 * that can be consumed by AI systems for integration automation.
	 *
	 * @param array $trigger_array Trigger array from domain object.
	 * @param array $legacy_trigger Legacy trigger data with field definitions.
	 * @return array MCP tool schema.
	 */
	private function build_mcp_tool_schema( array $trigger_array, array $legacy_trigger = array() ): array {
		// Extract fields from legacy trigger for schema generation
		$fields = $this->extract_trigger_fields( $legacy_trigger );

		// Convert fields to MCP properties format
		$schema_data = $this->convert_fields_to_mcp_properties( $fields );

		// Build base MCP schema structure
		$mcp_schema = array(
			'name'        => $trigger_array['trigger_code'] ?? '',
			'description' => $trigger_array['sentence'] ?? $trigger_array['sentence_human_readable'] ?? '',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => $schema_data['properties'],
			),
		);

		// Add required fields if any exist
		if ( ! empty( $schema_data['required'] ) ) {
			$mcp_schema['inputSchema']['required'] = $schema_data['required'];
		}

		return $mcp_schema;
	}

	/**
	 * Convert field definitions to MCP properties.
	 *
	 * Transforms Automator field definitions into JSON Schema compatible
	 * properties for Model Context Protocol integration.
	 *
	 * @param array $fields Field definitions from trigger options.
	 * @return array Array with 'properties' and 'required' keys.
	 */
	private function convert_fields_to_mcp_properties( array $fields ): array {
		// Early return for empty fields
		if ( empty( $fields ) ) {
			return array(
				'properties' => array(),
				'required'   => array(),
			);
		}

		$properties = array();
		$required   = array();

		foreach ( $fields as $field_code => $field_def ) {
			// Skip invalid field definitions
			if ( ! is_array( $field_def ) ) {
				continue;
			}

			$property                  = $this->build_mcp_property( $field_code, $field_def );
			$properties[ $field_code ] = $property;

			// Add to required list if marked as required
			if ( $field_def['required'] ?? false ) {
				$required[] = $field_code;
			}
		}

		return array(
			'properties' => $properties,
			'required'   => $required,
		);
	}

	/**
	 * Build single MCP property from field definition.
	 *
	 * Converts Automator field definition to JSON Schema property format
	 * for Model Context Protocol compatibility.
	 *
	 * @param string $field_code Field code/identifier.
	 * @param array  $field_def Field definition from Automator.
	 * @return array JSON Schema property definition.
	 */
	private function build_mcp_property( string $field_code, array $field_def ): array {
		$property = array(
			'type'        => $this->map_field_type_to_json_schema( $field_def['type'] ?? 'string' ),
			'description' => $field_def['label'] ?? $field_code,
		);

		// Add enum values for select/dropdown fields with static options
		if ( 'select' === ( $field_def['type'] ?? '' ) && ! empty( $field_def['options'] ) && is_array( $field_def['options'] ) ) {
			$property['enum'] = array_keys( $field_def['options'] );
		}

		// Add default value if explicitly set
		if ( isset( $field_def['default'] ) && null !== $field_def['default'] ) {
			$property['default'] = $field_def['default'];
		}

		return $property;
	}

	/**
	 * Map Automator field type to JSON Schema type.
	 *
	 * Provides mapping between Automator's field types and standard JSON Schema types
	 * for MCP (Model Context Protocol) compatibility.
	 *
	 * @param string $field_type Automator field type (select, text, textarea, etc.).
	 * @return string JSON Schema type (string, number, boolean, etc.).
	 */
	private function map_field_type_to_json_schema( string $field_type ): string {
		$type_map = array(
			'select'   => 'string',   // Dropdown/select fields
			'text'     => 'string',   // Single line text input
			'textarea' => 'string',   // Multi-line text input
			'number'   => 'number',   // Numeric input
			'integer'  => 'integer',  // Integer input
			'boolean'  => 'boolean',  // Checkbox/toggle
			'email'    => 'string',   // Email format validation
			'url'      => 'string',   // URL format validation
			'date'     => 'string',   // Date picker
			'time'     => 'string',   // Time picker
			'color'    => 'string',   // Color picker
			'file'     => 'string',   // File upload
		);

		// Default to string for unknown types
		return $type_map[ $field_type ] ?? 'string';
	}
}
