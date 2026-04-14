<?php

namespace Uncanny_Automator\Integrations\OpenAI;

use Exception;
use Uncanny_Automator\Settings\App_Integration_Settings;

/**
 * OpenAI Settings
 *
 * @package Uncanny_Automator
 *
 * @property OpenAI_App_Helpers $helpers
 * @property OpenAI_Api_Caller $api
 */
class OpenAI_Settings extends App_Integration_Settings {

	/**
	 * Get formatted account info for connected user display.
	 *
	 * @return array
	 */
	protected function get_formatted_account_info() {
		$secret_key = $this->helpers->get_credentials();
		$redacted   = substr( $secret_key, 0, 3 ) . '...' . substr( $secret_key, -4 );

		return array(
			'avatar_type'    => 'text',
			'avatar_value'   => 'O',
			'main_info'      => esc_html_x( 'OpenAI account', 'OpenAI', 'uncanny-automator' ),
			'main_info_icon' => true,
			'additional'     => sprintf(
				// translators: %s is the redacted API key.
				esc_html_x( 'API key connected: %s', 'OpenAI', 'uncanny-automator' ),
				$redacted
			),
		);
	}

	/**
	 * Register the API key field for disconnected state.
	 *
	 * @return void
	 */
	public function register_disconnected_options() {
		$this->register_option( $this->helpers->get_credentials_option_name() );
	}

	/**
	 * Validate the API key on authorize.
	 *
	 * @param array $response The current response array.
	 * @param array $data The posted data.
	 *
	 * @return array Modified response array.
	 */
	public function authorize_account( $response, $data ) {
		try {
			// Credentials are already stored by the framework at this point.
			// Validate the key by making a test API call.
			$this->api->api_request( 'get_models' );

			$this->register_connected_alert(
				esc_html_x( 'Your account has been connected successfully!', 'OpenAI', 'uncanny-automator' )
			);

		} catch ( Exception $e ) {
			// Delete stored key on failure.
			$this->helpers->delete_credentials();
			$response['success'] = false;
			$response['alert']   = $this->get_error_alert( $e->getMessage() );
		}

		return $response;
	}

	/**
	 * Output disconnected content: description, items list, setup instructions, and API key field.
	 *
	 * @return void
	 */
	public function output_main_disconnected_content() {
		$this->output_disconnected_header(
			esc_html_x(
				'Use Uncanny Automator to feed prompts to OpenAI and use AI-generated content inside your actions. Choose from multiple models and settings to automate AI-generated content on your WordPress site.',
				'OpenAI',
				'uncanny-automator'
			)
		);

		$this->output_available_items();

		$setup_url = automator_utm_parameters(
			'https://automatorplugin.com/knowledge-base/open-ai/',
			'settings',
			'open-ai-kb_article'
		);

		$this->alert_html(
			array(
				'heading' => esc_html_x( 'Setup instructions', 'OpenAI', 'uncanny-automator' ),
				'content' => sprintf(
					'%s<br/><br/><uo-button href="%s" target="_blank" color="secondary" size="small">%s</uo-button>',
					esc_html_x(
						'Connecting to OpenAI is a simple 1-step process of creating a secret API key in your OpenAI account.',
						'OpenAI',
						'uncanny-automator'
					),
					esc_url( $setup_url ),
					esc_html_x( 'Setup instructions', 'OpenAI', 'uncanny-automator' )
				),
			)
		);

		$this->text_input_html(
			array(
				'id'       => $this->helpers->get_credentials_option_name(),
				'value'    => '',
				'label'    => esc_html_x( 'Secret key', 'OpenAI', 'uncanny-automator' ),
				'required' => true,
				'class'    => 'uap-spacing-top',
			)
		);
	}

	/**
	 * Output connected content: single account message + GPT-4 access alerts.
	 *
	 * @return void
	 */
	public function output_main_connected_content() {
		$this->output_single_account_message(
			esc_html_x(
				'If you create recipes and then change the connected OpenAI account, your previous recipes may no longer work.',
				'OpenAI',
				'uncanny-automator'
			)
		);

		// GPT-4 access alerts.
		if ( $this->helpers->has_gpt4_access() ) {
			$this->alert_html(
				array(
					'type'    => 'success',
					'heading' => esc_html_x( 'The connected account has access to the GPT-4 API.', 'OpenAI', 'uncanny-automator' ),
				)
			);
		} else {
			$this->alert_html(
				array(
					'type'    => 'warning',
					'heading' => esc_html_x( 'GPT-4 API access', 'OpenAI', 'uncanny-automator' ),
					'content' => esc_html_x(
						'The connected account does not currently have access to the GPT-4 API. Once you gain access to the GPT-4 API, additional OpenAI actions will become available. If you have recently been granted access to GPT-4, please create a new key, disconnect the current connection, and reconnect by entering your new key. You may also use the button below to recheck access to GPT-4.',
						'OpenAI',
						'uncanny-automator'
					),
					'button'  => array(
						'action' => 'recheck_gpt4',
						'label'  => esc_html_x( 'Recheck GPT-4 access', 'OpenAI', 'uncanny-automator' ),
						'args'   => array( 'color' => 'secondary' ),
					),
				)
			);
		}
	}

	/**
	 * Handle the recheck_gpt4 custom REST action.
	 *
	 * @param array $response The current response.
	 * @param array $data The posted data.
	 *
	 * @return array Modified response.
	 */
	public function handle_recheck_gpt4( $response, $data ) {
		automator_delete_option( $this->helpers->get_option_key( 'gpt4_access' ) );
		$response['reload'] = true;
		return $response;
	}

	/**
	 * Clean up cached option data on disconnect.
	 *
	 * @param array $response The current response.
	 * @param array $data The posted data.
	 *
	 * @return array Modified response.
	 */
	protected function before_disconnect( $response = array(), $data = array() ) {
		automator_delete_option( $this->helpers->get_option_key( 'gpt4_access' ) );
		automator_delete_option( $this->helpers->get_option_key( 'gpt_models' ) );
		automator_delete_option( $this->helpers->get_option_key( 'image_generation_models' ) );
		return $response;
	}
}
