<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Threads;

use Exception;
use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;

/**
 * Threads_Settings
 *
 * @property Threads_App_Helpers $helpers
 * @property Threads_Api_Caller $api
 */
class Threads_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array Formatted account information for UI display
	 */
	protected function get_formatted_account_info() {
		$account = $this->helpers->get_account_info();
		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => 'T',
			'main_info'      => esc_html_x( 'Threads account', 'Threads', 'uncanny-automator' ),
			'main_info_icon' => true,
			'additional'     => $account['name'],
		);
	}

	////////////////////////////////////////////////////////////
	// OAuth overrides
	////////////////////////////////////////////////////////////

	/**
	 * Set properties.
	 */
	public function set_properties() {
		$this->oauth_action   = 'authorization';
		$this->redirect_param = 'wp_site';
	}

	/**
	 * Filter OAuth args to add Threads-specific parameters.
	 *
	 * @param array $args The OAuth arguments.
	 * @param array $data The posted data.
	 *
	 * @return array
	 */
	protected function maybe_filter_oauth_args( $args, $data = array() ) {
		// Map nonce to state for legacy compatibility.
		$args['state'] = $args['nonce'];
		unset( $args['nonce'] );
		return $args;
	}

	/**
	 * Validate integration-specific credentials.
	 * Override this in the integration class to add custom validation.
	 *
	 * @param array $credentials
	 * @return array
	 *
	 * @throws Exception
	 */
	protected function validate_integration_credentials( $credentials ) {

		// Validate required credential properties.
		$access_token = $credentials['access_token'] ?? '';
		$user_id      = $credentials['user_id'] ?? '';
		$expires_in   = $credentials['expires_in'] ?? '';

		if ( empty( $access_token ) || empty( $user_id ) || empty( $expires_in ) ) {
			throw new Exception( 'Missing or invalid credentials. Please reconnect your account.' );
		}

		// Return legacy credentials format.
		return array(
			'access_token' => $access_token,
			'token_type'   => 'bearer',
			'expires_in'   => $expires_in,
			'expiration'   => time() + absint( $expires_in ),
			'user_id'      => $user_id,
		);
	}

	////////////////////////////////////////////////////////////
	// Content output methods
	////////////////////////////////////////////////////////////

	/**
	 * Display - Main panel disconnected content.
	 *
	 * @return string - HTML
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected integration header with description.
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Threads to streamline automations that allow you to create thread posts directly from your WordPress site.', 'Threads', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions.
		$this->output_available_items();
	}

	/**
	 * Display - Main panel connected content.
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_connected_content() {
		// One account warning.
		$this->output_single_account_message(
			esc_html_x( 'If you create recipes and then change the connected Threads account, your previous recipes may no longer work.', 'Threads', 'uncanny-automator' ),
		);
	}
}
