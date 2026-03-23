<?php
/**
 * Field Registry Service
 *
 * Core business logic service for field definition discovery and lookup.
 * Single source of truth for retrieving raw field definitions from
 * action, trigger, and condition registrations.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Field;

use Uncanny_Automator\Services\Integrations\Fields;
use Uncanny_Automator\Api\Components\Condition\Registry\WP_Action_Condition_Registry;

/**
 * Field Registry Service Class
 *
 * Handles field definition retrieval with clean OOP architecture.
 * Provides access to raw field definitions including supports_markdown
 * and supports_tinymce flags that are needed for proper sanitization.
 */
class Field_Registry_Service {

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var Field_Registry_Service|null
	 */
	private static ?Field_Registry_Service $instance = null;

	/**
	 * Fields service instance (for actions/triggers).
	 *
	 * @var Fields|null
	 */
	private ?Fields $fields_service = null;

	/**
	 * Condition registry instance.
	 *
	 * @var WP_Action_Condition_Registry|null
	 */
	private ?WP_Action_Condition_Registry $condition_registry = null;

	/**
	 * Cache of field definitions by item.
	 *
	 * Keyed by "{item_type}:{item_code}".
	 *
	 * @var array<string, array>
	 */
	private array $item_cache = array();

	/**
	 * Cache of individual field definitions.
	 *
	 * Keyed by "{item_type}:{item_code}:{field_code}".
	 *
	 * @var array<string, array|null>
	 */
	private array $field_cache = array();

	/**
	 * Constructor.
	 *
	 * @param Fields|null                       $fields_service     Optional Fields service instance for testing.
	 * @param WP_Action_Condition_Registry|null $condition_registry Optional condition registry for testing.
	 */
	public function __construct( ?Fields $fields_service = null, ?WP_Action_Condition_Registry $condition_registry = null ) {
		$this->fields_service     = $fields_service;
		$this->condition_registry = $condition_registry;
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @return Field_Registry_Service
	 */
	public static function instance(): Field_Registry_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get a specific field definition by its option_code.
	 *
	 * Retrieves the raw field definition array from the action/trigger/condition
	 * registration, including flags like supports_markdown and supports_tinymce.
	 *
	 * @param string $item_type  Item type: 'action', 'trigger', or 'condition'.
	 * @param string $item_code  Action/Trigger/Condition code (e.g., 'WRITE_DATA_TO_LOG').
	 * @param string $field_code Field option_code (e.g., 'LOGGING_DATA_DATA').
	 *
	 * @return array|null Field definition array or null if not found.
	 */
	public function get_field_definition(
		string $item_type,
		string $item_code,
		string $field_code
	): ?array {
		$cache_key = "{$item_type}:{$item_code}:{$field_code}";

		// Return cached result if available.
		if ( array_key_exists( $cache_key, $this->field_cache ) ) {
			return $this->field_cache[ $cache_key ];
		}

		// Get all fields for this item.
		$all_fields = $this->get_fields_for_item( $item_type, $item_code );

		if ( empty( $all_fields ) ) {
			$this->field_cache[ $cache_key ] = null;
			return null;
		}

		// Search through field groups for matching option_code.
		// Structure varies by type:
		// - Actions/Triggers: ['GROUP_CODE' => [{field1}, {field2}, ...]]
		// - Conditions: [{field1}, {field2}, ...] (flat array from filter)
		$field = $this->find_field_by_option_code( $all_fields, $field_code );

		$this->field_cache[ $cache_key ] = $field;
		return $field;
	}

	/**
	 * Find field by option_code in a fields array.
	 *
	 * Handles both grouped structure (actions/triggers) and flat structure (conditions).
	 *
	 * @param array  $fields     Fields array to search.
	 * @param string $field_code Field option_code to find.
	 *
	 * @return array|null Field definition or null if not found.
	 */
	private function find_field_by_option_code( array $fields, string $field_code ): ?array {
		foreach ( $fields as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			// Check if this is a field definition (has option_code).
			if ( isset( $value['option_code'] ) ) {
				if ( $value['option_code'] === $field_code ) {
					return $value;
				}
				continue;
			}

			// This is a group - search within it.
			foreach ( $value as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
					continue;
				}

				if ( $field['option_code'] === $field_code ) {
					return $field;
				}
			}
		}

		return null;
	}

	/**
	 * Get all field definitions for an action, trigger, or condition.
	 *
	 * Returns the raw field definition array from the appropriate registry,
	 * structured as:
	 * - Actions/Triggers: ['GROUP_CODE' => [{field1}, {field2}, ...]]
	 * - Conditions: [{field1}, {field2}, ...] (flat array)
	 *
	 * @param string $item_type Item type: 'action', 'trigger', or 'condition'.
	 * @param string $item_code Action/Trigger/Condition code.
	 *
	 * @return array Field definitions array or empty array if not found.
	 */
	public function get_fields_for_item( string $item_type, string $item_code ): array {
		$cache_key = "{$item_type}:{$item_code}";

		// Return cached result if available.
		if ( isset( $this->item_cache[ $cache_key ] ) ) {
			return $this->item_cache[ $cache_key ];
		}

		// Route to appropriate handler based on item type.
		if ( 'condition' === $item_type ) {
			$definitions = $this->get_condition_fields( $item_code );
		} else {
			$definitions = $this->get_action_or_trigger_fields( $item_type, $item_code );
		}

		$this->item_cache[ $cache_key ] = $definitions;
		return $definitions;
	}

	/**
	 * Get fields for an action or trigger.
	 *
	 * @param string $item_type Item type: 'action' or 'trigger'.
	 * @param string $item_code Action/Trigger code.
	 *
	 * @return array Field definitions array.
	 */
	private function get_action_or_trigger_fields( string $item_type, string $item_code ): array {
		$fields = $this->get_fields_service();

		// Configure for the specific item.
		// Note: object_type expects plural form ('actions' or 'triggers').
		$fields->set_config(
			array(
				'object_type' => $item_type . 's',
				'code'        => $item_code,
			)
		);

		try {
			$definitions = $fields->get();
		} catch ( \Exception $e ) {
			return array();
		}

		return is_array( $definitions ) ? $definitions : array();
	}

	/**
	 * Get fields for a condition.
	 *
	 * Conditions are identified by condition_code. The integration_code
	 * is looked up from the condition registry.
	 *
	 * @param string $condition_code Condition code (e.g., 'WP_USER_LOGGED_IN').
	 *
	 * @return array Field definitions array (flat structure).
	 */
	private function get_condition_fields( string $condition_code ): array {
		$registry = $this->get_condition_registry();

		// Find integration code for this condition.
		$integration_code = $this->find_integration_for_condition( $condition_code );

		if ( empty( $integration_code ) ) {
			return array();
		}

		return $registry->get_raw_condition_fields( $integration_code, $condition_code );
	}

	/**
	 * Find the integration code for a condition.
	 *
	 * @param string $condition_code Condition code.
	 *
	 * @return string Integration code or empty string if not found.
	 */
	private function find_integration_for_condition( string $condition_code ): string {
		$registry       = $this->get_condition_registry();
		$all_conditions = $registry->get_all_conditions();

		foreach ( $all_conditions as $integration_code => $conditions ) {
			if ( isset( $conditions[ $condition_code ] ) ) {
				return $integration_code;
			}
		}

		return '';
	}

	/**
	 * Check if a field has markdown support.
	 *
	 * @param string $item_type  Item type: 'action', 'trigger', or 'condition'.
	 * @param string $item_code  Action/Trigger/Condition code.
	 * @param string $field_code Field option_code.
	 *
	 * @return bool True if field supports markdown.
	 */
	public function field_supports_markdown(
		string $item_type,
		string $item_code,
		string $field_code
	): bool {
		$field = $this->get_field_definition( $item_type, $item_code, $field_code );
		return ! empty( $field['supports_markdown'] );
	}

	/**
	 * Check if a field has TinyMCE/HTML support.
	 *
	 * @param string $item_type  Item type: 'action', 'trigger', or 'condition'.
	 * @param string $item_code  Action/Trigger/Condition code.
	 * @param string $field_code Field option_code.
	 *
	 * @return bool True if field supports TinyMCE.
	 */
	public function field_supports_tinymce(
		string $item_type,
		string $item_code,
		string $field_code
	): bool {
		$field = $this->get_field_definition( $item_type, $item_code, $field_code );
		return ! empty( $field['supports_tinymce'] );
	}

	/**
	 * Get the Fields service instance.
	 *
	 * @return Fields
	 */
	private function get_fields_service(): Fields {
		if ( null === $this->fields_service ) {
			$this->fields_service = new Fields();
		}

		return $this->fields_service;
	}

	/**
	 * Get the Condition Registry instance.
	 *
	 * @return WP_Action_Condition_Registry
	 */
	private function get_condition_registry(): WP_Action_Condition_Registry {
		if ( null === $this->condition_registry ) {
			$this->condition_registry = new WP_Action_Condition_Registry();
		}

		return $this->condition_registry;
	}

	/**
	 * Clear all caches.
	 *
	 * Useful for testing or when field definitions may have changed.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->item_cache  = array();
		$this->field_cache = array();
	}

	/**
	 * Set a custom Fields service (for testing).
	 *
	 * @param Fields $fields_service The Fields service instance.
	 *
	 * @return void
	 */
	public function set_fields_service( Fields $fields_service ): void {
		$this->fields_service = $fields_service;
		$this->clear_cache();
	}

	/**
	 * Set a custom Condition Registry (for testing).
	 *
	 * @param WP_Action_Condition_Registry $condition_registry The condition registry instance.
	 *
	 * @return void
	 */
	public function set_condition_registry( WP_Action_Condition_Registry $condition_registry ): void {
		$this->condition_registry = $condition_registry;
		$this->clear_cache();
	}
}
