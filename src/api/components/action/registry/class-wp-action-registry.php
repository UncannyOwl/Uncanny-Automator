<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Registry;

use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Code;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_User_Type;

/**
 * WordPress Action Registry.
 *
 * WordPress implementation of action registry using existing system.
 * Senior WordPress developers will recognize the pattern: uses Automator()->get_actions().
 *
 * @since 7.0.0
 */
class WP_Action_Registry implements Action_Registry {

	private array $actions    = array();
	private bool $initialized = false;
	private Action_Meta_Structure_Converter $meta_converter;

	/**
	 * Constructor.
	 *
	 * @param Action_Meta_Structure_Converter|null $meta_converter Optional meta structure converter.
	 */
	public function __construct( ?Action_Meta_Structure_Converter $meta_converter = null ) {
		$this->meta_converter = $meta_converter ?? new Action_Meta_Structure_Converter();
	}

	/**
	 * Get all available action types.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of action definitions.
	 */
	public function get_available_actions( array $options = array() ): array {
		$this->ensure_initialized();

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $this->actions;
		}

		return $this->add_schema_to_actions( $this->actions );
	}

	/**
	 * Get specific action definition.
	 *
	 * @param Action_Code $code Action code.
	 * @param array       $options Format options: ['include_schema' => bool].
	 * @return array|null Action definition or null if not found.
	 */
	public function get_action_definition( Action_Code $code, array $options = array() ): ?array {
		$this->ensure_initialized();

		$action = $this->actions[ $code->get_value() ] ?? null;

		if ( null === $action ) {
			return null;
		}

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $action;
		}

		return $this->add_schema_to_action( $action );
	}

	/**
	 * Get actions by type.
	 *
	 * @param Action_User_Type $type Action type.
	 * @param array            $options Format options: ['include_schema' => bool].
	 * @return array Array of actions for the specified type.
	 */
	public function get_actions_by_type( Action_User_Type $type, array $options = array() ): array {
		$this->ensure_initialized();

		$type_value = $type->get_value();

		$filtered_actions = array_filter(
			$this->actions,
			function ( $action ) use ( $type_value ) {
				return ( $action['action_type'] ?? 'user' ) === $type_value;
			}
		);

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $filtered_actions;
		}

		return $this->add_schema_to_actions( $filtered_actions );
	}

	/**
	 * Register an action type.
	 *
	 * @param string $code Action code.
	 * @param array  $definition Action definition.
	 */
	public function register_action( string $code, array $definition ): void {
		$this->actions[ $code ] = $this->normalize_action_definition( $definition );
	}

	/**
	 * Check if action is registered.
	 *
	 * @param Action_Code $code Action code.
	 * @return bool True if registered.
	 */
	public function is_registered( Action_Code $code ): bool {
		$this->ensure_initialized();
		return array_key_exists( $code->get_value(), $this->actions );
	}

	/**
	 * Get actions by integration.
	 *
	 * @param string $integration Integration name.
	 * @return array Array of actions for the integration.
	 */
	public function get_actions_by_integration( string $integration ): array {
		$this->ensure_initialized();

		return array_filter(
			$this->actions,
			function ( $action ) use ( $integration ) {
				return ( $action['integration'] ?? '' ) === $integration;
			}
		);
	}

	/**
	 * Get WordPress native actions.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of WordPress native actions.
	 */
	public function get_wordpress_native_actions( array $options = array() ): array {
		$this->ensure_initialized();

		$native_actions = array_filter(
			$this->actions,
			function ( $action ) {
				$integration = $action['action_integration_code'] ?? $action['integration'] ?? '';
				return 'WP' === strtoupper( $integration );
			}
		);

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $native_actions;
		}

		return $this->add_schema_to_actions( $native_actions );
	}

	/**
	 * Get API integration actions.
	 *
	 * @param array $options Format options: ['include_schema' => bool].
	 * @return array Array of API integration actions.
	 */
	public function get_api_actions( array $options = array() ): array {
		$this->ensure_initialized();

		$api_actions = array_filter(
			$this->actions,
			function ( $action ) {
				$integration = $action['action_integration_code'] ?? $action['integration'] ?? '';
				return 'WP' !== strtoupper( $integration );
			}
		);

		$include_schema = $options['include_schema'] ?? false;

		if ( ! $include_schema ) {
			return $api_actions;
		}

		return $this->add_schema_to_actions( $api_actions );
	}

	/**
	 * Ensure actions are loaded from WordPress.
	 */
	private function ensure_initialized(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->load_actions_from_wordpress();
		$this->initialized = true;
	}

	/**
	 * Load actions from existing WordPress system.
	 */
	private function load_actions_from_wordpress(): void {
		// Get actions from the existing Automator system
		$wp_actions = \Automator()->get_actions();

		foreach ( $wp_actions as $code => $action ) {
			$this->actions[ $code ] = $this->normalize_action_definition( $action );
		}

		/**
		 * Allow additional actions to be registered.
		 *
		 * @param array $actions Existing actions array.
		 */
		$additional_actions = apply_filters( 'automator_api_register_actions', array() );

		foreach ( $additional_actions as $code => $action ) {
			$this->register_action( $code, $action );
		}
	}

	/**
	 * Normalize action definition to domain format.
	 *
	 * @param array $definition Raw action definition.
	 * @return array Normalized definition with domain keys.
	 */
	private function normalize_action_definition( array $definition ): array {

		return array(
			'action_code'             => $definition['code'] ?? '',
			'action_meta_code'        => $definition['meta_code'] ?? '',
			'action_integration_code' => $definition['integration'] ?? '',
			'action_type'             => $definition['action_type'] ?? 'user',
			'integration'             => $definition['integration'] ?? '', // Legacy key
			'integration_type'        => $this->determine_integration_type( $definition ),
			'sentence'                => $definition['sentence'] ?? '',
			'sentence_readable'       => $definition['sentence_human_readable'] ?? $definition['sentence'] ?? '',
			'meta_structure'          => $this->extract_meta_structure( $definition ),
			'callback'                => $definition['execution_function'] ?? null,
			'select_option_name'      => $definition['select_option_name'] ?? '',
			'priority'                => $definition['priority'] ?? 10,
			'accepted_args'           => $definition['accepted_args'] ?? 1,
			'requires_user'           => ( $definition['action_type'] ?? 'user' ) === 'user',
			'uses_api'                => isset( $definition['uses_api'] ) ? $definition['uses_api'] : false,
			'is_pro'                  => ! empty( $definition['is_pro'] ),
			'is_elite'                => ! empty( $definition['is_elite'] ),
			'is_deprecated'           => ! empty( $definition['is_deprecated'] ),
			'background_processing'   => ! empty( $definition['background_processing'] ),
			'manifest'                => $definition['manifest'] ?? array(),
		);
	}

	/**
	 * Determine integration type.
	 *
	 * @param array $definition Action definition.
	 * @return string 'WordPress' or 'api'.
	 */
	private function determine_integration_type( array $definition ): string {
		$integration = $definition['integration'] ?? '';

		if ( 'WP' === strtoupper( $integration ) ) {
			return 'WordPress';
		}

		return 'api';
	}

	/**
	 * Extract meta structure from action definition.
	 *
	 * @param array $definition Action definition.
	 * @return array Meta structure for the action.
	 */
	private function extract_meta_structure( array $definition ): array {
		if ( isset( $definition['meta'] ) && is_array( $definition['meta'] ) && ! empty( $definition['meta'] ) ) {
			return $definition['meta'];
		}

		if ( isset( $definition['options'] ) && is_array( $definition['options'] ) && ! empty( $definition['options'] ) ) {
			return $this->meta_converter->convert( $definition['options'] );
		}

		return array();
	}

	/**
	 * Add schema information to actions array.
	 *
	 * @param array $actions Actions array.
	 * @return array Actions with schema.
	 */
	private function add_schema_to_actions( array $actions ): array {
		$actions_with_schema = array();

		foreach ( $actions as $code => $action ) {
			$actions_with_schema[ $code ] = $this->add_schema_to_action( $action );
		}

		return $actions_with_schema;
	}

	/**
	 * Add schema information to single action.
	 *
	 * @param array $action Action definition.
	 * @return array Action with schema.
	 */
	private function add_schema_to_action( array $action ): array {
		$meta_structure = $action['meta_structure'] ?? array();

		$schema_properties = array();
		$required_fields   = array();

		foreach ( $meta_structure as $field_code => $field_config ) {
			$schema_properties[ $field_code ] = array(
				'type'        => $field_config['type'] ?? 'string',
				'description' => $field_config['description'] ?? $field_config['label'] ?? '',
			);

			// Add enum for select fields
			if ( ! empty( $field_config['options'] ) ) {
				$schema_properties[ $field_code ]['enum'] = array_keys( $field_config['options'] );
			}

			// Track required fields
			if ( $field_config['required'] ?? false ) {
				$required_fields[] = $field_code;
			}
		}

		return array(
			'name'        => $action['action_code'],
			'description' => $action['sentence_readable'] ?? $action['sentence'] ?? 'Action: ' . $action['action_code'],
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => $schema_properties,
				'required'   => $required_fields,
			),
		);
	}
}
