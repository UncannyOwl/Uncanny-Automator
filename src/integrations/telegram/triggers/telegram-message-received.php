<?php

namespace Uncanny_Automator\Integrations\Telegram;

/**
 * Class TELEGRAM_MESSAGE_RECEIVED
 *
 * @package Uncanny_Automator
 *
 * @property Telegram_App_Helpers $helpers
 * @property Telegram_Api_Caller $api
 * @property Telegram_Webhooks $webhooks
 */
class TELEGRAM_MESSAGE_RECEIVED extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Setup trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'TELEGRAM' );
		$this->set_trigger_code( 'MESSAGE_RECEIVED' );
		$this->set_trigger_meta( 'TELEGRAM_MESSAGE_RECEIVED' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_uses_api( true );
		// The action hook to attach this trigger into.
		$this->add_action( $this->webhooks->get_const( 'INCOMING_WEBHOOK_ACTION' ) );
		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 1 );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the chat/channel title
				esc_html_x( 'A text message is received from {{a chat/channel:%1$s}}', 'Telegram', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A text message is received from {{a chat/channel}}', 'Telegram', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_attr_x( 'Channel/Chat', 'Telegram', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->get_channel_options(),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Get channel options for dropdown
	 *
	 * @return array
	 */
	private function get_channel_options() {
		$channels = $this->helpers->get_channel_data();

		$options = array(
			array(
				'text'  => esc_attr_x( 'Any', 'Telegram', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		if ( ! empty( $channels ) ) {
			foreach ( $channels as $channel ) {
				$options[] = array(
					'text'  => $channel['text'],
					'value' => $channel['value'],
				);
			}
		}

		return $options;
	}

	/**
	 * Define tokens
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$telegram_tokens = array(
			array(
				'tokenId'   => 'CHAT_ID',
				'tokenName' => esc_html_x( 'Chat ID', 'Telegram', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FIRST_NAME',
				'tokenName' => esc_html_x( 'First name', 'Telegram', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LAST_NAME',
				'tokenName' => esc_html_x( 'Last name', 'Telegram', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USERNAME',
				'tokenName' => esc_html_x( 'Username', 'Telegram', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CHAT_TYPE',
				'tokenName' => esc_html_x( 'Chat type', 'Telegram', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CHAT_TITLE',
				'tokenName' => esc_html_x( 'Chat title', 'Telegram', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DATE',
				'tokenName' => esc_html_x( 'Date', 'Telegram', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TEXT',
				'tokenName' => esc_html_x( 'Text', 'Telegram', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $tokens, $telegram_tokens );
	}

	/**
	 * Validate the trigger
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $request ) = $hook_args;
		$request_body    = $request->get_json_params();

		// Check if this is a text message (either in message or channel_post)
		if ( empty( $request_body['message']['text'] ) && empty( $request_body['channel_post']['text'] ) ) {
			return false;
		}

		// Get the selected channel from trigger meta.
		$selected_channel = $trigger['meta'][ $this->get_trigger_meta() ] ?? '-1';

		// Allow empty or any action selection to run ( should always be -1 as it's set to default ).
		if ( empty( $selected_channel ) || '-1' === (string) $selected_channel ) {
			return true;
		}

		// Get chat ID from the message.
		$message = $request_body['message'] ?? $request_body['channel_post'] ?? array();
		$chat_id = $message['chat']['id'] ?? '';

		// Validate that the message is from the selected channel.
		return (string) $chat_id === (string) $selected_channel;
	}

	/**
	 * Hydrate tokens with webhook data
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $request ) = $hook_args;
		$request_body    = $request->get_json_params();

		$message = array();

		// Get message data from either message or channel_post
		if ( isset( $request_body['message'] ) ) {
			$message = $request_body['message'];
		} elseif ( isset( $request_body['channel_post'] ) ) {
			$message = $request_body['channel_post'];
		}

		if ( empty( $message ) || ! isset( $message['text'] ) ) {
			return array();
		}

		$output = array();

		// Basic message data
		$output['DATE'] = $message['date'] ?? '';
		$output['TEXT'] = $message['text'] ?? '';

		// Chat information
		if ( isset( $message['chat'] ) ) {
			$chat                 = $message['chat'];
			$output['CHAT_ID']    = $chat['id'] ?? '';
			$output['CHAT_TITLE'] = $chat['title'] ?? '';
			$output['CHAT_TYPE']  = $chat['type'] ?? '';
		}

		// Sender information - handle both 'from' and 'sender_chat' for channel posts
		if ( isset( $message['from'] ) ) {
			$from                 = $message['from'];
			$output['USERNAME']   = $from['username'] ?? '';
			$output['FIRST_NAME'] = $from['first_name'] ?? '';
			$output['LAST_NAME']  = $from['last_name'] ?? '';
		} elseif ( isset( $message['sender_chat'] ) ) {
			// For channel posts, use sender_chat data
			$sender_chat          = $message['sender_chat'];
			$output['USERNAME']   = $sender_chat['username'] ?? '';
			$output['FIRST_NAME'] = $sender_chat['title'] ?? '';
			$output['LAST_NAME']  = '';
		}

		return $output;
	}
}
