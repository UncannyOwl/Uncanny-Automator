<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class ET_CHECK_IN_ATTENDEE
 *
 * Action: Check in an Event Tickets attendee.
 *
 * API: Tribe__Tickets__Tickets::checkin( $attendee_id, $qr = null, $event_id = null, $details = [] )
 * (verified at event-tickets/src/Tribe/Tickets.php:1061).
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class ET_CHECK_IN_ATTENDEE extends \Uncanny_Automator\Recipe\Action {

	use Has_Dependency;

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'EC' );
		$this->set_action_code( 'ET_CHECK_IN_ATTENDEE' );
		$this->set_action_meta( 'EC_CHECKIN_ATTENDEE_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_action_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s is the attendee ID field. */
		$this->set_sentence( sprintf( esc_html_x( 'Check in {{an attendee:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Check in {{an attendee}}', 'The Events Calendar', 'uncanny-automator' ) );
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
			'EC_CHECKIN_ATTENDEE_ID' => array(
				'name' => esc_html_x( 'Attendee ID', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EC_CHECKIN_EVENT_ID'    => array(
				'name' => esc_html_x( 'Event ID', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'int',
			),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Attendee ID', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'      => 'int',
				'required'        => true,
				'supports_tokens' => true,
				'description'     => esc_html_x( 'The Attendee ID is unique per ticket per event (not the WP user ID). Use the Attendee ID token from an Event Tickets attendee trigger, or the numeric attendee record ID.', 'The Events Calendar', 'uncanny-automator' ),
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

		$attendee_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $attendee_id ) {
			$this->add_log_error( esc_html_x( 'Attendee ID is required.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) || ! function_exists( 'tribe' ) ) {
			$this->add_log_error( esc_html_x( 'Event Tickets is not active.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$provider = $this->resolve_provider( $attendee_id );

		if ( ! $provider instanceof \Tribe__Tickets__Tickets ) {
			/* translators: %d attendee ID */
			$this->add_log_error( sprintf( esc_html_x( 'Could not resolve ticket provider for attendee %d.', 'The Events Calendar', 'uncanny-automator' ), $attendee_id ) );
			return false;
		}

		$result = $provider->checkin( $attendee_id );

		if ( false === $result ) {
			/* translators: %d attendee ID */
			$this->add_log_error( sprintf( esc_html_x( 'Could not check in attendee %d.', 'The Events Calendar', 'uncanny-automator' ), $attendee_id ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'EC_CHECKIN_ATTENDEE_ID' => $attendee_id,
				'EC_CHECKIN_EVENT_ID'    => $this->resolve_event_id( $attendee_id ),
			)
		);

		return true;
	}

	/**
	 * Resolve the ticket provider instance for an attendee.
	 *
	 * @param int $attendee_id
	 *
	 * @return \Tribe__Tickets__Tickets|null
	 */
	private function resolve_provider( $attendee_id ) {

		try {
			$data_api = tribe( 'tickets.data_api' );
			$provider = $data_api->get_ticket_provider( $attendee_id );
		} catch ( \Exception $e ) {
			return null;
		}

		return $provider instanceof \Tribe__Tickets__Tickets ? $provider : null;
	}

	/**
	 * Resolve the event ID for an attendee post.
	 *
	 * @param int $attendee_id
	 *
	 * @return int
	 */
	private function resolve_event_id( $attendee_id ) {
		return EC_Attendee_Resolver::event_id_from_attendee( $attendee_id );
	}
}
