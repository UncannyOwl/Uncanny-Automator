<?php

namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Automator_Send_Webhook;

/**
 * Trait Webhook
 */
trait Webhooks {

	/**
	 * Common function to run action on all outgoing webhooks
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$legacy = isset( $action_data['meta']['WEBHOOKURL'] ) ? true : false;

		$parsing_args = array(
			'recipe_id' => $recipe_id,
			'user_id'   => $user_id,
			'args'      => $args,
		);

		$data         = $action_data['meta'];
		$data_type    = Automator()->send_webhook->get_data_type( $data );
		$headers      = Automator()->send_webhook->get_headers( $data, $parsing_args );
		$webhook_url  = Automator()->send_webhook->get_url( $data, $legacy, $parsing_args );
		$fields       = Automator()->send_webhook->get_fields( $data, $legacy, $data_type, $parsing_args );
		$request_type = Automator()->send_webhook->request_type( $data );
		$headers      = Automator()->send_webhook->get_content_type( $data_type, $headers );

		// Fix required boundary for multipart/* content-type.
		if ( false !== strpos( $headers['Content-Type'], 'multipart/' ) ) {
			$headers['Content-Type'] .= ';boundary=' . sha1( time() );
		}

		if ( empty( $webhook_url ) ) {

			/* translators: 1. Webhook URL */
			$error_message = esc_attr__( 'Webhook URL is empty.', 'uncanny-automator' );

			$action_data['complete_with_errors'] = true;

			return Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

		}

		if ( empty( $fields ) ) {

			$error_message = esc_attr__( 'Webhook payload is empty.', 'uncanny-automator' );

			$action_data['complete_with_errors'] = true;

			return Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

		}

		$args = apply_filters(
			'automator_send_webhook_remote_args',
			array(
				'method'  => $request_type,
				'body'    => $fields,
				'timeout' => '30',
			),
			$data,
			$this
		);

		if ( ! empty( $headers ) ) {
			$args['headers'] = apply_filters( 'automator_send_webhook_remote_headers', $headers, $data, $this );
		}

		try {

			$response = Automator_Send_Webhook::call_webhook( $webhook_url, $args, $request_type );

			$validated = $this->validate_response( $response );

			if ( $validated ) {

				/**
				 * Send some do_action args to `automator_webhook_action_completed` action hook.
				 *
				 * @since 4.5
				 * @author Saad S.
				 */
				$do_action_args = array(
					'action_data'     => $action_data,
					'recipe_id'       => $recipe_id,
					'webhook_url'     => $webhook_url,
					'sent_to_webhook' => $args,
					'request_type'    => $request_type,
				);

				$body = wp_remote_retrieve_body( $response );

				if ( is_array( $validated ) && ! empty( $validated['error_message'] ) ) {

					$action_data['complete_with_notice'] = true;

					Automator()->complete->action( $user_id, $action_data, $recipe_id, $validated['error_message'] );

					do_action( 'automator_webhook_action_completed', $body, $response, $user_id, $do_action_args, $this );

					return;

				}

				Automator()->complete->action( $user_id, $action_data, $recipe_id );

				do_action( 'automator_webhook_action_completed', $body, $response, $user_id, $do_action_args, $this );

			}
		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			// Something bad happened. Complete with error.
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Validates the response and throws an \Exception if response has errors.
	 *
	 * @param $response
	 *
	 * @return boolean True if no exception has occured.
	 * @throws \Exception
	 */
	protected function validate_response( $response ) {

		// The client did not receive a valid response while sending data to webhook URL server (e.g. timeout, server not found, etc).
		if ( is_wp_error( $response ) ) {

			$error_message = sprintf(
			/* translators: 1. Webhook URL */
				esc_attr__( 'An error was found in the webhook (%1$s) response.', 'uncanny-automator' ),
				$response->get_error_message()
			);

			if ( ! empty( $response->get_error_message() ) ) {
				$error_message = $response->get_error_message();
			}

			throw new \Exception( $error_message, absint( $response->get_error_code() ) ); // Converts blank error code to 0.

		}

		return $this->validate_from_status_code( $response );

	}

	/**
	 * Validates the response from status code.
	 *
	 * @param mixed $response The response from the server.
	 *
	 * @return mixed True if response status code in range(200, 299).
	 *
	 * @throws \Exception
	 */
	protected function validate_from_status_code( $response = null ) {

		$response_code = wp_remote_retrieve_response_code( $response );

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		// Handle successful responses.
		if ( in_array( $response_code, range( 200, 299 ), true ) ) {
			return $this->handle_response_successful( $response, $response_code, '' );
		}

		// Handle redirection messages.
		if ( in_array( $response_code, range( 300, 399 ), true ) ) {
			return $this->handle_response_redirected( $response, $response_code, __( 'Request redirected to another URL.', 'uncanny-automator' ) );
		}

		// Handle client error response.
		if ( in_array( $response_code, range( 400, 499 ), true ) ) {
			/* translators: Response code */
			return $this->handle_response_client_error( $response, $response_code, sprintf( __( 'Client error, request responded with %d error.', 'uncanny-automator' ), $response_code ) );
		}

		// Handle server error responses .
		if ( in_array( $response_code, range( 500, 599 ), true ) ) {
			/* translators: Response code */
			return $this->handle_response_server_error( $response, $response_code, sprintf( __( 'Server error, request responded with %d error.', 'uncanny-automator' ), $response_code ) );
		}

		throw new \Exception(
			'Server has responded with invalid status code: ' . $response_code,
			$response_code
		);

	}

	/**
	 * Handles 20x successful responses from server.
	 *
	 * @param mixed $response The response body object.
	 *
	 * @return boolean True if no \Exception has occured.
	 */
	protected function handle_response_successful( $response = null, $response_code = 0, $error_message = '' ) {

		if ( $this->response_has_errors( $response ) ) {

			throw new \Exception(
				'A response containing an error object in the data was received.
				The server response in JSON format: ' . wp_json_encode( $response ),
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
	 * @param mixed $response The response body object.
	 */
	protected function handle_response_redirected( $response = null, $response_code = 0, $error_message = '' ) {

		// Overwrite error message if response has message.
		if ( $this->response_has_errors( $response ) ) {
			$error_message = sprintf( 'Request %d redirected to another URL - %s', $response_code, $this->format_response_body( $response ) );
		}

		return array(
			'error_message' => $error_message,
			'response_code' => $response_code,
		);

	}

	/**
	 * Handles 40x client response.
	 *
	 * @param mixed $response The response body object.
	 *
	 * @return string
	 *
	 * @throws \Exception
	 */
	protected function handle_response_client_error( $response = null, $response_code = 0, $error_message = '' ) {

		// Overwrite error message if response has message.
		if ( $this->response_has_errors( $response ) ) {

			$error_message = sprintf( 'Client error with %d error - %s', $response_code, $this->format_response_body( $response ) );

			throw new \Exception( $error_message, $response_code );

		}

		throw new \Exception( $error_message, $response_code );

	}

	/**
	 * Handles 50x server response.
	 *
	 * @param mixed $response The response body object.
	 *
	 * @return string
	 */
	public function handle_response_server_error( $response = null, $response_code = 0, $error_message = '' ) {

		// Overwrite error message if response has message.
		if ( $this->response_has_errors( $response ) ) {

			$error_message = sprintf( 'Server error: %d error - %s', $response_code, $this->format_response_body( $response ) );

			throw new \Exception( $error_message, $response_code );

		}

		throw new \Exception( $error_message, $response_code );

	}

	/**
	 * Formats the response body from error message.
	 *
	 * @param mixed $response The response body object.
	 *
	 * @return string The error message.
	 */
	private function format_response_body( $response = null ) {

		$encoded_json_response = wp_json_encode( $response, 0, 5 );

		// Bail early if server has responded with non-json format.
		// If server was redirected without any content in the body,
		// wp_json_encode response is `string` null.
		if ( 'null' === $encoded_json_response || empty( $encoded_json_response ) ) {
			return null;
		}

		// Apply generic error message in JSON format.
		$error_message = substr( $encoded_json_response, 0, 2000 ); // Max 2000 characters.

		// Try to detect actual errors.
		if ( ! empty( $response->error ) ) {
			$error_message = $response->error;
		}

		if ( ! empty( $response->data->error ) ) {
			$error_message = $response->data->error;
		}

		if ( ! empty( $response->data->message->error ) ) {
			$error_message = $response->data->message->error;
		}

		// Handle in case the server has responded with an object or an array.
		if ( is_array( $error_message ) || is_object( $error_message ) ) {
			$error_message = wp_json_encode( $error_message );
		}

		if ( empty( $error_message ) ) {
			$error_message = 'Empty response body.';
		}

		// Only return up to 5th level with 2000 max characters.
		return apply_filters( 'automator_trait_webhooks_format_error_message', $error_message, $this );

	}


	/**
	 * Checks whether the given response contains error.
	 *
	 * @return boolean True if there are any error(s). Otherwise, false.
	 */
	private function response_has_errors( $response = null ) {

		return ! empty( $response->data->errors ) ||
			   ! empty( $response->data->error ) ||
			   ! empty( $response->error ) ||
			   ! empty( $response->errors );

	}

}
