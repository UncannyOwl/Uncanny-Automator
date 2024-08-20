<?php

namespace Uncanny_Automator\Integrations\Fluent_Booking;

use Uncanny_Automator\Integration;

/**
 * Class Fluent_Booking_Integration
 *
 * @pacakge Uncanny_Automator
 */
class Fluent_Booking_Integration extends Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->helpers = new Fluent_Booking_Helpers();
		$this->set_integration( 'FLUENT_BOOKING' );
		$this->set_name( 'FluentBooking' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/fluentbooking-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new FLUENT_BOOKING_ONE_TO_ONE_MEETING_SCHEDULED( $this->helpers );
		new FLUENT_BOOKING_GROUP_MEETING_SCHEDULED( $this->helpers );
		new FLUENT_BOOKING_ONE_TO_ONE_MEETING_CANCELLED( $this->helpers );
		new FLUENT_BOOKING_GROUP_MEETING_CANCELLED( $this->helpers );

		// Load actions
		new FLUENT_BOOKING_NEW_BOOKING( $this->helpers );

		// Load ajax method
		add_action( 'wp_ajax_get_event_meeting_duration', array( $this->helpers, 'get_event_meeting_duration' ) );
		add_action( 'wp_ajax_get_event_meeting_location', array( $this->helpers, 'get_event_meeting_location' ) );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'FLUENT_BOOKING_VERSION' );
	}
}
