<?php

namespace Uncanny_Automator\Integrations\Helpscout;

use Exception;

/**
 * Class Helpscout_Customer_Properties_Update
 *
 * @package Uncanny_Automator
 * @method Helpscout_Helpers get_item_helpers()
 */
class Helpscout_Customer_Properties_Update extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'HELPSCOUT' );
		$this->set_action_code( 'HELPSCOUT_CUSTOMER_PROPERTIES_UPDATE' );
		$this->set_action_meta( 'HELPSCOUT_CUSTOMER_PROPERTIES_UPDATE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/helpscout/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Customer, %2$s: Properties */
				esc_html_x( 'Update {{the properties:%2$s}} of {{a customer:%1$s}}', 'HelpScout', 'uncanny-automator' ),
				$this->get_action_meta(),
				'FIELDS_NON_EXISTENT:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Update {{the properties}} of {{a customer}}', 'HelpScout', 'uncanny-automator' )
		);

		$this->set_background_processing( true );
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Email', 'HelpScout', 'uncanny-automator' ),
				'input_type'            => 'email',
				'supports_custom_value' => true,
				'required'              => true,
			),
			array(
				'option_code'           => 'FIELDS',
				'label'                 => esc_html_x( 'Property', 'HelpScout', 'uncanny-automator' ),
				'input_type'            => 'repeater',
				'relevant_tokens'       => array(),
				'supports_custom_value' => true,
				'required'              => true,
				'fields'                => array(
					array(
						'option_code' => 'PROPERTY_SLUG',
						'label'       => esc_html_x( 'Property ID', 'HelpScout', 'uncanny-automator' ),
						'input_type'  => 'text',
						'read_only'   => true,
					),
					array(
						'option_code' => 'PROPERTY_NAME',
						'label'       => esc_html_x( 'Name', 'HelpScout', 'uncanny-automator' ),
						'input_type'  => 'text',
						'read_only'   => true,
					),
					array(
						'option_code' => 'PROPERTY_VALUE',
						'label'       => esc_html_x( 'Value', 'HelpScout', 'uncanny-automator' ),
						'input_type'  => 'text',
						'required'    => false,
					),
				),
				'ajax'                  => array(
					'endpoint'       => 'automator_helpscout_fetch_properties',
					'event'          => 'on_load',
					'mapping_column' => 'PROPERTY_SLUG',
				),
				'hide_actions'          => true,
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
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$customer_email = $parsed[ $this->get_action_meta() ];

		if ( ! filter_var( $customer_email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( esc_html_x( 'Invalid email address: ', 'Help Scout', 'uncanny-automator' ) . esc_html( $customer_email ), 400 );
		}

		$fields = $this->process_fields( $parsed['FIELDS'] );

		$decoded = json_decode( $fields, true );

		// The json_decode will generate a null value if its failing.
		if ( null === $decoded ) {
			$action_data['complete_with_notice'] = true;
			\Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html_x( 'The JSON string generated from the repeater field is not valid.', 'Help Scout', 'uncanny-automator' ) );
			return false;
		}

		// Do not allow empty repeater fields. Atleast one field must be filled-out.
		if ( empty( $decoded ) ) {
			$action_data['complete_with_notice'] = true;
			\Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html_x( 'Incomplete Information: No updates were made as fields were left empty.', 'Help Scout', 'uncanny-automator' ) );
			return false;
		}

		$this->get_item_helpers()->api_request(
			array(
				'action'         => 'update_customer_properties',
				'customer_email' => $customer_email,
				'fields'         => $fields,
			),
			$action_data
		);

		return true;
	}

	/**
	 * @param string $fields
	 *
	 * @return string
	 */
	private function process_fields( $fields ) {

		$fields_arr = (array) json_decode( $fields, true );

		$parameters = array();

		foreach ( $fields_arr as $field ) {
			// Prevents accidental data erasure.
			if ( isset( $field['PROPERTY_VALUE'] ) && '' !== $field['PROPERTY_VALUE'] ) {
				$parameters[] = array(
					'id'    => $field['PROPERTY_SLUG'],
					'value' => $field['PROPERTY_VALUE'],
				);
			}
		}

		return wp_json_encode( $parameters );
	}
}
