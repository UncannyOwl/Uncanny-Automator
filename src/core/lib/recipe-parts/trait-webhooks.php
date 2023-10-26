<?php

namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Automator_Send_Webhook;
use Uncanny_Automator\Webhooks\Response_Validator;

/**
 * Trait Webhook
 */
trait Webhooks {

	use Action_Tokens;

	use Log_Properties;

	/**
	 * The elapsed time in milliseconds for webhook to complete its request.
	 *
	 * @var int
	 */
	private $elapsed_time_ms = 0;

	/**
	 * Filter function to inject "Send test" response values as Action tokens.
	 * Each Webhook calls this parent method.
	 *
	 * @param $tokens
	 * @param $action_id
	 * @param $recipe_id
	 *
	 * @return array|mixed
	 */
	public function inject_webhooks_response_tokens( $tokens = array(), $action_id = null, $recipe_id = null ) {

		$response_exists = get_post_meta( $action_id, 'webhook_response_tokens', true );

		if ( empty( $response_exists ) ) {
			return array();
		}

		// Make sure data is array. The func json_decode can return boolean or null.
		$response_exists = (array) json_decode( $response_exists, true );

		$new_tokens = array();

		foreach ( $response_exists as $action_token ) {
			$tag          = strtoupper( $action_token['key'] );
			$new_tokens[] = array(
				'tokenId'     => $tag,
				'tokenParent' => get_post_meta( $action_id, 'code', true ),
				'tokenName'   => sprintf( '%s - %s', __( 'Response', 'uncanny-automator' ), $action_token['key'] ),
				'tokenType'   => $action_token['type'],
			);
		}

		$new_tokens[] = array(
			'tokenId'     => 'WEBHOOK_RESPONSE_BODY',
			'tokenParent' => get_post_meta( $action_id, 'code', true ),
			'tokenName'   => _x( 'Response - Body (raw)', 'Webhook', 'uncanny-automator' ),
			'tokenType'   => 'text',
		);

		return array_merge( $new_tokens, $tokens );

	}

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

		// Start the timer.
		$start_time = microtime( true );

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

		if ( empty( $webhook_url ) ) {

			/* translators: 1. Webhook URL */
			$error_message = esc_attr__( 'Webhook URL is empty.', 'uncanny-automator' );

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

			// Get response header
			$response = Automator_Send_Webhook::call_webhook( $webhook_url, $args, $request_type );

			// Send some properties to the log.
			$response_headers = wp_remote_retrieve_headers( $response );

			$encoded_response_headers = $response_headers instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary ? $response_headers->getAll() : array();

			$this->set_log_properties(
				array(
					'type'       => 'code',
					'label'      => _x( 'Response headers', 'Send webhook action response headers property', 'uncanny-automator' ),
					'value'      => wp_json_encode( $encoded_response_headers ),
					'attributes' => array(
						'code_language' => 'json',
					),
				),
				// Webhook response
				array(
					'type'       => 'code',
					'label'      => _x( 'Response body', 'Send webhook action log response body property', 'uncanny-automator' ),
					'value'      => wp_remote_retrieve_body( $response ),
					'attributes' => array(
						'code_language' => 'json',
					),
				)
			);

			$header_response = wp_remote_retrieve_headers( $response );

			$header_leafs = Automator_Send_Webhook::parse_headers( $header_response );

			// Get response body.
			$response_body = Automator_Send_Webhook::get_leafs( json_decode( wp_remote_retrieve_body( $response ), true ), true );

			// Combine header and body tokens.
			$all_tokens = array_merge( $header_leafs, $response_body );

			// If tokens are not previously saved OR set to true, override previously saved tokens.
			if (
				empty( get_post_meta( $action_data['ID'], 'webhook_response_tokens', true ) ) ||
				true === apply_filters( 'automator_outgoing_webhook_live_response_tokens', false, $response )
			) {
				$save_tokens = Automator_Send_Webhook::clean_tokens_before_save( $all_tokens );
				update_post_meta( $action_data['ID'], 'webhook_response_tokens', wp_json_encode( $save_tokens ) );
				unset( $save_tokens );
			}

			// Parse response into leafs.
			$hydration_data = Automator_Send_Webhook::before_hydrate_tokens( $all_tokens, $response );

			// Pass to hydrate tokens.
			$this->hydrate_tokens( $hydration_data );

			$validated = $this->validate_response( $response );

			if ( ! empty( $validated ) ) {

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

					// Marks the action as completed with notice.
					$action_data['complete_with_notice'] = true;
					// Complete the action.
					Automator()->complete->action( $user_id, $action_data, $recipe_id, $validated['error_message'] );
					// Log the webhook request.
					$this->log_webhook_request( $args, $webhook_url, $response, $action_data );
					// Invoke the action hook.
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

		// End the timer.
		$end_time = microtime( true );

		$this->elapsed_time_ms = ( $end_time - $start_time ) * 1000;

		$this->log_webhook_request( $args, $webhook_url, $response, $action_data );

	}

	/**
	 * Log the webhook request.
	 *
	 * @param mixed[] $args
	 * @param string $webhook_url
	 * @param WP_REST_Response $response
	 * @param mixed[] $action_data
	 *
	 * @return void
	 */
	private function log_webhook_request( $args, $webhook_url, $response, $action_data ) {

		// Log the request in the API for replay.
		$log_parameters = $this->make_log_parameters( $args, $webhook_url, wp_remote_retrieve_response_code( $response ) );

		$log_parameters['elapsed'] = $this->elapsed_time_ms;

		$this->log_request_as_api(
			$action_data['recipe_log_id'],
			$action_data['action_log_id'],
			$log_parameters
		);

	}

	/**
	 * Generates request log parameters.
	 *
	 * @param mixed[] $args
	 * @param string $url
	 * @param int $response_code
	 *
	 * @return array
	 */
	public function make_log_parameters( $args, $url, $response_code ) {

		return array(
			'endpoint' => 'internal:webhook',
			'params'   => $args,
			'request'  => array(
				'http_url' => $url,
			),
			'response' => array(
				'code' => $response_code,
			),
		);

	}

	/**
	 * Log the webhook request as api.
	 *
	 * @param int $recipe_log_id
	 * @param int $action_log_id
	 * @param mixed[] $logger_params
	 *
	 * @return void
	 */
	public function log_request_as_api( $recipe_log_id, $action_log_id, $logger_params ) {

		$log = array(
			'type'          => 'action',
			'recipe_log_id' => $recipe_log_id,
			'item_log_id'   => $action_log_id,
			'endpoint'      => $logger_params['endpoint'],
			'params'        => maybe_serialize( $logger_params['params'] ),
			'request'       => maybe_serialize( $logger_params['request'] ),
			'response'      => maybe_serialize( $logger_params['response'] ),
			'balance'       => null,
			'price'         => null,
			'status'        => $logger_params['response']['code'],
			'time_spent'    => $logger_params['elapsed'],
		);

		Automator()->db->api->add( $log );

	}

	/**
	 * Validates the response object (WP_Rest_Response). Throws an Exception if the response has errors.
	 *
	 * @param WP_Rest_Response $response
	 *
	 * @return array{error_message:string,response_code:int}|null Returns array for ok response. Returns null for error but throws Exceptions.
	 *
	 * @throws \Exception
	 */
	protected function validate_response( $response ) {

		return Response_Validator::validate_webhook_response( $response );

	}

}
