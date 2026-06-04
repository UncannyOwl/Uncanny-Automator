<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class EC_Provider_Constants
 *
 * Canonical keys for every Event Tickets provider. Consumed via FQN by
 * the unified attendee triggers and any provider-aware Pro item — never
 * import the integration class just to read these.
 *
 * @package Uncanny_Automator
 */
final class EC_Provider_Constants {

	const RSVP             = 'rsvp';
	const WOO              = 'woo';
	const EDD              = 'edd';
	const TPP              = 'tpp';
	const TICKETS_COMMERCE = 'tickets-commerce';

	/**
	 * Resolve the provider key for an attendee by querying ET's
	 * canonical provider lookup, then delegating to `from_provider_class()`
	 * for the actual FQN-to-constant mapping.
	 *
	 * @param int $attendee_id
	 *
	 * @return string One of the class constants, or '' on unknown.
	 */
	public static function from_attendee( $attendee_id ) {

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) || ! method_exists( '\Tribe__Tickets__Tickets', 'get_event_ticket_provider' ) ) {
			return '';
		}

		$provider = (string) \Tribe__Tickets__Tickets::get_event_ticket_provider( $attendee_id );

		return self::from_provider_class( $provider );
	}

	/**
	 * Resolve a provider class FQN (or any string the plugin uses to
	 * identify a ticket provider — slug, FQN, label) to a canonical
	 * EC_Provider_Constants value.
	 *
	 * Match order is **most-specific first** so a future Tickets
	 * Commerce gateway class containing "PayPal" in its FQN doesn't
	 * get mis-classified as the legacy TPP provider. This is the single
	 * source of truth for provider classification — every consumer
	 * (`from_attendee()`, the `tribe_tickets_plus_attendee_update` hook,
	 * any future per-event/per-order resolver) routes through this
	 * method so a fix to the matcher propagates everywhere.
	 *
	 * @param string $provider Provider class FQN or identifier.
	 *
	 * @return string One of the class constants, or '' on unknown.
	 */
	public static function from_provider_class( $provider ) {

		if ( '' === $provider ) {
			return '';
		}

		// Tickets Commerce — check before any substring match that could
		// catch a TC gateway class (e.g. a future TC PayPal gateway).
		if ( false !== stripos( $provider, 'TEC\\Tickets\\Commerce' ) || false !== stripos( $provider, 'Commerce\\Module' ) ) {
			return self::TICKETS_COMMERCE;
		}

		if ( false !== stripos( $provider, 'RSVP' ) ) {
			return self::RSVP;
		}

		if ( false !== stripos( $provider, 'WooCommerce' ) || false !== stripos( $provider, 'Woo' ) ) {
			return self::WOO;
		}

		if ( false !== stripos( $provider, 'EDD' ) ) {
			return self::EDD;
		}

		// Legacy Tribe Commerce / PayPal Express — checked last because
		// "PayPal" is a generic substring.
		if ( false !== stripos( $provider, 'PayPal' ) || false !== stripos( $provider, 'TPP' ) ) {
			return self::TPP;
		}

		return '';
	}
}
