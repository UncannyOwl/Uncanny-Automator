<?php
namespace Uncanny_Automator;

/**
 * Class Zoho_Campaigns_Contact_List_Unsub
 *
 * @package Uncanny_Automator
 */
class Zoho_Campaigns_Contact_List_Unsub {

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

		$this->set_action_code( 'ZOHO_CAMPAIGNS_CONTACT_LIST_UNSUB' );

		$this->set_action_meta( 'ZOHO_CAMPAIGNS_CONTACT_LIST_UNSUB_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/zoho-campaigns/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Unsubscribe {{a contact:%1$s}} from {{a list:%2$s}}', 'uncanny-automator' ),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr__( 'Unsubscribe {{a contact}} from {{a list}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'LIST_NAME' => array(
					'name' => __( 'List name', 'uncanny-automator-pro' ),
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
							'option_code'              => 'LIST',
							/* translators: Action field */
							'label'                    => esc_attr__( 'List', 'uncanny-automator' ),
							'custom_value_description' => esc_attr__( 'Contact email address', 'uncanny-automator' ),
							'input_type'               => 'select',
							'options'                  => array(),
							'ajax'                     => array(
								'event'    => 'on_load',
								'endpoint' => 'automator-fetch-lists',
							),
							'required'                 => true,
							'options_show_id'          => false,
							'token_name'               => esc_attr__( 'List ID', 'uncanny-automator' ),
						),
						array(
							'option_code' => $this->get_action_meta(),
							/* translators: Action field */
							'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
							'input_type'  => 'email',
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

		$list    = isset( $parsed['LIST'] ) ? sanitize_text_field( $parsed['LIST'] ) : '';
		$contact = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';

		try {

			$this->set_helpers( new Zoho_Campaigns_Helpers( false ) );

			$this->get_helpers()->require_dependency( 'client/actions/zoho-campaigns-actions' );
			$this->get_helpers()->require_dependency( 'client/auth/zoho-campaigns-client-auth' );

			$authentication = new Zoho_Campaigns_Client_Auth();
			$zoho_action    = new Zoho_Campaigns_Actions( Api_Server::get_instance(), $authentication );

			$response = $zoho_action->contact_list_unsub(
				array(
					'contact'  => $contact,
					'list_key' => $list,
				),
				$action_data
			);

			$this->hydrate_tokens(
				array(
					'LIST_NAME' => $action_data['meta']['LIST_readable'],
				)
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

}
