<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Aweber;

use Exception;
use Uncanny_Automator\App_Integrations\Api_Caller;
use Uncanny_Automator\App_Integrations\Token_Refresh_Lock;

/**
 * Class Aweber_Api_Caller
 *
 * @package Uncanny_Automator
 * @property Aweber_App_Helpers $helpers
 */
class Aweber_Api_Caller extends Api_Caller {

	use Token_Refresh_Lock;

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set custom properties
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set the credential request key for access token.
		$this->set_credential_request_key( 'access_token' );

		// AWeber uses a 10 minute buffer before token expiry.
		$this->set_token_refresh_buffer_seconds( 600 );
	}

	/**
	 * Prepare credentials for use in API requests
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string Valid access token.
	 * @throws Exception If access token is missing or invalid.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		$token = $credentials['access_token'] ?? null;

		if ( empty( $token ) ) {
			throw new Exception( 'invalid credentials', 400 );
		}

		// Calculate expiry timestamp from date_added + expires_in.
		$date_added = absint( $credentials['date_added'] ?? 0 );
		$expires_in = absint( $credentials['expires_in'] ?? 0 );
		$expires_at = $date_added + $expires_in;

		// Check if token is expired or about to expire.
		if ( $this->is_token_expiring( $expires_at ) ) {
			$credentials = $this->handle_token_refresh_with_lock( $credentials, array( $this, 'refresh_and_store_token' ) );
			$token       = $credentials['access_token'] ?? null;

			if ( empty( $token ) ) {
				throw new Exception( 'invalid credentials', 400 );
			}
		}

		return $token;
	}

	/**
	 * Check response for errors
	 *
	 * @param array $response The response.
	 * @param array $args     The arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs.
	 */
	public function check_for_errors( $response, $args = array() ) {

		$status_code = isset( $response['statusCode'] ) ? absint( $response['statusCode'] ) : 0;

		// Check for internal invalid credentials error.
		if ( isset( $response['error'] ) && false !== strpos( strtolower( $response['error'] ), 'invalid credentials' ) ) {
			$this->handle_400_error( $response, $args );
			return;
		}

		// Success status codes.
		if ( in_array( $status_code, array( 200, 201, 209 ), true ) ) {
			return;
		}

		// Handle specific status codes.
		if ( 401 === $status_code ) {
			throw new Exception(
				esc_html_x( 'Authentication failed. Please check your AWeber connection.', 'AWeber', 'uncanny-automator' ),
				401
			);
		}

		// Handle 400 errors with parent method for formatted messages.
		if ( 400 === $status_code ) {
			$this->handle_400_error( $response, $args );
			return;
		}

		// Handle other error status codes.
		if ( $status_code >= 400 ) {
			// Check if API proxy already formatted the error message.
			if ( ! empty( $response['error'] ) ) {
				throw new Exception(
					esc_html( $response['error'] ),
					absint( $status_code )
				);
			}

			// Check for message in data.
			if ( ! empty( $response['data']['message'] ) ) {
				throw new Exception(
					esc_html( $response['data']['message'] ),
					absint( $status_code )
				);
			}

			// Generic error message.
			throw new Exception(
				sprintf(
					/* translators: %d: HTTP status code */
					esc_html_x( 'API error (status code: %d). Please try again later.', 'AWeber', 'uncanny-automator' ),
					absint( $status_code )
				),
				absint( $status_code )
			);
		}
	}

	////////////////////////////////////////////////////////////
	// OAuth methods
	////////////////////////////////////////////////////////////

	/**
	 * Refresh access token and store updated credentials.
	 *
	 * Used as callback for handle_token_refresh_with_lock().
	 *
	 * @param array $credentials Current credentials with refresh_token.
	 *
	 * @return array Updated credentials.
	 * @throws Exception If refresh fails.
	 */
	protected function refresh_and_store_token( $credentials ) {

		try {
			// Exclude credentials and error check for refresh request.
			$args = array(
				'include_timeout'     => 15,
				'exclude_error_check' => true,
				'exclude_credentials' => true,
			);

			$body = array(
				'action'        => 'refresh_access_token',
				'refresh_token' => $credentials['refresh_token'] ?? '',
			);

			$response = $this->api_request( $body, null, $args );

			if ( ! empty( $response['data'] ) && in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
				// Assign the data to credentials and flag the new time.
				$new_credentials               = $response['data'];
				$new_credentials['date_added'] = time();

				// Store the refreshed credentials.
				$this->helpers->store_credentials( $new_credentials );

				return $this->helpers->get_credentials();
			}

			throw new Exception( 'Token refresh failed', 400 );

		} catch ( Exception $e ) {
			// Disconnect the integration to prevent further invalid requests.
			$this->helpers->delete_credentials();

			// Throw invalid credentials error to inform user they need to reconnect.
			throw new Exception( 'invalid credentials', 400 );
		}
	}

	////////////////////////////////////////////////////////////
	// API methods
	////////////////////////////////////////////////////////////

	/**
	 * Get AWeber accounts
	 *
	 * @return array
	 * @throws Exception If request fails.
	 */
	public function get_accounts() {
		return $this->api_request( 'get_accounts' );
	}

	/**
	 * Get AWeber lists
	 *
	 * @param string $account_id The account ID.
	 *
	 * @return array
	 * @throws Exception If request fails.
	 */
	public function get_lists( $account_id ) {

		if ( empty( $account_id ) ) {
			throw new Exception( esc_html_x( 'Account ID is required.', 'AWeber', 'uncanny-automator' ), 400 );
		}

		return $this->api_request(
			array(
				'action'     => 'get_lists',
				'account_id' => $account_id,
			)
		);
	}

	/**
	 * Get custom fields
	 *
	 * @param string $account_id The account ID.
	 * @param string $list_id    The list ID.
	 *
	 * @return array
	 * @throws Exception If request fails.
	 */
	public function get_custom_fields( $account_id, $list_id ) {

		if ( empty( $account_id ) || empty( $list_id ) ) {
			throw new Exception( esc_html_x( 'Account ID and list ID are required.', 'AWeber', 'uncanny-automator' ), 400 );
		}

		return $this->api_request(
			array(
				'action'     => 'get_custom_fields',
				'account_id' => $account_id,
				'list_id'    => $list_id,
			)
		);
	}
}
