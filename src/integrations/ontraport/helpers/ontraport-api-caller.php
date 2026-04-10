<?php

namespace Uncanny_Automator\Integrations\Ontraport;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Ontraport_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Ontraport_App_Helpers $helpers
 */
class Ontraport_Api_Caller extends Api_Caller {

	/**
	 * Check for errors in the API response.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs.
	 */
	public function check_for_errors( $response, $args = array() ) {

		$status = absint( $response['statusCode'] ?? 0 );

		if ( ! in_array( $status, array( 200, 201 ), true ) ) {

			$message = isset( $response['data']['message'] )
				? $response['data']['message']
				// translators: 1: Status code.
				: sprintf( esc_html_x( 'Ontraport API error: Received unexpected status code %1$s.', 'Ontraport', 'uncanny-automator' ), $status );

			throw new Exception( esc_html( $message ), absint( $status ) );
		}
	}

	/**
	 * Fetch custom contact field definitions from the Ontraport API.
	 *
	 * Returns only editable, user-defined (f-prefixed) fields,
	 * excluding file fields which are read-only via the API.
	 *
	 * @return array The filtered custom field definitions.
	 * @throws Exception If the request fails.
	 */
	public function fetch_custom_fields() {

		$response   = $this->send_request( 'get_fields' );
		$all_fields = $response['data']['data'][0]['fields'] ?? array();
		$raw_fields = array();

		foreach ( $all_fields as $key => $field ) {
			// Only include custom fields (f-prefixed) that are editable.
			if ( ! preg_match( '/^f\d+$/', $key ) ) {
				continue;
			}
			if ( empty( $field['editable'] ) ) {
				continue;
			}
			// File fields are read-only via the API.
			if ( 'file' === ( $field['type'] ?? '' ) ) {
				continue;
			}
			$raw_fields[] = array(
				'key'     => $key,
				'alias'   => $field['alias'] ?? $key,
				'type'    => $field['type'] ?? 'text',
				'options' => $field['options'] ?? array(),
			);
		}

		return $raw_fields;
	}

	/**
	 * Send a request to the Ontraport API via the proxy server.
	 *
	 * Injects the legacy credential format (key/id as flat body params)
	 * and uses exclude_credentials to prevent the framework from also
	 * injecting a credentials JSON blob.
	 *
	 * @param string $action      The API action to perform.
	 * @param array  $body        The request body.
	 * @param mixed  $action_data The action data for logging.
	 *
	 * @return array The API response.
	 * @throws Exception If the request fails.
	 */
	public function send_request( $action, $body = array(), $action_data = null ) {

		$credentials = $this->helpers->get_credentials();

		$body['key']    = $credentials['key'] ?? '';
		$body['id']     = $credentials['id'] ?? '';
		$body['action'] = $action;

		return $this->api_request(
			$body,
			$action_data,
			array( 'exclude_credentials' => true )
		);
	}
}
