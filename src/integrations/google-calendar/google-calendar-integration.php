<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Google_Calendar_Integration
 *
 * @package Uncanny_Automator
 */
class Google_Calendar_Integration extends App_Integration {

	/**
	 * Define configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'GOOGLE_CALENDAR',
			'name'         => 'Google Calendar',
			'api_endpoint' => 'v2/google-calendar',
			'settings_id'  => 'google-calendar',
		);
	}

	/**
	 * Setup the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$config = self::get_config();

		// Create helpers with config
		$this->helpers = new Google_Calendar_Helpers( $config );

		// Set icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/google-calendar-icon.svg' );

		// Setup app integration with same config.
		$this->setup_app_integration( $config );
	}

	/**
	 * Load the integration.
	 *
	 * @return void
	 */
	protected function load() {
		// Settings page.
		new Google_Calendar_Settings( $this->dependencies, $this->get_settings_config() );

		// Actions.
		new GCALENDAR_ADDEVENT( $this->dependencies );
		new GCALENDAR_ADDATTENDEE( $this->dependencies );
		new GCALENDAR_REMOVEATTENDEE( $this->dependencies );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		// Recipe UI endpoints.
		add_action( 'wp_ajax_automator_google_calendar_get_calendar_options', array( $this->helpers, 'ajax_get_calendar_options' ) );
		add_action( 'wp_ajax_automator_google_calendar_get_event_options', array( $this->helpers, 'ajax_get_event_options' ) );
	}

	/**
	 * Check if the app is connected
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();
		if ( ! is_array( $credentials ) || ! isset( $credentials['access_token'] ) ) {
			return false;
		}

		return true;
	}
}
