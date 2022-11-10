<?php

namespace Uncanny_Automator;

/**
 * Class JETCRM_DELETE_CONTACT
 * @package Uncanny_Automator
 */
class JETCRM_DELETE_CONTACT {


	use Recipe\Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		 $this->setup_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		 $this->set_integration( 'JETCRM' );
		$this->set_action_code( 'JETCRM_DELETE_CONTACT' );
		$this->set_action_meta( 'JETCRM_CONTACT' );
		$this->set_requires_user( false );
		/* translators: Action - JetPack CRM */
		$this->set_sentence( sprintf( esc_attr__( 'Delete {{a contact:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - JetPack CRM */
		$this->set_readable_sentence( esc_attr__( 'Delete {{a contact}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => $this->get_action_meta(),
							'label'       => esc_attr__( 'Contact ID or email', 'uncanny-automator' ),
						)
					),
				),
			)
		);

	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$contact = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		if ( empty( $contact ) ) {
			return;
		}

		global $wpdb;
		$contact_id = $wpdb->get_var( $wpdb->prepare( "SELECT `ID` FROM `{$wpdb->prefix}zbs_contacts` WHERE ID = %d OR zbsc_email LIKE %s", $contact, $contact ) );

		if ( ! empty( $contact_id ) ) {
			zeroBS_deleteCustomer( $contact_id, false );
			Automator()->complete->action( $user_id, $action_data, $recipe_id );

			return;
		}
		$action_data['do-nothing']           = true;
		$action_data['complete_with_errors'] = true;
		Automator()->complete->action( $user_id, $action_data, $recipe_id, sprintf( __( 'Contact was not found matching (%s).', 'uncanny-automator' ), $contact ) );
	}

}
