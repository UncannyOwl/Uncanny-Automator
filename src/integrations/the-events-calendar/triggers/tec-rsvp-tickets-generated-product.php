<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class ET_RSVP_TICKETS_GENERATED_PRODUCT
 *
 * Trigger: RSVP tickets are generated for a product (ticket) on an
 * event, optionally filtered by going / not-going status. Gated on
 * Event Tickets.
 *
 * Hook signature: event_tickets_rsvp_tickets_generated_for_product(
 *   $product_id, $order_id, $qty, $attendee_ids
 * ) — verified at event-tickets/src/Tribe/RSVP.php:2807. Note an older
 * 3-arg fire path exists at Repositories/Attendee/RSVP.php:191 — we
 * treat missing $attendee_ids as empty array.
 *
 * Status is NOT a hook arg. We resolve it from the first attendee's
 * `_tribe_rsvp_status` post-meta (verified as the canonical key at
 * event-tickets/src/Tribe/RSVP.php:106 → const ATTENDEE_RSVP_KEY).
 * Values: 'yes' (going) / 'no' (not going).
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class ET_RSVP_TICKETS_GENERATED_PRODUCT extends \Uncanny_Automator\Recipe\Trigger {

	use Has_Dependency;

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ET_RSVP_TICKETS_GENERATED_PRODUCT', 'EC' )
			->trigger_meta( 'ECEVENTS' )
			->trigger_type( 'anonymous' )
			->hook( 'event_tickets_rsvp_tickets_generated_for_product', 10, 4 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s event field, %2$s ticket field, %3$s status field */
		$this->set_sentence( sprintf( esc_html_x( 'RSVP tickets are generated for {{a ticket type:%2$s}} on {{an event:%1$s}} with {{a status:%3$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_trigger_meta(), 'ECTICKET:' . $this->get_trigger_meta(), 'ECRSVPSTATUS:' . $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'RSVP tickets are generated for {{a ticket type}} on {{an event}} with {{a status}}', 'The Events Calendar', 'uncanny-automator' ) );
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
			array(
				'input_type'            => 'select',
				'option_code'           => 'ECTICKET',
				'label'                 => esc_html_x( 'Ticket type', 'The Events Calendar', 'uncanny-automator' ),
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'tickets_for_event', array( $this->get_trigger_meta() ) ),
			),
			array(
				'input_type'  => 'select',
				'option_code' => 'ECRSVPSTATUS',
				'label'       => esc_html_x( 'Status', 'The Events Calendar', 'uncanny-automator' ),
				'required'    => true,
				'options'     => array(
					array(
						'text'  => esc_html_x( 'Any', 'The Events Calendar', 'uncanny-automator' ),
						'value' => '-1',
					),
					array(
						'text'  => esc_html_x( 'Going', 'The Events Calendar', 'uncanny-automator' ),
						'value' => 'going',
					),
					array(
						'text'  => esc_html_x( 'Not going', 'The Events Calendar', 'uncanny-automator' ),
						'value' => 'not_going',
					),
				),
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

		$product_id   = absint( $hook_args[0] );
		$attendee_ids = isset( $hook_args[3] ) && is_array( $hook_args[3] ) ? $hook_args[3] : array();

		if ( 0 === $product_id ) {
			return false;
		}

		$event_id = 0;

		if ( function_exists( 'tribe_events_get_ticket_event' ) ) {
			$event = tribe_events_get_ticket_event( $product_id );
			if ( $event instanceof \WP_Post ) {
				$event_id = (int) $event->ID;
			}
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

		$selected_ticket = isset( $trigger['meta']['ECTICKET'] ) ? (string) $trigger['meta']['ECTICKET'] : '-1';

		if ( '-1' !== $selected_ticket && absint( $selected_ticket ) !== $product_id ) {
			return false;
		}

		$selected_status = isset( $trigger['meta']['ECRSVPSTATUS'] ) ? (string) $trigger['meta']['ECRSVPSTATUS'] : '-1';

		if ( '-1' !== $selected_status ) {

			$resolved_status = $this->resolve_status( $attendee_ids );

			if ( $resolved_status !== $selected_status ) {
				return false;
			}
		}

		// Resolve acting user from the first attendee when possible.
		$first_attendee = ! empty( $attendee_ids ) ? absint( reset( $attendee_ids ) ) : 0;

		if ( $first_attendee > 0 ) {
			$user_id = $this->resolve_user_id( $first_attendee );
			if ( $user_id > 0 ) {
				$this->set_user_id( $user_id );
			}
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

		$event_id  = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';
		$ticket_id = $trigger['meta']['ECTICKET'] ?? '';

		return array_merge(
			$tokens,
			$this->item_helpers->tokens()->event_tokens( $this->get_trigger_meta() ),
			$this->item_helpers->tokens()->ticket_tokens( 'ECTICKET' ),
			// Per-ticket IAC custom registration fields (+ basic holder
			// name/email) for the selected event, scoped to the chosen ticket
			// type when one is pinned. Requires Event Tickets Plus; degrades to
			// the basic holder tokens otherwise.
			$this->item_helpers->tokens()->dynamic_attendee_meta_tokens( $event_id, $ticket_id ),
			array(
				array(
					'tokenId'   => 'ECRSVP_ORDER_ID',
					'tokenName' => esc_html_x( 'RSVP order ID', 'The Events Calendar', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ECRSVP_STATUS',
					'tokenName' => esc_html_x( 'RSVP status', 'The Events Calendar', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ECRSVP_QTY',
					'tokenName' => esc_html_x( 'Quantity', 'The Events Calendar', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
				array(
					'tokenId'   => 'ECRSVP_ATTENDEE_IDS',
					'tokenName' => esc_html_x( 'Attendee IDs', 'The Events Calendar', 'uncanny-automator' ),
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

		$product_id   = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$order_id     = isset( $hook_args[1] ) ? (string) $hook_args[1] : '';
		$qty          = isset( $hook_args[2] ) ? absint( $hook_args[2] ) : 0;
		$attendee_ids = isset( $hook_args[3] ) && is_array( $hook_args[3] ) ? $hook_args[3] : array();

		// Custom registration fields are per-attendee; the first attendee in
		// the order is the representative one (mirrors resolve_status()).
		$first_attendee = ! empty( $attendee_ids ) ? absint( reset( $attendee_ids ) ) : 0;

		$event_id = 0;
		if ( function_exists( 'tribe_events_get_ticket_event' ) ) {
			$event = tribe_events_get_ticket_event( $product_id );
			if ( $event instanceof \WP_Post ) {
				$event_id = (int) $event->ID;
			}
		}

		// Resolve the auto-registered ECRSVPSTATUS field-token to its
		// human-readable label. Without it the framework falls back to
		// get_the_title( $stored_meta ), rendering garbage.
		$status_labels   = array(
			'-1'        => esc_html_x( 'Any', 'The Events Calendar', 'uncanny-automator' ),
			'going'     => esc_html_x( 'Going', 'The Events Calendar', 'uncanny-automator' ),
			'not_going' => esc_html_x( 'Not going', 'The Events Calendar', 'uncanny-automator' ),
		);
		$selected_status = (string) ( $trigger['meta']['ECRSVPSTATUS'] ?? '-1' );

		return array_merge(
			$this->item_helpers->tokens()->hydrate_event_tokens( $event_id, $this->get_trigger_meta() ),
			$this->item_helpers->tokens()->hydrate_ticket_tokens( $product_id, 'ECTICKET' ),
			$this->item_helpers->tokens()->hydrate_dynamic_attendee_meta_tokens( $first_attendee ),
			array(
				'ECRSVPSTATUS'        => $status_labels[ $selected_status ] ?? $selected_status,
				'ECRSVP_ORDER_ID'     => $order_id,
				'ECRSVP_STATUS'       => $this->resolve_status( $attendee_ids ),
				'ECRSVP_QTY'          => (string) $qty,
				'ECRSVP_ATTENDEE_IDS' => implode( ',', array_map( 'absint', $attendee_ids ) ),
			)
		);
	}

	/**
	 * Resolve normalized status ('going' / 'not_going' / '') from a list
	 * of attendee post IDs. Reads `_tribe_rsvp_status` directly (key
	 * confirmed at event-tickets/src/Tribe/RSVP.php:106).
	 *
	 * **Primary-attendee semantics**: only the FIRST attendee in the
	 * list is inspected. If a single RSVP submission generates multiple
	 * attendees with mixed statuses (rare — would require a guest form
	 * that lets one submission RSVP "going" for one ticket and "not
	 * going" for another), the status sub-select effectively means
	 * "the primary attendee is going / not going". Aggregating to
	 * all-going / all-not-going / mixed would be more correct but is
	 * deferred unless a customer reports the case.
	 *
	 * @param array<int,int> $attendee_ids
	 *
	 * @return string
	 */
	private function resolve_status( $attendee_ids ) {

		if ( empty( $attendee_ids ) ) {
			return '';
		}

		$first = absint( reset( $attendee_ids ) );

		if ( 0 === $first ) {
			return '';
		}

		$raw = (string) get_post_meta( $first, '_tribe_rsvp_status', true );

		if ( 'yes' === $raw ) {
			return 'going';
		}

		if ( 'no' === $raw ) {
			return 'not_going';
		}

		return '';
	}

	/**
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
