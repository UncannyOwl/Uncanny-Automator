<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Helper for The Events Calendar / Event Tickets integration.
 *
 * Owns four concerns:
 *   1. Legacy AJAX handlers (`get_events`, `get_organizers`, …) used by
 *      triggers/actions still on the `'ajax'` field-config pattern.
 *   2. Unified Remote_Data handlers (`remote_data_get_events`,
 *      `remote_data_get_events_strict`) auto-wired via the parent's
 *      `automator_remote_data_instance_ec` filter. Both share a single
 *      `fetch_post_options()` fetcher with the legacy AJAX template, so
 *      improvements (tribe_get_events bypass, status suffix, broader
 *      post_status) apply uniformly.
 *   3. The lazy `tokens()` accessor for the dedicated tokens class.
 *   4. Cross-provider ticket-registration normalization — RSVP,
 *      WooCommerce (Event Tickets Plus), PayPal/Tribe Commerce, and
 *      modern Tickets Commerce all fan into a single internal action
 *      (USER_REGISTERED_ACTION) that the `EC_REGISTER` trigger listens
 *      for.
 *
 * The historical FQN `\Uncanny_Automator\Event_Tickets_Helpers` stays
 * callable via `class_alias` at the bottom of this file.
 *
 * @package Uncanny_Automator
 */
class The_Events_Calendar_Helpers extends Abstract_Helpers {

	/**
	 * Internal action fired by every normalized ticket provider hook.
	 *
	 * @var string
	 */
	const USER_REGISTERED_ACTION = 'automator_event_tickets_user_registered';

	/**
	 * Internal normalized check-in action. RSVP fires its own `rsvp_checkin`
	 * hook while every other provider fires `event_tickets_checkin`; both are
	 * funnelled into this single action so the check-in trigger monitors one
	 * hook and fires for all providers.
	 *
	 * Signature: do_action( CHECKIN_ACTION, int $attendee_id, bool|null $qr, int|null $event_id ).
	 *
	 * @var string
	 */
	const CHECKIN_ACTION = 'automator_event_tickets_checkin';

	/**
	 * Internal normalized check-in-reversed action. Funnels RSVP's
	 * `rsvp_uncheckin` and the generic `event_tickets_uncheckin`.
	 *
	 * Signature: do_action( UNCHECKIN_ACTION, int $attendee_id ).
	 *
	 * @var string
	 */
	const UNCHECKIN_ACTION = 'automator_event_tickets_uncheckin';

	/**
	 * Lazy-loaded shared tokens instance.
	 *
	 * @var The_Events_Calendar_Tokens|null
	 */
	private $tokens = null;

	/**
	 * Backward-compat shim — old Pro (< 7.3) Event Tickets items call this helper as
	 * Automator()->helpers->recipe->event_tickets->options->method().
	 *
	 * @deprecated 7.3
	 * @var self
	 */
	public $options;

	/**
	 * Backward-compat shim for old Pro's ->options->pro->method() calls.
	 *
	 * @deprecated 7.3
	 * @var object|null
	 */
	public $pro;

	/**
	 * Whether the normalized hooks have been registered.
	 *
	 * Static guard so a second helper instance (e.g. Pro's composition
	 * `$base = new The_Events_Calendar_Helpers()`) does not re-register
	 * the same listeners.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Constructor — wires the cross-provider normalization listeners.
	 */
	public function __construct() {
		$this->register_normalized_hooks();

		// Backward-compat for OLD Pro (< 7.3) Event Tickets items: expose ->options so
		// recipe->event_tickets->options->all_ec_*() resolves to the shims below. The
		// pre-rename slug alias and the ->pro wiring happen after helpers load — see
		// The_Events_Calendar_Integration::alias_legacy_event_tickets_slug(). Harmless
		// for new Pro 7.3+ (which ships its own integration and self-wires).
		$this->options = $this;
	}

	/**
	 * Backward-compat shim. Old Pro stored its helper into ->pro via setPro().
	 *
	 * @deprecated 7.3
	 *
	 * @param object $pro The Pro helper instance.
	 *
	 * @return void
	 */
	public function setPro( $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Backward-compat: events dropdown field-definition for OLD Pro (< 7.3) Event
	 * Tickets items calling recipe->event_tickets->options->all_ec_events(). Ported
	 * from the pre-rename Event_Tickets_Helpers; its dependency
	 * (recipe->options->wp_query) still ships in 7.3.
	 *
	 * @deprecated 7.3
	 *
	 * @param string|null $label       Field label.
	 * @param string      $option_code Option/token code.
	 * @param array       $extra_args  Optional is_ajax/target_field/endpoint.
	 *
	 * @return array Field definition.
	 */
	public function all_ec_events( $label = null, $option_code = 'ECEVENTS', $extra_args = array() ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Event', 'uncanny-automator' );
		}

		$is_ajax      = key_exists( 'is_ajax', $extra_args ) ? $extra_args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $extra_args ) ? $extra_args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $extra_args ) ? $extra_args['endpoint'] : '';

		$args = array(
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		);

		$all_events = Automator()->helpers->recipe->options->wp_query( $args, true, esc_html__( 'Any event', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $all_events,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Event URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Event featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Event featured image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_ec_events', $option );
	}

	/**
	 * Backward-compat: RSVP-events dropdown field-definition for OLD Pro (< 7.3) Event
	 * Tickets items. Ported from the pre-rename Event_Tickets_Helpers; the stale
	 * recipe->load_helpers guard is replaced with a Tribe-class check.
	 *
	 * @deprecated 7.3
	 *
	 * @param string|null $label       Field label.
	 * @param string      $option_code Option/token code.
	 *
	 * @return array Field definition.
	 */
	public function all_ec_rsvp_events( $label = null, $option_code = 'ECEVENTS' ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Event', 'uncanny-automator' );
		}

		$args    = array(
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		);
		$options = array();

		if ( class_exists( '\Tribe__Tickets__Tickets_Handler' ) ) {
			$posts          = Automator()->helpers->recipe->options->wp_query( $args );
			$ticket_handler = new \Tribe__Tickets__Tickets_Handler();
			foreach ( $posts as $post_id => $title ) {
				if ( empty( $title ) ) {
					/* translators: 1: Event ID */
					$title = sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $post_id );
				}
				$rsvp_ticket = $ticket_handler->get_event_rsvp_tickets( get_post( $post_id ) );
				if ( ! empty( $rsvp_ticket ) ) {
					$options[ $post_id ] = $title;
				}
			}
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code          => esc_attr__( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Event URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_ec_events', $option );
	}

	/**
	 * Get the shared tokens instance.
	 *
	 * @return The_Events_Calendar_Tokens
	 */
	public function tokens() {
		if ( null === $this->tokens ) {
			$this->tokens = new The_Events_Calendar_Tokens( $this );
		}

		return $this->tokens;
	}

	// =========================================================================
	// Remote_Data segments (modern callers — auto-wired via parent filter)
	// =========================================================================

	/**
	 * Remote_Data handler: published `tribe_events` posts with an
	 * "Any event" sentinel at the top. Used by triggers, where "any
	 * event" is a meaningful subscription.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_events( $request ): array {

		unset( $request );

		$options = $this->fetch_post_options(
			'tribe_events',
			esc_html_x( 'Any event', 'The Events Calendar', 'uncanny-automator' )
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data handler: published `tribe_events` posts WITHOUT the
	 * "Any event" sentinel. Used by actions, where the recipe must
	 * target a concrete event — "any" makes no sense as an action target.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_events_strict( $request ): array {

		unset( $request );

		// Empty $any_label skips the sentinel prepend.
		$options = $this->fetch_post_options( 'tribe_events', '' );

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data handler: published `tribe_organizer` posts with an
	 * "Any organizer" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_organizers( $request ): array {

		unset( $request );

		$options = $this->fetch_post_options(
			'tribe_organizer',
			esc_html_x( 'Any organizer', 'The Events Calendar', 'uncanny-automator' )
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data handler: published `tribe_venue` posts with an "Any
	 * venue" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_venues( $request ): array {

		unset( $request );

		$options = $this->fetch_post_options(
			'tribe_venue',
			esc_html_x( 'Any venue', 'The Events Calendar', 'uncanny-automator' )
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data handler: published `tribe_event_series` posts with an
	 * "Any series" sentinel. Returns just the sentinel if the Series
	 * post type is not registered (Series add-on inactive).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_series( $request ): array {

		unset( $request );

		$options = $this->fetch_post_options(
			'tribe_event_series',
			esc_html_x( 'Any series', 'The Events Calendar', 'uncanny-automator' ),
			array(
				'gate' => function () {
					return post_type_exists( 'tribe_event_series' );
				},
			)
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data handler: `tribe_event_series` posts without the "Any
	 * series" sentinel. Used by conditions / loop-filters.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_series_strict( $request ): array {

		unset( $request );

		$options = $this->fetch_post_options(
			'tribe_event_series',
			'',
			array(
				'gate' => function () {
					return post_type_exists( 'tribe_event_series' );
				},
			)
		);

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data handler: published `tribe_events` posts whose end
	 * date has already passed. Timezone-correct via `tribe_get_end_date`.
	 *
	 * Filtering happens in PHP rather than `meta_query` because
	 * `_EventEndDate` is stored in the event's local timezone, not UTC,
	 * and per-event timezones drift. `tribe_get_end_date( $id, false, 'U' )`
	 * normalizes everything to a UTC unix timestamp.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_past_events( $request ): array {

		unset( $request );

		return $this->remote_data_success(
			$this->build_past_events_options( esc_html_x( 'Any past event', 'The Events Calendar', 'uncanny-automator' ) )
		);
	}

	/**
	 * Remote_Data handler: past events without the "Any past event"
	 * sentinel. Used by loop-filters where a specific past event is
	 * the only meaningful selection.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_past_events_strict( $request ): array {

		unset( $request );

		return $this->remote_data_success(
			$this->build_past_events_options( '' )
		);
	}

	/**
	 * Shared builder for the past-events option list.
	 *
	 * @param string $any_label Sentinel label, or '' to skip the sentinel.
	 *
	 * @return array
	 */
	private function build_past_events_options( string $any_label ): array {

		return $this->fetch_post_options(
			'tribe_events',
			$any_label,
			array(
				'gate'           => function () {
					return class_exists( 'Tribe__Events__Main' ) && function_exists( 'tribe_get_end_date' );
				},
				// SQL pre-filter reduces the candidate set; runtime filter enforces UTC.
				'get_args'       => array(
					'meta_key'   => '_EventStartDate',
					'orderby'    => 'meta_value',
					'order'      => 'DESC',
					'meta_query' => array(
						array(
							'key'     => '_EventEndDate',
							'value'   => current_time( 'mysql' ),
							'compare' => '<',
							'type'    => 'DATETIME',
						),
					),
				),
				'runtime_filter' => function ( $event ) {
					$end_utc = (int) tribe_get_end_date( $event->ID, false, 'U' );
					return $end_utc > 0 && $end_utc < time();
				},
			)
		);
	}

	/**
	 * Remote_Data handler: published `tribe_organizer` posts WITHOUT
	 * the "Any organizer" sentinel. Used by actions where the recipe
	 * must target a concrete organizer.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_organizers_strict( $request ): array {

		unset( $request );

		$options = $this->fetch_post_options( 'tribe_organizer', '' );

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data handler: published `tribe_venue` posts WITHOUT
	 * the "Any venue" sentinel. Used by actions where the recipe
	 * must target a concrete venue.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_venues_strict( $request ): array {

		unset( $request );

		$options = $this->fetch_post_options( 'tribe_venue', '' );

		return $this->remote_data_success( $options );
	}

	/**
	 * Remote_Data handler: tickets attached to the parent event field's
	 * selected event. Includes an "Any ticket" sentinel. Reads the
	 * parent value via `$request->get_group_id()` so the segment is
	 * portable across triggers/actions that use different parent codes.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tickets_for_event( $request ): array {
		return $this->remote_data_success( $this->fetch_tickets_for_event( $request, true ) );
	}

	/**
	 * Remote_Data handler: tickets attached to the parent event field's
	 * selected event WITHOUT the "Any ticket" sentinel. Used by actions
	 * where the recipe must target a concrete ticket.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tickets_for_event_strict( $request ): array {
		return $this->remote_data_success( $this->fetch_tickets_for_event( $request, false ) );
	}

	/**
	 * Resolve tickets for the event ID selected in the parent field.
	 *
	 * @param Remote_Data_Request $request    The remote-data request.
	 * @param bool                $include_any Whether to prepend the "Any ticket" sentinel.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function fetch_tickets_for_event( $request, bool $include_any ): array {

		$options = array();

		if ( $include_any ) {
			$options[] = $this->any_option( esc_html_x( 'Any ticket', 'The Events Calendar', 'uncanny-automator' ) );
		}

		// Resolve the event whose tickets we list. The recipe builder only sends
		// `group_id` (the parent field code) on a `parent_fields_change` event; on
		// `refresh-button` and `on-load` it is absent, so resolve_parent_event_id()
		// falls back to scanning the posted values for the event field.
		$event_id = $this->resolve_parent_event_id( $request );

		if ( 0 === $event_id ) {
			return $options;
		}

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) || ! method_exists( '\Tribe__Tickets__Tickets', 'get_all_event_tickets' ) ) {
			return $options;
		}

		$tickets = \Tribe__Tickets__Tickets::get_all_event_tickets( $event_id );

		if ( empty( $tickets ) || ! is_array( $tickets ) ) {
			return $options;
		}

		foreach ( $tickets as $ticket ) {
			if ( ! is_object( $ticket ) || empty( $ticket->ID ) ) {
				continue;
			}
			$options[] = $this->option_pair(
				(int) $ticket->ID,
				! empty( $ticket->name ) ? (string) $ticket->name : ''
			);
		}

		return $options;
	}

	/**
	 * Resolve the parent event ID for the tickets dropdown.
	 *
	 * The recipe builder only sends `group_id` (the parent field code) on a
	 * `parent_fields_change` event. On `refresh-button` and `on-load` it is
	 * absent — which left the dropdown empty because $values[''] resolved to
	 * nothing. When group_id is missing, fall back to scanning the posted
	 * values for the one field whose value is an actual `tribe_events` post.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return int Event post ID, or 0 when none resolves.
	 */
	private function resolve_parent_event_id( $request ): int {

		$values = $request->get_values();

		// Preferred: the parent field the builder named (parent_fields_change).
		$group_id = $request->get_group_id();
		if ( '' !== $group_id ) {
			$event_id = $this->event_value_from( $values, $group_id );
			if ( $event_id > 0 ) {
				return $event_id;
			}
		}

		// Fallback (refresh-button / on-load): the event field is the only posted
		// value that resolves to a `tribe_events` post. Ticket/operator fields
		// never do, so there is no ambiguity.
		foreach ( array_keys( $values ) as $key ) {

			// Skip the readable/label/custom companion keys.
			if ( '_custom' === substr( $key, -7 ) || false !== strpos( $key, '_readable' ) || false !== strpos( $key, '_label' ) ) {
				continue;
			}

			$event_id = $this->event_value_from( $values, $key );
			if ( $event_id > 0 && 'tribe_events' === get_post_type( $event_id ) ) {
				return $event_id;
			}
		}

		return 0;
	}

	/**
	 * Unwrap a single posted value into an event ID, honouring the
	 * `automator_custom_value` token-driven mode (value stored in
	 * `<key>_custom`) and the "-1" / "Any" sentinel.
	 *
	 * @param array  $values The posted values.
	 * @param string $key    The field key to read.
	 *
	 * @return int
	 */
	private function event_value_from( array $values, string $key ): int {

		$raw = $values[ $key ] ?? '';

		if ( '-1' === (string) $raw ) {
			return 0;
		}

		return ( 'automator_custom_value' === $raw )
			? absint( $values[ $key . '_custom' ] ?? 0 )
			: absint( $raw );
	}

	/**
	 * Remote_Data handler: `tribe_events_cat` terms with an "Any
	 * category" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_event_categories( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_event_category_options( true ) );
	}

	/**
	 * Remote_Data handler: `tribe_events_cat` terms WITHOUT the "Any category"
	 * sentinel. Used by conditions/loop-filters that resolve one concrete
	 * category (an "Any" value would absint(-1) => 1 and match the wrong term).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_event_categories_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_event_category_options( false ) );
	}

	/**
	 * Build `tribe_events_cat` term options. Shared by the two handlers above.
	 *
	 * @param bool $include_any Prepend the "Any category" sentinel.
	 *
	 * @return array<int,array{value:string,text:string}>
	 */
	private function build_event_category_options( $include_any = true ) {

		$options = array();

		if ( $include_any ) {
			$options[] = $this->any_option( esc_html_x( 'Any category', 'The Events Calendar', 'uncanny-automator' ) );
		}

		if ( ! class_exists( 'Tribe__Events__Main' ) || ! taxonomy_exists( 'tribe_events_cat' ) ) {
			return $options;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'tribe_events_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => 999,
			)
		);

		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = array(
					'value' => (string) $term->term_id,
					'text'  => '' !== $term->name
						? $term->name
						/* translators: %d is a term ID */
						: sprintf( esc_html_x( 'ID: %d (no name)', 'The Events Calendar', 'uncanny-automator' ), $term->term_id ),
				);
			}
		}

		return $options;
	}

	// =========================================================================
	// Legacy AJAX endpoints (still used by triggers/actions on the 'ajax' pattern)
	// =========================================================================

	/**
	 * AJAX endpoint: returns all published `tribe_events` posts as a
	 * dropdown. Includes an "Any event" sentinel (`-1`) at the top.
	 *
	 * @return void
	 */
	public function get_events() {
		$this->respond_with_post_options(
			'tribe_events',
			esc_html_x( 'Any event', 'The Events Calendar', 'uncanny-automator' )
		);
	}

	/**
	 * AJAX endpoint: returns all published `tribe_organizer` posts as a
	 * dropdown. Includes an "Any organizer" sentinel.
	 *
	 * @return void
	 */
	public function get_organizers() {
		$this->respond_with_post_options(
			'tribe_organizer',
			esc_html_x( 'Any organizer', 'The Events Calendar', 'uncanny-automator' )
		);
	}

	/**
	 * AJAX endpoint: returns all published `tribe_venue` posts as a
	 * dropdown. Includes an "Any venue" sentinel.
	 *
	 * @return void
	 */
	public function get_venues() {
		$this->respond_with_post_options(
			'tribe_venue',
			esc_html_x( 'Any venue', 'The Events Calendar', 'uncanny-automator' )
		);
	}

	/**
	 * AJAX endpoint: returns all published `tribe_event_series` posts as
	 * a dropdown. Includes an "Any series" sentinel. Returns just the
	 * sentinel if the series post type is not registered (Series add-on
	 * inactive).
	 *
	 * @return void
	 */
	public function get_series() {
		$this->respond_with_post_options(
			'tribe_event_series',
			esc_html_x( 'Any series', 'The Events Calendar', 'uncanny-automator' ),
			array(
				'gate' => function () {
					return post_type_exists( 'tribe_event_series' );
				},
			)
		);
	}

	/**
	 * AJAX endpoint: returns all tickets attached to the parent event
	 * field's selected event. Includes an "Any ticket" sentinel. Returns
	 * just the sentinel if no event is selected, "Any" is selected, or
	 * Event Tickets is not active.
	 *
	 * @return void
	 */
	public function get_tickets_for_event() {

		\Automator()->utilities->ajax_auth_check();

		$options  = array( $this->any_option( esc_html_x( 'Any ticket', 'The Events Calendar', 'uncanny-automator' ) ) );
		$event_id = automator_filter_input( 'value', INPUT_POST );

		if ( empty( $event_id ) || '-1' === (string) $event_id || ! class_exists( '\Tribe__Tickets__Tickets' ) ) {
			$this->json_response_die( $options );
		}

		$tickets = \Tribe__Tickets__Tickets::get_all_event_tickets( absint( $event_id ) );

		if ( ! empty( $tickets ) && is_array( $tickets ) ) {
			foreach ( $tickets as $ticket ) {
				if ( ! is_object( $ticket ) || empty( $ticket->ID ) ) {
					continue;
				}
				$options[] = $this->option_pair(
					(int) $ticket->ID,
					! empty( $ticket->name ) ? (string) $ticket->name : ''
				);
			}
		}

		$this->json_response_die( $options );
	}

	/**
	 * AJAX endpoint: returns published `tribe_events` posts whose end
	 * date has already passed. Timezone-correct via `tribe_get_end_date`.
	 *
	 * Filtering happens in PHP rather than `meta_query` because
	 * `_EventEndDate` is stored in the event's local timezone, not UTC,
	 * and per-event timezones drift. `tribe_get_end_date( $id, false, 'U' )`
	 * normalizes everything to a UTC unix timestamp.
	 *
	 * @return void
	 */
	public function get_past_events() {
		$this->respond_with_post_options(
			'tribe_events',
			esc_html_x( 'Any past event', 'The Events Calendar', 'uncanny-automator' ),
			array(
				'gate'           => function () {
					return class_exists( 'Tribe__Events__Main' ) && function_exists( 'tribe_get_end_date' );
				},
				// SQL pre-filter (coarse, site-tz) reduces the candidate
				// set; the runtime_filter then enforces UTC correctness.
				'get_args'       => array(
					'meta_key'   => '_EventStartDate',
					'orderby'    => 'meta_value',
					'order'      => 'DESC',
					'meta_query' => array(
						array(
							'key'     => '_EventEndDate',
							'value'   => current_time( 'mysql' ),
							'compare' => '<',
							'type'    => 'DATETIME',
						),
					),
				),
				'runtime_filter' => function ( $event ) {
					$end_utc = (int) tribe_get_end_date( $event->ID, false, 'U' );
					return $end_utc > 0 && $end_utc < time();
				},
			)
		);
	}

	/**
	 * AJAX endpoint: returns all `tribe_events_cat` terms as a dropdown.
	 * Includes an "Any category" sentinel (`-1`) at the top.
	 *
	 * @return void
	 */
	public function get_event_categories() {

		\Automator()->utilities->ajax_auth_check();

		$options = array(
			array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any category', 'The Events Calendar', 'uncanny-automator' ),
			),
		);

		if ( ! class_exists( 'Tribe__Events__Main' ) || ! taxonomy_exists( 'tribe_events_cat' ) ) {
			$this->json_response_die( $options );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'tribe_events_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'number'     => 999,
			)
		);

		if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = array(
					'value' => (string) $term->term_id,
					'text'  => '' !== $term->name
						? $term->name
						/* translators: %d is a term ID */
						: sprintf( esc_html_x( 'ID: %d (no name)', 'The Events Calendar', 'uncanny-automator' ), $term->term_id ),
				);
			}
		}

		$this->json_response_die( $options );
	}

	// =========================================================================
	// AJAX response template + shared fetcher
	// =========================================================================

	/**
	 * Template method shared by every "list posts of type X as select
	 * options" AJAX handler. Auth-checks, calls the shared fetcher, and
	 * emits the JSON `text/value` shape the recipe builder expects.
	 * Always echoes + dies — never returns.
	 *
	 * @param string                                 $post_type   WP post type to query.
	 * @param string                                 $any_label   Translated label for the `-1` sentinel.
	 * @param array{
	 *     gate?: callable():bool,
	 *     get_args?: array<string,mixed>,
	 *     runtime_filter?: callable(\WP_Post):bool,
	 * }                                             $opts        Optional overrides.
	 *
	 * @return void
	 */
	private function respond_with_post_options( $post_type, $any_label, array $opts = array() ) {

		\Automator()->utilities->ajax_auth_check();

		$options = $this->fetch_post_options( $post_type, $any_label, $opts );

		$this->json_response_die( $options );
	}

	/**
	 * Shared post-option fetcher used by both the legacy AJAX template
	 * (`respond_with_post_options`) and the modern Remote_Data segments.
	 *
	 * Improvements that apply uniformly to every consumer:
	 *
	 *   - For `tribe_events`, routes through `tribe_get_events()` with
	 *     `eventDisplay=custom` to bypass The Events Calendar's
	 *     pre_get_posts upcoming-only date filter; otherwise past events
	 *     never appear in the picker.
	 *   - Returns events / organizers / venues in every visible status
	 *     (publish, future, draft, pending, private), so users can build
	 *     recipes against drafts and scheduled posts.
	 *   - Non-published titles get a "[Status Label]" suffix from the
	 *     post-status object's localized `label`.
	 *
	 * @param string $post_type WP post type to query.
	 * @param string $any_label Translated label for the `-1` sentinel.
	 *                          Empty string skips the prepend entirely.
	 * @param array  $opts      Optional gate / get_args / runtime_filter.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function fetch_post_options( $post_type, $any_label, array $opts = array() ) {

		$options = array();

		if ( '' !== $any_label ) {
			$options[] = $this->any_option( $any_label );
		}

		// Default gate: TEC core must be active.
		$gate = isset( $opts['gate'] ) && is_callable( $opts['gate'] )
			? $opts['gate']
			: static function () {
				return class_exists( 'Tribe__Events__Main' );
			};

		if ( ! $gate() ) {
			return $options;
		}

		$query_args = array_merge(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'posts_per_page' => 999,
				'orderby'        => 'title',
				'order'          => 'ASC',
			),
			isset( $opts['get_args'] ) && is_array( $opts['get_args'] ) ? $opts['get_args'] : array()
		);

		// For `tribe_events`, route through Tribe's own getter with
		// eventDisplay=custom — this bypasses the upcoming-only date
		// filter Tribe injects via pre_get_posts. Other post types use
		// the standard `get_posts()` path.
		if ( 'tribe_events' === $post_type && function_exists( 'tribe_get_events' ) ) {
			$posts = tribe_get_events(
				array_merge( $query_args, array( 'eventDisplay' => 'custom' ) )
			);
		} else {
			$posts = get_posts( $query_args );
		}

		$runtime_filter = isset( $opts['runtime_filter'] ) && is_callable( $opts['runtime_filter'] )
			? $opts['runtime_filter']
			: null;

		foreach ( $posts as $post ) {
			if ( null !== $runtime_filter && ! $runtime_filter( $post ) ) {
				continue;
			}
			$options[] = $this->option_pair( (int) $post->ID, (string) $post->post_title, (string) $post->post_status );
		}

		return $options;
	}

	/**
	 * Build the canonical "Any X" sentinel option row.
	 *
	 * @param string $label Translated user-facing label.
	 *
	 * @return array{value:string,text:string}
	 */
	private function any_option( $label ) {
		return array(
			'value' => '-1',
			'text'  => $label,
		);
	}

	/**
	 * Build a single `text/value` option row, falling back to "ID: %d
	 * (no title)" when the post has no title. Non-published posts get a
	 * "[Status Label]" suffix so the user knows the post isn't live yet.
	 *
	 * @param int    $id     Post ID.
	 * @param string $title  Post title (may be empty).
	 * @param string $status Post status (publish, draft, future, …). Empty
	 *                       string skips the suffix logic entirely.
	 *
	 * @return array{value:string,text:string}
	 */
	private function option_pair( $id, $title, $status = '' ) {

		$text = '' !== $title
			? $title
			/* translators: %d is a post ID */
			: sprintf( esc_html_x( 'ID: %d (no title)', 'The Events Calendar', 'uncanny-automator' ), $id );

		if ( '' === $status || 'publish' === $status ) {
			return array(
				'value' => (string) $id,
				'text'  => $text,
			);
		}

		$status_obj = get_post_status_object( $status );
		$status_lbl = ( $status_obj && ! empty( $status_obj->label ) ) ? $status_obj->label : ucfirst( $status );

		return array(
			'value' => (string) $id,
			/* translators: %1$s is the post title, %2$s is the post-status label (e.g. Draft, Scheduled). */
			'text'  => sprintf( esc_html_x( '%1$s [%2$s]', 'The Events Calendar', 'uncanny-automator' ), $text, $status_lbl ),
		);
	}

	/**
	 * Echo the canonical AJAX JSON envelope and die. Never use
	 * `wp_send_json_success()` — it nests under a `data` key, which the
	 * Automator AJAX field consumer doesn't unwrap.
	 *
	 * @param array $options
	 *
	 * @return void
	 */
	private function json_response_die( array $options ) {
		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $options,
			)
		);
		die();
	}

	// =========================================================================
	// Cross-provider ticket-registration normalization
	// =========================================================================

	/**
	 * Register listeners for all TEC ticket provider hooks and normalize
	 * them into a single internal action with a consistent signature.
	 *
	 * Providers: RSVP, WooCommerce (Event Tickets Plus), PayPal/Tribe
	 * Commerce, and Tickets Commerce.
	 *
	 * @return void
	 */
	public function register_normalized_hooks() {

		if ( true === self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		// Legacy RSVP.
		add_action( 'event_tickets_rsvp_tickets_generated_for_product', array( $this, 'normalize_rsvp_registration' ), 10, 3 );

		// Legacy WooCommerce (via Event Tickets Plus).
		add_action( 'event_tickets_woocommerce_tickets_generated_for_product', array( $this, 'normalize_woo_registration' ), 10, 4 );

		// Legacy PayPal / Tribe Commerce.
		add_action( 'event_tickets_tpp_tickets_generated_for_product', array( $this, 'normalize_tpp_registration' ), 10, 3 );

		// Modern Tickets Commerce.
		add_action( 'tec_tickets_commerce_attendee_after_create', array( $this, 'normalize_tc_registration' ), 10, 4 );

		// Check-in normalization — each ticket provider fires its OWN check-in
		// hook, and only one of them carries the event ID:
		//   - event_tickets_checkin( id, qr, event_id ) — core, Tickets
		//     Commerce, PayPal (the only variant with the event ID).
		//   - rsvp_checkin / eddtickets_checkin / wootickets_checkin( id, qr )
		//     — RSVP and the Event Tickets Plus providers; no event ID.
		// None of these overlap for a single check-in (the ETP providers do
		// not call parent::checkin()), so bridging all four cannot double-fire.
		// Funnel them into one internal action; the trigger resolves the event
		// from the attendee when the hook omits it.
		add_action( 'event_tickets_checkin', array( $this, 'normalize_checkin' ), 10, 3 );
		add_action( 'rsvp_checkin', array( $this, 'normalize_legacy_checkin' ), 10, 2 );
		add_action( 'eddtickets_checkin', array( $this, 'normalize_legacy_checkin' ), 10, 2 );
		add_action( 'wootickets_checkin', array( $this, 'normalize_legacy_checkin' ), 10, 2 );

		// Uncheck-in: the Event Tickets Plus providers (EDD, Woo) route their
		// uncheckin through parent::uncheckin(), which already fires
		// event_tickets_uncheckin — so bridging only that plus RSVP's own hook
		// covers every provider. Bridging eddtickets_uncheckin too would
		// double-fire for EDD.
		add_action( 'event_tickets_uncheckin', array( $this, 'normalize_uncheckin' ), 10, 1 );
		add_action( 'rsvp_uncheckin', array( $this, 'normalize_uncheckin' ), 10, 1 );
	}

	/**
	 * Normalize a generic provider check-in into the internal action.
	 *
	 * @param int       $attendee_id The attendee post ID.
	 * @param bool|null $qr          Whether the check-in came from a QR scan.
	 * @param int|null  $event_id    The event post ID (optional — null on the
	 *                               bulk Attendees-table, Tickets Commerce and
	 *                               QR paths; the trigger resolves it from the
	 *                               attendee when absent).
	 *
	 * @return void
	 */
	public function normalize_checkin( $attendee_id, $qr = null, $event_id = null ) {
		do_action( self::CHECKIN_ACTION, absint( $attendee_id ), $qr, null === $event_id ? null : absint( $event_id ) );
	}

	/**
	 * Normalize a legacy per-provider check-in (RSVP, EDD, Woo) into the
	 * internal action. These hooks carry no event ID, so the trigger resolves
	 * it from the attendee.
	 *
	 * @param int       $attendee_id The attendee post ID.
	 * @param bool|null $qr          Whether the check-in came from a QR scan.
	 *
	 * @return void
	 */
	public function normalize_legacy_checkin( $attendee_id, $qr = null ) {
		do_action( self::CHECKIN_ACTION, absint( $attendee_id ), $qr, null );
	}

	/**
	 * Normalize a check-in reversal (generic or RSVP) into the internal action.
	 *
	 * @param int $attendee_id The attendee post ID.
	 *
	 * @return void
	 */
	public function normalize_uncheckin( $attendee_id ) {
		do_action( self::UNCHECKIN_ACTION, absint( $attendee_id ) );
	}

	/**
	 * Normalize RSVP ticket registration.
	 *
	 * @param int    $product_id RSVP ticket post ID.
	 * @param string $order_id   ID (hash) of the RSVP order.
	 * @param int    $qty        Quantity ordered.
	 *
	 * @return void
	 */
	public function normalize_rsvp_registration( $product_id, $order_id, $qty ) {

		unset( $qty );

		$event = tribe_events_get_ticket_event( $product_id );

		if ( ! $event instanceof \WP_Post ) {
			return;
		}

		do_action( self::USER_REGISTERED_ACTION, $event->ID, $product_id, $order_id, get_current_user_id() );
	}

	/**
	 * Normalize WooCommerce ticket registration.
	 *
	 * @param int $product_id WooCommerce ticket post ID.
	 * @param int $order_id   ID of the WooCommerce order.
	 * @param int $quantity   Quantity ordered.
	 * @param int $post_id    ID of the event.
	 *
	 * @return void
	 */
	public function normalize_woo_registration( $product_id, $order_id, $quantity, $post_id ) {

		unset( $quantity );

		$user_id = get_current_user_id();

		// Fallback: retrieve customer from WooCommerce order (e.g. background/cron processing).
		if ( 0 === $user_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$user_id = absint( $order->get_customer_id() );
			}
		}

		do_action( self::USER_REGISTERED_ACTION, absint( $post_id ), $product_id, $order_id, $user_id );
	}

	/**
	 * Normalize PayPal / Tribe Commerce ticket registration.
	 *
	 * @param int    $product_id PayPal ticket post ID.
	 * @param string $order_id   ID of the PayPal order.
	 * @param int    $qty        Quantity ordered.
	 *
	 * @return void
	 */
	public function normalize_tpp_registration( $product_id, $order_id, $qty ) {

		unset( $qty );

		$event = tribe_events_get_ticket_event( $product_id );

		if ( ! $event instanceof \WP_Post ) {
			return;
		}

		do_action( self::USER_REGISTERED_ACTION, $event->ID, $product_id, $order_id, get_current_user_id() );
	}

	/**
	 * Normalize Tickets Commerce registration.
	 *
	 * @param \WP_Post $attendee Attendee post object.
	 * @param \WP_Post $order    Order post object.
	 * @param object   $ticket   Ticket object (Tribe__Tickets__Ticket_Object).
	 * @param array    $args     Extra arguments used to populate attendee data.
	 *
	 * @return void
	 */
	public function normalize_tc_registration( $attendee, $order, $ticket, $args ) {

		unset( $args );

		if ( ! $attendee instanceof \WP_Post ) {
			return;
		}

		// Resolve event ID from ticket first (most reliable at creation time), then attendee.
		$event_id = is_callable( array( $ticket, 'get_event_id' ) ) ? absint( $ticket->get_event_id() ) : 0;

		if ( 0 === $event_id && ! empty( $attendee->event_id ) ) {
			$event_id = absint( $attendee->event_id );
		}

		$product_id = is_object( $ticket ) && ! empty( $ticket->ID ) ? absint( $ticket->ID ) : 0;
		$order_id   = $order instanceof \WP_Post ? $order->ID : 0;

		// Resolve user from order purchaser data, then fallback to current user.
		$user_id = ! empty( $order->purchaser['user_id'] ) ? absint( $order->purchaser['user_id'] ) : 0;

		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		do_action( self::USER_REGISTERED_ACTION, $event_id, $product_id, $order_id, $user_id );
	}

	// =========================================================================
	// Field option utilities
	// =========================================================================

	/**
	 * Convert the framework's legacy `less_or_greater_than()` option
	 * shape into the modern `text/value` pair list expected by select
	 * fields. Shared by every numeric-comparison condition and loop
	 * filter so the conversion lives in exactly one place.
	 *
	 * Returns: `[ [ 'text' => 'Equal to', 'value' => '=' ], ... ]`
	 *
	 * **Static** so callers don't need to instantiate the helper just
	 * to read a stateless option list. Conditions and loop filters do
	 * not have access to `$this->get_item_helpers()` (different framework
	 * base classes), so the static accessor is the cleanest read path.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function comparison_options() {

		$legacy = \Automator()->helpers->recipe->field->less_or_greater_than();
		$out    = array();

		if ( ! is_array( $legacy ) || empty( $legacy['options'] ) ) {
			return $out;
		}

		foreach ( $legacy['options'] as $value => $label ) {
			$out[] = array(
				'text'  => $label,
				'value' => $value,
			);
		}

		return $out;
	}
}

// Preserve the historical FQN `\Uncanny_Automator\Event_Tickets_Helpers`
// for the singleton chain and any addon that referenced the old class
// name directly. The aliased class behaves identically to the modern
// one — the alias is a name, not a separate class.
class_alias( The_Events_Calendar_Helpers::class, 'Uncanny_Automator\\Event_Tickets_Helpers' );
