<?php
namespace Uncanny_Automator;

/**
 * Class Add_Google_Calendar_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Google_Calendar_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}


	/**
	 * Integration Set-up.
	 */
	protected function setup() {

		$this->set_integration( 'GOOGLE_CALENDAR' );

		$this->set_name( 'Google Calendar' );

		$this->set_connected( false !== get_option( 'automator_google_calendar_credentials', false ) ? true : false );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'google-calendar' ) );

		$this->set_icon( __DIR__ . '/img/google-calendar-icon.svg' );

	}

	/**
	 * Explicitly return true because its a 3rd-party plugin.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}
}
