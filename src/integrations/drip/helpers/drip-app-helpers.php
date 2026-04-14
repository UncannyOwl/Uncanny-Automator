<?php

namespace Uncanny_Automator\Integrations\Drip;

/**
 * Class Drip_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Drip_Api_Caller $api
 */
class Drip_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	////////////////////////////////////////////////////////////
	// Abstract method implementations
	////////////////////////////////////////////////////////////

	/**
	 * Get account info from stored credentials.
	 *
	 * @return array
	 */
	public function get_account_info() {
		$credentials = $this->get_credentials();
		return $credentials['account'] ?? array();
	}

	/**
	 * Prepare credentials for storage.
	 *
	 * Fetches account info from the API and stores it alongside the token
	 * to preserve the existing credential format: ['token' => {...}, 'account' => {...}]
	 *
	 * @param array $credentials The raw OAuth credentials.
	 *
	 * @return array The credentials with account info.
	 */
	public function prepare_credentials_for_storage( $credentials ) {

		$account = $this->api->get_account_info( $credentials );

		return array(
			'token'   => $credentials,
			'account' => $account,
		);
	}

	////////////////////////////////////////////////////////////
	// Validation helpers
	////////////////////////////////////////////////////////////

	/**
	 * Sanitize and validate an email address.
	 *
	 * @param string $email The email address to validate.
	 *
	 * @return string The sanitized email address.
	 * @throws \Exception If the email is empty or invalid.
	 */
	public function validate_email( $email ) {

		if ( empty( $email ) ) {
			throw new \Exception( esc_html_x( 'Email is required', 'Drip', 'uncanny-automator' ) );
		}

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( esc_html_x( 'Invalid email address', 'Drip', 'uncanny-automator' ) );
		}

		return $email;
	}

	////////////////////////////////////////////////////////////
	// Recipe UI option configs
	////////////////////////////////////////////////////////////

	/**
	 * Get email option config for action fields.
	 *
	 * @param string $option_code The option code. Default 'EMAIL'.
	 *
	 * @return array
	 */
	public function get_email_option_config( $option_code = 'EMAIL' ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Email', 'Drip', 'uncanny-automator' ),
			'input_type'      => 'email',
			'required'        => true,
			'supports_tokens' => true,
		);
	}

	/**
	 * Get tag option config for action fields.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_tag_option_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_html_x( 'Tag', 'Drip', 'uncanny-automator' ),
			'input_type'            => 'select',
			'options'               => array(),
			'required'              => true,
			'supports_custom_value' => true,
			'supports_tokens'       => true,
			'options_show_id'       => false,
			'placeholder'           => esc_html_x( 'Select a tag', 'Drip', 'uncanny-automator' ),
			'ajax'                  => array(
				'endpoint' => 'automator_drip_get_tags_options',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get campaign option config for action fields.
	 *
	 * @param string $endpoint The AJAX endpoint to use. Default 'automator_drip_get_campaigns_options'.
	 *
	 * @return array
	 */
	public function get_campaign_option_config( $endpoint = 'automator_drip_get_campaigns_options' ) {
		return array(
			'option_code'           => 'CAMPAIGN',
			'label'                 => esc_html_x( 'Campaign', 'Drip', 'uncanny-automator' ),
			'input_type'            => 'select',
			'options'               => array(),
			'required'              => true,
			'supports_custom_value' => false,
			'supports_tokens'       => false,
			'options_show_id'       => false,
			'ajax'                  => array(
				'endpoint' => $endpoint,
				'event'    => 'on_load',
			),
		);
	}

	////////////////////////////////////////////////////////////
	// Option data (cached)
	////////////////////////////////////////////////////////////

	/**
	 * Get cached select options, fetching fresh data when needed.
	 *
	 * Handles the cache-check → fetch → save cycle. The API method is only
	 * invoked when the cache is stale, empty, or the user clicked refresh.
	 *
	 * @param string $cache_key  The cache key suffix (e.g. 'tags', 'campaigns').
	 * @param string $api_method The API caller method that returns formatted options.
	 *
	 * @return array
	 */
	private function get_cached_options( $cache_key, $api_method ) {

		$option_key = $this->get_option_key( $cache_key );
		$cached     = $this->get_app_option( $option_key );

		if ( ! $this->is_ajax_refresh() && ! $cached['refresh'] && ! empty( $cached['data'] ) ) {
			return $cached['data'];
		}

		$options = $this->api->$api_method();

		$this->save_app_option( $option_key, $options );

		return $options;
	}

	/**
	 * Get tags as options for select dropdown.
	 *
	 * @return array
	 */
	public function get_tags_options() {
		return $this->get_cached_options( 'tags', 'get_tags_as_options' );
	}

	/**
	 * Get campaigns as options for select dropdown.
	 *
	 * @param bool $include_unsubscribe_all Whether to include "Unsubscribe from all" option.
	 *
	 * @return array
	 */
	public function get_campaigns_options( $include_unsubscribe_all = false ) {

		$options = $this->get_cached_options( 'campaigns', 'get_campaigns_as_options' );

		if ( $include_unsubscribe_all ) {
			$options[] = array(
				'text'  => esc_html_x( 'Unsubscribe from all campaigns', 'Drip', 'uncanny-automator' ),
				'value' => 'unsubscribe_from_all',
			);
		}

		return $options;
	}

	////////////////////////////////////////////////////////////
	// AJAX handlers
	////////////////////////////////////////////////////////////

	/**
	 * AJAX handler for tags options.
	 *
	 * @return void
	 */
	public function get_tags_options_ajax() {
		Automator()->utilities->verify_nonce();
		$this->ajax_success( $this->get_tags_options() );
	}

	/**
	 * AJAX handler for campaigns options.
	 *
	 * @return void
	 */
	public function get_campaigns_options_ajax() {
		Automator()->utilities->verify_nonce();
		$this->ajax_success( $this->get_campaigns_options() );
	}

	/**
	 * AJAX handler for campaigns options with unsubscribe all.
	 *
	 * @return void
	 */
	public function get_campaigns_with_unsubscribe_options_ajax() {
		Automator()->utilities->verify_nonce();
		$this->ajax_success( $this->get_campaigns_options( true ) );
	}

	/**
	 * AJAX handler for the custom fields repeater.
	 *
	 * Returns the full field definitions with options populated.
	 * On refresh, flushes the fields cache first so fresh data is fetched.
	 *
	 * @return void
	 */
	public function get_custom_fields_handler_ajax() {
		Automator()->utilities->verify_nonce();

		if ( $this->is_ajax_refresh() ) {
			automator_delete_option( $this->get_option_key( 'fields' ) );
		}

		$field_properties = array(
			'fields' => array(
				array(
					'option_code'     => 'FIELD_NAME',
					'label'           => esc_html_x( 'Field', 'Drip', 'uncanny-automator' ),
					'input_type'      => 'select',
					'supports_tokens' => false,
					'required'        => true,
					'read_only'       => false,
					'options_show_id' => false,
					'options'         => $this->get_cached_options( 'fields', 'get_fields_as_options' ),
					'placeholder'     => esc_html_x( 'Select a field', 'Drip', 'uncanny-automator' ),
				),
				array(
					'option_code'     => 'FIELD_VALUE',
					'label'           => esc_html_x( 'Value', 'Drip', 'uncanny-automator' ),
					'input_type'      => 'text',
					'supports_tokens' => true,
					'required'        => false,
					'read_only'       => false,
				),
			),
		);

		$this->ajax_success( $field_properties, 'field_properties' );
	}
}
