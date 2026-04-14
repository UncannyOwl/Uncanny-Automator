<?php

namespace Uncanny_Automator\Integrations\Drip;

use Exception;

/**
 * Class DRIP_CREATE_SUBSCRIBER
 *
 * @package Uncanny_Automator
 *
 * @property Drip_App_Helpers $helpers
 * @property Drip_Api_Caller $api
 */
class DRIP_CREATE_SUBSCRIBER extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'DRIP' );
		$this->set_action_code( 'CREATE_SUBSCRIBER' );
		$this->set_action_meta( 'EMAIL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/drip/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a subscriber}}', 'Drip', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: email
				esc_attr_x( 'Create or update {{a subscriber:%1$s}}', 'Drip', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'SUBSCRIBER_ID' => array(
					'name' => esc_html_x( 'Drip subscriber ID', 'Drip', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_option_config( $this->action_meta ),
			$this->get_custom_field_repeater_config(),
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
	 * @throws Exception If the API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = $this->helpers->validate_email( $parsed[ $this->action_meta ] ?? '' );

		// Generate the subscriber data.
		$fields          = json_decode( $action_data['meta']['FIELDS'], true );
		$fields          = $this->parse_repeater_fields( $fields, $recipe_id, $user_id, $args );
		$subscriber      = $this->build_subscriber( $email, $fields );
		$subscriber_json = wp_json_encode( $subscriber );

		// Create the subscriber.
		$response   = $this->api->create_subscriber( $subscriber_json, $action_data );
		$subscriber = array_shift( $response['data']['subscribers'] );

		$this->hydrate_tokens(
			array(
				'SUBSCRIBER_ID' => $subscriber['id'] ?? '',
			)
		);

		return true;
	}

	/**
	 * Get the custom field repeater config.
	 *
	 * @return array
	 */
	private function get_custom_field_repeater_config() {
		return array(
			'option_code'       => 'FIELDS',
			'label'             => esc_html_x( 'Fields', 'Drip', 'uncanny-automator' ),
			'input_type'        => 'repeater',
			'relevant_tokens'   => array(),
			'fields'            => array(),
			'add_row_button'    => esc_html_x( 'Add field', 'Drip', 'uncanny-automator' ),
			'remove_row_button' => esc_html_x( 'Remove field', 'Drip', 'uncanny-automator' ),
			'hide_actions'      => false,
			'ajax'              => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_drip_custom_fields_handler',
			),
		);
	}

	/**
	 * Get default subscriber fields.
	 *
	 * Maps display labels to Drip API field identifiers.
	 * Used by the API caller to build field select options.
	 *
	 * @return array
	 */
	public static function default_fields() {
		return apply_filters(
			'automator_drip_default_subscriber_fields',
			array(
				'First name'                         => 'first_name',
				'Last name'                          => 'last_name',
				'Address line 1'                     => 'address1',
				'Address line 2'                     => 'address2',
				'City'                               => 'city',
				'State'                              => 'state',
				'Zip'                                => 'zip',
				'Country'                            => 'country',
				'Phone'                              => 'phone',
				'SMS number'                         => 'sms_number',
				'SMS consent (boolean)'              => 'sms_consent',
				'Custom user ID'                     => 'user_id',
				'Time zone (in Olson format)'        => 'time_zone',
				'Lifetime value (in cents)'          => 'lifetime_value',
				'IP address (E.g. "111.111.111.11")' => 'ip_address',
				'Add tags (comma separated)'         => 'tags',
				'Remove tags (comma separated)'      => 'remove_tags',
				'Prospect (boolean)'                 => 'prospect',
				'Base lead score (integer)'          => 'base_lead_score',
				'EU consent ("granted" or "denied")' => 'eu_consent',
				'EU consent message'                 => 'eu_consent_message',
				'Status (either "active" or "unsubscribed")' => 'status',
				'Initial status (either "active" or "unsubscribed")' => 'initial_status',
			)
		);
	}

	/**
	 * Build subscriber data from parsed repeater fields.
	 *
	 * Separates default fields from custom fields and type casts
	 * values to their expected types for the Drip API.
	 *
	 * @param string $email  The subscriber email.
	 * @param array  $fields The parsed repeater fields.
	 *
	 * @return array
	 */
	private function build_subscriber( $email, $fields ) {

		$subscriber     = array();
		$default_fields = self::default_fields();

		foreach ( $fields as $field ) {

			if ( empty( $field['FIELD_NAME'] ) || ! isset( $field['FIELD_VALUE'] ) ) {
				continue;
			}

			$name  = $field['FIELD_NAME'];
			$value = $field['FIELD_VALUE'];

			if ( ! in_array( $name, $default_fields, true ) ) {
				$subscriber['custom_fields'][ $name ] = $value;
				continue;
			}

			// Type cast known fields.
			switch ( $name ) {
				case 'sms_consent':
				case 'prospect':
					$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					break;
				case 'tags':
				case 'remove_tags':
					$value = array_map( 'trim', explode( ',', $value ) );
					break;
				case 'base_lead_score':
					$value = intval( $value );
					break;
			}

			$subscriber[ $name ] = $value;
		}

		$subscriber['email'] = $email;

		return apply_filters( 'automator_drip_subscriber_fields', $subscriber, $email, $fields );
	}

	/**
	 * Parse repeater fields with token replacement.
	 *
	 * @param array $fields
	 * @param int   $recipe_id
	 * @param int   $user_id
	 * @param array $args
	 *
	 * @return array
	 */
	private function parse_repeater_fields( $fields, $recipe_id, $user_id, $args ) {

		if ( empty( $fields ) ) {
			return array();
		}

		$parsed = array();

		foreach ( $fields as $field ) {

			if ( empty( $field['FIELD_NAME'] ) || ! isset( $field['FIELD_VALUE'] ) ) {
				continue;
			}

			$key   = sanitize_text_field( Automator()->parse->text( $field['FIELD_NAME'], $recipe_id, $user_id, $args ) );
			$value = sanitize_text_field( Automator()->parse->text( $field['FIELD_VALUE'], $recipe_id, $user_id, $args ) );

			$parsed[] = array(
				'FIELD_NAME'  => $key,
				'FIELD_VALUE' => $value,
			);
		}

		return $parsed;
	}
}
