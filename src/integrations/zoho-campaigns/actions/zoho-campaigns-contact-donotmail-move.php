<?php
namespace Uncanny_Automator;

/**
 * Class Zoho_Campaigns_Contact_Donotmail_Move
 *
 * @package Uncanny_Automator
 */
class Zoho_Campaigns_Contact_Donotmail_Move {

	use Recipe\Actions;

	/**
	 * Method __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setups the Action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'ZOHO_CAMPAIGNS' );

		$this->set_action_code( 'ZOHO_CAMPAIGNS_CONTACT_DONOTMAIL_MOVE' );

		$this->set_action_meta( 'ZOHO_CAMPAIGNS_CONTACT_DONOTMAIL_MOVE_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/zoho-campaigns/' ) );

		$this->set_requires_user( false );

		/* translators: Action sentence */
		$this->set_sentence( sprintf( esc_attr__( 'Move {{a contact:%1$s}} to Do-Not-Mail', 'uncanny-automator' ), $this->get_action_meta() ) );

		$this->set_readable_sentence( esc_attr__( 'Move {{a contact}} to Do-Not-Mail', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->register_action();

	}

	/**
	 * Loads options.
	 *
	 * @return void.
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code' => $this->get_action_meta(),
							/* translators: Action field */
							'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
							'input_type'  => 'email',
							'options'     => array(),
							'required'    => true,
						),
					),
				),
			)
		);

	}

	/**
	 * Processes the action.
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$contact = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;

		try {

			$this->set_helpers( new Zoho_Campaigns_Helpers( false ) );

			$this->get_helpers()->require_dependency( 'client/actions/zoho-campaigns-actions' );
			$this->get_helpers()->require_dependency( 'client/auth/zoho-campaigns-client-auth' );

			$zoho_actions = new Zoho_Campaigns_Actions( Api_Server::get_instance(), new Zoho_Campaigns_Client_Auth() );

			$response = $zoho_actions->contact_donotmail_move( $contact, $action_data );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

			return false;

		}

		Automator()->complete->action( $user_id, $action_data, $recipe_id );

		return true;

	}

}
