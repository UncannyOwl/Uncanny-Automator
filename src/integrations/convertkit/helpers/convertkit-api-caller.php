<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class ConvertKit_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property ConvertKit_App_Helpers $helpers
 */
class ConvertKit_Api_Caller extends Api_Caller {

	/**
	 * Prepare credentials for use in API requests (OAuth flow).
	 *
	 * @param array $credentials The raw credentials from options.
	 * @param array $args        Additional arguments.
	 *
	 * @return string JSON-encoded credentials for the proxy.
	 */
	public function prepare_request_credentials( $credentials, $args = array() ) {
		return wp_json_encode(
			array(
				'vault_signature' => $credentials['vault_signature'],
				'kit_id'          => $credentials['kit_id'],
			)
		);
	}

	/**
	 * Authorize API keys and store vault credentials.
	 *
	 * Sends the v3 API key and secret to the API server for validation
	 * and vault storage, then persists the returned credentials locally.
	 *
	 * @param string $api_key    The Kit v3 API key.
	 * @param string $api_secret The Kit v3 API secret.
	 *
	 * @return void
	 *
	 * @throws Exception If authorization fails.
	 */
	public function authorize_api_keys( $api_key, $api_secret ) {

		$result = $this->api_request(
			array(
				'action'     => 'authorize_api_keys',
				'api_key'    => $api_key,
				'api_secret' => $api_secret,
			),
			null,
			array( 'exclude_credentials' => true )
		);

		$this->helpers->store_credentials( $result['data'] ?? array() );
	}

	/**
	 * Authorize a v4 personal API key and store vault credentials.
	 *
	 * @param string $api_key The Kit v4 personal API key.
	 *
	 * @return void
	 *
	 * @throws Exception If authorization fails.
	 */
	public function authorize_v4_api_key( $api_key ) {

		$result = $this->api_request(
			array(
				'action'  => 'authorize_v4_api_key',
				'api_key' => $api_key,
			),
			null,
			array( 'exclude_credentials' => true )
		);

		$this->helpers->store_credentials( $result['data'] ?? array() );
	}

	/**
	 * Fetch the current OAuth enablement status from the proxy.
	 *
	 * Used by the settings page to decide whether to surface the Quick
	 * connect radio. Result is cached by the caller via transient.
	 *
	 * @return bool True if OAuth is currently enabled on the proxy.
	 *
	 * @throws Exception If the request fails.
	 */
	public function fetch_oauth_status() {

		$result = $this->api_request(
			array( 'action' => 'oauth_status' ),
			null,
			array( 'exclude_credentials' => true )
		);

		return ! empty( $result['data']['oauth_enabled'] );
	}

	/**
	 * Check for errors in the API response.
	 *
	 * @param array $response The API response.
	 * @param array $args Additional arguments.
	 *
	 * @return void
	 *
	 * @throws Exception If an error is found.
	 */
	public function check_for_errors( $response, $args = array() ) {

		$status_code = isset( $response['statusCode'] ) ? intval( $response['statusCode'] ) : 0;

		// 200 = success, 201 = created (v4 API), 204 = no content (v4 delete/unsubscribe).
		if ( $status_code >= 200 && $status_code < 300 ) {
			return;
		}

		$data = $response['data'] ?? array();

		// v4 error shape: { "errors": ["message", ...] }
		if ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: ConvertKit API error message */
					esc_html_x( 'Kit API error: %s', 'ConvertKit', 'uncanny-automator' ),
					esc_html( implode( ' | ', $data['errors'] ) )
				),
				absint( $status_code )
			);
		}

		// v3 error shape: { "error": "...", "message": "..." }
		if ( isset( $data['error'], $data['message'] ) ) {
			throw new Exception(
				sprintf(
					/* translators: %s: ConvertKit API error message */
					esc_html_x( 'Kit API error: %s', 'ConvertKit', 'uncanny-automator' ),
					esc_html( $data['message'] )
				),
				absint( $status_code )
			);
		}

		// Fallback — include status code for context when data is empty/null.
		throw new Exception(
			sprintf(
				/* translators: 1: HTTP status code, 2: response data */
				esc_html_x( 'Kit API returned status %1$d. Response: %2$s', 'ConvertKit', 'uncanny-automator' ),
				absint( $status_code ),
				esc_html( ! empty( $data ) ? wp_json_encode( $data ) : 'empty' )
			),
			absint( $status_code )
		);
	}
}
