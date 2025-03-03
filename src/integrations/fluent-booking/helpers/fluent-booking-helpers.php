<?php

namespace Uncanny_Automator\Integrations\Fluent_Booking;

use FluentBooking\App\Models\Calendar;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\DateTimeHelper;

/**
 * Class Fluent_Booking_Helpers
 *
 * @pacakge Uncanny_Automator
 */
class Fluent_Booking_Helpers {

	/**
	 * @return array
	 */
	public function get_fluent_booking_common_tokens() {
		return array(
			array(
				'tokenId'   => 'booking_title',
				'tokenName' => esc_html__( 'Meeting title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'invitee_name',
				'tokenName' => esc_html__( 'Invitee name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'invitee_email',
				'tokenName' => esc_html__( 'Invitee email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'invitee_timezone',
				'tokenName' => esc_html__( 'Invitee timezone', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'booked_at',
				'tokenName' => esc_html__( 'Booked at', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'meeting_host_name',
				'tokenName' => esc_html__( 'Meeting host name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'meeting_host_email',
				'tokenName' => esc_html__( 'Meeting host email', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'meeting_time',
				'tokenName' => esc_html__( 'Meeting time', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'meeting_duration',
				'tokenName' => esc_html__( 'Meeting duration', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'location',
				'tokenName' => esc_html__( 'Location', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'status',
				'tokenName' => esc_html__( 'Status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'booking_url',
				'tokenName' => esc_html__( 'Booking URL', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'note',
				'tokenName' => esc_html__( 'Note', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @return array[]
	 */
	public function get_fluent_booking_cancellation_tokens() {
		return array(
			array(
				'tokenId'   => 'cancelled_at',
				'tokenName' => esc_html__( 'Cancellation date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'reason_for_cancellation',
				'tokenName' => esc_html__( 'Cancellation reason', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param $booking
	 * @param $bookingData
	 *
	 * @return array
	 */
	public function parse_common_token_values( $booking, $bookingData ) {
		$date      = $booking->getAttribute( 'created_at' );
		$user_host = get_userdata( $bookingData['host_user_id'] );

		return array(
			'invitee_name'       => $bookingData['first_name'] . ' ' . $bookingData['last_name'],
			'invitee_email'      => $bookingData['email'],
			'invitee_timezone'   => $bookingData['person_time_zone'],
			'booked_at'          => wp_date(
				sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ),
				$date->date
			),
			'meeting_host_name'  => $user_host->first_name . ' ' . $user_host->last_name,
			'meeting_host_email' => $user_host->user_email,
			'meeting_time'       => $booking->getFullBookingDateTimeText( $bookingData['person_time_zone'] ),
			'meeting_duration'   => $bookingData['slot_minutes'],
			'location'           => $booking->getLocationAsText(),
			'status'             => ucfirst( $bookingData['status'] ),
			'booking_url'        => $booking->getConfirmationUrl(),
			'note'               => $bookingData['message'],
			'booking_title'      => $booking->getBookingTitle(),
		);

	}

	/**
	 * @param $cancellation_reason
	 * @param $cancellation_date
	 *
	 * @return array
	 */
	public function parse_cancellation_token_values( $cancellation_reason, $cancellation_date ) {
		return array(
			'cancelled_at'            => wp_date(
				sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) ),
				strtotime( $cancellation_date )
			),
			'reason_for_cancellation' => $cancellation_reason,
		);

	}

	/**
	 * Get all hosts
	 *
	 * @param $is_any
	 *
	 * @return array
	 */
	public function get_all_hosts_option( $is_any = false ) {
		$load_hosts = Calendar::where( 'type', '!=', 'team' )->pluck( 'title', 'user_id' )->toArray();
		$options    = array();
		if ( true === $is_any ) {
			$options[] = array(
				'text'  => _x( 'Any host', 'FluentBooking', 'uncanny-automator' ),
				'value' => '-1',
			);
		}
		foreach ( $load_hosts as $title => $user_id ) {
			$options[] = array(
				'text'  => $user_id,
				'value' => $title,
			);
		}

		return $options;
	}

	/**
	 * Get all active events
	 *
	 * @param $is_any
	 *
	 * @return array
	 */
	public function get_all_events_option( $is_any = false ) {
		$load_events = CalendarSlot::pluck( 'id', 'title' )->toArray();
		$options     = array();

		if ( true === $is_any ) {
			$options[] = array(
				'text'  => _x( 'Any event', 'FluentBooking', 'uncanny-automator' ),
				'value' => '-1',
			);
		}
		foreach ( $load_events as $title => $event_id ) {
			$options[] = array(
				'value' => $event_id,
				'text'  => $title,
			);
		}

		return $options;
	}

	/**
	 * @param $is_any
	 *
	 * @return array
	 */
	public function get_all_statuses( $is_any = false ) {
		$options = array(
			array(
				'value' => 'scheduled',
				'text'  => esc_attr_x( 'Scheduled', 'FluentBooking', 'uncanny-automator' ),
			),
			array(
				'value' => 'pending',
				'text'  => esc_attr_x( 'Pending', 'FluentBooking', 'uncanny-automator' ),
			),
			array(
				'value' => 'completed',
				'text'  => esc_attr_x( 'Completed', 'FluentBooking', 'uncanny-automator' ),
			),
		);

		if ( true === $is_any ) {
			$any_option = array(
				array(
					'text'  => _x( 'Any status', 'FluentBooking', 'uncanny-automator' ),
					'value' => '-1',
				),
			);
			$options    = array_merge( $any_option, $options );
		}

		return $options;
	}

	/**
	 * @return void
	 */
	public function get_event_meeting_duration() {
		Automator()->utilities->verify_nonce();
		// Ignore nonce, already handled above.
		$event_id       = $this->get_posted_booking_event_id();
		$options        = array();
		$event_duration = CalendarSlot::where( 'id', $event_id )->pluck( 'duration' )->toArray();

		foreach ( $event_duration as $duration ) {
			$options[] = array(
				'value' => $duration,
				'text'  => $duration,
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );
	}

	/**
	 * @return void
	 */
	public function get_event_meeting_location() {
		Automator()->utilities->verify_nonce();
		// Ignore nonce, already handled above.
		$event_id       = $this->get_posted_booking_event_id();
		$options        = array();
		$event_location = CalendarSlot::where( 'id', $event_id )->pluck( 'location_settings' )->toArray();
		$event_location = maybe_unserialize( $event_location[0] );
		foreach ( $event_location as $location ) {
			$options[] = array(
				'value' => $location['type'],
				'text'  => $location['title'],
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );
	}

	/**
	 * Get all timezones
	 *
	 * @param $is_any
	 *
	 * @return array
	 */
	public function get_all_timezones_option( $is_any = false ) {
		$options = array();
		if ( true === $is_any ) {
			$options[] = array(
				'text'  => _x( 'Any timezone', 'FluentBooking', 'uncanny-automator' ),
				'value' => '-1',
			);
		}
		$ungrouped_timezones = DateTimeHelper::getFlatGroupedTimeZones();

		foreach ( $ungrouped_timezones as $timezone ) {
			$options[] = array(
				'text'  => $timezone['label'],
				'value' => $timezone['value'],
			);
		}

		return $options;
	}

	/**
	 * Get the booking event ID from POST data
	 *
	 * @return string The sanitized booking event ID
	 */
	private function get_posted_booking_event_id() {
		$values = automator_filter_input_array( 'values', INPUT_POST );
		return isset( $values['FB_BOOKING_EVENT'] ) ? sanitize_text_field( $values['FB_BOOKING_EVENT'] ) : '';
	}

}
