<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Threads;

use Exception;
use Uncanny_Automator\App_Integrations\Api_Caller;

/**
 * Class Threads_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Threads_App_Helpers $helpers
 */
class Threads_Api_Caller extends Api_Caller {

	/**
	 * Prepare request credentials.
	 *
	 * @param array $credentials
	 * @param array $args
	 * @return string
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return wp_json_encode( $credentials );
	}

	/**
	 * Check response for errors.
	 *
	 * @param  mixed $response
	 * @param  array $args
	 *
	 * @return void
	 */
	public function check_for_errors( $response, $args = array() ) {

		// Legacy handling of updated credentials.
		$this->maybe_handle_updated_credentials( $response );

		if ( 201 !== $response['statusCode'] && 200 !== $response['statusCode'] && 209 !== $response['statusCode'] ) {
			$message = sprintf(
				// translators: %d: HTTP status code
				esc_html_x( 'API Exception (status code: %d). An error has occurred while performing the action. Please try again later.', 'Threads', 'uncanny-automator' ),
				absint( $response['statusCode'] )
			);
			throw new \Exception( esc_html( $message ), absint( $response['statusCode'] ) );
		}
	}

	/**
	 * Maybe handle updated credentials.
	 *
	 * @param array $response
	 * @return void
	 */
	public function maybe_handle_updated_credentials( $response ) {

		$data        = $response['data'] ?? array();
		$credentials = $data['credentials'] ?? array();

		if ( ! empty( $credentials ) ) {
			$credentials['user_id']    = $this->get_credential_user_id();
			$credentials['expiration'] = time() + absint( $credentials['expires_in'] );

			$this->helpers->store_credentials( $credentials );
		}
	}

	/**
	 * Maybe handle updated credentials.
	 *
	 * @return string - The user ID from credentials.
	 * @throws \Exception If credentials are missing or invalid.
	 */
	public function get_credential_user_id() {
		$credentials = $this->helpers->get_credentials();

		$user_id = $credentials['user_id'] ?? '';

		if ( empty( $user_id ) ) {
			throw new \Exception( 'Missing or invalid credentials. Please reconnect your account through the settings page to continue.' );
		}

		return $user_id;
	}
}
