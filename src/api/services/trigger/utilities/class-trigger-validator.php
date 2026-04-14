<?php
/**
 * Trigger Validation Service.
 *
 * Handles business-rule validation for triggers and recipes. Field-level
 * validation (required fields, formats, enum) is delegated to the unified
 * Field_Validator via inheritance.
 *
 * @deprecated 7.1.0 Field validation now lives in {@see \Uncanny_Automator\Api\Services\Field\Utilities\Field_Validator}.
 *                   This class retains trigger/recipe-specific validation only.
 *
 * @since   7.0.0
 * @package Uncanny_Automator\Api\Services\Trigger\Utilities
 */

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
use Uncanny_Automator\Api\Services\Field\Utilities\Field_Validator;
use Uncanny_Automator\Services\Integrations\Fields;

/**
 * Trigger Validator Class.
 *
 * @deprecated 7.1.0 Field-level validation is now in Field_Validator.
 */
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
class Trigger_Validator extends Field_Validator {

	/**
	 * Recipe store.
	 *
	 * @var WP_Recipe_Store
	 */
	private $recipe_store;

	/**
	 * Trigger registry.
	 *
	 * @var WP_Trigger_Registry
	 */
	private $trigger_registry;

	/**
	 * Trigger registry service.
	 *
	 * @var Trigger_Registry_Service
	 */
	private $trigger_registry_service;

	/**
	 * Constructor.
	 *
	 * @param WP_Recipe_Store|null    $recipe_store    Recipe store implementation.
	 * @param WP_Trigger_Registry|null $trigger_registry Trigger registry implementation.
	 */
	public function __construct( ?WP_Recipe_Store $recipe_store = null, ?WP_Trigger_Registry $trigger_registry = null ) {
		$this->recipe_store             = $recipe_store ?? new WP_Recipe_Store();
		$this->trigger_registry         = $trigger_registry ?? new WP_Trigger_Registry();
		$this->trigger_registry_service = Trigger_Registry_Service::instance();
	}

	/**
	 * Validate trigger can be added to recipe.
	 *
	 * @param Trigger   $trigger   Trigger to validate.
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @throws \InvalidArgumentException If validation fails.
	 */
	public function validate_trigger_for_recipe( Trigger $trigger, Recipe_Id $recipe_id ): void {
		// Get recipe to check type and current triggers.
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

		// Validate trigger type matches recipe type.
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

		// Validate anonymous recipe trigger limit.
		if ( 'anonymous' === $recipe_type && $current_count >= 1 ) {
			throw new InvalidArgumentException( esc_html_x( 'Anonymous recipes can only have 1 trigger', 'Trigger validation error', 'uncanny-automator' ) );
		}

		// Validate trigger exists in registry.
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

		// Validate trigger integration is available.
		$this->validate_trigger_integration_availability( $trigger );
	}

	/**
	 * Validate trigger configuration.
	 *
	 * Uses the Fields service to get properly structured field definitions,
	 * then delegates field-level checks to Field_Validator.
	 *
	 * @param string $trigger_code  Trigger code.
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

		// Delegate field-level validation to parent Field_Validator.
		$result = parent::validate( $trigger_code, $configuration, 'trigger' );

		if ( is_wp_error( $result ) ) {
			// Enrich error with expected schema for AI recovery.
			$schema_converter = new Trigger_Schema_Converter();
			$expected_schema  = $schema_converter->convert_fields_to_schema( $configuration_fields );

			$error_message = sprintf(
				"Trigger validation failed: %s.\n\nExpected schema:\n%s\n\nYou can also call get_component_schema with component_type=\"trigger\" and the trigger code to get field options.",
				$result->get_error_message(),
				wp_json_encode( $expected_schema, JSON_PRETTY_PRINT )
			);

			throw new InvalidArgumentException( $error_message );
		}

		return true;
	}

	/**
	 * Validate trigger logic setting.
	 *
	 * @param Recipe_Id $recipe_id Recipe ID.
	 * @param string    $logic     Trigger logic ('all' or 'any').
	 * @throws \InvalidArgumentException If validation fails.
	 */
	public function validate_trigger_logic_setting( Recipe_Id $recipe_id, string $logic ): void {

		// Validate logic value.
		new Recipe_Trigger_Logic( $logic ); // Will throw if invalid.

		// Get recipe.
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

		// Only user recipes can have trigger logic.
		if ( 'anonymous' === $recipe_type ) {
			throw new InvalidArgumentException( esc_html_x( 'Anonymous recipes cannot have trigger logic', 'Trigger validation error', 'uncanny-automator' ) );
		}

		// Logic only makes sense with multiple triggers.
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
}
