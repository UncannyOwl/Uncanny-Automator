<?php

namespace Uncanny_Automator\Integrations\Ontraport;

/**
 * Trait Ontraport_Contact_Fields_Trait
 *
 * Provides shared contact field definitions and processing
 * for Ontraport add/update contact actions.
 *
 * @package Uncanny_Automator\Integrations\Ontraport
 */
trait Ontraport_Contact_Fields_Trait {

	////////////////////////////////////////////////////////////
	// Shared — used by both the current and deprecated actions.
	////////////////////////////////////////////////////////////

	/**
	 * Get the hardcoded contact field definitions.
	 *
	 * Each entry maps an Ontraport API key to its UI configuration.
	 * Extended fields (title, company, office_phone) are only included
	 * for the current action to avoid changing deprecated action behavior.
	 *
	 * @param bool $include_extended Whether to include extended fields.
	 *
	 * @return array
	 */
	private function get_contact_fields( $include_extended = false ) {

		$fields = array(
			array(
				'api_key'     => 'firstname',
				'option_code' => 'FIRST_NAME',
				'label'       => esc_html_x( 'First name', 'Ontraport', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'api_key'     => 'lastname',
				'option_code' => 'LAST_NAME',
				'label'       => esc_html_x( 'Last name', 'Ontraport', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
		);

		if ( $include_extended ) {
			$fields[] = array(
				'api_key'     => 'title',
				'option_code' => 'TITLE',
				'label'       => esc_html_x( 'Title', 'Ontraport', 'uncanny-automator' ),
				'input_type'  => 'text',
			);
			$fields[] = array(
				'api_key'     => 'company',
				'option_code' => 'COMPANY',
				'label'       => esc_html_x( 'Company', 'Ontraport', 'uncanny-automator' ),
				'input_type'  => 'text',
			);
		}

		$fields[] = array(
			'api_key'     => 'address',
			'option_code' => 'ADDRESS',
			'label'       => esc_html_x( 'Address', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
		);

		if ( $include_extended ) {
			$fields[] = array(
				'api_key'     => 'office_phone',
				'option_code' => 'OFFICE_PHONE',
				'label'       => esc_html_x( 'Office phone', 'Ontraport', 'uncanny-automator' ),
				'input_type'  => 'text',
			);
		}

		$fields[] = array(
			'api_key'     => 'sms_number',
			'option_code' => 'SMS_NUMBER',
			'label'       => esc_html_x( 'SMS number', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
		);
		$fields[] = array(
			'api_key'     => 'facebook_link',
			'option_code' => 'FACEBOOK_LINK',
			'label'       => esc_html_x( 'Facebook link', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'url',
			'placeholder' => esc_html_x( 'https://', 'Ontraport', 'uncanny-automator' ),
		);
		$fields[] = array(
			'api_key'     => 'instagram_link',
			'option_code' => 'INSTAGRAM_LINK',
			'label'       => esc_html_x( 'Instagram link', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'url',
			'placeholder' => esc_html_x( 'https://', 'Ontraport', 'uncanny-automator' ),
		);
		$fields[] = array(
			'api_key'     => 'linkedin_link',
			'option_code' => 'LINKEDIN_LINK',
			'label'       => esc_html_x( 'LinkedIn link', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'url',
			'placeholder' => esc_html_x( 'https://', 'Ontraport', 'uncanny-automator' ),
		);
		$fields[] = array(
			'api_key'     => 'twitter_link',
			'option_code' => 'TWITTER_LINK',
			'label'       => esc_html_x( 'Twitter link', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'url',
			'placeholder' => esc_html_x( 'https://', 'Ontraport', 'uncanny-automator' ),
		);

		return $fields;
	}

	/**
	 * Build flat option configs from the shared field definitions.
	 *
	 * Used by the deprecated action to render individual fields.
	 *
	 * @return array
	 */
	private function get_contact_field_options() {

		$options = array();

		foreach ( $this->get_contact_fields() as $field ) {
			$option = array(
				'option_code' => $field['option_code'],
				'label'       => $field['label'],
				'input_type'  => $field['input_type'],
				'required'    => false,
			);

			if ( ! empty( $field['placeholder'] ) ) {
				$option['placeholder'] = $field['placeholder'];
			}

			$options[] = $option;
		}

		return $options;
	}

	/**
	 * Get the status dropdown option config.
	 *
	 * @param bool $include_defaults Whether to include placeholder and the delete option.
	 *
	 * @return array
	 */
	private function get_status_field( $include_defaults = true ) {
		return array(
			'option_code'         => 'STATUS',
			'label'               => esc_html_x( 'Status', 'Ontraport', 'uncanny-automator' ),
			'input_type'          => 'select',
			'required'            => false,
			'options'             => $this->get_status_options( $include_defaults ),
			'allow_custom_values' => true,
		);
	}

	/**
	 * Get the status select options.
	 *
	 * @param bool $include_defaults Whether to include placeholder and the delete option.
	 *
	 * @return array
	 */
	private function get_status_options( $include_defaults = true ) {

		$options = array();

		if ( $include_defaults ) {
			$options[] = array(
				'value' => '',
				'text'  => esc_html_x( 'Select option', 'Ontraport', 'uncanny-automator' ),
			);
		}

		$options = array_merge(
			$options,
			array(
				array(
					'text'  => esc_html_x( 'Closed - Lost', 'Ontraport', 'uncanny-automator' ),
					'value' => '1',
				),
				array(
					'text'  => esc_html_x( 'Closed - Won', 'Ontraport', 'uncanny-automator' ),
					'value' => '2',
				),
				array(
					'text'  => esc_html_x( 'Committed', 'Ontraport', 'uncanny-automator' ),
					'value' => '3',
				),
				array(
					'text'  => esc_html_x( 'Consideration', 'Ontraport', 'uncanny-automator' ),
					'value' => '4',
				),
				array(
					'text'  => esc_html_x( 'Demo Scheduled', 'Ontraport', 'uncanny-automator' ),
					'value' => '5',
				),
				array(
					'text'  => esc_html_x( 'Qualified Lead', 'Ontraport', 'uncanny-automator' ),
					'value' => '6',
				),
				array(
					'text'  => esc_html_x( 'New Prospect', 'Ontraport', 'uncanny-automator' ),
					'value' => '7',
				),
			)
		);

		if ( $include_defaults ) {
			$options[] = array(
				'value' => Ontraport_Add_Update_Contact::DELETE_VALUE,
				'text'  => esc_html_x( 'Delete value', 'Ontraport', 'uncanny-automator' ),
			);
		}

		return $options;
	}

	/**
	 * Parse the repeater fields into an associative array of API key => value.
	 *
	 * Only includes fields where the update toggle is enabled.
	 *
	 * @param array $repeater_data The decoded repeater JSON data.
	 *
	 * @return array
	 */
	private function parse_contact_fields( $repeater_data ) {

		// Build a set of valid API keys for validation.
		$valid_keys = array();
		foreach ( $this->get_contact_fields( true ) as $field ) {
			$valid_keys[ $field['api_key'] ] = true;
		}

		$fields = array();

		foreach ( $repeater_data as $row ) {
			$api_key = $row->ONTRAPORT_FIELD_KEY ?? ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$value   = $row->ONTRAPORT_FIELD_VALUE ?? ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$update  = $row->ONTRAPORT_UPDATE_FIELD ?? false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			// Only include if the field key is valid and the update toggle is on.
			if ( ! empty( $api_key ) && isset( $valid_keys[ $api_key ] ) && ! empty( $update ) ) {
				$fields[ $api_key ] = $value;
			}
		}

		return $fields;
	}

	////////////////////////////////////////////////////////////
	// Current action only — custom fields and repeater helpers.
	////////////////////////////////////////////////////////////

	/**
	 * Build default rows for the contact fields repeater from field definitions.
	 *
	 * @return array
	 */
	private function get_contact_field_default_rows() {

		$rows = array();

		foreach ( $this->get_contact_fields( true ) as $field ) {
			$rows[] = array(
				'ONTRAPORT_FIELD_KEY'    => $field['api_key'],
				'ONTRAPORT_FIELD_NAME'   => $field['label'],
				'ONTRAPORT_FIELD_VALUE'  => '',
				'ONTRAPORT_UPDATE_FIELD' => true,
			);
		}

		return $rows;
	}

	/**
	 * Parse the custom fields transposed repeater data.
	 *
	 * Empty values are ignored. The [DELETE] sentinel maps to an empty string
	 * so Ontraport clears the field value.
	 *
	 * @param string $custom_fields_json The raw JSON from the repeater meta.
	 *
	 * @return array Associative array of field key => value.
	 */
	private function parse_custom_fields( $custom_fields_json ) {
		$data   = json_decode( $custom_fields_json, true );
		$fields = array();
		if ( ! is_array( $data ) || empty( $data ) ) {
			return $fields;
		}

		// Transposed repeaters wrap values in an outer array.
		$row = array_shift( $data );
		if ( ! is_array( $row ) ) {
			return $fields;
		}

		// Build a type map for validation.
		$type_map = array_column(
			$this->helpers->get_custom_fields( false, false ),
			'ontraport_type',
			'option_code'
		);

		foreach ( $row as $key => $value ) {
			// Skip internal keys added by the repeater UI.
			if ( '_readable' === substr( $key, -9 ) || '_custom' === substr( $key, -7 ) ) {
				continue;
			}
			// Multi-select (list) fields come as arrays — convert to Ontraport's */* format.
			if ( is_array( $value ) ) {
				$value = empty( $value ) ? '' : '*/*' . implode( '*/*', $value ) . '*/*';
			}
			// Skip empty values — only process fields the user explicitly filled.
			if ( '' === $value || null === $value ) {
				continue;
			}
			// [DELETE] means clear the value on Ontraport.
			if ( Ontraport_Add_Update_Contact::DELETE_VALUE === $value ) {
				$fields[ $key ] = '';
				continue;
			}
			// Type-specific validation and conversion.
			$field_type = $type_map[ $key ] ?? '';
			$error      = $this->validate_custom_field_value( $key, $value, $field_type );
			if ( null !== $error ) {
				$this->custom_field_errors[] = $error;
				continue;
			}
			$fields[ $key ] = $value;
		}

		return $fields;
	}

	/**
	 * Validate and convert a custom field value based on its Ontraport type.
	 *
	 * Returns null on success (and modifies $value by reference),
	 * or an error string if validation fails.
	 *
	 * @param string $key        The field key.
	 * @param mixed  $value      The field value (passed by reference).
	 * @param string $field_type The Ontraport field type.
	 *
	 * @return string|null Error message or null on success.
	 */
	private function validate_custom_field_value( $key, &$value, $field_type ) {
		switch ( $field_type ) {
			case 'fulldate':
			case 'timestamp':
				$converted = strtotime( $value );
				if ( false === $converted ) {
					return sprintf(
						// translators: 1: Field key, 2: Invalid value.
						esc_html_x( '%1$s: invalid date "%2$s"', 'Ontraport', 'uncanny-automator' ),
						$key,
						$value
					);
				}
				$value = (string) $converted;
				break;

			case 'numeric':
			case 'price':
				if ( false === filter_var( $value, FILTER_VALIDATE_FLOAT ) ) {
					return sprintf(
						// translators: 1: Field key, 2: Invalid value.
						esc_html_x( '%1$s: invalid number "%2$s"', 'Ontraport', 'uncanny-automator' ),
						$key,
						$value
					);
				}
				break;

			case 'email':
				if ( ! filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
					return sprintf(
						// translators: 1: Field key, 2: Invalid value.
						esc_html_x( '%1$s: invalid email "%2$s"', 'Ontraport', 'uncanny-automator' ),
						$key,
						$value
					);
				}
				break;
		}

		return null;
	}
}
