<?php

namespace Uncanny_Automator\Integrations\Telegram;

use Uncanny_Automator\App_Integrations\App_Helpers;

/**
 * Class Telegram_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Telegram_Api_Caller $api
 * @property Telegram_Webhooks $webhooks
 */
class Telegram_App_Helpers extends App_Helpers {

	/**
	 * Credentials - Bot secret.
	 *
	 * @var string
	 */
	const BOT_SECRET_OPTION = 'automator_telegram_bot_secret';

	/**
	 * Account info - Bot info.
	 *
	 * @var string
	 */
	const BOT_INFO = 'automator_telegram_bot_info';

	/**
	 * Channel data - Channel data.
	 *
	 * @var string
	 */
	const CHANNEL_DATA_OPTION = 'automator_telegram_channel_data';

	/**
	 * Set properties
	 *
	 * @return void
	 */
	public function set_properties() {
		// Map to existing option names to preserve connections.
		$this->set_credentials_option_name( self::BOT_SECRET_OPTION );
		$this->set_account_option_name( self::BOT_INFO );
	}

	/**
	 * Validate credentials format
	 *
	 * @param array $credentials
	 * @param array $args
	 *
	 * @return array|\WP_Error
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( empty( $credentials ) ) {
			return new \WP_Error( 'missing_token', esc_html_x( 'Bot token is required', 'Telegram', 'uncanny-automator' ) );
		}
		return $credentials;
	}

	/**
	 * Get channels/chats options.
	 *
	 * @return void
	 */
	public function ajax_get_channels_chats_options() {
		Automator()->utilities->verify_nonce();

		$channels = $this->get_channel_data();
		if ( empty( $channels ) ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => sprintf(
						// translators: %s is a link to the Telegram Settings page
						esc_html_x( 'No channels/chats registered. Please visit your %s for instructions on how to register a channel/chat.', 'Telegram', 'uncanny-automator' ),
						sprintf(
							'<a href="%s" target="_blank">%s</a>',
							$this->get_settings_page_url(),
							esc_html_x( 'Telegram Settings', 'Telegram', 'uncanny-automator' )
						)
					),
				)
			);
		}

		$options = array();
		foreach ( $channels as $channel ) {
			$options[] = array(
				'value' => $channel['value'],
				'text'  => $channel['text'],
			);
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * Get channel data
	 *
	 * @return array
	 */
	public function get_channel_data() {
		return automator_get_option( self::CHANNEL_DATA_OPTION, array() );
	}

	/**
	 * Set channel data
	 *
	 * @param array $data
	 * @return void
	 */
	public function set_channel_data( $data ) {
		automator_update_option( self::CHANNEL_DATA_OPTION, $data );
	}

	/**
	 * Delete channel data
	 *
	 * @return void
	 */
	public function delete_channel_data() {
		automator_delete_option( self::CHANNEL_DATA_OPTION );
	}
}

// CRITICAL: Add class_alias for Pro compatibility
class_alias( __NAMESPACE__ . '\Telegram_App_Helpers', 'Telegram_Helpers' );
