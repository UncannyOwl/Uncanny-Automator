<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Eventin_Check_In_Attendee
 *
 * Marks an attendee's ticket used — the same two meta writes the Eventin Pro QR
 * scanner performs (eventin-pro/core/attendee/hooks.php:982-983). There is no
 * plugin API for this; the scanner writes the meta directly.
 *
 * The two guards the scanner applies before writing
 * (eventin-pro/core/attendee/hooks.php:967-976) are applied here too: a failed
 * attendee cannot be checked in, and an already-used ticket is an explicit
 * error rather than a silent no-op. That second guard matters because
 * update_post_meta() returns false for an unchanged value, so without it a
 * repeat check-in would be indistinguishable from a write failure.
 *
 * The timestamp is written with a CORRECT format string. Eventin's own
 * `date_i18n( 'Y-m-d g:i:m', … )` (eventin-pro/core/attendee/hooks.php:979)
 * puts the month where the minutes belong.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 *
 * @property Eventin_Helpers $item_helpers
 */
class Eventin_Check_In_Attendee extends Action {

	/**
	 * Option code of the event field.
	 */
	const EVENT = 'EVENTIN_EVENT';

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EVENTIN' );
		$this->set_action_code( 'EVENTIN_CHECK_IN_ATTENDEE' );
		$this->set_action_meta( 'EVENTIN_ATTENDEE' );
		// Writes attendee post meta; no recipe user is involved.
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Attendee */
				esc_html_x( 'Check in {{an attendee:%1$s}}', 'Eventin', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Check in {{an attendee}}', 'Eventin', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * The event field is a filter for the attendee list, not part of the
	 * sentence — attendee lists are per-event and unusable unfiltered.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => self::EVENT,
				'label'       => esc_html_x( 'Event', 'Eventin', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'events_strict' ),
			),
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Attendee', 'Eventin', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_parent_config(
					'attendees_strict',
					array( self::EVENT )
				),
			),
		);
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'EVENTIN_CHECKED_IN_ATTENDEE_ID' => array(
				'name' => esc_html_x( 'Attendee ID', 'Eventin', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EVENTIN_CHECKED_IN_NAME'        => array(
				'name' => esc_html_x( 'Attendee name', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENTIN_CHECKED_IN_EMAIL'       => array(
				'name' => esc_html_x( 'Attendee email', 'Eventin', 'uncanny-automator' ),
				'type' => 'email',
			),
			'EVENTIN_CHECKED_IN_TIME'        => array(
				'name' => esc_html_x( 'Check-in time', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENTIN_CHECKED_IN_EVENT_ID'    => array(
				'name' => esc_html_x( 'Event ID', 'Eventin', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EVENTIN_CHECKED_IN_EVENT_TITLE' => array(
				'name' => esc_html_x( 'Event title', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed options.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$attendee_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $attendee_id || ! $this->item_helpers->is_attendee( $attendee_id ) ) {
			$this->add_log_error( sprintf( 'Attendee not found: [%d].', $attendee_id ) );
			return false;
		}

		$payment_status = (string) get_post_meta( $attendee_id, Eventin_Helpers::ATTENDEE_PAYMENT_STATUS_META, true );

		// The scanner refuses a failed attendee (eventin-pro/core/attendee/hooks.php:967-976).
		if ( 'failed' === $payment_status ) {
			$this->add_log_error( sprintf( 'Attendee [%d] has a failed payment and cannot be checked in.', $attendee_id ) );
			return false;
		}

		$ticket_status = (string) get_post_meta( $attendee_id, Eventin_Helpers::ATTENDEE_TICKET_STATUS_META, true );

		if ( 'used' === $ticket_status ) {
			$this->add_log_error( sprintf( 'Attendee [%d] is already checked in.', $attendee_id ) );
			return false;
		}

		// Correct format string — Eventin's own writes the month in the seconds
		// position (eventin-pro/core/attendee/hooks.php:979). current_time( 'mysql' )
		// is that exact format in site-local time, and unlike date_i18n() it does
		// not route a raw timestamp through a filter phpcs warns about.
		$checked_in_at = current_time( 'mysql' );

		update_post_meta( $attendee_id, Eventin_Helpers::ATTENDEE_TICKET_STATUS_META, 'used' );
		update_post_meta( $attendee_id, Eventin_Helpers::ATTENDEE_CHECKIN_TIME_META, $checked_in_at );

		$event_id = $this->item_helpers->get_attendee_event_id( $attendee_id );

		$this->hydrate_tokens(
			array(
				'EVENTIN_CHECKED_IN_ATTENDEE_ID' => $attendee_id,
				'EVENTIN_CHECKED_IN_NAME'        => (string) get_post_meta( $attendee_id, 'etn_name', true ),
				'EVENTIN_CHECKED_IN_EMAIL'       => (string) get_post_meta( $attendee_id, 'etn_email', true ),
				'EVENTIN_CHECKED_IN_TIME'        => $checked_in_at,
				'EVENTIN_CHECKED_IN_EVENT_ID'    => $event_id,
				'EVENTIN_CHECKED_IN_EVENT_TITLE' => 0 === $event_id ? '' : (string) get_the_title( $event_id ),
			)
		);

		return true;
	}
}
