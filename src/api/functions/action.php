<?php
/**
 * Action Functions
 *
 * WordPress developer convenience functions for action operations.
 * Covers registry, recipe management, validation, and configuration.
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
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Services\Action\Services\Action_Registry_Service;

// =============================================================================
// ACTION REGISTRY
// =============================================================================

/**
 * Get all available actions.
 *
 * @param string $integration Filter by integration code. Default '' (all).
 * @param bool   $include_schema Include configuration schema. Default false.
 * @return array|WP_Error Array of actions on success, WP_Error on failure.
 */
function automator_get_available_actions( string $integration = '', bool $include_schema = false ) {
	$registry_service = automator_get_action_registry_service();
	return $registry_service->get_available_actions( $integration, $include_schema );
}

/**
 * Search for actions using semantic search.
 *
 * @param string $query      Search query.
 * @param string $integration Filter by integration code. Default '' (all).
 * @param int    $limit      Maximum results. Default 10, max 50.
 * @return array|WP_Error Array of matching actions on success, WP_Error on failure.
 */
function automator_find_actions( string $query, string $integration = '', int $limit = 10 ) {
	if ( empty( $query ) ) {
		return new WP_Error(
			'missing_query',
			esc_html_x( 'Search query is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	$limit = min( $limit, 50 );

	$registry_service = automator_get_action_registry_service();
	return $registry_service->find_actions( $query, $integration, $limit );
}

/**
 * Get action definition by code.
 *
 * @param string $action_code Action code.
 * @param bool   $include_schema Include configuration schema. Default true.
 * @return array|WP_Error Action definition on success, WP_Error on failure.
 */
function automator_get_action_definition( string $action_code, bool $include_schema = true ) {
	if ( empty( $action_code ) ) {
		return new WP_Error(
			'missing_action_code',
			esc_html_x( 'Action code is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	$registry_service = automator_get_action_registry_service();
	return $registry_service->get_action_definition( $action_code, $include_schema );
}

/**
 * Check if action exists.
 *
 * @param string $action_code Action code.
 * @return bool True if action exists.
 */
function automator_action_exists( string $action_code ) {
	$result = automator_get_action_definition( $action_code, false );
	return ! is_wp_error( $result );
}

/**
 * Get actions by integration.
 *
 * @param string $integration Integration code.
 * @return array|WP_Error Array of actions on success, WP_Error on failure.
 */
function automator_get_actions_by_integration( string $integration ) {
	return automator_get_available_actions( $integration, false );
}

// =============================================================================
// ACTION CRUD OPERATIONS
// =============================================================================

/**
 * Add action to recipe.
 *
 * @param int   $recipe_id   Recipe ID.
 * @param array $action_data {
 *     Action configuration data.
 *     @type string $action_code Action code (required).
 *     @type array  $config      Action configuration (optional).
 * }
 * @return array|WP_Error Action data on success, WP_Error on failure.
 */
function automator_add_action_to_recipe( int $recipe_id, array $action_data ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	if ( empty( $action_data['action_code'] ) ) {
		return new WP_Error(
			'missing_action_code',
			esc_html_x( 'Action code is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	if ( ! isset( $action_data['config'] ) ) {
		return new WP_Error(
			'missing_action_config',
			esc_html_x( 'Action configuration is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_action_crud_service();

	$action_code = $action_data['action_code'];
	$config      = $action_data['config'];

	return $crud_service->add_to_recipe( $recipe_id, $action_code, $config );
}

/**
 * Get actions for recipe.
 *
 * @param int $recipe_id Recipe ID.
 * @return array|WP_Error Array of actions on success, WP_Error on failure.
 */
function automator_get_recipe_actions( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_action_crud_service();
	return $crud_service->get_recipe_actions( $recipe_id );
}

/**
 * Get specific action by ID.
 *
 * @param int $action_id Action ID.
 * @return array|WP_Error Action data on success, WP_Error on failure.
 */
function automator_get_action_by_id( int $action_id ) {
	if ( empty( $action_id ) || $action_id <= 0 ) {
		return new WP_Error(
			'invalid_action_id',
			esc_html_x( 'Valid action ID is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_action_crud_service();
	return $crud_service->get_action( $action_id );
}

/**
 * Get action by code.
 *
 * @param string $action_code Action code.
 *
 * @return false|mixed
 */
function automator_get_action_by_code( string $action_code ) {
	return Automator()->get_action( $action_code );
}

/**
 * Update action configuration.
 *
 * @param int   $action_id Action ID.
 * @param array $config    Updated configuration.
 * @return array|WP_Error Updated action data on success, WP_Error on failure.
 */
function automator_update_action( int $action_id, array $config ) {
	if ( empty( $action_id ) || $action_id <= 0 ) {
		return new WP_Error(
			'invalid_action_id',
			esc_html_x( 'Valid action ID is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_action_crud_service();
	return $crud_service->update_action( $action_id, $config );
}

/**
 * Delete action from recipe.
 *
 * @param int  $action_id Action ID.
 * @param bool $confirmed Confirmation flag (safety measure).
 * @return array|WP_Error Success confirmation on success, WP_Error on failure.
 */
function automator_delete_action( int $action_id, bool $confirmed = false ) {
	if ( empty( $action_id ) || $action_id <= 0 ) {
		return new WP_Error(
			'invalid_action_id',
			esc_html_x( 'Valid action ID is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	if ( ! $confirmed ) {
		return new WP_Error(
			'confirmation_required',
			esc_html_x( 'Action deletion must be confirmed with $confirmed = true.', 'Action helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_action_crud_service();
	return $crud_service->delete_action( $action_id, true );
}

/**
 * Get action count for recipe.
 *
 * @param int $recipe_id Recipe ID.
 * @return array|WP_Error Action count data on success, WP_Error on failure.
 */
function automator_get_recipe_action_count( int $recipe_id ) {
	if ( empty( $recipe_id ) || $recipe_id <= 0 ) {
		return new WP_Error(
			'invalid_recipe_id',
			esc_html_x( 'Valid recipe ID is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	$crud_service = automator_get_action_crud_service();
	return $crud_service->get_recipe_action_count( $recipe_id );
}

// =============================================================================
// ACTION VALIDATION
// =============================================================================

/**
 * Validate action configuration.
 *
 * @param string $action_code Action code.
 * @param array  $config      Configuration to validate.
 * @return array|WP_Error Validation result on success, WP_Error on failure.
 */
function automator_validate_action_configuration( string $action_code, array $config ) {
	if ( empty( $action_code ) ) {
		return new WP_Error(
			'missing_action_code',
			esc_html_x( 'Action code is required.', 'Action helper error', 'uncanny-automator' )
		);
	}

	// Use the legacy Fields service for validation
	try {
		$fields = new \Uncanny_Automator\Services\Integrations\Fields();
		$fields->set_config(
			array(
				'object_type' => 'actions',
				'code'        => $action_code,
			)
		);
		$configuration_fields = $fields->get();

		$required_fields = automator_get_required_fields_from_schema( $configuration_fields );
		$missing_fields  = array_diff( $required_fields, array_keys( $config ) );

		if ( $missing_fields ) {
			return new WP_Error(
				'missing_required_fields',
				sprintf(
					/* translators: %s Comma-separated field list. */
					esc_html_x( 'Missing required configuration fields: %s', 'Action helper error', 'uncanny-automator' ),
					implode( ', ', $missing_fields )
				)
			);
		}

		return array( 'valid' => true );

	} catch ( \Exception $e ) {
		return new WP_Error(
			'validation_error',
			sprintf(
				/* translators: %s Error message. */
				esc_html_x( 'Action configuration validation failed: %s', 'Action helper error', 'uncanny-automator' ),
				$e->getMessage()
			)
		);
	}
}

/**
 * Get required fields from configuration schema.
 *
 * @param array $configuration_fields Configuration fields array.
 * @return array Required field names.
 */
function automator_get_required_fields_from_schema( array $configuration_fields ) {
	$required_fields = array();

	foreach ( $configuration_fields as $field_group ) {
		if ( ! is_array( $field_group ) ) {
			continue;
		}

		foreach ( $field_group as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
				continue;
			}

			if ( ! empty( $field['required'] ) ) {
				$required_fields[] = $field['option_code'];
			}
		}
	}

	return $required_fields;
}

// =============================================================================
// ACTION UTILITIES
// =============================================================================

/**
 * Create action instance from parameters and definition.
 *
 * @param int   $recipe_id      Recipe ID.
 * @param array $params         Request parameters.
 * @param array $action_definition Action definition from registry.
 * @return \Uncanny_Automator\Api\Components\Action\Action|WP_Error Action instance or error.
 */
function automator_create_action_instance( int $recipe_id, array $params, array $action_definition ) {
	try {
		$integration_code             = $action_definition['integration'] ?? '';
		$action_type                  = $action_definition['type'] ?? 'user';
		$sentence_human_readable      = $action_definition['sentence_human_readable'] ?? $action_definition['sentence'] ?? '';
		$sentence_human_readable_html = $action_definition['sentence_human_readable_html'] ?? $action_definition['sentence_html'] ?? '';

		$meta = array(
			'recipe_id'                    => $recipe_id,
			'sentence_human_readable'      => $sentence_human_readable,
			'sentence_human_readable_html' => $sentence_human_readable_html,
		);

		if ( ! empty( $params['config'] ) && is_array( $params['config'] ) ) {
			$meta = array_merge( $meta, $params['config'] );
		}

		$config = ( new \Uncanny_Automator\Api\Components\Action\Action_Config() )
			->id( null )
			->integration_code( $integration_code )
			->code( $params['action_code'] )
			->type( $action_type )
			->meta( $meta );

		return new \Uncanny_Automator\Api\Components\Action\Action( $config );

	} catch ( \Exception $e ) {
		return new WP_Error(
			'action_creation_error',
			sprintf(
			/* translators: %s Error message */
				esc_html_x( 'Failed to create action: %s', 'Action creation error', 'uncanny-automator' ),
				$e->getMessage()
			)
		);
	}
}

/**
 * Update action instance with new configuration.
 *
 * @param \Uncanny_Automator\Api\Components\Action\Action $existing_action Current action instance.
 * @param array                                           $new_config New configuration to merge.
 * @return \Uncanny_Automator\Api\Components\Action\Action Updated action instance.
 */
function automator_update_action_instance( $existing_action, array $new_config ) {
	$current_meta = $existing_action->get_action_meta()->to_array();
	$updated_meta = array_merge( $current_meta, $new_config );

	$config = ( new \Uncanny_Automator\Api\Components\Action\Action_Config() )
		->id( $existing_action->get_action_id()->get_value() )
		->integration_code( $existing_action->get_action_integration_code()->get_value() )
		->code( $existing_action->get_action_code()->get_value() )
		->type( $existing_action->get_action_type()->get_value() )
		->meta( $updated_meta );

	return new \Uncanny_Automator\Api\Components\Action\Action( $config );
}

/**
 * Check action integration availability.
 *
 * @param array $action_data {
 *     Action data to check availability for.
 *
 *     @type string $integration_id Integration code.
 *     @type string $code           Action code.
 *     @type string $required_tier  Required tier for this action.
 * }
 * @return array {
 *     Availability information.
 *
 *     @type bool   $available  Whether the feature is available for use.
 *     @type string $message    Human-readable availability message.
 *     @type array  $blockers   Array of blocking issues (empty if available).
 * }
 */
function automator_check_action_integration_availability( array $action_data ) {
	$registry_service = automator_get_action_registry_service();
	return $registry_service->check_action_integration_availability( $action_data );
}

/**
 * Format action response data.
 *
 * @param array $action_data Action data array.
 * @return array Formatted response data.
 */
function automator_format_action_response( array $action_data ) {
	return array(
		'action_id'                    => $action_data['action_id'],
		'action_code'                  => $action_data['action_code'],
		'integration'                  => $action_data['integration'],
		'user_type'                    => $action_data['user_type'],
		'recipe_id'                    => $action_data['recipe_id'],
		'sentence_human_readable'      => $action_data['sentence_human_readable'] ?? '',
		'sentence_human_readable_html' => $action_data['sentence_human_readable_html'] ?? '',
		'config'                       => $action_data['config'] ?? array(),
	);
}
