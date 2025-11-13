<?php
/**
 * Creates the settings page for Sendy
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Sendy;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Sendy_App_Settings
 *
 * @property Sendy_App_Helpers $helpers
 * @property Sendy_Api_Caller $api
 */
class Sendy_Settings extends App_Integration_Settings {

	/**
	 * Temporary option key for saving API Key on settings submit.
	 *
	 * @var string
	 */
	const API_KEY_OPTION = 'automator_sendy_api_key';

	/**
	 * Temporary option key for saving Sendy Installation URL on settings submit.
	 *
	 * @var string
	 */
	const URL_OPTION_KEY = 'automator_sendy_url';

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
				// translators: %1$s Sendy URL
				esc_html_x( 'Connected to: %1$s', 'Sendy', 'uncanny-automator' ),
				esc_html( $account['url'] )
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Override framework methods (following golden standards)
	////////////////////////////////////////////////////////////

	/**
	 * Register disconnected options.
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		$this->register_option( self::API_KEY_OPTION );
		$this->register_option( self::URL_OPTION_KEY );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action(
			'automator_app_settings_sendy_before_disconnected_panel',
			array( $this, 'maybe_add_api_key_error_alert' )
		);
	}

	/**
	 * Authorize account
	 *
	 * @param array $response The current response array
	 * @param array $options The stored option data
	 *
	 * @return array
	 */
	protected function authorize_account( $response = array(), $options = array() ) {
		// Set reload to true by default.
		$response['reload'] = true;

		// Get user submitted credentials.
		$key = $options[ self::API_KEY_OPTION ] ?? '';
		$url = $options[ self::URL_OPTION_KEY ] ?? '';

		// Clean up temporary options.
		automator_delete_option( self::API_KEY_OPTION );
		automator_delete_option( self::URL_OPTION_KEY );

		// Set credentials object.
		$credentials = array(
			'api_key' => $key,
			'url'     => esc_url( rtrim( $url, '/' ) ),
			'status'  => false,
			'error'   => '',
		);

		if ( empty( $key ) || empty( $url ) ) {
			$credentials['error'] = esc_html_x( 'Please enter a valid Sendy installation URL and API key.', 'Sendy', 'uncanny-automator' );
		}

		// Store credentials.
		$this->helpers->store_credentials( $credentials );

		// If there is an error, return the response.
		if ( ! empty( $credentials['error'] ) ) {
			return $response;
		}

		// Validate API connection.
		try {
			$this->api->get_lists( true );

			// Update credential flags.
			$credentials['status'] = true;
			$credentials['error']  = '';

			// Add a success alert.
			$this->register_alert(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'Sendy account connected', 'Sendy', 'uncanny-automator' ),
					'content' => esc_html_x( 'Your Sendy account has been connected successfully.', 'Sendy', 'uncanny-automator' ),
				)
			);

		} catch ( Exception $e ) {
			// Add error to credentials.
			$credentials['error'] = esc_html( $e->getMessage() );
		}

		// Store credentials.
		$this->helpers->store_credentials( $credentials );

		return $response;
	}

	/**
	 * Called after disconnecting the integration has been validated.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array
	 */
	protected function after_disconnect( $response = array(), $data = array() ) {
		// Clear lists transient
		delete_transient( $this->helpers->get_const( 'LIST_TRANSIENT_KEY' ) );

		return $response;
	}

	/**
	 * Maybe add the API key error alert.
	 *
	 * @return void
	 */
	public function maybe_add_api_key_error_alert() {
		$error = $this->helpers->get_sendy_setting( 'error' );

		// No errors to display.
		if ( empty( $error ) ) {
			return;
		}

		// Show alert for errors.
		$this->add_alert(
			array(
				'type'    => 'error',
				'heading' => esc_attr_x( 'Unable to connect to Sendy', 'Sendy', 'uncanny-automator' ),
				'content' => esc_html( $error ),
			)
		);
	}

	////////////////////////////////////////////////////////////
	// Abstract content output methods (following golden standards)
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void - Outputs the generated HTML.
	 */
	public function output_main_disconnected_content() {
		// Output the standard disconnected header with description
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Sendy to link contact and list management to WordPress activities like submitting forms, making purchases and joining groups.', 'Sendy', 'uncanny-automator' )
		);

		// Output available recipe items.
		$this->output_available_items();

		// Output setup instructions.
		$this->output_setup_instructions(
			esc_html_x( 'To obtain your Sendy API Key, follow these steps in your Sendy installation:', 'Sendy', 'uncanny-automator' ),
			array(
				sprintf(
					// translators: %1$s Opening strong tag, %2$s Closing strong tag
					esc_html_x( 'Log in to Sendy as the %1$sMain user%2$s with the email/password you set when you first set up Sendy.', 'Sendy', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					// translators: %1$s Opening strong tag with icon, %2$s Closing strong tag
					esc_html_x( 'On the upper right hand corner of the page, click the button that says %1$sSendy%2$s.', 'Sendy', 'uncanny-automator' ),
					'<span class="dashicons dashicons-admin-users"></span><strong>',
					'</strong>'
				),
				sprintf(
					// translators: %1$s Opening strong tag, %2$s Closing strong tag
					esc_html_x( 'Select %1$sSettings%2$s.', 'Sendy', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
				sprintf(
					// translators: %1$s Opening italic tag, %2$s Closing italic tag, %3$s Opening strong tag, %4$s Closing strong tag
					esc_html_x( 'On the %1$sSettings%2$s page you will see your API Key on the right under the title %3$sYour API key%4$s.', 'Sendy', 'uncanny-automator' ),
					'<i>',
					'</i>',
					'<strong>',
					'</strong>'
				),
				sprintf(
					// translators: %1$s Opening strong tag, %2$s Closing strong tag
					esc_html_x( 'Please enter the API key and your Sendy installation URL in the fields below. Once entered, click the %1$sConnect Sendy account%2$s button to enable your integration with Automator.', 'Sendy', 'uncanny-automator' ),
					'<strong>',
					'</strong>'
				),
			)
		);

		// Output Sendy URL field.
		$this->text_input_html(
			array(
				'id'       => self::URL_OPTION_KEY,
				'value'    => esc_attr( $this->helpers->get_sendy_setting( 'url' ) ),
				'label'    => esc_html_x( 'Sendy installation URL', 'Sendy', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);

		// Output API Key field.
		$this->text_input_html(
			array(
				'id'       => self::API_KEY_OPTION,
				'value'    => esc_attr( $this->helpers->get_sendy_setting( 'api_key' ) ),
				'label'    => esc_html_x( 'API key', 'Sendy', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);
	}

	/**
	 * Output main connected content.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message();

		// Output the Sendy transient data manager.
		$this->output_panel_subtitle( esc_html_x( 'Sendy Data', 'Sendy', 'uncanny-automator' ) );
		$this->output_subtle_panel_paragraph( esc_html_x( 'The following data is available for use in your recipes:', 'Sendy', 'uncanny-automator' ) );

		$table_data = $this->get_transient_refresh_table_data();
		$this->output_settings_table( $table_data['columns'], $table_data['data'], 'card', false );
	}

	/**
	 * Get the transient refresh table data
	 *
	 * @return array
	 */
	private function get_transient_refresh_table_data() {
		// 1. Define columns
		$columns = array(
			array(
				'key' => 'icon',
			),
			array(
				'key' => 'title',
			),
			array(
				'key' => 'action',
			),
		);

		// 2. Build data array
		$key     = 'lists';
		$options = get_transient( "automator_sendy_{$key}" );
		if ( false === $options ) {
			// If not, get options from API.
			$options = $this->api->get_lists();
		}
		$count = ! empty( $options ) ? count( $options ) : 0;
		$name  = esc_html_x( 'Contact lists', 'Sendy', 'uncanny-automator' );
		$desc  = sprintf(
			// translators: %s Data type
			esc_html_x(
				'Use the sync button if %s were updated within the last 24hrs and aren\'t yet showing in your recipes.',
				'Sendy',
				'uncanny-automator'
			),
			esc_html( strtolower( $name ) )
		);

		$data = array(
			array(
				'id'          => $key,
				'columns'     => array(
					'icon'   => array(
						'options' => array(
							array(
								'type' => 'icon',
								'data' => array(
									'id' => 'list',
								),
							),
						),
					),
					'title'  => array(
						'options' => array(
							array(
								'type' => 'text',
								'data' => sprintf( '%s ( %d )', $name, $count ),
							),
						),
					),
					'action' => array(
						'options' => array(
							array(
								'type' => 'button',
								'data' => array(
									'type'           => 'submit',
									'name'           => 'automator_action',
									'value'          => 'transient_refresh',
									'row-submission' => true,
									'label'          => esc_html_x( 'Refresh', 'Sendy', 'uncanny-automator' ),
									'color'          => 'secondary',
									'size'           => 'extra-small',
									'icon'           => array(
										'id' => 'rotate',
									),
								),
							),
						),
					),
				),
				'description' => $desc,
			),
		);

		return array(
			'columns' => $columns,
			'data'    => $data,
		);
	}

	/**
	 * Handle transient refresh action
	 *
	 * @param array $response - The current response array
	 * @param array $data - The data posted to the settings page.
	 *
	 * @return array
	 */
	public function handle_transient_refresh( $response = array(), $data = array() ) {
		$key = $this->maybe_get_posted_row_id( $data );
		if ( 'lists' !== $key ) {
			$response['alert'] = $this->get_error_alert(
				esc_attr_x( 'Unable to refresh data', 'Sendy', 'uncanny-automator' ),
				esc_html_x( 'Invalid key', 'Sendy', 'uncanny-automator' )
			);
			return $response;
		}

		// Get updated lists.
		$refresh = true;
		$options = $this->api->get_lists( $refresh );

		// If no options are returned, return a warning alert.
		if ( empty( $options ) ) {
			$response['alert'] = $this->get_warning_alert(
				esc_attr_x( 'No data found', 'Sendy', 'uncanny-automator' ),
				esc_html_x( 'No lists returned from the API', 'Sendy', 'uncanny-automator' )
			);
			return $response;
		}

		// Get updated table data.
		$table_data = $this->get_transient_refresh_table_data();

		// Set the response data.
		$response['data']  = $table_data['data'];
		$response['alert'] = $this->get_success_alert(
			esc_attr_x( 'Data refreshed', 'Sendy', 'uncanny-automator' ),
			esc_html_x( 'Lists refreshed successfully', 'Sendy', 'uncanny-automator' )
		);
		return $response;
	}
}
