<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class HUBSPOT_ADD_USER
 *
 * Adds the current WordPress user to HubSpot as a contact.
 *
 * @package Uncanny_Automator
 *
 * @property HubSpot_App_Helpers $helpers
 * @property HubSpot_Api_Caller $api
 */
class HUBSPOT_ADD_USER extends App_Action {

	use HubSpot_Contact_Fields;

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'HUBSPOT' );
		$this->set_action_code( 'HUBSPOT_ADD_USER' );
		$this->set_action_meta( 'HUBSPOT_ADD_USER_META' );
		$this->set_requires_user( true );
		$this->set_sentence(
			sprintf(
				// translators: %s: Action meta key for the user field.
				esc_html_x( 'Add/Update {{the user:%s}} in HubSpot', 'HubSpot', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add/Update {{the user}} in HubSpot', 'HubSpot', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Load options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_contact_fields_option_config(),
			$this->get_custom_fields_option_config(),
			$this->get_additional_fields_option_config(),
			$this->helpers->get_update_option_config(),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int $user_id The user ID.
	 * @param array $action_data The action data.
	 * @param int $recipe_id The recipe ID.
	 * @param array $args The arguments.
	 * @param array $parsed The parsed data.
	 *
	 * @return bool
	 * @throws Exception If the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			throw new Exception( esc_html_x( 'User not found.', 'HubSpot', 'uncanny-automator' ) );
		}

		$update = true;
		if ( ! empty( $action_data['meta']['UPDATE'] ) ) {
			$update = filter_var( $action_data['meta']['UPDATE'], FILTER_VALIDATE_BOOLEAN );
		}

		// Start with user data properties.
		$properties = array(
			array(
				'property' => 'email',
				'value'    => $user->user_email,
			),
			array(
				'property' => 'firstname',
				'value'    => $user->first_name,
			),
			array(
				'property' => 'lastname',
				'value'    => $user->last_name,
			),
		);

		// Add contact fields from transposed repeater (HubSpot-defined contact info).
		$contact_fields_json = $this->get_parsed_meta_value( 'CONTACT_FIELDS', '' );
		$contact_properties  = $this->process_contact_fields( $contact_fields_json );
		$properties          = array_merge( $properties, $contact_properties );

		// Add custom fields from transposed repeater (user-defined fields).
		$custom_fields_json = $this->get_parsed_meta_value( 'CUSTOM_FIELDS', '' );
		$custom_properties  = $this->process_custom_fields( $custom_fields_json );
		$properties         = array_merge( $properties, $custom_properties );

		// Add additional fields from legacy-style repeater.
		$additional_fields_json = $this->get_parsed_meta_value( 'ADDITIONAL_FIELDS', '' );
		$additional_properties  = $this->process_additional_fields( $additional_fields_json );
		$properties             = array_merge( $properties, $additional_properties );

		// Allow filtering of properties.
		$properties = apply_filters(
			'automator_hubspot_add_user_properties',
			$properties,
			array(
				'user_id'     => $user_id,
				'action_data' => $action_data,
				'recipe_id'   => $recipe_id,
				'args'        => $args,
			)
		);

		// Make API request.
		$this->api->create_contact( $properties, $update, $action_data );

		// Log any field processing errors as warnings (non-fatal).
		if ( $this->has_field_errors() ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( $this->get_field_errors() );
		}

		return true;
	}
}
