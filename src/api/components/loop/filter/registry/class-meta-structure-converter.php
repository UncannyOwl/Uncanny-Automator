<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Filter\Registry;

/**
 * Meta_Structure_Converter.
 *
 * Converts legacy Automator filter option definitions into the normalized
 * meta structure consumed by the API layer.
 *
 * @since 7.0.0
 */
class Meta_Structure_Converter {

	/**
	 * Convert WordPress style option definitions to the normalized meta structure.
	 *
	 * Supports both legacy grouped option arrays and simplified associative arrays
	 * keyed by option code.
	 *
	 * @param array $options Raw option definition array.
	 *
	 * @return array Normalized meta structure keyed by option code.
	 */
	public function convert( array $options ): array {
		$fields         = $this->collect_fields( $options );
		$meta_structure = array();

		foreach ( $fields as $field_code => $field ) {
			if ( '' === $field_code ) {
				continue;
			}

			$meta_structure[ $field_code ] = array(
				'type'        => $this->convert_input_type( $field['input_type'] ?? 'text' ),
				'label'       => $field['label'] ?? '',
				'description' => $field['description'] ?? $field['custom_value_description'] ?? '',
				'required'    => ! empty( $field['required'] ),
				'placeholder' => $field['placeholder'] ?? '',
				'options'     => $this->normalize_options( $field['options'] ?? array() ),
			);
		}

		return $meta_structure;
	}

	/**
	 * Recursively collect field definitions from the provided options array.
	 *
	 * @param array  $options      Raw options array.
	 * @param string $fallback_key Fallback option code inherited from parent
	 *                             associative keys.
	 *
	 * @return array<string,array> Field definitions keyed by option code.
	 */
	public function collect_fields( array $options, string $fallback_key = '' ): array {
		// If the current array already looks like a field definition, return it.
		if ( $this->looks_like_field_definition( $options ) ) {
			$field_code = $this->resolve_field_code( $options, $fallback_key );

			if ( '' === $field_code ) {
				return array();
			}

			return array(
				$field_code => $options,
			);
		}

		$fields = array();

		foreach ( $options as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			$fallback = is_string( $key ) ? (string) $key : $fallback_key;
			$nested   = $this->collect_fields( $value, $fallback );

			foreach ( $nested as $field_code => $definition ) {
				$fields[ $field_code ] = $definition;
			}
		}

		return $fields;
	}

	/**
	 * Determine whether the provided array resembles a field definition.
	 *
	 * @param array $value Array to inspect.
	 *
	 * @return bool
	 */
	public function looks_like_field_definition( array $value ): bool {
		return isset( $value['option_code'] )
			|| isset( $value['input_type'] )
			|| isset( $value['label'] )
			|| isset( $value['options'] );
	}

	/**
	 * Resolve the option code for a field definition.
	 *
	 * @param array  $field        Field definition array.
	 * @param string $fallback_key Fallback key sourced from parent array key.
	 *
	 * @return string
	 */
	public function resolve_field_code( array $field, string $fallback_key ): string {
		if ( isset( $field['option_code'] ) && '' !== trim( (string) $field['option_code'] ) ) {
			return (string) $field['option_code'];
		}

		if ( '' !== $fallback_key ) {
			return $fallback_key;
		}

		return '';
	}

	/**
	 * Normalize the options structure into key/value pairs.
	 *
	 * @param array $raw_options Raw options array.
	 *
	 * @return array<string,string>
	 */
	public function normalize_options( array $raw_options ): array {
		$normalized = array();

		foreach ( $raw_options as $key => $option ) {
			if ( is_array( $option ) ) {
				if ( isset( $option['value'] ) ) {
					$value                = (string) $option['value'];
					$text                 = (string) ( $option['text'] ?? $option['label'] ?? $option['value'] );
					$normalized[ $value ] = $text;
				} elseif ( isset( $option['label'] ) ) {
					$value                = is_string( $key ) ? (string) $key : (string) ( $option['value'] ?? $option['label'] );
					$normalized[ $value ] = (string) $option['label'];
				}
				continue;
			}

			if ( is_string( $option ) ) {
				$normalized[ (string) $key ] = $option;
			}
		}

		return $normalized;
	}

	/**
	 * Convert the legacy input type into an OpenAPI compatible type.
	 *
	 * @param string $input_type Raw input type.
	 *
	 * @return string
	 */
	public function convert_input_type( string $input_type ): string {
		$type_map = array(
			'text'     => 'string',
			'textarea' => 'string',
			'select'   => 'string',
			'choice'   => 'string',
			'email'    => 'string',
			'url'      => 'string',
			'number'   => 'number',
			'int'      => 'number',
			'checkbox' => 'boolean',
			'toggle'   => 'boolean',
		);

		$normalized = strtolower( trim( $input_type ) );

		return $type_map[ $normalized ] ?? 'string';
	}
}
