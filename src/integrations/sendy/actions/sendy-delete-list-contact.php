<?php

namespace Uncanny_Automator\Integrations\Sendy;

/**
 * Class SENDY_DELETE_LIST_CONTACT
 *
 * @package Uncanny_Automator
 * @property Sendy_App_Helpers $helpers
 * @property Sendy_Api_Caller $api
 */
class SENDY_DELETE_LIST_CONTACT extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'SENDY' );
		$this->set_action_code( 'SENDY_DELETE_LIST_CONTACT' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/sendy/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Contact Email,%2$s: List Name
				esc_attr_x( 'Delete {{a contact:%1$s}} from {{a list:%2$s}}', 'Sendy', 'uncanny-automator' ),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Delete {{a contact}} from {{a list}}', 'Sendy', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_list_field_config(),
			$this->helpers->get_email_field_config( $this->get_action_meta() ),
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

		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$list  = $this->helpers->get_list_from_parsed( $parsed, 'LIST' );

		$this->api->delete_contact_from_list( $email, $list, $action_data );

		return true;
	}
}
