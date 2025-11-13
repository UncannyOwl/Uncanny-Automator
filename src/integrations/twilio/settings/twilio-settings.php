<?php

namespace Uncanny_Automator\Integrations\Twilio;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * Class Twilio_Settings
 *
 * @package Uncanny_Automator
 *
 * @property Twilio_App_Helpers $helpers
 * @property Twilio_Api_Caller $api
 */
class Twilio_Settings extends App_Integration_Settings {

	/**
	 * Register disconnected options - Manual credentials for Twilio
	 *
	 * @return void
	 */
	public function register_options() {
		$this->register_option( $this->helpers->get_const( 'PHONE_NUMBER' ) );
	}

	/**
	 * Register disconnected options - Manual credentials for Twilio
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		$this->register_option( $this->helpers->get_const( 'ACCOUNT_SID' ) );
		$this->register_option( $this->helpers->get_const( 'AUTH_TOKEN' ) );
	}

	/**
	 * Get formatted account info for connected state
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$account      = $this->helpers->get_account_info();
		$phone_number = $this->helpers->get_constant_option( 'PHONE_NUMBER' );

		return array(
			'main_info'  => $account['friendly_name'],
			'additional' => sprintf(
				// translators: 1. Phone number
				esc_html_x( 'Active number: %1$s', 'Twilio', 'uncanny-automator' ),
				esc_html( $phone_number )
			),
		);
	}

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		// Disconnected header with custom description
		$this->output_disconnected_header(
			esc_html_x( 'Integrate your WordPress site directly with Twilio. Send SMS messages to users when they make a purchase, fill out a form, complete a course, or complete any combination of supported triggers.', 'Twilio', 'uncanny-automator' )
		);

		// Available items
		$this->output_available_items();

		// Setup instructions
		$this->alert_html(
			array(
				'heading' => esc_html_x( 'Setup instructions', 'Twilio', 'uncanny-automator' ),
				'content' => sprintf(
					// translators: %s: Knowledge base link
					esc_html_x( "Connecting to Twilio requires getting 3 values from inside your account. It's really easy, we promise! Visit our %s for simple instructions.", 'Twilio', 'uncanny-automator' ),
					$this->get_escaped_link(
						automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/twilio/', 'settings', 'twilio-kb_article' ),
						esc_html_x( 'Knowledge Base article', 'Twilio', 'uncanny-automator' )
					)
				),
			)
		);

		// Display App fields
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'ACCOUNT_SID' ),
				'value'    => $this->helpers->get_constant_option( 'ACCOUNT_SID' ),
				'label'    => esc_html_x( 'Account SID', 'Twilio', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
				'helper'   => esc_html_x( 'Your Twilio Account SID from the console dashboard', 'Twilio', 'uncanny-automator' ),
			)
		);

		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'AUTH_TOKEN' ),
				'value'    => $this->helpers->get_constant_option( 'AUTH_TOKEN' ),
				'label'    => esc_html_x( 'Auth token', 'Twilio', 'uncanny-automator' ),
				'type'     => 'password',
				'required' => true,
				'class'    => 'uap-spacing-top',
				'helper'   => esc_html_x( 'Your Twilio Auth Token from the console dashboard', 'Twilio', 'uncanny-automator' ),
			)
		);

		$this->output_phone_number_input();
	}

	/**
	 * Display - Main connected content
	 *
	 * @return void - Outputs HTML directly
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message();

		$this->output_phone_number_input();
	}

	/**
	 * Output phone number input.
	 *
	 * @return void
	 */
	public function output_phone_number_input() {
		$this->text_input_html(
			array(
				'id'          => $this->helpers->get_const( 'PHONE_NUMBER' ),
				'value'       => $this->helpers->get_constant_option( 'PHONE_NUMBER' ),
				'label'       => esc_html_x( 'Active number', 'Twilio', 'uncanny-automator' ),
				'placeholder' => '+15017122661',
				'required'    => true,
				'class'       => 'uap-spacing-top',
				'helper'      => sprintf(
					// translators: %s: Link to Twilio active numbers page
					esc_html_x( 'See your list of active phone numbers on the %s page.', 'Twilio', 'uncanny-automator' ),
					$this->get_escaped_link(
						'https://www.twilio.com/console/phone-numbers/incoming',
						esc_html_x( 'Active numbers', 'Twilio', 'uncanny-automator' )
					)
				),
			)
		);
	}

	/**
	 * Output the save settings button - override to revalidate phone number.
	 *
	 * @return void
	 */
	protected function output_save_settings_button() {
		$this->output_action_button(
			'phone_number_update',
			esc_html_x(
				'Update active number',
				'Twilio',
				'uncanny-automator'
			)
		);
	}

	/**
	 * Handle authorization flow ( registered options have been saved ).
	 *
	 * @param array $response
	 * @param array $options
	 *
	 * @return array
	 */
	public function authorize_account( $response, $options ) {
		try {
			// Clear any existing data.
			$this->helpers->delete_account_info();

			// Clean phone number before building credentials.
			$phone_number = $options[ $this->helpers->get_const( 'PHONE_NUMBER' ) ];
			$phone_number = $this->helpers->validate_phone_number( $phone_number );

			if ( ! $phone_number ) {
				throw new Exception( esc_html_x( 'Phone number format is invalid', 'Twilio', 'uncanny-automator' ) );
			}

			// Build credentials array with cleaned phone number.
			$credentials = array(
				'account_sid'  => $options[ $this->helpers->get_const( 'ACCOUNT_SID' ) ],
				'auth_token'   => $options[ $this->helpers->get_const( 'AUTH_TOKEN' ) ],
				'phone_number' => $phone_number,
			);

			// Validate credentials format.
			$validation = $this->helpers->validate_credentials( $credentials );
			if ( is_wp_error( $validation ) ) {
				throw new Exception( $validation->get_error_message() );
			}

			// Store credentials temporarily for API validation.
			$this->helpers->store_credentials( $credentials );

			// Validate phone number exists in the account.
			$this->api->validate_phone_number_in_account( $credentials['phone_number'] );

			// Test the connection by getting account info.
			$account_info = $this->api->get_account_info();

			// Store account info.
			$this->helpers->store_account_info( $account_info );

			// Register success alert for reload.
			$this->register_success_alert(
				esc_html_x( 'You have successfully connected your Twilio account', 'Twilio', 'uncanny-automator' )
			);

			$response['reload'] = true;

		} catch ( Exception $e ) {
			$response['success'] = false;
			$response['alert']   = $this->get_error_alert( $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Handle phone number update.
	 *
	 * @param array $response
	 * @param array $data
	 *
	 * @return array $response
	 */
	public function handle_phone_number_update( $response, $data ) {

		// Get the new and old phone numbers.
		$new = $data[ $this->helpers->get_const( 'PHONE_NUMBER' ) ];
		$new = $this->helpers->validate_phone_number( $new );
		$old = $this->helpers->get_constant_option( 'PHONE_NUMBER' );
		$old = $this->helpers->validate_phone_number( $old );

		if ( ! $new ) {
			$response['success'] = false;
			$response['alert']   = $this->get_error_alert(
				esc_html_x( 'The phone number format is invalid.', 'Twilio', 'uncanny-automator' )
			);
			return $response;
		}

		// Check if number was actually updated.
		if ( $new === $old ) {
			$response['success'] = false;
			$response['alert']   = $this->get_error_alert(
				esc_html_x( 'No changes made to your current active number.', 'Twilio', 'uncanny-automator' )
			);
			return $response;
		}

		// Set reload to update the UI.
		$response['reload'] = true;

		try {
			// Validate phone number exists in the Twilio account.
			$this->api->validate_phone_number_in_account( $new );

			// Update the phone number in the credentials.
			$credentials                 = $this->helpers->get_credentials();
			$credentials['phone_number'] = $new;
			$this->helpers->store_credentials( $credentials );

			// Register success alert for reload.
			$this->register_success_alert(
				esc_html_x( 'You have successfully updated your active number', 'Twilio', 'uncanny-automator' )
			);
		} catch ( Exception $e ) {
			$response['success'] = false;
			// Register error alert for reload.
			$this->register_error_alert(
				sprintf(
					// translators: %s: Error message
					esc_html_x( 'The following error occurred while updating your active number: %s', 'Twilio', 'uncanny-automator' ),
					esc_html( $e->getMessage() )
				),
				esc_html_x( 'Error updating active number', 'Twilio', 'uncanny-automator' )
			);
		}

		return $response;
	}
}
