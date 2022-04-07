<?php

namespace Uncanny_Automator;

/**
 * Class Ec_Tokens
 * @package Uncanny_Automator
 */
class Ec_Tokens {

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

		$new_tokens = array();
		foreach ( $tickets as $ticket ) {
			$new_tokens = array(
				array(
					'tokenId'         => sprintf( '%s|tribe-tickets-plus-iac-name', sanitize_title( $ticket->name ) ),
					'tokenName'       => sprintf( '%s &mdash; Name', $ticket->name ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				),
				array(
					'tokenId'         => sprintf( '%s|tribe-tickets-plus-iac-email', sanitize_title( $ticket->name ) ),
					'tokenName'       => sprintf( '%s &mdash; Email', $ticket->name ),
					'tokenType'       => 'email',
					'tokenIdentifier' => $trigger_meta,
				),
			);

			$meta = \Tribe__Tickets_Plus__Meta::get_attendee_meta_fields( $ticket->ID );
			if ( empty( $meta ) ) {
				continue;
			}
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

				$new_tokens[] = array(
					'tokenId'         => sprintf( '%s|%s', sanitize_title( $ticket->name ), $fields['slug'] ),
					'tokenName'       => sprintf( '%s &mdash; %s', $ticket->name, $fields['label'] ),
					'tokenType'       => $type,
					'tokenIdentifier' => $trigger_meta,
				);
			}
		}

		return array_merge( $tokens, $new_tokens );
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

		$piece = 'ECEVENTS';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {
				if ( $trigger_data ) {
					global $wpdb;
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$token_info     = explode( '|', $pieces[2] );
							$meta_key       = isset( $token_info[1] ) ? $token_info[1] : '';
							$order_id       = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = 'ec_order_id' AND automator_trigger_id = %d AND automator_trigger_log_id = %d ORDER BY ID DESC LIMIT 0,1", $trigger['ID'], $replace_args['trigger_log_id'] ) );
							$attendees_data = get_post_meta( $order_id, '_tribe_tickets_meta', true );
							$attendees_data = maybe_unserialize( $attendees_data );
							if ( ! empty( $attendees_data ) ) {
								if ( is_array( $attendees_data ) && ! empty( $meta_key ) ) {
									foreach ( $attendees_data as $attendees ) {
										foreach ( $attendees as $attendee ) {
											$value = isset( $attendee[ $meta_key ] ) ? $attendee[ $meta_key ] : '';
											if ( is_array( $value ) ) {
												$value = implode( ', ', $value );
											}
										}
									}
								}
							}
						}
					}//end foreach
				}//end if
			}//end if
		}//end if

		return $value;
	}
}
