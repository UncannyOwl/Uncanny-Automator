<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar\Tokens\Trigger\Loopable;

use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Trigger_Loopable_Token;

/**
 * Loopable Attendees token for the Sugar Calendar ticket purchased trigger.
 *
 * @package Uncanny_Automator\Integrations\Sugar_Calendar
 */
class Attendees extends Trigger_Loopable_Token {

	/**
	 * Register the loopable token and its child tokens.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'FULL_NAME' => array(
				'name'       => esc_html_x( 'Attendee name', 'Sugar Calendar', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'EMAIL'     => array(
				'name'       => esc_html_x( 'Attendee email', 'Sugar Calendar', 'uncanny-automator' ),
				'token_type' => 'email',
			),
		);

		$this->set_id( 'ATTENDEES' );
		$this->set_name( esc_html_x( 'Attendees', 'Sugar Calendar', 'uncanny-automator' ) );
		$this->set_log_identifier( '{{FULL_NAME}} ({{EMAIL}})' );
		$this->set_child_tokens( $child_tokens );
	}

	/**
	 * Hydrate the loopable token with attendee data for the order.
	 *
	 * @param mixed $trigger_args Hook args: [ $order_id, $order_data ].
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $trigger_args ) {

		$loopable = new Loopable_Token_Collection();

		$order_id = $trigger_args[0] ?? 0;

		if ( empty( $order_id ) ) {
			return $loopable;
		}

		if ( ! function_exists( 'Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendees_by_order_id' ) ) {
			return $loopable;
		}

		$attendees = \Sugar_Calendar\AddOn\Ticketing\Common\Functions\get_attendees_by_order_id( absint( $order_id ) );

		foreach ( (array) $attendees as $attendee ) {
			$full_name = trim( $attendee->first_name . ' ' . $attendee->last_name );
			$loopable->create_item(
				array(
					'FULL_NAME' => $full_name,
					'EMAIL'     => $attendee->email ?? '',
				)
			);
		}

		return $loopable;
	}
}
