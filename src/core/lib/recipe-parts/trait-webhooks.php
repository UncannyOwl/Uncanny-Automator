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
		$legacy = false;
		if ( isset( $action_data['meta']['WEBHOOKURL'] ) ) {
			$legacy = true;
		}
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
			$error_message                       = esc_attr__( 'Webhook URL is empty.', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		if ( empty( $fields ) ) {
			$error_message                       = esc_attr__( 'Webhook payload is empty.', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
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

				// All good. Completing action.
				Automator()->complete->action( $user_id, $action_data, $recipe_id );
				/**
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
				$body           = wp_remote_retrieve_body( $response );
				do_action( 'automator_webhook_action_completed', $body, $response, $user_id, $do_action_args, $this );
			}
		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			// Something bad happened. Complete with error.
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
	}

	/**
	 * Validates the response
	 *
	 * @param $response
	 *
	 * @return boolean|\Exception True if no exception has occured.
	 * @throws \Exception
	 */
	protected function validate_response( $response ) {

		$response_code = wp_remote_retrieve_response_code( $response );

		// The client did not receive a valid response while sending data to webhook url server.
		// e.g. timeout, server not found, etc.
		if ( is_wp_error( $response ) ) {

			/* translators: 1. Webhook URL */
			$error_message = sprintf( esc_attr__( 'An error was found in the webhook (%1$s) response.', 'uncanny-automator' ), $response->get_error_message() );

			if ( ! empty( $response->get_error_message() ) ) {

				$error_message = $response->get_error_message();

			}

			throw new \Exception( $error_message, absint( $response->get_error_code() ) ); // Converts blank error code to 0.
		}

		// Server returned non 200 OK status.
		if ( 200 !== $response_code ) {

			/* translators: Error message */
			$error_message = sprintf( esc_html__( 'An error has been encountered with response code: %s', 'uncanny-automator' ), $response_code );

			$response = json_decode( wp_remote_retrieve_body( $response ) );

			// Overwrite error message if response has message.
			if ( ! empty( $response->message ) ) {

				$error_message = $response->message;

			}

			throw new \Exception( $error_message, $response_code );

		}

		// Server has returned 200 OK status but with an error message in the body.
		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! empty( $response->data->errors ) || ! empty( $response->data->error ) ) {

			throw new \Exception(
				'A response containing an error object in the data was received. The server response in JSON format: ' . wp_json_encode( $response ),
				400 // Send 400 status code.
			);

		}

		return true;
	}
}
