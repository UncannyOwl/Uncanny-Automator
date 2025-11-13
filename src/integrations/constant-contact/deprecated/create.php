<?php
namespace Uncanny_Automator\Integrations\Constant_Contact;

use DateTime;
use Exception;

/**
 * Class Uncanny_Automator\Integrations\Constant_Contact\CREATE
 *
 * @deprecated Oct 2025 - with more robust field handling.
 *
 * @package Uncanny_Automator
 *
 * @property Constant_Contact_App_Helpers $helpers
 * @property Constant_Contact_Api_Caller $api
 */
class CREATE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CONSTANT_CONTACT' );
		$this->set_action_code( 'ADD_UPDATE_CONTACT' );
		$this->set_action_meta( 'CC_CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/constant-contact/' ) );
		$this->set_requires_user( false );
		$this->set_is_deprecated( true );
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
			),
			array(
				'option_code' => 'LAST_NAME',
				'label'       => esc_html_x( 'Last name', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'JOB_TITLE',
				'label'       => esc_html_x( 'Job title', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'COMPANY_NAME',
				'label'       => esc_html_x( 'Company name', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'PHONE_NUMBER',
				'label'       => esc_html_x( 'Phone number', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'ANNIVERSARY',
				'label'       => esc_html_x( 'Anniversary', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			// Street address.
			array(
				'option_code'     => 'STREET_ADDRESS',
				'label'           => esc_html_x( 'Address', 'Constant Contact', 'uncanny-automator' ),
				'input_type'      => 'repeater',
				'relevant_tokens' => array(),
				'fields'          => array(
					array(
						'option_code'           => 'KEY',
						'label'                 => esc_html_x( 'Type', 'Constant Contact', 'uncanny-automator' ),
						'input_type'            => 'select',
						'options'               => array(
							array(
								'text'  => esc_html_x( 'Street', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'street',
							),
							array(
								'text'  => esc_html_x( 'City', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'city',
							),
							array(
								'text'  => esc_html_x( 'State', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'state',
							),
							array(
								'text'  => esc_html_x( 'Postal code', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'postal_code',
							),
							array(
								'text'  => esc_html_x( 'Country', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'country',
							),
						),
						'supports_custom_value' => false,
						'options_show_id'       => false,
					),
					array(
						'option_code' => 'VALUE',
						'label'       => esc_html_x( 'Value', 'Constant Contact', 'uncanny-automator' ),
						'input_type'  => 'text',
					),
				),
			),
			// Custom fields.
			array(
				'hide_actions'    => true,
				'option_code'     => 'CUSTOM_FIELDS',
				'label'           => esc_html_x( 'Custom fields', 'Constant Contact', 'uncanny-automator' ),
				'input_type'      => 'repeater',
				'relevant_tokens' => array(),
				'ajax'            => array(
					'event'          => 'on_load',
					'endpoint'       => 'automator_constant_contact_contact_fields_get',
					'mapping_column' => 'CUSTOM_FIELD_ID',
				),
				'fields'          => array(
					array(
						'option_code' => 'CUSTOM_FIELD_ID',
						'label'       => esc_html_x( 'ID', 'Constant Contact', 'uncanny-automator' ),
						'input_type'  => 'text',
						'read_only'   => true,
					),
					array(
						'option_code' => 'CUSTOM_FIELD_NAME',
						'label'       => esc_html_x( 'Field', 'Constant Contact', 'uncanny-automator' ),
						'input_type'  => 'text',
						'read_only'   => true,
					),
					array(
						'option_code' => 'CUSTOM_FIELD_VALUE',
						'label'       => esc_html_x( 'Value', 'Constant Contact', 'uncanny-automator' ),
						'input_type'  => 'text',
					),
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

		// Build the body with required fields.
		$body = array(
			'email_address' => $email,
			'list'          => $parsed['LIST'],
		);

		// Add optional fields only if they have values.
		$optional_fields = array(
			'first_name'   => sanitize_text_field( $parsed['FIRST_NAME'] ?? '' ),
			'last_name'    => sanitize_text_field( $parsed['LAST_NAME'] ?? '' ),
			'job_title'    => sanitize_text_field( $parsed['JOB_TITLE'] ?? '' ),
			'company_name' => sanitize_text_field( $parsed['COMPANY_NAME'] ?? '' ),
			'phone_number' => sanitize_text_field( $parsed['PHONE_NUMBER'] ?? '' ),
		);

		foreach ( $optional_fields as $key => $value ) {
			if ( ! empty( $value ) ) {
				$body[ $key ] = $value;
			}
		}

		// Handle anniversary if provided.
		if ( ! empty( $parsed['ANNIVERSARY'] ) ) {
			$anniversary = wp_strip_all_tags( $parsed['ANNIVERSARY'] );
			$timestamp   = strtotime( sanitize_text_field( $anniversary ) );
			if ( false !== $timestamp ) {
				$body['anniversary'] = gmdate( 'm/d/Y', $timestamp );
			}
		}

		// Handle street address if provided.
		$street_address = $this->resolve_street_address( $parsed['STREET_ADDRESS'] ?? '' );
		if ( false !== $street_address ) {
			$body['street_address'] = $street_address;
		}

		// Handle custom fields if provided.
		$custom_fields = $this->resolve_custom_fields( $parsed['CUSTOM_FIELDS'] ?? '' );
		if ( ! empty( $custom_fields ) ) {
			$body['custom_fields'] = $custom_fields;
		}

		$this->api->create_contact( $body, $action_data );
	}

	/**
	 * Resolves the custom fields.
	 *
	 * @param string $custom_fields Expects valid JSON string.
	 *
	 * @return string
	 */
	private function resolve_custom_fields( $custom_fields ) {

		// If empty, return empty string.
		if ( empty( $custom_fields ) ) {
			return '';
		}

		// Decode and validate JSON.
		$fields = json_decode( $custom_fields, true );

		// If invalid JSON or empty, return empty string.
		if ( null === $fields || empty( $fields ) ) {
			return '';
		}

		// Filter out any custom fields that don't have both ID and value.
		$filtered_fields = array_filter(
			$fields,
			function ( $field ) {
				return ! empty( $field['CUSTOM_FIELD_ID'] ) && ! empty( $field['CUSTOM_FIELD_VALUE'] );
			}
		);

		// If no valid fields remain, return empty string.
		if ( empty( $filtered_fields ) ) {
			return '';
		}

		// Re-encode for API server.
		return wp_json_encode( array_values( $filtered_fields ) );
	}

	/**
	 * Resolves the street address field.
	 *
	 * @param string $street_address Expects valid JSON string.
	 *
	 * @return string|false
	 */
	private function resolve_street_address( $street_address ) {

		// If empty, return false so API server skips it.
		if ( empty( $street_address ) ) {
			return false;
		}

		// Decode and validate JSON.
		$address = json_decode( $street_address, true );

		// If invalid JSON or empty array, return false.
		if ( null === $address || empty( $address ) ) {
			return false;
		}

		// Re-encode for API server.
		return wp_json_encode( $address );
	}
}
