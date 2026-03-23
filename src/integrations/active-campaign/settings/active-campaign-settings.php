<?php
/**
 * Creates the settings page
 */

namespace Uncanny_Automator\Integrations\Active_Campaign;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Uncanny_Automator\Settings\Premium_Integration_Webhook_Settings;
use Exception;

/**
 * Active_Campaign_Settings
 *
 * @property Active_Campaign_App_Helpers $helpers
 * @property Active_Campaign_Api $api
 * @property Active_Campaign_Webhooks $webhooks
 */
class Active_Campaign_Settings extends App_Integration_Settings {

	use Premium_Integration_Webhook_Settings;

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
			'avatar_value'   => strtoupper( $account['firstName'][0] ),
			'main_info'      => "{$account['firstName']} {$account['lastName']}",
			'main_info_icon' => true,
			'additional'     => $account['email'],
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
		$this->register_option( $this->helpers->get_const( 'API_URL_OPTION' ) );
		$this->register_option( $this->helpers->get_const( 'API_KEY_OPTION' ) );
	}

	/**
	 * Register connected options.
	 *
	 * @return void
	 */
	protected function register_connected_options() {
		// Register webhook-related options.
		$this->register_webhook_options();
	}

	/**
	 * After authorization ( settings have been sanitized and saved )
	 *
	 * @param array $response The current response array
	 * @param array $options The stored option data
	 *
	 * @return array Modified response array
	 */
	protected function authorize_account( $response = array(), $options = array() ) {
		try {
			// Validate the URL.
			if ( ! wp_http_validate_url( $this->helpers->get_account_api_url_option() ) ) {
				throw new Exception( esc_html_x( 'The account URL is not a valid URL', 'Active Campaign', 'uncanny-automator' ) );
			}

			// Authenticate the user and store results.
			$users = $this->api->get_account_user();
			$this->helpers->store_account_info( $users );

			// Register a success alert.
			$this->register_connected_alert();

		} catch ( Exception $e ) {
			$this->register_error_alert( $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Before save settings
	 *
	 * @param array $response Current response
	 * @param array $data Posted data
	 *
	 * @return array
	 */
	protected function before_save_settings( $response = array(), $data = array() ) {
		return $this->handle_webhook_status_before_save( $response, $data );
	}

	////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////

	/**
	 * Get ActiveCampaign transient config for table and refresh actions.
	 *
	 * @return array
	 */
	protected function get_transient_config() {
		return array(
			'tags'           => array(
				'icon'       => 'tag',
				'name'       => esc_html_x( 'Tags', 'ActiveCampaign', 'uncanny-automator' ),
				'api_method' => 'sync_tags',
				'transient'  => 'ua_ac_tag_list',
			),
			'lists'          => array(
				'icon'       => 'list',
				'name'       => esc_html_x( 'Lists', 'ActiveCampaign', 'uncanny-automator' ),
				'api_method' => 'sync_lists',
				'transient'  => 'ua_ac_list_group',
			),
			'contact_fields' => array(
				'icon'       => 'user',
				'name'       => esc_html_x( 'Custom contact fields', 'ActiveCampaign', 'uncanny-automator' ),
				'api_method' => 'sync_contact_fields',
				'transient'  => 'ua_ac_contact_fields_list',
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
			$options = get_transient( $item['transient'] );
			if ( false === $options ) {
				// If not, get options from API.
				$options = $this->helpers->{ $item['api_method'] }();
			}
			// Check for WP_Error before counting
			$count = ( ! is_wp_error( $options ) && ! empty( $options ) ) ? count( $options ) : 0;
			$desc  = sprintf(
				// translators: %s Data type
				esc_html_x(
					'Use the refresh button if %s were updated within the last hour and aren\'t yet showing in your recipes.',
					'ActiveCampaign',
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
									'label'          => esc_html_x( 'Refresh', 'ActiveCampaign', 'uncanny-automator' ),
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
				esc_attr_x( 'Unable to refresh data', 'ActiveCampaign', 'uncanny-automator' ),
				esc_html_x( 'Invalid key', 'ActiveCampaign', 'uncanny-automator' )
			);
			return $response;
		}

		// Delete existing transient.
		delete_transient( $config[ $key ]['transient'] );

		// Get selected options.
		$message = $config[ $key ]['name'];
		$options = $this->helpers->{ $config[ $key ]['api_method'] }();

		// Check for WP_Error first
		if ( is_wp_error( $options ) ) {
			$response['alert'] = $this->get_error_alert(
				esc_attr_x( 'API Error', 'ActiveCampaign', 'uncanny-automator' ),
				$options->get_error_message()
			);
			return $response;
		}

		// If no options are returned, return a warning alert.
		if ( empty( $options ) ) {
			$response['alert'] = $this->get_warning_alert(
				esc_attr_x( 'No data found', 'ActiveCampaign', 'uncanny-automator' ),
				sprintf(
					// translators: %s Data type
					esc_html_x( 'No data returned from the API for %s', 'ActiveCampaign', 'uncanny-automator' ),
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
			esc_attr_x( 'Data refreshed', 'ActiveCampaign', 'uncanny-automator' ),
			sprintf(
				// translators: %s Data type
				esc_html_x( 'Data refreshed successfully for %s', 'ActiveCampaign', 'uncanny-automator' ),
				esc_html( $message )
			)
		);
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
			esc_html_x( "Connect Uncanny Automator to ActiveCampaign to better segment and engage with your customers. Add and update contacts and add/remove tags based on a user's activity on your WordPress site, or automatically perform actions on your users when tags are added or removed in ActiveCampaign.", 'ActiveCampaign', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions scanned from Premium_Integration_Items trait.
		$this->output_available_items();

		// Add seperator
		$this->output_panel_separator();

		// Output setup instructions.
		$this->output_setup_instructions(
			// Subtitle
			esc_html_x( 'To obtain your ActiveCampaign API URL and Key, follow these steps in your ActiveCampaign account:', 'ActiveCampaign', 'uncanny-automator' ),
			// Steps
			array(
				sprintf(
					// translators: %s: HTML link to ActiveCampaign login
					esc_html_x(
						'Log in to your %s.',
						'ActiveCampaign',
						'uncanny-automator'
					),
					$this->get_escaped_link(
						'https://www.activecampaign.com/login',
						esc_html_x( 'ActiveCampaign account', 'ActiveCampaign', 'uncanny-automator' )
					)
				),
				esc_html_x( 'Click the "Settings" option located in the left side navigation menu.', 'ActiveCampaign', 'uncanny-automator' ),
				esc_html_x( 'The Account Settings menu will appear. Click the "Developer" option.', 'ActiveCampaign', 'uncanny-automator' ),
				esc_html_x( 'On the "Developer Settings" page, copy your API URL and API Key.', 'ActiveCampaign', 'uncanny-automator' ),
				esc_html_x( 'Paste the API URL and API Key into the respective fields in the form below.', 'ActiveCampaign', 'uncanny-automator' ),
				esc_html_x( 'Click the "Connect ActiveCampaign account" button to save your details and complete the setup.', 'ActiveCampaign', 'uncanny-automator' ),
			)
		);

		// Show API URL field
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'API_URL_OPTION' ),
				'value'    => esc_attr( $this->helpers->get_account_api_url_option() ),
				'label'    => esc_attr_x( 'API URL', 'ActiveCampaign', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);

		// Show API key field
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'API_KEY_OPTION' ),
				'value'    => esc_attr( $this->helpers->get_account_api_key_option() ),
				'label'    => esc_attr_x( 'API key', 'ActiveCampaign', 'uncanny-automator' ),
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
		// Output data manager for Tags, lists, and custom fields
		$this->output_data_manager();

		// Output panel separator
		$this->output_panel_separator();

		// Output webhook settings with switch and conditional content
		$this->output_webhook_settings();
	}

	////////////////////////////////////////////////////////////
	// Custom ActiveCampaign templating.
	////////////////////////////////////////////////////////////

	/**
	 * Output the data manager for Tags, lists, and custom fields
	 *
	 * @return void
	 */
	private function output_data_manager() {
		$this->output_panel_subtitle( esc_html_x( 'ActiveCampaign Data', 'ActiveCampaign', 'uncanny-automator' ) );
		$this->output_subtle_panel_paragraph( esc_html_x( 'The following data is available for use in your recipes:', 'ActiveCampaign', 'uncanny-automator' ) );

		$table_data = $this->get_transient_refresh_table_data();
		$this->output_settings_table( $table_data['columns'], $table_data['data'], 'card', false );
	}

	/**
	 * Output the webhook content.
	 *
	 * @return void
	 */
	public function output_webhook_content() {
		$this->output_webhook_instructions(
			array(
				'sections' => array(
					array(
						'type'    => 'text',
						'content' => sprintf(
							esc_html_x( "Enabling ActiveCampaign triggers requires setting up a webhook in your ActiveCampaign account using the URL below. A few steps and you'll be up and running in no time. Visit our %1\$s for simple instructions.", 'Active Campaign', 'uncanny-automator' ),
							$this->get_escaped_link(
								automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/activecampaign-triggers/', 'settings', 'active-campaign-triggers-kb_article' ),
								esc_html_x( 'Knowledge Base article', 'ActiveCampaign', 'uncanny-automator' )
							)
						),
					),
					array(
						'type'   => 'field',
						'config' => array(
							'value'    => $this->webhooks->get_authorized_url(),
							'label'    => esc_attr_x( 'Webhook URL', 'Active Campaign', 'uncanny-automator' ),
							'disabled' => true,
						),
					),
					$this->get_webhook_regeneration_button(),
				),
			)
		);
	}
}
