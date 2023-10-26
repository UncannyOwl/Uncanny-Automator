<?php
namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Settings\Premium_Integration_Settings;

/**
 * @package Uncanny_Automator\Integrations\Mautic
 *
 * @since 5.0
 */
class Mautic_Settings extends Premium_Integration_Settings {

	/**
	 * @var Mautic_Client_Auth
	 */
	protected $client_auth = null;

	/**
	 * @var string
	 */
	const SETTINGS_ERROR = 'automator_mautic_connection_alerts';

	/**
	 * @param Mautic_Client_Auth $client_auth
	 *
	 * @return self
	 */
	public function set_client_auth( Mautic_Client_Auth $client_auth ) {

		$this->client_auth = $client_auth;

		return $this;
	}

	/**
	 * Returns the helper class.
	 *
	 * @return Mautic_Helpers The helper object.
	 */
	public function get_helper() {

		return $this->helpers;

	}

	/**
	 * Retrieves the integration status.
	 *
	 * @return string Returns 'success' if there is a agent. Returns empty string otherwise.
	 */
	public function get_status() {

		$resource_owner = get_option( 'automator_mautic_resource_owner', false );

		return ! empty( $resource_owner ) ? 'success' : '';

	}

	/**
	 * Basic settings page props.
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'mautic' );
		$this->set_icon( 'MAUTIC' );
		$this->set_name( 'Mautic' );

		$this->register_option( 'automator_mautic_base_url' );
		$this->register_option( 'automator_mautic_username' );
		$this->register_option( 'automator_mautic_password' );
		$this->register_option( 'automator_mautic_credentials' );

		// Validate credentials.
		$this->set_client_auth( new Mautic_Client_Auth( Api_Server::get_instance() ) );

		add_filter( 'sanitize_option_automator_mautic_credentials', array( $this, 'validate_input' ), 40, 3 );

	}

	/**
	 * @param string $sanitized_input
	 * @param string $option_name
	 * @param string $original_input
	 *
	 * @return string|false
	 */
	public function validate_input( $sanitized_input, $option_name, $original_input ) {

		return $this->client_auth->validate_credentials( $sanitized_input, $option_name, $original_input );

	}

	/**
	 * @return string
	 */
	public function disconnect_url() {

		return add_query_arg(
			array(
				'nonce'  => wp_create_nonce( 'mautic-disconnect' ),
				'action' => 'automator_mautic_disconnect_client',
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$this->load_js( '/mautic/settings/scripts/settings.js' );

		$resource_owner = get_option( 'automator_mautic_resource_owner', false );

		$vars = array(
			'alerts'         => (array) get_settings_errors( self::SETTINGS_ERROR ),
			'disconnect_url' => $this->disconnect_url(),
			'is_connected'   => ! empty( $resource_owner ),
			'resource_owner' => ! empty( $resource_owner ) ? json_decode( $resource_owner, true ) : false,
			'fields'         => array(
				'base_url'    => get_option( 'automator_mautic_base_url', '' ),
				'username'    => get_option( 'automator_mautic_username', '' ),
				'password'    => get_option( 'automator_mautic_password', '' ),
				'credentials' => get_option( 'automator_mautic_credentials', '' ),
			),
		);

		// Actions.
		$vars['actions'] = array(
			_x( 'Create or update a contact', 'Mautic', 'uncanny-automator' ),
			_x( 'Create a segment', 'Mautic', 'uncanny-automator' ),
			_x( 'Add a contact to a segment', 'Mautic', 'uncanny-automator' ),
			_x( 'Remove a contact from a segment', 'Mautic', 'uncanny-automator' ),
		);

		include_once 'mautic-settings-view.php';

	}

}
