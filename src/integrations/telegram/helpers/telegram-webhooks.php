<?php

namespace Uncanny_Automator\Integrations\Telegram;

use Uncanny_Automator\App_Integrations\App_Webhooks;
use WP_REST_Response;
use Exception;

/**
 * Class Telegram_Webhooks
 *
 * @package Uncanny_Automator
 *
 * @property Telegram_App_Helpers $helpers
 * @property Telegram_API_Caller $api
 */
class Telegram_Webhooks extends App_Webhooks {

	/**
	 * Incoming webhook action - legacy
	 *
	 * @var string
	 */
	const INCOMING_WEBHOOK_ACTION = 'automator_telegram_incoming_webhook';

	/**
	 * Registration token prefix
	 *
	 * @var string
	 */
	private $registration_prefix = 'automator_register_';

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Set webhook properties
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set legacy webhook endpoint to preserve existing URLs
		$this->set_webhook_endpoint( 'automator_telegram' );
	}

	/**
	 * Override - Check if webhooks should be registered.
	 * - Checks if the integration is connected.
	 * - Checks if webhooks are enabled via stored option.
	 * - Includes fallback legacy mapping for already connected / registered webhooks.
	 *
	 * @return bool
	 */
	public function should_register_webhooks() {
		// Check if the integration is connected.
		if ( ! $this->is_connected ) {
			return false;
		}

		// Check if webhooks are enabled via stored option.
		if ( $this->get_webhooks_enabled_status() ) {
			return true;
		}

		// Fallback migration logic here.
		// 1. Check for legacy automator_telegram_webhook option and delete it.
		$details = automator_get_option( 'automator_telegram_webhook', array() );
		automator_delete_option( 'automator_telegram_webhook' );

		// 2. Check if we have a secret_token.
		$secret_token = $details['secret_token'] ?? '';

		// 3. Check if the secret_token is empty.
		if ( empty( $secret_token ) ) {
			return false;
		}

		// 4. Store the secret token and enable webhooks.
		automator_update_option( $this->get_webhook_key_option_name(), $secret_token );
		$this->store_webhooks_enabled_status( true );

		// 5. Schedule webhook refresh.
		add_action( 'init', array( $this, 'refresh_webhook_after_migration' ) );

		return true;
	}

	/**
	 * Refresh webhook after migration with updated allowed_updates.
	 *
	 * @return void
	 */
	public function refresh_webhook_after_migration() {
		try {
			$this->register_telegram_webhook();
		} catch ( Exception $e ) {
			$this->store_webhooks_enabled_status( false );
		}
	}

	////////////////////////////////////////////////////////////
	// Webhook processing override methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate the incoming webhook.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool|WP_REST_Response
	 */
	protected function validate_webhook( $request ) {
		$request_security_token = $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
		if ( empty( $request_security_token ) ) {
			return false;
		}

		if ( ! $this->is_valid_webhook_key( $request_security_token ) ) {
			return false;
		}

		// Maybe handle registration (both /start and callback queries).
		$results = $this->maybe_handle_registration( $request );
		if ( is_a( $results, 'WP_REST_Response' ) ) {
			// Bail return response to prevent further processing.
			return $results;
		}

		// Continue with regular webhook processing.
		return true;
	}

	/**
	 * Set the shutdown data for triggers.
	 *
	 * @param WP_REST_Request $request The WP_REST_Request object.
	 *
	 * @return array
	 */
	protected function set_shutdown_data( $request ) {
		return array(
			'action_name'   => self::INCOMING_WEBHOOK_ACTION,
			'action_params' => array( $request ),
		);
	}

	////////////////////////////////////////////////////////////
	// Webhook CRUD
	////////////////////////////////////////////////////////////

	/**
	 * Register Telegram webhook with API
	 *
	 * @return array
	 * @throws Exception If registration fails
	 */
	public function register_telegram_webhook() {
		return $this->api->register_telegram_webhook(
			array(
				'secret_token'    => $this->regenerate_webhook_key(),
				'url'             => $this->get_webhook_url(),
				'allowed_updates' => array( 'message', 'channel_post', 'callback_query' ),
			)
		);
	}

	/**
	 * Delete Telegram webhook
	 *
	 * @return array
	 * @throws Exception If deletion fails
	 */
	public function delete_telegram_webhook() {
		$webhook_key = $this->get_webhook_key( false );

		if ( empty( $webhook_key ) ) {
			return;
		}

		return $this->api->delete_telegram_webhook();
	}

	////////////////////////////////////////////////////////////
	// Channel / Chat Registration handling
	////////////////////////////////////////////////////////////

	/**
	 * Maybe handle registration requests (both /start commands and callback queries)
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool|WP_REST_Response
	 */
	private function maybe_handle_registration( $request ) {
		$body = $request->get_json_params();
		if ( empty( $body ) ) {
			return false;
		}

		// Handle /start command from regular messages.
		if ( isset( $body['message']['text'] ) && 0 === strpos( $body['message']['text'], '/start' ) ) {
			return $this->maybe_handle_start_command( $body );
		}

		// Handle /start command from channel posts.
		if ( isset( $body['channel_post']['text'] ) && 0 === strpos( $body['channel_post']['text'], '/start' ) ) {
			return $this->maybe_handle_start_command( $body, 'channel_post' );
		}

		// Handle callback query (button presses).
		if ( isset( $body['callback_query']['data'] ) ) {
			$callback_data = $body['callback_query']['data'];
			// Only process our registration callbacks.
			if ( $this->is_registration_callback( $callback_data ) ) {
				return $this->handle_callback_query( $body );
			}
			// Return response to prevent any callback query from triggering recipes.
			// We only added allowed_updates callback_query for our new registration flow.
			return $this->create_response( true, 'Callback query received' );
		}

		return false;
	}

	/**
	 * Check if callback data is an Automator registration callback.
	 *
	 * @param string $callback_data
	 *
	 * @return bool
	 */
	private function is_registration_callback( $callback_data ) {
		return 0 === strpos( $callback_data, $this->registration_prefix );
	}

	/**
	 * Handle /start command for registration.
	 *
	 * @param array $body
	 * @param string $source_type Either 'message' or 'channel_post'
	 *
	 * @return bool|WP_REST_Response
	 */
	private function maybe_handle_start_command( $body, $source_type = 'message' ) {
		// Extract message data from body.
		$message_data = $this->extract_message_data( $body, $source_type );

		// Extract token from /start command.
		$token_data = $this->extract_token_from_start_command( $message_data['text'] );
		if ( ! $token_data ) {
			return false;
		}

		// Only process if this is a valid registration token for OUR system.
		if ( ! $this->is_valid_registration_token( $token_data['token'] ) ) {
			return false;
		}

		// Process registration based on context.
		$this->process_start_command_registration(
			$message_data,
			$token_data['token'],
			$token_data['target_channel']
		);

		// Return response to prevent trigger processing.
		return $this->create_response( true, 'Registration request processed' );
	}

	/**
	 * Extract message data from webhook body.
	 *
	 * @param array $body
	 * @param string $source_type
	 *
	 * @return array
	 */
	private function extract_message_data( $body, $source_type ) {
		if ( 'channel_post' === $source_type ) {
			return array(
				'text'                => $body['channel_post']['text'],
				'chat'                => $body['channel_post']['chat'],
				'original_message_id' => $body['channel_post']['message_id'],
			);
		}

		return array(
			'text'                => $body['message']['text'],
			'chat'                => $body['message']['chat'],
			'original_message_id' => $body['message']['message_id'],
		);
	}

	/**
	 * Extract token from /start command text
	 *
	 * @param string $text
	 * @return array|false
	 */
	private function extract_token_from_start_command( $text ) {
		$exploded = explode( '/start ', $text );

		// If there's no token after /start, this is not our registration flow.
		if ( ! isset( $exploded[1] ) || empty( $exploded[1] ) ) {
			return false;
		}

		$parts = explode( ' ', $exploded[1] );

		return array(
			'token'          => $parts[0],
			'target_channel' => $parts[1] ?? null,
		);
	}

	/**
	 * Process registration based on context.
	 *
	 * @param array $message_data
	 * @param string $token
	 * @param string|null $target_channel
	 *
	 * @return void
	 */
	private function process_start_command_registration( $message_data, $token, $target_channel ) {
		// If target channel specified, send there directly.
		if ( ! empty( $target_channel ) ) {
			$this->send_inline_registration_message( $target_channel, $token );
			return;
		}

		// Otherwise, handle based on current chat type.
		$chat_type = $message_data['chat']['type'];
		$chat_id   = $message_data['chat']['id'];

		// If we're in a channel/group, send registration message directly here.
		if ( in_array( $chat_type, array( 'channel', 'group', 'supergroup' ), true ) ) {
			$this->send_inline_registration_message(
				$chat_id,
				$token,
				$message_data['original_message_id']
			);
			return;
		}

		// If we're in private chat, send instructions.
		$this->send_registration_instructions( $chat_id, $token );
	}

	/**
	 * Send registration message directly to the target channel/chat
	 *
	 * @param mixed $chat_id
	 * @param string $token
	 * @param string|null $start_message_id
	 * @return array
	 */
	private function send_inline_registration_message( $chat_id, $token, $start_message_id = null ) {
		// Create callback data - token already includes the prefix.
		$callback_data = $token;
		if ( $start_message_id ) {
			$callback_data .= "|{$start_message_id}";
		}

		$reply_markup = array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => esc_html_x( 'Register This Channel', 'Telegram', 'uncanny-automator' ),
						'callback_data' => $callback_data,
					),
				),
			),
		);

		return $this->api->api_request(
			array(
				'action'       => 'send_message',
				'chat_id'      => $chat_id,
				'text'         => esc_html_x( 'Click the button below to register this channel with Uncanny Automator:', 'Telegram', 'uncanny-automator' ),
				'parse_mode'   => 'Markdown',
				'reply_markup' => wp_json_encode( $reply_markup ),
			)
		);
	}

	/**
	 * Send registration instructions to user
	 *
	 * @param string $chat_id
	 * @param string $token
	 * @return void
	 */
	private function send_registration_instructions( $chat_id, $token ) {

		// Get bot username.
		$bot_info     = $this->helpers->get_account_info();
		$bot_username = $bot_info['username'];

		$instructions = sprintf(
			esc_html_x(
				'To register a channel:' . "\n\n" .
				'1. Add me (%s) to your channel as an admin' . "\n" .
				'2. In your channel, type: `/start %s`' . "\n" .
				'3. Click the "Register This Channel" button' . "\n\n" .
				"That's it! I'll automatically detect it's a channel and send the registration button there.",
				'Telegram',
				'uncanny-automator'
			),
			$bot_username,
			$token
		);

		$this->api->api_request(
			array(
				'action'     => 'send_message',
				'chat_id'    => $chat_id,
				'text'       => $instructions,
				'parse_mode' => 'Markdown',
			)
		);
	}

	/**
	 * Handle callback query for channel registration.
	 *
	 * @param array $body
	 *
	 * @return bool|WP_REST_Response
	 */
	private function handle_callback_query( $body ) {
		$callback_data = $body['callback_query'];
		$chat_data     = $callback_data['message']['chat'];
		$query_id      = $callback_data['id'];

		// Check if this is a registration callback.
		if ( ! $this->is_registration_callback( $callback_data['data'] ) ) {
			return false;
		}

		// Extract token and message ID from callback data.
		$token_data = $this->extract_token_from_callback_data( $callback_data['data'] );

		if ( ! $this->is_valid_registration_token( $token_data['token'] ) ) {
			$this->answer_callback_query(
				$query_id,
				esc_html_x( 'Invalid registration token.', 'Telegram', 'uncanny-automator' )
			);
			return $this->create_response( false, 'Invalid registration token' );
		}

		// Save channel data.
		$this->store_channel_data(
			$chat_data['id'],
			$chat_data['title'] ?? 'Unknown Channel',
			$chat_data['type'] ?? 'unknown'
		);

		// Answer the callback query with success message.
		$this->answer_callback_query(
			$query_id,
			esc_html_x( 'Channel registered successfully for use in your Automator recipes!', 'Telegram', 'uncanny-automator' )
		);

		// Delete the registration messages after successful registration.
		$this->delete_registration_messages( $body, $token_data['original_message_id'] );

		return $this->create_response( true, 'Channel registered successfully' );
	}

	/**
	 * Extract token and message ID from callback data.
	 *
	 * @param string $callback_data
	 *
	 * @return array
	 */
	private function extract_token_from_callback_data( $callback_data ) {
		// Split by pipe delimiter first.
		$parts = explode( '|', $callback_data );

		return array(
			'token'               => $parts[0], // Full token with prefix
			'original_message_id' => $parts[1] ?? null,
		);
	}

	/**
	 * Answer a callback query
	 *
	 * @param string $query_id
	 * @param string $text
	 *
	 * @return void
	 */
	private function answer_callback_query( $query_id, $text ) {
		$this->api->api_request(
			array(
				'action'            => 'answer_callback_query',
				'callback_query_id' => $query_id,
				'text'              => $text,
				'show_alert'        => true,
			)
		);
	}

	/**
	 * Delete registration messages after successful registration.
	 *
	 * @param array $body
	 * @param string|null $start_message_id
	 *
	 * @return void
	 */
	private function delete_registration_messages( $body, $start_message_id = null ) {
		$chat_id    = $body['callback_query']['message']['chat']['id'];
		$message_id = $body['callback_query']['message']['message_id'];

		// Delete the registration message (the one with the button).
		$this->api->api_request(
			array(
				'action'     => 'delete_message',
				'chat_id'    => $chat_id,
				'message_id' => $message_id,
			)
		);

		// Delete the original /start command message if we have the precise ID.
		if ( $start_message_id ) {
			$this->api->api_request(
				array(
					'action'     => 'delete_message',
					'chat_id'    => $chat_id,
					'message_id' => $start_message_id,
				)
			);
		}
	}

	/**
	 * Create a response.
	 *
	 * @param bool $success
	 * @param string $message
	 *
	 * @return WP_REST_Response
	 */
	private function create_response( $success, $message ) {
		return new WP_REST_Response(
			array(
				'success' => $success,
				'message' => $message,
			),
		);
	}

	/**
	 * Store registered channel data.
	 *
	 * @param string $id
	 * @param string $title
	 * @param string $type
	 *
	 * @return void
	 */
	private function store_channel_data( $id, $title, $type ) {
		// Get existing data.
		$data = $this->helpers->get_channel_data();
		// Merge in the new data.
		$data[ $id ] = array(
			'text'  => sprintf( '%s (%s)', $title, $type ),
			'value' => $id,
			'title' => $title,
			'type'  => $type,
		);
		// Set the new data.
		$this->helpers->set_channel_data( $data );
	}

	////////////////////////////////////////////////////////////
	// Registration token generation and validation
	////////////////////////////////////////////////////////////

	/**
	 * Generate registration token for the connected bot.
	 *
	 * @return string|false Returns token or false if bot not connected
	 */
	public function generate_registration_token() {
		$bot_info = $this->helpers->get_account_info();
		$bot_id   = is_array( $bot_info ) ? $bot_info['id'] ?? '' : '';

		if ( empty( $bot_id ) ) {
			return false;
		}

		return "{$this->registration_prefix}{$bot_id}";
	}

	/**
	 * Validate registration token.
	 *
	 * @param string $token
	 *
	 * @return bool
	 */
	private function is_valid_registration_token( $token ) {
		// Check if token starts with our registration prefix.
		if ( ! $this->is_registration_callback( $token ) ) {
			return false;
		}

		// Extract bot ID from token.
		$token_bot_id = substr( $token, strlen( $this->registration_prefix ) );

		// Get connected bot info.
		$bot_info = $this->helpers->get_account_info();
		$bot_id   = is_array( $bot_info ) ? $bot_info['id'] ?? '' : '';

		// Validate token matches connected bot.
		return $token_bot_id === (string) $bot_id;
	}
}
