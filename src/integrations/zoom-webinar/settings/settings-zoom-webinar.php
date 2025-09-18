<?php

namespace Uncanny_Automator\Integrations\Zoom_Webinar;

use Uncanny_Automator\Settings\App_Integration_Settings;

/**
 * Class Zoom_Webinar_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Zoom_Webinar_App_Helpers $helpers
 * @property Zoom_Webinar_Api_Caller $api
 */
class Zoom_Webinar_Settings extends App_Integration_Settings {

	/**
	 * Get formatted account info for connected user display.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$user = $this->helpers->get_account_info();

		if ( empty( $user['email'] ) ) {
			return array();
		}

		return array(
			'avatar_type'    => 'icon',
			'avatar_value'   => $this->get_icon(),
			'main_info'      => $user['email'],
			'main_info_icon' => false,
			'additional'     => ! empty( $user['display_name'] ) ? $user['display_name'] : '',
		);
	}

	/**
	 * Register disconnected options.
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		$this->register_option( $this->helpers->get_const( 'ACCOUNT_ID' ) );
		$this->register_option( $this->helpers->get_const( 'CLIENT_ID' ) );
		$this->register_option( $this->helpers->get_const( 'CLIENT_SECRET' ) );
		$this->register_option( 'uap_automator_zoom_webinar_api_settings_version' );
	}

	/**
	 * Handle authorization flow ( registered options have been saved ).
	 *
	 * @param array $data
	 * @param array $response
	 *
	 * @return array
	 */
	public function authorize_account( $data, $response ) {
		try {
			// Clear any existing data.
			$this->helpers->delete_account_info();
			$this->helpers->delete_credentials();

			// Authorize account ( sets token credentials ).
			$this->api->authorize_account();

			// Get user info.
			$user = $this->api->get_user_info();

			// Store user info
			$this->helpers->store_account_info( $user['data'] );

			// Register success alert for reload.
			$this->register_success_alert(
				esc_html_x( 'You have successfully connected your Zoom Webinar account', 'Zoom Webinar', 'uncanny-automator' )
			);

			$response['reload'] = true;

		} catch ( \Exception $e ) {
			$response['success'] = false;
			$response['alert']   = $this->get_error_alert( $e->getMessage() );
		}

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Templating methods
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 */
	public function output_main_disconnected_content() {

		// Disconnected header with custom description.
		$this->output_disconnected_header(
			esc_html_x( 'Automatically register users for Zoom Webinars when they complete actions on your site, such as completing a course, filling out a form, or even simply clicking a button!', 'Zoom Webinar', 'uncanny-automator' )
		);

		// Automatically generated list of available triggers and actions scanned from Premium_Integration_Items trait.
		$this->output_available_items();

		// Setup instructions.
		$this->alert_html(
			array(
				'heading' => esc_html_x( 'Setup instructions', 'Zoom Webinar', 'uncanny-automator' ),
				'content' => sprintf(
					// translators: %1$s: Knowledge Base article link
					esc_html_x( "Connecting to Zoom requires setting up a Server-to-Server OAuth app and getting 3 values from inside your account. It's really easy, we promise! Visit our %1\$s for simple instructions.", 'Zoom Webinar', 'uncanny-automator' ),
					$this->get_escaped_link(
						automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/zoom/', 'settings', 'zoom_meeting-kb_article' ),
						esc_html_x( 'Knowledge Base article', 'Zoom Webinar', 'uncanny-automator' )
					)
				),
			)
		);

		// Display App fields.
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'ACCOUNT_ID' ),
				'value'    => $this->helpers->get_const_option_value( 'ACCOUNT_ID' ),
				'label'    => esc_html_x( 'Account ID', 'Zoom Webinar', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);

		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'CLIENT_ID' ),
				'value'    => $this->helpers->get_const_option_value( 'CLIENT_ID' ),
				'label'    => esc_html_x( 'Client ID', 'Zoom Webinar', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);

		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'CLIENT_SECRET' ),
				'value'    => $this->helpers->get_const_option_value( 'CLIENT_SECRET' ),
				'label'    => esc_html_x( 'Client secret', 'Zoom Webinar', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);

		$this->text_input_html(
			array(
				'id'       => 'uap_automator_zoom_webinar_api_settings_version',
				'value'    => '3',
				'hidden'   => true,
				'disabled' => true,
			)
		);
	}
}
