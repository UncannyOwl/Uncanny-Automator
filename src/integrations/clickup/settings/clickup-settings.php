<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * ClickUp_Settings
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_App_Helpers $helpers
 * @property ClickUp_Api_Caller $api
 */
class ClickUp_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * Set the disconnected properties.
	 *
	 * @return void
	 */
	public function set_disconnected_properties() {
		// Backwards compatibility with the old redirect_param property.
		$this->redirect_param = 'user_url';
	}

	/**
	 * Validate integration-specific credentials from the OAuth callback.
	 *
	 * ClickUp does not use the API vault. The proxy exchanges the auth code for
	 * an access_token directly. We validate the token, fetch the authorized user,
	 * and merge the user data into the credentials before storage.
	 *
	 * @param array $credentials The decoded credentials from the OAuth callback.
	 *
	 * @return array The credentials merged with the authorized user data.
	 * @throws \Exception If the access token is missing or the user cannot be fetched.
	 */
	protected function validate_integration_credentials( $credentials ) {
		if ( empty( $credentials['access_token'] ) ) {
			throw new \Exception(
				esc_html_x( 'Missing access token. Please try connecting again.', 'ClickUp', 'uncanny-automator' )
			);
		}

		// Temporarily store credentials so the API caller can use them.
		$this->helpers->store_credentials( $credentials );

		try {
			// Fetch the authorized user and merge into credentials.
			$response = $this->api->api_request( 'get_authorized_user' );
		} catch ( \Exception $e ) {
			// Clean up temporary credentials on failure.
			$this->helpers->delete_credentials();
			throw $e;
		}

		$user = $response['data']['user'] ?? array();

		return array_merge( $credentials, $user );
	}

	/**
	 * Get formatted account information for the connected user display.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$account = $this->helpers->get_credentials();
		$color   = $account['color'] ?? '#7B68EE';

		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => $account['initials'] ?? '',
			'avatar_styles'  => 'background-color: ' . esc_attr( $color ) . '; color: #fff; font-size: 12px;',
			'main_info'      => $account['username'] ?? '',
			'main_info_icon' => true,
			'additional'     => $account['email'] ?? '',
		);
	}

	/**
	 * Clean up cached option data after disconnecting.
	 *
	 * @param array $response The current response array.
	 * @param array $data     The posted data.
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		$this->delete_option_data( $this->helpers->get_option_prefix() );
		return $response;
	}

	/**
	 * Output disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x(
				'Connect Uncanny Automator to ClickUp to have WordPress site activity create tasks, add comments and more. Put Project Management workflows on autopilot by linking form submissions to new tasks, post site updates when comments are added in ClickUp, and keep users engaged with ClickUp activity.',
				'ClickUp',
				'uncanny-automator'
			)
		);

		$this->output_available_items();
	}
}
