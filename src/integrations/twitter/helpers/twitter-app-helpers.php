<?php

namespace Uncanny_Automator\Integrations\Twitter;

use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Twitter_Helpers
 *
 * @package Uncanny_Automator
 */
class Twitter_App_Helpers extends App_Helpers {

	/**
	 * Set the properties for the Twitter integration.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set legacy option names for credentials and user account info.
		$this->set_credentials_option_name( '_uncannyowl_twitter_settings' );
		$this->set_account_option_name( 'automator_twitter_user' );
	}

	/**
	 * Validate credentials.
	 *
	 * @param array $credentials -The credentials.
	 * @param array $args - Optional arguments.
	 *
	 * @return mixed - Array or string of credentials
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( empty( $credentials['oauth_token'] ) || empty( $credentials['oauth_token_secret'] ) ) {
			throw new \Exception( esc_html_x( 'Twitter is not connected', 'Twitter', 'uncanny-automator' ) );
		}

		return $credentials;
	}

	/**
	 * Check if the user app is connected.
	 *
	 * @return bool - True if the user app is connected, false otherwise.
	 */
	public function is_user_app_connected() {
		try {
			$credentials = $this->get_credentials();
		} catch ( \Exception $e ) {
			return false;
		}

		return ! empty( $credentials['api_secret'] );
	}

	/**
	 * Validate account info.
	 * Handles the hybrid approach where user info can be stored in credentials or account option.
	 *
	 * @param array $account_info - The account info.
	 *
	 * @return mixed - Array or string of account info
	 */
	public function validate_account_info( $account_info ) {
		// If the user app is connected, use the account info from the account option.
		if ( $this->is_user_app_connected() ) {
			return array(
				'id'          => $account_info['id'] ?? '',
				'screen_name' => $account_info['screen_name'] ?? '',
			);
		}

		// If the user app is not connected, use the account info from the credentials.
		try {
			$credentials = $this->get_credentials();
			return array(
				'id'          => $credentials['user_id'] ?? '',
				'screen_name' => $credentials['screen_name'] ?? '',
			);
		} catch ( \Exception $e ) {
			return array(
				'id'          => '',
				'screen_name' => '',
			);
		}
	}

	/**
	 * Get the username from normalized account info.
	 *
	 * @return string
	 */
	public function get_username() {
		$account_info = $this->get_account_info();
		return $account_info['screen_name'];
	}

	/**
	 * Get recipe status ( message ) configuration.
	 *
	 * @param string $option_code - The option code.
	 *
	 * @return array
	 */
	public function get_recipe_status_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'Status', 'Twitter', 'uncanny-automator' ),
			'input_type'            => 'textarea',
			'supports_custom_value' => true,
			'supports_markdown'     => true,
			'required'              => true,
			'placeholder'           => esc_attr_x( 'Enter the message', 'Twitter', 'uncanny-automator' ),
			'description'           => esc_attr_x( 'Messages posted to X/Twitter have a 280 character limit.', 'Twitter', 'uncanny-automator' ),
			'max_length'            => 278,
		);
	}
}
