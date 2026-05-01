<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class ConvertKit_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property ConvertKit_Api_Caller $api
 */
class ConvertKit_App_Helpers extends App_Helpers {

	////////////////////////////////////////////////////////////
	// Framework methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate credentials.
	 *
	 * @param array $credentials The credentials to validate.
	 * @param array $args Optional arguments.
	 *
	 * @return array The validated credentials.
	 *
	 * @throws Exception If credentials are invalid.
	 */
	public function validate_credentials( $credentials, $args = array() ) {

		if ( ! empty( $credentials['vault_signature'] ) ) {
			return $credentials;
		}

		throw new Exception(
			esc_html_x( 'Kit is not connected. Please connect your Kit account in the settings.', 'ConvertKit', 'uncanny-automator' )
		);
	}

	////////////////////////////////////////////////////////////
	// Common recipe field option configs
	////////////////////////////////////////////////////////////

	/**
	 * Get the tag select field option config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_tag_option_config( $option_code ) {
		return array(
			'option_code'              => $option_code,
			'label'                    => esc_attr_x( 'Tag', 'ConvertKit', 'uncanny-automator' ),
			'input_type'               => 'select',
			'options'                  => array(),
			'token_name'               => esc_attr_x( 'Tag ID', 'ConvertKit', 'uncanny-automator' ),
			'custom_value_description' => esc_attr_x( 'Tag ID', 'ConvertKit', 'uncanny-automator' ),
			'required'                 => true,
			'ajax'                     => array(
				'endpoint' => 'automator_convertkit_tags_dropdown_handler',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get the email text field option config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_email_option_config( $option_code = 'EMAIL', $supports_custom_value = true ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'Email address', 'ConvertKit', 'uncanny-automator' ),
			'input_type'            => 'email',
			'required'              => true,
			'supports_custom_value' => $supports_custom_value,
		);
	}

	/**
	 * Get the first name text field option config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_first_name_option_config( $option_code = 'FIRST_NAME' ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_attr_x( 'First name', 'ConvertKit', 'uncanny-automator' ),
			'input_type'  => 'text',
		);
	}

	////////////////////////////////////////////////////////////
	// Validation methods
	////////////////////////////////////////////////////////////


	/**
	 * Validate and return a sanitized email address.
	 *
	 * @param string $email The email to validate.
	 *
	 * @return string The validated email.
	 *
	 * @throws Exception If the email is empty or invalid.
	 */
	public function require_valid_email( $email ) {
		$email = sanitize_text_field( $email );
		if ( empty( $email ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception(
				esc_html_x( 'Please provide a valid email address.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		return $email;
	}

	/**
	 * Validate and return a sanitized tag ID.
	 *
	 * @param mixed $tag_id The tag ID to validate.
	 *
	 * @return int The validated tag ID.
	 *
	 * @throws Exception If the tag ID is empty or not numeric.
	 */
	public function require_valid_tag_id( $tag_id ) {
		$tag_id = absint( $tag_id );
		if ( empty( $tag_id ) ) {
			throw new Exception(
				esc_html_x( 'Please select a valid tag.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		return $tag_id;
	}

	////////////////////////////////////////////////////////////
	// AJAX handlers
	////////////////////////////////////////////////////////////

	/**
	 * AJAX handler for forms dropdown.
	 *
	 * @return void
	 */
	public function get_form_options_ajax() {

		Automator()->utilities->ajax_auth_check();

		try {
			$this->ajax_success(
				$this->get_cached_select_options( 'forms', 'list_forms', 'forms' )
			);
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for sequences dropdown.
	 *
	 * @return void
	 */
	public function get_sequence_options_ajax() {

		Automator()->utilities->ajax_auth_check();

		try {
			$this->ajax_success(
				$this->get_cached_select_options( 'sequences', 'list_sequences', 'courses' )
			);
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for tags dropdown.
	 *
	 * @return void
	 */
	public function get_tag_options_ajax() {

		Automator()->utilities->ajax_auth_check();

		try {
			$this->ajax_success(
				$this->get_cached_select_options( 'tags', 'list_tags', 'tags' )
			);
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for custom fields repeater rows (v4 only).
	 *
	 * Returns full repeater row data for the custom fields repeater.
	 *
	 * @return void
	 */
	public function get_custom_field_rows_ajax() {

		Automator()->utilities->ajax_auth_check();

		if ( $this->is_ajax_refresh() ) {
			$this->delete_prefixed_app_option( 'custom_fields' );
		}

		try {

			$rows = array();

			foreach ( $this->get_custom_fields() as $field ) {
				$rows[] = array(
					'CONVERTKIT_FIELD_KEY'    => $field['key'],
					'CONVERTKIT_FIELD_NAME'   => $field['label'],
					'CONVERTKIT_FIELD_VALUE'  => '',
					'CONVERTKIT_UPDATE_FIELD' => true,
				);
			}

			$this->ajax_success( $rows, 'rows' );

		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage(), 'rows' );
		}
	}

	////////////////////////////////////////////////////////////
	// Option data handlers
	////////////////////////////////////////////////////////////

	/**
	 * Get cached select options from a Kit API endpoint.
	 *
	 * Fetches data from the given API action, formats each record
	 * as a select option (id → value, name → text), and caches the result.
	 *
	 * @param string $cache_key    The cache key suffix (e.g. 'forms', 'tags').
	 * @param string $api_action   The API action to request (e.g. 'list_forms').
	 * @param string $response_key The key in response['data'] containing the records.
	 *
	 * @return array The formatted select options.
	 */
	private function get_cached_select_options( $cache_key, $api_action, $response_key ) {

		$option_key = $this->get_option_key( $cache_key );
		$cached     = $this->get_app_option( $option_key );

		if ( ! $this->is_ajax_refresh() && ! $cached['refresh'] && ! empty( $cached['data'] ) ) {
			return $cached['data'];
		}

		$response = $this->api->api_request( $api_action );
		$options  = array();

		if ( ! empty( $response['data'][ $response_key ] ) ) {
			foreach ( $response['data'][ $response_key ] as $record ) {
				$options[] = array(
					'value' => $record['id'],
					'text'  => $record['name'],
				);
			}
		}

		$this->save_app_option( $option_key, $options );

		return $options;
	}

	/**
	 * Get cached custom fields from the Kit v4 API.
	 *
	 * Returns raw key/label pairs for use in repeater default rows.
	 *
	 * @return array Array of [ 'key' => string, 'label' => string ] entries.
	 */
	public function get_custom_fields() {

		$option_key = $this->get_option_key( 'custom_fields' );
		$cached     = $this->get_app_option( $option_key );

		if ( ! $cached['refresh'] && ! empty( $cached['data'] ) ) {
			return $cached['data'];
		}

		try {
			$response = $this->api->api_request( 'list_custom_fields' );
		} catch ( Exception $e ) {
			return array();
		}

		$fields = array();

		if ( ! empty( $response['data']['custom_fields'] ) ) {
			foreach ( $response['data']['custom_fields'] as $field ) {
				$fields[] = array(
					'key'   => $field['key'],
					'label' => $field['label'],
				);
			}
		}

		$this->save_app_option( $option_key, $fields );

		return $fields;
	}

	////////////////////////////////////////////////////////////
	// Miscellaneous helpers
	////////////////////////////////////////////////////////////

	/**
	 * Get the API version of the current connection.
	 *
	 * Returns one of:
	 *   'v3'         — legacy v3 API key + secret
	 *   'v4-api-key' — v4 personal API key (X-Kit-Api-Key header)
	 *   'v4-oauth'   — v4 OAuth (Bearer token)
	 *   ''           — no connection / unknown
	 *
	 * @return string
	 */
	public function get_api_version() {
		try {
			$credentials = $this->get_credentials();
			$version     = $credentials['version'] ?? '';

			// Proxy stores OAuth connections with version: 'v4'. Map to
			// 'v4-oauth' here so callers don't have to special-case it.
			if ( 'v4' === $version ) {
				return 'v4-oauth';
			}

			return (string) $version;
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Check if the current connection uses the legacy v3 API (API key).
	 *
	 * @return bool True if using v3, false otherwise.
	 */
	public function is_v3() {
		return 'v3' === $this->get_api_version();
	}

	/**
	 * Check if the current connection uses a v4 personal API key.
	 *
	 * @return bool
	 */
	public function is_v4_api_key() {
		return 'v4-api-key' === $this->get_api_version();
	}

	/**
	 * Check if the current connection uses v4 OAuth.
	 *
	 * @return bool
	 */
	public function is_v4_oauth() {
		return 'v4-oauth' === $this->get_api_version();
	}

	/**
	 * Check if the current connection uses either v4 variant.
	 *
	 * @return bool
	 */
	public function is_v4() {
		return in_array( $this->get_api_version(), array( 'v4-api-key', 'v4-oauth' ), true );
	}

	/**
	 * Transient key for the cached OAuth enablement status.
	 *
	 * @var string
	 */
	const OAUTH_STATUS_TRANSIENT = 'automator_convertkit_oauth_enabled';

	/**
	 * Transient expiry for the OAuth status cache (12 hours — polled twice daily).
	 *
	 * @var int
	 */
	const OAUTH_STATUS_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Whether OAuth (Quick connect) is currently enabled on the proxy.
	 *
	 * Cached for 12 hours so we don't hit the proxy on every settings
	 * page render. On cache miss, the result is fetched from the proxy
	 * and stored. Any fetch failure falls back to false (hide the OAuth
	 * radio) so a transient API outage doesn't leave users staring at
	 * an option they can't actually use.
	 *
	 * @return bool
	 */
	public function is_oauth_enabled() {

		$cached = get_transient( self::OAUTH_STATUS_TRANSIENT );

		// Transient stores '1' / '0' so a genuine `false` (cache miss)
		// can be told apart from a cached-false status.
		if ( false !== $cached ) {
			return '1' === $cached;
		}

		try {
			$enabled = $this->api->fetch_oauth_status();
		} catch ( Exception $e ) {
			$enabled = false;
		}

		set_transient(
			self::OAUTH_STATUS_TRANSIENT,
			$enabled ? '1' : '0',
			self::OAUTH_STATUS_TTL
		);

		return $enabled;
	}

	/**
	 * Formats the time to WordPress' readable format with respect to timezone.
	 *
	 * @param string $datetime The datetime to format.
	 *
	 * @return string The time formatted. Returns empty string for invalid dates.
	 */
	public function get_formatted_time( $datetime ) {
		try {
			$date = new \DateTime( $datetime );
			$date->setTimezone( new \DateTimeZone( Automator()->get_timezone_string() ) );
		} catch ( \Exception $e ) {
			return '';
		}

		return $date->format(
			sprintf(
				'%s %s',
				get_option( 'date_format', 'F j, Y' ),
				get_option( 'time_format', 'g:i a' )
			)
		);
	}
}
