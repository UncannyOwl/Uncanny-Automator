<?php

namespace Uncanny_Automator\Integrations\HubSpot;

/**
 * Class HubSpot_Field_Utils
 *
 * Static utility methods for HubSpot field generation and filtering.
 * Used by AJAX handlers to build repeater configurations.
 *
 * @package Uncanny_Automator\Integrations\HubSpot
 */
class HubSpot_Field_Utils {

	/**
	 * Contact category.
	 *
	 * @var string
	 */
	const CATEGORY_CONTACT = 'contact';

	/**
	 * Custom category.
	 *
	 * @var string
	 */
	const CATEGORY_CUSTOM = 'custom';

	/**
	 * Additional category.
	 *
	 * @var string
	 */
	const CATEGORY_ADDITIONAL = 'additional';

	/**
	 * Curated list of core contact field names with display order.
	 *
	 * These are universal HubSpot default properties that exist on every account.
	 * Order determines display position in the Contact fields repeater.
	 *
	 * @return array Field names as keys, display order as values.
	 */
	public static function get_core_contact_fields() {
		return array(
			// Core contact info.
			'firstname'      => 1,
			'lastname'       => 2,
			'phone'          => 3,
			'mobilephone'    => 4,
			'jobtitle'       => 5,
			'company'        => 6,
			'website'        => 7,
			// Address.
			'address'        => 10,
			'city'           => 11,
			'state'          => 12,
			'zip'            => 13,
			'country'        => 14,
			// CRM.
			'lifecyclestage' => 20,
		);
	}

	/**
	 * Generate repeater fields configuration from HubSpot fields.
	 *
	 * Converts HubSpot field definitions to Automator repeater field format
	 * with appropriate input types. Each field is categorized and sorted by
	 * display order for organized presentation.
	 *
	 * @param array $hubspot_fields Array of HubSpot field definitions.
	 *
	 * @return array The repeater fields configuration, sorted by display_order.
	 */
	public static function generate_repeater_fields( $hubspot_fields ) {
		$fields              = array();
		$core_contact_fields = self::get_core_contact_fields();

		foreach ( $hubspot_fields as $field ) {
			// Skip read-only, calculated, and email fields.
			if ( self::should_exclude_field( $field ) ) {
				continue;
			}

			$field_name   = $field['name'] ?? '';
			$hubspot_type = $field['type'] ?? 'string';

			// Get input type and markdown support flag.
			list( $automator_type, $supports_markdown ) = self::get_automator_input_type( $field );

			// Determine the field category.
			$field_category = self::get_field_category( $field );

			// Use curated order for contact fields, alphabetical for others.
			if ( self::CATEGORY_CONTACT === $field_category && isset( $core_contact_fields[ $field_name ] ) ) {
				$display_order = $core_contact_fields[ $field_name ];
			} else {
				// Use -1 to trigger alphabetical sorting for non-contact fields.
				$display_order = -1;
			}

			$field_config = array(
				'option_code'     => $field_name,
				'label'           => $field['label'],
				'input_type'      => $automator_type,
				'supports_tokens' => true,
				'required'        => false,
				'hubspot_type'    => $hubspot_type,
				'field_category'  => $field_category,
				'display_order'   => $display_order,
			);

			// Add markdown support for rich text and textarea fields.
			if ( $supports_markdown ) {
				$field_config['supports_markdown'] = true;
			}

			// Add select-specific configuration.
			if ( 'select' === $automator_type ) {
				$field_config = self::add_select_config( $field, $field_config );
			}

			// Index by field name for easy lookup during processing.
			$fields[ $field_name ] = $field_config;
		}

		// Sort by display_order (fields with -1 sorted alphabetically).
		uasort( $fields, array( __CLASS__, 'sort_by_display_order' ) );

		return $fields;
	}

	/**
	 * Sort callback for display order.
	 *
	 * @param array $a First field.
	 * @param array $b Second field.
	 *
	 * @return int Sort result.
	 */
	public static function sort_by_display_order( $a, $b ) {
		$order_a = $a['display_order'] ?? -1;
		$order_b = $b['display_order'] ?? -1;

		// Both have -1, sort alphabetically by label.
		if ( -1 === $order_a && -1 === $order_b ) {
			return strcasecmp( $a['label'], $b['label'] );
		}

		// -1 goes to end.
		if ( -1 === $order_a ) {
			return 1;
		}
		if ( -1 === $order_b ) {
			return -1;
		}

		return $order_a - $order_b;
	}

	/**
	 * Filter fields by category.
	 *
	 * @param array  $fields   The field configurations.
	 * @param string $category The category to filter by.
	 *
	 * @return array Fields matching the category.
	 */
	public static function filter_by_category( $fields, $category ) {
		return array_values(
			array_filter(
				$fields,
				function ( $field ) use ( $category ) {
					return ( $field['field_category'] ?? '' ) === $category;
				}
			)
		);
	}

	/**
	 * Filter to return only contact fields.
	 *
	 * @param array $fields The field configurations.
	 *
	 * @return array Contact category fields.
	 */
	public static function filter_contact_fields( $fields ) {
		return self::filter_by_category( $fields, self::CATEGORY_CONTACT );
	}

	/**
	 * Filter to return only custom-defined fields.
	 *
	 * @param array $fields The field configurations.
	 *
	 * @return array Custom category fields.
	 */
	public static function filter_custom_fields( $fields ) {
		return self::filter_by_category( $fields, self::CATEGORY_CUSTOM );
	}

	/**
	 * Filter to return only additional fields.
	 *
	 * @param array $fields The field configurations.
	 *
	 * @return array Additional category fields.
	 */
	public static function filter_additional_fields( $fields ) {
		return self::filter_by_category( $fields, self::CATEGORY_ADDITIONAL );
	}

	/**
	 * Exclude specific fields by option_code.
	 *
	 * @param array $fields        The field configurations.
	 * @param array $exclude_codes Array of option_code values to exclude.
	 *
	 * @return array Fields with exclusions removed.
	 */
	public static function exclude_fields( $fields, $exclude_codes ) {
		return array_values(
			array_filter(
				$fields,
				function ( $field ) use ( $exclude_codes ) {
					return ! in_array( $field['option_code'] ?? '', $exclude_codes, true );
				}
			)
		);
	}

	/**
	 * Generate dropdown options for additional fields selector.
	 *
	 * @param array $fields The field configurations.
	 *
	 * @return array Select options with value/text pairs.
	 */
	public static function generate_additional_field_options( $fields ) {
		$additional = self::filter_additional_fields( $fields );

		$options = array(
			array(
				'value' => '',
				'text'  => esc_html_x( 'Select a field', 'HubSpot', 'uncanny-automator' ),
			),
		);

		foreach ( $additional as $field ) {
			$options[] = array(
				'value' => $field['option_code'],
				'text'  => $field['label'],
			);
		}

		return $options;
	}

	/**
	 * Determine the category for a field.
	 *
	 * Categories:
	 * - 'contact': Curated list of core HubSpot contact fields
	 * - 'custom': User-defined custom fields
	 * - 'additional': All other HubSpot-defined fields
	 *
	 * @param array $field The HubSpot field definition.
	 *
	 * @return string The field category.
	 */
	private static function get_field_category( $field ) {
		$is_hubspot_defined  = ! empty( $field['hubspotDefined'] );
		$field_name          = $field['name'] ?? '';
		$core_contact_fields = self::get_core_contact_fields();

		// Custom fields (not HubSpot-defined).
		if ( ! $is_hubspot_defined ) {
			return self::CATEGORY_CUSTOM;
		}

		// Curated core contact fields.
		if ( array_key_exists( $field_name, $core_contact_fields ) ) {
			return self::CATEGORY_CONTACT;
		}

		// Everything else.
		return self::CATEGORY_ADDITIONAL;
	}

	/**
	 * Check if the field should be excluded from the repeater.
	 *
	 * @param array $field The HubSpot field.
	 * @return bool
	 */
	private static function should_exclude_field( $field ) {
		// Exclude read-only fields.
		if ( ! empty( $field['readOnlyValue'] ) ) {
			return true;
		}

		// Exclude calculated fields.
		if ( ! empty( $field['calculated'] ) ) {
			return true;
		}

		// Exclude calculation fields (internal HubSpot calculations).
		$field_type = $field['fieldType'] ?? '';
		if ( in_array( $field_type, array( 'calculation_equation', 'calculation_rollup' ), true ) ) {
			return true;
		}

		// Exclude email field (handled separately).
		if ( 'email' === ( $field['name'] ?? '' ) ) {
			return true;
		}

		// Exclude hidden fields.
		if ( ! empty( $field['hidden'] ) ) {
			return true;
		}

		// Exclude non-form fields (internal HubSpot system properties).
		// v3 API returns additional system properties not present in v1.
		if ( empty( $field['formField'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Convert HubSpot field type to Automator input type.
	 *
	 * @param array $field The HubSpot field.
	 * @return array Input type and whether markdown is supported.
	 */
	private static function get_automator_input_type( $field ) {
		$type       = $field['type'] ?? 'string';
		$field_type = $field['fieldType'] ?? '';

		// External options without provided options (owner selectors) → text for advanced users.
		if ( ! empty( $field['externalOptions'] ) && empty( $field['options'] ) ) {
			return array( 'text', false );
		}

		switch ( $type ) {
			case 'enumeration':
			case 'bool':
				return array( 'select', false );

			case 'number':
			case 'date':
			case 'datetime':
				// Use text for numbers and dates to support tokens.
				return array( 'text', false );

			case 'string':
			default:
				// Rich text (html) and multi-line (textarea) → textarea with markdown support.
				if ( in_array( $field_type, array( 'textarea', 'html' ), true ) ) {
					return array( 'textarea', true );
				}
				return array( 'text', false );
		}
	}

	/**
	 * Add select field configuration with options.
	 *
	 * @param array $field The HubSpot field.
	 * @param array $field_config The base field configuration.
	 * @return array The updated field configuration.
	 */
	private static function add_select_config( $field, $field_config ) {
		$type = $field['type'] ?? 'string';

		// Boolean fields get Yes/No options.
		if ( 'bool' === $type ) {
			$field_config['options'] = array(
				array(
					'value' => '',
					'text'  => esc_html_x( 'Select a value', 'HubSpot', 'uncanny-automator' ),
				),
				array(
					'value' => 'true',
					'text'  => esc_html_x( 'Yes', 'HubSpot', 'uncanny-automator' ),
				),
				array(
					'value' => 'false',
					'text'  => esc_html_x( 'No', 'HubSpot', 'uncanny-automator' ),
				),
				array(
					'value' => '[delete]',
					'text'  => esc_html_x( '[DELETE]', 'HubSpot', 'uncanny-automator' ),
				),
			);

			$field_config['supports_custom_value'] = true;
			return $field_config;
		}

		// Enumeration fields get options from field definition.
		$options = array(
			array(
				'value' => '',
				'text'  => esc_html_x( 'Select a value', 'HubSpot', 'uncanny-automator' ),
			),
		);

		if ( ! empty( $field['options'] ) ) {
			foreach ( $field['options'] as $option ) {
				if ( ! empty( $option['hidden'] ) ) {
					continue;
				}
				$options[] = array(
					'value' => $option['value'] ?? '',
					'text'  => $option['label'] ?? $option['value'] ?? '',
				);
			}
		}

		// Only checkbox is multi-select (not booleancheckbox which is single Yes/No).
		$is_multi = 'checkbox' === ( $field['fieldType'] ?? '' );

		if ( $is_multi ) {
			$field_config['supports_multiple_values'] = true;
			$field_config['placeholder']              = esc_html_x( 'Select options', 'HubSpot', 'uncanny-automator' );
			// Remove empty option for multi-select.
			array_shift( $options );
		}

		// Always add [DELETE] option.
		$options[] = array(
			'value' => '[delete]',
			'text'  => esc_html_x( '[DELETE]', 'HubSpot', 'uncanny-automator' ),
		);

		$field_config['options']               = $options;
		$field_config['supports_custom_value'] = true;

		return $field_config;
	}
}
