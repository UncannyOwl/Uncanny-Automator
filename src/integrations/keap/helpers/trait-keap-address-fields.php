<?php
/**
 * Keap Address Fields Trait
 *
 * Provides address field configuration and parsing methods
 * for contact and company actions.
 *
 * @package Uncanny_Automator\Integrations\Keap
 * @since 7.0
 */

namespace Uncanny_Automator\Integrations\Keap;

/**
 * Trait Keap_Address_Fields
 *
 * @property Keap_App_Helpers $helpers
 * @property Keap_Api_Caller $api
 */
trait Keap_Address_Fields {

	/**
	 * Get address fields configuration.
	 *
	 * @param string $type Address type (billing, shipping, other, or company).
	 *
	 * @return array Array of field configurations.
	 */
	protected function get_address_fields_config( $type ) {

		$cap_type = ucfirst( $type );
		$prefix   = strtoupper( $type ) . '_';

		// Dynamic visibility rules.
		$visibility = array(
			'default_state'    => 'hidden',
			'visibility_rules' => array(
				array(
					'operator'             => 'AND',
					'rule_conditions'      => array(
						array(
							'option_code' => "{$prefix}ADDRESS_ENABLED",
							'compare'     => '==',
							'value'       => true,
						),
					),
					'resulting_visibility' => 'show',
				),
			),
		);

		// Checkbox to enable/disable address visibility.
		$fields[] = array(
			'input_type'      => 'checkbox',
			'option_code'     => "{$prefix}ADDRESS_ENABLED",
			'label'           => sprintf(
				/* translators: %s: Address type */
				esc_html_x( 'Add/Update %s address', 'Keap', 'uncanny-automator' ),
				$type
			),
			'required'        => false,
			'supports_tokens' => false,
			'is_toggle'       => true,
		);

		// Address line 1.
		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}LINE1",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				/* translators: %s: Address type */
				esc_html_x( '%s address line 1', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
		);

		// Address line 2.
		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}LINE2",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				/* translators: %s: Address type */
				esc_html_x( '%s address line 2', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
		);

		// City/Locality.
		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}LOCALITY",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				/* translators: %s: Address type */
				esc_html_x( '%s address city/locality', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
		);

		// Region code.
		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}REGION_CODE",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				/* translators: %s: Address type */
				esc_html_x( '%s address region code', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
			'description'        => sprintf(
				/* translators: %1$s opening anchor tag, %2$s: closing anchor tag */
				esc_html_x( 'An %1$sISO 3166-2%2$s province/region code, such as "US-CA" for California.', 'Keap', 'uncanny-automator' ),
				'<a href="https://en.wikipedia.org/wiki/ISO_3166-2:US" target="_blank">',
				'</a>'
			),
		);

		// Zip/postal code.
		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}ZIP_CODE",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				/* translators: %s: Address type */
				esc_html_x( '%s address zip/postal code', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
		);

		// Country code.
		$fields[] = array(
			'input_type'         => 'text',
			'option_code'        => "{$prefix}COUNTRY_CODE",
			'dynamic_visibility' => $visibility,
			'label'              => sprintf(
				/* translators: %s: Address type */
				esc_html_x( '%s address country code', 'Keap', 'uncanny-automator' ),
				$cap_type
			),
			'description'        => sprintf(
				/* translators: %1$s opening anchor tag, %2$s: closing anchor tag */
				esc_html_x( 'An %1$sISO 3166-2%2$s Country Code, such as "USA" for the United States of America.', 'Keap', 'uncanny-automator' ),
				'<a href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-3" target="_blank">',
				'</a>'
			),
		);

		return $fields;
	}

	/**
	 * Get address fields from parsed data.
	 *
	 * @param array  $parsed Parsed action data.
	 * @param string $type   Address type (billing, shipping, other, or company).
	 *
	 * @return object|false Address object or false if not enabled/empty.
	 */
	protected function get_address_fields_from_parsed( $parsed, $type ) {

		// Bail if we have invalid data.
		if ( empty( $parsed ) || ! is_array( $parsed ) || ! is_string( $type ) ) {
			return false;
		}

		// Set prefix.
		$prefix = strtoupper( $type ) . '_';

		// Check if enabled.
		if ( ! $this->get_bool_value_from_parsed( $parsed, "{$prefix}ADDRESS_ENABLED" ) ) {
			return false;
		}

		// Get field values.
		$fields = array(
			'line1'        => (string) ( $parsed[ "{$prefix}LINE1" ] ?? '' ),
			'line2'        => (string) ( $parsed[ "{$prefix}LINE2" ] ?? '' ),
			'locality'     => (string) ( $parsed[ "{$prefix}LOCALITY" ] ?? '' ),
			'region_code'  => (string) ( $parsed[ "{$prefix}REGION_CODE" ] ?? '' ),
			'zip_code'     => (string) ( $parsed[ "{$prefix}ZIP_CODE" ] ?? '' ),
			'country_code' => (string) ( $parsed[ "{$prefix}COUNTRY_CODE" ] ?? '' ),
		);

		// Sanitize and remove empty fields.
		$fields = array_filter( array_map( 'sanitize_text_field', $fields ) );
		if ( empty( $fields ) ) {
			return false;
		}

		// Convert all delete keys to empty strings.
		$fields = array_map(
			function ( $value ) {
				return $this->get_delete_key() === $value ? '' : $value;
			},
			$fields
		);

		// Add Keap field type.
		$fields['field'] = 'company' === $type ? 'ADDRESS_FIELD_UNSPECIFIED' : strtoupper( $type );

		// Return as object with fields for the request.
		return (object) $fields;
	}
}
