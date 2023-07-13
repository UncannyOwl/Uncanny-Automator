<?php
/**
 * Creates the settings page
 *
 * @since   4.8
 * @version 4.8
 * @package Uncanny_Automator
 * @author  Ajay V.
 */

namespace Uncanny_Automator;

/**
 * Telegram_Settings
 */
class Telegram_Settings extends Settings\Premium_Integration_Settings {

	protected $functions;
	protected $client;

	/**
	 * set_properties
	 *
	 * @return void
	 */
	public function set_properties() {

		$this->set_id( 'telegram' );

		$this->set_icon( 'TELEGRAM' );

		$this->set_name( 'Telegram' );

		$this->register_option( 'automator_telegram_bot_secret' );

		$this->functions = new Telegram_Functions();
	}

	/**
	 * Sets up the properties of the settings page
	 */
	public function get_status() {
		return $this->helpers->functions->integration_connected() ? 'success' : '';
	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {
		include_once 'view-telegram.php';
	}

	/**
	 * settings_updated
	 *
	 * @return void
	 */
	public function settings_updated() {

		$alert_type    = 'success';
		$alert_heading = __( 'You have successfully connected your Telegram bot', 'uncanny-automator' );

		try {
			$this->functions->api->verify_token();
		} catch ( \Exception $e ) {
			$alert_type    = 'error';
			$alert_heading = __( 'Something went wrong: ', 'uncanny-automator' ) . $e->getMessage();
		}

		$this->add_alert(
			array(
				'type'    => $alert_type,
				'heading' => $alert_heading,
			)
		);

	}
}
