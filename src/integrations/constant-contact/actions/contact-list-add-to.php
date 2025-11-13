<?php

namespace Uncanny_Automator\Integrations\Constant_Contact;

use Exception;

/**
 * Class Uncanny_Automator\Integrations\Constant_Contact\CONTACT_LIST_ADD_TO
 *
 * @package Uncanny_Automator
 *
 * @property Constant_Contact_App_Helpers $helpers
 * @property Constant_Contact_Api_Caller $api
 */
class CONTACT_LIST_ADD_TO extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CONSTANT_CONTACT' );
		$this->set_action_code( 'CONTACT_LIST_ADD_TO' );
		$this->set_action_meta( 'CONTACT_LIST_ADD_TO_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/constant-contact/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Contact, %2$s: List
				esc_attr_x(
					'Add {{a contact:%1$s}} to {{a list:%2$s}}',
					'Constant Contact',
					'uncanny-automator'
				),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Add {{a contact}} to {{a list}}', 'Constant Contact', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_config( $this->get_action_meta() ),
			array(
				'option_code' => 'LIST',
				'label'       => esc_html_x( 'List', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'required'    => true,
				'ajax'        => array(
					'event'    => 'on_load',
					'endpoint' => 'automator_constant_contact_list_memberships_get',
				),
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
		// Get the email from the parsed data - throws an exception if invalid.
		$email = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$list  = sanitize_text_field( $parsed['LIST'] ?? '' );

		if ( empty( $list ) ) {
			throw new Exception(
				esc_html_x( 'List is required', 'Constant Contact', 'uncanny-automator' )
			);
		}

		// Add contact to list.
		$this->api->contact_list_add_to( $email, $list, $action_data );
	}
}
