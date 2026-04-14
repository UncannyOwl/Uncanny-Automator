<?php

namespace Uncanny_Automator\Integrations\OpenAI;

use Exception;
use Uncanny_Automator\Api_Server;

/**
 * OpenAI API Caller
 *
 * Consolidates OpenAI HTTP communication into a single class.
 *
 * Two request paths:
 *
 * 1. api_request()   — Routes through the Automator API proxy (v2/open-ai).
 *                      Used for admin/AJAX calls like fetching models and validating keys.
 *
 * 2. openai_request() — Calls the OpenAI API directly (https://api.openai.com/).
 *                       Used for all action execution (chat completions, image generation, etc.).
 *                       Reduces Automator credits before each request (unless disabled).
 *
 * @package Uncanny_Automator
 *
 * @property OpenAI_App_Helpers $helpers
 */
class OpenAI_Api_Caller extends \Uncanny_Automator\App_Integrations\Api_Caller {

	/**
	 * OpenAI base URL.
	 *
	 * @var string
	 */
	const OPENAI_API_URL = 'https://api.openai.com/';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set class properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->set_credential_request_key( 'access_token' );
	}

	/**
	 * Check for errors in a proxy API response.
	 *
	 * @param array $response The API response.
	 * @param array $args     The arguments.
	 *
	 * @throws Exception If the response contains an error.
	 * @return void
	 */
	public function check_for_errors( $response, $args = array() ) {

		parent::check_for_errors( $response, $args );

		if ( isset( $response['statusCode'] ) && 200 !== $response['statusCode'] ) {
			throw new Exception(
				sprintf(
					// translators: %s is the HTTP status code.
					esc_html_x( 'Request to OpenAI returned with status: %s', 'OpenAI', 'uncanny-automator' ),
					absint( $response['statusCode'] )
				),
				absint( $response['statusCode'] )
			);
		}
	}

	////////////////////////////////////////////////////////////
	// Direct OpenAI requests
	////////////////////////////////////////////////////////////

	/**
	 * Send a request directly to the OpenAI API.
	 *
	 * This bypasses the Automator proxy and hits api.openai.com directly.
	 * An Automator credit is consumed before each request (unless skipped).
	 *
	 * @param string $endpoint      The OpenAI endpoint, e.g. 'v1/chat/completions'.
	 * @param array  $body          The request body (will be JSON-encoded for POST).
	 * @param array  $args {
	 *     Optional. Request arguments.
	 *
	 *     @type string $method         HTTP method. Default 'POST'.
	 *     @type bool   $reduce_credits Whether to reduce Automator credits. Default true.
	 *     @type int    $timeout        Request timeout in seconds. Default 120.
	 * }
	 *
	 * @throws Exception On HTTP error or non-200 response.
	 * @return array The decoded JSON response body.
	 */
	public function openai_request( $endpoint, $body = array(), $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'method'         => 'POST',
				'reduce_credits' => true,
				'timeout'        => 120,
			)
		);

		if ( true === AUTOMATOR_DISABLE_APP_INTEGRATION_REQUESTS ) {
			throw new Exception( esc_html_x( 'App integrations have been disabled in wp-config.php.', 'OpenAI', 'uncanny-automator' ), 500 );
		}

		$api_key = (string) $this->helpers->get_credentials();

		if ( empty( $api_key ) ) {
			throw new Exception( esc_html_x( 'OpenAI API key is not configured.', 'OpenAI', 'uncanny-automator' ), 400 );
		}

		// Reduce Automator credits after validating the key but before making the request.
		if ( $args['reduce_credits'] ) {
			Api_Server::get_instance()->charge_usage();
		}

		$request_args = array(
			'method'  => $args['method'],
			'timeout' => apply_filters( 'automator_openai_request_timeout', absint( $args['timeout'] ) ),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
		);

		// JSON-encode the body for POST requests.
		if ( 'POST' === $args['method'] && ! empty( $body ) ) {
			$request_args['body'] = wp_json_encode( $body );
		}

		$url      = self::OPENAI_API_URL . ltrim( $endpoint, '/' );
		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ), 500 );
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code ) {
			$error_type    = $response_body['error']['type'] ?? 'unknown_error';
			$error_message = $response_body['error']['message'] ?? 'OpenAI returned an error with no message.';

			throw new Exception(
				// translators: %1$s is the error type, %2$s is the error message.
				esc_html( sprintf( 'OpenAI error: [%1$s] %2$s', $error_type, $error_message ) ),
				absint( $status_code )
			);
		}

		return $response_body;
	}

	////////////////////////////////////////////////////////////
	//  Chat completion processing
	////////////////////////////////////////////////////////////

	/**
	 * Send a chat completion request and return the response text.
	 *
	 * Used by V2 GPT-4 actions (sentiment, translate, excerpt, etc.).
	 *
	 * @param string $prompt      The user prompt.
	 * @param string $model       The model ID, e.g. 'gpt-4'.
	 * @param string $action_code The action code, used for filter names.
	 *
	 * @throws Exception On failure or empty response.
	 * @return string The first chat completion text.
	 */
	public function process_chat_completion( $prompt, $model, $action_code ) {

		$filter_id = strtolower( $action_code );

		$body = array(
			'model'    => apply_filters( $filter_id . '_model', $model, $prompt ),
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$body = apply_filters( $filter_id . '_body', $body );

		$response = $this->openai_request( 'v1/chat/completions', $body );

		return $this->get_chat_response_text( $response );
	}

	////////////////////////////////////////////////////////////
	// Response helpers
	////////////////////////////////////////////////////////////

	/**
	 * Check if a model exists (e.g. for GPT-4 access detection).
	 *
	 * @param string $model_id The model ID, e.g. 'gpt-4'.
	 *
	 * @throws Exception On failure.
	 * @return array The decoded response.
	 */
	public function get_model( $model_id ) {
		return $this->openai_request(
			'v1/models/' . sanitize_text_field( $model_id ),
			array(),
			array(
				'method'         => 'GET',
				'reduce_credits' => false,
			)
		);
	}

	/**
	 * Extract the first chat completion text from a response.
	 *
	 * @param array $response The chat completions response.
	 *
	 * @throws Exception If the response contains no text.
	 * @return string The response text.
	 */
	public function get_chat_response_text( $response ) {

		$text = $response['choices'][0]['message']['content'] ?? '';

		if ( 0 === strlen( $text ) ) {
			throw new Exception(
				esc_html_x( 'The model predicted a completion that results in no output. Consider adjusting your prompt.', 'OpenAI', 'uncanny-automator' ),
				400
			);
		}

		return $text;
	}

	/**
	 * Extract the first legacy completion text from a response.
	 *
	 * @param array $response The completions response.
	 *
	 * @throws Exception If the response contains no text.
	 * @return string The response text.
	 */
	public function get_completion_text( $response ) {

		$text = $response['choices'][0]['text'] ?? '';

		if ( 0 === strlen( $text ) ) {
			throw new Exception(
				esc_html_x( 'The model predicted a completion that results in no output. Consider adjusting your prompt.', 'OpenAI', 'uncanny-automator' ),
				400
			);
		}

		return $text;
	}
}
