<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use TEC\Tickets\Commerce\Attendee as Tickets_Commerce_Attendee;
use TEC\Tickets\Commerce\Gateways\Manual\Order as Manual_Order;
use TEC\Tickets\Commerce\Module as Tickets_Commerce_Module;
use TEC\Tickets\Commerce\Order as Tickets_Commerce_Order;
use TEC\Tickets\RSVP\V2\Constants as Rsvp_Constants;
use Tribe__Tickets__RSVP;
use Tribe__Tickets__Ticket_Object;
use Tribe__Tickets__Tickets;
use WP_Error;
use WP_Post;

/**
 * RSVPs in both Event Tickets formats.
 *
 * Event Tickets 5.30 stores an RSVP as a Tickets Commerce ticket of type
 * `tc-rsvp`, and its migration moves existing RSVPs there. Before that, an
 * RSVP is a `Tribe__Tickets__RSVP` ticket. A site runs one format at a time,
 * so every RSVP item asks the ticket or attendee which format it is in.
 *
 * Reached through The_Events_Calendar_Helpers, so Pro's helper can wrap it.
 *
 * @since 8.0.3
 *
 * @package Uncanny_Automator
 */
final class EC_Rsvp {

	/**
	 * Whether the ticket is an RSVP, in either format.
	 *
	 * @param Tribe__Tickets__Ticket_Object|int $ticket The ticket or its ID.
	 *
	 * @return bool
	 */
	public function is_rsvp_ticket( $ticket ) {

		$ticket = $this->ticket( $ticket );

		if ( null === $ticket ) {
			return false;
		}

		return 'Tribe__Tickets__RSVP' === $ticket->provider_class || $this->is_tickets_commerce_rsvp( $ticket );
	}

	/**
	 * Whether the ticket is a Tickets Commerce RSVP (Event Tickets 5.30+).
	 *
	 * @param Tribe__Tickets__Ticket_Object|int $ticket The ticket or its ID.
	 *
	 * @return bool
	 */
	public function is_tickets_commerce_rsvp( $ticket ) {

		$ticket = $this->ticket( $ticket );

		if ( null === $ticket || ! class_exists( Rsvp_Constants::class ) ) {
			return false;
		}

		return Rsvp_Constants::TC_RSVP_TYPE === $ticket->type();
	}

	/**
	 * Create a Tickets Commerce RSVP the way Event Tickets' own RSVP form does.
	 *
	 * The RSVP form fills the visitor's cart and turns it into a Free-gateway
	 * order. An action must not touch a visitor's cart, so this creates the
	 * order through the Manual gateway, as Event Tickets does for an attendee
	 * added by hand, with the item typed as an RSVP and carrying the answer.
	 * Moving it to Pending and then Completed runs Event Tickets' own steps:
	 * attendees with the answer, stock (Going only), the Going or Not going
	 * email, and every Tickets Commerce hook.
	 *
	 * @param Tribe__Tickets__Ticket_Object $ticket   A Tickets Commerce RSVP ticket.
	 * @param array<string,mixed>           $attendee {
	 *     @type string $full_name The attendee's name.
	 *     @type string $email     The attendee's email.
	 *     @type int    $user_id   The WordPress user the RSVP belongs to, or 0.
	 *     @type int    $quantity  How many places.
	 *     @type bool   $going     Going (true) or Not going (false).
	 * }
	 *
	 * @return array{order_id:int,attendee_ids:int[]}|WP_Error
	 */
	public function create_tickets_commerce_rsvp( Tribe__Tickets__Ticket_Object $ticket, array $attendee ) {

		if ( ! $this->is_tickets_commerce_rsvp( $ticket ) || ! class_exists( Manual_Order::class ) ) {
			return new WP_Error( 'not_tickets_commerce_rsvp', esc_html_x( 'The ticket is not an RSVP ticket.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		$quantity = max( 1, absint( $attendee['quantity'] ?? 1 ) );
		$going    = ! empty( $attendee['going'] );

		if ( $going ) {
			$available = (int) $ticket->available();

			if ( -1 !== $available && $available < $quantity ) {
				return new WP_Error(
					'rsvp_capacity',
					sprintf(
						/* translators: %1$d the places asked for, %2$d the places left */
						esc_html_x( 'The RSVP has %2$d places left, and %1$d were asked for.', 'The Events Calendar', 'uncanny-automator' ),
						$quantity,
						max( 0, $available )
					)
				);
			}
		}

		// A list, as the RSVP form's cart gives one: Event Tickets' RSVP email
		// reads the answer from the first item by index.
		$items = array(
			array(
				'ticket_id' => $ticket->ID,
				'event_id'  => (int) $ticket->get_event_id(),
				'quantity'  => $quantity,
				'type'      => Rsvp_Constants::TC_RSVP_TYPE,
				'extra'     => array(
					'order_status' => $going ? 'yes' : 'no',
					'optout'       => false,
				),
			),
		);

		$order = tribe( Manual_Order::class )->create(
			$items,
			array(
				'user_id'   => absint( $attendee['user_id'] ?? 0 ),
				'full_name' => (string) ( $attendee['full_name'] ?? '' ),
				'email'     => (string) ( $attendee['email'] ?? '' ),
			)
		);

		if ( ! $order instanceof WP_Post ) {
			return new WP_Error( 'rsvp_order', esc_html_x( 'Event Tickets did not create the RSVP order.', 'The Events Calendar', 'uncanny-automator' ) );
		}

		foreach ( array( 'pending', 'completed' ) as $status ) {
			$moved = tribe( Tickets_Commerce_Order::class )->modify_status( $order->ID, $status );

			if ( is_wp_error( $moved ) ) {
				return $moved;
			}
		}

		$attendee_ids = array_map( 'absint', array_column( tribe( Tickets_Commerce_Module::class )->get_attendees_by_order_id( $order->ID ), 'attendee_id' ) );
		$expected     = $going ? 'going' : 'not_going';

		if ( count( $attendee_ids ) !== $quantity ) {
			return new WP_Error(
				'rsvp_attendees',
				sprintf(
					/* translators: %1$d the order ID, %2$d the attendees asked for, %3$d the attendees created */
					esc_html_x( 'RSVP order %1$d has %3$d attendees instead of %2$d.', 'The Events Calendar', 'uncanny-automator' ),
					$order->ID,
					$quantity,
					count( $attendee_ids )
				)
			);
		}

		foreach ( $attendee_ids as $attendee_id ) {
			if ( $this->attendee_status( $attendee_id ) !== $expected ) {
				return new WP_Error(
					'rsvp_status',
					/* translators: %d the attendee ID */
					sprintf( esc_html_x( 'Attendee %d was created without the RSVP answer.', 'The Events Calendar', 'uncanny-automator' ), $attendee_id )
				);
			}
		}

		return array(
			'order_id'     => (int) $order->ID,
			'attendee_ids' => $attendee_ids,
		);
	}

	/**
	 * The RSVP answer of an attendee in either format.
	 *
	 * Read from the attendee post with the meta key Event Tickets itself
	 * names for that format, as Event Tickets' own RSVP code reads it.
	 *
	 * @param int $attendee_id The attendee post ID.
	 *
	 * @return string `going`, `not_going`, or '' when the attendee has no answer.
	 */
	public function attendee_status( $attendee_id ) {

		$attendee_id = absint( $attendee_id );
		$meta_key    = $this->status_meta_key( (string) get_post_type( $attendee_id ) );

		if ( 0 === $attendee_id || '' === $meta_key ) {
			return '';
		}

		$answer = (string) get_post_meta( $attendee_id, $meta_key, true );

		if ( 'yes' === $answer ) {
			return 'going';
		}

		if ( 'no' === $answer ) {
			return 'not_going';
		}

		return '';
	}

	/**
	 * The event and answer meta keys of an RSVP attendee, per format.
	 *
	 * @return array<int,array{event:string,status:string}>
	 */
	public function attendee_meta_keys() {

		$keys = array();

		if ( class_exists( Tribe__Tickets__RSVP::class ) ) {
			$keys[] = array(
				'event'  => Tribe__Tickets__RSVP::ATTENDEE_EVENT_KEY,
				'status' => Tribe__Tickets__RSVP::ATTENDEE_RSVP_KEY,
			);
		}

		if ( class_exists( Rsvp_Constants::class ) && class_exists( Tickets_Commerce_Attendee::class ) ) {
			$keys[] = array(
				'event'  => Tickets_Commerce_Attendee::$event_relation_meta_key,
				'status' => Rsvp_Constants::RSVP_STATUS_META_KEY,
			);
		}

		return $keys;
	}

	/**
	 * The answer meta key for an attendee post type.
	 *
	 * @param string $post_type The attendee's post type.
	 *
	 * @return string
	 */
	private function status_meta_key( $post_type ) {

		if ( class_exists( Tribe__Tickets__RSVP::class ) && Tribe__Tickets__RSVP::ATTENDEE_OBJECT === $post_type ) {
			return Tribe__Tickets__RSVP::ATTENDEE_RSVP_KEY;
		}

		if ( class_exists( Rsvp_Constants::class ) && class_exists( Tickets_Commerce_Attendee::class ) && Tickets_Commerce_Attendee::POSTTYPE === $post_type ) {
			return Rsvp_Constants::RSVP_STATUS_META_KEY;
		}

		return '';
	}

	/**
	 * Resolve a ticket object.
	 *
	 * @param Tribe__Tickets__Ticket_Object|int $ticket The ticket or its ID.
	 *
	 * @return Tribe__Tickets__Ticket_Object|null
	 */
	private function ticket( $ticket ) {

		if ( $ticket instanceof Tribe__Tickets__Ticket_Object ) {
			return $ticket;
		}

		if ( ! is_numeric( $ticket ) || 0 >= (int) $ticket || ! class_exists( Tribe__Tickets__Tickets::class ) ) {
			return null;
		}

		$ticket = Tribe__Tickets__Tickets::load_ticket_object( (int) $ticket );

		return $ticket instanceof Tribe__Tickets__Ticket_Object ? $ticket : null;
	}
}
