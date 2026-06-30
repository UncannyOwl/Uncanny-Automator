<?php
namespace Uncanny_Automator\Integrations\Google_Contacts;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Google_Contacts_Api_Caller
 *
 * @property Google_Contacts_Helpers $helpers
 */
class Google_Contacts_Api_Caller extends Api_Caller {

	/**
	 * Set properties.
	 */
	public function set_properties() {
		// Override the default 'credentials' request key until migration to vault.
		$this->set_credential_request_key( 'access_token' );
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

		if ( in_array( $response['statusCode'] ?? 0, array( 200, 201 ), true ) ) {
			return;
		}

		throw new Exception( esc_html( $this->get_error_message( $response ) ), absint( $response['statusCode'] ?? 0 ) );
	}

	/**
	 * Extract a human-readable message from an error response.
	 *
	 * The platform exposes the real reason in error.description (with error.message
	 * as a generic summary); on the exception path api_request passes it as a plain
	 * $response['error'] string. Surface that rather than json-encoding the whole
	 * response payload (which logged {"error":"...","statusCode":404}).
	 *
	 * @param array $response The response.
	 *
	 * @return string
	 */
	private function get_error_message( $response ) {

		$error = $response['error'] ?? '';

		if ( is_array( $error ) ) {
			$message = $error['description'] ?? $error['message'] ?? '';
			if ( '' !== $message ) {
				return $message;
			}
		} elseif ( is_string( $error ) && '' !== $error ) {
			return $error;
		}

		if ( ! empty( $response['data']['error'] ) && is_string( $response['data']['error'] ) ) {
			return $response['data']['error'];
		}

		return esc_html_x( 'Google Contacts API error', 'Google Contacts', 'uncanny-automator' );
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

		$body = array(
			'action' => 'user_info',
			'client' => wp_json_encode( $credentials ),
		);

		return $this->api_request( $body, null );
	}

	/**
	 * Fetches labels/contact groups from Google Contacts API
	 *
	 * @return array The contact groups/labels
	 * @throws Exception On API errors
	 */
	public function fetch_contact_groups() {

		$response = $this->api_request( 'list_labels' );
		$options  = array();

		if ( isset( $response['data']['contactGroups'] ) && is_array( $response['data']['contactGroups'] ) ) {
			foreach ( $response['data']['contactGroups'] as $label ) {
				if ( 'USER_CONTACT_GROUP' === $label['groupType'] ) {
					$options[] = array(
						'text'  => $label['name'],
						'value' => $label['resourceName'],
					);
				}
			}
		}

		return $options;
	}
}
