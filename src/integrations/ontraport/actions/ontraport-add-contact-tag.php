<?php

namespace Uncanny_Automator\Integrations\Ontraport;

/**
 * Class Ontraport_Add_Contact_Tag
 *
 * @package Uncanny_Automator
 *
 * @property Ontraport_App_Helpers $helpers
 * @property Ontraport_Api_Caller $api
 */
class Ontraport_Add_Contact_Tag extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Spins up new action inside "ONTRAPORT" integration.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'ONTRAPORT' );
		$this->set_action_code( 'ONTRAPORT_ADD_CONTACT_TAG_CODE' );
		$this->set_action_meta( 'ONTRAPORT_ADD_CONTACT_TAG_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/ontraport/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a contact}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Tag name, %2$s: Email address
				esc_attr_x( 'Add {{a tag:%1$s}} to {{a contact:%2$s}}', 'Ontraport', 'uncanny-automator' ),
				$this->get_action_meta(),
				'CONTACT_EMAIL:' . $this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$tags = array(
			'option_code'              => $this->get_action_meta(),
			'label'                    => esc_html_x( 'Tags', 'Ontraport', 'uncanny-automator' ),
			'input_type'               => 'select',
			'multiple'                 => true,
			'required'                 => true,
			'ajax'                     => array(
				'endpoint' => 'automator_ontraport_list_tags',
				'event'    => 'on_load',
			),
			'supports_multiple_values' => true,
			'options'                  => array(),
		);

		$email = $this->helpers->get_email_field( 'CONTACT_EMAIL' );

		return array( $tags, $email );
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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = $this->helpers->validate_email( $this->get_parsed_meta_value( 'CONTACT_EMAIL', '' ) );
		$tags  = $this->get_parsed_meta_value( $this->get_action_meta(), '' );

		$body = array(
			'email_address' => $email,
			'tags'          => implode( ',', (array) json_decode( $tags, true ) ),
		);

		$this->api->send_request( 'contact_add_tag', $body, $action_data );

		return true;
	}
}
