<?php

namespace Uncanny_Automator\Integrations\Fluent_Booking;

use FluentBooking\App\Hooks\Handlers\TimeSlotServiceHandler;
use FluentBooking\App\Models\Calendar;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\BookingService;
use FluentBooking\App\Services\DateTimeHelper;
use FluentBooking\Framework\Support\Arr;

/**
 * Class FLUENT_BOOKING_NEW_BOOKING
 *
 * @pacakge Uncanny_Automator
 */
class FLUENT_BOOKING_NEW_BOOKING extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	/**
	 * @return void
	 */
	protected function setup_action() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'FLUENT_BOOKING' );
		$this->set_action_code( 'FB_NEW_BOOKING' );
		$this->set_action_meta( 'FB_BOOKING' );
		$this->set_requires_user( true );
		// translators: 1: Event name
		$this->set_sentence( sprintf( esc_attr_x( 'Add {{a meeting:%1$s}}', 'FluentBooking', 'uncanny-automator' ), $this->get_action_meta() . '_EVENT:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Add a meeting', 'FluentBooking', 'uncanny-automator' ) );
	}

	/**
	 * Define the Action's options
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'input_type'     => 'select',
				'option_code'    => $this->get_action_meta() . '_EVENT',
				'supports_token' => false,
				'label'          => esc_html__( 'Event', 'uncanny-automator' ),
				'options'        => $this->helpers->get_all_events_option(),
			),
			array(
				'input_type'     => 'select',
				'option_code'    => $this->get_action_meta() . '_TIMEZONE',
				'supports_token' => false,
				'label'          => esc_html__( 'Timezone', 'uncanny-automator' ),
				'options'        => $this->helpers->get_all_timezones_option(),
			),
			array(
				'input_type'     => 'select',
				'option_code'    => $this->get_action_meta() . '_DURATION',
				'supports_token' => false,
				'label'          => esc_html__( 'Duration', 'uncanny-automator' ),
				'ajax'           => array(
					'endpoint'      => 'get_event_meeting_duration',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( $this->get_action_meta() . '_EVENT' ),
				),
			),
			array(
				'input_type'     => 'select',
				'option_code'    => $this->get_action_meta() . '_LOCATION',
				'supports_token' => false,
				'label'          => esc_html__( 'Location', 'uncanny-automator' ),
				'ajax'           => array(
					'endpoint'      => 'get_event_meeting_location',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( $this->get_action_meta() . '_EVENT' ),
				),
			),
			array(
				'input_type'     => 'text',
				'required'       => false,
				'option_code'    => $this->get_action_meta() . '_LOCATION_DESCRIPTION',
				'supports_token' => false,
				'label'          => esc_html__( 'Attendee address', 'uncanny-automator' ),
				'description'    => esc_html__( 'If the meeting is in-person then provide the attendee address.', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'date',
				'option_code'    => $this->get_action_meta() . '_START_DATE',
				'supports_token' => false,
				'required'       => true,
				'label'          => esc_html__( 'Start date', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'time',
				'option_code'    => $this->get_action_meta() . '_START_TIME',
				'supports_token' => false,
				'required'       => true,
				'label'          => esc_html__( 'Start time', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'checkbox',
				'option_code'    => $this->get_action_meta() . '_IGNORE_AVAILABILITY',
				'required'       => false,
				'supports_token' => false,
				'label'          => esc_html__( 'Ignore availability', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'select',
				'option_code'    => $this->get_action_meta() . '_STATUS',
				'supports_token' => false,
				'label'          => esc_html__( 'Status', 'uncanny-automator' ),
				'options'        => $this->helpers->get_all_statuses(),
			),
			array(
				'input_type'     => 'email',
				'option_code'    => $this->get_action_meta() . '_EMAIL',
				'supports_token' => true,
				'required'       => true,
				'label'          => esc_html__( 'Attendee email', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'text',
				'option_code'    => $this->get_action_meta() . '_NAME',
				'supports_token' => true,
				'label'          => esc_html__( 'Attendee name', 'uncanny-automator' ),
			),
			array(
				'input_type'     => 'textarea',
				'required'       => false,
				'option_code'    => $this->get_action_meta() . '_SUBJECT',
				'supports_token' => true,
				'label'          => esc_html__( 'Agenda', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param       $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$event_id       = isset( $parsed[ $this->get_action_meta() . '_EVENT' ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() . '_EVENT' ] ) : 0;
		$calendar_event = CalendarSlot::findOrfail( $event_id );

		if ( $calendar_event->status !== 'active' ) {
			$this->add_log_error( 'Sorry, the host is not accepting any new bookings at the moment.' );

			return false;
		}

		$attendee_name        = isset( $parsed[ $this->get_action_meta() . '_NAME' ] ) ? $parsed[ $this->get_action_meta() . '_NAME' ] : '';
		$subject              = isset( $parsed[ $this->get_action_meta() . '_SUBJECT' ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() . '_SUBJECT' ] ) : '';
		$attendee_email       = isset( $parsed[ $this->get_action_meta() . '_EMAIL' ] ) ? sanitize_email( $parsed[ $this->get_action_meta() . '_EMAIL' ] ) : '';
		$event_duration       = isset( $parsed[ $this->get_action_meta() . '_DURATION' ] ) ? absint( $parsed[ $this->get_action_meta() . '_DURATION' ] ) : 0;
		$timezone             = isset( $parsed[ $this->get_action_meta() . '_TIMEZONE' ] ) ? $parsed[ $this->get_action_meta() . '_TIMEZONE' ] : 'UTC';
		$status               = isset( $parsed[ $this->get_action_meta() . '_STATUS' ] ) ? $parsed[ $this->get_action_meta() . '_STATUS' ] : '';
		$start_date           = isset( $parsed[ $this->get_action_meta() . '_START_DATE' ] ) ? $parsed[ $this->get_action_meta() . '_START_DATE' ] : '';
		$start_time           = isset( $parsed[ $this->get_action_meta() . '_START_TIME' ] ) ? $parsed[ $this->get_action_meta() . '_START_TIME' ] : '';
		$location_type        = isset( $parsed[ $this->get_action_meta() . '_LOCATION' ] ) ? $parsed[ $this->get_action_meta() . '_LOCATION' ] : '';
		$location_description = isset( $parsed[ $this->get_action_meta() . '_LOCATION_DESCRIPTION' ] ) ? $parsed[ $this->get_action_meta() . '_LOCATION_DESCRIPTION' ] : '';
		$ignore_availability  = isset( $parsed[ $this->get_action_meta() . '_IGNORE_AVAILABILITY' ] ) ? $parsed[ $this->get_action_meta() . '_IGNORE_AVAILABILITY' ] : false;
		$event_time           = $start_date . ' ' . $start_time;
		$duration             = $calendar_event->getDuration( $event_duration );
		$event_start          = DateTimeHelper::convertToUtc( $event_time, $timezone );
		$event_end            = gmdate( 'Y-m-d H:i:s', strtotime( $event_start ) + ( $duration * 60 ) );

		$booking_data = array(
			'person_time_zone' => $timezone,
			'person_user_id'   => $user_id,
			'start_time'       => $event_start,
			'name'             => $attendee_name,
			'email'            => $attendee_email,
			'message'          => $subject,
			'status'           => $status,
			'source'           => 'uncanny-automator',
			'event_type'       => $calendar_event->event_type,
			'slot_minutes'     => $duration,
			'host_user_id'     => $calendar_event->user_id,
		);

		$event_locations   = array();
		$location_settings = $calendar_event->location_settings;
		foreach ( $location_settings as $index => $location ) {
			$event_locations[ $location['type'] ] = $location;
		}

		$location_details['type'] = $location_type;
		if ( $location_type === 'phone_organizer' ) {
			$location_details['description'] = $event_locations[ $location_type ]['host_phone_number'];
		} elseif ( $location_type === 'phone_guest' ) {
			$booking_data['phone'] = $location_description;
		} elseif ( $location_type === 'in_person_guest' ) {
			$location_details['description'] = $location_description;
		} elseif ( in_array( $location_type, array( 'custom', 'in_person_organizer' ) ) ) {
			$location_details['description'] = $event_locations[ $location_type ]['description'];
		} elseif ( in_array( $location_type, array( 'google_meet', 'online_meeting', 'zoom_meeting', 'ms_teams' ) ) ) {
			$location_details['description'] = $event_locations[ $location_type ]['meeting_link'];
		}

		$booking_data['location_details'] = $location_details;
		if ( true === $ignore_availability ) {
			$time_slot_service = TimeSlotServiceHandler::initService( $calendar_event->calendar, $calendar_event );

			if ( is_wp_error( $time_slot_service ) ) {
				$this->add_log_error( $time_slot_service->get_error_message() );

				return false;
			}
			$is_spot_available = $time_slot_service->isSpotAvailable( $event_start, $event_end, $duration, $calendar_event->user_id );

			if ( ! $is_spot_available ) {
				$this->add_log_error( esc_attr_x( 'This selected time slot is no longer available, as it may have already been booked by someone else.', 'fluent-booking', 'uncanny-automator' ) );

				return false;
			}
			if ( $calendar_event->isTeamEvent() && ! $calendar_event->user_id ) {
				$booking_data['host_user_id'] = $time_slot_service->hostUserId;
			}
		}

		$booking = BookingService::createBooking( $booking_data, $calendar_event );

		if ( ! ( $booking ) ) {
			$this->add_log_error( "We couldn't create the booking. Please try again." );

			return false;
		}

		return true;
	}
}
