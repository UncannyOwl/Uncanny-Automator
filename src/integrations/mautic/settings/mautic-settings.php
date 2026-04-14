<?php

namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Renders the Mautic settings page in the Automator premium integrations tab.
 *
 * Manages the connection flow: displays credential input fields when disconnected,
 * validates credentials via the API proxy, and shows connected account info when
 * authenticated. Uses the App_Integration_Settings framework for templating and
 * REST-based authorization.
 *
 * @package Uncanny_Automator\Integrations\Mautic
 *
 * @property Mautic_App_Helpers $helpers
 * @property Mautic_Api_Caller $api
 *
 * @since 5.0
 */
class Mautic_Settings extends App_Integration_Settings {

	/**
	 * Temporary option key for Base URL.
	 *
	 * @var string
	 */
	const BASE_URL_KEY = 'automator_mautic_base_url';

	/**
	 * Temporary option key for Username.
	 *
	 * @var string
	 */
	const USERNAME_KEY = 'automator_mautic_username';

	/**
	 * Temporary option key for Password.
	 *
	 * @var string
	 */
	const PASSWORD_KEY = 'automator_mautic_password';

	////////////////////////////////////////////////////////////
	// Required abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Retrieve and format the connected Mautic account info for the settings UI.
	 *
	 * @return array{avatar_type: string, avatar_value?: string, main_info: string, additional?: string}
	 */
	protected function get_formatted_account_info() {

		$account_info = $this->helpers->get_account_info();
		$account_info = (array) json_decode( $account_info, true );

		$username = $account_info['username'] ?? '';
		$email    = $account_info['email'] ?? '';

		return array(
			'avatar_type'  => 'text',
			'avatar_value' => ! empty( $username ) ? substr( $username, 0, 1 ) : 'M',
			'main_info'    => $username,
			'additional'   => ! empty( $email ) ? sprintf(
				// translators: %1$s is the email address
				esc_html_x( 'Email: %1$s', 'Mautic', 'uncanny-automator' ),
				esc_html( $email )
			) : '',
		);
	}

	////////////////////////////////////////////////////////////
	// Abstract method overrides
	////////////////////////////////////////////////////////////

	/**
	 * Register the temporary options used to capture Base URL, Username,
	 * and Password from the user before authorization. These are deleted
	 * after successful connection.
	 *
	 * @return void
	 */
	protected function register_disconnected_options() {
		$this->register_option( self::BASE_URL_KEY );
		$this->register_option( self::USERNAME_KEY );
		$this->register_option( self::PASSWORD_KEY );
	}

	/**
	 * Authorize the Mautic account using the submitted credentials.
	 *
	 * Reads the Base URL, Username, and Password from the stored temp options,
	 * validates them against the Mautic API via the proxy, and on success stores
	 * both the credentials and the resource owner account info. Temporary options
	 * are cleaned up after a successful connection.
	 *
	 * @param array $response The current REST response array.
	 * @param array $options  The stored option data keyed by option name.
	 *
	 * @throws Exception If required fields are missing.
	 *
	 * @return array The (potentially modified) REST response array.
	 */
	protected function authorize_account( $response = array(), $options = array() ) {

		$base_url = $options[ self::BASE_URL_KEY ] ?? '';
		$username = $options[ self::USERNAME_KEY ] ?? '';
		$password = $options[ self::PASSWORD_KEY ] ?? '';

		if ( empty( $base_url ) || empty( $username ) || empty( $password ) ) {
			throw new Exception( esc_html_x( 'Please enter your Base URL, Username, and Password.', 'Mautic', 'uncanny-automator' ) );
		}

		// Build credentials as a JSON string — the same format used by the
		// API proxy and stored in the database throughout the integration.
		$credentials = wp_json_encode(
			array(
				'baseUrl'      => esc_url_raw( rtrim( $base_url, '/' ) ),
				'userName'     => $username,
				'userPassword' => $password,
			)
		);

		// Store consolidated credentials.
		$this->helpers->store_credentials( $credentials );

		try {
			// Validate credentials by calling the API.
			$api_response = $this->api->api_request( 'validate_credentials' );
			$account_info = $api_response['data'] ?? array();

			if ( empty( $account_info ) ) {
				throw new Exception( esc_html_x( 'Unable to retrieve account information. Please verify your credentials and try again.', 'Mautic', 'uncanny-automator' ) );
			}

			// Store account info as a JSON string (legacy format).
			$this->helpers->store_account_info( wp_json_encode( $account_info ) );

			// Delete temporary options.
			automator_delete_option( self::BASE_URL_KEY );
			automator_delete_option( self::USERNAME_KEY );
			automator_delete_option( self::PASSWORD_KEY );

			// Register a success alert.
			$this->register_connected_alert();

		} catch ( Exception $e ) {
			// Connection failed — remove credentials and show the error.
			$this->helpers->delete_credentials();
			$this->helpers->delete_account_info();
			$this->register_error_alert( $e->getMessage() );
			$response['success'] = false;
		}

		return $response;
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

	////////////////////////////////////////////////////////////////////
	// Template method overrides
	////////////////////////////////////////////////////////////////////

	/**
	 * Render the settings page content shown when Mautic is not connected.
	 *
	 * Outputs a header with description, the list of available actions,
	 * setup instructions, and three credential input fields (Base URL,
	 * Username, Password).
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		// Output the standard disconnected integration header.
		$this->output_disconnected_header(
			esc_html_x( "Connect Uncanny Automator to Mautic to create or update contacts, manage segments, and more. With this integration, it's easy to automate your Mautic workflows from WordPress.", 'Mautic', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions.
		$this->output_available_items();

		// Output setup instructions.
		$this->output_setup_instructions(
			esc_html_x( 'To connect your Mautic account, follow these steps:', 'Mautic', 'uncanny-automator' ),
			array(
				esc_html_x( 'Enter the Base URL of your Mautic installation (e.g. https://yourdomain.com).', 'Mautic', 'uncanny-automator' ),
				esc_html_x( 'Enter the username and password you use to log into your Mautic admin panel.', 'Mautic', 'uncanny-automator' ),
				sprintf(
					/* translators: %1$s Opening strong tag, %2$s Closing strong tag */
					esc_html_x( 'Click the %1$sConnect Mautic account%2$s button to enable the integration.', 'Mautic', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					/* translators: %1$s Opening strong tag, %2$s Closing strong tag */
					esc_html_x( 'If you have trouble connecting, verify that the API and HTTP basic auth are enabled in your Mautic instance under %1$sSettings > Configuration > API Settings%2$s.', 'Mautic', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
			)
		);

		// Base URL field.
		$this->text_input_html(
			array(
				'id'          => self::BASE_URL_KEY,
				'value'       => esc_attr( automator_get_option( self::BASE_URL_KEY, '' ) ),
				'label'       => esc_attr_x( 'Base URL', 'Mautic', 'uncanny-automator' ),
				'placeholder' => esc_attr_x( 'https://yourdomain.com', 'Mautic', 'uncanny-automator' ),
				'required'    => true,
				'class'       => 'uap-spacing-top',
			)
		);

		// Username field.
		$this->text_input_html(
			array(
				'id'       => self::USERNAME_KEY,
				'value'    => esc_attr( automator_get_option( self::USERNAME_KEY, '' ) ),
				'label'    => esc_attr_x( 'Username', 'Mautic', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);

		// Password field.
		$this->text_input_html(
			array(
				'id'       => self::PASSWORD_KEY,
				'value'    => esc_attr( automator_get_option( self::PASSWORD_KEY, '' ) ),
				'label'    => esc_attr_x( 'Password', 'Mautic', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);
	}

	/**
	 * Display - Main connected content
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message(
			esc_html_x( 'If you create recipes and then change the connected Mautic account, your previous recipes may no longer work.', 'Mautic', 'uncanny-automator' )
		);
	}
}
