<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_attendee;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_order;
use function Sugar_Calendar\AddOn\Ticketing\Common\Functions\add_ticket;

/**
 * Class Sugar_Calendar_Register_Attendee
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sugar_Calendar\Sugar_Calendar_Helpers get_item_helpers()
 */
class Sugar_Calendar_Register_Attendee extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SUGAR_CALENDAR' );
		$this->set_action_code( 'SUGAR_CALENDAR_REGISTER_ATTENDEE' );
		$this->set_action_meta( 'SC_EVENT' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		// translators: %1$s is the event.
		$this->set_sentence( sprintf( esc_html_x( 'Register an attendee to {{an event:%1$s}}', 'Sugar Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Register an attendee to {{an event}}', 'Sugar Calendar', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'ATTENDEE_ID'    => array(
					'name' => esc_html_x( 'Attendee ID', 'Sugar Calendar', 'uncanny-automator' ),
					'type' => 'int',
				),
				'ATTENDEE_EMAIL' => array(
					'name' => esc_html_x( 'Attendee email', 'Sugar Calendar', 'uncanny-automator' ),
					'type' => 'email',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Check if the ticketing add-on is available.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return function_exists( 'Sugar_Calendar\\AddOn\\Ticketing\\Common\\Functions\\add_order' );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Event', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_item_helpers()->get_events( false ),
				'supports_custom_value' => true,
			),
			array(
				'option_code' => 'SC_FIRST_NAME',
				'label'       => esc_html_x( 'First name', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'SC_LAST_NAME',
				'label'       => esc_html_x( 'Last name', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'SC_EMAIL',
				'label'       => esc_html_x( 'Email', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$event_post_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$first_name    = sanitize_text_field( $parsed['SC_FIRST_NAME'] ?? '' );
		$last_name     = sanitize_text_field( $parsed['SC_LAST_NAME'] ?? '' );
		$email         = sanitize_email( $parsed['SC_EMAIL'] ?? '' );

		if ( empty( $event_post_id ) ) {
			$this->add_log_error( esc_html_x( 'Event is required.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( empty( $email ) || false === is_email( $email ) ) {
			$this->add_log_error( esc_html_x( 'A valid email address is required.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		// Get the SC event from the WP post ID.
		$event = sugar_calendar_get_event_by_object( $event_post_id );

		if ( empty( $event->id ) ) {
			$this->add_log_error(
				sprintf(
					// translators: %d is the post ID.
					esc_html_x( 'No Sugar Calendar event found for post ID %d.', 'Sugar Calendar', 'uncanny-automator' ),
					$event_post_id
				)
			);
			return false;
		}

		// Create attendee.
		$attendee_id = add_attendee(
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
			)
		);

		if ( empty( $attendee_id ) ) {
			$this->add_log_error( esc_html_x( 'Failed to create attendee.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		// Create a free order.
		$order_id = add_order(
			array(
				'event_id'   => $event->id,
				'email'      => $email,
				'total'      => 0,
				'status'     => 'paid',
				'event_date' => $event->start,
			)
		);

		if ( empty( $order_id ) ) {
			$this->add_log_error( esc_html_x( 'Failed to create order.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		// Create an active ticket.
		$ticket_id = add_ticket(
			array(
				'order_id'    => $order_id,
				'event_id'    => $event->id,
				'attendee_id' => $attendee_id,
				'event_date'  => $event->start,
				'status'      => 'active',
			)
		);

		if ( empty( $ticket_id ) ) {
			$this->add_log_error( esc_html_x( 'Failed to create ticket.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'ATTENDEE_ID'    => $attendee_id,
				'ATTENDEE_EMAIL' => $email,
			)
		);

		return true;
	}
}
