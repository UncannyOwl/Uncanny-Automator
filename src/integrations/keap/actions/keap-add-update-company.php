<?php

namespace Uncanny_Automator\Integrations\Keap;

use Exception;
use Uncanny_Automator\Recipe\Log_Properties;

/**
 * Class KEAP_ADD_UPDATE_COMPANY
 *
 * @package Uncanny_Automator
 */
class KEAP_ADD_UPDATE_COMPANY extends \Uncanny_Automator\Recipe\Action {

	use Log_Properties;

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'KEAP_ADD_UPDATE_COMPANY';

	/**
	 * Store the complete with notice messages.
	 *
	 * @var array
	 */
	public $complete_with_notice_messages = array();

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
				// translators: %1$s Company name.
				esc_attr_x( 'Add/Update {{a company:%1$s}}', 'Keap', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add/Update {{a company}}', 'Keap', 'uncanny-automator' ) );
		$this->set_background_processing( true );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array();

		// Required Company Name.
		$fields[] = array(
			'option_code' => $this->get_action_meta(),
			'label'       => _x( 'Company name', 'Keap', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => true,
		);

		// Allow user to update existing companies.
		$fields[] = $this->helpers->get_update_existing_option_config( 'company' );

		// Email
		$fields[] = $this->helpers->get_email_field_config( 'EMAIL', false );

		// Phone
		$fields[] = array(
			'option_code' => 'PHONE',
			'label'       => _x( 'Phone number', 'Keap', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		// Fax
		$fields[] = array(
			'option_code' => 'FAX',
			'label'       => _x( 'Fax number', 'Keap', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
		);

		// Website
		$fields[] = array(
			'option_code' => 'WEBSITE',
			'label'       => _x( 'Website', 'Keap', 'uncanny-automator' ),
			'input_type'  => 'url',
			'required'    => false,
		);

		// Address Fields
		$address_fields = $this->helpers->get_address_fields_config( 'company' );
		$fields         = array_merge( $fields, $address_fields );

		// Custom Fields
		$fields[] = $this->helpers->get_custom_fields_repeater_config( 'company' );

		// Notes
		$fields[] = array(
			'option_code' => 'NOTES',
			'label'       => esc_attr__( 'Description', 'uncanny-automator' ),
			'input_type'  => 'textarea',
			'required'    => false,
			'description' => esc_attr__( 'Add a note about the company.', 'uncanny-automator' ),
		);

		return $fields;
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

		// Required field
		$company_name = $this->get_parsed_meta_value( $this->get_action_meta(), false );
		if ( empty( $company_name ) ) {
			throw new \Exception( esc_html_x( 'Missing company name', 'Keap', 'uncanny-automator' ) );
		}

		// Check if updating is allowed.
		$update_existing = $this->helpers->get_bool_value_from_parsed( $parsed, 'UPDATE_EXISTING_COMPANY' );

		// Validate if we have the company ID.
		$company_object = $this->helpers->get_valid_company_selection( $company_name );
		$company_id     = is_wp_error( $company_object ) ? 0 : (int) $company_object->id;

		// Throw error if company already exists and update existing is not set.
		if ( ! $update_existing && ! empty( $company_id ) ) {
			throw new \Exception(
				sprintf(
					// translators: %s Company name.
					_x( 'Company with name %s already exists and Update existing company option is set to No.', 'Keap', 'uncanny-automator' ),
					$company_name
				)
			);
		}

		// Get custom field repeater.
		$custom = json_decode( Automator()->parse->text( $action_data['meta']['CUSTOM_FIELDS'], $recipe_id, $user_id, $args ), true );

		// Build company request data.
		$company = array(
			'company_name' => $company_name,
		);

		// Build optional fields.
		$company = $this->add_optional_fields( $company );

		// Build address.
		$address = $this->helpers->get_address_fields_from_parsed( $parsed, 'company' );
		if ( ! empty( $address ) ) {
			$company['address'] = $address;
		}

		// Build custom fields.
		$custom = $this->helpers->build_custom_fields_request_data( $custom, 'company' );
		// Add any custom field errors.
		if ( ! empty( $custom['errors'] ) ) {
			$this->complete_with_notice_messages[] = $custom['errors'];
		}
		if ( ! empty( $custom['fields'] ) ) {
			$company['custom_fields'] = $custom['fields'];
		}

		// Build request body.
		$body = array(
			'company_id' => $company_id,
			'update'     => $update_existing,
			'company'    => wp_json_encode( $company ),
		);

		// Send request.
		$response = $this->helpers->api_request(
			'add_update_company',
			$body,
			$action_data
		);

		// Maybe add new company ID to saved options.
		$new_company_id = $response['data']['id'] ?? false;
		if ( false !== $new_company_id && $new_company_id !== $company_id ) {
			$company_name = $response['data']['company_name'] ?? $company_name;
			$this->helpers->add_new_company_to_saved_options( $new_company_id, $company_name );
		}

		if ( ! empty( $this->complete_with_notice_messages ) ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( implode( ', ', $this->complete_with_notice_messages ) );

			return null;
		}

		return true;
	}

	/**
	 * Add optional fields to company.
	 *
	 * @param array $company
	 *
	 * @return array
	 */
	private function add_optional_fields( $company ) {

		// Map field code to helper validation method.
		$map = array(
			'EMAIL'   => 'get_valid_email',
			'PHONE'   => 'get_valid_phone_number',
			'FAX'     => 'get_valid_phone_number',
			'WEBSITE' => 'get_valid_url',
		);

		// Build optional fields.
		foreach ( $map as $key => $validation_method ) {

			$value = $this->get_parsed_meta_value( $key, false );
			if ( empty( $value ) ) {
				continue;
			}

			// Validate field via helper methods if not delete value.
			$validated = $this->helpers->is_delete_value( $value ) ? '' : $this->helpers->$validation_method( $value );
			if ( false === $validated ) {
				$this->complete_with_notice_messages[] = sprintf(
					_x( 'Invalid %1$s: "%2$s"', 'Keap', 'uncanny-automator' ),
					strtolower( $key ),
					$value
				);
				continue;
			}

			// Add to company.
			switch ( $key ) {
				case 'EMAIL':
					$company['email_address'] = (object) array(
						'email' => $validated,
						'field' => 'EMAIL1',
						//'email_opt_status' =>
						//'is_opt_in' => true,
						//'opt_in_reason' => '',
					);
					break;
				case 'PHONE':
					$company['phone_number'] = (object) array(
						'field'  => 'PHONE1',
						'number' => $validated,
						'type'   => 'Work',
					);
					break;
				case 'FAX':
					$company['fax_number'] = (object) array(
						'field'  => 'FAX1',
						'number' => $validated,
						'type'   => 'Work',
					);
					break;
				case 'WEBSITE':
					$company['website'] = $validated;
					break;
			}
		}

		// Notes
		$notes = $this->get_parsed_meta_value( 'NOTES', false );
		$notes = ! empty( $notes ) ? sanitize_textarea_field( $notes ) : '';
		if ( ! empty( $notes ) ) {
			$company['notes'] = $this->helpers->maybe_remove_delete_value( $notes, false );
		}

		return $company;
	}

}
