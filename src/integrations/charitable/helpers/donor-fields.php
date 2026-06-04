<?php

namespace Uncanny_Automator\Integrations\Charitable;

use Charitable_Donor;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Shared field definitions and value handling for the donor create/update actions.
 *
 * Charitable Pro stores donor data across three tables:
 *   - wp_charitable_donors    : core columns (email, first_name, last_name, ...)
 *   - wp_charitable_donormeta : profile fields (phone, company, title, ...) and custom fields
 *   - wp_charitable_donor_fields + donormeta : numbered custom fields
 *
 * Charitable_Donor::update() routes core fields to the donors table and
 * non-core profile fields to donormeta. Address is handled separately via
 * update_primary_address(); arbitrary key/value pairs go through update_donor_meta().
 */
class Donor_Fields {

	/**
	 * Regular donor profile fields (excluding address and custom fields).
	 *
	 * @param string             $email_option_code The option code to use for the email field (matches action_meta on create).
	 * @param bool               $is_create         When true, email + first name are required.
	 * @param Abstract_Helpers   $helpers           Integration helper for select option lists.
	 *
	 * @return array
	 */
	public static function regular_fields( $email_option_code, $is_create, $helpers ) {
		return array(
			array(
				'option_code' => $email_option_code,
				'label'       => esc_html_x( 'Email', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => $is_create,
			),
			array(
				'option_code' => 'DONOR_FIRST_NAME',
				'label'       => esc_html_x( 'First name', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => $is_create,
			),
			array(
				'option_code' => 'DONOR_LAST_NAME',
				'label'       => esc_html_x( 'Last name', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_MIDDLE_NAME',
				'label'       => esc_html_x( 'Middle name', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code'           => 'DONOR_PREFIX',
				'label'                 => esc_html_x( 'Prefix', 'Charitable', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'remote_data'           => $helpers->remote_data_load_config( 'donor_prefixes_strict' ),
			),
			array(
				'option_code'           => 'DONOR_SUFFIX',
				'label'                 => esc_html_x( 'Suffix', 'Charitable', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'remote_data'           => $helpers->remote_data_load_config( 'donor_suffixes_strict' ),
			),
			array(
				'option_code' => 'DONOR_TITLE',
				'label'       => esc_html_x( 'Title', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_COMPANY',
				'label'       => esc_html_x( 'Company', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_PHONE',
				'label'       => esc_html_x( 'Phone', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_BIRTHDAY',
				'label'       => esc_html_x( 'Birthday', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_html_x( 'Format: YYYY-MM-DD', 'Charitable', 'uncanny-automator' ),
			),
			array(
				'option_code'           => 'DONOR_PRIMARY_LANGUAGE',
				'label'                 => esc_html_x( 'Primary language', 'Charitable', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'remote_data'           => $helpers->remote_data_load_config( 'donor_languages_strict' ),
			),
			array(
				'option_code' => 'DONOR_CONTACT_CONSENT',
				'label'       => esc_html_x( 'Contact consent', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'required'    => false,
				'description' => esc_html_x( 'Whether the donor consents to being contacted by email.', 'Charitable', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Address fields.
	 *
	 * @return array
	 */
	public static function address_fields() {
		return array(
			array(
				'option_code' => 'DONOR_ADDRESS',
				'label'       => esc_html_x( 'Address line 1', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_ADDRESS_2',
				'label'       => esc_html_x( 'Address line 2', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_CITY',
				'label'       => esc_html_x( 'City', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_STATE',
				'label'       => esc_html_x( 'State/Province', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_POSTCODE',
				'label'       => esc_html_x( 'Postcode/ZIP', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DONOR_COUNTRY',
				'label'       => esc_html_x( 'Country', 'Charitable', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_html_x( 'Two-letter country code, e.g. US, CA, GB.', 'Charitable', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Custom fields repeater for Charitable global donor custom fields.
	 *
	 * @return array
	 */
	public static function custom_fields_repeater() {

		$options = array();

		if ( function_exists( 'charitable_get_table' ) ) {
			$fields_db     = charitable_get_table( 'fields' );
			$custom_fields = $fields_db ? $fields_db->get_all_global_fields( 'meta' ) : array();
			foreach ( (array) $custom_fields as $custom_field ) {
				$options[] = array(
					'text'  => $custom_field->field_name,
					'value' => $custom_field->field_id,
				);
			}
		}

		return array(
			'option_code'       => 'DONOR_CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'label'             => esc_html_x( 'Custom fields', 'Charitable', 'uncanny-automator' ),
			'description'       => esc_html_x( 'Set values for global donor custom fields defined in Charitable.', 'Charitable', 'uncanny-automator' ),
			'required'          => false,
			'relevant_tokens'   => array(),
			'add_row_button'    => esc_html_x( 'Add field', 'Charitable', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove field', 'Charitable', 'uncanny-automator' ),
			'fields'            => array(
				array(
					'option_code'           => 'CUSTOM_FIELD_KEY',
					'label'                 => esc_html_x( 'Custom field', 'Charitable', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_tokens'       => false,
					'supports_custom_value' => false,
					'required'              => true,
					'read_only'             => false,
					'options'               => $options,
				),
				array(
					'option_code' => 'CUSTOM_FIELD_VALUE',
					'label'       => esc_html_x( 'Value', 'Charitable', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
			),
		);
	}

	/**
	 * Map regular profile fields onto a Charitable_Donor instance via Charitable_Donor::update().
	 *
	 * Mirrors the payload shape that Charitable's own admin sends to the same method
	 * (Charitable_Donors::ajax_donor_update()): bare snake_case keys matching
	 * charitable_donor_get_core_fields() field IDs. Only non-empty values are
	 * included so unprovided fields are left untouched (this is an update action,
	 * not a replace-all).
	 *
	 * contact_consent is updated via a separate direct table call (not through
	 * Charitable_Donor::update()) because Charitable's admin does the same workaround —
	 * Donor::update() does not reliably persist contact_consent on its own.
	 *
	 * @param \Charitable_Donor $donor
	 * @param array             $parsed
	 *
	 * @return void
	 */
	public static function apply_profile_fields( $donor, $parsed ) {

		$mapping = array(
			'DONOR_EMAIL'            => 'email',
			'DONOR_FIRST_NAME'       => 'first_name',
			'DONOR_LAST_NAME'        => 'last_name',
			'DONOR_MIDDLE_NAME'      => 'middle_name',
			'DONOR_PREFIX'           => 'prefix',
			'DONOR_SUFFIX'           => 'suffix',
			'DONOR_TITLE'            => 'title',
			'DONOR_COMPANY'          => 'company',
			'DONOR_PHONE'            => 'phone',
			'DONOR_BIRTHDAY'         => 'birthday',
			'DONOR_PRIMARY_LANGUAGE' => 'primary_language',
		);

		$update_data = array();

		foreach ( $mapping as $option_code => $donor_key ) {
			$value = $parsed[ $option_code ] ?? '';
			if ( '' === $value ) {
				continue;
			}
			if ( 'email' === $donor_key ) {
				$value = sanitize_email( $value );
				if ( ! is_email( $value ) ) {
					continue;
				}
			} else {
				$value = sanitize_text_field( $value );
			}
			$update_data[ $donor_key ] = $value;
		}

		if ( ! empty( $update_data ) ) {
			$donor->update( $update_data );
		}

		// Charitable_Donor::update() does not reliably persist contact_consent to the
		// donors table, so update it explicitly the same way Charitable's admin does.
		if ( array_key_exists( 'DONOR_CONTACT_CONSENT', $parsed ) && '' !== $parsed['DONOR_CONTACT_CONSENT'] && function_exists( 'charitable_get_table' ) ) {
			$consent = filter_var( $parsed['DONOR_CONTACT_CONSENT'], FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
			charitable_get_table( 'donors' )->update( $donor->donor_id, array( 'contact_consent' => $consent ), 'donor_id' );
		}
	}

	/**
	 * Apply the donor's primary address if any address field was provided.
	 *
	 * @param \Charitable_Donor $donor
	 * @param array             $parsed
	 *
	 * @return void
	 */
	public static function apply_address( $donor, $parsed ) {

		if ( ! method_exists( $donor, 'update_primary_address' ) ) {
			return;
		}

		$mapping = array(
			'DONOR_ADDRESS'   => 'address',
			'DONOR_ADDRESS_2' => 'address_2',
			'DONOR_CITY'      => 'city',
			'DONOR_STATE'     => 'state',
			'DONOR_POSTCODE'  => 'zip',
			'DONOR_COUNTRY'   => 'country',
		);

		$address = array();
		foreach ( $mapping as $option_code => $address_key ) {
			$value = $parsed[ $option_code ] ?? '';
			if ( '' === $value ) {
				continue;
			}
			$address[ $address_key ] = sanitize_text_field( $value );
		}

		if ( empty( $address ) ) {
			return;
		}

		$donor->update_primary_address( $address );
	}

	/**
	 * Persist the custom fields repeater entries.
	 *
	 * Charitable stores donor custom fields in two coupled rows: a value row in
	 * wp_charitable_donormeta keyed `custom_field_{field_id}`, and a link row in
	 * wp_charitable_donor_fields mapping field_id <-> donormeta_id <-> donor_id.
	 * Charitable_Donor::update_donor_meta() only writes the donormeta row, so
	 * the value never surfaces on the donor profile. We mirror the two-step
	 * write performed by Charitable's own donor save flow
	 * (class-charitable-donor.php:1246-1309).
	 *
	 * @param \Charitable_Donor $donor
	 * @param int donor_id
	 * @param array             $action_data
	 * @param int               $recipe_id
	 * @param int               $user_id
	 * @param array             $args
	 *
	 * @return void
	 */
	public static function apply_custom_fields( $donor, $donor_id, $action_data, $recipe_id, $user_id, $args ) {
		if ( ! function_exists( 'charitable_get_table' ) ) {
			return;
		}

		$raw = $action_data['meta']['DONOR_CUSTOM_FIELDS'] ?? '';
		if ( empty( $raw ) ) {
			return;
		}

		$rows = json_decode( $raw, true );
		if ( ! is_array( $rows ) ) {
			return;
		}

		$donormeta_db    = charitable_get_table( 'donormeta' );
		$donor_fields_db = charitable_get_table( 'donor_fields' );
		if ( ! $donormeta_db || ! $donor_fields_db ) {
			return;
		}

		global $wpdb;

		foreach ( $rows as $row ) {

			$field_id = isset( $row['CUSTOM_FIELD_KEY'] )
				? (int) \Automator()->parse->text( $row['CUSTOM_FIELD_KEY'], $recipe_id, $user_id, $args )
				: 0;

			if ( $field_id <= 0 ) {
				continue;
			}

			$value = isset( $row['CUSTOM_FIELD_VALUE'] )
				? \Automator()->parse->text( $row['CUSTOM_FIELD_VALUE'], $recipe_id, $user_id, $args )
				: '';

			$meta_key   = 'custom_field_' . $donor_id . '_' . $field_id;
			$meta_value = is_array( $value ) ? maybe_serialize( $value ) : sanitize_text_field( $value );

			$existing_meta_id = (int) $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT meta_id FROM $wpdb->donormeta WHERE donor_id = %d AND meta_key = %s",
					$donor_id,
					$meta_key
				)
			);

			if ( 0 === $existing_meta_id ) {
				$donormeta_id = $donormeta_db->insert(
					array(
						'donor_id'   => $donor_id,
						'meta_key'   => $meta_key,
						'meta_value' => $meta_value,
					)
				);
			} else {
				$donormeta_id = $donormeta_db->update_meta_value_by_meta_id( $existing_meta_id, $meta_value );
			}

			if ( empty( $donormeta_id ) ) {
				continue;
			}

			$existing_link = $donor_fields_db->get_field_by_donor_id_field_id( $donor_id, $field_id );

			if ( ! empty( $existing_link ) ) {
				$existing_link = $existing_link[0];
				$donor_fields_db->update_donormeta_id_by_donor_id_field_id(
					array(
						'field_id'     => (int) $existing_link['field_id'],
						'donormeta_id' => (int) $donormeta_id,
						'donor_id'     => (int) $existing_link['donor_id'],
					)
				);
			} else {
				$donor_fields_db->insert(
					array(
						'donor_id'     => $donor_id,
						'field_id'     => $field_id,
						'donormeta_id' => (int) $donormeta_id,
					)
				);
			}
		}
	}
}
