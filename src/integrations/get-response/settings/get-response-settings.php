<?php
/**
 * Creates the settings page
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Get_Response;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Get_Response_Settings
 *
 * @property Get_Response_App_Helpers $helpers
 * @property Get_Response_Api_Caller $api
 */
class Get_Response_Settings extends App_Integration_Settings {

	/**
	 * Settings arguments.
	 *
	 * @var array
	 */
	private $settings_args = array(
		'context' => 'settings',
	);

	////////////////////////////////////////////////////
	// Abstract methods.
	////////////////////////////////////////////////////

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
	 * Get formatted account info.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$account = $this->helpers->get_account_info();

		if ( empty( $account['status'] ) ) {
			return array();
		}

		return array(
			'avatar_type'    => 'icon',
			'avatar_value'   => $this->get_icon(),
			'main_info'      => $account['email'],
			'main_info_icon' => false,
			'additional'     => sprintf(
				// translators: Account ID
				esc_html_x( 'Account ID: %s', 'GetResponse', 'uncanny-automator' ),
				$account['id']
			),
		);
	}

	/**
	 * Authorize account after API key submission.
	 *
	 * @param array $response Current response
	 * @param string $credentials The API key
	 *
	 * @return array
	 * @throws Exception
	 */
	protected function authorize_account( $response, $credentials ) {
		// Validate the API key and get account info.
		$account = $this->api->get_account();

		if ( ! empty( $account['status'] ) ) {
			// Set initial transient data
			$this->helpers->get_contact_fields( false, $this->settings_args );
			$this->helpers->get_lists( false, $this->settings_args );

			$response['success'] = true;
			$this->register_success_alert(
				esc_html_x(
					'GetResponse account connected successfully!',
					'GetResponse',
					'uncanny-automator'
				)
			);

			return $response;
		}

		$response['success'] = false;
		$error_message       = ! empty( $account['error'] ) ? $account['error'] : esc_html_x( 'Unable to connect to GetResponse. Please check your API key.', 'GetResponse', 'uncanny-automator' );
		$this->register_error_alert( $error_message );

		return $response;
	}

	/**
	 * Before disconnect.
	 *
	 * @param array $response The current response array
	 * @param array $data The posted data
	 *
	 * @return array
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		// Clear transients.
		$config = $this->get_transient_config();
		foreach ( $config as $key_part => $item ) {
			delete_transient( "automator_getresponse_{$key_part}" );
		}

		return $response;
	}

	////////////////////////////////////////////////////
	// Transient data management.
	////////////////////////////////////////////////////

	/**
	 * Get GetResponse transient config for table and refresh actions.
	 *
	 * @return array
	 */
	protected function get_transient_config() {
		return array(
			'contact/lists'  => array(
				'icon'       => 'list',
				'name'       => esc_html_x( 'Contact lists', 'GetResponse', 'uncanny-automator' ),
				'api_method' => 'get_lists',
			),
			'contact/fields' => array(
				'icon'       => 'user',
				'name'       => esc_html_x( 'Contact fields', 'GetResponse', 'uncanny-automator' ),
				'api_method' => 'get_contact_fields',
			),
		);
	}

	/**
	 * Get the transient refresh table data.
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
			$options       = get_transient( "automator_getresponse_{$key_part}" );
			$error_message = '';
			if ( false === $options ) {
				// If not, get options from API.
				try {
					$options = $this->helpers->{ $item['api_method'] }( false, $this->settings_args );
				} catch ( Exception $e ) {
					// If API fails, return empty array and store error message.
					$options       = array();
					$error_message = $e->getMessage();
				}
			}
			$count = ! empty( $options ) ? count( $options ) : 0;

			// Set description based on whether there was an error
			$desc = ! empty( $error_message )
				? sprintf(
					// translators: %1$s Data type, %2$s Error message
					esc_html_x(
						'Error loading %1$s: %2$s',
						'GetResponse',
						'uncanny-automator'
					),
					esc_html( strtolower( $item['name'] ) ),
					esc_html( $error_message )
				)
				: sprintf(
					// translators: %s Data type
					esc_html_x(
						'Use the sync button if %s were updated within the last 24hrs and aren\'t yet showing in your recipes.',
						'GetResponse',
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
									'label'          => esc_html_x( 'Refresh', 'GetResponse', 'uncanny-automator' ),
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
	 * Handle transient refresh action.
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
				esc_attr_x( 'Unable to refresh data', 'GetResponse', 'uncanny-automator' ),
				esc_html_x( 'Invalid key', 'GetResponse', 'uncanny-automator' )
			);
			return $response;
		}

		// Delete existing transient.
		delete_transient( "automator_getresponse_{$key}" );

		// Get selected options.
		$message = $config[ $key ]['name'];
		try {
			$options = $this->helpers->{ $config[ $key ]['api_method'] }( false, $this->settings_args );
		} catch ( Exception $e ) {
			// Disconnect account and show error.
			$this->delete_all_registered_options();
			$this->register_error_alert( $e->getMessage() );
			$response['reload'] = true;
			return $response;
		}

		// If no options are returned, return a warning alert.
		if ( empty( $options ) ) {
			$response['alert'] = $this->get_warning_alert(
				esc_attr_x( 'No data found', 'GetResponse', 'uncanny-automator' ),
				sprintf(
					// translators: %s Data type
					esc_html_x( 'No data returned from the API for %s', 'GetResponse', 'uncanny-automator' ),
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
			esc_attr_x( 'Data refreshed', 'GetResponse', 'uncanny-automator' ),
			sprintf(
				// translators: %s Data type
				esc_html_x( 'Data refreshed successfully for %s', 'GetResponse', 'uncanny-automator' ),
				esc_html( $message )
			)
		);
		return $response;
	}

	////////////////////////////////////////////////////
	// Templating methods
	////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		// Use template methods from framework.
		$this->output_disconnected_header(
			esc_html_x(
				'Connect Uncanny Automator to GetResponse to connect contact and list management to WordPress activities like submitting forms, making purchases and joining groups.',
				'GetResponse',
				'uncanny-automator'
			)
		);

		// List available items.
		$this->output_available_items();

		// Show setup instructions.
		$initial_text = sprintf(
			// translators: 1. Formatted link to GetResponse API page
			esc_html_x( 'To obtain your GetResponse API Key, follow these steps from your %1$s page:', 'GetResponse', 'uncanny-automator' ),
			$this->get_escaped_link( 'https://app.getresponse.com/api', 'GetResponse API' ),
		);

		$steps = array(
			sprintf(
				// translators: %s: <strong> "Generate API key" text
				esc_html_x( 'Click the large %s button on the API page.', 'GetResponse', 'uncanny-automator' ),
				'<strong>' . esc_html_x( 'Generate API key', 'GetResponse', 'uncanny-automator' ) . '</strong>'
			),
			sprintf(
				// translators: %s: <em> "Blog name Automator" text
				esc_html_x( 'Enter a unique name for your key, such as %s.', 'GetResponse', 'uncanny-automator' ),
				'<em>' . esc_html( get_bloginfo( 'name' ) ) . ' Automator' . '</em>'
			),
			sprintf(
				// translators: %s: <strong> "Generate" text
				esc_html_x( 'Click the %s button.', 'GetResponse', 'uncanny-automator' ),
				'<strong>' . esc_html_x( 'Generate', 'GetResponse', 'uncanny-automator' ) . '</strong>'
			),
			sprintf(
				// translators: %s: <strong> "Copy" text
				esc_html_x( 'Click the %s button next to the generated API key.', 'GetResponse', 'uncanny-automator' ),
				'<strong>' . esc_html_x( 'Copy', 'GetResponse', 'uncanny-automator' ) . '</strong>'
			),
			sprintf(
				// translators: %s: <strong> "API key" text
				esc_html_x( 'You will now have an %s to enter in the field below.', 'GetResponse', 'uncanny-automator' ),
				'<strong>' . esc_html_x( 'API key', 'GetResponse', 'uncanny-automator' ) . '</strong>'
			),
			sprintf(
				// translators: %s: <strong> "Connect GetResponse account" text
				esc_html_x( 'Once entered, click the %s button to enable your integration with Automator.', 'GetResponse', 'uncanny-automator' ),
				'<strong>' . esc_html_x( 'Connect GetResponse account', 'GetResponse', 'uncanny-automator' ) . '</strong>'
			),
		);

		$this->output_setup_instructions( $initial_text, $steps );

		// API key field.
		$this->text_input_html(
			array(
				'id'       => esc_attr( $this->helpers->get_const( 'API_KEY_OPTION' ) ),
				'value'    => esc_attr( $this->helpers->get_credentials() ),
				'label'    => esc_attr_x( 'API key', 'GetResponse', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top uap-spacing-bottom',
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

		// Output the GetResponse transient data manager.
		$this->output_panel_subtitle( esc_html_x( 'GetResponse Data', 'GetResponse', 'uncanny-automator' ) );
		$this->output_subtle_panel_paragraph( esc_html_x( 'The following data is available for use in your recipes:', 'GetResponse', 'uncanny-automator' ) );

		$table_data = $this->get_transient_refresh_table_data();
		$this->output_settings_table( $table_data['columns'], $table_data['data'], 'card', false );
	}
}
