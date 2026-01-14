<?php
/**
 * Trigger Functions
 *
 * WordPress developer convenience functions for trigger operations.
 * Covers registry, recipe management, validation, and schema creation.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Functions
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) && ! defined( 'PHPUNIT_COMPOSER_INSTALL' ) && ! defined( 'WP_TESTS_DIR' ) ) {
	exit;
}

// Import classes
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Services\Trigger\Utilities\Trigger_Schema_Converter;

// =============================================================================
// TRIGGER REGISTRY
// =============================================================================

/**
 * Get all available triggers.
 *
 * @param array $filters {
 *     Optional filters for trigger listing.
 *     @type string $integration Filter by integration code.
 *     @type string $type        Filter by trigger type ('user', 'anonymous').
 * }
 * @param bool  $include_schema Include configuration schema. Default false.
 * @return array|WP_Error Array of triggers on success, WP_Error on failure.
 */
function automator_get_available_triggers( array $filters = array(), bool $include_schema = false ) {
	$registry_service = automator_get_trigger_registry_service();
	return $registry_service->list_triggers( $filters, $include_schema );
}

/**
 * Search for triggers using semantic search.
 *
 * @param string $query      Search query.
 * @param array  $filters    Additional filters.
 * @param int    $limit      Maximum results. Default 10, max 50.
 * @return array|WP_Error Array of matching triggers on success, WP_Error on failure.
 */
function automator_find_triggers( string $query, array $filters = array(), int $limit = 10 ) {
	if ( empty( $query ) ) {
		return new WP_Error(
			'missing_query',
			esc_html_x( 'Search query is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$limit = min( $limit, 50 );

	$registry_service = automator_get_trigger_registry_service();
	return $registry_service->find_triggers( $query, $filters, $limit );
}

/**
 * Get trigger definition by code.
 *
 * @param string $trigger_code Trigger code.
 * @param bool   $include_schema Include configuration schema. Default true.
 * @return array|WP_Error Trigger definition on success, WP_Error on failure.
 */
function automator_get_trigger_definition( string $trigger_code, bool $include_schema = true ) {
	if ( empty( $trigger_code ) ) {
		return new WP_Error(
			'missing_trigger_code',
			esc_html_x( 'Trigger code is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$registry_service = automator_get_trigger_registry_service();
	return $registry_service->get_trigger_definition( $trigger_code, $include_schema );
}

/**
 * Get trigger by code.
 *
 * @param string $trigger_code Trigger code.
 * @return array|WP_Error Trigger on success, WP_Error on failure.
 */
function automator_get_trigger_by_code( string $trigger_code ) {

	if ( empty( $trigger_code ) ) {
		return new WP_Error(
			'missing_trigger_code',
			esc_html_x( 'Trigger code is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$registry_service = automator_get_trigger_registry_service();
	return $registry_service->get_trigger_by_code( $trigger_code );
}

/**
 * Check if trigger exists.
 *
 * @param string $trigger_code Trigger code.
 * @return bool True if trigger exists.
 */
function automator_trigger_exists( string $trigger_code ) {
	$result = automator_get_trigger_registry_service()->trigger_exists( $trigger_code );
	return ! is_wp_error( $result ) && ! empty( $result['exists'] );
}

/**
 * Get triggers by type.
 *
 * @param string $type          Trigger type ('user', 'anonymous').
 * @param bool   $include_schema Include configuration schema. Default false.
 * @return array|WP_Error Array of triggers on success, WP_Error on failure.
 */
function automator_get_triggers_by_type( string $type, bool $include_schema = false ) {
	if ( ! in_array( $type, array( 'user', 'anonymous' ), true ) ) {
		return new WP_Error(
			'invalid_trigger_type',
			esc_html_x( 'Trigger type must be "user" or "anonymous".', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$registry_service = automator_get_trigger_registry_service();
	return $registry_service->get_triggers_by_type( $type, $include_schema );
}

/**
 * Get user-compatible triggers.
 *
 * @param bool $include_schema Include configuration schema. Default false.
 * @return array|WP_Error Array of user triggers on success, WP_Error on failure.
 */
function automator_get_user_triggers( bool $include_schema = false ) {
	return automator_get_triggers_by_type( 'user', $include_schema );
}

/**
 * Get anonymous-compatible triggers.
 *
 * @param bool $include_schema Include configuration schema. Default false.
 * @return array|WP_Error Array of anonymous triggers on success, WP_Error on failure.
 */
function automator_get_anonymous_triggers( bool $include_schema = false ) {
	return automator_get_triggers_by_type( 'anonymous', $include_schema );
}

/**
 * Get triggers by integration.
 *
 * @param string $integration   Integration code.
 * @param bool   $include_schema Include configuration schema. Default false.
 * @return array|WP_Error Array of triggers on success, WP_Error on failure.
 */
function automator_get_triggers_by_integration( string $integration, bool $include_schema = false ) {
	if ( empty( $integration ) ) {
		return new WP_Error(
			'missing_integration',
			esc_html_x( 'Integration code is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$registry_service = automator_get_trigger_registry_service();
	return $registry_service->get_triggers_by_integration( $integration, $include_schema );
}

// =============================================================================
// TRIGGER CRUD OPERATIONS
// =============================================================================

/**
 * Add trigger to recipe.
 *
 * @param int   $recipe_id    Recipe ID.
 * @param array $trigger_data {
 *     Trigger configuration data.
 *     @type string $trigger_code Trigger code (required).
 *     @type array  $config       Trigger configuration (optional).
 * }
 * @return array|WP_Error Trigger data on success, WP_Error on failure.
 */
function automator_add_trigger_to_recipe( int $recipe_id, array $trigger_data ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	if ( empty( $trigger_data['trigger_code'] ) ) {
		return new WP_Error(
			'missing_trigger_code',
			esc_html_x( 'Trigger code is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	if ( ! isset( $trigger_data['config'] ) ) {
		return new WP_Error(
			'missing_trigger_config',
			esc_html_x( 'Trigger configuration is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_trigger_crud_service();

	$trigger_code = $trigger_data['trigger_code'];
	$config       = $trigger_data['config'];

	return $crud_service->add_to_recipe( $recipe_id, $trigger_code, $config );
}

/**
 * Get triggers for recipe.
 *
 * @param int $recipe_id Recipe ID.
 * @return array|WP_Error Array of triggers on success, WP_Error on failure.
 */
function automator_get_recipe_triggers( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_trigger_crud_service();
	return $crud_service->get_recipe_triggers( $recipe_id );
}

/**
 * Get specific trigger by ID.
 *
 * @param int $trigger_id Trigger ID.
 * @return array|WP_Error Trigger data on success, WP_Error on failure.
 */
function automator_get_trigger( int $trigger_id ) {
	if ( empty( $trigger_id ) || $trigger_id <= 0 ) {
		return new WP_Error(
			'invalid_trigger_id',
			esc_html_x( 'Valid trigger ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_trigger_crud_service();
	return $crud_service->get_trigger( $trigger_id );
}

/**
 * Update trigger configuration.
 *
 * @param int   $recipe_id  Recipe ID.
 * @param int   $trigger_id Trigger ID.
 * @param array $config     Updated configuration.
 * @return array|WP_Error Updated trigger data on success, WP_Error on failure.
 */
function automator_update_trigger( int $recipe_id, int $trigger_id, array $config ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	if ( empty( $trigger_id ) || $trigger_id <= 0 ) {
		return new WP_Error(
			'invalid_trigger_id',
			esc_html_x( 'Valid trigger ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_trigger_crud_service();
	return $crud_service->update_trigger( $trigger_id, $config );
}

/**
 * Remove trigger from recipe.
 *
 * @param int $recipe_id  Recipe ID.
 * @param int $trigger_id Trigger ID.
 * @return array|WP_Error Success confirmation on success, WP_Error on failure.
 */
function automator_remove_trigger_from_recipe( int $recipe_id, int $trigger_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	if ( empty( $trigger_id ) || $trigger_id <= 0 ) {
		return new WP_Error(
			'invalid_trigger_id',
			esc_html_x( 'Valid trigger ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_trigger_crud_service();
	return $crud_service->remove_from_recipe( $recipe_id, $trigger_id );
}

/**
 * Set trigger logic for recipe.
 *
 * @param int    $recipe_id Recipe ID.
 * @param string $logic     Trigger logic ('all', 'any').
 * @return array|WP_Error Success confirmation on success, WP_Error on failure.
 */
function automator_set_recipe_trigger_logic( int $recipe_id, string $logic ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	if ( ! in_array( $logic, array( 'all', 'any', 'AND', 'OR' ), true ) ) {
		return new WP_Error(
			'invalid_logic',
			esc_html_x( 'Trigger logic must be "all", "any", "AND", or "OR".', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_trigger_crud_service();
	return $crud_service->set_trigger_logic( $recipe_id, $logic );
}

/**
 * Get trigger logic for recipe.
 *
 * @param int $recipe_id Recipe ID.
 * @return array|WP_Error Trigger logic data on success, WP_Error on failure.
 */
function automator_get_recipe_trigger_logic( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_trigger_crud_service();
	return $crud_service->get_trigger_logic( $recipe_id );
}

/**
 * Get trigger count for recipe.
 *
 * @param int $recipe_id Recipe ID.
 * @return array|WP_Error Trigger count data on success, WP_Error on failure.
 */
function automator_get_recipe_trigger_count( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_trigger_crud_service();
	return $crud_service->recipe_has_triggers( $recipe_id );
}

// =============================================================================
// TRIGGER VALIDATION
// =============================================================================

/**
 * Validate trigger configuration.
 *
 * @param string $trigger_code Trigger code.
 * @param array  $config       Configuration to validate.
 * @return array|WP_Error Validation result on success, WP_Error on failure.
 */
function automator_validate_trigger_configuration( string $trigger_code, array $config ) {
	if ( empty( $trigger_code ) ) {
		return new WP_Error(
			'missing_trigger_code',
			esc_html_x( 'Trigger code is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$registry_service = automator_get_trigger_registry_service();
	return $registry_service->validate_trigger_configuration( $trigger_code, $config );
}

/**
 * Check trigger integration availability.
 *
 * @param array $trigger_data {
 *     Trigger data to check availability for.
 *
 *     @type string $integration_id Integration code.
 *     @type string $code           Trigger code.
 *     @type string $required_tier  Required tier for this trigger.
 * }
 * @return array {
 *     Availability information.
 *
 *     @type bool   $available  Whether the feature is available for use.
 *     @type string $message    Human-readable availability message.
 *     @type array  $blockers   Array of blocking issues (empty if available).
 * }
 */
function automator_check_trigger_integration_availability( array $trigger_data ) {
	$registry_service = automator_get_trigger_registry_service();
	return $registry_service->check_trigger_integration_availability( $trigger_data );
}

// =============================================================================
// TRIGGER SCHEMA OPERATIONS (MAGICAL!)
// =============================================================================

/**
 * Create trigger from MCP schema.
 *
 * MAGICAL! Converts MCP tool schema into a complete trigger definition.
 *
 * @param array $mcp_schema MCP tool schema.
 * @return array|WP_Error Created trigger data on success, WP_Error on failure.
 */
function automator_create_trigger_from_schema( array $mcp_schema ) {
	if ( empty( $mcp_schema ) ) {
		return new WP_Error(
			'missing_schema',
			esc_html_x( 'MCP schema is required.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	try {
		// Validate MCP schema format
		$validation_result = automator_validate_mcp_schema( $mcp_schema );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		// Convert MCP schema to clean trigger data
		$clean_trigger_data = automator_convert_mcp_schema_to_trigger( $mcp_schema );
		if ( is_wp_error( $clean_trigger_data ) ) {
			return $clean_trigger_data;
		}

		// Register the new trigger
		$trigger_registry = automator_get_trigger_registry();

		$trigger_code = $clean_trigger_data['trigger_code'];
		$trigger_registry->register_trigger( $trigger_code, $clean_trigger_data );

		return array(
			'success'      => true,
			'trigger_code' => $trigger_code,
			'trigger_data' => $clean_trigger_data,
			'message'      => sprintf(
				/* translators: %s Trigger code. */
				esc_html_x( "Trigger '%s' created successfully from MCP schema.", 'Trigger helper success message', 'uncanny-automator' ),
				$trigger_code
			),
		);

	} catch ( \Exception $e ) {
		return new WP_Error(
			'schema_creation_error',
			sprintf(
				/* translators: %s Error message. */
				esc_html_x( 'Failed to create trigger schema: %s', 'Trigger helper error', 'uncanny-automator' ),
				$e->getMessage()
			)
		);
	}
}

/**
 * Validate MCP schema format.
 *
 * @param array $mcp_schema MCP schema to validate.
 * @return bool|WP_Error True if valid, WP_Error if invalid.
 */
function automator_validate_mcp_schema( array $mcp_schema ) {
	$required_fields = array( 'name', 'description', 'inputSchema' );

	foreach ( $required_fields as $field ) {
		if ( ! isset( $mcp_schema[ $field ] ) ) {
			return new WP_Error(
				'invalid_mcp_schema',
				sprintf(
					/* translators: %s Field name. */
					esc_html_x( 'Missing required field: %s', 'Trigger helper error', 'uncanny-automator' ),
					$field
				)
			);
		}
	}

	if ( empty( $mcp_schema['name'] ) || ! is_string( $mcp_schema['name'] ) ) {
		return new WP_Error(
			'invalid_trigger_name',
			esc_html_x( 'Trigger name must be a non-empty string.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	$input_schema = $mcp_schema['inputSchema'];
	if ( ! isset( $input_schema['type'] ) || 'object' !== $input_schema['type'] ) {
		return new WP_Error(
			'invalid_input_schema',
			esc_html_x( 'inputSchema must be an object type.', 'Trigger helper error', 'uncanny-automator' )
		);
	}

	return true;
}

/**
 * Convert MCP schema to trigger data.
 *
 * @param array $mcp_schema MCP schema.
 * @return array|WP_Error Trigger data on success, WP_Error on failure.
 */
function automator_convert_mcp_schema_to_trigger( array $mcp_schema ) {
	try {
		$trigger_code = strtoupper( $mcp_schema['name'] );
		$description  = $mcp_schema['description'];
		$input_schema = $mcp_schema['inputSchema'];

		$fields = array();
		if ( isset( $input_schema['properties'] ) ) {
			foreach ( $input_schema['properties'] as $field_name => $property ) {
				$fields[ strtoupper( $field_name ) ] = array(
					'type'        => automator_map_json_schema_type_to_field_type( $property['type'] ?? 'string' ),
					'label'       => $property['description'] ?? $field_name,
					'required'    => in_array( $field_name, $input_schema['required'] ?? array(), true ),
					'description' => $property['description'] ?? '',
					'default'     => $property['default'] ?? null,
				);

				if ( isset( $property['enum'] ) && is_array( $property['enum'] ) ) {
					$fields[ strtoupper( $field_name ) ]['type']    = 'select';
					$fields[ strtoupper( $field_name ) ]['options'] = array_combine( $property['enum'], $property['enum'] );
				}
			}
		}

		return array(
			'trigger_code'      => $trigger_code,
			'trigger_type'      => 'user',
			'integration'       => 'CUSTOM',
			'sentence'          => $description,
			'readable_sentence' => $description,
			'hook'              => array(
				'name'       => 'init',
				'priority'   => 10,
				'args_count' => 0,
			),
			'fields'            => $fields,
			'tokens'            => array(),
			'is_pro'            => false,
			'is_elite'          => false,
			'_source'           => 'mcp_schema',
		);

	} catch ( \Exception $e ) {
		return new WP_Error(
			'schema_conversion_error',
			sprintf(
				/* translators: %s Error message. */
				esc_html_x( 'Failed to convert MCP schema: %s', 'Trigger helper error', 'uncanny-automator' ),
				$e->getMessage()
			)
		);
	}
}

/**
 * Map JSON Schema type to field type.
 *
 * @param string $json_type JSON Schema type.
 * @return string Field type.
 */
function automator_map_json_schema_type_to_field_type( string $json_type ) {
	$type_map = array(
		'string'  => 'text',
		'number'  => 'number',
		'integer' => 'number',
		'boolean' => 'boolean',
		'array'   => 'select',
	);

	return $type_map[ $json_type ] ?? 'text';
}

/**
 * Create multiple triggers from MCP schemas.
 *
 * @param array $mcp_schemas Array of MCP schemas.
 * @return array Results for each schema.
 */
function automator_create_triggers_from_schemas( array $mcp_schemas ) {
	$results = array();

	foreach ( $mcp_schemas as $index => $schema ) {
		$results[ $index ] = automator_create_trigger_from_schema( $schema );
	}

	return $results;
}
