<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

/**
 * Class Sugar_Calendar_Integration
 *
 * @package Uncanny_Automator
 */
class Sugar_Calendar_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Sugar_Calendar_Helpers();
		$this->set_integration( 'SUGAR_CALENDAR' );
		$this->set_name( 'Sugar Calendar' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sugar-calendar-icon.svg' );
		$this->register_hooks();
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Sugar_Calendar_Event_Created( $this->helpers );
		new Sugar_Calendar_Ticket_Purchased( $this->helpers );

		// Actions.
		new Sugar_Calendar_Create_Event( $this->helpers );
		new Sugar_Calendar_Delete_Event( $this->helpers );
		new Sugar_Calendar_Register_Attendee( $this->helpers );
		new Sugar_Calendar_Rsvp_Event( $this->helpers );
	}

	/**
	 * Check if Sugar Calendar is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'SC_PLUGIN_VERSION' );
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		add_action( 'wp_ajax_automator_sugar_calendar_fetch_events', array( $this->helpers, 'ajax_fetch_events' ) );
		add_action( 'wp_ajax_automator_sugar_calendar_fetch_events_for_triggers', array( $this->helpers, 'ajax_fetch_events_for_triggers' ) );
	}
}
