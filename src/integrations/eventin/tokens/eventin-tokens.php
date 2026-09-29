<?php

namespace Uncanny_Automator\Integrations\Eventin\Tokens;

use Uncanny_Automator\Integrations\Eventin\Eventin_Helpers;

/**
 * Class Eventin_Tokens
 *
 * Event, order and attendee token definitions shared by the four Free
 * triggers, kept here rather than duplicated across them.
 *
 * Every hydrate method returns its FULL keyset — empty values for an
 * unresolvable record — so a recipe never resolves a partial map.
 *
 * @package Uncanny_Automator\Integrations\Eventin\Tokens
 */
class Eventin_Tokens {

	/**
	 * Event tokens.
	 *
	 * @return array
	 */
	public function event_tokens() {
		return array(
			array(
				'tokenId'   => 'ETN_EVENT_ID',
				'tokenName' => esc_html_x( 'Event ID', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ETN_EVENT_TITLE',
				'tokenName' => esc_html_x( 'Event title', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_URL',
				'tokenName' => esc_html_x( 'Event URL', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'ETN_EVENT_STATUS',
				'tokenName' => esc_html_x( 'Event status', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_START_DATE',
				'tokenName' => esc_html_x( 'Event start date', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_START_TIME',
				'tokenName' => esc_html_x( 'Event start time', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_END_DATE',
				'tokenName' => esc_html_x( 'Event end date', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_END_TIME',
				'tokenName' => esc_html_x( 'Event end time', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_TIMEZONE',
				'tokenName' => esc_html_x( 'Event timezone', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_TYPE',
				'tokenName' => esc_html_x( 'Event type', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_LOCATION',
				'tokenName' => esc_html_x( 'Event location', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_MEETING_LINK',
				'tokenName' => esc_html_x( 'Event meeting link', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_CATEGORIES',
				'tokenName' => esc_html_x( 'Event categories', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_TAGS',
				'tokenName' => esc_html_x( 'Event tags', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_DESCRIPTION',
				'tokenName' => esc_html_x( 'Event description', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_LOCATION_TYPE',
				'tokenName' => esc_html_x( 'Event location type', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_REGISTRATION_DEADLINE',
				'tokenName' => esc_html_x( 'Registration deadline', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_TOTAL_TICKETS',
				'tokenName' => esc_html_x( 'Total available tickets', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ETN_EVENT_SOLD_TICKETS',
				'tokenName' => esc_html_x( 'Total sold tickets', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ETN_EVENT_SPEAKERS',
				'tokenName' => esc_html_x( 'Event speakers', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_ORGANIZERS',
				'tokenName' => esc_html_x( 'Event organizers', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_EVENT_BANNER_URL',
				'tokenName' => esc_html_x( 'Event banner URL', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'ETN_EVENT_LOGO_URL',
				'tokenName' => esc_html_x( 'Event logo URL', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Hydrate the event tokens.
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return array
	 */
	public function hydrate_event_tokens( $event_id ) {

		$event_id = absint( $event_id );

		$tokens = array(
			'ETN_EVENT_ID'                    => $event_id,
			'ETN_EVENT_TITLE'                 => '',
			'ETN_EVENT_URL'                   => '',
			'ETN_EVENT_STATUS'                => '',
			'ETN_EVENT_START_DATE'            => '',
			'ETN_EVENT_START_TIME'            => '',
			'ETN_EVENT_END_DATE'              => '',
			'ETN_EVENT_END_TIME'              => '',
			'ETN_EVENT_TIMEZONE'              => '',
			'ETN_EVENT_TYPE'                  => '',
			'ETN_EVENT_LOCATION'              => '',
			'ETN_EVENT_MEETING_LINK'          => '',
			'ETN_EVENT_CATEGORIES'            => '',
			'ETN_EVENT_TAGS'                  => '',
			'ETN_EVENT_DESCRIPTION'           => '',
			'ETN_EVENT_LOCATION_TYPE'         => '',
			'ETN_EVENT_REGISTRATION_DEADLINE' => '',
			'ETN_EVENT_TOTAL_TICKETS'         => 0,
			'ETN_EVENT_SOLD_TICKETS'          => 0,
			'ETN_EVENT_SPEAKERS'              => '',
			'ETN_EVENT_ORGANIZERS'            => '',
			'ETN_EVENT_BANNER_URL'            => '',
			'ETN_EVENT_LOGO_URL'              => '',
		);

		if ( 0 === $event_id || Eventin_Helpers::EVENT_POST_TYPE !== get_post_type( $event_id ) ) {
			return $tokens;
		}

		$tokens['ETN_EVENT_TITLE']        = (string) get_the_title( $event_id );
		$tokens['ETN_EVENT_URL']          = (string) get_permalink( $event_id );
		$tokens['ETN_EVENT_STATUS']       = ( new Eventin_Helpers() )->get_event_status( $event_id );
		$tokens['ETN_EVENT_START_DATE']   = (string) get_post_meta( $event_id, 'etn_start_date', true );
		$tokens['ETN_EVENT_START_TIME']   = (string) get_post_meta( $event_id, 'etn_start_time', true );
		$tokens['ETN_EVENT_END_DATE']     = (string) get_post_meta( $event_id, 'etn_end_date', true );
		$tokens['ETN_EVENT_END_TIME']     = (string) get_post_meta( $event_id, 'etn_end_time', true );
		$tokens['ETN_EVENT_TIMEZONE']     = (string) get_post_meta( $event_id, 'event_timezone', true );
		$tokens['ETN_EVENT_TYPE']         = (string) get_post_meta( $event_id, 'event_type', true );
		$tokens['ETN_EVENT_LOCATION']     = $this->get_event_address( $event_id );
		$tokens['ETN_EVENT_MEETING_LINK'] = (string) get_post_meta( $event_id, 'meeting_link', true );
		$tokens['ETN_EVENT_CATEGORIES']   = $this->term_list( $event_id, 'etn_category' );
		$tokens['ETN_EVENT_TAGS']         = $this->term_list( $event_id, 'etn_tags' );

		$tokens['ETN_EVENT_DESCRIPTION']           = (string) get_post_field( 'post_content', $event_id );
		$tokens['ETN_EVENT_LOCATION_TYPE']         = (string) get_post_meta( $event_id, 'etn_event_location_type', true );
		$tokens['ETN_EVENT_REGISTRATION_DEADLINE'] = (string) get_post_meta( $event_id, 'etn_registration_deadline', true );
		// Three i's in "avaiilable" — Eventin's typo, and the real meta key.
		$tokens['ETN_EVENT_TOTAL_TICKETS'] = absint( get_post_meta( $event_id, 'etn_total_avaiilable_tickets', true ) );
		$tokens['ETN_EVENT_SOLD_TICKETS']  = absint( get_post_meta( $event_id, 'etn_total_sold_tickets', true ) );
		$tokens['ETN_EVENT_SPEAKERS']      = $this->user_name_list( $event_id, 'etn_event_speaker' );
		$tokens['ETN_EVENT_ORGANIZERS']    = $this->user_name_list( $event_id, 'etn_event_organizer' );
		$tokens['ETN_EVENT_BANNER_URL']    = (string) get_post_meta( $event_id, 'event_banner', true );
		$tokens['ETN_EVENT_LOGO_URL']      = (string) get_post_meta( $event_id, 'etn_event_logo', true );

		return $tokens;
	}

	/**
	 * Order tokens.
	 *
	 * @return array
	 */
	public function order_tokens() {
		return array(
			array(
				'tokenId'   => 'ETN_ORDER_ID',
				'tokenName' => esc_html_x( 'Order ID', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ETN_ORDER_STATUS',
				'tokenName' => esc_html_x( 'Order status', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'ETN_ORDER_DISCOUNT_TOTAL',
				'tokenName' => esc_html_x( 'Order discount total', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'ETN_ORDER_TAX_TOTAL',
				'tokenName' => esc_html_x( 'Order tax total', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'ETN_ORDER_CURRENCY',
				'tokenName' => esc_html_x( 'Order currency', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ORDER_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ORDER_COUPON_CODE',
				'tokenName' => esc_html_x( 'Coupon code', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_CUSTOMER_FIRST_NAME',
				'tokenName' => esc_html_x( 'Customer first name', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_CUSTOMER_LAST_NAME',
				'tokenName' => esc_html_x( 'Customer last name', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_CUSTOMER_EMAIL',
				'tokenName' => esc_html_x( 'Customer email', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ETN_CUSTOMER_PHONE',
				'tokenName' => esc_html_x( 'Customer phone', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'tel',
			),
			array(
				'tokenId'   => 'ETN_ORDER_TICKET_NAMES',
				'tokenName' => esc_html_x( 'Ticket names', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ORDER_TICKET_QUANTITY',
				'tokenName' => esc_html_x( 'Ticket quantity', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ETN_ORDER_ATTENDEE_COUNT',
				'tokenName' => esc_html_x( 'Attendee count', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate the order tokens straight from post meta.
	 *
	 * Reads meta rather than OrderModel so the token map resolves identically
	 * whether or not Eventin's autoloader has the model in scope, and so a
	 * deleted order degrades to an empty keyset instead of a fatal.
	 *
	 * @param int $order_id The order post ID.
	 *
	 * @return array
	 */
	public function hydrate_order_tokens( $order_id ) {

		$order_id = absint( $order_id );

		$tokens = array(
			'ETN_ORDER_ID'              => $order_id,
			'ETN_ORDER_STATUS'          => '',
			'ETN_ORDER_TOTAL'           => '',
			'ETN_ORDER_DISCOUNT_TOTAL'  => '',
			'ETN_ORDER_TAX_TOTAL'       => '',
			'ETN_ORDER_CURRENCY'        => '',
			'ETN_ORDER_PAYMENT_METHOD'  => '',
			'ETN_ORDER_COUPON_CODE'     => '',
			'ETN_CUSTOMER_FIRST_NAME'   => '',
			'ETN_CUSTOMER_LAST_NAME'    => '',
			'ETN_CUSTOMER_EMAIL'        => '',
			'ETN_CUSTOMER_PHONE'        => '',
			'ETN_ORDER_TICKET_NAMES'    => '',
			'ETN_ORDER_TICKET_QUANTITY' => 0,
			'ETN_ORDER_ATTENDEE_COUNT'  => 0,
		);

		if ( 0 === $order_id || Eventin_Helpers::ORDER_POST_TYPE !== get_post_type( $order_id ) ) {
			return $tokens;
		}

		$tokens['ETN_ORDER_STATUS']         = (string) get_post_meta( $order_id, 'status', true );
		$tokens['ETN_ORDER_TOTAL']          = (string) get_post_meta( $order_id, 'total_price', true );
		$tokens['ETN_ORDER_DISCOUNT_TOTAL'] = (string) get_post_meta( $order_id, 'discount_total', true );
		$tokens['ETN_ORDER_TAX_TOTAL']      = (string) get_post_meta( $order_id, 'tax_total', true );
		$tokens['ETN_ORDER_CURRENCY']       = (string) get_post_meta( $order_id, 'currency', true );
		$tokens['ETN_ORDER_PAYMENT_METHOD'] = (string) get_post_meta( $order_id, 'payment_method', true );
		$tokens['ETN_ORDER_COUPON_CODE']    = (string) get_post_meta( $order_id, 'coupon_code', true );
		$tokens['ETN_CUSTOMER_FIRST_NAME']  = (string) get_post_meta( $order_id, 'customer_fname', true );
		$tokens['ETN_CUSTOMER_LAST_NAME']   = (string) get_post_meta( $order_id, 'customer_lname', true );
		$tokens['ETN_CUSTOMER_EMAIL']       = (string) get_post_meta( $order_id, 'customer_email', true );
		$tokens['ETN_CUSTOMER_PHONE']       = (string) get_post_meta( $order_id, 'customer_phone', true );

		$event_id = absint( get_post_meta( $order_id, 'event_id', true ) );
		$helpers  = new Eventin_Helpers();
		$names    = array();
		$quantity = 0;

		foreach ( $this->get_order_tickets( $order_id ) as $row ) {

			$slug      = (string) ( $row['ticket_slug'] ?? '' );
			$variation = $helpers->get_ticket_variation( $event_id, $slug );
			$names[]   = (string) ( $variation['etn_ticket_name'] ?? $slug );
			$quantity += absint( $row['ticket_quantity'] ?? 0 );
		}

		$tokens['ETN_ORDER_TICKET_NAMES']    = implode( ', ', array_filter( $names ) );
		$tokens['ETN_ORDER_TICKET_QUANTITY'] = $quantity;
		$tokens['ETN_ORDER_ATTENDEE_COUNT']  = $this->count_order_attendees( $order_id );

		return $tokens;
	}

	/**
	 * Attendee tokens.
	 *
	 * @return array
	 */
	public function attendee_tokens() {
		return array(
			array(
				'tokenId'   => 'ETN_ATTENDEE_ID',
				'tokenName' => esc_html_x( 'Attendee ID', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_NAME',
				'tokenName' => esc_html_x( 'Attendee name', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_EMAIL',
				'tokenName' => esc_html_x( 'Attendee email', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_PHONE',
				'tokenName' => esc_html_x( 'Attendee phone', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'tel',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_TICKET_NAME',
				'tokenName' => esc_html_x( 'Ticket name', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_TICKET_SLUG',
				'tokenName' => esc_html_x( 'Ticket slug', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_TICKET_PRICE',
				'tokenName' => esc_html_x( 'Ticket price', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'float',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_UNIQUE_TICKET_ID',
				'tokenName' => esc_html_x( 'Unique ticket ID', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_TICKET_STATUS',
				'tokenName' => esc_html_x( 'Ticket status', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_PAYMENT_STATUS',
				'tokenName' => esc_html_x( 'Attendee payment status', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ETN_ATTENDEE_SEAT',
				'tokenName' => esc_html_x( 'Attendee seat', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the attendee tokens.
	 *
	 * @param int $attendee_id The attendee post ID.
	 *
	 * @return array
	 */
	public function hydrate_attendee_tokens( $attendee_id ) {

		$attendee_id = absint( $attendee_id );

		$tokens = array(
			'ETN_ATTENDEE_ID'               => $attendee_id,
			'ETN_ATTENDEE_NAME'             => '',
			'ETN_ATTENDEE_EMAIL'            => '',
			'ETN_ATTENDEE_PHONE'            => '',
			'ETN_ATTENDEE_TICKET_NAME'      => '',
			'ETN_ATTENDEE_TICKET_SLUG'      => '',
			'ETN_ATTENDEE_TICKET_PRICE'     => '',
			'ETN_ATTENDEE_UNIQUE_TICKET_ID' => '',
			'ETN_ATTENDEE_TICKET_STATUS'    => '',
			'ETN_ATTENDEE_PAYMENT_STATUS'   => '',
			'ETN_ATTENDEE_SEAT'             => '',
		);

		if ( 0 === $attendee_id || Eventin_Helpers::ATTENDEE_POST_TYPE !== get_post_type( $attendee_id ) ) {
			return $tokens;
		}

		$tokens['ETN_ATTENDEE_NAME']             = (string) get_post_meta( $attendee_id, 'etn_name', true );
		$tokens['ETN_ATTENDEE_EMAIL']            = (string) get_post_meta( $attendee_id, 'etn_email', true );
		$tokens['ETN_ATTENDEE_PHONE']            = (string) get_post_meta( $attendee_id, 'etn_phone', true );
		$tokens['ETN_ATTENDEE_TICKET_NAME']      = (string) get_post_meta( $attendee_id, 'ticket_name', true );
		$tokens['ETN_ATTENDEE_TICKET_SLUG']      = (string) get_post_meta( $attendee_id, 'ticket_slug', true );
		$tokens['ETN_ATTENDEE_TICKET_PRICE']     = (string) get_post_meta( $attendee_id, 'etn_ticket_price', true );
		$tokens['ETN_ATTENDEE_UNIQUE_TICKET_ID'] = (string) get_post_meta( $attendee_id, 'etn_unique_ticket_id', true );
		$tokens['ETN_ATTENDEE_TICKET_STATUS']    = (string) get_post_meta( $attendee_id, Eventin_Helpers::ATTENDEE_TICKET_STATUS_META, true );
		$tokens['ETN_ATTENDEE_PAYMENT_STATUS']   = (string) get_post_meta( $attendee_id, Eventin_Helpers::ATTENDEE_PAYMENT_STATUS_META, true );
		$tokens['ETN_ATTENDEE_SEAT']             = (string) get_post_meta( $attendee_id, 'attendee_seat', true );

		return $tokens;
	}

	/**
	 * The check-in time token, used only by the check-in trigger.
	 *
	 * @return array
	 */
	public function checkin_tokens() {
		return array(
			array(
				'tokenId'   => 'ETN_CHECKIN_TIME',
				'tokenName' => esc_html_x( 'Check-in time', 'Eventin', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the check-in time.
	 *
	 * Eventin Pro writes this meta with a broken format string —
	 * `date_i18n( 'Y-m-d g:i:m', ... )` at eventin-pro/core/attendee/hooks.php:979
	 * puts the MONTH where the minutes belong, so a 14:32 check-in in September
	 * is stored as "…:09". Reparse and re-render when the stored value carries
	 * that signature; pass anything else through untouched.
	 *
	 * @param int $attendee_id The attendee post ID.
	 *
	 * @return array
	 */
	public function hydrate_checkin_tokens( $attendee_id ) {

		$raw = (string) get_post_meta( absint( $attendee_id ), Eventin_Helpers::ATTENDEE_CHECKIN_TIME_META, true );

		return array( 'ETN_CHECKIN_TIME' => $this->normalize_checkin_time( $raw ) );
	}

	/**
	 * Repair Eventin's malformed check-in timestamps.
	 *
	 * @param string $raw The stored value.
	 *
	 * @return string
	 */
	private function normalize_checkin_time( $raw ) {

		$raw = trim( (string) $raw );

		if ( '' === $raw ) {
			return '';
		}

		// "Y-m-d g:i:m" renders as e.g. "2026-09-01 2:32:09" — hour, minute, then
		// the month masquerading as seconds. Drop the third segment and keep the
		// date and the real hour:minute.
		if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})\s+(\d{1,2}):(\d{2}):(\d{2})$/', $raw, $matches ) ) {
			return sprintf( '%1$s %2$02d:%3$s', $matches[1], absint( $matches[2] ), $matches[3] );
		}

		return $raw;
	}

	/**
	 * The event's street address.
	 *
	 * `etn_event_location` is an ARRAY, not a string — Eventin stores
	 * `address`, `latitude`, `longitude` and `integration` under that one meta
	 * key, and reads the address back as `$location['address']`
	 * (core/event/event-model.php:306, core/Emails/AttendeeOrderEmail.php:84).
	 * Casting the raw meta to a string yields the literal text "Array".
	 *
	 * This deliberately does NOT reproduce Event_Model::get_address()'s extra
	 * gate on `event_type` being offline/hybrid (:304). That gate makes the
	 * address disappear for an event whose type is unset — common on events
	 * imported or created before the field existed — and an address on file is
	 * what a recipe builder means by "Event location".
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return string
	 */
	private function get_event_address( $event_id ) {

		$location = get_post_meta( absint( $event_id ), 'etn_event_location', true );

		if ( is_array( $location ) ) {
			return (string) ( $location['address'] ?? '' );
		}

		return (string) $location;
	}

	/**
	 * Comma-separated display names for a meta key holding WP user IDs.
	 *
	 * Speakers and organizers are WordPress users, not posts — roles
	 * `etn-speaker` / `etn-organizer` (core/speaker/speaker-role.php:57) — and
	 * the meta holds an ARRAY of their user IDs, which Eventin iterates in
	 * Event_Model::get_speakers() / get_organizers() (event-model.php:582, 605).
	 * Casting that meta to a string yields the literal text "Array", the same
	 * trap as `etn_event_location`.
	 *
	 * Resolved through core `get_userdata()` rather than Eventin's own
	 * User_Model so the token still works if that class is ever moved.
	 *
	 * @param int    $event_id The event post ID.
	 * @param string $meta_key The meta key holding the user IDs.
	 *
	 * @return string
	 */
	private function user_name_list( $event_id, $meta_key ) {

		$ids = get_post_meta( absint( $event_id ), $meta_key, true );

		if ( ! is_array( $ids ) ) {
			$ids = '' === (string) $ids ? array() : array( $ids );
		}

		$names = array();

		foreach ( $ids as $id ) {

			$user = get_userdata( absint( $id ) );

			if ( $user instanceof \WP_User ) {
				$names[] = (string) $user->display_name;
			}
		}

		return implode( ', ', array_filter( $names ) );
	}

	/**
	 * An order's raw ticket rows.
	 *
	 * @param int $order_id The order post ID.
	 *
	 * @return array
	 */
	private function get_order_tickets( $order_id ) {

		$tickets = get_post_meta( absint( $order_id ), 'tickets', true );

		return is_array( $tickets ) ? $tickets : array();
	}

	/**
	 * How many attendees an order produced.
	 *
	 * @param int $order_id The order post ID.
	 *
	 * @return int
	 */
	private function count_order_attendees( $order_id ) {

		$attendees = get_posts(
			array(
				'post_type'        => Eventin_Helpers::ATTENDEE_POST_TYPE,
				'post_status'      => 'any',
				'fields'           => 'ids',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'posts_per_page'   => 999,
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'         => 'eventin_order_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'       => (string) absint( $order_id ),
			)
		);

		return is_array( $attendees ) ? count( $attendees ) : 0;
	}

	/**
	 * Comma-separated term names for a taxonomy.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return string
	 */
	private function term_list( $post_id, $taxonomy ) {

		$terms = get_the_terms( absint( $post_id ), $taxonomy );

		if ( ! is_array( $terms ) ) {
			return '';
		}

		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}
}
