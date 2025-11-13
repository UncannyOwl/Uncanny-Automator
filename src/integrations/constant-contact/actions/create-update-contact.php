<?php
namespace Uncanny_Automator\Integrations\Constant_Contact;

use Exception;

/**
 * Class Uncanny_Automator\Integrations\Constant_Contact\CREATE_CONTACT
 *
 * @package Uncanny_Automator
 *
 * @property Constant_Contact_App_Helpers $helpers
 * @property Constant_Contact_Api_Caller $api
 */
class CREATE_UPDATE_CONTACT extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CONSTANT_CONTACT' );
		$this->set_action_code( 'CREATE_UPDATE_CONTACT' );
		$this->set_action_meta( 'CC_CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/constant-contact/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Contact Email
				esc_attr_x( 'Create or update {{a contact:%1$s}}', 'Constant Contact', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Constant Contact', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$description = esc_html_x( 'Leave empty to ignore or use [DELETE] to remove from existing contact', 'Constant Contact', 'uncanny-automator' );

		return array(
			$this->helpers->get_email_config( $this->get_action_meta() ),
			array(
				'option_code' => 'LIST',
				'label'       => esc_html_x( 'List', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'required'    => true,
				'ajax'        => array(
					'event'    => 'on_load',
					'endpoint' => 'automator_constant_contact_list_memberships_get',
				),
			),
			array(
				'option_code' => 'FIRST_NAME',
				'label'       => esc_html_x( 'First name', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
				'description' => $description,
			),
			array(
				'option_code' => 'LAST_NAME',
				'label'       => esc_html_x( 'Last name', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
				'description' => $description,
			),
			array(
				'option_code' => 'JOB_TITLE',
				'label'       => esc_html_x( 'Job title', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
				'description' => $description,
			),
			array(
				'option_code' => 'COMPANY_NAME',
				'label'       => esc_html_x( 'Company name', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
				'description' => $description,
			),
			array(
				'option_code' => 'PHONE_NUMBER',
				'label'       => esc_html_x( 'Phone number', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
				'description' => $description,
			),
			array(
				'option_code' => 'ANNIVERSARY',
				'label'       => esc_html_x( 'Anniversary', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
				'description' => $description,
			),
			// Street address.
			array(
				'option_code'     => 'STREET_ADDRESS',
				'label'           => esc_html_x( 'Address', 'Constant Contact', 'uncanny-automator' ),
				'input_type'      => 'repeater',
				'layout'          => 'transposed',
				'hide_actions'    => true,
				'hide_header'     => true,
				'required'        => true,
				'relevant_tokens' => array(),
				'description'     => $description,
				'fields'          => array(
					array(
						'option_code'           => 'kind',
						'label'                 => esc_html_x( 'Address type', 'Constant Contact', 'uncanny-automator' ),
						'input_type'            => 'select',
						'required'              => true,
						'default'               => 'other',
						'supports_custom_value' => false,
						'options_show_id'       => false,
						'options'               => array(
							array(
								'value' => 'home',
								'text'  => esc_html_x( 'Home', 'Constant Contact', 'uncanny-automator' ),
							),
							array(
								'value' => 'work',
								'text'  => esc_html_x( 'Work', 'Constant Contact', 'uncanny-automator' ),
							),
							array(
								'value' => 'other',
								'text'  => esc_html_x( 'Other', 'Constant Contact', 'uncanny-automator' ),
							),
						),
					),
					array(
						'option_code'     => 'street',
						'label'           => esc_html_x( 'Street', 'Constant Contact', 'uncanny-automator' ),
						'input_type'      => 'text',
						'supports_tokens' => true,
						'required'        => false,
					),
					array(
						'option_code'     => 'city',
						'label'           => esc_html_x( 'City', 'Constant Contact', 'uncanny-automator' ),
						'input_type'      => 'text',
						'supports_tokens' => true,
						'required'        => false,
					),
					array(
						'option_code'     => 'state',
						'label'           => esc_html_x( 'State', 'Constant Contact', 'uncanny-automator' ),
						'input_type'      => 'text',
						'supports_tokens' => true,
						'required'        => false,
					),
					array(
						'option_code'     => 'postal_code',
						'label'           => esc_html_x( 'Postal code', 'Constant Contact', 'uncanny-automator' ),
						'input_type'      => 'text',
						'supports_tokens' => true,
						'required'        => false,
					),
					array(
						'option_code'     => 'country',
						'label'           => esc_html_x( 'Country', 'Constant Contact', 'uncanny-automator' ),
						'input_type'      => 'text',
						'supports_tokens' => true,
						'required'        => false,
					),
				),
			),
			// Custom fields - new transposed layout.
			array(
				'option_code'     => 'CUSTOM_FIELDS',
				'label'           => esc_html_x( 'Custom fields', 'Constant Contact', 'uncanny-automator' ),
				'input_type'      => 'repeater',
				'hide_actions'    => true,
				'hide_header'     => true,
				'required'        => true,
				'layout'          => 'transposed',
				'relevant_tokens' => array(),
				'fields'          => array(),
				'description'     => $description,
				'ajax'            => array(
					'event'    => 'on_load',
					'endpoint' => 'automator_constant_contact_get_custom_fields_repeater',
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 * @return void
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Get the email from the parsed data - throws an exception if invalid.
		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

		// Build the contact object completely on plugin side (without email).
		$contact = $this->build_contact_body( $parsed );

		// Send to endpoint with email as separate parameter.
		$this->api->api_request(
			array(
				'action'  => 'create_update_contact',
				'email'   => $email,
				'contact' => wp_json_encode( $contact ),
			),
			$action_data
		);

		return true;
	}

	/**
	 * Build the complete contact body for the API.
	 *
	 * @param array $parsed
	 *
	 * @return array
	 */
	private function build_contact_body( $parsed ) {

		// Start with required fields (email is sent separately).
		$contact = array(
			'list_memberships' => array( $parsed['LIST'] ),
		);

		// Handle optional text fields with [DELETE] support.
		$optional_fields = array(
			'first_name'   => 'FIRST_NAME',
			'last_name'    => 'LAST_NAME',
			'job_title'    => 'JOB_TITLE',
			'company_name' => 'COMPANY_NAME',
			'phone_number' => 'PHONE_NUMBER',
		);

		foreach ( $optional_fields as $api_key => $option_code ) {
			$value = trim( $parsed[ $option_code ] ?? '' );

			// Skip empty values.
			if ( empty( $value ) ) {
				continue;
			}

			// Handle [DELETE] - add as null to actively remove.
			if ( Constant_Contact_Custom_Fields::is_delete_value( $value ) ) {
				$contact[ $api_key ] = null;
				continue;
			}

			// Add the value.
			$contact[ $api_key ] = sanitize_text_field( $value );
		}

		// Handle anniversary field with [DELETE] support.
		$anniversary = trim( $parsed['ANNIVERSARY'] ?? '' );
		if ( ! empty( $anniversary ) ) {
			if ( Constant_Contact_Custom_Fields::is_delete_value( $anniversary ) ) {
				$contact['anniversary'] = null;
			} else {
				$timestamp = strtotime( sanitize_text_field( $anniversary ) );
				if ( false !== $timestamp ) {
					$contact['anniversary'] = gmdate( 'm/d/Y', $timestamp );
				}
			}
		}

		// Handle street address.
		$street_address = $this->build_street_address( $parsed['STREET_ADDRESS'] ?? '' );
		if ( false !== $street_address ) {
			$contact['street_address'] = $street_address;
		}

		// Handle custom fields with [DELETE] support.
		$custom_fields = Constant_Contact_Custom_Fields::process_fields_for_api( $parsed['CUSTOM_FIELDS'] ?? '', $this->helpers, $this->api );
		if ( ! empty( $custom_fields ) ) {
			$contact['custom_fields'] = $custom_fields;
		}

		return $contact;
	}

	/**
	 * Build the street address array from transposed repeater data.
	 *
	 * @param string $street_address Expects valid JSON string from transposed repeater.
	 *
	 * @return array|false Returns array or false to skip.
	 */
	private function build_street_address( $street_address ) {

		// If empty, return false to skip.
		if ( empty( $street_address ) ) {
			return false;
		}

		// Decode and validate JSON.
		$address = json_decode( $street_address, true );

		// If invalid JSON or empty array, return false.
		if ( null === $address || empty( $address ) ) {
			return false;
		}

		// Transposed repeater returns nested array - extract first element.
		if ( isset( $address[0] ) && is_array( $address[0] ) ) {
			$address = $address[0];
		}

		$built_address = array();
		$has_data      = false;

		// Get address type (kind) - required field with default.
		$kind = isset( $address['kind'] ) ? trim( $address['kind'] ) : 'other';
		// Validate kind is one of the allowed values.
		if ( ! in_array( $kind, array( 'home', 'work', 'other' ), true ) ) {
			$kind = 'other';
		}
		$built_address['kind'] = $kind;

		// Process each address field (street, city, state, postal_code, country).
		$address_fields = array( 'street', 'city', 'state', 'postal_code', 'country' );

		foreach ( $address_fields as $field ) {
			// Check if the field exists in the user input.
			if ( ! array_key_exists( $field, $address ) ) {
				// Key doesn't exist = omit (will keep existing value on update).
				continue;
			}

			$value = trim( $address[ $field ] );

			// If [DELETE] or empty, send empty string (will clear on API side).
			if ( Constant_Contact_Custom_Fields::is_delete_value( $value ) || empty( $value ) ) {
				$built_address[ $field ] = '';
				$has_data                = true;
				continue;
			}

			// Has actual value = add it.
			$built_address[ $field ] = sanitize_text_field( $value );
			$has_data                = true;
		}

		// Return address if we have data (kind is always present).
		if ( $has_data ) {
			return $built_address;
		}

		return false;
	}
}
