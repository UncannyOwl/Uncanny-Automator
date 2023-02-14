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
class ClickUp_Settings extends Settings\Premium_Integration_Settings {

	/**
	 * Retrieves the integration status.
	 *
	 * @return string Returns 'success' if there is a client. Returns empty string otherwise.
	 */
	public function get_status() {

		return false !== $this->get_helper()->get_client() ? 'success' : '';

	}

	/**
	 * ClickUp's settings page props.
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'clickup' );
		$this->set_icon( 'CLICKUP' );
		$this->set_name( 'ClickUp' );

	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helpers object.
	 */
	public function get_helper() {

		return $this->helpers;

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$vars = array(
			'connect_url'    => $this->get_helper()->get_oauth_url(),
			'disconnect_url' => $this->get_helper()->get_disconnect_url(),
			'is_connected'   => $this->get_helper()->is_connected(),
			'user'           => $this->get_helper()->get_client(),
			'oauth_response' => json_decode( urldecode( automator_filter_input( 'response' ) ), true ),
		);

		// Triggers.
		$vars['triggers'] = array(
			__( 'A comment is added to a task', 'uncanny-automator' ),
			__( 'A task is created in a list with a status', 'uncanny-automator' ),
		);

		// Actions.
		$vars['actions'] = array(
			__( 'Add a comment to a task', 'uncanny-automator' ),
			__( 'Create a task in a specific list', 'uncanny-automator' ),
			__( 'Delete a specific task', 'uncanny-automator' ),
			__( 'Update a task', 'uncanny-automator' ),
		);

		if ( 'error' === automator_filter_input( 'status' ) && filter_has_var( INPUT_GET, 'code' ) ) {
			$vars['has_errors']    = true;
			$vars['error_message'] = 'Help Scout has responded with status code: ' . automator_filter_input( 'code' ); // Prefer not to translate err message.
		}

		include_once 'view-clickup.php';

	}

}
