<?php
/**
 * Creates the settings page
 */

namespace Uncanny_Automator\Integrations\Bluesky;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Bluesky_Settings
 *
 * @property Bluesky_App_Helpers $helpers
 * @property Bluesky_Api_Caller $api
 */
class Bluesky_Settings extends App_Integration_Settings {

	/**
	 * Temporary username key - will be stored at the Proxy server after connected
	 *
	 * @var string
	 */
	const USERNAME_KEY = 'automator_bluesky_username';

	/**
	 * Temporary app password key - will be stored at the Proxy server after connected
	 *
	 * @var string
	 */
	const APP_PASSWORD_KEY = 'automator_bluesky_app_password';

	////////////////////////////////////////////////////////////
	// Required abstract method
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		// Get the user credentials
		$avatar = $this->helpers->get_credential_setting( 'avatar' );
		$email  = $this->helpers->get_credential_setting( 'email' );
		$handle = $this->helpers->get_credential_setting( 'handle' );

		// Prepare the args array for output_connected_user_info
		return array(
			'avatar_type'  => ! empty( $avatar ) ? 'image' : 'icon',
			'avatar_value' => ! empty( $avatar ) ? $avatar : 'BLUESKY',
			'main_info'    => $handle,
			'additional'   => ! empty( $email ) ? sprintf(
				/* translators: 1. Email address */
				esc_html_x( 'Account email: %1$s', 'Bluesky', 'uncanny-automator' ),
				esc_html( $email )
			) : '',
		);
	}

	////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////

	/**
	 * Register disconnected options.
	 *
	 * @return void
	 */
	protected function register_disconnected_options() {
		$this->register_option( self::USERNAME_KEY );
		$this->register_option( self::APP_PASSWORD_KEY );
	}

	/**
	 * Authorize account ( setting have been validated and saved )
	 *
	 * @param array $response The current response array for REST
	 * @param array $options The stored option data
	 *
	 * @return array
	 */
	protected function authorize_account( $response = array(), $options = array() ) {

		// Get the username and app password from the registered options
		$username     = automator_get_option( self::USERNAME_KEY, '' );
		$app_password = automator_get_option( self::APP_PASSWORD_KEY, '' );

		if ( empty( $username ) || empty( $app_password ) ) {
			throw new Exception( esc_html_x( 'Please enter a valid username and app password.', 'Bluesky', 'uncanny-automator' ) );
		}

		try {
			// Authenticate the user and store vault credentials.
			$this->api->authenticate_user( $username, $app_password );

			// Register a success alert.
			$this->register_connected_alert();

			// Delete the temporary username and app password options.
			automator_delete_option( self::USERNAME_KEY );
			automator_delete_option( self::APP_PASSWORD_KEY );

		} catch ( Exception $e ) {
			$this->register_error_alert( $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Before disconnect
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		// This deletes the vault credentials from the server.
		$this->helpers->remove_credentials();

		return $response;
	}

	////////////////////////////////////////////////////
	// Abstract template methods
	////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		// Output the standard disconnected integration header with subtitle and description.
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Bluesky to streamline automations to post to your account', 'Bluesky', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions scanned from Premium_Integration_Items trait.
		$this->output_available_items();

		// Output setup instructions.
		$this->output_setup_instructions(
			// Main heading.
			esc_html_x( 'To obtain your Bluesky App Password, follow these steps:', 'Bluesky', 'uncanny-automator' ),
			// Array of instruction steps to obtain the API key.
			array(
				sprintf(
					// translators: %s: HTML link to Bluesky app passwords page
					esc_html_x(
						'Visit your %s in your Bluesky account or navigate to Settings > Security > App Passwords',
						'Bluesky',
						'uncanny-automator'
					),
					$this->get_escaped_link(
						'https://bsky.app/settings/app-passwords',
						esc_html_x( 'App Password settings', 'Bluesky', 'uncanny-automator' )
					)
				),
				esc_html_x( 'Click on the "Add App Password" button', 'Bluesky', 'uncanny-automator' ),
				esc_html_x( 'Give your password a unique name such as "Automator"', 'Bluesky', 'uncanny-automator' ),
				esc_html_x( 'Click "Next"', 'Bluesky', 'uncanny-automator' ),
				esc_html_x( 'Copy your new password using the copy button and paste it directly into the App Password field below', 'Bluesky', 'uncanny-automator' ),
			)
		);

		// Show username field
		$this->text_input_html(
			array(
				'id'          => self::USERNAME_KEY,
				'value'       => esc_attr( automator_get_option( self::USERNAME_KEY, '' ) ),
				'label'       => esc_attr_x( 'Username', 'Bluesky', 'uncanny-automator' ),
				'placeholder' => esc_attr_x( 'example.bsky.social', 'Bluesky', 'uncanny-automator' ),
				'required'    => true,
				'class'       => 'uap-spacing-top',
			)
		);

		// Show app password field
		$this->text_input_html(
			array(
				'id'       => self::APP_PASSWORD_KEY,
				'value'    => esc_attr( automator_get_option( self::APP_PASSWORD_KEY, '' ) ),
				'label'    => esc_attr_x( 'App Password', 'Bluesky', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);
	}
}
