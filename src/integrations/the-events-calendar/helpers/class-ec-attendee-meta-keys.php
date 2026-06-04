<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Single source of truth for the Event Tickets attendee post-meta keys
 * used across triggers, actions, conditions, and loop filters.
 *
 * **Why this exists**: prior to this class, the same 5 provider event-key
 * arrays + 5 check-in-key arrays + 1 user-id key were duplicated across
 * 10+ files (verified at review time). When ET ships a new ticket
 * provider (e.g. a future Stripe Tickets module), every consumer must
 * be updated in lockstep — and per-file duplication makes that error
 * prone. Centralizing here means one edit per provider.
 *
 * **Production verified** in this session against installed source:
 *   - `_tribe_rsvp_event`           → `event-tickets/src/Tribe/RSVP.php:33`
 *   - `_tribe_wooticket_event`      → `event-tickets-plus/src/Tribe/Commerce/WooCommerce/Main.php`
 *   - `_tribe_eddticket_event`      → `event-tickets-plus/src/Tribe/Commerce/EDD/Main.php`
 *   - `_tribe_tpp_event`            → `event-tickets/src/Tribe/Commerce/PayPal/Main.php`
 *   - `_tec_tickets_commerce_event` → `event-tickets/src/Tickets/Commerce/Attendee.php:47` ($event_relation_meta_key)
 *   - `_tribe_tickets_attendee_user_id` → `event-tickets/src/Tribe/Tickets.php:228`
 *   - check-in keys mirror the per-provider scheme (Tickets Commerce:
 *     `_tec_tickets_commerce_checked_in` → `Attendee.php:119`)
 *
 * Free + pro both consume this class — placement is intentional in free.
 *
 * @package Uncanny_Automator
 */
final class EC_Attendee_Meta_Keys {

	/**
	 * Provider-specific post-meta keys that link an attendee post to its
	 * parent event post. One key per ticket provider.
	 *
	 * @var string[]
	 */
	const EVENT_KEYS = array(
		'_tribe_rsvp_event',
		'_tribe_wooticket_event',
		'_tribe_eddticket_event',
		'_tribe_tpp_event',
		'_tec_tickets_commerce_event',
	);

	/**
	 * Provider-specific post-meta keys that mark an attendee as
	 * checked in. Mirror of `EVENT_KEYS`, one per provider.
	 *
	 * @var string[]
	 */
	const CHECKIN_KEYS = array(
		'_tribe_rsvp_checkedin',
		'_tribe_wooticket_checkedin',
		'_tribe_eddticket_checkedin',
		'_tribe_tpp_checkedin',
		'_tec_tickets_commerce_checked_in',
	);

	/**
	 * Provider-specific post-meta keys that link an attendee post to its
	 * ticket (product) post. Used by per-ticket-type lookups.
	 *
	 * @var string[]
	 */
	const PRODUCT_KEYS = array(
		'_tribe_rsvp_product',
		'_tribe_wooticket_product',
		'_tribe_eddticket_product',
		'_tribe_tpp_product',
		'_tec_tickets_commerce_ticket',
	);

	/**
	 * Single attendee → user_id meta key (provider-agnostic).
	 *
	 * @var string
	 */
	const USER_ID = '_tribe_tickets_attendee_user_id';
}
