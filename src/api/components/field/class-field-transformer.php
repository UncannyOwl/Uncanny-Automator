<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Field;

use Uncanny_Automator\Api\Components\Field\Value_Objects\Flattened_Field_Config;
use Uncanny_Automator\Api\Services\Field\Field_Sanitizer;

/**
 * Field Transformer DTO.
 *
 * Simple DTO layer that transforms Field aggregates to Flattened_Field_Config.
 * Delegates WordPress-specific sanitization to Field_Sanitizer service.
 *
 * Transforms Field components to flat config format:
 * [
 *   "FIELD_CODE" => "automator_custom_value",
 *   "FIELD_CODE_readable" => "Use a token/custom value",
 *   "FIELD_CODE_custom" => "{{recipe_id}}"
 * ]
 *
 * @since 7.0
 */
class Field_Transformer {

	/**
	 * Field sanitizer service.
	 *
	 * @var Field_Sanitizer
	 */
	private Field_Sanitizer $sanitizer;

	/**
	 * Constructor.
	 *
	 * @param Field_Sanitizer|null $sanitizer Optional sanitizer instance.
	 */
	public function __construct( ?Field_Sanitizer $sanitizer = null ) {
		$this->sanitizer = $sanitizer ?? new Field_Sanitizer();
	}

	/**
	 * Transform Field component to Flattened_Field_Config.
	 *
	 * @param Field  $field     Field component.
	 * @param string $transport Transport identifier (e.g., 'rest', 'mcp').
	 * @return Flattened_Field_Config Flattened field config value object.
	 */
	public function to_config( Field $field, string $transport ): Flattened_Field_Config {
		$config     = Flattened_Field_Config::empty();
		$field_code = $field->get_code();
		$readable   = $field->get_readable();
		$value_vo   = $field->get_value();
		$custom_vo  = $field->get_custom();

		// Add readable value if present.
		if ( $readable->has_value() ) {
			$config->add_readable( $field_code, $readable->get_value() );
		}

		// Handle custom values - when value is sentinel, keep it and add _custom suffix.
		if ( $value_vo->is_custom_value_sentinel() && $custom_vo->has_value() ) {
			// Keep the sentinel value.
			$config->add_field( $field_code, $value_vo->get_value() );

			// Add the actual custom value with _custom suffix.
			$config->add_custom( $field_code, $custom_vo->get_value() );

			return $config;
		}

		// Process the field value (sanitization handled by Field_Sanitizer service).
		$value = $this->sanitizer->process_value( $field, $transport );

		// Set the main field value.
		$config->add_field( $field_code, $value );

		return $config;
	}

	/**
	 * Transform array of Field components to flat config array.
	 *
	 * @param array<string, Field> $fields    Array of Field components keyed by field code.
	 * @param string                $transport Transport identifier (e.g., 'rest', 'mcp').
	 * @return array<string, mixed> Flat config array for CRUD services.
	 * @throws \InvalidArgumentException If non-Field objects are found in array.
	 */
	public function to_config_array( array $fields, string $transport ): array {
		$config = Flattened_Field_Config::empty();

		foreach ( $fields as $field_code => $field ) {
			if ( ! $field instanceof Field ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Invalid field at key "%s": expected Field instance, got %s',
						esc_html( $field_code ),
						is_object( $field ) ? esc_html( get_class( $field ) ) : esc_html( gettype( $field ) )
					)
				);
			}

			$field_config = $this->to_config( $field, $transport );
			$config->merge( $field_config );
		}

		return $config->to_array();
	}
}
