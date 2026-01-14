<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Trigger\Utilities;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Trigger_Logic;
use Uncanny_Automator\Api\Components\Trigger\Registry\WP_Trigger_Registry;
use Uncanny_Automator\Api\Components\Trigger\Trigger;
use Uncanny_Automator\Api\Components\Trigger\Value_Objects\Trigger_Code;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Services\Trigger\Utilities\Trigger_Schema_Converter;
use Uncanny_Automator\Services\Integrations\Fields;


/**
 * Trigger Validation Service.
 *
 * Handles business rule validation for triggers and recipes.
 * WordPress developers will see this as "trigger validation functions".
 *
 * @since 7.0.0
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
class Trigger_Validator {

	private $recipe_store;
	private $trigger_registry;
	private $trigger_registry_service;

	/**
	 * Constructor.
	 *
	 * @param mixed $recipe_store Recipe store implementation.
	 * @param mixed $trigger_registry Trigger registry implementation.
	 */
	public function __construct( ?WP_Recipe_Store $recipe_store = null, ?WP_Trigger_Registry $trigger_registry = null ) {
		$this->recipe_store             = $recipe_store ?? new WP_Recipe_Store();
		$this->trigger_registry         = $trigger_registry ?? new WP_Trigger_Registry();
		$this->trigger_registry_service = Trigger_Registry_Service::get_instance();
	}

	/**
	 * Validate trigger can be added to recipe.
	 *
	 * @param Trigger   $trigger Trigger to validate.
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	public function validate_trigger_for_recipe( Trigger $trigger, Recipe_Id $recipe_id ): void {
		// Get recipe to check type and current triggers
		$recipe = $this->recipe_store->get( $recipe_id->get_value() );

		if ( ! $recipe ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: %d Recipe ID */
					esc_html_x( 'Recipe not found: %d', 'Trigger validation error', 'uncanny-automator' ),
					$recipe_id->get_value()
				)
			);
		}

		$recipe_type   = $recipe->get_recipe_type()->get_value();
		$current_count = $recipe->get_recipe_triggers()->count();
		$trigger_type  = $trigger->get_trigger_type()->get_value();

		// Validate trigger type matches recipe type
		if ( $recipe_type !== $trigger_type ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: 1: Trigger type, 2: Recipe type */
					esc_html_x( 'Trigger type "%1$s" does not match recipe type "%2$s"', 'Trigger validation error', 'uncanny-automator' ),
					$trigger_type,
					$recipe_type
				)
			);
		}

		// Validate anonymous recipe trigger limit
		if ( 'anonymous' === $recipe_type && $current_count >= 1 ) {
			throw new InvalidArgumentException( esc_html_x( 'Anonymous recipes can only have 1 trigger', 'Trigger validation error', 'uncanny-automator' ) );
		}

		// Validate trigger exists in registry
		$trigger_code = $trigger->get_trigger_code()->get_value();
		if ( ! $this->trigger_registry->get_trigger_definition( $trigger->get_trigger_code() ) ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: %s Trigger code */
					esc_html_x( 'Unknown trigger code: %s', 'Trigger validation error', 'uncanny-automator' ),
					$trigger_code
				)
			);
		}

		// Validate trigger integration is available
		$this->validate_trigger_integration_availability( $trigger );
	}

	/**
	 * Validate trigger configuration.
	 *
	 * Uses the Fields service to get properly structured field definitions,
	 * matching the approach used in Action_Validator.
	 *
	 * @param string $trigger_code Trigger code.
	 * @param array  $configuration Configuration to validate.
	 * @return bool True if valid.
	 * @throws \InvalidArgumentException If invalid.
	 */
	public function validate_trigger_configuration( string $trigger_code, array $configuration ): bool {

		// Verify trigger exists in registry first.
		$definition = $this->trigger_registry->get_trigger_definition(
			new Trigger_Code( $trigger_code )
		);

		if ( ! $definition ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: %s Trigger code */
					esc_html_x( 'Unable to load the trigger: %s from registry.', 'Trigger validation error', 'uncanny-automator' ),
					$trigger_code
				)
			);
		}

		// Use Fields service to get properly structured configuration fields.
		// This matches the approach used in Action_Validator.
		$fields = new Fields();
		$fields->set_config(
			array(
				'object_type' => 'triggers',
				'code'        => $trigger_code,
			)
		);

		try {
			$configuration_fields = $fields->get();
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
			// If we can't get fields, the trigger might not have any configurable fields.
			return true;
		}

		return $this->validate_configuration_against_fields( $configuration, $configuration_fields );
	}

	/**
	 * Validate trigger logic setting.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @param string    $logic Trigger logic ('all' or 'any').
	 * @throws \InvalidArgumentException If validation fails.
	 */
	public function validate_trigger_logic_setting( Recipe_Id $recipe_id, string $logic ): void {

		// Validate logic value
		new Recipe_Trigger_Logic( $logic ); // Will throw if invalid.

		// Get recipe
		$recipe = $this->recipe_store->get( $recipe_id->get_value() );

		if ( ! $recipe ) {
			throw new InvalidArgumentException(
				sprintf(
				/* translators: %d Recipe ID */
					esc_html_x( 'Recipe not found: %d', 'Trigger validation error', 'uncanny-automator' ),
					$recipe_id->get_value()
				)
			);
		}

		$recipe_type   = $recipe->get_recipe_type()->get_value();
		$trigger_count = $recipe->get_recipe_triggers()->count();

		// Only user recipes can have trigger logic
		if ( 'anonymous' === $recipe_type ) {
			throw new InvalidArgumentException( esc_html_x( 'Anonymous recipes cannot have trigger logic', 'Trigger validation error', 'uncanny-automator' ) );
		}

		// Logic only makes sense with multiple triggers
		if ( $trigger_count <= 1 ) {
			throw new InvalidArgumentException( esc_html_x( 'Trigger logic requires multiple triggers', 'Trigger validation error', 'uncanny-automator' ) );
		}
	}

	/**
	 * Validate anonymous recipe trigger limit.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @throws \InvalidArgumentException If limit exceeded.
	 */
	public function validate_anonymous_recipe_trigger_limit( Recipe_Id $recipe_id ): void {
		$recipe = $this->recipe_store->get( $recipe_id->get_value() );

		if ( ! $recipe ) {
			return;
		}

		if ( 'anonymous' === $recipe->get_recipe_type()->get_value() ) {
			$trigger_count = $recipe->get_recipe_triggers()->count();

			if ( $trigger_count > 1 ) {
				throw new InvalidArgumentException(
					sprintf(
						/* translators: 1: Recipe ID, 2: Trigger count */
						esc_html_x( 'Anonymous recipe %1$d has %2$d triggers, maximum allowed is 1', 'Trigger validation error', 'uncanny-automator' ),
						$recipe_id->get_value(),
						$trigger_count
					)
				);
			}
		}
	}

	/**
	 * Validate trigger integration availability.
	 *
	 * Ensures the trigger's integration is available for use - either the plugin
	 * is installed (for plugin integrations) or the app is connected (for app integrations).
	 *
	 * @since 7.0.0
	 * @param Trigger $trigger Trigger to validate.
	 * @throws \InvalidArgumentException If integration not available.
	 */
	public function validate_trigger_integration_availability( Trigger $trigger ): void {
		// Get trigger data array.
		$trigger_data = $trigger->to_array();

		$integration_code = $trigger_data['integration'] ?? '';

		if ( empty( $integration_code ) ) {
			// No integration specified - this is valid for core WordPress triggers.
			return;
		}

		// Prepare data for availability check.
		$availability_input = array(
			'integration_id' => $integration_code,
			'code'           => $trigger_data['code'] ?? '',
			'required_tier'  => $trigger_data['required_tier'] ?? 'lite',
		);

		// Check integration availability using centralized service.
		$availability = $this->trigger_registry_service->check_trigger_integration_availability( $availability_input );

		if ( ! $availability['available'] ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: 1: Integration code, 2: Availability message */
					esc_html_x( 'Integration "%1$s" is not available: %2$s', 'Trigger validation error', 'uncanny-automator' ),
					$integration_code,
					$availability['message']
				)
			);
		}
	}

	/**
	 * Validate configuration against field definitions.
	 *
	 * Iterates through the Fields service output structure to validate
	 * required fields and field formats. Mirrors Action_Validator logic.
	 *
	 * @param array $configuration Configuration to validate.
	 * @param array $configuration_fields Field definitions from Fields service.
	 * @return bool True if valid.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate_configuration_against_fields( array $configuration, array $configuration_fields ): bool {

		$errors = array();

		// 1. Get required fields from configuration fields.
		$required_fields = $this->get_required_fields( $configuration_fields );
		$missing_fields  = array_diff( $required_fields, array_keys( $configuration ) );

		if ( ! empty( $missing_fields ) ) {
			$errors[] = sprintf(
				/* translators: %s Field list. */
				esc_html_x( 'Missing required fields: %s', 'Trigger validation error', 'uncanny-automator' ),
				implode( ', ', $missing_fields )
			);
		}

		// 2. Check for required fields that are present but empty.
		foreach ( $required_fields as $required_field ) {
			if ( isset( $configuration[ $required_field ] ) ) {
				$value = $configuration[ $required_field ];
				// Skip arrays (repeaters, multi-selects) - they have their own validation.
				if ( is_array( $value ) ) {
					continue;
				}
				// Validate scalar values.
				if ( empty( trim( (string) $value ) ) ) {
					$errors[] = sprintf(
						/* translators: %s Field code. */
						esc_html_x( 'Required field "%s" cannot be empty.', 'Trigger validation error', 'uncanny-automator' ),
						$required_field
					);
				}
			}
		}

		// 3. Validate field formats.
		foreach ( $configuration_fields as $field_group ) {

			if ( ! is_array( $field_group ) ) {
				continue;
			}

			foreach ( $field_group as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
					continue;
				}

				$field_code  = $field['option_code'];
				$field_value = $configuration[ $field_code ] ?? '';

				// Skip validation for empty non-required fields.
				if ( empty( $field_value ) && empty( $field['required'] ) ) {
					continue;
				}

				// Validate based on field type and attributes.
				$field_errors = $this->validate_field_value( $field, $field_value, $field_code );
				if ( ! empty( $field_errors ) ) {
					$errors = array_merge( $errors, $field_errors );
				}
			}
		}

		// Throw exception if any errors found - include schema to help AI recover.
		if ( ! empty( $errors ) ) {
			$schema_converter = new Trigger_Schema_Converter();
			$expected_schema  = $schema_converter->convert_fields_to_schema( $configuration_fields );

			$error_message = sprintf(
				"Trigger validation failed: %s.\n\nExpected schema:\n%s\n\nYou can also call get_component_schema with component_type=\"trigger\" and the trigger code to get field options.",
				implode( '; ', $errors ),
				wp_json_encode( $expected_schema, JSON_PRETTY_PRINT )
			);

			throw new InvalidArgumentException( $error_message );
		}

		return true;
	}

	/**
	 * Get required fields from configuration fields.
	 *
	 * @param array $configuration_fields Configuration fields array.
	 * @return string[] Required field names.
	 */
	private function get_required_fields( array $configuration_fields ): array {

		$required_fields = array();

		foreach ( $configuration_fields as $field_group ) {
			if ( ! is_array( $field_group ) ) {
				continue;
			}

			foreach ( $field_group as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
					continue;
				}

				// Ensure option_code is a string, not an array.
				if ( ! is_string( $field['option_code'] ) ) {
					continue;
				}

				if ( ! empty( $field['required'] ) ) {
					$required_fields[] = $field['option_code'];
				}
			}
		}

		return $required_fields;
	}

	/**
	 * Validate a single field value against its definition.
	 *
	 * @param array  $field Field definition.
	 * @param mixed  $value Field value.
	 * @param string $field_code Field code for error messages.
	 * @return array Array of error messages (empty if valid).
	 */
	private function validate_field_value( array $field, $value, string $field_code ): array {

		$errors = array();

		// Get field attributes.
		$input_type  = $field['input_type'] ?? '';
		$field_label = $field['label'] ?? $field_code;

		// Skip validation for non-scalar values (arrays, objects, etc.).
		if ( ! is_scalar( $value ) ) {
			return array();
		}

		// Don't validate if the field value is a token.
		if ( preg_match( '/\{\{.*\}\}/', (string) $value ) ) {
			return array();
		}

		// Repeater validation - must be valid JSON array.
		if ( 'repeater' === $input_type && ! empty( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
				$errors[] = sprintf( '"%s" contains invalid data. Repeater fields expect a JSON array of objects with key-value pairs matching the repeater columns. Use get_component_schema to see the expected structure.', $field_label );
			}
		}

		// Email validation.
		if ( 'email' === $input_type ) {
			if ( ! empty( $value ) && ! is_email( $value ) ) {
				$errors[] = sprintf( '"%s" must be a valid email address', $field_label );
			}
		}

		// URL validation.
		if ( 'url' === $input_type ) {
			if ( ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$errors[] = sprintf( '"%s" must be a valid URL', $field_label );
			}
		}

		// Number validation.
		if ( 'int' === $input_type ) {
			if ( ! empty( $value ) && ! is_numeric( $value ) ) {
				$errors[] = sprintf( '"%s" must be a valid number', $field_label );
			}
		}

		// Required field validation.
		if ( ! empty( $field['required'] ) && empty( $value ) ) {
			$errors[] = sprintf( '"%s" is required and cannot be empty', $field_label );
		}

		return $errors;
	}
}
