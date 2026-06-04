<?php

namespace Uncanny_Automator\Integrations\Groundhogg;

/**
 * Class GH_CREATE_UPDATE_CONTACT
 *
 * @package Uncanny_Automator\Integrations\Groundhogg
 *
 * @property Groundhogg_Helpers $item_helpers
 */
class GH_CREATE_UPDATE_CONTACT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GH' );
		$this->set_action_code( 'GH_CREATE_UPDATE_CONTACT' );
		$this->set_action_meta( 'GH_CONTACT' );
		$this->set_requires_user( false );
		/* translators: %1$s is the contact email */
		$this->set_sentence( sprintf( esc_html_x( 'Create or update {{a contact:%1$s}}', 'Groundhogg', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create or update {{a contact}}', 'Groundhogg', 'uncanny-automator' ) );
	}

	/**
	 * Define options for the action.
	 *
	 * @return array
	 */
	public function options() {
		$location_visibility = array(
			'default_state'    => 'hidden',
			'visibility_rules' => array(
				array(
					'operator'             => 'AND',
					'rule_conditions'      => array(
						array(
							'option_code' => 'INCLUDE_LOCATION',
							'compare'     => '==',
							'value'       => true,
						),
					),
					'resulting_visibility' => 'show',
				),
			),
		);

		return array(
			// Core contact fields.
			array(
				'option_code' => $this->get_action_meta(),
				'input_type'  => 'email',
				'label'       => esc_html_x( 'Email', 'Groundhogg', 'uncanny-automator' ),
				'required'    => true,
			),
			array(
				'option_code' => 'first_name',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'First name', 'Groundhogg', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'last_name',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Last name', 'Groundhogg', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'mobile_phone',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Mobile phone', 'Groundhogg', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code' => 'primary_phone',
				'input_type'  => 'text',
				'label'       => esc_html_x( 'Primary phone', 'Groundhogg', 'uncanny-automator' ),
				'required'    => false,
			),
			array(
				'option_code'           => 'optin_status',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Opt-in status', 'Groundhogg', 'uncanny-automator' ),
				'required'              => false,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'optin_statuses' ),
			),

			// Location toggle.
			array(
				'option_code'           => 'INCLUDE_LOCATION',
				'input_type'            => 'checkbox',
				'label'                 => esc_html_x( 'Include location', 'Groundhogg', 'uncanny-automator' ),
				'is_toggle'             => true,
				'required'              => false,
				'description'           => esc_html_x( 'Set address fields on the contact.', 'Groundhogg', 'uncanny-automator' ),
				'exclude_default_token' => true,
			),

			// Location fields — visible when toggle is on.
			array(
				'option_code'        => 'street_address_1',
				'input_type'         => 'text',
				'label'              => esc_html_x( 'Address line 1', 'Groundhogg', 'uncanny-automator' ),
				'required'           => false,
				'dynamic_visibility' => $location_visibility,
			),
			array(
				'option_code'        => 'street_address_2',
				'input_type'         => 'text',
				'label'              => esc_html_x( 'Address line 2', 'Groundhogg', 'uncanny-automator' ),
				'required'           => false,
				'dynamic_visibility' => $location_visibility,
			),
			array(
				'option_code'        => 'city',
				'input_type'         => 'text',
				'label'              => esc_html_x( 'City', 'Groundhogg', 'uncanny-automator' ),
				'required'           => false,
				'dynamic_visibility' => $location_visibility,
			),
			array(
				'option_code'        => 'region',
				'input_type'         => 'text',
				'label'              => esc_html_x( 'State / Province', 'Groundhogg', 'uncanny-automator' ),
				'required'           => false,
				'dynamic_visibility' => $location_visibility,
			),
			array(
				'option_code'        => 'postal_zip',
				'input_type'         => 'text',
				'label'              => esc_html_x( 'Postal / Zip code', 'Groundhogg', 'uncanny-automator' ),
				'required'           => false,
				'dynamic_visibility' => $location_visibility,
			),
			array(
				'option_code'           => 'country',
				'input_type'            => 'select',
				'label'                 => esc_html_x( 'Country', 'Groundhogg', 'uncanny-automator' ),
				'required'              => false,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'countries' ),
				'dynamic_visibility'    => $location_visibility,
			),

			// Additional emails repeater.
			array(
				'option_code'           => 'ADDITIONAL_EMAILS',
				'input_type'            => 'repeater',
				'label'                 => esc_html_x( 'Additional emails', 'Groundhogg', 'uncanny-automator' ),
				'required'              => false,
				'relevant_tokens'       => array(),
				'exclude_default_token' => true,
				'add_row_button'        => esc_html_x( 'Add email', 'Groundhogg', 'uncanny-automator' ),
				'remove_row_button'     => esc_html_x( 'Remove email', 'Groundhogg', 'uncanny-automator' ),
				'fields'                => array(
					array(
						'option_code' => 'ALT_EMAIL',
						'label'       => esc_html_x( 'Email address', 'Groundhogg', 'uncanny-automator' ),
						'input_type'  => 'email',
						'required'    => true,
					),
				),
			),

			// Additional phones repeater.
			array(
				'option_code'           => 'ADDITIONAL_PHONES',
				'input_type'            => 'repeater',
				'label'                 => esc_html_x( 'Additional phone numbers', 'Groundhogg', 'uncanny-automator' ),
				'required'              => false,
				'relevant_tokens'       => array(),
				'exclude_default_token' => true,
				'add_row_button'        => esc_html_x( 'Add phone number', 'Groundhogg', 'uncanny-automator' ),
				'remove_row_button'     => esc_html_x( 'Remove phone number', 'Groundhogg', 'uncanny-automator' ),
				'fields'                => array(
					array(
						'option_code'           => 'PHONE_TYPE',
						'label'                 => esc_html_x( 'Type', 'Groundhogg', 'uncanny-automator' ),
						'input_type'            => 'select',
						'required'              => true,
						'supports_custom_value' => true,
						'options'               => array(
							array(
								'value' => 'mobile',
								'text'  => esc_html_x( 'Mobile', 'Groundhogg', 'uncanny-automator' ),
							),
							array(
								'value' => 'home',
								'text'  => esc_html_x( 'Home', 'Groundhogg', 'uncanny-automator' ),
							),
							array(
								'value' => 'work',
								'text'  => esc_html_x( 'Work', 'Groundhogg', 'uncanny-automator' ),
							),
						),
					),
					array(
						'option_code' => 'PHONE_NUMBER',
						'label'       => esc_html_x( 'Phone number', 'Groundhogg', 'uncanny-automator' ),
						'input_type'  => 'text',
						'required'    => true,
					),
				),
			),

			// Custom meta repeater.
			array(
				'option_code'           => 'CUSTOM_META',
				'input_type'            => 'repeater',
				'label'                 => esc_html_x( 'Meta', 'Groundhogg', 'uncanny-automator' ),
				'required'              => false,
				'relevant_tokens'       => array(),
				'exclude_default_token' => true,
				'add_row_button'        => esc_html_x( 'Add meta', 'Groundhogg', 'uncanny-automator' ),
				'remove_row_button'     => esc_html_x( 'Remove meta', 'Groundhogg', 'uncanny-automator' ),
				'fields'                => array(
					array(
						'option_code'           => 'META_KEY',
						'label'                 => esc_html_x( 'Key', 'Groundhogg', 'uncanny-automator' ),
						'input_type'            => 'select',
						'required'              => true,
						'supports_custom_value' => true,
						'options_show_id'       => false,
						'options'               => $this->item_helpers->get_meta_key_options(),
					),
					array(
						'option_code' => 'META_VALUE',
						'label'       => esc_html_x( 'Value', 'Groundhogg', 'uncanny-automator' ),
						'input_type'  => 'text',
						'required'    => false,
					),
				),
			),

			// Custom properties (transposed repeater — fields loaded via AJAX).
			array(
				'option_code'           => 'CUSTOM_PROPERTIES',
				'input_type'            => 'repeater',
				'label'                 => esc_html_x( 'Custom properties', 'Groundhogg', 'uncanny-automator' ),
				'required'              => true,
				'relevant_tokens'       => array(),
				'exclude_default_token' => true,
				'hide_actions'          => true,
				'hide_header'           => true,
				'layout'                => 'transposed',
				'fields'                => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'custom_properties' ),
				'description'           => esc_html_x( 'Empty fields are skipped when updating an existing contact. Enter [DELETE] to clear a field value.', 'Groundhogg', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception If the email is empty.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$contact_email = sanitize_email( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( ! is_email( $contact_email ) ) {
			throw new \Exception( esc_html_x( 'A valid email address is required.', 'Groundhogg', 'uncanny-automator' ) );
		}

		// Core DB fields — skip empty, [DELETE] sends empty string.
		$contact_details = array( 'email' => $contact_email );

		foreach ( array( 'first_name', 'last_name', 'optin_status' ) as $key ) {
			$value = sanitize_text_field( $parsed[ $key ] ?? '' );
			if ( '' !== $value ) {
				$contact_details[ $key ] = '[DELETE]' === $value ? '' : $value;
			}
		}

		$contact = $this->item_helpers->new_contact( $contact_email );

		if ( true === $contact->exists() ) {
			$contact->update( $contact_details );
		} else {
			$contact->create( $contact_details );
		}

		// Phone fields are contact meta — not core DB columns.
		$this->set_contact_meta( $contact, 'mobile_phone', $parsed['mobile_phone'] ?? '' );
		$this->set_contact_meta( $contact, 'primary_phone', $parsed['primary_phone'] ?? '' );

		// Location fields — only process if toggle is enabled.
		if ( ! empty( $parsed['INCLUDE_LOCATION'] ) ) {
			$location_keys = array( 'street_address_1', 'street_address_2', 'city', 'region', 'postal_zip', 'country' );
			foreach ( $location_keys as $key ) {
				$this->set_contact_meta( $contact, $key, $parsed[ $key ] ?? '' );
			}
		}

		// Additional emails — stored as flat array of email strings.
		$additional_emails = $this->parse_repeater_emails( $parsed['ADDITIONAL_EMAILS'] ?? '' );
		if ( ! empty( $additional_emails ) ) {
			$existing = $contact->get_meta( 'alternate_emails' ) ? $contact->get_meta( 'alternate_emails' ) : array();
			$contact->update_meta( 'alternate_emails', array_values( array_unique( array_merge( $existing, $additional_emails ) ) ) );
		}

		// Additional phones — stored as array of [type, number] pairs.
		$additional_phones = $this->parse_repeater_phones( $parsed['ADDITIONAL_PHONES'] ?? '' );
		if ( ! empty( $additional_phones ) ) {
			$existing = $contact->get_meta( 'alternate_phones' ) ? $contact->get_meta( 'alternate_phones' ) : array();
			$merged   = array_merge( $existing, $additional_phones );
			// Deduplicate by phone number.
			$seen   = array();
			$unique = array();
			foreach ( $merged as $entry ) {
				if ( ! in_array( $entry[1], $seen, true ) ) {
					$seen[]   = $entry[1];
					$unique[] = $entry;
				}
			}
			$contact->update_meta( 'alternate_phones', array_values( $unique ) );
		}

		// Custom meta fields.
		$custom_meta = $this->parse_repeater_meta( $parsed['CUSTOM_META'] ?? '' );
		foreach ( $custom_meta as $key => $value ) {
			$this->set_contact_meta( $contact, $key, $value );
		}

		// Custom properties (transposed repeater — single row of field_name => value pairs).
		$properties = json_decode( $parsed['CUSTOM_PROPERTIES'] ?? '', true );
		$properties = $properties[0] ?? array();

		foreach ( $properties as $meta_key => $meta_value ) {
			// Skip _readable companion keys added by the framework.
			if ( str_ends_with( $meta_key, '_readable' ) ) {
				continue;
			}

			$meta_key = sanitize_text_field( $meta_key );

			if ( ! empty( $meta_key ) ) {
				$this->set_contact_meta( $contact, $meta_key, $meta_value );
			}
		}

		return true;
	}

	/**
	 * Parse custom meta fields from repeater field JSON.
	 *
	 * @param string $json The repeater JSON string.
	 *
	 * @return array Associative array of meta_key => meta_value.
	 */
	private function parse_repeater_meta( $json ) {
		$rows = json_decode( $json, true );
		$meta = array();

		if ( ! is_array( $rows ) ) {
			return $meta;
		}

		foreach ( $rows as $row ) {
			$key = sanitize_text_field( $row['META_KEY'] ?? '' );
			if ( ! empty( $key ) ) {
				$meta[ $key ] = sanitize_text_field( $row['META_VALUE'] ?? '' );
			}
		}

		return $meta;
	}

	/**
	 * Parse additional emails from repeater field JSON.
	 *
	 * @param string $json The repeater JSON string.
	 *
	 * @return array Flat array of valid email strings.
	 */
	private function parse_repeater_emails( $json ) {
		$rows   = json_decode( $json, true );
		$emails = array();

		if ( ! is_array( $rows ) ) {
			return $emails;
		}

		foreach ( $rows as $row ) {
			$email = sanitize_email( $row['ALT_EMAIL'] ?? '' );
			if ( is_email( $email ) ) {
				$emails[] = $email;
			}
		}

		return $emails;
	}

	/**
	 * Parse additional phones from repeater field JSON.
	 *
	 * @param string $json The repeater JSON string.
	 *
	 * @return array Array of [type, number] pairs.
	 */
	private function parse_repeater_phones( $json ) {
		$rows   = json_decode( $json, true );
		$phones = array();

		if ( ! is_array( $rows ) ) {
			return $phones;
		}

		foreach ( $rows as $row ) {
			$type   = sanitize_text_field( $row['PHONE_TYPE'] ?? 'mobile' );
			$number = sanitize_text_field( $row['PHONE_NUMBER'] ?? '' );
			if ( ! empty( $number ) ) {
				$phones[] = array( $type, $number );
			}
		}

		return $phones;
	}

	/**
	 * Safely update or delete a contact meta key.
	 *
	 * Skips empty values to avoid overwriting existing data.
	 * Deletes the meta key if [DELETE] is passed.
	 * Handles arrays for multi-select fields.
	 *
	 * @param object       $contact The Groundhogg contact.
	 * @param string       $key     The meta key.
	 * @param string|array $value   The meta value.
	 *
	 * @return void
	 */
	private function set_contact_meta( $contact, $key, $value ) {
		// Multi-select fields send arrays.
		if ( is_array( $value ) ) {
			$value = array_map( 'sanitize_text_field', $value );
			$value = array_filter(
				$value,
				function ( $v ) {
					return '' !== $v;
				}
			);

			if ( empty( $value ) ) {
				return;
			}

			$contact->update_meta( $key, $value );
			return;
		}

		$value = sanitize_text_field( $value );

		if ( '' === $value ) {
			return;
		}

		if ( '[DELETE]' === $value ) {
			$contact->delete_meta( $key );
			return;
		}

		$contact->update_meta( $key, $value );
	}
}
