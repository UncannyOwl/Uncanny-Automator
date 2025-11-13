<?php
/**
 * Creates the settings page
 *
 * @since   4.15.1.1
 * @version 4.15.1.1
 * @package Uncanny_Automator
 * @author  Curt K.
 */

namespace Uncanny_Automator\Integrations\Brevo;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Brevo_Settings
 *
 * @property Brevo_App_Helpers $helpers
 * @property Brevo_Api_Caller $api
 */
class Brevo_Settings extends App_Integration_Settings {

	/**
	 * Account Details.
	 *
	 * @var mixed $account - false if not connected or array of account details
	 */
	protected $account;

	/**
	 * Register the options.
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		// Existing API key field.
		$this->register_option( $this->helpers->get_const( 'API_KEY_OPTION' ) );
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action(
			'automator_app_settings_brevo_before_disconnected_panel',
			array( $this, 'maybe_add_api_key_error_alert' )
		);
	}

	/**
	 * Sets up the properties of the settings page
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->account = $this->helpers->get_saved_account_details();
	}

	/**
	 * Called before authorization is attempted
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array
	 */
	protected function before_authorization( $response = array(), $data = array() ) {
		// Clear any existing account data before attempting to authorize
		$this->helpers->delete_account_info();

		return $response;
	}

	/**
	 * Called after options are saved
	 *
	 * @param array $response The current response array
	 * @param array $options The stored option data
	 *
	 * @return array
	 */
	protected function authorize_account( $response = array(), $options = array() ) {
		// Validate the account details.
		$this->account = $this->helpers->get_saved_account_details();

		return $response;
	}

	/**
	 * Called after successful authorization
	 *
	 * @param array $options The stored option data
	 * @param array $response The current response array
	 *
	 * @return array
	 */
	protected function after_authorization( $response = array(), $options = array() ) {
		if ( ! empty( $this->account['status'] ) ) {
			// Set initial transient data
			foreach ( $this->get_transient_config() as $key => $item ) {
				$this->api->{ $item['api_method'] }();
			}
		}

		return $response;
	}

	/**
	 * Get formatted account information for connected user info display
	 *
	 * @return array Formatted account information for UI display
	 */
	protected function get_formatted_account_info() {
		return array(
			'main_info'  => $this->account['company'] ?? '',
			'additional' => ! empty( $this->account['email'] ) ? sprintf(
				// translators: %1$s Email address
				esc_html_x( 'Account email: %1$s', 'Brevo', 'uncanny-automator' ),
				esc_html( $this->account['email'] )
			) : '',
		);
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
		// Clear all transients
		foreach ( array_keys( $this->get_transient_config() ) as $key ) {
			delete_transient( "automator_brevo_{$key}" );
		}

		return $response;
	}

	/**
	 * Maybe add the API key error alert.
	 *
	 * @return void
	 */
	public function maybe_add_api_key_error_alert() {
		// No errors to display.
		if ( empty( $this->account['error'] ?? '' ) ) {
			return;
		}

		// If the error is an unauthorized IP address, display the setup instructions.
		if ( 'unauthorized-ip' === $this->account['error'] ) {
			$content  = esc_html_x( 'Unable to connect your Brevo account due to blocking of unknown IP addresses.', 'Brevo', 'uncanny-automator' );
			$content .= $this->generate_steps_list(
				array(
					sprintf(
						// translators: %s: Link to Brevo security page
						esc_html_x( 'Go to %s in your Brevo account', 'Brevo', 'uncanny-automator' ),
						$this->helpers->get_authorized_ips_link()
					),
					sprintf(
						// translators: %s: Deactivate blocking text
						esc_html_x( 'Click %s', 'Brevo', 'uncanny-automator' ),
						'<strong>' . esc_html_x( 'Deactivate blocking', 'Brevo', 'uncanny-automator' ) . '</strong>'
					),
					esc_html_x( 'Once deactivated, please try connecting your account again.', 'Brevo', 'uncanny-automator' ),
				)
			);

			$this->add_alert(
				array(
					'type'    => 'error',
					'heading' => esc_attr_x( 'IP Whitelist Restriction', 'Brevo', 'uncanny-automator' ),
					'content' => $content,
				)
			);
			return;
		}

		// Show alert for other errors.
		$this->add_alert(
			array(
				'type'    => 'error',
				'heading' => esc_attr_x( 'Unable to connect to Brevo', 'Brevo', 'uncanny-automator' ),
				'content' => $this->account['error'],
			)
		);
	}

	/**
	 * Get Brevo transient config for table and refresh actions.
	 *
	 * @return array
	 */
	protected function get_transient_config() {
		return array(
			'contacts/lists'      => array(
				'icon'       => 'list',
				'name'       => esc_html_x( 'Contact lists', 'Brevo', 'uncanny-automator' ),
				'api_method' => 'get_lists',
			),
			'contacts/attributes' => array(
				'icon'       => 'user',
				'name'       => esc_html_x( 'Custom contact attributes', 'Brevo', 'uncanny-automator' ),
				'api_method' => 'get_contact_attributes',
			),
			'templates'           => array(
				'icon'       => 'envelope',
				'name'       => esc_html_x( 'Email templates', 'Brevo', 'uncanny-automator' ),
				'api_method' => 'get_templates',
			),
		);
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

		// 2. Build data array from config
		$config = $this->get_transient_config();
		$data   = array();
		foreach ( $config as $key_part => $item ) {
			// Check if transient exists.
			$options = get_transient( "automator_brevo_{$key_part}" );
			if ( false === $options ) {
				// If not, get options from API.
				$options = $this->api->{ $item['api_method'] }();
			}
			$count = ! empty( $options ) ? count( $options ) : 0;
			$desc  = sprintf(
				// translators: %s Data type
				esc_html_x(
					'Use the sync button if %s were updated within the last 24hrs and aren\'t yet showing in your recipes.',
					'Brevo',
					'uncanny-automator'
				),
				esc_html( strtolower( $item['name'] ) )
			);
			$data[] = array(
				'id'          => $key_part,
				'columns'     => array(
					'icon'   => array(
						'options' => array(
							array(
								'type' => 'icon',
								'data' => array(
									'id' => $item['icon'],
								),
							),
						),
					),
					'title'  => array(
						'options' => array(
							array(
								'type' => 'text',
								'data' => sprintf( '%s ( %d )', $item['name'], $count ),
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
									'label'          => esc_html_x( 'Refresh', 'Brevo', 'uncanny-automator' ),
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
			);
		}

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
		$config = $this->get_transient_config();
		$key    = $this->maybe_get_posted_row_id( $data );
		if ( ! $key || ! array_key_exists( $key, $config ) ) {
			$response['alert'] = $this->get_error_alert(
				esc_attr_x( 'Unable to refresh data', 'Brevo', 'uncanny-automator' ),
				esc_html_x( 'Invalid key', 'Brevo', 'uncanny-automator' )
			);
			return $response;
		}

		// Delete existing transient.
		delete_transient( "automator_brevo_{$key}" );

		// Get selected options.
		$message = $config[ $key ]['name'];
		$options = $this->api->{ $config[ $key ]['api_method'] }();

		// If no options are returned, return a warning alert.
		if ( empty( $options ) ) {
			$response['alert'] = $this->get_warning_alert(
				esc_attr_x( 'No data found', 'Brevo', 'uncanny-automator' ),
				sprintf(
					// translators: %s Data type
					esc_html_x( 'No data returned from the API for %s', 'Brevo', 'uncanny-automator' ),
					esc_html( $message )
				)
			);
			return $response;
		}

		// Get updated table data.
		$table_data = $this->get_transient_refresh_table_data();

		// Set the response data.
		$response['data']  = $table_data['data'];
		$response['alert'] = $this->get_success_alert(
			esc_attr_x( 'Data refreshed', 'Brevo', 'uncanny-automator' ),
			sprintf(
				// translators: %s Data type
				esc_html_x( 'Data refreshed successfully for %s', 'Brevo', 'uncanny-automator' ),
				esc_html( $message )
			)
		);
		return $response;
	}

	////////////////////////////////////////////////////////////
	// Templating
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		// Output the standard disconnected integration header with subtitle and description.
		$this->output_disconnected_header(
			esc_html_x( 'Connect Uncanny Automator to Brevo to connect contact and list management to WordPress activities like submitting forms, making purchases and joining groups.', 'Brevo', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions scanned from Premium_Integration_Items trait.
		$this->output_available_items();

		// Output setup instructions.
		$this->output_setup_instructions(
			// Main heading.
			sprintf(
				// translators: %s Brevo account URL
				esc_html_x( 'To obtain your Brevo API Key, follow these steps in your %s account:', 'Brevo', 'uncanny-automator' ),
				$this->get_escaped_link( 'https://app.brevo.com/', 'Brevo' )
			),
			// Array of instruction steps to obtain the API key.
			array(
				esc_html_x( 'Click your Profile button in the upper right side of the screen to see your profile options.', 'Brevo', 'uncanny-automator' ),
				esc_html_x( 'Select SMTP & API.', 'Brevo', 'uncanny-automator' ),
				esc_html_x( 'On the SMTP & API page, click API Keys.', 'Brevo', 'uncanny-automator' ),
				esc_html_x( 'In the upper right, click Generate a new API key.', 'Brevo', 'uncanny-automator' ),
				esc_html_x( 'A pop-up window will ask you to Name your API key. Enter a name such as "your-website-automator" and click Generate.', 'Brevo', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.SentenceCase.IncorrectReservedWordCase
				esc_html_x( 'You will now have an API key to enter in the field below. Once entered, click the Connect Brevo account button to enable your integration with Automator. Note: Save this key somewhere safe as it will not be accessible again and you will have to generate a new one.', 'Brevo', 'uncanny-automator' ),
			)
		);

		// Output security notice as a separate alert.
		$this->alert_html(
			array(
				'type'    => 'info',
				'heading' => esc_attr_x( 'Important', 'Brevo', 'uncanny-automator' ),
				'content' => sprintf(
					/* translators: %1$s: Link to Brevo security page, %2$s: Deactivate blocking text */
					esc_html_x( 'To use the Brevo integration with Automator you must %2$s from %1$s', 'Brevo', 'uncanny-automator' ),
					$this->helpers->get_authorized_ips_link(),
					'<strong>' . esc_html_x( 'deactivate unknown IP blocking', 'Brevo', 'uncanny-automator' ) . '</strong>'
				),
			)
		);

		// Show API Key field
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'API_KEY_OPTION' ),
				'value'    => esc_attr( $this->helpers->get_credentials() ),
				'label'    => esc_attr_x( 'API key', 'Brevo', 'uncanny-automator' ),
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

		// Output the Brevo transient data manager.
		$this->output_panel_subtitle( esc_html_x( 'Brevo Data', 'Brevo', 'uncanny-automator' ) );
		$this->output_subtle_panel_paragraph( esc_html_x( 'The following data is available for use in your recipes:', 'Brevo', 'uncanny-automator' ) );

		$table_data = $this->get_transient_refresh_table_data();
		$this->output_settings_table( $table_data['columns'], $table_data['data'], 'card', false );
	}
}
