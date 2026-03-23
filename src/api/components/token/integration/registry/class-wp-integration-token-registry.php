<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Token\Integration\Registry;

use Uncanny_Automator\Api\Components\Token\Integration\Value_Objects\Integration_Token_Code;

/**
 * WordPress Integration Token Registry.
 *
 * WordPress implementation of integration token registry using existing system.
 * Retrieves tokens registered via automator_integration_items filter.
 *
 * This registry handles integration-level tokens (tokens provided by integrations).
 * Integration-level tokens never require a recipe ID.
 *
 * @since 7.0.0
 */
class WP_Integration_Token_Registry implements Integration_Token_Registry {

	/**
	 * Tokens
	 *
	 * @var array
	 */
	private array $tokens = array();

	/**
	 * Initialized
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Get all available integration tokens.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 *
	 * @return array Array of integration token definitions.
	 */
	public function get_available_tokens( array $options = array() ): array {
		$this->ensure_initialized();

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $this->tokens;
		}

		return $this->add_schema_to_tokens( $this->tokens );
	}

	/**
	 * Get specific integration token definition.
	 *
	 * @param Integration_Token_Code $code Integration token code.
	 * @param array                  $options Format options: ['include_schema' => bool].
	 *
	 * @return array|null Integration token definition or null if not found.
	 */
	public function get_token_definition( Integration_Token_Code $code, array $options = array() ): ?array {
		$this->ensure_initialized();

		$token_id = $code->get_value();
		$token    = $this->tokens[ $token_id ] ?? null;

		if ( null === $token ) {
			return null;
		}

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $token;
		}

		return $this->add_schema_to_token( $token );
	}

	/**
	 * Register an integration token type.
	 *
	 * @param string $code Integration token code.
	 * @param array  $definition Integration token definition.
	 *
	 * @return void
	 */
	public function register_token( string $code, array $definition ): void {
		// Store original properties.
		$this->tokens[ $code ] = array(
			'id'             => $code,
			'integration'    => $definition['integration'] ?? '',
			'name'           => $definition['name'] ?? '',
			'cacheable'      => $definition['cacheable'] ?? false,
			'requiresUser'   => $definition['requiresUser'] ?? $definition['requires_user_data'] ?? false,
			'type'           => $definition['type'] ?? $definition['data_type'] ?? 'text',
			'supportedItems' => $definition['supportedItems'] ?? $definition['supported_items'] ?? array(),
			'fields'         => $definition['fields'] ?? array(),
			'idTemplate'     => $definition['idTemplate'] ?? $definition['id_template'] ?? '',
			'nameTemplate'   => $definition['nameTemplate'] ?? $definition['name_template'] ?? '',
		);
	}

	/**
	 * Check if integration token is registered.
	 *
	 * @param Integration_Token_Code $code Integration token code.
	 *
	 * @return bool True if registered.
	 */
	public function is_registered( Integration_Token_Code $code ): bool {
		$this->ensure_initialized();

		$token_id = $code->get_value();

		return array_key_exists( $token_id, $this->tokens );
	}

	/**
	 * Get integration tokens by integration.
	 *
	 * @param string $integration Integration code.
	 *
	 * @return array Array of integration tokens for the integration.
	 */
	public function get_tokens_by_integration( string $integration ): array {
		$this->ensure_initialized();

		return array_filter(
			$this->tokens,
			function ( $token ) use ( $integration ) {
				return ( $token['integration'] ?? '' ) === $integration;
			}
		);
	}

	/**
	 * Ensure tokens are loaded from WordPress.
	 *
	 * @return void
	 */
	private function ensure_initialized(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->load_tokens_from_wordpress();
		$this->initialized = true;
	}

	/**
	 * Load integration tokens from existing WordPress system.
	 *
	 * Builds minimal structure and applies automator_integration_items filter directly.
	 * This avoids unnecessary database queries that Structure would perform for recipe-specific data.
	 * Integration-level tokens don't require recipe context.
	 *
	 * @return void
	 */
	private function load_tokens_from_wordpress(): void {
		// Build minimal structure for integrations
		$items = $this->build_items_structure();

		// Create minimal Structure-like object for filter compatibility
		// Token classes call $structure->get_recipe_id(), so we provide that method
		$structure_mock = $this->create_structure_mock();

		// Apply the automator_integration_items filter to get tokens
		$items = apply_filters( 'automator_integration_items', $items, $structure_mock );

		// Get active integrations to filter tokens
		$active_integrations = Automator()->get_integrations();

		// Only process tokens for active integrations
		foreach ( $items as $integration_code => $integration_data ) {
			// Skip inactive integrations.
			if ( ! isset( $active_integrations[ $integration_code ] ) ) {
				continue;
			}

			// Confirm if app and if it's connected.
			if ( $integration_data['is_app'] ?? false ) {
				// Skip if app is not connected.
				$is_app_connected = $integration_data['miscellaneous']['is_app_connected'] ?? null;
				if ( null === $is_app_connected ) {
					continue;
				}
			}

			$tokens = $integration_data['tokens'] ?? array();

			foreach ( $tokens as $token ) {

				// error_log( print_r( array( 'token' => $token, 'integration_code' => $integration_code ), true ) );

				$token_id = $token['id'] ?? '';

				// Skip if no token ID
				if ( empty( $token_id ) ) {
					continue;
				}

				// Store original properties.
				$this->tokens[ $token_id ] = array(
					'id'             => $token_id,
					'integration'    => $integration_code,
					'name'           => $token['name'] ?? '',
					'cacheable'      => $token['cacheable'] ?? false,
					'requiresUser'   => $token['requiresUser'] ?? false,
					'type'           => $token['type'] ?? 'text',
					'supportedItems' => $token['supportedItems'] ?? array(),
					'fields'         => $token['fields'] ?? array(),
					'idTemplate'     => $token['idTemplate'] ?? '',
					'nameTemplate'   => $token['nameTemplate'] ?? '',
				);
			}
		}

		/**
		 * Allow additional integration tokens to be registered.
		 *
		 * @param array $tokens Existing tokens array.
		 *
		 * @return array Additional tokens array.
		 * @example
		 * [
		 *  'TOKEN_ID' => array(
		 *      'code'               => 'TOKEN_ID',
		 *      'name'               => 'TOKEN_NAME',
		 *      'data_type'          => 'TOKEN_DATA_TYPE',
		 *      'requires_user_data' => true,
		 *  ),
		 * ]
		 */
		$additional_tokens = apply_filters( 'automator_api_register_integration_tokens', array() );
		foreach ( $additional_tokens as $code => $token ) {
			$this->register_token( $code, $token );
		}
	}

	/**
	 * Build items structure for integrations.
	 *
	 * Creates minimal structure needed for the automator_integration_items filter.
	 * Includes all integrations (so filters can work), but we only process tokens
	 * for active integrations later.
	 *
	 * @return array Items structure.
	 */
	private function build_items_structure(): array {
		$items = array();

		// Get all integrations (needed for filters to work properly)
		$all_integrations    = Automator()->get_all_integrations();
		$active_integrations = Automator()->get_integrations();

		// Build minimal structure for all integrations
		// Filters expect all integrations to be present
		foreach ( $all_integrations as $code => $props ) {
			// Confirm app settings.
			$has_settings       = ! empty( $active_integrations[ $code ]['settings_url'] ?? '' );
			$has_connected_prop = isset( $active_integrations[ $code ]['connected'] );
			$is_app             = $has_settings && $has_connected_prop;

			$misc = array();
			if ( $is_app ) {
				$misc['is_app_connected'] = $active_integrations[ $code ]['connected'] ?? null;
			}

			$items[ $code ] = array(
				'name'           => $props['name'] ?? '',
				'icon'           => $props['icon_svg'] ?? '',
				'is_available'   => isset( $active_integrations[ $code ] ),
				'is_app'         => $is_app,
				'is_third_party' => $props['is_third_party'] ?? false,
				'miscellaneous'  => $misc,
				'triggers'       => array(),
				'actions'        => array(),
				'conditions'     => array(),
				'loop_filters'   => array(),
			);
		}

		return $items;
	}

	/**
	 * Create minimal Structure-like object for filter compatibility.
	 *
	 * Token classes call $structure->get_recipe_id() on the Structure instance
	 * passed to the filter. This mock object provides that method without
	 * triggering unnecessary database queries.
	 *
	 * @return object Structure-like object with get_recipe_id() method.
	 */
	private function create_structure_mock(): object {
		return new class() {
			/**
			 * Get recipe ID.
			 *
			 * Returns 0 since integration-level tokens don't require recipe context.
			 *
			 * @return int
			 */
			public function get_recipe_id(): int {
				return 0;
			}
		};
	}

	/**
	 * Add schema information to tokens array.
	 *
	 * @param array $tokens Tokens array.
	 *
	 * @return array Tokens with schema.
	 */
	private function add_schema_to_tokens( array $tokens ): array {
		$tokens_with_schema = array();

		foreach ( $tokens as $id => $token ) {
			$tokens_with_schema[ $id ] = $this->add_schema_to_token( $token );
		}

		return $tokens_with_schema;
	}

	/**
	 * Add schema information to single token.
	 *
	 * @param array $token Token definition.
	 *
	 * @return array Token with schema.
	 */
	private function add_schema_to_token( array $token ): array {
		$fields            = $token['fields'] ?? array();
		$schema_properties = array();

		foreach ( $fields as $field_code => $field_config ) {
			$schema_properties[ $field_code ] = array(
				'type'        => $field_config['type'] ?? 'string',
				'description' => $field_config['description'] ?? $field_config['label'] ?? '',
			);

			// Add enum for select fields
			if ( ! empty( $field_config['options'] ) ) {
				$schema_properties[ $field_code ]['enum'] = array_keys( $field_config['options'] );
			}
		}

		return array(
			'name'        => $token['id'] ?? '',
			'description' => ! empty( $token['name'] ) ? $token['name'] : 'Integration Token: ' . ( $token['id'] ?? '' ),
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => $schema_properties,
			),
		);
	}
}
