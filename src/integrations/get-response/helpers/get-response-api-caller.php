<?php
namespace Uncanny_Automator\Integrations\Get_Response;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Get_Response_Api_Caller
 *
 * @package Uncanny_Automator
 */
class Get_Response_Api_Caller extends Api_Caller {

	/**
	 * Set class properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Map credential property to api-key until migration to vault.
		$this->set_credential_request_key( 'api-key' );
	}

	/**
	 * Check for errors in the response.
	 *
	 * @param mixed $response
	 * @param array $args
	 *
	 * @return void
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {

		// Check for error.
		if ( ! empty( $response['data']['error'] ) ) {
			throw new Exception( esc_html( $response['data']['error'] ), 400 );
		}

		if ( 400 <= $response['statusCode'] ) {

			// Custom messages for specific error types.
			switch ( $response['statusCode'] ) {
				case 401:
					throw new Exception( esc_html( $this->get_authentication_error_message( $args ) ) );

				case 429:
					throw new Exception(
						esc_html_x( 'The throttling limit has been reached, please try again later.', 'GetResponse', 'uncanny-automator' ),
						400
					);
			}

			// For all other errors, use the original context-based formatting.
			$message = isset( $response['data']['message'] ) ? $response['data']['message'] : false;
			if ( ! empty( $message ) ) {

				// Check for context message and append.
				if ( isset( $response['data']['context'] ) ) {
					if ( ! empty( $response['data']['context'] ) && is_array( $response['data']['context'] ) ) {
						foreach ( $response['data']['context'] as $item ) {
							$item = is_string( $item ) ? json_decode( $item, true ) : $item;
							if ( is_array( $item ) ) {
								if ( isset( $item['message'] ) ) {
									$message .= ' ' . $item['message'];
								} elseif ( isset( $item['errorDescription'] ) ) {
									$message .= ' ' . $item['errorDescription'];
								}
							}
						}
					}

					throw new Exception( esc_html( $message ), 400 );
				}
			}

			// Fallback for other errors.
			throw new Exception(
				esc_html_x( 'GetResponse API error', 'GetResponse', 'uncanny-automator' ),
				400
			);
		}
	}

	/**
	 * Get authentication error message with help link.
	 *
	 * @param array $args The arguments passed to api_request.
	 * @return string
	 */
	private function get_authentication_error_message( $args = array() ) {

		$context = $args['context'] ?? 'logs';

		// Check if this isn't for the logs.
		if ( 'logs' !== $context ) {
			return esc_html_x( 'Your GetResponse API key is invalid. Please re-authorize your account.', 'GetResponse', 'uncanny-automator' );
		}

		// Logs context - show markdown link for logs.
		return sprintf(
			// translators: %s: Settings page URL
			esc_html_x( 'Your GetResponse API key is invalid. [reconnect your account](%s)', 'GetResponse', 'uncanny-automator' ),
			esc_url( $this->helpers->get_settings_page_url() )
		);
	}

	/**
	 * Get account - validates the connection.
	 *
	 * @return array
	 */
	public function get_account() {

		// Set defaults.
		$account = array(
			'id'     => '',
			'email'  => '',
			'status' => '',
			'error'  => '',
		);

		// Validate api key.
		$api_key = $this->helpers->get_credentials();
		if ( empty( $api_key ) ) {
			return $account;
		}

		// Make request to get account.
		try {
			// Exclude error check.
			$args     = array(
				'exclude_error_check' => true,
			);
			$response = $this->api_request( 'get_account', null, $args );

		} catch ( Exception $e ) {
			$error            = $e->getMessage();
			$account['error'] = ! empty( $error ) ? $error : esc_html_x( 'GetResponse API error', 'GetResponse', 'uncanny-automator' );
			$this->helpers->store_account_info( $account );

			return $account;
		}

		// Success.
		if ( ! empty( $response['data']['accountId'] ) ) {
			$account['id']     = $response['data']['accountId'];
			$account['email']  = $response['data']['email'];
			$account['status'] = 'success';
		} else {
			$account['status'] = '';
			$account['error']  = esc_html_x( 'GetResponse API error', 'GetResponse', 'uncanny-automator' );
		}

		// Check for invalid key.
		if ( ! empty( $response['data']['httpStatus'] ) ) {
			// [code] => 1014 && [httpStatus] => 401
			$account['status'] = '';
			$account['error']  = ! empty( $response['data']['message'] )
				? $response['data']['message']
				: esc_html_x( 'Invalid API key', 'GetResponse', 'uncanny-automator' );
		}

		$this->helpers->store_account_info( $account );

		return $account;
	}
}
