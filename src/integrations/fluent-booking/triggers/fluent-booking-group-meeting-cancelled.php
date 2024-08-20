<?php

namespace Uncanny_Automator\Integrations\Fluent_Booking;

use FluentBooking\App\Models\Booking;
use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENT_BOOKING_GROUP_MEETING_CANCELLED
 * @pacakge Uncanny_Automator
 */
class FLUENT_BOOKING_GROUP_MEETING_CANCELLED extends Trigger {

	protected $helpers;

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'FLUENT_BOOKING' );
		$this->set_trigger_code( 'FB_GROUP_MEETING_CANCELLED' );
		$this->set_trigger_meta( 'FB_MEETING_SCHEDULED' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - FluentBooking
		$this->set_sentence( esc_attr_x( 'A meeting is cancelled', 'FluentBooking', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_attr_x( 'A meeting is cancelled', 'FluentBooking', 'uncanny-automator' ) );
		$this->add_action( 'fluent_booking/booking_schedule_cancelled', 10, 2 );
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		$booking = $hook_args[0];

		if ( ! $booking instanceof Booking ) {
			return false;
		}

		$booking_data = $booking->attributesToArray();

		if ( 'group' !== $booking_data['event_type'] ) {
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
		$common_tokens  = $this->helpers->get_fluent_booking_common_tokens();
		$trigger_tokens = $this->helpers->get_fluent_booking_cancellation_tokens();

		return array_merge( $tokens, $common_tokens, $trigger_tokens );
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
		$booking = $hook_args[0];

		if ( ! $booking instanceof Booking ) {
			return array();
		}

		$attributes = $booking->attributesToArray();
		$ct_values  = $this->helpers->parse_common_token_values( $booking, $attributes );
		$tt_values  = $this->helpers->parse_cancellation_token_values( $booking->getCancelReason( true ), $attributes['updated_at'] );

		return array_merge( $tt_values, $ct_values );
	}

}
