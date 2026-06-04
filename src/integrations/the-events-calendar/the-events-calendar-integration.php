<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class The_Events_Calendar_Integration
 *
 * Modern integration shell for The Events Calendar / Event Tickets.
 *
 * The integration code remains 'EC' (sacred — stored in recipe DB).
 *
 * Cross-provider ticket-registration normalization (the
 * USER_REGISTERED_ACTION fan-in) lives on the helper class, since the
 * helper owns the data plane for the integration (Remote_Data segments,
 * tokens, ticket-provider hooks). The integration class is just the
 * shell that wires AJAX routes and instantiates triggers/actions.
 *
 * @package Uncanny_Automator
 */
class The_Events_Calendar_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup integration metadata.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new The_Events_Calendar_Helpers();
		$this->set_integration( 'EC' );
		$this->set_name( 'The Events Calendar' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/the-events-calendar-icon.svg' );
		$this->set_plugin_file_path( 'the-events-calendar/the-events-calendar.php' );

		// Backward-compat: Free 7.3 renamed this dir event-tickets → the-events-calendar
		// (code still 'EC'), but old Pro (< 7.3) items call this helper under the
		// pre-rename slug recipe->event_tickets->options->all_ec_*() / ->options->pro->*.
		// The renamed Pro dir is addon-only, so the legacy loader stores its BARE Pro
		// helper (no ->options) at recipe->event_tickets during Phase 4 (load_helpers).
		// So alias AFTER that (PHP_INT_MAX on automator_add_integration_helpers, which
		// fires after Phase 4 and before recipe parts load at init:30) to win. Gated on
		// old Pro; new Pro 7.3+ ships its own integration and self-wires.
		if ( class_exists( '\Uncanny_Automator_Pro\Event_Tickets_Pro_Helpers' )
			&& ! class_exists( '\Uncanny_Automator_Pro\Integrations\The_Events_Calendar\The_Events_Calendar_Pro_Integration' ) ) {
			add_action( 'automator_add_integration_helpers', array( $this, 'alias_legacy_event_tickets_slug' ), PHP_INT_MAX );
		}
	}

	/**
	 * Point the pre-rename slug recipe->event_tickets at the modern helper (which
	 * exposes ->options + ->pro + the all_ec_* shims), overriding the bare Pro helper
	 * the legacy loader stored there. Reuses that Pro helper instance as ->pro so the
	 * Pro helper is not double-instantiated. Backward-compat for old Pro (< 7.3).
	 *
	 * @return void
	 */
	public function alias_legacy_event_tickets_slug() {

		$recipe   = \Automator()->helpers->recipe;
		$existing = isset( $recipe->event_tickets ) ? $recipe->event_tickets : null;

		// Reuse the Pro helper the legacy loader already built (avoids a second
		// instance double-registering its admin-ajax handlers).
		if ( $existing instanceof \Uncanny_Automator_Pro\Event_Tickets_Pro_Helpers ) {
			$this->helpers->pro = $existing;
		}

		$recipe->event_tickets = $this->helpers;
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	protected function load() {
		new EC_REGISTER( $this->helpers );
		new EC_EVENT_CREATED_OR_UPDATED( $this->helpers );
		new EC_EVENT_UPDATED( $this->helpers );
		new EC_ORGANIZER_CREATED( $this->helpers );
		new EC_ORGANIZER_UPDATED( $this->helpers );
		new EC_VENUE_CREATED( $this->helpers );
		new EC_VENUE_UPDATED( $this->helpers );
		new EC_EVENT_LINKED( $this->helpers );
		new EC_EVENT_UNLINKED( $this->helpers );

		// Wave 2 / 1B — Event Tickets triggers (each gates on ET per item).
		new ET_ATTENDEE_CHECKED_IN( $this->helpers );
		new ET_ATTENDEE_UNCHECKED_IN( $this->helpers );
		new ET_RSVP_TICKETS_GENERATED_PRODUCT( $this->helpers );
		new ET_TICKET_ADDED( $this->helpers );
		new ET_TICKET_DELETED( $this->helpers );

		// Phase 2A — TEC core CRUD actions (gate on TEC core only).
		new EC_CREATE_EVENT( $this->helpers );
		new EC_UPDATE_EVENT( $this->helpers );
		new EC_DELETE_EVENT( $this->helpers );
		new EC_CREATE_ORGANIZER( $this->helpers );
		new EC_CREATE_VENUE( $this->helpers );
		new EC_ASSIGN_ORGANIZER( $this->helpers );
		new EC_ASSIGN_VENUE( $this->helpers );

		// Phase 2B: ET-gated actions.
		new ET_CHECK_IN_ATTENDEE( $this->helpers );
		new ET_UNCHECK_IN_ATTENDEE( $this->helpers );
		new ET_CREATE_RSVP_ATTENDEE( $this->helpers );
	}

	/**
	 * Plugin-active gate. The Events Calendar core is the baseline
	 * runtime dependency; Event Tickets features are gated per
	 * trigger/action via requirements_met() using the Has_Dependency trait.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Tribe__Events__Main' );
	}
}
