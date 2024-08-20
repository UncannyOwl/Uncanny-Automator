<?php

namespace Uncanny_Automator\Integrations\Keap;

use Exception;
use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class KEAP_ADD_UPDATE_CONTACT
 *
 * @package Uncanny_Automator
 */
class KEAP_ADD_UPDATE_CONTACT extends \Uncanny_Automator\Recipe\Action {

	use Log_Properties;

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'KEAP_ADD_UPDATE_CONTACT';

	/**
	 * Store the complete with notice messages.
	 *
	 * @var array
	 */
	public $complete_with_notice_messages = array();

	/**
	 * Address Types
	 *
	 * @var array
	 */
	public $address_types = array( 'billing', 'shipping', 'other' );

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {

		/** @var \Uncanny_Automator\Integrations\Keap\Keap_Helpers $helper */
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'KEAP' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/keap/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
			/* translators: %1$s Contact Email */
				esc_attr_x( 'Add/Update {{a contact:%1$s}}', 'Keap', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add/Update {{a contact}}', 'Keap', 'uncanny-automator' ) );
		$this->set_background_processing( true );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array();

		// Required Email.
		$fields[] = $this->helpers->get_email_field_config( $this->get_action_meta() );

		// Allow user to update existing contacts.
		$fields[] = $this->helpers->get_update_existing_option_config( 'contact' );

		// Contact info repeater.
		$fields[] = array(
			'option_code'       => 'CONTACT_INFO',
			'input_type'        => 'repeater',
			'label'             => _x( 'Contact info', 'Keap', 'uncanny-automator' ),
			'add_row_button'    => _x( 'Add a field', 'Keap', 'uncanny-automator' ),
			'remove_row_button' => _x( 'Remove field', 'Keap', 'uncanny-automator' ),
			'required'          => false,
			'fields'            => array(
				array(
					'option_code'           => 'FIELD',
					'label'                 => _x( 'Field', 'Keap', 'uncanny-automator' ),
					'input_type'            => 'select',
					'options'               => $this->get_contact_profile_options(),
					'options_show_id'       => false,
					'supports_custom_value' => false,
					'required'              => true,
				),
				array(
					'option_code' => 'FIELD_VALUE',
					'label'       => _x( 'Value', 'Keap', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
			),
			'relevant_tokens'   => array(),
		);

		// Phone Repeater.
		$fields[] = array(
			'option_code'       => 'PHONES',
			'input_type'        => 'repeater',
			'label'             => _x( 'Phone numbers', 'Keap', 'uncanny-automator' ),
			'add_row_button'    => _x( 'Add phone number', 'Keap', 'uncanny-automator' ),
			'remove_row_button' => _x( 'Remove phone number', 'Keap', 'uncanny-automator' ),
			'required'          => false,
			'fields'            => array(
				array(
					'option_code'           => 'FIELD_KEY',
					'label'                 => _x( 'Field', 'Keap', 'uncanny-automator' ),
					'input_type'            => 'select',
					'options'               => $this->get_phone_fields_options(),
					'options_show_id'       => false,
					'supports_custom_value' => false,
					'required'              => true,
				),
				array(
					'option_code' => 'EXTENSION',
					'label'       => _x( 'Extension', 'Keap', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
				array(
					'option_code' => 'NUMBER',
					'label'       => _x( 'Number', 'Keap', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
				),
				array(
					'option_code'           => 'TYPE_OF_NUMBER',
					'label'                 => _x( 'Type', 'Keap', 'uncanny-automator' ),
					'input_type'            => 'select',
					'options'               => $this->get_phone_types_options(),
					'options_show_id'       => false,
					'supports_custom_value' => false,
					'required'              => true,
				),
			),
			'relevant_tokens'   => array(),
		);

		// Links Repeater.
		$fields[] = array(
			'option_code'       => 'LINKS',
			'input_type'        => 'repeater',
			'label'             => _x( 'Links', 'Keap', 'uncanny-automator' ),
			'add_row_button'    => _x( 'Add a link', 'Keap', 'uncanny-automator' ),
			'remove_row_button' => _x( 'Remove link', 'Keap', 'uncanny-automator' ),
			'required'          => false,
			'fields'            => array(
				array(
					'option_code'           => 'TYPE',
					'label'                 => _x( 'Type', 'Keap', 'uncanny-automator' ),
					'input_type'            => 'select',
					'options'               => $this->get_link_types_options(),
					'options_show_id'       => false,
					'supports_custom_value' => false,
					'required'              => true,
				),
				array(
					'option_code' => 'URL',
					'label'       => _x( 'URL', 'Keap', 'uncanny-automator' ),
					'input_type'  => 'url',
					'required'    => true,
				),
			),
			'description'       => _x( 'One of each link type supported', 'Keap', 'uncanny-automator' ),
			'relevant_tokens'   => array(),
		);

		// All Address type fields.
		foreach ( $this->address_types as $type ) {
			$address_fields = $this->helpers->get_address_fields_config( $type );
			$fields         = array_merge( $fields, $address_fields );
		}

		// Custom Fields.
		$fields[] = $this->helpers->get_custom_fields_repeater_config( 'contact' );

		// Companies.
		$fields[] = array(
			'option_code' => 'COMPANY',
			'label'       => _x( 'Company', 'Keap', 'uncanny-automator' ),
			'input_type'  => 'select',
			'options'     => array(),
			'required'    => false,
			'description' => _x( 'Select a company to assign to the contact. If using a custom value company ID or name will be excepted.', 'Keap', 'uncanny-automator' ),
			'ajax'        => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_keap_get_companies',
			),
		);

		// Contact Type.
		$fields[] = array(
			'option_code'           => 'CONTACT_TYPE',
			'label'                 => _x( 'Contact type', 'Keap', 'uncanny-automator' ),
			'input_type'            => 'select',
			'options'               => $this->get_contact_type_options(),
			'options_show_id'       => false,
			'supports_custom_value' => false,
			'required'              => false,
		);

		// Owner ID.
		$fields[] = array(
			'option_code' => 'OWNER_ID',
			'label'       => _x( 'Contact owner', 'Keap', 'uncanny-automator' ),
			'input_type'  => 'select',
			'options'     => array(),
			'description' => _x( 'Select the Keap account user to assign as the contact owner. If using a custom value emails are excepted.', 'Keap', 'uncanny-automator' ),
			'required'    => false,
			'ajax'        => array(
				'endpoint' => 'automator_keap_get_account_users',
				'event'    => 'on_load',
			),
		);

		return $fields;
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		$tokens                 = $this->helpers->define_contact_action_tokens();
		$tokens['COMPANY_NAME'] = array(
			'name' => esc_attr_x( 'Company name', 'Keap', 'uncanny-automator' ),
			'type' => 'string',
		);
		return $tokens;
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Required field - throws error if not set and valid.
		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

		// Build contact request data.
		$contact = array();

		// Get all repeaters.
		$info   = json_decode( Automator()->parse->text( $action_data['meta']['CONTACT_INFO'], $recipe_id, $user_id, $args ), true );
		$phones = json_decode( Automator()->parse->text( $action_data['meta']['PHONES'], $recipe_id, $user_id, $args ), true );
		$links  = json_decode( Automator()->parse->text( $action_data['meta']['LINKS'], $recipe_id, $user_id, $args ), true );
		$custom = json_decode( Automator()->parse->text( $action_data['meta']['CUSTOM_FIELDS'], $recipe_id, $user_id, $args ), true );

		// Add contact emails array.
		$contact['email_addresses'] = $this->build_contact_emails( $email, $info );

		// Add info fields.
		$contact = $this->add_contact_info( $info, $links, $contact );

		// Build phones.
		$phones = $this->build_contact_phones( $phones );
		if ( ! empty( $phones ) ) {
			$contact['phone_numbers'] = $phones;
		}

		// Build socials.
		$socials = $this->build_contact_socials( $links );
		if ( ! empty( $socials ) ) {
			$contact['social_accounts'] = $socials;
		}

		// Build addresses.
		$addresses = $this->build_contact_addresses( $parsed );
		if ( ! empty( $addresses ) ) {
			$contact['addresses'] = $addresses;
		}

		// Build custom fields.
		$custom = $this->helpers->build_custom_fields_request_data( $custom, 'contact' );
		// Merge in any custom field errors.
		if ( ! empty( $custom['errors'] ) ) {
			$this->complete_with_notice_messages[] = $custom['errors'];
		}
		if ( ! empty( $custom['fields'] ) ) {
			$contact['custom_fields'] = $custom['fields'];
		}

		// Build request body.
		$body = array(
			'email'   => $email,
			'update'  => $this->helpers->get_bool_value_from_parsed( $parsed, 'UPDATE_EXISTING_CONTACT' ),
			'contact' => wp_json_encode( $contact ),
		);

		// Send request.
		$response = $this->helpers->api_request(
			'add_update_contact',
			$body,
			$action_data
		);

		// Hydrate contact tokens.
		$results                = $response['data'] ?? array();
		$tokens                 = $this->helpers->hydrate_contact_tokens( $results );
		$tokens['COMPANY_NAME'] = isset( $contact['company'] ) && is_object( $contact['company'] ) ? $contact['company']->company_name : '';

		$this->hydrate_tokens( $tokens );

		if ( ! empty( $this->complete_with_notice_messages ) ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( implode( ', ', $this->complete_with_notice_messages ) );

			return null;
		}

		return true;
	}

	/**
	 * Get Profile Options.
	 *
	 * @return array
	 */
	private function get_contact_profile_options() {

		return array(
			// Name fields.
			array(
				'text'  => _x( 'Name prefix', 'Keap', 'uncanny-automator' ),
				'value' => 'PREFIX',
			),
			array(
				'text'  => _x( 'First name', 'Keap', 'uncanny-automator' ),
				'value' => 'GIVEN_NAME',
			),
			array(
				'text'  => _x( 'Middle name', 'Keap', 'uncanny-automator' ),
				'value' => 'MIDDLE_NAME',
			),
			array(
				'text'  => _x( 'Last name', 'Keap', 'uncanny-automator' ),
				'value' => 'FAMILY_NAME',
			),
			array(
				'text'  => _x( 'Name suffix', 'Keap', 'uncanny-automator' ),
				'value' => 'SUFFIX',
			),
			array(
				'text'  => _x( 'Preferred name', 'Keap', 'uncanny-automator' ),
				'value' => 'PREFERRED_NAME',
			),
			array(
				'text'  => _x( 'Job title', 'Keap', 'uncanny-automator' ),
				'value' => 'JOB_TITLE',
			),
			// Additional Emails.
			array(
				'text'  => _x( 'Personal email', 'Keap', 'uncanny-automator' ),
				'value' => 'EMAIL2',
			),
			array(
				'text'  => _x( 'Other email', 'Keap', 'uncanny-automator' ),
				'value' => 'EMAIL3',
			),
			// Dates.
			array(
				'text'  => _x( 'Anniversary', 'Keap', 'uncanny-automator' ),
				'value' => 'ANNIVERSARY_DATE',
			),
			array(
				'text'  => _x( 'Birthday', 'Keap', 'uncanny-automator' ),
				'value' => 'BIRTH_DATE',
			),
			// Additional info.
			array(
				'text'  => _x( 'Spouse name', 'Keap', 'uncanny-automator' ),
				'value' => 'SPOUSE_NAME',
			),
			array(
				'text'  => _x( 'Preferred locale', 'Keap', 'uncanny-automator' ),
				'value' => 'PREFERRED_LOCALE',
			),
			array(
				'text'  => _x( 'Time zone', 'Keap', 'uncanny-automator' ),
				'value' => 'TIME_ZONE',
			),
			array(
				'text'  => _x( 'Referral code', 'Keap', 'uncanny-automator' ),
				'value' => 'REFERRAL_CODE',
			),
			array(
				'text'  => _x( 'Lead source ID', 'Keap', 'uncanny-automator' ),
				'value' => 'LEAD_SOURCE_ID',
			),
		);

		return $options;
	}

	/**
	 * Get phone fields.
	 *
	 * @return array
	 */
	private function get_phone_fields_options() {
		$options = array();

		for ( $i = 1; $i <= 5; $i ++ ) {
			$options[] = array(
				'text'  => sprintf(
					/* translators: %d: phone field number priority */
					_x( 'Phone %d', 'Keap', 'uncanny-automator' ),
					$i
				),
				'value' => 'PHONE' . $i,
			);
		}

		return $options;
	}

	/**
	 * Get phone types options.
	 *
	 * @return array
	 */
	private function get_phone_types_options() {
		$types   = $this->helpers->get_account_contact_config( 'phone_types', array() );
		$options = array();
		foreach ( $types as $type ) {
			$options[] = array(
				'text'  => apply_filters( 'automator_keap_phone_type_label', $type ),
				'value' => $type,
			);
		}
		return $options;
	}

	/**
	 * Get link types options.
	 *
	 * @return array
	 */
	private function get_link_types_options() {

		return array(
			array(
				'text'  => _x( 'Website', 'Keap', 'uncanny-automator' ),
				'value' => 'WEBSITE',
			),
			array(
				'text'  => _x( 'Facebook', 'Keap', 'uncanny-automator' ),
				'value' => 'FACEBOOK',
			),
			array(
				'text'  => _x( 'LinkedIn', 'Keap', 'uncanny-automator' ),
				'value' => 'LINKED_IN',
			),
			array(
				'text'  => _x( 'Twitter', 'Keap', 'uncanny-automator' ),
				'value' => 'TWITTER',
			),
			array(
				'text'  => _x( 'Instagram', 'Keap', 'uncanny-automator' ),
				'value' => 'INSTAGRAM',
			),
			array(
				'text'  => _x( 'Snapchat', 'Keap', 'uncanny-automator' ),
				'value' => 'SNAPCHAT',
			),
			array(
				'text'  => _x( 'YouTube', 'Keap', 'uncanny-automator' ),
				'value' => 'YOUTUBE',
			),
			array(
				'text'  => _x( 'Pinterest', 'Keap', 'uncanny-automator' ),
				'value' => 'PINTEREST',
			),
		);

	}

	/**
	 * Get contact type options.
	 *
	 * @return array
	 */
	private function get_contact_type_options() {

		$options = array(
			array(
				'text'  => _x( 'Other', 'Keap', 'uncanny-automator' ),
				'value' => 'Other',
			),
			array(
				'text'  => _x( 'Client', 'Keap', 'uncanny-automator' ),
				'value' => 'Customer',
			),
			array(
				'text'  => _x( 'Lead', 'Keap', 'uncanny-automator' ),
				'value' => 'Lead',
			),
		);

		$types = $this->helpers->get_account_contact_config( 'contact_types', false );
		if ( empty( $types ) ) {
			return $options;
		}

		foreach ( $types as $type ) {
			$options[] = array(
				'text'  => $type,
				'value' => $type,
			);
		}

		return $options;
	}

	/**
	 * Generate email object array.
	 *
	 * @param string $email
	 * @param array $info
	 *
	 * @return array
	 */
	private function build_contact_emails( $email, $info, $opt_in = '' ) {

		$opt_in = empty( $opt_in ) ? _x( 'Opt In from API', 'Keap', 'uncanny-automator' ) : $opt_in;

		$emails = array(
			(object) array(
				'email'         => $email,
				'field'         => 'EMAIL1',
				'opt_in_reason' => $opt_in,
			),
		);

		// Add additional emails.
		if ( ! empty( $info ) ) {
			$email_fields = array_filter(
				$info,
				function ( $field ) {
					return in_array( $field['FIELD'], array( 'EMAIL2', 'EMAIL3' ), true );
				}
			);

			// Get additional emails.
			foreach ( $email_fields as $field ) {
				$validated = $this->get_validated_email( $field['FIELD_VALUE'], $field['FIELD'] );
				if ( ! empty( $validated ) ) {
					$emails[] = (object) array(
						'email' => $this->helpers->maybe_remove_delete_value( $validated, false ),
						'field' => $field['FIELD'],
					);
				}
			}
		}

		return $emails;
	}

	/**
	 * Add contact general and additional info.
	 *
	 * @param array $info
	 * @param array $links
	 * @param array $contact
	 *
	 * @return array - $contact
	 */
	private function add_contact_info( $info, $links, $contact ) {

		// Add source type.
		$contact['source_type'] = 'API';

		// Add contact type / default to other.
		$contact_type            = $this->get_parsed_meta_value( 'CONTACT_TYPE', false );
		$contact_type            = empty( $contact_type ) ? 'Other' : $contact_type;
		$contact['contact_type'] = $contact_type;

		// Maybe add company.
		$company = $this->get_parsed_meta_value( 'COMPANY', false );
		$company = $this->get_validated_company( $company );
		if ( is_object( $company ) ) {
			$contact['company'] = $company;
		}

		// Maybe add owner.
		$owner_id = $this->get_parsed_meta_value( 'OWNER_ID', false );
		if ( ! empty( $owner_id ) ) {
			$validated_owner_id = $this->helpers->get_valid_account_user_selection( $owner_id );
			if ( is_wp_error( $validated_owner_id ) ) {
				$this->complete_with_notice_messages[] = sprintf(
					/* translators: %s: error message */
					_x( 'Unable to add Owner: %s', 'Keap', 'uncanny-automator' ),
					$validated_owner_id->get_error_message()
				);
			} else {
				$contact['owner_id'] = $validated_owner_id;
			}
		}

		// Check for website.
		if ( ! empty( $links ) && is_array( $links ) ) {
			$website_link = array_filter(
				$links,
				function ( $link ) {
					return 'WEBSITE' === $link['TYPE'];
				}
			);
			if ( ! empty( $website_link ) ) {
				$validated_link = $this->get_validated_url( $website_link[0]['URL'], 'WEBSITE' );
				if ( ! empty( $validated_link ) ) {
					$contact['website'] = $this->helpers->maybe_remove_delete_value( $validated_link );
				}
			}
		}

		// Bail if no info.
		if ( empty( $info ) || ! is_array( $info ) ) {
			return $contact;
		}

		$skip  = array( 'EMAIL2', 'EMAIL3' );
		$dates = array( 'ANNIVERSARY_DATE', 'BIRTH_DATE' );
		foreach ( $info as $field ) {
			if ( in_array( $field['FIELD'], $skip, true ) ) {
				continue;
			}

			$value = sanitize_text_field( $field['FIELD_VALUE'] );
			if ( empty( $value ) ) {
				continue;
			}

			// Check for date fields.
			if ( in_array( $field['FIELD'], $dates, true ) ) {
				$value = $this->get_validated_date( $value, $field['FIELD'] );
				if ( empty( $value ) ) {
					continue;
				}
			}

			if ( 'PREFIX' === $field['FIELD'] ) {
				$value = $this->get_validated_prefix( $value );
				if ( empty( $value ) ) {
					continue;
				}
			}

			if ( 'SUFFIX' === $field['FIELD'] ) {
				$value = $this->get_validated_suffix( $value );
				if ( empty( $value ) ) {
					continue;
				}
			}

			// Add to contact.
			$key             = strtolower( $field['FIELD'] );
			$contact[ $key ] = $this->helpers->maybe_remove_delete_value( $value );
		}

		return $contact;
	}

	/**
	 * Build contact phones.
	 *
	 * @param array $phones
	 *
	 * @return array
	 */
	private function build_contact_phones( $phones ) {

		$data = array();

		// Bail if no phones.
		if ( empty( $phones ) || ! is_array( $phones ) ) {
			return $data;
		}

		// Build phones.
		foreach ( $phones as $phone ) {

			$field_key = sanitize_text_field( $phone['FIELD_KEY'] );

			// Validate phone number.
			$number = $this->get_validated_phone_number( $phone['NUMBER'], $field_key );
			if ( empty( $number ) ) {
				continue;
			}

			// Add to data with key to ensure we only have one of each.
			$data[ $field_key ] = (object) array(
				'extension' => $this->helpers->maybe_remove_delete_value( $phone['EXTENSION'] ),
				'number'    => $this->helpers->maybe_remove_delete_value( $number ),
				'type'      => $this->helpers->maybe_remove_delete_value( $phone['TYPE_OF_NUMBER'] ),
				'field'     => $field_key,
			);
		}

		return ! empty( $data ) ? array_values( $data ) : $data;
	}

	/**
	 * Build contact socials.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	private function build_contact_socials( $links ) {

		$data = array();

		// Bail if no links.
		if ( empty( $links ) || ! is_array( $links ) ) {
			return $data;
		}

		// Build socials.
		foreach ( $links as $link ) {

			$type = sanitize_text_field( $link['TYPE'] );

			// Skip website.
			if ( 'WEBSITE' === $type ) {
				continue;
			}

			$url = $this->get_validated_url( $link['URL'], $type );
			if ( empty( $url ) ) {
				continue;
			}

			// Add to data with key to ensure we only have one of each.
			$data[ $type ] = (object) array(
				'name' => $this->helpers->maybe_remove_delete_value( $url ),
				'type' => $type,
			);
		}

		return ! empty( $data ) ? array_values( $data ) : $data;
	}

	/**
	 * Build contact addresses.
	 *
	 * @param array $parsed
	 *
	 * @return array
	 */
	private function build_contact_addresses( $parsed ) {

		$addresses = array();
		foreach ( $this->address_types as $type ) {
			$address = $this->helpers->get_address_fields_from_parsed( $parsed, $type );
			if ( ! empty( $address ) ) {
				$addresses[] = $address;
			}
		}

		return $addresses;
	}

	/**
	 * Get validated email.
	 *
	 * @param string $email - Email
	 * @param string $key - Field Key
	 *
	 * @return string
	 */
	private function get_validated_email( $email, $key ) {

		if ( $this->helpers->is_delete_value( $email ) ) {
			return $email;
		}

		$validated_email = $this->helpers->get_valid_email( $email );
		if ( false === $validated_email ) {
			$this->complete_with_notice_messages[] = sprintf(
				_x( 'Invalid email: "%1$s" for key: "%2$s"', 'Keap', 'uncanny-automator' ),
				$email,
				strtolower( $key )
			);
		}

		return $validated_email;
	}

	/**
	 * Validated URL.
	 *
	 * @param string $url - URL
	 * @param string $key - Field Key
	 *
	 * @return array
	 */
	private function get_validated_url( $url, $key ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return '';
		}
		if ( $this->helpers->is_delete_value( $url ) ) {
			return $url;
		}

		$validate_link = esc_url( $url );
		if ( ! $validate_link ) {
			$this->complete_with_notice_messages[] = sprintf(
				_x( 'Invalid url: "%1$s" for key: "%2$s"', 'Keap', 'uncanny-automator' ),
				$email,
				strtolower( $key )
			);
		}

		return $validate_link;
	}

	/**
	 * Get validated date.
	 *
	 * @param string $date - Date
	 * @param string $key - Field Key
	 * @param string $format - Date Format
	 *
	 * @return string
	 */
	private function get_validated_date( $date, $key, $format = 'Y-m-d' ) {

		if ( empty( $date ) ) {
			return '';
		}
		if ( $this->helpers->is_delete_value( $date ) ) {
			return $date;
		}

		$validated_date = $this->helpers->get_formatted_date( $date, $format );
		if ( is_wp_error( $validated_date ) ) {
			$this->complete_with_notice_messages[] = sprintf(
				_x( 'Invalid date: "%1$s" for key: "%2$s"', 'Keap', 'uncanny-automator' ),
				$date,
				strtolower( $key )
			);
			return '';
		}

		return $validated_date;
	}

	/**
	 * Get validated phone number.
	 *
	 * @param string $number - Phone Number
	 * @param string $key - Field Key
	 *
	 * @return string
	 */
	private function get_validated_phone_number( $number, $key ) {
		if ( empty( $number ) || ! is_string( $number ) ) {
			return '';
		}
		if ( $this->helpers->is_delete_value( $number ) ) {
			return $number;
		}

		$validate_number = $this->helpers->get_valid_phone_number( $number );
		if ( false === $validate_number ) {
			$this->complete_with_notice_messages[] = sprintf(
				_x( 'Invalid phone number: "%1$s" for key: "%2$s"', 'Keap', 'uncanny-automator' ),
				$number,
				strtolower( $key )
			);
		}

		return $validate_number;
	}

	/**
	 * Get validated prefix.
	 *
	 * @param string $prefix
	 *
	 * @return string
	 */
	private function get_validated_prefix( $prefix ) {
		if ( empty( $prefix ) || $this->helpers->is_delete_value( $prefix ) ) {
			return $prefix;
		}
		// Trim the value and lowercase and then capitalize the first letter.
		$prefix = ucfirst( strtolower( trim( $prefix ) ) );
		// Make sure it ends with 1 period.
		$prefix   = rtrim( $prefix, '.' ) . '.';
		$prefixes = $this->helpers->get_account_contact_config( 'title_types', array( 'Mr.', 'Ms.', 'Mrs.', 'Dr.' ) );
		if ( ! in_array( $prefix, $prefixes, true ) ) {
			$this->complete_with_notice_messages[] = sprintf(
				// translators: %s: valid prefixes
				_x( 'Invalid name prefix. Valid prefixes are: %s', 'Keap', 'uncanny-automator' ),
				implode( ', ', $prefixes )
			);
			return '';
		}

		return $prefix;
	}

	/**
	 * Get validated suffix.
	 *
	 * @param string $suffix
	 *
	 * @return string
	 */
	private function get_validated_suffix( $suffix ) {
		if ( empty( $suffix ) || $this->helpers->is_delete_value( $suffix ) ) {
			return $suffix;
		}
		$suffix   = trim( $suffix );
		$suffixes = $this->helpers->get_account_contact_config( 'suffix_types', false );
		if ( empty( $suffixes ) ) {
			$suffixes = array( 'Jr', 'PhD', 'I', 'II', 'III', 'IV', 'V' );
		}
		if ( ! in_array( $suffix, $suffixes, true ) ) {
			$this->complete_with_notice_messages[] = sprintf(
				// translators: %s: valid suffixes
				_x( 'Invalid name suffix. Valid suffixes are: %s', 'Keap', 'uncanny-automator' ),
				implode( ', ', $suffixes )
			);
			return '';
		}

		return $suffix;
	}

	/**
	 * Validate company selection.
	 *
	 * @param string $company
	 *
	 * @return string
	 */
	private function get_validated_company( $company ) {

		// Bail not set.
		if ( empty( $company ) ) {
			return '';
		}

		// Return empty object to remove.
		if ( $this->helpers->is_delete_value( $company ) ) {
			return (object) array(
				'id'           => '',
				'company_name' => '',
			);
		}

		$validated = $this->helpers->get_valid_company_selection( $company );
		if ( is_wp_error( $validated ) ) {
			$this->complete_with_notice_messages[] = sprintf(
				/* translators: %s: error message */
				_x( 'Unable to add Company: %s', 'Keap', 'uncanny-automator' ),
				$validated->get_error_message()
			);
			$validated = false;
		}

		return $validated;
	}

}
