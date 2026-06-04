<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class ET_ATTENDEE_CHECKED_IN
 *
 * Trigger: An attendee checks in to an event. Gated on Event Tickets
 * being active via the Has_Dependency trait.
 *
 * Listens to the helper's normalized internal action
 * `automator_event_tickets_checkin( $attendee_id, $qr, $event_id )` rather
 * than the raw provider hooks. RSVP fires its own `rsvp_checkin` while every
 * other provider fires `event_tickets_checkin`; the helper funnels both into
 * the internal action so this single trigger fires for all providers.
 *
 * $event_id is OPTIONAL — the bulk Attendees-table action, Tickets Commerce,
 * QR scans, and every RSVP check-in supply no event, so we resolve it from
 * the attendee when absent.
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class ET_ATTENDEE_CHECKED_IN extends \Uncanny_Automator\Recipe\Trigger {

	use Has_Dependency;

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ET_ATTENDEE_CHECKED_IN', 'EC' )
			->trigger_meta( 'ECEVENTS' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_event_tickets_checkin', 10, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s is the event field token */
		$this->set_sentence( sprintf( esc_html_x( 'An attendee is checked in to {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'An attendee is checked in to {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return bool
	 */
	public function requirements_met() {
		return $this->et_active();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Event', 'The Events Calendar', 'uncanny-automator' ),
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'events' ),
			),
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		$attendee_id = absint( $hook_args[0] );

		if ( 0 === $attendee_id ) {
			return false;
		}

		// $event_id is an optional hook arg (null on the bulk/Commerce/QR
		// paths). Fall back to resolving it from the attendee.
		$event_id = isset( $hook_args[2] ) ? absint( $hook_args[2] ) : 0;

		if ( 0 === $event_id ) {
			$event_id = $this->resolve_event_id( $attendee_id );
		}

		if ( 0 === $event_id ) {
			return false;
		}

		$selected_event = isset( $trigger['meta'][ $this->get_trigger_meta() ] )
			? (string) $trigger['meta'][ $this->get_trigger_meta() ]
			: '';

		if ( '' === $selected_event ) {
			return false;
		}

		if ( '-1' !== $selected_event && absint( $selected_event ) !== $event_id ) {
			return false;
		}

		$user_id = $this->resolve_user_id( $attendee_id );

		if ( $user_id > 0 ) {
			$this->set_user_id( $user_id );
		}

		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$tc       = $this->item_helpers->tokens();
		$event_id = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		return array_merge(
			$tokens,
			$tc->event_tokens( $this->get_trigger_meta() ),
			// Per-ticket IAC custom registration fields (+ basic holder
			// name/email) for the selected event, when ET+ is present.
			$tc->dynamic_attendee_meta_tokens( $event_id ),
			array(
				array(
					'tokenId'   => 'EC_PROVIDER',
					'tokenName' => esc_html_x( 'Ticket provider', 'The Events Calendar', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array<string,string>
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		unset( $trigger );

		$attendee_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$event_id    = isset( $hook_args[2] ) ? absint( $hook_args[2] ) : 0;

		if ( 0 === $event_id ) {
			$event_id = $this->resolve_event_id( $attendee_id );
		}

		return array_merge(
			$this->item_helpers->tokens()->hydrate_event_tokens( $event_id, $this->get_trigger_meta() ),
			$this->item_helpers->tokens()->hydrate_dynamic_attendee_meta_tokens( $attendee_id ),
			array(
				'EC_PROVIDER' => EC_Provider_Constants::from_attendee( $attendee_id ),
			)
		);
	}

	/**
	 * Resolve the event ID that an attendee belongs to.
	 *
	 * @param int $attendee_id
	 *
	 * @return int
	 */
	private function resolve_event_id( $attendee_id ) {
		return EC_Attendee_Resolver::event_id_from_attendee( $attendee_id );
	}

	/**
	 * Resolve a WP user ID from an attendee via Tribe's get_attendee API.
	 *
	 * @param int $attendee_id
	 *
	 * @return int
	 */
	private function resolve_user_id( $attendee_id ) {

		$attendee_id = absint( $attendee_id );

		if ( 0 === $attendee_id || ! function_exists( 'tribe_tickets_get_ticket_provider' ) ) {
			return 0;
		}

		// Resolve the provider instance via the ET template tag, then call
		// its (non-static) get_attendee(). Calling get_attendee() statically
		// fatals on current Event Tickets.
		$provider = tribe_tickets_get_ticket_provider( $attendee_id );

		if ( ! $provider instanceof \Tribe__Tickets__Tickets ) {
			return 0;
		}

		$attendee = $provider->get_attendee( $attendee_id );

		if ( is_array( $attendee ) && ! empty( $attendee['user_id'] ) ) {
			return absint( $attendee['user_id'] );
		}

		return 0;
	}
}
