<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

/**
 * Class ET_CREATE_RSVP_ATTENDEE
 *
 * Action: Create an RSVP attendee for a ticket on an event.
 *
 * A legacy RSVP ticket goes through
 * Tribe__Tickets__RSVP::create_attendee_for_ticket(). A Tickets Commerce
 * RSVP (Event Tickets 5.30+) goes through the helper's
 * create_tickets_commerce_rsvp(), which follows Event Tickets' own RSVP
 * steps (EC_Rsvp).
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
	 *
	 * @throws Action_Error_Exception When an input is missing or invalid, or Event Tickets refuses the RSVP.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$event_id  = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$ticket_id = absint( $parsed['EC_RSVP_TICKET_ID'] ?? 0 );
		$full_name = sanitize_text_field( $parsed['EC_RSVP_ATTENDEE_NAME'] ?? '' );
		$email     = sanitize_email( $parsed['EC_RSVP_ATTENDEE_EMAIL'] ?? '' );
		$status    = (string) ( $parsed['EC_RSVP_ATTENDEE_STATUS'] ?? 'going' );

		if ( 0 === $event_id ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'Event ID is required.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		if ( 0 === $ticket_id ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'Ticket ID is required.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		if ( '' === $full_name ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'Attendee name is required.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		if ( '' === $email || ! is_email( $email ) ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'A valid attendee email is required.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) || ! class_exists( '\Tribe__Tickets__RSVP' ) ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Event Tickets is not active.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		// Reject ticket/event mismatches up front, so a stale dropdown or a
		// pasted ID never creates an attendee on another event.
		if ( function_exists( 'tribe_events_get_ticket_event' ) ) {
			$ticket_event    = tribe_events_get_ticket_event( $ticket_id );
			$ticket_event_id = ( $ticket_event instanceof \WP_Post ) ? (int) $ticket_event->ID : 0;
			if ( 0 === $ticket_event_id || $ticket_event_id !== $event_id ) {
				throw Action_Error_Exception::validation_failed(
					sprintf(
						/* translators: %1$d is the ticket ID, %2$d is the user-selected event ID */
						esc_html_x( 'Ticket %1$d does not belong to event %2$d.', 'The Events Calendar', 'uncanny-automator' ),
						absint( $ticket_id ),
						absint( $event_id )
					)
				);
			}
		}

		$ticket_object = \Tribe__Tickets__Tickets::load_ticket_object( $ticket_id );

		if ( ! $ticket_object instanceof \Tribe__Tickets__Ticket_Object ) {
			/* translators: %d ticket ID */
			throw Action_Error_Exception::not_found( sprintf( esc_html_x( 'Could not load ticket object for ID %d.', 'The Events Calendar', 'uncanny-automator' ), absint( $ticket_id ) ) );
		}

		if ( ! $this->item_helpers->is_rsvp_ticket( $ticket_object ) ) {
			/* translators: %d ticket ID */
			throw Action_Error_Exception::validation_failed( sprintf( esc_html_x( 'Ticket %d is not an RSVP ticket.', 'The Events Calendar', 'uncanny-automator' ), absint( $ticket_id ) ) );
		}

		$attendee_id = $this->item_helpers->is_tickets_commerce_rsvp( $ticket_object )
			? $this->create_tickets_commerce_attendee( $ticket_object, $full_name, $email, 'not_going' !== $status, absint( $user_id ) )
			: $this->create_legacy_attendee( $ticket_object, $full_name, $email, $status, absint( $user_id ) );

		$this->hydrate_tokens(
			array(
				'EC_CREATED_ATTENDEE_ID'    => $attendee_id,
				'EC_CREATED_ATTENDEE_NAME'  => $full_name,
				'EC_CREATED_ATTENDEE_EMAIL' => $email,
			)
		);

		return true;
	}

	/**
	 * Create the attendee on a Tickets Commerce RSVP (Event Tickets 5.30+).
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket    The RSVP ticket.
	 * @param string                         $full_name The attendee's name.
	 * @param string                         $email     The attendee's email.
	 * @param bool                           $going     Going or Not going.
	 * @param int                            $user_id   The recipe's user.
	 *
	 * @return int The attendee ID.
	 *
	 * @throws Action_Error_Exception When Event Tickets refuses the RSVP.
	 */
	private function create_tickets_commerce_attendee( \Tribe__Tickets__Ticket_Object $ticket, $full_name, $email, $going, $user_id ) {

		$created = $this->item_helpers->create_tickets_commerce_rsvp(
			$ticket,
			array(
				'full_name' => $full_name,
				'email'     => $email,
				'user_id'   => $user_id,
				'quantity'  => 1,
				'going'     => $going,
			)
		);

		if ( is_wp_error( $created ) ) {
			throw Action_Error_Exception::execution_failed( esc_html( $created->get_error_message() ) );
		}

		return (int) reset( $created['attendee_ids'] );
	}

	/**
	 * Create the attendee on a legacy RSVP ticket.
	 *
	 * @param \Tribe__Tickets__Ticket_Object $ticket    The RSVP ticket.
	 * @param string                         $full_name The attendee's name.
	 * @param string                         $email     The attendee's email.
	 * @param string                         $status    `going` or `not_going`.
	 * @param int                            $user_id   The recipe's user.
	 *
	 * @return int The attendee ID.
	 *
	 * @throws Action_Error_Exception When Event Tickets refuses the RSVP.
	 */
	private function create_legacy_attendee( \Tribe__Tickets__Ticket_Object $ticket, $full_name, $email, $status, $user_id ) {

		$rsvp = \Tribe__Tickets__RSVP::get_instance();

		if ( ! $rsvp instanceof \Tribe__Tickets__RSVP ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'Could not resolve the RSVP provider.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		try {
			$attendee_object = $rsvp->create_attendee_for_ticket(
				$ticket,
				array(
					'full_name'    => $full_name,
					'email'        => $email,
					// Event Tickets reads yes / no; any other answer it takes as Going.
					'order_status' => 'not_going' === $status ? 'no' : 'yes',
					'user_id'      => $user_id,
				)
			);
		} catch ( \Exception $e ) {
			throw Action_Error_Exception::execution_failed( esc_html( $e->getMessage() ) );
		}

		$attendee_id = 0;

		if ( is_object( $attendee_object ) && ! empty( $attendee_object->ID ) ) {
			$attendee_id = absint( $attendee_object->ID );
		} elseif ( is_int( $attendee_object ) ) {
			$attendee_id = absint( $attendee_object );
		}

		if ( 0 === $attendee_id ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'RSVP attendee was not created.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		return $attendee_id;
	}
}
