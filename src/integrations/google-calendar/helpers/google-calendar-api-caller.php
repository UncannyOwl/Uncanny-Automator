<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Google_Calendar_Api_Caller
 *
 * @property Google_Calendar_Helpers $helpers
 */
class Google_Calendar_Api_Caller extends Api_Caller {

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Override the default 'credentials' request key until migration to vault.
		$this->set_credential_request_key( 'access_token' );
	}

	/**
	 * Prepare request credentials - Override to send full credentials as JSON string
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments.
	 *
	 * @return string The prepared credentials as JSON string.
	 */
	public function prepare_request_credentials( $credentials, $args = array() ) {
		// Google Calendar API expects the full credentials object as JSON-encoded string
		return wp_json_encode( $credentials );
	}

	/**
	 * Check for errors.
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs
	 */
	public function check_for_errors( $response, $args = array() ) {
		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw new Exception( esc_html( wp_json_encode( $response ) ), absint( $response['statusCode'] ) );
		}
	}

	/**
	 * Request the resource owner.
	 *
	 * @return void|mixed[]
	 */
	public function request_resource_owner() {

		$credentials = $this->helpers->get_credentials();

		if ( empty( $credentials['scope'] ) ) {
			throw new Exception( 'Invalid credentials', 400 );
		}

		return $this->api_request( 'user_info' );
	}
}
