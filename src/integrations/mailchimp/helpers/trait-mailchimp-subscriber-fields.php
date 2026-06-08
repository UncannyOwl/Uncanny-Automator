<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Trait Mailchimp_Subscriber_Fields
 *
 * Provides subscriber management field configurations and parsing methods.
 *
 * @package Uncanny_Automator
 */
trait Mailchimp_Subscriber_Fields {

	/**
	 * Get Yes/No options for select fields.
	 *
	 * @return array The options array.
	 */
	private function get_yes_no_options() {
		return array(
			array(
				'value' => 'true',
				'text'  => esc_html_x( 'Yes', 'Mailchimp', 'uncanny-automator' ),
			),
			array(
				'value' => 'false',
				'text'  => esc_html_x( 'No', 'Mailchimp', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Get double opt-in select field configuration.
	 *
	 * @return array The field configuration.
	 */
	public function get_double_optin_config() {
		return array(
			'option_code'     => 'MCDOUBLEOPTIN',
			'label'           => esc_html_x( 'Double opt-in', 'Mailchimp', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->get_yes_no_options(),
			'options_show_id' => false,
			'description'     => esc_html_x(
				'When set to "yes", a confirmation email will be sent before the user is added to the selected audience.',
				'Mailchimp',
				'uncanny-automator'
			),
		);
	}

	/**
	 * Get update existing select field configuration.
	 *
	 * @return array The field configuration.
	 */
	public function get_update_existing_config() {
		return array(
			'option_code'     => 'MCUPDATEEXISTING',
			'label'           => esc_html_x( 'Update existing', 'Mailchimp', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->get_yes_no_options(),
			'options_show_id' => false,
			'description'     => esc_html_x(
				'If this is set to Yes, the information provided will be used to update the existing user. Fields that are left blank will not be updated.',
				'Mailchimp',
				'uncanny-automator'
			),
		);
	}

	/**
	 * Get delete member select field configuration.
	 *
	 * @return array The field configuration.
	 */
	public function get_delete_member_config() {
		return array(
			'option_code'     => 'MCDELETEMEMBER',
			'label'           => esc_html_x( 'Delete subscriber from Mailchimp?', 'Mailchimp', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $this->get_yes_no_options(),
			'options_show_id' => false,
			'description'     => esc_html_x(
				'Yes, delete from Mailchimp, No, only unsubscribe from audience',
				'Mailchimp',
				'uncanny-automator'
			),
		);
	}

	/**
	 * Get change groups select field configuration.
	 *
	 * @return array The field configuration.
	 */
	public function get_change_groups_config() {
		return array(
			'option_code' => 'MCCHANGEGROUPS',
			'label'       => esc_html_x( 'Change groups?', 'Mailchimp', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(
				array(
					'value' => 'replace-all',
					'text'  => esc_html_x( 'Replace all', 'Mailchimp', 'uncanny-automator' ),
				),
				array(
					'value' => 'add-only',
					'text'  => esc_html_x( 'Add only', 'Mailchimp', 'uncanny-automator' ),
				),
				array(
					'value' => 'replace-matching',
					'text'  => esc_html_x( 'Remove matching', 'Mailchimp', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Get groups/interests select field configuration.
	 *
	 * This field listens to MCLIST changes to populate group options via AJAX.
	 *
	 * @return array The field configuration.
	 */
	public function get_groups_select_config() {
		return array(
			'option_code'              => 'MCLISTGROUPS',
			'label'                    => esc_html_x( 'Groups', 'Mailchimp', 'uncanny-automator' ),
			'input_type'               => 'select',
			'supports_multiple_values' => true,
			'supports_custom_value'    => false,
			'required'                 => false,
			'options'                  => array(),
			'remote_data'              => $this->helpers->remote_data_parent_config( 'groups', array( 'MCLIST' ) ),
		);
	}

	/**
	 * Get merge fields repeater configuration.
	 *
	 * Rows are populated via AJAX when the audience (MCLIST) changes.
	 *
	 * @return array The field configuration.
	 */
	public function get_merge_fields_repeater_config() {
		return array(
			'option_code'       => 'MERGE_FIELDS',
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'label'             => esc_html_x( 'Merge fields', 'Mailchimp', 'uncanny-automator' ),
			'description'       => '',
			'required'          => false,
			'fields'            => array(
				array(
					'option_code' => 'FIELD_NAME',
					'label'       => esc_html_x( 'Field', 'Mailchimp', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
					'read_only'   => true,
				),
				array(
					'option_code' => 'FIELD_VALUE',
					'label'       => esc_html_x( 'Value', 'Mailchimp', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
					'tokens'      => true,
				),
			),
			'remote_data'       => $this->helpers->remote_data_with_mapping_column(
				$this->helpers->remote_data_parent_config( 'merge_field_rows', array( 'MCLIST' ) ),
				'FIELD_NAME'
			),
			'add_row_button'    => esc_html_x( 'Add pair', 'Mailchimp', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove pair', 'Mailchimp', 'uncanny-automator' ),
			'hide_actions'      => true,
		);
	}

	/**
	 * Check if double opt-in is enabled.
	 *
	 * @return bool True if double opt-in is enabled.
	 */
	public function is_double_optin_enabled() {
		return $this->is_affirmative_meta( 'MCDOUBLEOPTIN' );
	}

	/**
	 * Check if update existing is enabled.
	 *
	 * @return bool True if update existing is enabled.
	 */
	public function is_update_existing_enabled() {
		return $this->is_affirmative_meta( 'MCUPDATEEXISTING' );
	}

	/**
	 * Check if member should be deleted (vs just unsubscribed).
	 *
	 * @return bool True if member should be deleted.
	 */
	public function should_delete_member() {
		return $this->is_affirmative_meta( 'MCDELETEMEMBER' );
	}

	/**
	 * Check whether a yes/no select meta parses as affirmative.
	 *
	 * Recipes built before the 7.3.0 migration stored these selects as
	 * 'yes'/'no' (legacy Mailchimp_Helpers::get_double_opt_in); migrated
	 * selects store 'true'/'false'. Both vocabularies must parse, or every
	 * pre-7.3.0 recipe silently flips its setting to "No".
	 *
	 * @param string $meta_key The option code to check.
	 *
	 * @return bool True if the stored value is affirmative.
	 */
	private function is_affirmative_meta( $meta_key ) {
		return in_array( $this->get_parsed_meta_value( $meta_key ), array( 'true', 'yes' ), true );
	}

	/**
	 * Get change groups value from parsed data.
	 *
	 * @return string The change groups setting (replace-all, add-only, replace-matching).
	 */
	public function get_change_groups_from_parsed() {
		return sanitize_text_field( $this->get_parsed_meta_value( 'MCCHANGEGROUPS' ) );
	}

	/**
	 * Get groups list from parsed data.
	 *
	 * @return string The groups list value.
	 */
	public function get_groups_from_parsed() {
		return $this->get_parsed_meta_value( 'MCLISTGROUPS' );
	}

	/**
	 * Get merge fields from parsed data.
	 *
	 * Transforms repeater format [{FIELD_NAME, FIELD_VALUE}, ...] to API format.
	 * Handles ADDRESS fields specially by grouping subfields (addr1, city, state, zip, country).
	 * Skips fields with empty values to avoid overwriting existing data.
	 *
	 * @return array The merge fields in API format.
	 * @throws \Exception If address field is partially filled (missing required subfields).
	 */
	public function get_merge_fields_from_parsed() {
		$fields = $this->get_parsed_meta_value( 'MERGE_FIELDS' );

		// Normalize to array.
		if ( is_string( $fields ) ) {
			$fields = json_decode( $fields, true );
		}

		if ( ! is_array( $fields ) ) {
			return array();
		}

		$merge        = array();
		$addr_buckets = array();

		// Split out address parts vs. regular fields.
		foreach ( $fields as $field ) {
			$name  = $field['FIELD_NAME'] ?? '';
			$value = $field['FIELD_VALUE'] ?? '';

			// Check if this is an address subfield (e.g., ADDRESS_addr1, ADDRESS_city).
			if ( preg_match( '/^(.+?)_(addr1|addr2|city|state|zip|country)$/', $name, $match ) ) {
				list( , $tag, $part )          = $match;
				$addr_buckets[ $tag ][ $part ] = $value;
			} else {
				// Only include non-empty scalar values.
				// Empty strings should not overwrite existing data.
				if ( is_array( $value ) ) {
					$merge[ $name ] = $value;
				} elseif ( '' !== (string) $value ) {
					$merge[ $name ] = $value;
				}
			}
		}

		// Validate and emit address groups.
		foreach ( $addr_buckets as $tag => $parts ) {
			// Check if any subfields have non-empty values.
			$has_any = count(
				array_filter(
					$parts,
					function ( $v ) {
						return '' !== $v;
					}
				)
			) > 0;

			if ( $has_any ) {
				// Must have addr1, city, state, and zip. Subfields addr2 and country are optional.
				foreach ( array( 'addr1', 'city', 'state', 'zip' ) as $key ) {
					if ( empty( $parts[ $key ] ) ) {
						throw new \Exception(
							sprintf(
								/* translators: 1: Address field name, 2: Missing subfield name */
								esc_html_x( 'Address field "%1$s" is partially filled. Missing or empty subfield "%2$s".', 'Mailchimp', 'uncanny-automator' ),
								esc_html( $tag ),
								esc_html( $key )
							)
						);
					}
				}
				$merge[ $tag ] = $parts;
			}
			// Else: All subfields are empty, omit entirely.
		}

		return $merge;
	}
}
