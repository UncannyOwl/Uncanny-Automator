<?php

namespace Uncanny_Automator\Integrations\Get_Response;

/**
 * Class GET_RESPONSE_DELETE_CONTACT
 *
 * @package Uncanny_Automator
 *
 * @property Get_Response_App_Helpers $helpers
 * @property Get_Response_Api_Caller $api
 */
class GET_RESPONSE_DELETE_CONTACT extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'GETRESPONSE' );
		$this->set_action_code( 'GR_DELETE_CONTACT_CODE' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/getresponse/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: Contact Email
				esc_attr_x( 'Remove {{a contact:%1$s}}', 'GetResponse', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a contact}}', 'GetResponse', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Email', 'GetResponse', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Required field - throws error if not set and valid.
		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

		// Send request.
		$this->api->api_request(
			array(
				'action' => 'delete_contact',
				'email'  => $email,
			),
			$action_data
		);

		return true;
	}
}
