<?php
namespace Uncanny_Automator\OpenAI;

use Uncanny_Automator\Api_Server;

/**
 * Class usage:
 *
 * $x = new HTTP_Client()\
 * $x->set_endpoint('chat-completions')\
 * $x->set_request_body( ..$args[] )\
 * $x->set_api_key( '1bdccseewThe-apikey-here')\
 * Try {\
 *     $x = $x->send_request();\
 *     // To get the result.
 *     $x->get_response();
 * } catch( \Exception $e ) {\
 *     error_log( var_export( $e->getMessage(), true ) );\
 * }\
 *
 * Dall-E 3 sizes [1024x1024, 1024x1792 or 1792x1024]
 *
 * @since 4.13
 */
class HTTP_Client {

	/**
	 * @var string
	 */
	const API_URL = 'https://api.openai.com/';

	/**
	 * @var Api_Server
	 */
	protected $api = null;

	/**
	 * @var string $endpoint.
	 */
	protected $endpoint = '';

	/**
	 * @var mixed[] $request_body
	 */
	protected $request_body = array();

	/**
	 * @var string $key
	 */
	private $api_key = '';

	/**
	 * @var mixed $response
	 */
	protected $response = null;

	/**
	 * @param \Uncanny_Automator\Api_Server $api
	 */
	public function __construct( Api_Server $api ) {
		$this->api = $api;
	}
	/**
	 * Get $endpoint.
	 *
	 * @return  string
	 */
	public function get_endpoint() {
		return $this->endpoint;
	}

	/**
	 * Set $endpoint.
	 *
	 * @param  string  $endpoint  $endpoint.
	 *
	 * @return  self
	 */
	public function set_endpoint( $endpoint ) {
		$this->endpoint = $endpoint;

		return $this;
	}

	/**
	 * Get the value of request_body
	 */
	public function get_request_body() {
		return $this->request_body;
	}

	/**
	 * Set the value of request_body
	 *
	 * @return  self
	 */
	public function set_request_body( $request_body ) {
		$this->request_body = $request_body;

		return $this;
	}

	/**
	 * Get the value of key
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Set the value of key
	 *
	 * @return  self
	 */
	public function set_api_key( $key ) {
		$this->api_key = $key;

		return $this;
	}

	/**
	 * @return null|mixed[]
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * @throws \Exception
	 */
	public function send_request( $method = 'POST' ) {

		$this->reduce_credits();

		$body = $this->get_request_body();

		if ( 'POST' === $method ) {
			$body = wp_json_encode( $body );
		}

		$response = wp_remote_request(
			$this->get_url(),
			array(
				'method'  => $method,
				'timeout' => apply_filters( 'automator_openai_http_client_timeout', 120 ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->get_api_key(),
				),
				'body'    => $body,
			)
		);

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code ) {

			$response_body = wp_parse_args(
				$response_body,
				array(
					'error' => array(
						'message' => 'OpenAI has returned an error with empty message',
						'type'    => 'invalid_unknown',
					),
				)
			);

			$err_message = sprintf( 'OpenAI error: [%s] %s', $response_body['error']['type'], $response_body['error']['message'] );

			throw new \Exception( $err_message, (int) $status_code );

		}

		$this->response = $response_body;

	}

	/**
	 * @return string
	 */
	protected function get_url() {
		return self::API_URL . $this->get_endpoint();
	}

	/**
	 * Validates the headers before sending request to openai.
	 *
	 * @throws \Exception
	 */
	private function reduce_credits() {

		Api_Server::api_call(
			array(
				'endpoint' => 'v2/credits',
				'body'     => array(
					'action' => 'reduce_credits',
				),
				'timeout'  => 30,
			)
		);

	}

	/**
	 * Retrieves the first response string.
	 *
	 * @param array $response
	 *
	 * @return string The response
	 */
	public function get_first_response_string( $response ) {

		$response_text = isset( $response['choices'][0]['message']['content'] )
			? $response['choices'][0]['message']['content'] :
			''; // Defaults to empty string.

		if ( 0 === strlen( $response_text ) ) {
			throw new \Exception( 'The model predicted a completion that results in no output. Consider adjusting your prompt.', 400 );
		}

		return $response_text;

	}

}
