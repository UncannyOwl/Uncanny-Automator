<?php
namespace Uncanny_Automator\Integrations\Google_Contacts;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\OAuth_App_Integration;
use Exception;

/**
 * @package Uncanny_Automator\Integrations\Google Contacts
 *
 * @since 5.0
 *
 * @property Google_Contacts_Helpers $helpers
 * @property Google_Contacts_Api_Caller $api
 */
class Google_Contacts_Settings extends App_Integration_Settings {

	use OAuth_App_Integration;

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->show_connect_arrow = true;

		// Early connection validation - only on settings page
		if ( $this->is_current_page_settings() && $this->is_connected ) {
			$this->validate_user_transient_status();
		}
	}

	/**
	 * Validate if the user is still connected by checking account info
	 * If not connected, disconnect the account and update connection status
	 *
	 * @return void
	 */
	private function validate_user_transient_status() {
		try {
			// Try to get account info to verify connection is still valid.
			$this->helpers->get_account_info();
		} catch ( Exception $e ) {
			// Connection is no longer valid, clear stored data.
			$this->helpers->clear_connection();

			// Update the connection status for proper templating.
			$this->set_is_connected( false );

			// Register an alert to inform the user.
			$this->register_alert(
				$this->get_error_alert(
					sprintf(
						// translators: 1: error code, 2: error message
						esc_html_x( 'An error has occured while fetching the resource owner: (%1$s) %2$s', 'Google Contacts', 'uncanny-automator' ),
						absint( $e->getCode() ),
						esc_html( $e->getMessage() )
					),
					esc_html_x( 'Error exception', 'Google Contacts', 'uncanny-automator' )
				)
			);
		}
	}

	/////////////////////////////////////////////////////////////
	// Required Abstract method.
	/////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array Formatted account information for UI display
	 */
	protected function get_formatted_account_info() {
		// Get the account info.
		$account = $this->helpers->get_account_info();

		// Prepare main info with Google icon
		$main_info  = ! empty( $account['name'] ) ? $account['name'] : $account['email'];
		$main_info .= ' <uo-icon id="google"></uo-icon>';

		return array(
			'avatar_type'  => ! empty( $account['avatar_uri'] ) ? 'image' : 'icon',
			'avatar_value' => ! empty( $account['avatar_uri'] ) ? $account['avatar_uri'] : 'google',
			'main_info'    => $main_info,
			'additional'   => ! empty( $account['name'] ) && ! empty( $account['email'] ) ? $account['email'] : '',
		);
	}

	/////////////////////////////////////////////////////////////
	// Templating methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Display - Main disconnected content
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x(
				'Connect Uncanny Automator to Google Contacts to automatically create contacts when users perform actions like submitting forms, joining groups and making purchases on your site. ',
				'Google Contacts',
				'uncanny-automator'
			)
		);

		// Output available recipe items.
		$this->output_available_items();
	}

	/**
	 * Output the OAuth connect button - overiding the abstract method.
	 *
	 * @return void
	 */
	public function output_oauth_connect_button() {
		$this->output_action_button(
			'oauth_init',
			esc_html_x( 'Sign in with Google', 'Google Contacts', 'uncanny-automator' ),
			array(
				'class' => 'uap-settings-button-google',
				'icon'  => 'google',
			)
		);
	}

	/////////////////////////////////////////////////////////////
	// OAuth handling overrides.
	/////////////////////////////////////////////////////////////

	/**
	 * Register error message alert - override to handle auth_error formatting.
	 *
	 * @return void
	 */
	public function register_oauth_error_alert( $message ) {
		$message = str_replace( ' ', '_', strtolower( rawurlencode( $message ) ) );
		$this->register_alert( $this->get_error_alert( $message ) );
	}

	/**
	 * Validate integration-specific credentials
	 *
	 * @param array $credentials
	 * @return array
	 */
	protected function validate_integration_credentials( $credentials ) {
		if ( $this->has_missing_scopes( $credentials ) ) {
			// TODO REVIEW - do we have a KB article or similar to offer a help link
			// or more descriptive error message?
			$this->register_oauth_error_alert( esc_html_x( 'missing_scope', 'Google Contacts', 'uncanny-automator' ) );
		}

		return $credentials;
	}

	/**
	 * Authorize the account.
	 *
	 * @param array $response
	 * @param array $credentials
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function authorize_account( $response, $credentials ) {
		// Ensure any transients are cleared.
		$this->helpers->delete_account_info();

		// Validate the connected account and let it throw an exception if it fails.
		// Abstract will handle it from there.
		$this->helpers->get_account_info();

		return $response;
	}

	/////////////////////////////////////////////////////////////
	// OAuth custom methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Check if the user has missing scopes.
	 *
	 * @param array $token The access token combination.
	 *
	 * @return boolean True if there are scopes missing. Otherwise, false.
	 */
	private function has_missing_scopes( $token ) {

		if ( ! isset( $token['scope'] ) || empty( $token['scope'] ) ) {
			return true;
		}

		$scopes = array(
			'https://www.googleapis.com/auth/contacts',
			'https://www.googleapis.com/auth/userinfo.profile',
			'https://www.googleapis.com/auth/userinfo.email',
		);

		$has_missing_scope = false;

		foreach ( $scopes as $scope ) {
			if ( false === strpos( $token['scope'], $scope ) ) {
				$has_missing_scope = true;
			}
		}

		return $has_missing_scope;
	}
}
