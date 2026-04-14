<?php

namespace Uncanny_Automator\Integrations\Ontraport;

/**
 * Class Ontraport_Delete_Contact
 *
 * @package Uncanny_Automator
 *
 * @property Ontraport_App_Helpers $helpers
 * @property Ontraport_Api_Caller $api
 */
class Ontraport_Delete_Contact extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Spins up new action inside "ONTRAPORT" integration.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'ONTRAPORT' );
		$this->set_action_code( 'ONTRAPORT_DELETE_CONTACT_CODE' );
		$this->set_action_meta( 'ONTRAPORT_DELETE_CONTACT_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/ontraport/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_attr_x( 'Delete {{a contact}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Email address
				esc_attr_x( 'Delete {{a contact:%1$s}}', 'Ontraport', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_field( $this->get_action_meta() ),
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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = $this->helpers->validate_email( $this->get_parsed_meta_value( $this->get_action_meta(), '' ) );

		$body = array(
			'contact_email' => $email,
		);

		$this->api->send_request( 'contact_delete', $body, $action_data );

		return true;
	}
}
