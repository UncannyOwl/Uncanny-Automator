<?php

namespace Uncanny_Automator\Integrations\M4IS;

/**
 * Class M4IS_HELPERS
 *
 * @package Uncanny_Automator
 */
class M4IS_HELPERS {

	/**
	 * M4IS_HELPERS constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get contact fields for select field.
	 *
	 * @return array
	 */
	public function get_contact_fields() {

		static $contact_fields = null;

		if ( is_null( $contact_fields ) ) {
			$contact_fields = array();
			$field_map      = memb_getContactFieldsMap();
			if ( ! empty( $field_map ) && is_array( $field_map ) ) {
				foreach ( $field_map as $key => $field ) {
					$contact_fields[] = array(
						'value' => $field,
						'text'  => $field,
					);
				}
			}
		}

		return $contact_fields;
	}

	/**
	 * Get email from parsed.
	 *
	 * @param  array $parsed
	 * @param  string $meta_key
	 * @return string
	 */
	public function get_email_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new \Exception( esc_html_x( 'Missing email', 'M4IS', 'uncanny-automator' ) );
		}

		$email = $this->validate_email( $parsed[ $meta_key ] );
		if ( ! $email ) {
			throw new \Exception( esc_html_x( 'Invalid email', 'M4IS', 'uncanny-automator' ) );
		}

		return $email;
	}

	/**
	 * Validate email.
	 *
	 * @param string $email
	 *
	 * @return mixed false || string
	 */
	public function validate_email( $email ) {

		if ( empty( $email ) ) {
			return false;
		}

		$email = sanitize_text_field( $email );
		if ( ! $email || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}

		return $email;
	}

	/**
	 * Update contact.
	 *
	 * @param string $email
	 * @param array $fields
	 *
	 * @return mixed \WP_Error || array or updated fields
	 */
	public function update_contact( $email, $fields ) {

		$fields = $this->validate_contact_fields( $fields );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$contact_id = $this->get_contact_id_by_email( $email );
		if ( empty( $contact_id ) ) {
			return new \WP_Error( 'invalid_contact', esc_html_x( 'Contact not found by email', 'M4IS', 'uncanny-automator' ) );
		}

		// Determine how we're going to update the contact.
		$user_id      = memb_getUserIdByContactId( $contact_id );
		$i2sdk_update = empty( $user_id );
		$response     = array();

		// No user ID means we're updating the contact via i2sdk.
		if ( $i2sdk_update ) {
			if ( ! $this->i2sdk() ) {
				return new \WP_Error( 'invalid_i2sdk', esc_html_x( 'Invalid connection to Keap', 'M4IS', 'uncanny-automator' ) );
			}
			// Update contact via Keap.
			$response = $this->i2sdk()->isdk->updateCon( $contact_id, $fields );
			if ( (int) $response !== (int) $contact_id ) {
				return new \WP_Error( 'invalid_response', esc_html_x( 'Invalid response from Keap', 'M4IS', 'uncanny-automator' ) );
			}

			return $fields;
		}

		// Update contact via Memberium API.
		foreach ( $fields as $field_name => $value ) {
			if ( ! empty( memb_setContactField( $field_name, $value, $contact_id ) ) ) {
				$response[ $field_name ] = $value;
			}
		}

		return $response;
	}

	/**
	 * Validate contact fields.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function validate_contact_fields( $fields ) {

		$contact_fields = array();

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return $contact_fields;
		}

		$field_map = memb_getContactFieldsMap();
		if ( empty( $field_map ) || ! is_array( $field_map ) ) {
			return $contact_fields;
		}

		foreach ( $fields as $field_name => $value ) {
			if ( ! in_array( $field_name, $field_map, true ) ) {
				continue;
			}
			$contact_fields[ $field_name ] = $value;
		}

		return $contact_fields;
	}

	/**
	 * Get contact ID by email.
	 *
	 * @param string $email
	 *
	 * @return mixed false || int
	 */
	public function get_contact_id_by_email( $email ) {

		$contact_id = 0;

		// First check if user exists in WP.
		$user_id = $this->get_user_id_by_email( $email );
		if ( ! empty( $user_id ) ) {
			$contact_id = memb_getContactIdByUserId( $user_id );
		}

		if ( ! empty( $contact_id ) ) {
			return (int) $contact_id;
		}

		// Retrieve contact ID by email from Keap.
		return $this->get_contact_id_by_email_from_keap( $email );
	}

	/**
	 * Retrieve contact ID by email from Keap.
	 *
	 * @param string $email
	 *
	 * @return mixed false || int
	 */
	public function get_contact_id_by_email_from_keap( $email ) {

		$email = $this->validate_email( $email );
		if ( ! $email ) {
			return false;
		}

		$i2sdk = $this->i2sdk();
		if ( $i2sdk ) {
			$data = $i2sdk->isdk->findByEmail( $email, array( 'Id' ) );
			if ( is_array( $data ) ) {
				return empty( $data[0]['Id'] ) ? false : (int) $data[0]['Id'];
			}
		}

		return false;
	}

	/**
	 * Get user ID by email.
	 *
	 * @param string $email
	 *
	 * @return int
	 */
	public function get_user_id_by_email( $email ) {
		$user = get_user_by( 'email', $email );
		return is_a( $user, 'WP_User' ) ? $user->ID : 0;
	}

	/**
	 * Get i2SDK object to make API calls.
	 *
	 * @return mixed false || \i2SDK
	 */
	public function i2sdk() {
		static $app = null;
		if ( is_null( $app ) ) {
			$app = array_key_exists( 'i2sdk', $GLOBALS ) ? $GLOBALS['i2sdk'] : false;
		}
		return $app;
	}
}
