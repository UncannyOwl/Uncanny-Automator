<?php

namespace Uncanny_Automator;

/**
 * Class Telegram_Functions
 *
 * @package Uncanny_Automator
 */
class Telegram_Functions {

	const BOT_SECRET_OPTION = 'automator_telegram_bot_secret';
	const NONCE             = 'automator_telegram_disconnect';
	const SETTINGS_TAB      = 'telegram';
	const BOT_INFO          = 'automator_telegram_bot_info';

	public $api;
	public $webhook;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		$this->api     = new Telegram_Api( $this );
		$this->webhook = new Telegram_Webhook( $this );
	}

	/**
	 * register_hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_telegram_disconnect', array( $this, 'disconnect' ) );

		if ( $this->integration_connected() ) {
			$this->webhook->register_hooks();
		}
	}

	/**
	 * get_tab_url
	 *
	 * @return string
	 */
	public function get_tab_url() {
		return admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=' . self::SETTINGS_TAB;
	}

	/**
	 * integration_connected
	 *
	 * @return bool
	 */
	public function integration_connected() {

		if ( false === $this->get_bot_info() ) {
			return false;
		}

		return true;
	}

	/**
	 * get_bot_token
	 *
	 * @return mixed
	 */
	public function get_bot_token() {
		return automator_get_option( self::BOT_SECRET_OPTION, false );
	}

	/**
	 * disconnect_url
	 *
	 * @return string
	 */
	public function disconnect_url() {
		return add_query_arg(
			array(
				'action' => 'automator_telegram_disconnect',
				'nonce'  => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * disconnect
	 *
	 * @return void
	 */
	public function disconnect() {

		$this->webhook->delete_telegram_webhook();

		delete_option( self::BOT_SECRET_OPTION );
		delete_option( self::BOT_INFO );

		wp_safe_redirect( $this->get_tab_url() );

		exit;
	}

	/**
	 * get_bot_info
	 *
	 * @return mixed
	 */
	public function get_bot_info() {
		return automator_get_option( self::BOT_INFO, false );
	}
}
