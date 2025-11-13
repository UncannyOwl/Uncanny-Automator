<?php
namespace Uncanny_Automator\Integrations\Constant_Contact;

use Uncanny_Automator\Settings\OAuth_App_Integration;
use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Class Constant_Contact_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Constant_Contact_App_Helpers $helpers
 */
class Constant_Contact_Settings extends App_Integration_Settings {

	/**
	 * Use OAuth trait for OAuth functionality.
	 */
	use OAuth_App_Integration;

	/**
	 * Sets up the properties of the settings page.
	 *
	 * @return void
	 */
	public function set_properties() {
		// OAuth action name - using 'authorization' as per current implementation.
		$this->oauth_action = 'authorization';

		// Redirect parameter name - using 'wp_site' to preserve current behavior.
		$this->redirect_param = 'wp_site';
	}

	/**
	 * Get formatted account info.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$account_info = $this->helpers->get_account_info();

		if ( empty( $account_info ) ) {
			return array();
		}

		$first_name = $account_info['first_name'] ?? '';
		$last_name  = $account_info['last_name'] ?? '';
		$name       = trim( $first_name . ' ' . $last_name );

		return array(
			'main_info'  => ! empty( $name ) ? $name : $account_info['contact_email'],
			'additional' => ! empty( $name ) ? $account_info['contact_email'] : '',
		);
	}

	/**
	 * Validate integration credentials.
	 *
	 * @param array $credentials
	 * @return array
	 * @throws Exception
	 */
	protected function validate_integration_credentials( $credentials ) {
		// Constant Contact doesn't have vault_signature like other integrations.
		if ( empty( $credentials['access_token'] ) ) {
			throw new Exception(
				esc_html_x( 'Missing access token in credentials', 'Constant Contact', 'uncanny-automator' )
			);
		}
		return $credentials;
	}

	/**
	 * Authorize account.
	 *
	 * @param array $response
	 * @param mixed $credentials
	 * @return array
	 * @throws Exception
	 */
	protected function authorize_account( $response, $credentials ) {
		// Fetch and store account info.
		$account_info = $this->helpers->get_account_info();

		if ( empty( $account_info ) ) {
			throw new Exception( esc_html_x( 'Unable to fetch account information.', 'Constant Contact', 'uncanny-automator' ) );
		}

		// Return response unchanged for OAuth flow.
		return $response;
	}

	/**
	 * Remove cached option data during disconnect.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array Modified response array
	 * @throws Exception If cleanup fails
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		automator_delete_option( $this->helpers->get_const( 'OPTION_CUSTOM_FIELDS_REPEATER' ) );
		return $response;
	}
}
