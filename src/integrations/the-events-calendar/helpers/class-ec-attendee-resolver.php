<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Stateless resolver for "given an attendee post ID, find …" lookups.
 *
 * Centralizes two queries that were previously duplicated across
 * triggers, actions, and the tokens class:
 *
 *   1. attendee → event ID (3 inline copies pre-refactor)
 *   2. attendee → ticket (product) ID (1 inline copy pre-refactor)
 *
 * Both follow the same fallback strategy: try the canonical Tribe API
 * first (`Tribe__Tickets__Tickets::get_attendee_event_ticket()`), and
 * if that returns nothing, walk every per-provider post-meta key from
 * `EC_Attendee_Meta_Keys` until a value is found.
 *
 * Free + pro both consume — placement is intentional in free.
 *
 * @package Uncanny_Automator
 */
final class EC_Attendee_Resolver {

	/**
	 * Resolve the parent event post ID for an attendee.
	 *
	 * @param int $attendee_id Attendee post ID.
	 *
	 * @return int Event post ID, or 0 if no provider matched.
	 */
	public static function event_id_from_attendee( $attendee_id ) {

		$attendee_id = absint( $attendee_id );

		if ( 0 === $attendee_id ) {
			return 0;
		}

		// Canonical: Tribe's own provider lookup. Returns array with
		// `event_id` when the attendee belongs to a known provider.
		if ( class_exists( '\Tribe__Tickets__Tickets' ) && method_exists( '\Tribe__Tickets__Tickets', 'get_attendee_event_ticket' ) ) {
			$ticket = \Tribe__Tickets__Tickets::get_attendee_event_ticket( $attendee_id );
			if ( is_array( $ticket ) && ! empty( $ticket['event_id'] ) ) {
				return absint( $ticket['event_id'] );
			}
		}

		// Fallback: walk every provider's event meta key.
		foreach ( EC_Attendee_Meta_Keys::EVENT_KEYS as $key ) {
			$event_id = (int) get_post_meta( $attendee_id, $key, true );
			if ( $event_id > 0 ) {
				return $event_id;
			}
		}

		return 0;
	}

	/**
	 * Resolve the first attendee post ID for a given event + order + ticket.
	 *
	 * The per-product "tickets generated" triggers receive the order and
	 * product in their hook but not the attendee IDs. This returns the first
	 * matching attendee so the recipe can expose that registrant's custom
	 * fields — mirroring the RSVP trigger's first-attendee semantics. Uses
	 * Tribe's own normalized attendee list (provider-agnostic).
	 *
	 * @param int $event_id   Event post ID.
	 * @param int $order_id   Order ID.
	 * @param int $product_id Ticket (product) post ID. 0 matches any ticket.
	 *
	 * @return int Attendee post ID, or 0 if none matched.
	 */
	public static function first_attendee_for_order( $event_id, $order_id, $product_id ) {

		$event_id   = absint( $event_id );
		$order_id   = absint( $order_id );
		$product_id = absint( $product_id );

		if ( 0 === $event_id || 0 === $order_id ) {
			return 0;
		}

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) || ! method_exists( '\Tribe__Tickets__Tickets', 'get_event_attendees' ) ) {
			return 0;
		}

		$attendees = \Tribe__Tickets__Tickets::get_event_attendees( $event_id );

		if ( empty( $attendees ) || ! is_array( $attendees ) ) {
			return 0;
		}

		foreach ( $attendees as $attendee ) {

			if ( ! is_array( $attendee ) || absint( $attendee['order_id'] ?? 0 ) !== $order_id ) {
				continue;
			}

			if ( 0 !== $product_id && absint( $attendee['product_id'] ?? 0 ) !== $product_id ) {
				continue;
			}

			$attendee_id = absint( $attendee['attendee_id'] ?? 0 );

			if ( $attendee_id > 0 ) {
				return $attendee_id;
			}
		}

		return 0;
	}

	/**
	 * Resolve every attendee record matching an email on an event.
	 *
	 * One person can hold several tickets for the same event, so this returns
	 * ALL matching attendee post IDs. Matches the attendee's holder email and
	 * falls back to the purchaser email, case-insensitive. Backs the email-based
	 * check-in / uncheck-in actions. Provider-agnostic (uses Tribe's normalized
	 * attendee list).
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $email    Attendee email to match.
	 *
	 * @return int[] All matching attendee post IDs (may be empty).
	 */
	public static function attendee_ids_for_email_on_event( $event_id, $email ) {

		$event_id = absint( $event_id );
		$email    = strtolower( trim( (string) $email ) );

		if ( 0 === $event_id || '' === $email ) {
			return array();
		}

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) || ! method_exists( '\Tribe__Tickets__Tickets', 'get_event_attendees' ) ) {
			return array();
		}

		$attendees = \Tribe__Tickets__Tickets::get_event_attendees( $event_id );

		if ( empty( $attendees ) || ! is_array( $attendees ) ) {
			return array();
		}

		$matches = array();

		foreach ( $attendees as $attendee ) {

			if ( ! is_array( $attendee ) ) {
				continue;
			}

			$holder    = strtolower( trim( (string) ( $attendee['holder_email'] ?? '' ) ) );
			$purchaser = strtolower( trim( (string) ( $attendee['purchaser_email'] ?? '' ) ) );

			if ( $email !== $holder && $email !== $purchaser ) {
				continue;
			}

			$attendee_id = absint( $attendee['attendee_id'] ?? 0 );

			if ( $attendee_id > 0 ) {
				$matches[] = $attendee_id;
			}
		}

		return array_values( array_unique( $matches ) );
	}

	/**
	 * Resolve the ticket (product) post ID for an attendee.
	 *
	 * @param int $attendee_id Attendee post ID.
	 *
	 * @return int Ticket post ID, or 0 if no provider matched.
	 */
	public static function ticket_id_from_attendee( $attendee_id ) {

		$attendee_id = absint( $attendee_id );

		if ( 0 === $attendee_id ) {
			return 0;
		}

		// Canonical: Tribe's provider lookup. Returns array with
		// `product_id` for the attendee's ticket.
		if ( class_exists( '\Tribe__Tickets__Tickets' ) && method_exists( '\Tribe__Tickets__Tickets', 'get_attendee_event_ticket' ) ) {
			$ticket = \Tribe__Tickets__Tickets::get_attendee_event_ticket( $attendee_id );
			if ( is_array( $ticket ) && ! empty( $ticket['product_id'] ) ) {
				return absint( $ticket['product_id'] );
			}
		}

		// Fallback: walk every provider's product meta key.
		foreach ( EC_Attendee_Meta_Keys::PRODUCT_KEYS as $key ) {
			$ticket_id = (int) get_post_meta( $attendee_id, $key, true );
			if ( $ticket_id > 0 ) {
				return $ticket_id;
			}
		}

		return 0;
	}
}
