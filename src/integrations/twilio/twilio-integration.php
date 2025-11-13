<?php

namespace Uncanny_Automator\Integrations\Twilio;

/**
 * Class Twilio_Integration
 *
 * @package Uncanny_Automator
 */
class Twilio_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get integration config
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'TWILIO',
			'name'         => 'Twilio',
			'api_endpoint' => 'v2/twilio',
			'settings_id'  => 'twilio-api',
		);
	}

	/**
	 * Setup the integration
	 *
	 * @return void
	 */
	protected function setup() {
		$config = self::get_config();

		// Create helpers with config
		$this->helpers = new Twilio_App_Helpers( $config );

		// Set icon URL
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/twilio-icon.svg' );

		// Setup app integration with same config
		$this->setup_app_integration( $config );
	}

	/**
	 * Load components
	 *
	 * @return void
	 */
	public function load() {
		// Settings page
		new Twilio_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions
		new TWILIO_SEND_SMS( $this->dependencies );
	}

	/**
	 * Check if the app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();
		$account     = $this->helpers->get_account_info();

		return ! empty( $credentials ) && ! empty( $account );
	}
}
