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

		$response      = Automator_Send_Webhook::call_webhook( $webhook_url, $args, $request_type );
		$response_code = wp_remote_retrieve_response_code( $response );

		// Server return invalid response.
		if ( 200 !== $response_code ) {

			/* translators: Error message */
			$error_message = sprintf( esc_html__( 'An error has been encountered with response code: %s', 'uncanny-automator' ), $response_code );
			$response      = json_decode( wp_remote_retrieve_body( $response ) );
			if ( isset( $response->message ) && ! empty( $response->message ) ) {
				$error_message = $response->message;
			}
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;

		}

		// The client return an invalid response. Failed to send data to webhook url server.
		if ( is_wp_error( $response ) ) {
			/* translators: 1. Webhook URL */
			$error_message = sprintf( esc_attr__( 'An error was found in the webhook (%1$s) response.', 'uncanny-automator' ), $response->get_error_message() );
			if ( ! empty( $response->get_error_message() ) ) {
				$error_message = $response->get_error_message();
			}
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		// All good. Completing action.
		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}
}
