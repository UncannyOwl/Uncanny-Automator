<?php
/**
 * Creates the settings page
 *
 * @since   4.8
 *
 * @package Uncanny_Automator
 */
namespace Uncanny_Automator;

/**
 * ClickUp Settings
 *
 * @package Uncanny_Automator
 * @since 4.10
 */
class Zoho_Campaigns_Settings extends Settings\Premium_Integration_Settings {

	protected $agent = null;

	public function set_agent( Zoho_Campaigns_Client $agent ) {
		$this->agent = $agent;
	}

	/**
	 * Retrieves the integration status.
	 *
	 * @return string Returns 'success' if there is a agent. Returns empty string otherwise.
	 */
	public function get_status() {
		return $this->agent->get_status();
	}

	/**
	 * Basic settings page props.
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'zoho_campaigns' );
		$this->set_icon( 'ZOHO_CAMPAIGNS' );
		$this->set_name( 'Zoho Campaigns' );

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$vars = array(
			'redacted_token' => $this->agent->get_redacted_token(), #$this->get_helper()->get_oauth_url(),
			'disconnect_url' => $this->agent->get_disconnect_url(), #$this->get_helper()->get_disconnect_url(),
			'is_connected'   => $this->agent->is_connected(), #$this->get_helper()->is_connected(),
			'connect_url'    => $this->agent->get_connect_url(), #$this->get_helper()->get_oauth_url(),
			'user'           => $this->agent->get_user(), #$this->get_helper()->get_agent(),
		);

		// Actions.
		$vars['actions'] = array(
			__( 'Create a list', 'uncanny-automator' ),
			__( 'Move a contact to Do-Not-Mail', 'uncanny-automator' ),
			__( 'Subscribe a contact to a list', 'uncanny-automator' ),
			__( 'Unsubscribe a contact from a list', 'uncanny-automator' ),
		);

		if ( filter_has_var( INPUT_GET, 'auth_error' ) ) {
			$vars['errors'] = array(
				array(
					'headline' => __( 'Authorization error', 'uncanny-automator' ),
					/* translators: Error message */
					'body'     => sprintf( __( 'Error message: %s', 'uncanny-automator' ), automator_filter_input( 'auth_error' ) ),
				),
			);
		}

		include_once 'zoho-campaigns-view.php';

	}

}
