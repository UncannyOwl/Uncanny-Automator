<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Ontraport;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Ontraport_Settings
 *
 * @property Ontraport_App_Helpers $helpers
 * @property Ontraport_Api_Caller $api
 */
class Ontraport_Settings extends App_Integration_Settings {

	/**
	 * The temporary option key for the API key.
	 *
	 * @var string
	 */
	const OPT_API_KEY = 'automator_ontraport_api_key';

	/**
	 * The temporary option key for the App ID.
	 *
	 * @var string
	 */
	const OPT_APP_ID_KEY = 'automator_ontraport_app_id';

	////////////////////////////////////////////////////////////
	// Abstract Methods
	////////////////////////////////////////////////////////////

	/**
	 * Register disconnected options.
	 *
	 * @return void
	 */
	protected function register_disconnected_options() {
		$this->register_option( self::OPT_API_KEY );
		$this->register_option( self::OPT_APP_ID_KEY );
	}

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {

		$credentials = $this->helpers->get_credentials();

		return array(
			'avatar_type'  => 'icon',
			'avatar_value' => 'ONTRAPORT',
			'main_info'    => esc_html_x( 'Ontraport account', 'Ontraport', 'uncanny-automator' ),
			'additional'   => sprintf(
				// translators: %s: Ontraport APP ID
				esc_html_x( 'APP ID: %s', 'Ontraport', 'uncanny-automator' ),
				esc_html( $credentials['id'] )
			),
		);
	}

	/**
	 * Authorize account after settings have been validated and saved.
	 *
	 * @param array $response The current response array for REST.
	 * @param array $options  The stored option data.
	 *
	 * @return array
	 * @throws Exception If authorization fails.
	 */
	protected function authorize_account( $response = array(), $options = array() ) {

		// Store credentials first so send_request may verify and use them.
		$this->helpers->store_credentials(
			array(
				'key' => $options[ self::OPT_API_KEY ] ?? '',
				'id'  => $options[ self::OPT_APP_ID_KEY ] ?? '',
			)
		);

		try {
			// Verify credentials with the proxy server.
			$check_response = $this->api->send_request( 'check_credentials' );
			$result         = strtolower( $check_response['data']['result'] ?? '' );

			if ( false !== strpos( $result, 'do not authenticate' ) ) {
				throw new Exception( esc_html_x( 'Please double-check your API Key and App ID', 'Ontraport', 'uncanny-automator' ) );
			}

			// Delete the individual options now that credentials are consolidated and verified.
			automator_delete_option( self::OPT_API_KEY );
			automator_delete_option( self::OPT_APP_ID_KEY );

			// Register a success alert.
			$this->register_connected_alert();

		} catch ( Exception $e ) {
			// Clean up stored credentials on failure.
			$this->helpers->delete_credentials();
			throw $e;
		}

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Templating Methods
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		// Output the standard disconnected integration header with description.
		$this->output_disconnected_header(
			esc_html_x( 'Integrate Uncanny Automator with Ontraport to elevate workflow automation and turbocharge productivity for businesses. Seamlessly connecting these platforms empowers users to effortlessly transfer data and ignite actions, slashing manual tasks and boosting efficiency.', 'Ontraport', 'uncanny-automator' )
		);

		// Output available recipe items (actions list).
		$this->output_available_items();

		// Output setup instructions.
		$this->output_setup_instructions(
			esc_html_x( 'To connect your Ontraport account, follow these steps:', 'Ontraport', 'uncanny-automator' ),
			array(
				sprintf(
					// translators: %1$s: link to Ontraport support article, %2$s: opening strong tag, %3$s: closing strong tag.
					esc_html_x( 'Visit the %1$s to learn how to obtain your %2$sAPI Key%3$s and %2$sApp ID%3$s', 'Ontraport', 'uncanny-automator' ),
					$this->get_escaped_link(
						'https://ontraport.com/support/integrations/obtain-ontraport-api-key-and-app-id/',
						esc_html_x( 'Ontraport support article', 'Ontraport', 'uncanny-automator' )
					),
					'<strong>',
					'</strong>'
				),
				sprintf(
					// translators: %1$s: opening strong tag, %2$s: closing strong tag.
					esc_html_x( 'Copy and paste the acquired %1$sAPI Key%2$s and %1$sApp ID%2$s into the designated fields below.', 'Ontraport', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					// translators: %1$s: opening strong tag, %2$s: closing strong tag.
					esc_html_x( 'Click the %1$sConnect Ontraport account%2$s button to proceed.', 'Ontraport', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
			)
		);

		// Show App ID field.
		$this->text_input_html(
			array(
				'id'       => self::OPT_APP_ID_KEY,
				'value'    => automator_get_option( self::OPT_APP_ID_KEY, '' ),
				'label'    => esc_html_x( 'App ID', 'Ontraport', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);

		// Show API Key field.
		$this->text_input_html(
			array(
				'id'       => self::OPT_API_KEY,
				'value'    => automator_get_option( self::OPT_API_KEY, '' ),
				'label'    => esc_html_x( 'API Key', 'Ontraport', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);
	}
}
