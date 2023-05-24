<?php
namespace Uncanny_Automator;

/**
 * Class Zoho_Campaigns_List_Add
 *
 * @package Uncanny_Automator
 */
class Zoho_Campaigns_List_Add {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

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

		$this->set_action_code( 'ZOHO_CAMPAIGNS_LIST_ADD' );

		$this->set_action_meta( 'ZOHO_CAMPAIGNS_LIST_ADD_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/zoho-campaigns/' ) );

		$this->set_requires_user( false );

		/* translators: Action sentence */
		$this->set_sentence( sprintf( esc_attr__( 'Create {{a list:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		$this->set_readable_sentence( esc_attr__( 'Create {{a list}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'LIST_ID' => array(
					'name' => esc_attr__( 'List ID', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);

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
							'label'       => esc_attr__( 'List name', 'uncanny-automator' ),
							'input_type'  => 'text',
							'required'    => true,
						),
						array(
							'option_code' => 'SIGNUP_FORM',
							/* translators: Action field */
							'label'       => esc_attr__( 'Signup form', 'uncanny-automator' ),
							'input_type'  => 'select',
							'options'     => array(
								'private' => esc_html__( 'Private', 'uncanny-automator' ),
								'public'  => esc_html__( 'Public', 'uncanny-automator' ),
							),
							'required'    => true,
						),
						array(
							'option_code' => 'EMAILS',
							/* translators: Action field */
							'label'       => esc_attr__( 'Emails', 'uncanny-automator' ),
							'description' => esc_attr__( 'Max 10 emails separated by comma', 'uncanny-automator' ),
							'input_type'  => 'text',
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

		$list_name   = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;
		$email_ids   = isset( $parsed['EMAILS'] ) ? sanitize_text_field( $parsed['EMAILS'] ) : 0;
		$signup_form = isset( $parsed['SIGNUP_FORM'] ) ? sanitize_text_field( $parsed['SIGNUP_FORM'] ) : 0;

		try {

			$this->set_helpers( new Zoho_Campaigns_Helpers( false ) );

			$this->get_helpers()->require_dependency( 'client/actions/zoho-campaigns-actions' );
			$this->get_helpers()->require_dependency( 'client/auth/zoho-campaigns-client-auth' );

			$zoho_actions = new Zoho_Campaigns_Actions( Api_Server::get_instance(), new Zoho_Campaigns_Client_Auth() );

			$response = $zoho_actions->list_add(
				array(
					'list_name'   => $list_name,
					'email_ids'   => $email_ids,
					'signup_form' => $signup_form,
				),
				$action_data
			);

			$this->hydrate_tokens(
				array(
					'LIST_ID' => isset( $response['data']['listkey'] ) ? $response['data']['listkey'] : '',
				)
			);

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

			return false;

		}

		Automator()->complete->action( $user_id, $action_data, $recipe_id );

	}

}
