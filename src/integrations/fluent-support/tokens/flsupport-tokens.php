<?php

namespace Uncanny_Automator;

/**
 * Class Flsupport_Tokens
 *
 * @package Uncanny_Automator
 */
class Flsupport_Tokens {

	protected $trigger_tickets = array();

	/**
	 * Flsupport_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_flsupport_tokens', array( $this, 'flsupport_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_flsupport_trigger_tokens' ), 20, 6 );
	}

	/**
	 * The possible tokens.
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function flsupport_possible_tokens( $tokens = array(), $args = array() ) {

		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = (string) $args['integration'];

		if ( 'FLSUPPORT' === $trigger_integration ) {

			$add_action   = (string) $args['triggers_meta']['add_action'];
			$trigger_meta = (string) $args['meta'];

			$ticket_token_actions = apply_filters( 'uap_fl_support_ticket_tokens', array( 'fluent_support/ticket_created', 'fluent_support/response_added_by_customer', 'fluent_support/ticket_closed_by_customer' ), $args );

			$agent_token_actions = apply_filters( 'uap_fl_support_agent_tokens', array( 'fluent_support/response_added_by_customer', 'fluent_support/ticket_closed_by_customer' ), $args );

			$ticket_response_token_actions = apply_filters( 'uap_fl_support_ticket_response_tokens', array( 'fluent_support/response_added_by_customer' ), $args );

			if ( in_array( $add_action, $ticket_token_actions, true ) ) {

				$fields_tickets = array(
					array(
						'tokenId'         => 'FLSUPPORT-TICKET-ID',
						'tokenName'       => __( 'Ticket ID', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-TICKET-TITLE',
						'tokenName'       => __( 'Ticket subject', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-TICKET-CONTENT',
						'tokenName'       => __( 'Ticket details', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-TICKET-PRIORITY',
						'tokenName'       => __( 'Ticket priority', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-TICKET-PRODUCT_TITLE', /* $ticket->product->title */
						'tokenName'       => __( 'Ticket product', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
				);

				if ( in_array( $add_action, $ticket_response_token_actions, true ) ) {

					$fields_tickets[] = array(
						'tokenId'         => 'FLSUPPORT-CONVERSATION-CONTENT',
						'tokenName'       => __( 'Ticket response', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					);
				}

				// Following tokens are only applicable to specific triggers.
				if ( in_array( $add_action, $agent_token_actions, true ) ) {
					$fields_tickets[] = array(
						'tokenId'         => 'FLSUPPORT-AGENT-USERNAME', /* $agent->username */
						'tokenName'       => __( 'Ticket assignee username', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					);

					$fields_tickets[] = array(
						'tokenId'         => 'FLSUPPORT-AGENT-EMAIL', /* $agent->email */
						'tokenName'       => __( 'Ticket assignee email', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					);
				}

				$fields_tickets[] = array(
					'tokenId'         => 'FLSUPPORT-TICKET-ADMIN-URL',
					'tokenName'       => __( 'Ticket admin URL', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_meta,
				);

				$fields_customers = array(
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-EMAIL', /* $customer->email */
						'tokenName'       => __( 'Customer email', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-FIRST-NAME',
						'tokenName'       => __( 'Customer first name', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-LAST-NAME',
						'tokenName'       => __( 'Customer last name', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-TITLE',
						'tokenName'       => __( 'Customer job title', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-NOTE',
						'tokenName'       => __( 'Customer note', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-STATUS',
						'tokenName'       => __( 'Customer status', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-ADDRESS-LINE-1', /* $customer->address_line_1 */
						'tokenName'       => __( 'Customer address line 1', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-CITY', /* $customer->city */
						'tokenName'       => __( 'Customer city', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-STATE',
						'tokenName'       => __( 'Customer state', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-ZIPCODE',
						'tokenName'       => __( 'Customer zip code', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
					array(
						'tokenId'         => 'FLSUPPORT-CUSTOMER-COUNTRY',
						'tokenName'       => __( 'Customer country', 'uncanny-automator' ),
						'tokenType'       => 'text',
						'tokenIdentifier' => $trigger_meta,
					),
				);

				$tokens = array_merge( $tokens, $fields_tickets, $fields_customers );
				$tokens = Automator()->utilities->remove_duplicate_token_ids( $tokens );
			}
		}

		return $tokens;
	}

	/**
	 * @param     $value
	 * @param     $pieces
	 * @param     $recipe_id
	 * @param     $trigger_data
	 * @param int $user_id
	 * @param     $replace_args
	 *
	 * @return int|mixed|string
	 */
	public function parse_flsupport_trigger_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {

			if ( ! isset( $pieces[2] ) ) {
				return $value;
			}

			if ( stristr( $pieces[2], 'FLSUPPORT-' ) ) {
				global $wpdb;

				$trigger_id     = isset( $replace_args['trigger_id'] ) ? $replace_args['trigger_id'] : 0;
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? $replace_args['trigger_log_id'] : 0;

				if ( 0 === (int) $trigger_id || 0 === (int) $trigger_log_id ) {
					return $value;
				}

				$trigger_code     = $pieces[1];
				$token_identifier = strtoupper( $pieces[2] );
				$identifier       = trim( str_replace( 'FLSUPPORT-', '', $token_identifier ), '-' );
				$identifier_parts = explode( '-', $identifier );
				$object_type      = $identifier_parts[0];
				$token_field      = strtolower( str_replace( $object_type . '-', '', $identifier ) );

				$ticket_id = intval( Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'FLSUPPORTTICKETID', $trigger_id, $trigger_log_id, $user_id ) );

				if ( 0 === (int) $ticket_id ) {
					return $value;
				}

				$person_id = intval( Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'FLSUPPORTPERSONID', $trigger_id, $trigger_log_id, $user_id ) );

				$cache_key = $trigger_id . '-' . $ticket_id;
				if ( ! isset( $this->trigger_tickets[ $cache_key ] ) ) {
					// Only query ticket object once per trigger request.
					$this->trigger_tickets[ $cache_key ] = $this->get_ticket_object( $ticket_id );
				}

				$ticket = $this->trigger_tickets[ $cache_key ];

				switch ( $object_type ) {
					case 'TICKET':
						$value = $this->get_object_field( $token_field, $ticket );
						break;
					case 'AGENT':
						$value = $this->get_object_field( $token_field, $this->get_person_object( $ticket->agent_id ) );
						break;
					case 'CUSTOMER':
						$value = $this->get_object_field( $token_field, $this->get_person_object( $ticket->customer_id ) );
						break;
					case 'CONVERSATION':
						$response_id = intval( Automator()->helpers->recipe->get_form_data_from_trigger_meta( 'FLSUPPORTRESPONSEID', $trigger_id, $trigger_log_id, $user_id ) );

						$value = $this->get_object_field( $token_field, $this->get_response_object( $response_id ) );
						break;
					default:
						$value = apply_filters( 'uap_fl_support_token_value', $value, $trigger_id, $pieces );
				}
			}
		}

		return $value;
	}

	protected function fillin_wpuser_data( $person ) {
		if ( is_object( $person ) ) {
			$user_id = 0;
			if ( isset( $person->user_id ) ) {
				if ( $person->user_id ) {
					// fill in WP user details.
					$user_data        = \get_userdata( $person->user_id );
					$person->username = $user_data->user_login;
				}
			}
		}

		return $person;
	}

	protected function get_person_object( $id ) {
		$id = intval( $id );
		if ( 0 === $id ) {
			return false;
		}
		return $this->fillin_wpuser_data( \FluentSupport\App\Models\Person::where( 'id', $id )->first() );
	}

	protected function get_response_object( $response_id ) {
		$response_id = absint( $response_id );
		$response    = \FluentSupport\App\Models\Conversation::where( 'id', $response_id )->first();
		return $response;
	}

	protected function get_ticket_object( $ticket_id ) {
		$ticket_id = absint( $ticket_id );
		$ticket    = \FluentSupport\App\Models\Ticket::find( $ticket_id );
		if ( $ticket->product_id ) {
			$ticket->load( 'product' );
		}
		$ticket->admin_url = admin_url( "admin.php?page=fluent-support#/tickets/{$ticket_id}/view" );
		return $ticket;
	}

	protected function get_object_field( $token_field, $object ) {

		if ( ! is_object( $object ) ) {
			return;
		}

		if ( strstr( $token_field, '_' ) ) {
			$parts        = explode( '_', $token_field );
			$property     = isset( $parts[0] ) ? $parts[0] : '';
			$sub_property = isset( $parts[1] ) ? $parts[1] : '';

			if ( '' !== $property && '' !== $sub_property ) {
				if ( isset( $object->$property ) && is_object( $object->$property ) ) {
					if ( isset( $object->$property->$sub_property ) ) {
						return $object->$property->$sub_property;
					}
				}
			}
		}

		$token_field = str_replace( '-', '_', $token_field );
		if ( isset( $object->$token_field ) ) {
			return $object->$token_field;
		}
	}
}
