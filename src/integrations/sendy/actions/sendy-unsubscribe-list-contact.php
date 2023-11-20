<?php

namespace Uncanny_Automator\Integrations\Sendy;

/**
 * Class SENDY_UNSUBSCRIBE_LIST_CONTACT
 *
 * @package Uncanny_Automator
 */
class SENDY_UNSUBSCRIBE_LIST_CONTACT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'SENDY' );
		$this->set_action_code( 'SENDY_UNSUBSCRIBE_LIST_CONTACT' );
		$this->set_action_meta( 'CONTACT_EMAIL' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/sendy/' ) );
		$this->set_requires_user( false );
		/* translators: Contact Email, List Name */
		$this->set_sentence( sprintf( esc_attr_x( 'Unsubscribe {{a contact:%1$s}} from {{a list:%2$s}}', 'Sendy', 'uncanny-automator' ), $this->get_action_meta(), 'LIST:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Unsubscribe {{a contact}} from {{a list}}', 'Sendy', 'uncanny-automator' ) );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array();

		$fields[] = array(
			'option_code'           => 'LIST',
			'label'                 => _x( 'List', 'Sendy', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'is_ajax'               => true,
			'endpoint'              => 'automator_sendy_get_lists',
			'supports_custom_value' => false,
		);

		$fields[] = array(
			'option_code' => $this->action_meta,
			'label'       => _x( 'Email', 'Sendy', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);

		return $fields;
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

		$email    = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );
		$list     = $this->helpers->get_list_from_parsed( $parsed, 'LIST' );
		$response = $this->helpers->unsubscribe_contact_from_list( $email, $list, $action_data );

		return true;
	}

}
