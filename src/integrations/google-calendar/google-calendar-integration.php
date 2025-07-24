<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

/**
 * Class Google_Calendar_Integration
 *
 * @package Uncanny_Automator
 */
class Google_Calendar_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration Set-up.
	 */
	protected function setup() {

		$this->helpers = new Google_Calendar_Helpers();

		$this->set_integration( 'GOOGLE_CALENDAR' );

		$this->set_name( 'Google Calendar' );

		$this->set_connected( false !== automator_get_option( 'automator_google_calendar_credentials', false ) ? true : false );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'google-calendar' ) );

		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/google-calendar-icon.svg' );
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		new Google_Calendar_Settings( $this->helpers );
		new GCALENDAR_ADDEVENT( $this->helpers );
		new GCALENDAR_ADDATTENDEE( $this->helpers );
		new GCALENDAR_REMOVEATTENDEE( $this->helpers );
	}
}
