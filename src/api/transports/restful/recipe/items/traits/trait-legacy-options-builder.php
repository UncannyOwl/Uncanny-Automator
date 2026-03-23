<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits;

use Uncanny_Automator\Api\Services\Field\Field_Registry_Service;

/**
 * Trait for building legacy options structure.
 *
 * Builds the $options array expected by the legacy
 * automator_sanitize_get_field_type_{$type} filter and deprecated hooks.
 *
 * Matches the structure sent by the frontend via $request->get_param('options'):
 * ```
 * $options = array(
 *     'groupCode' => 'OPTION_CODE',
 *     'fields'    => array(
 *         $field_code => array(
 *             'type'                     => 'textarea',
 *             'value'                    => 'field value',
 *             'value_readable'           => 'Human readable value',
 *             'supports_tinymce'         => true,
 *             'supports_markdown'        => false,
 *             'supports_multiple_values' => false,
 *             'supports_custom_value'    => true,
 *         ),
 *     ),
 * );
 * ```
 *
 * @since 7.0
 */
trait Legacy_Options_Builder {

	/**
	 * Build legacy options from fields.
	 *
	 * Combines field data from REST request with definition flags
	 * from Field_Registry_Service to match the legacy $options structure.
	 *
	 * @param array  $fields     Fields from REST request.
	 * @param string $item_type  Item type: 'action', 'trigger', or 'condition'.
	 * @param string $item_code  Action/Trigger/Condition code.
	 * @param string $group_code Optional group code (primary meta key) for deprecated hooks.
	 *
	 * @return array Legacy options structure.
	 */
	protected function build_legacy_options_from_fields(
		array $fields,
		string $item_type,
		string $item_code,
		string $group_code = ''
	): array {
		$registry      = Field_Registry_Service::instance();
		$legacy_fields = array();

		foreach ( $fields as $field_code => $field_data ) {
			if ( ! is_array( $field_data ) ) {
				continue;
			}

			// Start with field data from REST request.
			$legacy_field = array(
				'type'  => $field_data['type'] ?? 'text',
				'value' => $field_data['value'] ?? '',
			);

			if ( ! empty( $field_data['readable'] ) ) {
				$legacy_field['value_readable'] = $field_data['readable'];
			}

			// Get definition from registry for additional flags.
			$definition = $registry->get_field_definition(
				$item_type,
				$item_code,
				$field_code
			);

			if ( ! empty( $definition ) ) {
				// Legacy code checks for string 'true', not boolean.
				if ( ! empty( $definition['supports_tinymce'] ) ) {
					$legacy_field['supports_tinymce'] = 'true';
				}
				if ( ! empty( $definition['supports_markdown'] ) ) {
					$legacy_field['supports_markdown'] = 'true';
				}
				if ( isset( $definition['supports_multiple_values'] ) ) {
					$legacy_field['supports_multiple_values'] = $definition['supports_multiple_values'];
				}
				if ( isset( $definition['supports_custom_value'] ) ) {
					$legacy_field['supports_custom_value'] = $definition['supports_custom_value'];
				}
			}

			$legacy_fields[ $field_code ] = $legacy_field;
		}

		$options = array(
			'fields' => $legacy_fields,
		);

		if ( ! empty( $group_code ) ) {
			$options['groupCode'] = $group_code;
		}

		return $options;
	}

	/**
	 * Transform field types based on legacy options.
	 *
	 * Upgrades textarea fields to markdown/html based on integration definitions.
	 * Applies deprecated legacy filter for backwards compatibility.
	 *
	 * @param array $fields        Fields array (modified by reference).
	 * @param array $legacy_options Legacy options with field definitions.
	 *
	 * @return void
	 */
	protected function transform_field_types( array &$fields, array $legacy_options ): void {
		foreach ( $fields as $field_code => &$field_data ) {
			if ( ! is_array( $field_data ) ) {
				continue;
			}

			$base_type     = $field_data['type'] ?? 'text';
			$resolved_type = $this->resolve_field_type_from_options(
				$field_code,
				$base_type,
				$legacy_options
			);

			// Apply deprecated legacy filter (REST layer only).
			$resolved_type = $this->apply_deprecated_type_filter(
				$resolved_type,
				$field_code,
				$legacy_options
			);

			$field_data['type'] = $resolved_type;
		}
	}

	/**
	 * Resolve field type from legacy options.
	 *
	 * Upgrades textarea types to markdown or html based on field definition flags.
	 *
	 * @param string $field_code     The field code.
	 * @param string $base_type      The base field type from the request.
	 * @param array  $legacy_options Legacy options with field definitions.
	 *
	 * @return string The resolved field type.
	 */
	private function resolve_field_type_from_options(
		string $field_code,
		string $base_type,
		array $legacy_options
	): string {
		// Only upgrade textarea types.
		if ( 'textarea' !== $base_type ) {
			return $base_type;
		}

		$field_opts = $legacy_options['fields'][ $field_code ] ?? array();

		if ( ! empty( $field_opts['supports_markdown'] ) && 'true' === (string) $field_opts['supports_markdown'] ) {
			return 'markdown';
		}

		if ( ! empty( $field_opts['supports_tinymce'] ) && 'true' === (string) $field_opts['supports_tinymce'] ) {
			return 'html';
		}

		return $base_type;
	}

	/**
	 * Apply deprecated legacy type filter (REST layer only).
	 *
	 * The new automator_field_type filter is applied in Field_Sanitizer
	 * for all transports.
	 *
	 * @param string $type           The resolved field type.
	 * @param string $field_code     The field code.
	 * @param array  $legacy_options Legacy options with field definitions.
	 *
	 * @return string The filtered field type.
	 */
	private function apply_deprecated_type_filter(
		string $type,
		string $field_code,
		array $legacy_options
	): string {
		return apply_filters_deprecated(
			'automator_sanitize_get_field_type_' . $type,
			array( $type, $field_code, $legacy_options ),
			'7.0',
			'automator_field_type',
			esc_html_x(
				'Use the automator_field_type filter instead.',
				'Restful API',
				'uncanny-automator'
			)
		);
	}

	/**
	 * Apply deprecated automator_sanitized_data filter to flat config.
	 *
	 * Loops through fields and applies the deprecated filter to each value.
	 *
	 * @param array<string, mixed> $flat_config    Flattened config array.
	 * @param array<string, array> $fields         Original fields array with types.
	 * @param array                $legacy_options Legacy options array.
	 *
	 * @return array<string, mixed> Modified flat config.
	 */
	protected function apply_deprecated_sanitized_data_to_config(
		array $flat_config,
		array $fields,
		array $legacy_options
	): array {
		foreach ( $fields as $field_code => $field_data ) {
			if ( ! is_array( $field_data ) || ! isset( $flat_config[ $field_code ] ) ) {
				continue;
			}

			$type                       = $field_data['type'] ?? 'text';
			$flat_config[ $field_code ] = apply_filters_deprecated(
				'automator_sanitized_data',
				array( $flat_config[ $field_code ], $type, $field_code, $legacy_options ),
				'7.0',
				'automator_field_value_sanitized',
				esc_html_x(
					'Use automator_field_value_sanitized filter instead.',
					'Restful API',
					'uncanny-automator'
				)
			);
		}

		return $flat_config;
	}
}
