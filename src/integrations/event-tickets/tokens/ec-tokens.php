<?php

namespace Uncanny_Automator;

/**
 * Class Ec_Tokens
 *
 * @package Uncanny_Automator
 */
class Ec_Tokens {


	/**
	 *
	 */
	public function __construct() {
		add_action(
			'plugins_loaded',
			function () {
				add_filter( 'automator_maybe_trigger_ec_ecevents_tokens', array( $this, 'ec_possible_tokens' ), 20, 2 );
			},
			99
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_ec_tokens' ), 20, 6 );
	}

	/**
	 * Prepare tokens.
	 *
	 * @param array $tokens .
	 * @param array $args .
	 *
	 * @return array
	 */
	public function ec_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$event_id     = $args['value'];
		$trigger_meta = $args['meta'];

		if ( empty( $event_id ) || intval( '-1' ) === intval( $event_id ) ) {
			return $tokens;
		}

		if ( ! class_exists( '\Tribe__Tickets__Tickets' ) ) {
			return $tokens;
		}

		if ( ! method_exists( '\Tribe__Tickets__Tickets', 'get_all_event_tickets' ) ) {
			return $tokens;
		}

		$tickets = \Tribe__Tickets__Tickets::get_all_event_tickets( $event_id );

		if ( ! class_exists( '\Tribe__Tickets_Plus__Meta' ) ) {
			return $tokens;
		}

		if ( ! method_exists( '\Tribe__Tickets_Plus__Meta', 'get_attendee_meta_fields' ) ) {
			return $tokens;
		}

		if ( empty( $tickets ) ) {
			return $tokens;
		}

		$ticket_selected = isset( $args['triggers_meta']['EVENTTICKET'] ) ? $args['triggers_meta']['EVENTTICKET'] : '';
		foreach ( $tickets as $ticket ) {
			$ticket_id   = $ticket->ID;
			$ticket_name = $ticket->name;
			if ( ! empty( $ticket_selected ) && intval( '-1' ) === intval( $ticket_selected ) ) {
				$trigger_code = $args['triggers_meta']['code'];
				$new_tokens   = array(
					array(
						'tokenId'         => 'holder_name',
						'tokenName'       => __( 'Attendee name', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_code,
					),
					array(
						'tokenId'         => 'holder_email',
						'tokenName'       => __( 'Attendee email', 'uncanny-automator' ),
						'tokenType'       => 'email',
						'tokenIdentifier' => $trigger_code,
					),
				);
				$tokens       = array_merge( $tokens, $new_tokens );
			}
			if ( ! empty( $ticket_selected ) && absint( $ticket_selected ) === absint( $ticket_id ) ) {
				$new_tokens = $this->get_ticket_fields_token( $ticket_id, $ticket_name, $trigger_meta );
				$tokens     = array_merge( $tokens, $new_tokens );
			}
			if ( empty( $ticket_selected ) ) {
				$new_tokens = $this->get_ticket_fields_token( $ticket_id, $ticket_name, $trigger_meta );
				$tokens     = array_merge( $tokens, $new_tokens );
			}
		}

		return Automator()->utilities->remove_duplicate_token_ids( $tokens );
	}

	/**
	 * Parse the token.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function parse_ec_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( empty( $pieces ) ) {
			return $value;
		}
		if ( empty( $trigger_data ) ) {
			return $value;
		}
		if ( empty( $replace_args ) ) {
			return $value;
		}
		$piece = 'ECEVENTS';
		if ( ! in_array( $piece, $pieces, true ) ) {
			return $value;
		}

		$token_info = explode( '|', $pieces[2] );
		$meta_key   = isset( $token_info[1] ) ? $token_info[1] : '';
		if ( empty( $meta_key ) ) {
			return $value;
		}
		$attendee_id   = Automator()->db->token->get( 'attendee_id', $replace_args );
		$attendee_data = Automator()->db->token->get( 'attendee_data', $replace_args );

		if ( empty( $attendee_id ) || empty( $attendee_data ) || ! is_array( $attendee_data ) ) {
			return $value;
		}

		$value = '';
		if ( 'tribe-tickets-plus-iac-name' === $meta_key || 'holder_name' === $meta_key ) {
			return $attendee_data['full_name'];
		}
		if ( 'tribe-tickets-plus-iac-email' === $meta_key || 'holder_email' === $meta_key ) {
			return $attendee_data['email'];
		}

		if ( isset( $attendee_data[ $meta_key ] ) ) {
			return $attendee_data[ $meta_key ];
		}

		$attendee_meta = get_post_meta( $attendee_id, \Tribe__Tickets_Plus__Meta::META_KEY, true );

		if ( isset( $attendee_meta[ $meta_key ] ) ) {
			return $attendee_meta[ $meta_key ];
		}
		$checkboxes = array();
		foreach ( $attendee_meta as $k => $v ) {
			// Value stored as Field-ID_HASH,
			// example: attending-days_1a31a6f65cc993ff6bd9a5b85f0520b0,
			// hence the regex below
			if ( preg_match( '/(' . addcslashes( $meta_key, '/-:\\' ) . '\_\w+)/', $k ) ) {
				$checkboxes[] = $v;
			}
		}

		if ( ! empty( $checkboxes ) ) {
			return join( ', ', $checkboxes );
		}

		return $value;
	}

	/**
	 * @param $ticket_id
	 * @param $ticket_name
	 * @param $trigger_meta
	 *
	 * @return array[]
	 */
	public function get_ticket_fields_token( $ticket_id, $ticket_name, $trigger_meta ) {
		$tokens = array(
			array(
				'tokenId'         => sprintf( '%d|tribe-tickets-plus-iac-name', $ticket_id ),
				'tokenName'       => sprintf( '%s &mdash; Attendee name', $ticket_name ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => sprintf( '%d|tribe-tickets-plus-iac-email', $ticket_id ),
				'tokenName'       => sprintf( '%s &mdash; Attendee email', $ticket_name ),
				'tokenType'       => 'email',
				'tokenIdentifier' => $trigger_meta,
			),
		);

		$meta = \Tribe__Tickets_Plus__Meta::get_attendee_meta_fields( $ticket_id );
		if ( ! empty( $meta ) ) {
			foreach ( $meta as $fields ) {
				switch ( $fields['type'] ) {
					case 'email':
						$type = 'email';
						break;
					case 'number':
						$type = 'int';
						break;
					default:
						$type = 'text';
						break;
				}

				$tokens[] = array(
					'tokenId'         => sprintf( '%d|%s', $ticket_id, $fields['slug'] ),
					'tokenName'       => sprintf( '%s &mdash; %s', $ticket_name, $fields['label'] ),
					'tokenType'       => $type,
					'tokenIdentifier' => $trigger_meta,
				);
			}
		}

		return $tokens;
	}
}
