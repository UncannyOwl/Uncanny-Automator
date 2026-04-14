<?php

namespace Uncanny_Automator\Integrations\Mautic;

/**
 * Creates or updates a Mautic contact with the specified email and custom fields.
 *
 * @since 5.0
 *
 * @property Mautic_App_Helpers $helpers
 * @property Mautic_Api_Caller $api
 */
class CONTACT_UPSERT extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Configure the action code, meta key, sentence templates, and user requirement.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'MAUTIC' );
		$this->set_action_code( 'CONTACT_UPSERT' );
		$this->set_action_meta( 'CONTACT_UPSERT_META' );
		$this->set_requires_user( false );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Mautic', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the contact email
				esc_attr_x(
					'Create or update {{a contact:%1$s}}',
					'Mautic',
					'uncanny-automator'
				),
				'NON_EXISTING:' . $this->get_action_meta()
			)
		);
	}

	/**
	 * Define the option fields for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_option_config(),
			$this->get_fields_repeater_config(),
		);
	}

	/**
	 * Execute the upsert contact API call with the parsed email and field values.
	 *
	 * @param int                              $user_id     The WordPress user ID.
	 * @param mixed[]                          $action_data The action configuration data.
	 * @param int                              $recipe_id   The recipe ID.
	 * @param mixed[]                          $args        Additional arguments including action_meta.
	 * @param array{FIELDS:string,EMAIL:string} $parsed     The parsed token values.
	 *
	 * @return bool True on success.
	 * @throws \Exception For invalid params, or if the API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email  = $this->helpers->validate_email( $parsed['EMAIL'] ?? '' );
		$fields = $this->validate_fields( $parsed['FIELDS'] ?? '' );

		$fields_item = array(
			'email'              => $email,
			'overwriteWithBlank' => false, // Skip blank fields
		);

		foreach ( $fields as $field ) {
			$field = (array) $field;
			if ( isset( $field['ALIAS'] ) && isset( $field['VALUE'] ) ) {
				$fields_item[ $field['ALIAS'] ] = $field['VALUE'];
			}
		}

		$this->api->api_request(
			array(
				'action' => 'upsert',
				'fields' => wp_json_encode( $fields_item ),
			),
			$action_data
		);

		return true;
	}

	/**
	 * Get the repeater config for the fields option.
	 *
	 * @return array
	 */
	private function get_fields_repeater_config() {
		return array(
			'option_code'     => 'FIELDS',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Field', 'Mautic', 'uncanny-automator' ),
			'description'     => '',
			'required'        => true,
			'default_value'   => array(
				array(
					'ALIAS' => esc_html_x( 'Loading fields...', 'Mautic', 'uncanny-automator' ),
					'VALUE' => esc_html_x( 'Loading values...', 'Mautic', 'uncanny-automator' ),
				),
			),
			'fields'          => array(
				array(
					'option_code' => 'ALIAS',
					'label'       => esc_html_x( 'Field', 'Mautic', 'uncanny-automator' ),
					'input_type'  => 'text',
					'read_only'   => true,
				),
				array(
					'option_code' => 'VALUE',
					'label'       => esc_html_x( 'Value', 'Mautic', 'uncanny-automator' ),
					'input_type'  => 'text',
				),
			),
			'ajax'            => array(
				'event'          => 'on_load',
				'endpoint'       => 'automator_mautic_render_contact_fields',
				'mapping_column' => 'ALIAS',
			),
			'hide_actions'    => true,
		);
	}

	/**
	 * Validate the fields.
	 *
	 * @param string $fields The fields to validate.
	 *
	 * @return array The validated fields.
	 * @throws \Exception If the fields are empty.
	 */
	private function validate_fields( $fields ) {
		if ( ! empty( $fields ) ) {
			$fields = (array) json_decode( $fields, true );
		}

		if ( empty( $fields ) ) {
			throw new \Exception( 'Fields should not be empty', 500 );
		}

		return $fields;
	}
}
