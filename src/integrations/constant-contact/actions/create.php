<?php
namespace Uncanny_Automator\Integrations\Constant_Contact;

use DateTime;
use Exception;

/**
 * Class Uncanny_Automator\Integrations\Constant_Contact\CREATE
 *
 * @package Uncanny_Automator
 */
class CREATE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'CONSTANT_CONTACT' );
		$this->set_action_code( 'ADD_UPDATE_CONTACT' );
		$this->set_action_meta( 'CC_CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/constant-contact/' ) );
		$this->set_requires_user( false );
		/* translators: Contact Email */
		$this->set_sentence( sprintf( esc_attr_x( 'Create or update {{a contact:%1$s}}', 'Constant Contact', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Constant Contact', 'uncanny-automator' ) );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => _x( 'Email', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
			array(
				'option_code' => 'LIST',
				'label'       => _x( 'List', 'Constant Contact', 'uncanny-automator' ),
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
				'label'       => _x( 'First name', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'LAST_NAME',
				'label'       => _x( 'Last name', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'JOB_TITLE',
				'label'       => _x( 'Job title', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'COMPANY_NAME',
				'label'       => _x( 'Company name', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'PHONE_NUMBER',
				'label'       => _x( 'Phone number', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			array(
				'option_code' => 'ANNIVERSARY',
				'label'       => _x( 'Anniversary', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'text',
			),
			// Street address.
			array(
				'option_code' => 'STREET_ADDRESS',
				'label'       => _x( 'Address', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'repeater',
				'fields'      => array(
					array(
						'option_code'           => 'KEY',
						'label'                 => _x( 'Type', 'Constant Contact', 'uncanny-automator' ),
						'input_type'            => 'select',
						'options'               => array(
							array(
								'text'  => _x( 'Street', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'street',
							),
							array(
								'text'  => _x( 'City', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'city',
							),
							array(
								'text'  => _x( 'State', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'state',
							),
							array(
								'text'  => _x( 'Postal code', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'postal_code',
							),
							array(
								'text'  => _x( 'Country', 'Constant Contact', 'uncanny-automator' ),
								'value' => 'country',
							),
						),
						'supports_custom_value' => false,
						'options_show_id'       => false,
					),
					array(
						'option_code' => 'VALUE',
						'label'       => _x( 'Value', 'Constant Contact', 'uncanny-automator' ),
						'input_type'  => 'text',
					),
				),
			),
			// Custom fields.
			array(
				'hide_actions' => true,
				'option_code'  => 'CUSTOM_FIELDS',
				'label'        => _x( 'Custom fields', 'Constant Contact', 'uncanny-automator' ),
				'input_type'   => 'repeater',
				'ajax'         => array(
					'event'          => 'on_load',
					'endpoint'       => 'automator_constant_contact_contact_fields_get',
					'mapping_column' => 'CUSTOM_FIELD_ID',
				),
				'fields'       => array(
					array(
						'option_code' => 'CUSTOM_FIELD_ID',
						'label'       => _x( 'ID', 'Constant Contact', 'uncanny-automator' ),
						'input_type'  => 'text',
						'read_only'   => true,
					),
					array(
						'option_code' => 'CUSTOM_FIELD_NAME',
						'label'       => _x( 'Name', 'Constant Contact', 'uncanny-automator' ),
						'input_type'  => 'text',
						'read_only'   => true,
					),
					array(
						'option_code' => 'CUSTOM_FIELD_VALUE',
						'label'       => _x( 'Value', 'Constant Contact', 'uncanny-automator' ),
						'input_type'  => 'text',
					),
				),
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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helpers        = $this->helpers;
		$credentials    = $helpers->get_credentials();
		$first_name     = isset( $parsed['FIRST_NAME'] ) ? sanitize_text_field( $parsed['FIRST_NAME'] ) : '';
		$last_name      = isset( $parsed['LAST_NAME'] ) ? sanitize_text_field( $parsed['LAST_NAME'] ) : '';
		$job_title      = isset( $parsed['JOB_TITLE'] ) ? sanitize_text_field( $parsed['JOB_TITLE'] ) : '';
		$company_name   = isset( $parsed['COMPANY_NAME'] ) ? sanitize_text_field( $parsed['COMPANY_NAME'] ) : '';
		$phone_number   = isset( $parsed['PHONE_NUMBER'] ) ? sanitize_text_field( $parsed['PHONE_NUMBER'] ) : '';
		$anniversary    = isset( $parsed['ANNIVERSARY'] ) ? wp_strip_all_tags( $parsed['ANNIVERSARY'] ) : '';
		$custom_fields  = isset( $parsed['CUSTOM_FIELDS'] ) ? $parsed['CUSTOM_FIELDS'] : '';
		$street_address = isset( $parsed['STREET_ADDRESS'] ) ? $parsed['STREET_ADDRESS'] : '';

		// Handle invalid JSON at the client level. The server expects to receive a valid JSON.
		if ( false === json_decode( $custom_fields, true ) ) {
			throw new Exception( 'Invalid custom fields value: ' . wp_json_encode( $custom_fields ), 400 );
		}

		$anniversary_formatted = gmdate( 'm/d/Y', strtotime( sanitize_text_field( $anniversary ) ) );

		$body = array(
			'access_token'   => $credentials['access_token'],
			'action'         => 'create_contact',
			'email_address'  => $parsed[ $this->get_action_meta() ],
			'list'           => $parsed['LIST'],
			'first_name'     => $first_name,
			'last_name'      => $last_name,
			'job_title'      => $job_title,
			'company_name'   => $company_name,
			'phone_number'   => $phone_number,
			'anniversary'    => $anniversary_formatted,
			'street_address' => $this->resolve_street_address( $street_address ),
			'custom_fields'  => $custom_fields,
		);

		$helpers->api_request( $body, $action_data );

	}

	/**
	 * Resolves the street address field.
	 *
	 * @param string $street_address Expects valid JSON string.
	 *
	 * @return string
	 */
	private function resolve_street_address( $street_address ) {

		// Handle invalid JSON at the client level. The server expects to receive a valid JSON.
		if ( false === json_decode( $street_address, true ) ) {
			throw new Exception( 'Invalid address field values: ' . wp_json_encode( $street_address ), 400 );
		}

		$street_address_arr = (array) json_decode( $street_address, true );

		$street_address_final = array();

		foreach ( $street_address_arr as $addr ) {
			if ( '' !== $addr['VALUE'] ) {
				$street_address_final[ $addr['KEY'] ] = $addr['VALUE'];
			}
		}

		if ( empty( $street_address_final ) ) {
			return '';
		}

		return wp_json_encode( $street_address_final );

	}

}
