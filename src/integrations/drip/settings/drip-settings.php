<?php

namespace Uncanny_Automator\Integrations\Drip;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Drip_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Drip_App_Helpers $helpers
 * @property Drip_Api_Caller $api
 */
class Drip_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	////////////////////////////////////////////////////////////
	// Required abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array Formatted account information for UI display.
	 */
	protected function get_formatted_account_info() {

		$account = $this->helpers->get_account_info();
		$name    = $account['name'] ?? '';
		$url     = $account['url'] ?? '';

		$info = array(
			'avatar_type'    => 'text',
			'avatar_value'   => ! empty( $name ) ? strtoupper( $name[0] ) : 'D',
			'main_info'      => ! empty( $url ) ? $url : esc_html_x( 'Drip Account', 'Drip', 'uncanny-automator' ),
			'main_info_icon' => true,
		);

		if ( ! empty( $url ) ) {
			$info['additional'] = sprintf(
				// translators: %s: URL address
				esc_html_x( 'Account URL: %s', 'Drip', 'uncanny-automator' ),
				$url
			);
		}

		return $info;
	}

	////////////////////////////////////////////////////////////
	// Abstract method overrides
	////////////////////////////////////////////////////////////

	/**
	 * Validate integration credentials.
	 *
	 * Drip predates the vault signature pattern so we validate
	 * the OAuth token properties directly.
	 *
	 * @param array $credentials The decoded OAuth credentials.
	 *
	 * @return array
	 * @throws \Exception If credentials are invalid.
	 */
	protected function validate_integration_credentials( $credentials ) {

		if ( empty( $credentials ) || ! is_array( $credentials ) || empty( $credentials['access_token'] ) ) {
			throw new \Exception(
				esc_html_x( 'Invalid credentials.', 'Drip', 'uncanny-automator' )
			);
		}

		return $credentials;
	}

	/**
	 * After disconnect — delete all cached option data for this integration.
	 *
	 * @param array $response The current response array.
	 * @param array $data The posted data.
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		$this->delete_option_data( $this->helpers->get_option_prefix() );
		return $response;
	}

	////////////////////////////////////////////////////////////
	// Template method overrides
	////////////////////////////////////////////////////////////

	/**
	 * Display main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Drip to supercharge your marketing automation and email campaigns. Once configured, Automator recipes can create and manage subscribers, add and remove tags, plus much more.', 'Drip', 'uncanny-automator' )
		);

		$this->output_available_items();
	}

	/**
	 * Display main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message(
			esc_html_x( 'You can only connect to a Drip account for which you have read and write access.', 'Drip', 'uncanny-automator' )
		);
	}
}
