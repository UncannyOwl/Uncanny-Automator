<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Tribe__Tickets__Ticket_Object;
use WP_Post;

/**
 * Class Rsvp_Tickets_Dispatcher
 *
 * An RSVP in the old format fires
 * `event_tickets_rsvp_tickets_generated_for_product( $ticket_id, $order_id, $quantity, $attendee_ids )`
 * once per ticket when its attendees exist. Event Tickets 5.30 makes RSVPs
 * Tickets Commerce orders, which never fire it. Their attendees come from
 * Tickets Commerce's attendee generation, which fires
 * `tec_tickets_commerce_flag_action_generated_attendees` once per ticket of
 * the order, after each attendee carries its RSVP answer.
 *
 * This dispatcher answers that hook for RSVP tickets only and fires the same
 * four arguments as plain values:
 *
 *   `automator_tec_rsvp_tickets_generated_for_product( int $ticket_id, int $order_id, int $quantity, int[] $attendee_ids )`
 *
 * so the RSVP trigger and the registration bridge treat both formats alike.
 *
 * @since 8.0.3
 *
 * @package Uncanny_Automator
 */
class Rsvp_Tickets_Dispatcher {

	/**
	 * Whether the upstream listener is registered.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Idempotent boot.
	 *
	 * @return void
	 */
	public static function boot() {

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'tec_tickets_commerce_flag_action_generated_attendees', array( __CLASS__, 'on_attendees_generated' ), 20, 3 );
	}

	/**
	 * Test seam: unregister the listener and clear the boot guard.
	 *
	 * @return void
	 */
	public static function reset_boot() {

		remove_action( 'tec_tickets_commerce_flag_action_generated_attendees', array( __CLASS__, 'on_attendees_generated' ), 20 );

		self::$booted = false;
	}

	/**
	 * Fire the RSVP event for an RSVP ticket's generated attendees.
	 *
	 * do_action() passes a one-attendee list as the attendee post itself, so
	 * both shapes arrive here.
	 *
	 * @param array<int,mixed>|WP_Post            $attendees The generated attendees.
	 * @param Tribe__Tickets__Ticket_Object|mixed $ticket    The ticket they were generated for.
	 * @param WP_Post|mixed                       $order     The Tickets Commerce order.
	 *
	 * @return void
	 */
	public static function on_attendees_generated( $attendees, $ticket, $order ) {

		if ( $attendees instanceof WP_Post ) {
			$attendees = array( $attendees );
		}

		if ( ! $ticket instanceof Tribe__Tickets__Ticket_Object || ! $order instanceof WP_Post || ! is_array( $attendees ) ) {
			return;
		}

		if ( ! ( new EC_Rsvp() )->is_tickets_commerce_rsvp( $ticket ) ) {
			return;
		}

		$attendee_ids = array();

		foreach ( $attendees as $attendee ) {
			$attendee_id = $attendee instanceof WP_Post ? (int) $attendee->ID : 0;

			if ( 0 < $attendee_id ) {
				$attendee_ids[] = $attendee_id;
			}
		}

		if ( empty( $attendee_ids ) ) {
			return;
		}

		do_action( 'automator_tec_rsvp_tickets_generated_for_product', (int) $ticket->ID, (int) $order->ID, count( $attendee_ids ), $attendee_ids );
	}
}
