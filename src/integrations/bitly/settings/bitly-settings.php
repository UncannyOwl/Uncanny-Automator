<?php
/**
 * Creates the settings page for Bitly
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Bitly;

use Uncanny_Automator\Settings\App_Integration_Settings;

/**
 * Bitly_Settings
 *
 * @property Bitly_App_Helpers $helpers
 */
class Bitly_Settings extends App_Integration_Settings {

	/**
	 * Account info.
	 *
	 * @var array $account
	 */
	protected $account;

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
			'avatar_type' => 'icon',
			'main_info'   => sprintf(
				// translators: %1$s Email address
				esc_html_x( 'Account name: %1$s', 'Bitly', 'uncanny-automator' ),
				esc_html( $account['name'] )
			),
			'additional'  => sprintf(
				// translators: %1$s Email address
				esc_html_x( 'Account email: %1$s', 'Bitly', 'uncanny-automator' ),
				esc_html( $account['email'] )
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Override framework methods.
	////////////////////////////////////////////////////////////

	/**
	 * Sets up the properties of the settings page
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->account = $this->helpers->get_saved_account_details();
	}

	/**
	 * Register options.
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		$this->register_option( $this->helpers->get_credentials_option_name() );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action(
			'automator_app_settings_bitly_before_disconnected_panel',
			array( $this, 'maybe_add_api_key_error_alert' )
		);
	}

	/**
	 * Authorize account
	 *
	 * @param array $response The current response array
	 * @param array $options The stored option data
	 *
	 * @return void
	 */
	protected function authorize_account( $response = array(), $options = array() ) {
		// Generate account details.
		$this->account = $this->helpers->get_saved_account_details();

		// If the account is not connected bail.
		$status = $this->account['status'] ?? '';
		if ( 'connected' !== $status ) {
			$response['success'] = false;
			$response['reload']  = true;
			return $response;
		}

		// Add a success alert.
		$this->register_alert(
			array(
				'type'    => 'success',
				'heading' => esc_html_x( 'Bitly account connected', 'Bitly', 'uncanny-automator' ),
				'content' => esc_html_x( 'Your Bitly account has been connected successfully.', 'Bitly', 'uncanny-automator' ),
			)
		);

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods.
	////////////////////////////////////////////////////////////

	/**
	 * Maybe add API key error alert.
	 *
	 * @return void
	 */
	public function maybe_add_api_key_error_alert() {
		// If we have an API Key but unable to connect show error message with disconnect button.
		if ( ! $this->is_connected && ! empty( $this->account['error'] ) ) {
			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Unable to connect to Bitly', 'Bitly', 'uncanny-automator' ),
					'content' => esc_html_x( 'The Access Token you entered is invalid. Please re-enter your Access Token again.', 'Bitly', 'uncanny-automator' ),
				)
			);
		}
	}

	////////////////////////////////////////////////////////////
	// Abstract content output methods.
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected header with description
		$this->output_disconnected_header(
			esc_html_x( 'Integrate your WordPress site directly with Bitly. Shorten your URLs and use them in your recipes.', 'Bitly', 'uncanny-automator' )
		);

		// Output available recipe items.
		$this->output_available_items();

		// Output setup instructions.
		$this->output_setup_instructions(
			// Use HTML for the link
			sprintf(
				// translators: %1$s is a link to the Bitly API settings page
				esc_html_x( 'To obtain your Bitly Access Token, follow these steps in your %1$s account:', 'Bitly', 'uncanny-automator' ),
				$this->get_escaped_link( 'https://app.bitly.com/settings/api', $this->get_name() ),
			),
			array(
				sprintf(
					// translators: %1$s is the opening strong tag, %2$s is the closing strong tag
					esc_html_x( 'Enter your account password in the "Enter password" field and then click %1$sGenerate token%2$s.', 'Bitly', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					// translators: %1$s is the opening strong tag, %2$s is the closing strong tag
					esc_html_x( 'You will now have an Access Token to enter in the field below. Once entered, click the %1$sConnect Bitly account%2$s button to enable your integration with Automator.', 'Bitly', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					// translators: %1$s is the opening strong tag, %2$s is the closing strong tag, %3$s is the opening em tag, %4$s is the closing em tag
					esc_html_x( '%1$sNote :%2$s %3$sSave this token somewhere safe as it will not be accessible again and you will have to generate a new one.%4$s', 'Bitly', 'uncanny-automator' ),
					'<strong>',
					'</strong>',
					'<i>',
					'</i>'
				),
			)
		);

		// Output API Key field.
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_credentials_option_name(),
				'value'    => esc_attr( $this->helpers->get_credentials() ),
				'label'    => esc_html_x( 'Access Token', 'Bitly', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);
	}
}
