<?php
namespace Uncanny_Automator\Webhooks;

use Exception;
use Uncanny_Automator\Utilities\Automator_Http_Response_Code;
use WP_REST_Response;
use WP_Error;

/**
 * Validates the webhook response and returns|throws an error message.
 *
 * @since 5.2
 *
 * @package Uncanny_Automator\Webhooks
 */
class Response_Validator {

	/**
	 * Main class entry point. Validates the webhook response.
	 *
	 * @param array|WP_Error $response
	 *
	 * @return array{error_message:string,response_code:int}|null Returns array for ok response. Returns null for error but throws Exceptions.
	 *
	 * @throws Exception
	 */
	public static function validate_webhook_response( $response ) {

		if ( is_array( $response ) ) {
			return self::handle_validation( $response );
		}

		if ( $response instanceof WP_Error ) {
			return self::handle_validation( $response );
		}

		throw new Exception( 'Invalid parameter#1 passed to Response_Validator::validate_webhook_response. Expecting array|WP_Error. Received ' . gettype( $response ) );

	}

	/**
	 * WordPress marks it as error (e.g. timeout, server not found, etc.).
	 *
	 * @param WP_Error $wp_error
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected static function handle_wp_errors( $wp_error ) {

		$error_message = sprintf(
			/* translators: 1. Webhook URL */
			esc_attr_x( 'An error was found in the webhook response: (%1$s).', 'Webhook', 'uncanny-automator' ),
			$wp_error->get_error_message()
		);

		// Converts blank error code to 0.
		throw new Exception( $error_message, absint( $wp_error->get_error_code() ) );

	}

	/**
	 * Validates the response from a given instance of WP_REST_Response.
	 *
	 * @param array|WP_Error $response The response from the server.
	 *
	 * @return void|array{error_message:string,response_code:int} Throws an exception if status code is 400 - 599. Otherwise, an array.
	 *
	 * @throws Exception
	 */
	protected static function handle_validation( $response ) {

		$response_code = absint( wp_remote_retrieve_response_code( $response ) );

		// Handles wp errors.
		if ( is_wp_error( $response ) ) {
			return self::handle_wp_errors( $response );
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Handle 200-299 status code as successful responses.
		if ( in_array( $response_code, range( 200, 299 ), true ) ) {
			return self::handle_response_successful( $response, $response_code );
		}

		// Handle 300-399 status code message.
		if ( in_array( $response_code, range( 300, 399 ), true ) ) {
			return self::handle_response_redirected(
				$response,
				$response_code,
				_x( 'Request redirected to another URL.', 'Webhook', 'uncanny-automator' )
			);
		}

		// Handle 400-499 status code error response.
		if ( in_array( $response_code, range( 400, 499 ), true ) ) {
			$error_message = sprintf(
				/* translators: Response code */
				_x( 'Client error, request responded with: %2$d &mdash; %1$s error.', 'Webhook', 'uncanny-automator' ),
				Automator_Http_Response_Code::text( $response_code ),
				$response_code
			);
			throw new Exception( $error_message, $response_code );
		}

		// Handle 500-599 status code error response.
		if ( in_array( $response_code, range( 500, 599 ), true ) ) {
			$error_message = sprintf(
				/* translators: Response code */
				_x(
					'Server error, request responded with: %2$d &mdash; %1$s error.',
					'Webhook',
					'uncanny-automator'
				),
				Automator_Http_Response_Code::text( $response_code ),
				$response_code
			);
			throw new Exception( $error_message, $response_code );
		}

		throw new Exception(
			'Server has responded with invalid status code: ' . $response_code,
			$response_code
		);

	}

	/**
	 * Handles 20x successful responses from server.
	 *
	 * @param array|WP_Error $response The response body object.
	 * @param int $response_code
	 *
	 * @return boolean True if no Exception has occured.
	 */
	protected static function handle_response_successful( $response = null, $response_code = 0 ) {

		if ( self::response_has_errors( $response ) ) {

			throw new Exception(
				sprintf( 'The server has responded with a status code of %d, but has an "error" property.', $response_code ),
				400 // Send 400 status code.
			);

		}

		// Otherwise, return true.
		return array(
			'error_message' => null,
			'response_code' => $response_code,
		);

	}

	/**
	 * Handles 30x redirected response.
	 *
	 * @param array|WP_Error $response The response body object.
	 * @param int $response_code
	 * @param string $error_message
	 *
	 * @return array{error_message:string,response_code:int} $response The response body object.
	 */
	protected static function handle_response_redirected( $response, $response_code, $error_message ) {

		// Overwrite error message if response has message.
		if ( self::response_has_errors( $response ) ) {
			$error_message = sprintf(
				'Request redirected to another URL: (%d) %s',
				$response_code,
				Automator_Http_Response_Code::text( $response_code )
			);
		}

		return array(
			'error_message' => $error_message,
			'response_code' => $response_code,
		);

	}

	/**
	 * Some REST endpoint returns 200 status code but with error message.
	 *
	 * Its REST endpoint problem but we can possibly handle much for those scenarios.
	 *
	 * @param array|WP_Error
	 *
	 * @return boolean True if there are any error(s). Otherwise, false.
	 */
	private static function response_has_errors( $response = null ) {

		return ! empty( $response->data->errors ) ||
			! empty( $response->data->error ) ||
			! empty( $response->error ) ||
			! empty( $response->errors );

	}

}
