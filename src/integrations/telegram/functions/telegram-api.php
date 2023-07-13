<?php

namespace Uncanny_Automator;

/**
 * Class Telegram_Api
 *
 * @package Uncanny_Automator
 */
class Telegram_Api {

	const API_ENDPOINT = 'v2/telegram';

	protected $functions;

	/**
	 * __construct
	 *
	 * @param  mixed $functions
	 * @return void
	 */
	public function __construct( $functions ) {
		$this->functions = $functions;
	}

	/**
	 * api_request
	 *
	 * @param  array $body
	 * @param  array $action_data
	 * @return WP_HTTP_Response
	 */
	public function api_request( $body, $action_data = null ) {

		$body['bot_token'] = $this->functions->get_bot_token();

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
		);

		$response = Api_Server::api_call( $params );

		$this->check_for_errors( $response );

		return $response;
	}

	/**
	 * check_for_errors
	 *
	 * @param  WP_HTTP_Request $response
	 * @return void
	 */
	public function check_for_errors( $response ) {

		$reponse_data = $response['data'];

		if ( ! isset( $reponse_data['ok'] ) ) {
			throw new \Exception( __( 'Invalid response from the API', 'uncanny-automator' ) );
		}

		if ( true === $reponse_data['ok'] ) {
			return;
		}

		$error = ! empty( $reponse_data['description'] ) ? $reponse_data['description'] : 'Unknown error occured';

		$error_code = ! empty( $reponse_data['error_code'] ) ? $reponse_data['error_code'] : null;

		throw new \Exception( $error, $error_code );
	}

	/**
	 * verify_token
	 *
	 * @return void
	 */
	public function verify_token() {

		delete_option( Telegram_Functions::BOT_INFO );

		$api_request_body = array(
			'action' => 'bot_info',
		);

		$bot_info = $this->api_request( $api_request_body );

		$bot_info = $bot_info['data'];

		if ( empty( $bot_info['result'] ) ) {
			throw new \Exception( __( 'Bot token verification failed.', 'uncanny-automator' ) );
		}

		update_option( Telegram_Functions::BOT_INFO, $bot_info['result'] );
	}

	/**
	 * register_telegram_webhook
	 *
	 * @param  mixed $webhook
	 * @return WP_HTTP_Response
	 */
	public function register_telegram_webhook( $webhook ) {

		$api_request_body = array(
			'action'  => 'register_webhook',
			'webhook' => wp_json_encode( $webhook ),
		);

		return $this->api_request( $api_request_body );
	}

	/**
	 * delete_telegram_webhook
	 *
	 * @return WP_HTTP_Response
	 */
	public function delete_telegram_webhook() {

		$api_request_body = array(
			'action' => 'delete_webhook',
		);

		return $this->api_request( $api_request_body );
	}

	/**
	 * send_message
	 *
	 * @param  mixed $chat_id
	 * @param  string $text
	 * @return WP_HTTP_Response
	 */
	public function send_message( $chat_id, $text ) {

		$api_request_body = array(
			'action'  => 'send_message',
			'chat_id' => $chat_id,
			'text'    => $text,
		);

		return $this->api_request( $api_request_body );
	}
}
