<?php

namespace Uncanny_Automator\Integrations\Telegram;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Telegram_Api_Caller
 *
 * @package Uncanny_Automator
 */
class Telegram_Api_Caller extends Api_Caller {

	///////////////////////////////////////////////////////////
	// Abstract methods
	///////////////////////////////////////////////////////////

	/**
	 * Set properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->set_credential_request_key( 'bot_token' );
	}

	/**
	 * Prepare credentials for API request
	 *
	 * @param mixed $credentials
	 * @param array $args
	 *
	 * @return string
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		if ( is_wp_error( $credentials ) ) {
			throw new Exception( esc_html_x( 'Invalid credentials', 'Telegram', 'uncanny-automator' ) );
		}

		return $credentials;
	}

	/**
	 * Check for API errors
	 *
	 * @param array $response
	 * @param array $args
	 * @return void
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {

		$response_data = $response['data'];

		if ( ! isset( $response_data['ok'] ) ) {
			throw new Exception( esc_html_x( 'Invalid response from the API', 'Telegram', 'uncanny-automator' ) );
		}

		if ( true === $response_data['ok'] ) {
			return;
		}

		$error      = ! empty( $response_data['description'] ) ? $response_data['description'] : 'Unknown error occurred';
		$error_code = ! empty( $response_data['error_code'] ) ? $response_data['error_code'] : null;

		throw new Exception( esc_html( $error ), absint( $error_code ) );
	}

	///////////////////////////////////////////////////////////
	// Integration specific methods
	///////////////////////////////////////////////////////////

	/**
	 * Verify bot token
	 *
	 * @return array
	 * @throws Exception
	 */
	public function verify_bot_token() {
		// Clear existing bot info first.
		$this->helpers->delete_account_info();

		$bot_info = $this->api_request( 'bot_info' );
		$bot_info = $bot_info['data'];

		if ( empty( $bot_info['result'] ) ) {
			throw new Exception( esc_html_x( 'No bot information returned.', 'Telegram', 'uncanny-automator' ) );
		}

		// Store bot info
		$this->helpers->store_account_info( $bot_info['result'] );

		return $bot_info['result'];
	}

	/**
	 * Register Telegram webhook
	 *
	 * @param array $webhook
	 *
	 * @return array
	 */
	public function register_telegram_webhook( $webhook ) {
		return $this->api_request(
			array(
				'action'  => 'register_webhook',
				'webhook' => wp_json_encode( $webhook ),
			)
		);
	}

	/**
	 * Delete Telegram webhook
	 *
	 * @return array
	 */
	public function delete_telegram_webhook() {
		return $this->api_request( 'delete_webhook' );
	}

	/**
	 * Send message to chat
	 *
	 * @param mixed $chat_id
	 * @param string $text
	 * @param string $parse_mode Optional. 'Markdown', 'MarkdownV2', or 'HTML'. Defaults to 'Markdown'.
	 * @return array
	 */
	public function send_message( $chat_id, $text, $parse_mode = 'Markdown' ) {
		return $this->api_request(
			array(
				'action'     => 'send_message',
				'chat_id'    => $chat_id,
				'text'       => $text,
				'parse_mode' => $parse_mode,
			)
		);
	}
}
