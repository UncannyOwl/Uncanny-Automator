<?php

namespace Uncanny_Automator\Integrations\Telegram;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Telegram_Integration
 *
 * @package Uncanny_Automator
 */
class Telegram_Integration extends App_Integration {

	/**
	 * Get integration config
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'TELEGRAM',
			'name'         => 'Telegram',
			'api_endpoint' => 'v2/telegram',
			'settings_id'  => 'telegram',
		);
	}

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		// Initialize helpers first for App Integration.
		$this->helpers = new Telegram_App_Helpers( self::get_config() );

		// Set icon.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/telegram-icon.svg' );

		// Set up the App Integration.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	protected function load() {

		// Initialize settings.
		new Telegram_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Actions.
		new TELEGRAM_SEND_MESSAGE( $this->dependencies );

		// Triggers.
		new TELEGRAM_MESSAGE_RECEIVED( $this->dependencies );

		// Handle migrations.
		new Telegram_Chat_Option_Migration( 'telegram_chat_select_meta', $this->is_app_connected() );
	}

	/**
	 * Check if app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();
		$account     = $this->helpers->get_account_info();

		return ! empty( $credentials ) && ! is_wp_error( $credentials ) && ! empty( $account );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_telegram_get_channels_chats', array( $this->helpers, 'ajax_get_channels_chats_options' ) );
	}
}
