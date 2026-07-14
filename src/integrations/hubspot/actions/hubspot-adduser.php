<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class HUBSPOT_ADDUSER
 *
 * @deprecated Use HUBSPOT_ADD_USER instead.
 *
 * @package Uncanny_Automator
 *
 * @property HubSpot_App_Helpers $helpers
 * @property HubSpot_Api_Caller $api
 */
class HUBSPOT_ADDUSER extends App_Action {

	use HubSpot_Contact_Fields;

	/**
	 * Set up action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'HUBSPOT' );
		$this->set_action_code( 'HUBSPOTADDUSER' );
		$this->set_action_meta( 'HUBSPOTCONTACT' );
		$this->set_requires_user( true );
		$this->set_is_deprecated( true );
		$this->set_sentence(
			sprintf(
				// translators: %s: Action meta key for the user field
				esc_html_x( 'Add/Update {{the user:%s}} in HubSpot', 'HubSpot', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add/Update {{the user}} in HubSpot', 'HubSpot', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Load options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_legacy_custom_fields_option_config(),
			$this->helpers->get_update_option_config(),
		);
	}

	/**
	 * Process action
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
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

		// Use trait's process_additional_fields (same repeater structure: FIELD_NAME + FIELD_VALUE).
		$custom_fields_json = $this->get_parsed_meta_value( 'CUSTOM_FIELDS', '' );
		$custom_properties  = $this->process_additional_fields( $custom_fields_json );
		$properties         = array_merge( $properties, $custom_properties );

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

		$this->api->create_contact( $properties, $update, $action_data );

		return true;
	}

	/**
	 * Get legacy custom fields repeater option config.
	 *
	 * Uses cached fields from the new architecture but maintains
	 * the original option structure for backwards compatibility.
	 *
	 * @return array
	 */
	private function get_legacy_custom_fields_option_config() {
		return array(
			'option_code'       => 'CUSTOM_FIELDS',
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'label'             => esc_html_x( 'Custom fields', 'HubSpot', 'uncanny-automator' ),
			'description'       => esc_html_x( 'Leaving a field value empty will not update the field. To delete a value from a field, set its value to [delete], including the square brackets.', 'HubSpot', 'uncanny-automator' ),
			'required'          => false,
			'fields'            => array(
				array(
					'option_code'           => 'FIELD_NAME',
					'label'                 => esc_html_x( 'Field', 'HubSpot', 'uncanny-automator' ),
					'input_type'            => 'select',
					'supports_tokens'       => false,
					'supports_custom_value' => false,
					'required'              => true,
					'options'               => $this->get_all_field_options(),
				),
				array(
					'option_code'     => 'FIELD_VALUE',
					'label'           => esc_html_x( 'Value', 'HubSpot', 'uncanny-automator' ),
					'input_type'      => 'text',
					'supports_tokens' => true,
					'required'        => false,
				),
			),
			'add_row_button'    => esc_html_x( 'Add field', 'HubSpot', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove field', 'HubSpot', 'uncanny-automator' ),
		);
	}

	/**
	 * Get all field options for dropdown.
	 *
	 * @return array
	 */
	private function get_all_field_options() {
		// Pass true to refresh - maintains legacy behavior of always fetching fresh data.
		$fields  = $this->helpers->get_cached_fields( true );
		$options = array(
			array(
				'value' => '',
				'text'  => esc_html_x( 'Select a field', 'HubSpot', 'uncanny-automator' ),
			),
		);

		foreach ( $fields as $field ) {
			$options[] = array(
				'value' => $field['option_code'],
				'text'  => $field['label'],
			);
		}

		return $options;
	}
}
