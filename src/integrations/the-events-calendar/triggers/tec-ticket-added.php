<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class ET_TICKET_ADDED
 *
 * Trigger: A ticket is added to an event. Gated on Event Tickets.
 *
 * Hook signature: tribe_tickets_ticket_added( $post_id, $ticket, $ticket_data )
 * — verified at event-tickets/src/Tribe/Metabox.php:388 (int, int, array)
 * and event-tickets/src/Tribe/Editor/REST/V1/Endpoints/Single_Ticket.php:337
 * (int, Tribe__Tickets__Ticket_Object, array). The first arg is always
 * the EVENT post ID; the second arg may be an int ticket ID or an
 * object — we normalize to ID.
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class ET_TICKET_ADDED extends \Uncanny_Automator\Recipe\Trigger {

	use Has_Dependency;

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ET_TICKET_ADDED', 'EC' )
			->trigger_meta( 'ECEVENTS' )
			->trigger_type( 'anonymous' )
			->hook( 'tribe_tickets_ticket_added', 10, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s event field, %2$s ticket field */
		$this->set_sentence( sprintf( esc_html_x( '{{A ticket:%2$s}} is added to {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_trigger_meta(), 'ECTICKET:' . $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{A ticket}} is added to {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
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
				'label'                 => esc_html_x( 'Ticket', 'The Events Calendar', 'uncanny-automator' ),
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'tickets_for_event', array( $this->get_trigger_meta() ) ),
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

		if ( ! isset( $hook_args[0], $hook_args[1] ) ) {
			return false;
		}

		$event_id  = absint( $hook_args[0] );
		$ticket_id = self::normalize_ticket_id( $hook_args[1] );

		if ( 0 === $event_id || 0 === $ticket_id ) {
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

		if ( '-1' !== $selected_ticket && absint( $selected_ticket ) !== $ticket_id ) {
			return false;
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
		return array_merge(
			$tokens,
			$this->item_helpers->tokens()->event_tokens( $this->get_trigger_meta() ),
			$this->item_helpers->tokens()->ticket_tokens( 'ECTICKET' )
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

		$event_id  = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$ticket_id = isset( $hook_args[1] ) ? self::normalize_ticket_id( $hook_args[1] ) : 0;

		return array_merge(
			$this->item_helpers->tokens()->hydrate_event_tokens( $event_id, $this->get_trigger_meta() ),
			$this->item_helpers->tokens()->hydrate_ticket_tokens( $ticket_id, 'ECTICKET' )
		);
	}

	/**
	 * Normalize a ticket identifier — accepts an int ID or a
	 * Tribe__Tickets__Ticket_Object and returns the ticket post ID.
	 *
	 * @param mixed $ticket
	 *
	 * @return int
	 */
	public static function normalize_ticket_id( $ticket ) {

		if ( is_numeric( $ticket ) ) {
			return absint( $ticket );
		}

		if ( is_object( $ticket ) && ! empty( $ticket->ID ) ) {
			return absint( $ticket->ID );
		}

		return 0;
	}
}
