<?php
namespace Uncanny_Automator\Integrations\Google_Contacts;

use Exception;

/**
 * @property Google_Contacts_Helpers $helpers
 * @property Google_Contacts_Api_Caller $api
 *
 * @since 5.2
 */
class CREATE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'GOOGLE_CONTACTS' );
		$this->set_action_code( 'GOOGLE_CONTACTS_CREATE' );
		$this->set_action_meta( 'GOOGLE_CONTACTS_CREATE_META' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x(
					'Create {{a contact:%1$s}}',
					'Google Contacts',
					'uncanny-automator'
				),
				'NON_EXISTING:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create {{a contact}}', 'Google Contacts', 'uncanny-automator' ) );
	}

	/**
	 * @return mixed[]
	 */
	public function options() {

		$email = array(
			'option_code'     => 'EMAIL_ADDRESS',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Email', 'Google Contacts', 'uncanny-automator' ),
			'required'        => true,
			'fields'          => array(
				array(
					'option_code' => 'EMAIL',
					'input_type'  => 'email',
					'label'       => esc_html_x( 'Email', 'Google Contacts', 'uncanny-automator' ),
				),
				array(
					'option_code'     => 'EMAIL_LABEL',
					'input_type'      => 'select',
					'label'           => esc_html_x( 'Label', 'Google Contacts', 'uncanny-automator' ),
					'options'         => array(
						array(
							'value' => esc_html_x( 'Home', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Home', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Work', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Work', 'Google Contacts', 'uncanny-automator' ),
						),
					),
					'options_show_id' => false,
				),
			),
		);

		$first_name = array(
			'option_code' => 'FIRST_NAME',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'First name', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$last_name = array(
			'option_code' => 'LAST_NAME',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Last name', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$org_name = array(
			'option_code' => 'ORG_NAME',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Company', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$org_title = array(
			'option_code' => 'ORG_TITLE',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Job title', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$org_department = array(
			'option_code' => 'ORG_DEPARTMENT',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Department', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$phone = array(
			'option_code'     => 'PHONE_NUMBER',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Phone', 'Google Contacts', 'uncanny-automator' ),
			'required'        => false,
			'fields'          => array(
				array(
					'option_code' => 'PHONE',
					'input_type'  => 'text',
					'label'       => esc_html_x( 'Phone', 'Google Contacts', 'uncanny-automator' ),
				),
				array(
					'option_code'     => 'PHONE_LABEL',
					'input_type'      => 'select',
					'label'           => esc_html_x( 'Label', 'Google Contacts', 'uncanny-automator' ),
					'options'         => array(
						array(
							'value' => esc_html_x( 'Home', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Home', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Work', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Work', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Mobile', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Mobile', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Main', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Main', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Home fax', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Home fax', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Work fax', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Work fax', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Google voice', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Google voice', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Pager', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Pager', 'Google Contacts', 'uncanny-automator' ),
						),
					),
					'options_show_id' => false,
				),
			),
		);

		$birthday = array(
			'option_code' => 'BIRTHDAY',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Birthday', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$notes = array(
			'option_code' => 'NOTES',
			'input_type'  => 'textarea',
			'label'       => esc_html_x( 'Notes', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$addr_country = array(
			'option_code' => 'ADDR_COUNTRY',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Country / Region', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$addr_str1 = array(
			'option_code' => 'ADDR_STRT1',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Street address', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$addr_str2 = array(
			'option_code' => 'ADDR_STRT2',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Street address line 2', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$addr_city = array(
			'option_code' => 'ADDR_CITY',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'City', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$addr_postal_code = array(
			'option_code' => 'ADDR_POSTAL_CODE',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Postal code', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$addr_province = array(
			'option_code' => 'ADDR_PROVINCE',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Province', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$addr_po_box = array(
			'option_code' => 'ADDR_PO_BOX',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'PO box', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$addr_label = array(
			'option_code' => 'ADDR_LABEL',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Address label', 'Google Contacts', 'uncanny-automator' ),
			'required'    => false,
		);

		$website = array(
			'option_code'     => 'WEBSITE_URL',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Website', 'Google Contacts', 'uncanny-automator' ),
			'required'        => false,
			'fields'          => array(
				array(
					'option_code' => 'WEBSITE_URL',
					'input_type'  => 'url',
					'label'       => esc_html_x( 'Website', 'Google Contacts', 'uncanny-automator' ),
				),
				array(
					'option_code'     => 'WEBSITE_URL_LABEL',
					'input_type'      => 'select',
					'label'           => esc_html_x( 'Label', 'Google Contacts', 'uncanny-automator' ),
					'options'         => array(
						array(
							'value' => esc_html_x( 'Profile', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Profile', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Blog', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Blog', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Home page', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Home page', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Work', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Work', 'Google Contacts', 'uncanny-automator' ),
						),
					),
					'options_show_id' => false,
				),
			),
		);

		$person_related = array(
			'option_code'     => 'RELATED_PERSON',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Related person', 'Google Contacts', 'uncanny-automator' ),
			'required'        => false,
			'fields'          => array(
				array(
					'option_code' => 'RELATED_PERSON',
					'input_type'  => 'text',
					'label'       => esc_html_x( 'Related person', 'Google Contacts', 'uncanny-automator' ),
				),
				array(
					'option_code'     => 'RELATED_PERSON_LABEL',
					'input_type'      => 'select',
					'label'           => esc_html_x( 'Label', 'Google Contacts', 'uncanny-automator' ),
					'options'         => array(
						array(
							'value' => esc_html_x( 'Spouse', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Spouse', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Child', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Child', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Mother', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Mother', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Father', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Father', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Parent', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Parent', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Brother', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Brother', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Sister', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Sister', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Friend', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Friend', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Relative', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Relative', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Manager', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Manager', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Assistant', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Assistant', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Reference', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Reference', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Partner', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Partner', 'Google Contacts', 'uncanny-automator' ),
						),
						array(
							'value' => esc_html_x( 'Domestic partner', 'Google Contacts', 'uncanny-automator' ),
							'text'  => esc_html_x( 'Domestic partner', 'Google Contacts', 'uncanny-automator' ),
						),
					),
					'options_show_id' => false,
				),
			),
		);

		$custom_field = array(
			'option_code'     => 'CUSTOM_FIELD',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Custom field', 'Google Contacts', 'uncanny-automator' ),
			'required'        => false,
			'fields'          => array(
				array(
					'option_code' => 'CUSTOM_FIELD',
					'input_type'  => 'text',
					'label'       => esc_html_x( 'Custom field', 'Google Contacts', 'uncanny-automator' ),
				),
				array(
					'option_code' => 'CUSTOM_FIELD_LABEL',
					'input_type'  => 'text',
					'label'       => esc_html_x( 'Label', 'Google Contacts', 'uncanny-automator' ),
				),
			),
		);

		return array(
			$email,
			$first_name,
			$last_name,
			$org_name,
			$org_title,
			$org_department,
			$phone,
			$birthday,
			$notes,
			$addr_country,
			$addr_province,
			$addr_city,
			$addr_str1,
			$addr_str2,
			$addr_postal_code,
			$addr_po_box,
			$addr_label,
			$website,
			$person_related,
			$custom_field,
		);
	}

	/**
	 * @param int $user_id
	 * @param mixed[] $action_data
	 * @param int $recipe_id
	 * @param mixed[] $args
	 * @param array{FIELDS:string,EMAIL:string} $parsed
	 *
	 * @throws \Exception
	 *
	 * @return bool True if the action is successful. Returns false, otherwise.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		try {

			$body = array(
				'action' => 'create',
			);

			$fields = array();

			foreach ( $parsed as $key => $field ) {
				$fields[ strtolower( $key ) ] = $field;
			}

			$fields = $this->validate_fields( $fields );

			$payload = array_merge( $fields, $body );

			// Use injected API instance
			$this->api->api_request( $payload, $action_data );

		} catch ( \Exception $e ) {

			$this->add_log_error( $e->getMessage() );

			return false;

		}

		return true;
	}

	/**
	 * Validates the fields.
	 *
	 * @param mixed[] $fields
	 *
	 * @return mixed[] The fields after successful validation.
	 */
	private function validate_fields( $fields ) {

		// Validate email.
		if ( ! isset( $fields['email_address'] ) ) {
			throw new \Exception( 'Email address field is not found.', 400 );
		}

		$emails = (array) json_decode( $fields['email_address'], true );

		foreach ( $emails as $email ) {
			if ( empty( $email['EMAIL'] ) ) {
				throw new Exception( 'Email is required but missing.', 400 );
			}
		}

		return $fields;
	}
}
