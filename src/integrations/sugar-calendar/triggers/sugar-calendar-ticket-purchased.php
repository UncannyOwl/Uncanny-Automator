<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

use Uncanny_Automator\Integrations\Sugar_Calendar\Tokens\Trigger\Loopable\Attendees;

/**
 * Class Sugar_Calendar_Ticket_Purchased
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sugar_Calendar\Sugar_Calendar_Helpers get_item_helpers()
 */
class Sugar_Calendar_Ticket_Purchased extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_integration( 'SUGAR_CALENDAR' );
		$this->set_trigger_code( 'SUGAR_CALENDAR_TICKET_PURCHASED' );
		$this->set_trigger_meta( 'SC_EVENT' );
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		// translators: %1$s is the event.
		$this->set_sentence( sprintf( esc_html_x( 'A ticket for {{an event:%1$s}} is purchased', 'Sugar Calendar', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A ticket for {{an event}} is purchased', 'Sugar Calendar', 'uncanny-automator' ) );

		$this->set_loopable_tokens( array( 'ATTENDEES' => Attendees::class ) );

		$this->add_action( 'sc_et_checkout_pre_redirect', 10, 2 );
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
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Event', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->get_item_helpers()->get_events( true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$order_data = $hook_args[1];

		if ( empty( $order_data ) || ! isset( $order_data['event_id'] ) ) {
			return false;
		}

		$selected_event = $trigger['meta'][ $this->get_trigger_meta() ];

		// "Any event" — always match.
		if ( intval( '-1' ) === intval( $selected_event ) ) {
			return true;
		}

		// The dropdown stores WP post IDs; $order_data['event_id'] is the SC internal
		// event ID from the sc_events table — a different value. Convert before comparing.
		$sc_event = sugar_calendar_get_event_by_object( intval( $selected_event ) );

		if ( empty( $sc_event->id ) || intval( $order_data['event_id'] ) !== intval( $sc_event->id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger Trigger data.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->get_item_helpers()->get_ticket_tokens_config() );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$order_id   = $hook_args[0];
		$order_data = $hook_args[1];

		// Resolve user context from buyer email here, not in validate().
		if ( ! empty( $order_data['email'] ) ) {
			$user = get_user_by( 'email', $order_data['email'] );
			if ( false !== $user ) {
				$this->set_user_id( $user->ID );
			}
		}

		$defaults = wp_list_pluck( $this->get_item_helpers()->get_ticket_tokens_config(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$tokens['ORDER_ID'] = $order_id;

		if ( ! empty( $order_data['event_id'] ) ) {
			$tokens['EVENT_ID'] = $order_data['event_id'];

			$event = sugar_calendar_get_event( $order_data['event_id'] );

			if ( ! empty( $event->id ) ) {
				$tokens['EVENT_TITLE'] = $event->title;
			}
		}

		$tokens['BUYER_FIRST_NAME'] = isset( $order_data['first_name'] ) ? $order_data['first_name'] : '';
		$tokens['BUYER_LAST_NAME']  = isset( $order_data['last_name'] ) ? $order_data['last_name'] : '';
		$tokens['BUYER_EMAIL']      = isset( $order_data['email'] ) ? $order_data['email'] : '';
		$tokens['ORDER_TOTAL']      = isset( $order_data['total'] ) ? $order_data['total'] : '';
		$tokens['ORDER_CURRENCY']   = isset( $order_data['currency'] ) ? $order_data['currency'] : '';
		$tokens['TRANSACTION_ID']   = isset( $order_data['transaction_id'] ) ? $order_data['transaction_id'] : '';

		$attendees = function_exists( 'Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendees_by_order_id' )
			? \Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendees_by_order_id( $order_id )
			: array();

		$names  = array();
		$emails = array();

		foreach ( $attendees as $attendee ) {
			$full_name = trim( $attendee->first_name . ' ' . $attendee->last_name );
			if ( ! empty( $full_name ) ) {
				$names[] = $full_name;
			}
			if ( ! empty( $attendee->email ) ) {
				$emails[] = $attendee->email;
			}
		}

		$tokens['ATTENDEE_COUNT']  = count( $attendees );
		$tokens['ATTENDEE_NAMES']  = implode( ', ', $names );
		$tokens['ATTENDEE_EMAILS'] = implode( ', ', $emails );

		return $tokens;
	}
}
