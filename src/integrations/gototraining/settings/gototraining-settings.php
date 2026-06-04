<?php
/**
 * Creates the settings page
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator\Integrations\Gototraining;

use Uncanny_Automator\Settings\App_Integration_Settings;
use Exception;

/**
 * GoToTraining Settings
 *
 * @property Gototraining_App_Helpers $helpers
 * @property Gototraining_Api_Caller $api
 */
class Gototraining_Settings extends App_Integration_Settings {

	////////////////////////////////////////////////////////////
	// Integration custom OAuth flow
	// - User's define the callback URL in their own apps
	//   so this has to stay in plugin code.
	////////////////////////////////////////////////////////////

	/**
	 * Register hooks for OAuth callback detection.
	 *
	 * GoTo OAuth redirects back with just ?code=xxx (no automator_oauth_callback param)
	 * so we need to detect the callback ourselves on settings page load.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'load-uo-recipe_page_uncanny-automator-config', array( $this, 'maybe_handle_oauth_callback' ) );
	}

	/**
	 * Handle OAuth callback from GoTo on settings page load.
	 *
	 * @return void
	 */
	public function maybe_handle_oauth_callback() {

		// Only process on our settings page.
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		// Validate state nonce (includes user ID for uniqueness).
		if ( ! automator_filter_has_var( 'state' ) || ! wp_verify_nonce( automator_filter_input( 'state' ), 'automator_gtt_oauth_' . get_current_user_id() ) ) {
			return;
		}

		// Require authorization code.
		if ( ! automator_filter_has_var( 'code' ) ) {
			return;
		}

		try {
			$this->process_oauth_authentication();
		} catch ( Exception $e ) {
			$this->register_alert(
				array(
					'type'    => 'error',
					'heading' => esc_html_x( 'Connection failed', 'GoToTraining', 'uncanny-automator' ),
					'content' => esc_html( $e->getMessage() ),
				)
			);
		}

		// Redirect back to clean settings page URL.
		wp_safe_redirect( $this->get_settings_page_url() );
		exit;
	}

	/**
	 * Get the GoTo OAuth authorization URL.
	 *
	 * @return string
	 */
	private function get_oauth_url() {
		return add_query_arg(
			array(
				'response_type' => 'code',
				'client_id'     => $this->helpers->get_client_id(),
				'redirect_uri'  => rawurlencode( $this->get_settings_page_url() ),
				'state'         => wp_create_nonce( 'automator_gtt_oauth_' . get_current_user_id() ),
			),
			$this->api->get_oauth_base_url() . 'authorize'
		);
	}

	/**
	 * Process OAuth authentication callback from GoTo.
	 *
	 * @return void
	 * @throws Exception If authentication fails.
	 */
	private function process_oauth_authentication() {

		// Check for error from GoTo.
		if ( automator_filter_has_var( 'error' ) ) {
			$error_description = automator_filter_input( 'error_description' );
			$error             = ! empty( $error_description ) ? $error_description : automator_filter_input( 'error' );
			throw new Exception( esc_html( $error ) );
		}

		// Exchange the code for tokens via API caller.
		$code       = sanitize_text_field( wp_unslash( automator_filter_input( 'code' ) ) );
		$state      = sanitize_text_field( wp_unslash( automator_filter_input( 'state' ) ) );
		$token_data = $this->api->exchange_code_for_tokens( $code, $state );

		// Store credentials.
		$this->helpers->store_credentials( $token_data );

		// Register success alert.
		$this->register_alert( $this->get_connected_alert() );
	}

	////////////////////////////////////////////////////////////
	// Required abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Get formatted account information for connected user info display.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$credentials = $this->helpers->get_credentials();

		$first_name   = $credentials['firstName'] ?? '';
		$last_name    = $credentials['lastName'] ?? '';
		$display_name = trim( "{$first_name} {$last_name}" );
		$email        = $credentials['email'] ?? '';

		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => ! empty( $display_name ) ? strtoupper( $display_name[0] ) : 'G',
			'main_info'      => ! empty( $display_name ) ? $display_name : esc_html_x( 'GoTo Training account', 'GoToTraining', 'uncanny-automator' ),
			'main_info_icon' => true,
			'additional'     => $email,
		);
	}

	////////////////////////////////////////////////////////////
	// Framework overrides
	////////////////////////////////////////////////////////////

	/**
	 * Register disconnected options.
	 *
	 * @return void
	 */
	protected function register_disconnected_options() {
		$this->register_option( $this->helpers->get_const( 'CLIENT_ID_OPTION' ) );
		$this->register_option( $this->helpers->get_const( 'CLIENT_SECRET_OPTION' ) );
	}

	/**
	 * After authorization, redirect to GoTo OAuth.
	 *
	 * @param array $response The current response array.
	 * @param array $data The posted data.
	 *
	 * @return array
	 */
	protected function after_authorization( $response = array(), $data = array() ) {
		$client_id     = $this->helpers->get_client_id();
		$client_secret = $this->helpers->get_client_secret();

		if ( ! empty( $client_id ) && ! empty( $client_secret ) ) {
			$response['redirect_url'] = $this->get_oauth_url();
		}

		return $response;
	}

	////////////////////////////////////////////////////////////
	// Content output methods
	////////////////////////////////////////////////////////////

	/**
	 * Output main disconnected content.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {

		// Output disconnected header with description.
		$this->output_disconnected_header(
			esc_html_x( 'Automatically register users for GoTo Training sessions when they complete actions on your site, such as completing a course, filling out a form, or even simply clicking a button!', 'GoToTraining', 'uncanny-automator' )
		);

		// Output available recipe items.
		$this->output_available_items();

		// Output separator.
		$this->output_panel_separator();

		// Output setup instructions with redirect URL.
		$this->output_setup_instructions(
			sprintf(
				// translators: %s: Knowledge Base article link
				esc_html_x( "Connecting to GoTo Training requires setting up an application and getting 2 values from inside your account. It's really easy, we promise! Visit our %s for simple instructions.", 'GoToTraining', 'uncanny-automator' ),
				$this->get_escaped_link(
					automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/gototraining/', 'settings', 'gototraining-kb_article' ),
					esc_html_x( 'Knowledge Base article', 'GoToTraining', 'uncanny-automator' )
				)
			),
		);

		// Output Client ID field.
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'CLIENT_ID_OPTION' ),
				'value'    => esc_attr( $this->helpers->get_client_id() ),
				'label'    => esc_attr_x( 'Client ID', 'GoToTraining', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);

		// Output Client Secret field.
		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_const( 'CLIENT_SECRET_OPTION' ),
				'value'    => esc_attr( $this->helpers->get_client_secret() ),
				'label'    => esc_attr_x( 'Client secret', 'GoToTraining', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);
	}

	/**
	 * Generate steps list - overridden to output redirect URL field.
	 * 2nd $steps parameter from output_setup_instructions() method.
	 *
	 * @param array $steps The steps to display.
	 *
	 * @return string
	 */
	public function generate_steps_list( $steps = array() ) {
		$this->text_input_html(
			array(
				'id'                => 'uap_automator_gototraining_redirect_url',
				'value'             => $this->get_settings_page_url(),
				'label'             => esc_attr_x( 'Redirect URL', 'GoToTraining', 'uncanny-automator' ),
				'required'          => false,
				'disabled'          => true,
				'copy-to-clipboard' => true,
				'class'             => 'uap-spacing-top',
				'helper'            => esc_attr_x( "You'll be asked to enter a redirect URL.", 'GoToTraining', 'uncanny-automator' ),
			)
		);
		return '';
	}
}
