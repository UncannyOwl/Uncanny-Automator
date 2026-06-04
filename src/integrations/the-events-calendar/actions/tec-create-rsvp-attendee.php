<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class ET_CREATE_RSVP_ATTENDEE
 *
 * Action: Create an RSVP attendee for a ticket on an event.
 *
 * API: Tribe__Tickets__RSVP::create_attendee_for_ticket(
 *        Tribe__Tickets__Ticket_Object $ticket,
 *        array $attendee_data
 *      ) (verified at event-tickets/src/Tribe/RSVP.php:845).
 *
 * The API takes a Tribe__Tickets__Ticket_Object, NOT a ticket ID. We
 * resolve via Tribe__Tickets__Tickets::load_ticket_object() (verified at
 * event-tickets/src/Tribe/Tickets.php:625).
 *
 * Required attendee_data keys: full_name, email. Optional: order_status,
 * user_id, optout, order_id.
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class ET_CREATE_RSVP_ATTENDEE extends \Uncanny_Automator\Recipe\Action {

	use Has_Dependency;

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'EC' );
		$this->set_action_code( 'ET_CREATE_RSVP_ATTENDEE' );
		$this->set_action_meta( 'EC_RSVP_EVENT_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_action_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s event field. */
		$this->set_sentence( sprintf( esc_html_x( 'Create an RSVP attendee for {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create an RSVP attendee for {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return bool
	 */
	public function requirements_met() {
		return $this->et_active();
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		return array(
			'EC_CREATED_ATTENDEE_ID'    => array(
				'name' => esc_html_x( 'Attendee ID', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EC_CREATED_ATTENDEE_NAME'  => array(
				'name' => esc_html_x( 'Attendee name', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EC_CREATED_ATTENDEE_EMAIL' => array(
				'name' => esc_html_x( 'Attendee email', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'email',
			),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Event', 'The Events Calendar', 'uncanny-automator' ),
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'events_strict' ),
			),
			array(
				'input_type'            => 'select',
				'option_code'           => 'EC_RSVP_TICKET_ID',
				'label'                 => esc_html_x( 'Ticket type', 'The Events Calendar', 'uncanny-automator' ),
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => true,
				'remote_data'           => $this->item_helpers->remote_data_parent_config( 'tickets_for_event_strict', array( $this->get_action_meta() ) ),
			),
			array(
				'option_code'     => 'EC_RSVP_ATTENDEE_NAME',
				'label'           => esc_html_x( 'Attendee name', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'supports_tokens' => true,
			),
			array(
				'option_code'     => 'EC_RSVP_ATTENDEE_EMAIL',
				'label'           => esc_html_x( 'Attendee email', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'      => 'email',
				'required'        => true,
				'supports_tokens' => true,
			),
			array(
				'input_type'  => 'select',
				'option_code' => 'EC_RSVP_ATTENDEE_STATUS',
				'label'       => esc_html_x( 'RSVP status', 'The Events Calendar', 'uncanny-automator' ),
				'required'    => true,
				'options'     => array(
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
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$event_id  = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$ticket_id = absint( $parsed['EC_RSVP_TICKET_ID'] ?? 0 );
		$full_name = sanitize_text_field( $parsed['EC_RSVP_ATTENDEE_NAME'] ?? '' );
		$email     = sanitize_email( $parsed['EC_RSVP_ATTENDEE_EMAIL'] ?? '' );
		$status    = (string) ( $parsed['EC_RSVP_ATTENDEE_STATUS'] ?? 'going' );

		if ( 0 === $event_id ) {
			$this->add_log_error( esc_html_x( 'Event ID is required.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( 0 === $ticket_id ) {
			$this->add_log_error( esc_html_x( 'Ticket ID is required.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( '' === $full_name ) {
			$this->add_log_error( esc_html_x( 'Attendee name is required.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( '' === $email || ! is_email( $email ) ) {
			$this->add_log_error( esc_html_x( 'A valid attendee email is required.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) || ! class_exists( '\Tribe__Tickets__RSVP' ) ) {
			$this->add_log_error( esc_html_x( 'Event Tickets is not active.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		// Reject ticket/event mismatches up front. This catches stale
		// AJAX dropdowns or recipe authors pasting IDs from different
		// events — without this check we'd silently create an attendee
		// on the ticket's actual event instead of the user-selected one.
		if ( function_exists( 'tribe_events_get_ticket_event' ) ) {
			$ticket_event = tribe_events_get_ticket_event( $ticket_id );
			$ticket_event_id = ( $ticket_event instanceof \WP_Post ) ? (int) $ticket_event->ID : 0;
			if ( 0 === $ticket_event_id || $ticket_event_id !== $event_id ) {
				$this->add_log_error( sprintf(
					/* translators: %1$d is the ticket ID, %2$d is the user-selected event ID */
					esc_html_x( 'Ticket %1$d does not belong to event %2$d.', 'The Events Calendar', 'uncanny-automator' ),
					$ticket_id,
					$event_id
				) );
				return false;
			}
		}

		$ticket_object = \Tribe__Tickets__Tickets::load_ticket_object( $ticket_id );

		if ( ! $ticket_object instanceof \Tribe__Tickets__Ticket_Object ) {
			/* translators: %d ticket ID */
			$this->add_log_error( sprintf( esc_html_x( 'Could not load ticket object for ID %d.', 'The Events Calendar', 'uncanny-automator' ), $ticket_id ) );
			return false;
		}

		$rsvp = \Tribe__Tickets__RSVP::get_instance();

		if ( ! $rsvp instanceof \Tribe__Tickets__RSVP ) {
			$this->add_log_error( esc_html_x( 'Could not resolve the RSVP provider.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		try {
			$attendee_object = $rsvp->create_attendee_for_ticket(
				$ticket_object,
				array(
					'full_name'    => $full_name,
					'email'        => $email,
					'order_status' => $status,
					'user_id'      => absint( $user_id ),
				)
			);
		} catch ( \Exception $e ) {
			$this->add_log_error( $e->getMessage() );
			return false;
		}

		$attendee_id = 0;

		if ( is_object( $attendee_object ) && ! empty( $attendee_object->ID ) ) {
			$attendee_id = absint( $attendee_object->ID );
		} elseif ( is_int( $attendee_object ) ) {
			$attendee_id = absint( $attendee_object );
		}

		if ( 0 === $attendee_id ) {
			$this->add_log_error( esc_html_x( 'RSVP attendee was not created.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'EC_CREATED_ATTENDEE_ID'    => $attendee_id,
				'EC_CREATED_ATTENDEE_NAME'  => $full_name,
				'EC_CREATED_ATTENDEE_EMAIL' => $email,
			)
		);

		return true;
	}
}
