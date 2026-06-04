<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class ET_TICKET_DELETED
 *
 * Trigger: A ticket is removed from an event. Gated on Event Tickets.
 *
 * Hook signature: tribe_tickets_ticket_deleted( $post_id ) where
 * `$post_id` is the EVENT post ID (NOT the ticket ID). Verified at
 * event-tickets/src/Tribe/Metabox.php:599 and
 * event-tickets/src/Tribe/Editor/REST/V1/Endpoints/Single_Ticket.php:81
 * — both paths pass the parent event ID and no ticket identifier.
 *
 * Drift from EXPANSION-PLAN.md: the plan assumed `$post_id` was the
 * ticket ID and proposed a ticket-name token. That is incorrect — the
 * hook does not carry any ticket identifier. This trigger therefore
 * uses only an event selector and event tokens; the ticket is already
 * gone and its identity is not recoverable at this hook point.
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class ET_TICKET_DELETED extends \Uncanny_Automator\Recipe\Trigger {

	use Has_Dependency;

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ET_TICKET_DELETED', 'EC' )
			->trigger_meta( 'ECEVENTS' )
			->trigger_type( 'anonymous' )
			->hook( 'tribe_tickets_ticket_deleted', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s is the event field token */
		$this->set_sentence( sprintf( esc_html_x( '{{A ticket}} is removed from {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{A ticket}} is removed from {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
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

		$event_id = absint( $hook_args[0] );

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
			$this->item_helpers->tokens()->event_tokens( $this->get_trigger_meta() )
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

		$event_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;

		return $this->item_helpers->tokens()->hydrate_event_tokens( $event_id, $this->get_trigger_meta() );
	}
}
