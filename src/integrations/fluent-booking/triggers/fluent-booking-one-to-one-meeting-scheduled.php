<?php

namespace Uncanny_Automator\Integrations\Fluent_Booking;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENT_BOOKING_ONE_TO_ONE_MEETING_SCHEDULED
 *
 * @pacakge Uncanny_Automator
 */
class FLUENT_BOOKING_ONE_TO_ONE_MEETING_SCHEDULED extends Trigger {

	protected $helpers;

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'FLUENT_BOOKING' );
		$this->set_trigger_code( 'FB_ONE_TO_ONE_MEETING_SCHEDULED' );
		$this->set_trigger_meta( 'FB_MEETING_SCHEDULED' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - FluentBooking
		$this->set_sentence( esc_attr_x( 'A one-to-one meeting is scheduled', 'FluentBooking', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_attr_x( 'A one-to-one meeting is scheduled', 'FluentBooking', 'uncanny-automator' ) );
		$this->add_action( 'fluent_booking/after_booking_scheduled', 10, 3 );
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		list( $booking, $calendarSlot, $bookingData ) = $hook_args;

		if ( 'single' !== $bookingData['event_type'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$common_tokens = $this->helpers->get_fluent_booking_common_tokens();

		return array_merge( $tokens, $common_tokens );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $booking, $calendarSlot, $bookingData ) = $hook_args;

		return $this->helpers->parse_common_token_values( $booking, $bookingData );
	}
}
