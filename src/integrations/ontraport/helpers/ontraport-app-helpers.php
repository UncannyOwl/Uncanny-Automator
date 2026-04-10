<?php

namespace Uncanny_Automator\Integrations\Ontraport;

use Exception;
use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Ontraport_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Ontraport_Api_Caller $api
 */
class Ontraport_App_Helpers extends App_Helpers {

	////////////////////////////////////////////////////////////
	// Abstract Methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate credentials.
	 *
	 * @param mixed $credentials The credentials.
	 * @param array $args        Optional arguments.
	 *
	 * @return mixed
	 * @throws Exception If credentials are invalid.
	 */
	public function validate_credentials( $credentials, $args = array() ) {

		// Backwards compatibility for old credentials.
		if ( empty( $credentials ) ) {
			$credentials = $this->maybe_map_legacy_credentials();
		}

		if ( empty( $credentials['key'] ) || empty( $credentials['id'] ) ) {
			throw new Exception( esc_html_x( 'Ontraport is not connected', 'Ontraport', 'uncanny-automator' ) );
		}

		return $credentials;
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Maybe map legacy credentials.
	 *
	 * These legacy options are autoloaded via array-option-keys.php so
	 * reading them has no DB cost. If the user is not connected this
	 * only runs once during Ontraport_Integration::is_app_connected().
	 *
	 * @return array The credentials.
	 */
	private function maybe_map_legacy_credentials() {
		$api_key = automator_get_option( Ontraport_Settings::OPT_API_KEY );
		$app_id  = automator_get_option( Ontraport_Settings::OPT_APP_ID_KEY );
		// Bail if no credentials are found.
		if ( empty( $api_key ) || empty( $app_id ) ) {
			return array();
		}

		// Map the legacy credentials to the new credentials.
		$credentials = array(
			'key' => $api_key,
			'id'  => $app_id,
		);

		$this->store_credentials( $credentials );

		// Delete the legacy credentials.
		automator_delete_option( Ontraport_Settings::OPT_API_KEY );
		automator_delete_option( Ontraport_Settings::OPT_APP_ID_KEY );

		return $credentials;
	}

	////////////////////////////////////////////////////////////
	// Recipe UI helpers
	////////////////////////////////////////////////////////////

	/**
	 * Get the email field option config.
	 *
	 * @param string $option_code The option code for the field.
	 *
	 * @return array
	 */
	public function get_email_field( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Email', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'email',
			'required'    => true,
		);
	}

	/**
	 * Parse and validate an email address from a meta value.
	 *
	 * @param string $email The email address to validate.
	 *
	 * @return string The validated email address.
	 * @throws Exception If the email address is invalid.
	 */
	public function validate_email( $email ) {
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception(
				sprintf(
					// translators: %s: Email address
					esc_html_x( 'Invalid email provided: %s', 'Ontraport', 'uncanny-automator' ),
					esc_html( $email )
				),
				400
			);
		}

		return $email;
	}

	////////////////////////////////////////////////////////////
	// AJAX Handlers
	////////////////////////////////////////////////////////////

	/**
	 * Handler for listing tags.
	 *
	 * @return void
	 */
	public function ajax_get_tags() {
		Automator()->utilities->ajax_auth_check();
		try {
			$tags = $this->get_tags( $this->is_ajax_refresh() );
			$tags = ! empty( $tags ) ? array_values( $tags ) : array();
			$this->ajax_success( $tags );
		} catch ( Exception $e ) {
			$error = esc_html_x( 'API Exception: ', 'Ontraport', 'uncanny-automator' ) . $e->getMessage();
			$this->ajax_error( $error );
		}
	}

	/**
	 * Handler for listing custom contact fields (transposed repeater format).
	 *
	 * @return void
	 */
	public function ajax_get_custom_fields() {
		Automator()->utilities->ajax_auth_check();
		try {
			$configs = $this->get_custom_fields( $this->is_ajax_refresh() );
			// Strip internal lookup keys before sending to the UI.
			$configs = array_map(
				function ( $config ) {
					unset( $config['ontraport_type'] );
					return $config;
				},
				$configs
			);
			$this->ajax_success( array( 'fields' => $configs ), 'field_properties' );
		} catch ( Exception $e ) {
			$error = esc_html_x( 'API Exception: ', 'Ontraport', 'uncanny-automator' ) . $e->getMessage();
			$this->ajax_error( $error, 'field_properties' );
		}
	}

	////////////////////////////////////////////////////////////
	// Data Retrieval Methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the tags.
	 *
	 * @param bool $refresh Whether to refresh the tags.
	 *
	 * @return array The tags.
	 * @throws Exception If the tags cannot be retrieved.
	 */
	private function get_tags( $refresh = false ) {

		$option_key     = $this->get_option_key( 'tags' );
		$tag_data       = $this->get_app_option( $option_key );
		$tags           = $tag_data['data'];
		$should_refresh = $refresh || $tag_data['refresh'];

		if ( empty( $tags ) || $should_refresh ) {
			$response = $this->api->send_request( 'get_tags' );
			$results  = $response['data']['data'] ?? array();
			$tags     = array();
			foreach ( $results as $tag ) {
				$tags[] = array(
					'text'  => $tag['tag_name'] ?? '',
					'value' => $tag['tag_id'] ?? '',
				);
			}
			$this->save_app_option( $option_key, $tags );
		}

		return $tags;
	}

	/**
	 * Get custom contact field configs from the API with caching.
	 *
	 * Fetches raw field metadata, formats into repeater configs, and caches
	 * the formatted result. Each config includes an `ontraport_type` key for
	 * validation lookups — stripped before sending to the UI.
	 *
	 * @param bool $refresh     Whether to refresh the cache.
	 * @param bool $throw_error Whether to throw on API failure. When false,
	 *                          returns stale cache (or empty array) instead.
	 *
	 * @return array The formatted repeater field configs.
	 * @throws Exception If the fields cannot be retrieved and $throw_error is true.
	 */
	public function get_custom_fields( $refresh = false, $throw_error = true ) {

		$option_key     = $this->get_option_key( 'custom_fields' );
		$cached         = $this->get_app_option( $option_key );
		$configs        = $cached['data'];
		$should_refresh = $refresh || $cached['refresh'];

		if ( empty( $configs ) || $should_refresh ) {
			try {
				$raw_fields = $this->api->fetch_custom_fields();
				$configs    = Ontraport_Add_Update_Contact::generate_custom_field_configs( $raw_fields );
				$this->save_app_option( $option_key, $configs );
			} catch ( Exception $e ) {
				if ( $throw_error ) {
					throw $e;
				}
				// Fall back to stale cache if available.
				return ! empty( $configs ) ? $configs : array();
			}
		}

		return $configs;
	}
}
