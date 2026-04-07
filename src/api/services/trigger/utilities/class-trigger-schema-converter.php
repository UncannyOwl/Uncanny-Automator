<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Trigger\Utilities;

use Uncanny_Automator\Api\Components\Field\Field_Json_Schema;

/**
 * Trigger Schema Converter — thin delegate.
 *
 * Delegates field-to-schema conversion to the shared Field_Json_Schema.
 * Retains build_input_schema() for raw trigger definition → schema conversion.
 *
 * @since 7.0.0
 * @since 7.1.0 Delegates to Field_Json_Schema.
 * @package Uncanny_Automator
 */
class Trigger_Schema_Converter {

	/**
	 * Shared converter.
	 *
	 * @var Field_Json_Schema
	 */
	private Field_Json_Schema $converter;

	/**
	 * Constructor.
	 *
	 * @param Field_Json_Schema|null $converter Shared converter.
	 */
	public function __construct( ?Field_Json_Schema $converter = null ) {
		$this->converter = $converter ?? new Field_Json_Schema();
	}

	/**
	 * Convert fields array to JSON Schema format.
	 *
	 * @since 7.0.0
	 *
	 * @param array $fields Fields array from Fields service.
	 *
	 * @return array JSON Schema format.
	 */
	public function convert_fields_to_schema( array $fields ): array {
		return $this->converter->convert_fields_to_schema( $fields );
	}

	/**
	 * Build input schema from trigger definition.
	 *
	 * Used when building schema from raw Automator trigger definitions
	 * (not from Fields::get()). This path doesn't go through the field
	 * converter because the data shape is different.
	 *
	 * @since 7.0.0
	 *
	 * @param array $definition Trigger definition.
	 *
	 * @return array Input schema.
	 */
	public function build_input_schema( array $definition ): array {
		$schema = array(
			'type'       => 'object',
			'properties' => array(),
		);

		if ( ! empty( $definition['fields'] ) ) {
			foreach ( $definition['fields'] as $field_code => $field_def ) {
				$schema['properties'][ $field_code ] = array(
					'type'        => $field_def['type'] ?? 'string',
					'description' => $field_def['label'] ?? $field_code,
				);

				if ( isset( $field_def['options'] ) && is_array( $field_def['options'] ) ) {
					$schema['properties'][ $field_code ]['enum'] = array_keys( $field_def['options'] );
				}

				if ( $field_def['required'] ?? false ) {
					$schema['required'][] = $field_code;
				}
			}
		}

		return $schema;
	}
}
