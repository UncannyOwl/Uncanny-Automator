<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * ConvertKit - Create or update a subscriber (v4 only)
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class CONVERTKIT_SUBSCRIBER_CREATE_UPDATE extends \Uncanny_Automator\Recipe\App_Action {

	use ConvertKit_Subscriber_Tokens_Trait;

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CONVERTKIT' );
		$this->set_action_code( 'CONVERTKIT_SUBSCRIBER_CREATE_UPDATE' );
		$this->set_action_meta( 'CONVERTKIT_SUBSCRIBER_CREATE_UPDATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a subscriber}}', 'ConvertKit', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the email address
				esc_attr_x( 'Create or update {{a subscriber:%1$s}}', 'ConvertKit', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Requires OAuth (v4) connection.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return ! $this->helpers->is_v3();
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_option_config( $this->get_action_meta() ),
			$this->helpers->get_first_name_option_config(),
			$this->get_custom_fields_repeater_config(),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return $this->get_subscriber_token_definitions();
	}

	////////////////////////////////////////////////////////////
	// Custom fields repeater
	////////////////////////////////////////////////////////////

	/**
	 * Get the repeater config for custom fields.
	 *
	 * @return array
	 */
	private function get_custom_fields_repeater_config() {
		return array(
			'option_code'     => 'CONVERTKIT_CUSTOM_FIELDS',
			'input_type'      => 'repeater',
			'label'           => esc_html_x( 'Custom fields', 'ConvertKit', 'uncanny-automator' ),
			'required'        => false,
			'hide_actions'    => true,
			'fields'          => array(
				array(
					'option_code' => 'CONVERTKIT_FIELD_KEY',
					'label'       => esc_html_x( 'Key', 'ConvertKit', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
					'read_only'   => true,
					'is_hidden'   => true,
				),
				array(
					'option_code' => 'CONVERTKIT_FIELD_NAME',
					'label'       => esc_html_x( 'Field', 'ConvertKit', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => true,
					'read_only'   => true,
				),
				array(
					'option_code' => 'CONVERTKIT_FIELD_VALUE',
					'label'       => esc_html_x( 'Value', 'ConvertKit', 'uncanny-automator' ),
					'input_type'  => 'text',
					'required'    => false,
				),
				array(
					'option_code'   => 'CONVERTKIT_UPDATE_FIELD',
					'label'         => esc_html_x( 'Update', 'ConvertKit', 'uncanny-automator' ),
					'input_type'    => 'checkbox',
					'is_toggle'     => true,
					'required'      => false,
					'default_value' => true,
					'description'   => esc_html_x( 'Enable to update this field', 'ConvertKit', 'uncanny-automator' ),
				),
			),
			'ajax'            => array(
				'event'          => 'on_load',
				'endpoint'       => 'automator_convertkit_custom_fields_handler',
				'mapping_column' => 'CONVERTKIT_FIELD_KEY',
			),
			'relevant_tokens' => array(),
		);
	}

	////////////////////////////////////////////////////////////
	// Process
	////////////////////////////////////////////////////////////

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
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email      = $this->helpers->require_valid_email( $parsed[ $this->get_action_meta() ] ?? '' );
		$first_name = sanitize_text_field( $parsed['FIRST_NAME'] ?? '' );

		// Parse custom fields from repeater rows.
		$repeater_data = json_decode( $action_data['meta']['CONVERTKIT_CUSTOM_FIELDS'] ?? '[]', true );
		$custom_fields = $this->parse_custom_fields( $repeater_data );

		$body = array(
			'action'        => 'create_update_subscriber',
			'email_address' => $email,
			'first_name'    => $first_name,
			'fields'        => wp_json_encode( $custom_fields ),
		);

		$response = $this->api->api_request( $body, $action_data );

		$this->hydrate_tokens( $this->hydrate_subscriber_tokens( $response, 'created_at' ) );

		return true;
	}

	/**
	 * Parse custom fields from repeater rows.
	 *
	 * Only includes fields where the update toggle is enabled
	 * and a value has been provided.
	 *
	 * @param array $rows The decoded repeater rows.
	 *
	 * @return array Key-value pairs of custom fields.
	 */
	private function parse_custom_fields( $rows ) {

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$fields = array();

		foreach ( $rows as $row ) {
			$update = $row['CONVERTKIT_UPDATE_FIELD'] ?? false;
			$key    = $row['CONVERTKIT_FIELD_KEY'] ?? '';
			$value  = $row['CONVERTKIT_FIELD_VALUE'] ?? '';

			if ( empty( $key ) || ! $update ) {
				continue;
			}

			$fields[ $key ] = $value;
		}

		return $fields;
	}
}
