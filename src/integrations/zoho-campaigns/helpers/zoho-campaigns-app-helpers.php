<?php
namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

use Exception;

/**
 * Helper class for Zoho Campaigns integration.
 *
 * Provides shared option configurations and AJAX handlers for recipe UI.
 *
 * @since 4.10
 *
 * @property Zoho_Campaigns_Api_Caller $api
 */
class Zoho_Campaigns_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate credentials.
	 *
	 * @param array $credentials -The credentials.
	 * @param array $args - Optional arguments.
	 *
	 * @return mixed - Array or string of credentials
	 * @throws Exception If the credentials are invalid.
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		$signature = $credentials['vault_signature'] ?? '';
		$user_id   = $credentials['user_id'] ?? '';

		if ( empty( $signature ) || empty( $user_id ) ) {
			throw new Exception(
				esc_html_x( 'Invalid credentials. Please refresh and try connecting again.', 'Zoho Campaigns', 'uncanny-automator' )
			);
		}

		return $credentials;
	}

	////////////////////////////////////////////////////////////
	// Shared Option Configs
	////////////////////////////////////////////////////////////

	/**
	 * Get list dropdown option configuration.
	 *
	 * @return array The option configuration array.
	 */
	public function get_list_option_config() {
		return array(
			'option_code'              => 'LIST',
			'label'                    => esc_html_x( 'List', 'Zoho Campaigns', 'uncanny-automator' ),
			'custom_value_description' => esc_html_x( 'List key', 'Zoho Campaigns', 'uncanny-automator' ),
			'token_name'               => esc_html_x( 'List ID', 'Zoho Campaigns', 'uncanny-automator' ),
			'input_type'               => 'select',
			'options'                  => array(),
			'ajax'                     => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_zoho_campaigns_get_list_options',
			),
			'required'                 => true,
			'options_show_id'          => false,
		);
	}

	/**
	 * Get email option configuration.
	 *
	 * @param string $option_code The option code for the field.
	 *
	 * @return array The option configuration array.
	 */
	public function get_email_option_config( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Email', 'Zoho Campaigns', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);
	}

	////////////////////////////////////////////////////////////
	// AJAX Handlers
	////////////////////////////////////////////////////////////

	/**
	 * AJAX handler for list options.
	 *
	 * Endpoint: automator_zoho_campaigns_get_list_options
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_list_options() {
		Automator()->utilities->verify_nonce();

		try {
			$lists = $this->api->get_lists( $this->is_ajax_refresh() );
			$this->ajax_success( $lists );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for topic options.
	 *
	 * Endpoint: automator_zoho_campaigns_get_topic_options
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_topic_options() {
		Automator()->utilities->verify_nonce();

		try {
			$topics = $this->api->get_topics( $this->is_ajax_refresh() );
			$this->ajax_success( $topics );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for contact fields rows.
	 *
	 * Endpoint: automator_zoho_campaigns_get_fields_rows
	 *
	 * @return void Sends JSON response.
	 */
	public function ajax_get_fields_rows() {
		Automator()->utilities->verify_nonce();

		try {
			$rows = $this->api->get_fields( $this->is_ajax_refresh() );
			$this->ajax_success( $rows, 'rows' );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage(), 'rows' );
		}
	}
}
