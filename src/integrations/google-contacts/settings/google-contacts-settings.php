<?php
namespace Uncanny_Automator\Integrations\Google_Contacts;

use Uncanny_Automator\Settings\Premium_Integration_Settings;

/**
 * @package Uncanny_Automator\Integrations\Google Contacts
 *
 * @since 5.0
 */
class Google_Contacts_Settings extends Premium_Integration_Settings {

	/**
	 * @var string
	 */
	const SETTINGS_ERROR = 'automator_google_contacts_connection_alerts';

	/**
	 * @var Google_Contacts_Helpers
	 */
	public $helpers;

	/**
	 * Retrieves the integration status.
	 *
	 * @return string Returns 'success' if there is a agent. Returns empty string otherwise.
	 */
	public function get_status() {
		$credentials = get_option( Google_Contacts_Helpers::OPTION_KEY, false );
		return empty( $credentials ) ? '' : 'success'; // return '' to fail.
	}

	/**
	 * Basic settings page props.
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'google-contacts' );
		$this->set_icon( 'GOOGLE_CONTACTS' );
		$this->set_name( 'Google Contacts' );

	}

	/**
	 * @return string
	 */
	public function disconnect_url() {
		return add_query_arg(
			array(
				'nonce'  => wp_create_nonce( 'google-contacts-disconnect' ),
				'action' => 'automator_google_contacts_disconnect',
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Retrieves the auth url.
	 *
	 * @return string
	 */
	public static function get_auth_url() {

		// Create nonce.
		$nonce = wp_create_nonce( 'automator_api_google_contacts_authorize' );

		// Construct the redirect uri.
		$redirect_uri = add_query_arg(
			array(
				'action' => 'automator_google_contacts_process_code_callback',
				'nonce'  => $nonce,
			),
			admin_url( 'admin-ajax.php' )
		);

		set_transient( Google_Contacts_Helpers::AUTH_TRANSIENT_KEY, $nonce, 3600 );

		// Construct the OAuth URL.
		return add_query_arg(
			array(
				'action'       => 'authorization_request',
				'redirect_url' => rawurlencode( $redirect_uri ),
				'nonce'        => $nonce,
				'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,
			),
			AUTOMATOR_API_URL . 'v2/google-contacts'
		);

	}

	/**
	 * Method fetch_resource_owner.
	 *
	 * @return array The user info.
	 */
	public function fetch_resource_owner() {

		$user_info = array(
			'avatar_uri' => '',
			'name'       => '',
			'email'      => '',
		);

		$saved_user_info = get_transient( Google_Contacts_Helpers::RESOURCE_OWNER_KEY );

		if ( false !== $saved_user_info && ! empty( $saved_user_info['email'] ) ) {
			return $saved_user_info;
		}

		try {

			$user = $this->helpers->request_resource_owner();

			if ( empty( $user['data'] ) ) {
				return $user_info;
			}

			$user_info['name']       = isset( $user['data']['name'] ) ? $user['data']['name'] : '';
			$user_info['avatar_uri'] = isset( $user['data']['picture'] ) ? $user['data']['picture'] : '';
			$user_info['email']      = isset( $user['data']['email'] ) ? $user['data']['email'] : '';

			set_transient( Google_Contacts_Helpers::RESOURCE_OWNER_KEY, $user_info, DAY_IN_SECONDS );

		} catch ( \Exception $e ) {

			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => 'Error exception',
					'content' => sprintf( 'An error has occured while fetching the resource owner: (%s) %s', $e->getCode(), $e->getMessage() ),
				)
			);

			Google_Contacts_Helpers::clear_connection();

			return $user_info;
		}

		return $user_info;
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$this->fetch_resource_owner();

		$credentials    = get_option( Google_Contacts_Helpers::OPTION_KEY, false );
		$resource_owner = get_transient( Google_Contacts_Helpers::RESOURCE_OWNER_KEY );

		if ( ! is_array( $resource_owner ) && empty( $resource_owner ) ) {
			$resource_owner = '';
		}

		$vars = array(
			'auth_url'       => self::get_auth_url(),
			'disconnect_url' => $this->disconnect_url(),
			'alerts'         => (array) get_settings_errors( self::SETTINGS_ERROR ),
			'is_connected'   => ! empty( $credentials ),
			'resource_owner' => $resource_owner,
			'user_info'      => (array) $resource_owner,
		);

		// Actions.
		$vars['actions'] = array(
			_x( 'Create or update a contact', 'Google Contacts', 'uncanny-automator' ),
		);

		include_once 'google-contacts-settings-view.php';

	}

}
