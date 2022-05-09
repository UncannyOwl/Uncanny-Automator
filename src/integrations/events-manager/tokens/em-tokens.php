<?php

namespace Uncanny_Automator;

use EM_Event_Locations\Event_Location;

/**
 *
 */
class Em_Tokens {

	/**
	 *
	 */
	public function __construct() {
		add_filter(
			'automator_maybe_trigger_eventsmanager_emevents_tokens',
			array(
				$this,
				'em_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_em_tokens' ), 999, 6 );
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed
	 */
	public function em_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_meta    = $args['meta'];
		$possible_tokens = array(
			$trigger_meta . '_ATTENDEE_NAME'       => esc_attr__( 'Attendee - Name', 'uncanny-automator' ),
			$trigger_meta . '_ATTENDEE_EMAIL'      => esc_attr__( 'Attendee - Email', 'uncanny-automator' ),
			$trigger_meta . '_ATTENDEE_PHONE'      => esc_attr__( 'Attendee - Phone', 'uncanny-automator' ),
			$trigger_meta . '_BOOKED_SPACES'       => esc_attr__( 'Booking - Spaces booked', 'uncanny-automator' ),
			$trigger_meta . '_COMMENT'             => esc_attr__( 'Booking - Attendee comments', 'uncanny-automator' ),
			$trigger_meta . '_PRICE_PAID'          => esc_attr__( 'Booking - Price paid', 'uncanny-automator' ),
			$trigger_meta . '_START_DATE'          => esc_attr__( 'Event - Start date', 'uncanny-automator' ),
			$trigger_meta . '_END_DATE'            => esc_attr__( 'Event - End date', 'uncanny-automator' ),
			$trigger_meta . '_TOTAL_SPACES'        => esc_attr__( 'Event - Total spaces', 'uncanny-automator' ),
			$trigger_meta . '_MAX_SPACES'          => esc_attr__( 'Event - Maximum spaces per booking', 'uncanny-automator' ),
			$trigger_meta . '_CONFIRMED_SPACES'    => esc_attr__( 'Event - Confirmed spaces', 'uncanny-automator' ),
			$trigger_meta . '_PENDING_SPACES'      => esc_attr__( 'Event - Pending spaces', 'uncanny-automator' ),
			$trigger_meta . '_AVAILABLE_SPACES'    => esc_attr__( 'Event - Available spaces', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_NAME'       => esc_attr__( 'Location - Name', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_ADDRESS'    => esc_attr__( 'Location - Address', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_TOWN'       => esc_attr__( 'Location - Town', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_STATE'      => esc_attr__( 'Location - State', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_POSTCODE'   => esc_attr__( 'Location - Postcode', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_REGION'     => esc_attr__( 'Location - Region', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_COUNTRY'    => esc_attr__( 'Location - Country', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_URL'        => esc_attr__( 'Location - URL', 'uncanny-automator' ),
			$trigger_meta . '_LOCATION_LINK_TITLE' => esc_attr__( 'Location - Link text', 'uncanny-automator' ),
		);
		$possible_tokens = apply_filters( 'automator_possible_tokens_' . $trigger_meta, $possible_tokens, $trigger_meta );
		$fields          = array();
		foreach ( $possible_tokens as $token_id => $token_name ) {
			$token_type = 'text';
			if ( $trigger_meta . '_ATTENDEE_EMAIL' === $token_id ) {
				$token_type = 'email';
			}
			$fields[] = array(
				'tokenId'         => $token_id,
				'tokenName'       => $token_name,
				'tokenType'       => $token_type,
				'tokenIdentifier' => $trigger_meta,
			);
		}

		return array_merge( $tokens, $fields );
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
	public function parse_em_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if (
			! in_array( 'EMEVENTS', $pieces, true ) &&
			! in_array( 'SELECTEDEVENT', $pieces, true ) &&
			! in_array( 'EVENTREGISTER', $pieces, true ) &&
			! in_array( 'ANONBOOKINGAPPROVED', $pieces, true ) &&
			! in_array( 'ANONEVENTREGISTER', $pieces, true )
		) {
			return $value;
		}
		if ( empty( $trigger_data ) ) {
			return $value;
		}
		foreach ( $trigger_data as $trigger ) {
			if ( empty( $trigger ) ) {
				continue;
			}

			$meta_key       = $pieces[2];
			$trigger_id     = absint( $trigger['ID'] );
			$trigger_log_id = absint( $replace_args['trigger_log_id'] );
			$parse_tokens   = array(
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'user_id'        => $user_id,
			);

			$value = Automator()->db->trigger->get_token_meta( $meta_key, $parse_tokens );
		}

		return $value;
	}

	/**
	 * @param $trigger_meta
	 * @param $args
	 * @param \EM_Booking $em_booking_obj
	 *
	 * @return void
	 */
	public static function em_save_tokens( $trigger_meta, $args, \EM_Booking $em_booking_obj ) {
		$em_event_obj = $em_booking_obj->get_event();
		$person       = $em_booking_obj->person->data;
		$user_id      = $em_booking_obj->person_id;

		$location_name      = '-';
		$location_address   = '-';
		$location_town      = '-';
		$location_state     = '-';
		$location_postcode  = '-';
		$location_region    = '-';
		$location_country   = '-';
		$location_url       = '-';
		$location_link_text = '-';

		$location_obj = $em_event_obj->get_location();

		if ( $em_event_obj instanceof \EM_Event ) {
			if ( 0 !== $em_event_obj->location_id && $location_obj instanceof \EM_Location && 'url' !== $em_event_obj->event_location_type ) {
				$location_name     = $location_obj->location_name;
				$location_address  = $location_obj->location_address;
				$location_town     = $location_obj->location_town;
				$location_state    = $location_obj->location_state;
				$location_postcode = $location_obj->location_postcode;
				$location_region   = $location_obj->location_region;
				$location_country  = $location_obj->location_country;
			}
			if ( 'url' === $em_event_obj->event_location_type ) {
				/** @var \EM_Event_Locations\Event_Location $event_location_obj */
				$event_location_obj = $em_event_obj->event_location;
				$data               = $event_location_obj->data;
				$location_url       = ! empty( $data['url'] ) ? $data['url'] : '-';
				$location_link_text = ! empty( $data['text'] ) ? $data['text'] : '-';
			}
		}

		$trigger_meta_args = array(
			'user_id'        => $user_id,
			'trigger_id'     => $args['trigger_id'],
			'trigger_log_id' => $args['get_trigger_id'],
			'run_number'     => $args['run_number'],
		);

		$trigger_meta_args['meta_key']   = $trigger_meta . '_ATTENDEE_NAME';
		$trigger_meta_args['meta_value'] = maybe_serialize( $person->display_name );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_ATTENDEE_EMAIL';
		$trigger_meta_args['meta_value'] = maybe_serialize( $person->user_email );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_ATTENDEE_PHONE';
		$trigger_meta_args['meta_value'] = maybe_serialize( $person->phone );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta;
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_booking_obj->event->event_name );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_ID';
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_booking_obj->event->event_id );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_URL';
		$trigger_meta_args['meta_value'] = maybe_serialize( get_permalink( $em_booking_obj->event->post_id ) );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_THUMB_URL';
		$trigger_meta_args['meta_value'] = ( empty( get_the_post_thumbnail_url( $em_booking_obj->event->post_id, 'full' ) ) ) ? '-' : maybe_serialize( get_the_post_thumbnail_url( $em_booking_obj->event->post_id, 'full' ) );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_THUMB_ID';
		$trigger_meta_args['meta_value'] = ( empty( get_post_thumbnail_id( $em_booking_obj->event->post_id ) ) ) ? '-' : maybe_serialize( get_post_thumbnail_id( $em_booking_obj->event->post_id ) );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_BOOKED_SPACES';
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_booking_obj->get_spaces() );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_PRICE_PAID';
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_booking_obj->get_price() );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_COMMENT';
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_booking_obj->booking_comment );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_START_DATE';
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_event_obj->event_start_date );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_END_DATE';
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_event_obj->event_end_date );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_NAME';
		$trigger_meta_args['meta_value'] = maybe_serialize( $location_name );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_ADDRESS';
		$trigger_meta_args['meta_value'] = maybe_serialize( $location_address );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_TOWN';
		$trigger_meta_args['meta_value'] = maybe_serialize( $location_town );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_STATE';
		$trigger_meta_args['meta_value'] = maybe_serialize( $location_state );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_POSTCODE';
		$trigger_meta_args['meta_value'] = maybe_serialize( $location_postcode );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_REGION';
		$trigger_meta_args['meta_value'] = maybe_serialize( $location_region );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_COUNTRY';
		$trigger_meta_args['meta_value'] = maybe_serialize( $location_country );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_CONFIRMED_SPACES';
		$trigger_meta_args['meta_value'] = ( $em_event_obj->get_bookings()->get_booked_spaces() > 0 ) ? $em_event_obj->get_bookings()->get_booked_spaces() : 0;
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_PENDING_SPACES';
		$trigger_meta_args['meta_value'] = ( $em_event_obj->get_bookings()->get_pending_spaces() > 0 ) ? $em_event_obj->get_bookings()->get_pending_spaces() : 0;
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_AVAILABLE_SPACES';
		$trigger_meta_args['meta_value'] = ( $em_event_obj->get_bookings()->get_available_spaces() > 0 ) ? $em_event_obj->get_bookings()->get_available_spaces() : 0;
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_TOTAL_SPACES';
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_event_obj->get_bookings()->get_spaces() );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_MAX_SPACES';
		$trigger_meta_args['meta_value'] = maybe_serialize( $em_event_obj->event_rsvp_spaces );
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_URL';
		$trigger_meta_args['meta_value'] = $location_url;
		Automator()->insert_trigger_meta( $trigger_meta_args );

		$trigger_meta_args['meta_key']   = $trigger_meta . '_LOCATION_LINK_TITLE';
		$trigger_meta_args['meta_value'] = $location_link_text;
		Automator()->insert_trigger_meta( $trigger_meta_args );

		do_action( 'automator_after_save_tokens_' . $trigger_meta, $trigger_meta, $trigger_meta_args, $em_booking_obj );
	}
}
