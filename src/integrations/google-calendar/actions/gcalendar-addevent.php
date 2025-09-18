<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

use DateTime;
use Uncanny_Automator\Recipe\Action;
use Uncanny_Automator\Recipe\Action_Tokens;

/**
 * Class GCALENDAR_ADDEVENT
 *
 * @package Uncanny_Automator
 */
class GCALENDAR_ADDEVENT extends Action {

	/**
	 * The prefix for the action fields.
	 *
	 * @var string
	 */
	const PREFIX = 'GCALENDAR_ADDEVENT';

	/**
	 * The helper.
	 *
	 * @var \Uncanny_Automator\Integrations\Google_Calendar\Google_Calendar_Helpers
	 */
	protected $helper;

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		/** @var \Uncanny_Automator\Integrations\Google_Calendar\Google_Calendar_Helpers $helper */
		$this->helper = array_shift( $this->dependencies );

		$this->set_integration( 'GOOGLE_CALENDAR' );

		$this->set_action_code( self::PREFIX . '_CODE' );

		$this->set_action_meta( self::PREFIX . '_META' );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/google-calendar/' ) );

		$this->set_is_pro( false );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
			/* translators: %1$s: Event title, %2$s: Calendar ID */
				esc_attr_x( 'Add {{an event:%1$s}} to {{a Google Calendar:%2$s}}', 'Google Calendar', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->get_formatted_code( 'calendar' ) . ':' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Add {{an event}} to {{a Google Calendar}}', 'Google Calendar', 'uncanny-automator' ) );

		$this->set_background_processing( true );

		// Set action tokens for event information
		$this->set_action_tokens(
			array(
				'EVENT_ID' => array(
					'name' => esc_html_x( 'Event ID', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'EVENT_LINK' => array(
					'name' => esc_html_x( 'Event link', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'url',
				),
				'EVENT_START' => array(
					'name' => esc_html_x( 'Event start time', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'EVENT_END' => array(
					'name' => esc_html_x( 'Event end time', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'CALENDAR_ID' => array(
					'name' => esc_html_x( 'Calendar ID', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'GOOGLE_CALENDAR_LINK' => array(
					'name' => esc_html_x( 'Add to Google Calendar URL', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'url',
				),
				'GOOGLE_CALENDAR_ANCHOR' => array(
					'name' => esc_html_x( 'Add to Google Calendar anchor link', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'APPLE_CALENDAR_LINK' => array(
					'name' => esc_html_x( 'Add to Apple Calendar URL', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'url',
				),
				'APPLE_CALENDAR_ANCHOR' => array(
					'name' => esc_html_x( 'Add to Apple Calendar anchor link', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'OUTLOOK_CALENDAR_LINK' => array(
					'name' => esc_html_x( 'Add to Outlook URL', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'url',
				),
				'OUTLOOK_CALENDAR_ANCHOR' => array(
					'name' => esc_html_x( 'Add to Outlook anchor link', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'COMBINED_CALENDAR_LINKS' => array(
					'name' => esc_html_x( 'All calendar platform links', 'Google Calendar', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->action_code
		);
	}

	/**
	 * Get formatted code.
	 *
	 * @param  string $option_code The option code.
	 *
	 * @return string The prefix underscore option code string.
	 */
	protected function get_formatted_code( $option_code = '' ) {

		return sprintf( '%1$s_%2$s', self::PREFIX, $option_code );
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array The options array.
	 */
	public function options() {

		return array(
			// Calendar list.
			array(
				'option_code'           => $this->get_formatted_code( 'calendar' ),
				/* translators: Calendar field */
				'label'                 => esc_attr_x( 'Calendar', 'Google Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'is_ajax'               => true,
				'endpoint'              => 'automator_google_calendar_list_calendars_dropdown',
				'required'              => true,
				'supports_custom_value' => false,
				'options_show_id'       => false,
			),
			// Summary.
			array(
				'option_code'           => $this->get_action_meta(),
				/* translators: Calendar field */
				'label'                 => esc_attr_x( 'Title', 'Google Calendar', 'uncanny-automator' ),
				'input_type'            => 'text',
				'supports_custom_value' => true,
				'required'              => true,
			),
			// Location.
			array(
				'option_code'           => $this->get_formatted_code( 'location' ),
				/* translators: Calendar field */
				'label'                 => esc_attr_x( 'Location', 'Google Calendar', 'uncanny-automator' ),
				'input_type'            => 'text',
				'supports_custom_value' => true,
				'required'              => false,
			),
			// Description.
			array(
				'option_code'           => $this->get_formatted_code( 'description' ),
				/* translators: Calendar field */
				'label'                 => esc_attr_x( 'Description', 'Google Calendar', 'uncanny-automator' ),
				'input_type'            => 'textarea',
				'supports_custom_value' => true,
				'required'              => false,
			),
			// Start date.
			array(
				'option_code'     => $this->get_formatted_code( 'start_date' ),
				'label'           => esc_attr_x( 'Start date', 'Google Calendar', 'uncanny-automator' ),
				'input_type'      => 'date',
				'supports_tokens' => true,
				'required'        => true,
				'description'     => sprintf(
					'%1$s <a target="_blank" title="%3$s" href="%2$s">%3$s</a>.',
					esc_attr_x( 'Start date must be in the date format set in', 'Google Calendar', 'uncanny-automator' ),
					admin_url( 'options-general.php#timezone_string' ),
					esc_attr_x( 'WordPress', 'Google Calendar', 'uncanny-automator' )
				),
			),
			// Start time.
			array(
				'option_code'     => $this->get_formatted_code( 'start_time' ),
				/* translators: Calendar field */
				'label'           => esc_attr_x( 'Start time', 'Google Calendar', 'uncanny-automator' ),
				'input_type'      => 'time',
				'supports_tokens' => true,
				'required'        => false,
				'description'     => sprintf(
					'%1$s <a target="_blank" title="%3$s" href="%2$s">%3$s</a>. %4$s',
					esc_attr_x( 'The event time will match the timezone set in', 'Google Calendar', 'uncanny-automator' ),
					admin_url( 'options-general.php#timezone_string' ),
					esc_attr_x( 'WordPress Settings', 'Google Calendar', 'uncanny-automator' ),
					esc_attr_x( 'Leave blank to create an all-day event.', 'Google Calendar', 'uncanny-automator' )
				),
			),
			// End date.
			array(
				'option_code'     => $this->get_formatted_code( 'end_date' ),
				/* translators: Calendar field */
				'label'           => esc_attr_x( 'End date', 'Google Calendar', 'uncanny-automator' ),
				'input_type'      => 'date',
				'supports_tokens' => true,
				'required'        => true,
				'description'     => sprintf(
					'%1$s <a target="_blank" title="%3$s" href="%2$s">%3$s</a>.',
					esc_attr_x( 'End date must be in the date format set in', 'Google Calendar', 'uncanny-automator' ),
					admin_url( 'options-general.php#timezone_string' ),
					esc_attr_x( 'WordPress', 'Google Calendar', 'uncanny-automator' )
				),
			),
			// End time.
			array(
				'option_code'     => $this->get_formatted_code( 'end_time' ),
				/* translators: Calendar field */
				'label'           => esc_attr_x( 'End time', 'Google Calendar', 'uncanny-automator' ),
				'input_type'      => 'time',
				'supports_tokens' => true,
				'required'        => false,
				'description'     => sprintf(
					'%1$s <a target="_blank" title="%3$s" href="%2$s">%3$s</a>. %4$s',
					esc_attr_x( 'The event time will match the timezone set in', 'Google Calendar', 'uncanny-automator' ),
					admin_url( 'options-general.php#timezone_string' ),
					esc_attr_x( 'WordPress Settings', 'Google Calendar', 'uncanny-automator' ),
					esc_attr_x( 'Leave blank to create an all-day event.', 'Google Calendar', 'uncanny-automator' )
				),
			),
			// Timezone.
			array(
				'option_code'   => $this->get_formatted_code( 'timezone' ),
				/* translators: Calendar field */
				'label'         => esc_attr_x( 'Timezone', 'Google Calendar', 'uncanny-automator' ),
				'description'   => esc_attr_x( 'Select the timezone for this event. Leave blank to use the site default timezone.', 'Google Calendar', 'uncanny-automator' ),
				'input_type'    => 'select',
				'options'       => $this->get_timezone_options(),
				'default_value' => wp_timezone_string(),
				'required'      => false,
			),
			// Attendees.
			array(
				'option_code'           => $this->get_formatted_code( 'attendees' ),
				/* translators: Calendar field */
				'label'                 => esc_attr_x( 'Attendees', 'Google Calendar', 'uncanny-automator' ),
				'description'           => esc_attr_x( 'Comma separated email addresses of the attendees', 'Google Calendar', 'uncanny-automator' ),
				'input_type'            => 'text',
				'supports_custom_value' => true,
				'required'              => false,
			),
			// Email Notifications.
			array(
				'option_code'   => $this->get_formatted_code( 'notification_email' ),
				/* translators: Calendar field */
				'label'         => esc_attr_x( 'Enable email notifications in Google Calendar', 'Google Calendar', 'uncanny-automator' ),
				'input_type'    => 'checkbox',
				'default_value' => true,
			),
			// Notification time.
			array(
				'option_code'   => $this->get_formatted_code( 'notification_time_email' ),
				/* translators: Calendar field */
				'label'         => esc_attr_x( 'Minutes before event to trigger email notification', 'Google Calendar', 'uncanny-automator' ),
				'description'   => esc_attr_x( 'If no value is entered, the notification will fire 15 minutes before the event.', 'Google Calendar', 'uncanny-automator' ),
				'placeholder'   => esc_attr_x( '15', 'Google Calendar', 'uncanny-automator' ),
				'input_type'    => 'text',
				'default_value' => 15,
				'required'      => false,
			),
			// Popup Notifications.
			array(
				'option_code'   => $this->get_formatted_code( 'notification_popup' ),
				/* translators: Calendar field */
				'label'         => esc_attr_x( 'Enable popup notifications in Google Calendar', 'Google Calendar', 'uncanny-automator' ),
				'input_type'    => 'checkbox',
				'default_value' => true,
			),
			array(
				'option_code'   => $this->get_formatted_code( 'notification_time_popup' ),
				/* translators: Calendar field */
				'label'         => esc_attr_x( 'Minutes before event to trigger popup notification', 'Google Calendar', 'uncanny-automator' ),
				'description'   => esc_attr_x( 'If no value is entered, the notification will fire 15 minutes before the event.', 'Google Calendar', 'uncanny-automator' ),
				'placeholder'   => esc_attr_x( '15', 'Google Calendar', 'uncanny-automator' ),
				'required'      => false,
				'default_value' => 15,
				'input_type'    => 'text',
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$calendar_id             = isset( $parsed[ $this->get_formatted_code( 'calendar' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'calendar' ) ] ) : 0;
		$summary                 = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$location                = isset( $parsed[ $this->get_formatted_code( 'location' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'location' ) ] ) : '';
		$description             = isset( $parsed[ $this->get_formatted_code( 'description' ) ] ) ? sanitize_textarea_field( $parsed[ $this->get_formatted_code( 'description' ) ] ) : '';
		$start_date              = isset( $parsed[ $this->get_formatted_code( 'start_date' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'start_date' ) ] ) : false;
		$start_time              = isset( $parsed[ $this->get_formatted_code( 'start_time' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'start_time' ) ] ) : false;
		$end_date                = isset( $parsed[ $this->get_formatted_code( 'end_date' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'end_date' ) ] ) : false;
		$end_time                = isset( $parsed[ $this->get_formatted_code( 'end_time' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'end_time' ) ] ) : false;
		$attendees               = isset( $parsed[ $this->get_formatted_code( 'attendees' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'attendees' ) ] ) : '';
		$notification_email      = isset( $parsed[ $this->get_formatted_code( 'notification_email' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'notification_email' ) ] ) : 0;
		$notification_popup      = isset( $parsed[ $this->get_formatted_code( 'notification_popup' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'notification_popup' ) ] ) : 0;
		$notification_time_email = isset( $parsed[ $this->get_formatted_code( 'notification_time_email' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'notification_time_email' ) ] ) : 0;
		$notification_time_popup = isset( $parsed[ $this->get_formatted_code( 'notification_time_popup' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'notification_time_popup' ) ] ) : 0;
		$timezone                = isset( $parsed[ $this->get_formatted_code( 'timezone' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'timezone' ) ] ) : '';

		// Validate attendees email addresses if provided (before try block)
		// NOTE: This is intentionally outside the try-catch block because:
		// 1. The Uncanny Automator framework handles exceptions from process_action() at the abstract Action class level
		// 2. Input validation should happen before main processing logic
		// 3. Framework will catch exceptions, log errors, and complete action with errors properly
		// 4. This separation keeps validation logic separate from API processing logic
		if ( ! empty( $attendees ) ) {
			$this->validate_attendee_emails( $attendees );
		}

		try {

			$body = array(
				'action'                  => 'create_event',
				'access_token'            => $this->helper->get_client(),
				'summary'                 => $summary,
				'location'                => $location,
				'calendar_id'             => $calendar_id,
				'description'             => $description,
				'start_date'              => $this->autoformat_date( $start_date ),
				'start_time'              => $this->autoformat_time( $start_time ),
				'end_date'                => $this->autoformat_date( $end_date ),
				'end_time'                => $this->autoformat_time( $end_time ),
				'attendees'               => str_replace( ' ', '', trim( $attendees ) ),
				'notification_email'      => $notification_email,
				'notification_popup'      => $notification_popup,
				'notification_time_email' => $notification_time_email,
				'notification_time_popup' => $notification_time_popup,
				'timezone'                => ! empty( $timezone ) ? $timezone : apply_filters( 'automator_google_calendar_add_event_timezone', Automator()->get_timezone_string() ),
				// Google Calendar endpoint is written so the date format can be changed from the Client.
				'date_format'             => $this->get_date_format(),
				'time_format'             => $this->get_time_format(),
			);

			$response = $this->helper->api_call(
				$body,
				$action_data
			);

			// Hydrate action tokens with event information
			if ( ! empty( $response ) && ! empty( $response['data'] ) && ! empty( $response['data']['event'] ) ) {
				$event_data = $response['data']['event'];

				// Get formatted dates for calendar links
				$start_datetime = $this->format_datetime_for_calendar( $start_date, $start_time );
				$end_datetime   = $this->format_datetime_for_calendar( $end_date, $end_time );

				$this->hydrate_tokens(
					array(
						'EVENT_ID' => $event_data['id'] ?? '',
						'EVENT_LINK' => $event_data['htmlLink'] ?? '',
						'EVENT_START' => $event_data['start']['dateTime'] ?? $start_datetime,
						'EVENT_END' => $event_data['end']['dateTime'] ?? $end_datetime,
						'CALENDAR_ID' => $calendar_id,
						'GOOGLE_CALENDAR_LINK' => $this->generate_google_calendar_link( $summary, $start_datetime, $end_datetime, $description, $location ),
						'APPLE_CALENDAR_LINK' => $this->generate_apple_calendar_link( $summary, $start_datetime, $end_datetime, $description, $location ),
						'OUTLOOK_CALENDAR_LINK' => $this->generate_outlook_calendar_link( $summary, $start_datetime, $end_datetime, $description, $location ),
						'COMBINED_CALENDAR_LINKS' => $this->generate_combined_calendar_links( $summary, $start_datetime, $end_datetime, $description, $location ),
						'GOOGLE_CALENDAR_ANCHOR' => $this->generate_google_calendar_anchor( $summary, $start_datetime, $end_datetime, $description, $location ),
						'APPLE_CALENDAR_ANCHOR' => $this->generate_apple_calendar_anchor( $summary, $start_datetime, $end_datetime, $description, $location ),
						'OUTLOOK_CALENDAR_ANCHOR' => $this->generate_outlook_calendar_anchor( $summary, $start_datetime, $end_datetime, $description, $location ),
					)
				);
			}

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}
	}

	/**
	 * Autoformats the given time base on the format from WordPress.
	 *
	 * @return string The formatted time.
	 */
	protected function autoformat_time( $time ) {

		try {
			$dt = new \DateTime( $time ); // Accept whatever date.
		} catch ( \Exception $e ) {
			// translators: %s: Invalid time that was provided
			throw new \Exception(
				sprintf(
					esc_html_x( 'Error: Invalid time provided (%s)', 'Google Calendar', 'uncanny-automator' ),
					esc_html( $time )
				)
			);
		}

		return $dt->format( $this->get_time_format() );
	}

	/**
	 * Autoformats the given date base on the format from WordPress.
	 *
	 * @return string The formatted date.
	 */
	protected function autoformat_date( $date = '' ) {

		try {
			$dt = new \DateTime( $date ); // Accept whatever date.
		} catch ( \Exception $e ) {
			// translators: %s: Invalid date that was provided
			throw new \Exception(
				sprintf(
					esc_html_x( 'Error: Invalid date provided (%s)', 'Google Calendar', 'uncanny-automator' ),
					esc_html( $date )
				)
			);
		}

		return $dt->format( $this->get_date_format() );
	}

	/**
	 * Retrieves the date format.
	 *
	 * @return string The date format. E.g. 'F j, Y'. Overridable with `automator_google_calendar_date_format`
	 */
	protected function get_date_format() {

		return apply_filters( 'automator_google_calendar_date_format', get_option( 'date_format', 'F j, Y' ), $this );
	}

	/**
	 * Retrieves the date format.
	 *
	 * @return string The date format. E.g. 'g:i a'. Overridable with `automator_google_calendar_time_format`
	 */
	protected function get_time_format() {

		return apply_filters( 'automator_google_calendar_time_format', get_option( 'time_format', 'g:i a' ), $this );
	}

	/**
	 * Get timezone options for select field
	 *
	 * @return array
	 */
	private function get_timezone_options() {
		$timezones = \DateTimeZone::listIdentifiers();
		$options   = array();

		// Add default option
		$options[] = array(
			'text' => esc_html_x( 'Use site default timezone', 'Google Calendar', 'uncanny-automator' ),
			'value' => '',
		);

		// Group timezones by region
		$grouped = array();
		foreach ( $timezones as $timezone ) {
			$parts  = explode( '/', $timezone );
			$region = $parts[0];
			$city   = isset( $parts[1] ) ? $parts[1] : $timezone;

			if ( ! isset( $grouped[ $region ] ) ) {
				$grouped[ $region ] = array();
			}

			$grouped[ $region ][ $timezone ] = $city;
		}

		// Build options array
		foreach ( $grouped as $region => $cities ) {
			// Add region header
			$options[] = array(
				'text' => '--- ' . $region . ' ---',
				'value' => '',
				'disabled' => true,
			);

			// Add cities in this region
			foreach ( $cities as $timezone => $city ) {
				$offset    = $this->get_timezone_offset( $timezone );
				$options[] = array(
					'text' => $city . ' (' . $offset . ')',
					'value' => $timezone,
				);
			}
		}

		return $options;
	}

	/**
	 * Get timezone offset for display
	 *
	 * @param string $timezone
	 * @return string
	 */
	private function get_timezone_offset( $timezone ) {
		try {
			$dt     = new \DateTime( 'now', new \DateTimeZone( $timezone ) );
			$offset = $dt->format( 'P' );
			return $offset;
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Format datetime for calendar links
	 *
	 * @param string $date
	 * @param string $time
	 * @return string
	 */
	private function format_datetime_for_calendar( $date, $time ) {
		if ( empty( $date ) ) {
			return '';
		}

		if ( empty( $time ) ) {
			// All-day event
			try {
				$datetime = new \DateTime( $date );
				return $datetime->format( 'Y-m-d' );
			} catch ( \Exception $e ) {
				return '';
			}
		}

		// Combine date and time
		$datetime_string = $date . ' ' . $time;
		try {
			$datetime = new \DateTime( $datetime_string );
			return $datetime->format( 'Y-m-d\TH:i:s' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Generate Google Calendar add event link
	 *
	 * @param string $title
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param string $description
	 * @param string $location
	 * @return string
	 */
	private function generate_google_calendar_link( $title, $start_datetime, $end_datetime, $description, $location ) {
		$params = array(
			'action' => 'TEMPLATE',
			'text' => urlencode( $title ),
			'dates' => urlencode( $start_datetime . '/' . $end_datetime ),
		);

		if ( ! empty( $description ) ) {
			$params['details'] = urlencode( $description );
		}

		if ( ! empty( $location ) ) {
			$params['location'] = urlencode( $location );
		}

		return 'https://calendar.google.com/calendar/render?' . http_build_query( $params );
	}

	/**
	 * Generate Apple Calendar (.ics) download link
	 *
	 * @param string $title
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param string $description
	 * @param string $location
	 * @return string
	 */
	private function generate_apple_calendar_link( $title, $start_datetime, $end_datetime, $description, $location ) {
		// Generate ICS content
		$ics_content = $this->generate_ics_content( $title, $start_datetime, $end_datetime, $description, $location );

		// Use charset=utf-8 (with dash) and prefer %20 for spaces - EXACT same format as working anchor
		$encoded_content = str_replace( '+', '%20', urlencode( $ics_content ) );
		return 'data:text/calendar;charset=utf-8,' . $encoded_content . '#.ics';
	}

	/**
	 * Generate Outlook Calendar add event link
	 *
	 * @param string $title
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param string $description
	 * @param string $location
	 * @return string
	 */
	private function generate_outlook_calendar_link( $title, $start_datetime, $end_datetime, $description, $location ) {
		$params = array(
			'subject' => urlencode( $title ),
			'startdt' => urlencode( $start_datetime ),
			'enddt' => urlencode( $end_datetime ),
		);

		if ( ! empty( $description ) ) {
			$params['body'] = urlencode( $description );
		}

		if ( ! empty( $location ) ) {
			$params['location'] = urlencode( $location );
		}

		return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query( $params );
	}

	/**
	 * Generate Google Calendar anchor link
	 *
	 * @param string $title
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param string $description
	 * @param string $location
	 * @return string
	 */
	private function generate_google_calendar_anchor( $title, $start_datetime, $end_datetime, $description, $location ) {
		$url = $this->generate_google_calendar_link( $title, $start_datetime, $end_datetime, $description, $location );
		return sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">Add to Google Calendar</a>', esc_url( $url ) );
	}

	/**
	 * Generate Apple Calendar anchor link
	 *
	 * @param string $title
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param string $description
	 * @param string $location
	 * @return string
	 */
	private function generate_apple_calendar_anchor( $title, $start_datetime, $end_datetime, $description, $location ) {
		$url = $this->generate_apple_calendar_link( $title, $start_datetime, $end_datetime, $description, $location );
		return sprintf( '<a download="%s.ics" href="%s">Add to Apple Calendar</a>', sanitize_title( $title ), $url );
	}

	/**
	 * Generate Outlook Calendar anchor link
	 *
	 * @param string $title
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param string $description
	 * @param string $location
	 * @return string
	 */
	private function generate_outlook_calendar_anchor( $title, $start_datetime, $end_datetime, $description, $location ) {
		$url = $this->generate_outlook_calendar_link( $title, $start_datetime, $end_datetime, $description, $location );
		return sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">Add to Outlook</a>', esc_url( $url ) );
	}

	/**
	 * Generate combined calendar links HTML
	 *
	 * @param string $title
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param string $description
	 * @param string $location
	 * @return string
	 */
	private function generate_combined_calendar_links( $title, $start_datetime, $end_datetime, $description, $location ) {
		$google_link  = $this->generate_google_calendar_link( $title, $start_datetime, $end_datetime, $description, $location );
		$apple_link   = $this->generate_apple_calendar_link( $title, $start_datetime, $end_datetime, $description, $location );
		$outlook_link = $this->generate_outlook_calendar_link( $title, $start_datetime, $end_datetime, $description, $location );

		$html  = '<div style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">';
		$html .= '<h3 style="margin: 0 0 15px 0; color: #333;">📅 Add to Your Calendar</h3>';
		$html .= '<ul style="margin: 0; padding: 0; list-style: none;">';
		$html .= '<li style="margin: 10px 0;"><a href="' . esc_url( $google_link ) . '" target="_blank" rel="noopener noreferrer" style="color: #4285f4; text-decoration: none;">Add to Google Calendar</a></li>';
		$html .= '<li style="margin: 10px 0;"><a download="' . sanitize_title( $title ) . '.ics" href="' . $apple_link . '" style="color: #007aff; text-decoration: none;">Add to Apple Calendar</a></li>';
		$html .= '<li style="margin: 10px 0;"><a href="' . esc_url( $outlook_link ) . '" target="_blank" rel="noopener noreferrer" style="color: #0078d4; text-decoration: none;">Add to Outlook</a></li>';
		$html .= '</ul>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate ICS file content
	 *
	 * @param string $title
	 * @param string $start_datetime
	 * @param string $end_datetime
	 * @param string $description
	 * @param string $location
	 * @return string
	 */
	private function generate_ics_content( $title, $start_datetime, $end_datetime, $description, $location ) {
		$ics  = "BEGIN:VCALENDAR\r\n";
		$ics .= "VERSION:2.0\r\n";
		$ics .= "PRODID:-//Uncanny Automator//Google Calendar Integration//EN\r\n";
		$ics .= "CALSCALE:GREGORIAN\r\n";
		$ics .= "METHOD:PUBLISH\r\n";
		$ics .= "BEGIN:VEVENT\r\n";
		$ics .= "UID:" . uniqid() . "@uncannyautomator.com\r\n";
		$ics .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z', time() ) . "\r\n";

		if ( ! empty( $start_datetime ) ) {
			try {
				$datetime = new \DateTime( $start_datetime );
				$ics     .= "DTSTART:" . $datetime->format( 'Ymd\THis\Z' ) . "\r\n";
			} catch ( \Exception $e ) {
				// Skip this field if date parsing fails
				// Log the error for debugging purposes
				error_log( 'Google Calendar: Failed to parse start datetime: ' . $e->getMessage() );
			}
		}

		if ( ! empty( $end_datetime ) ) {
			try {
				$datetime = new \DateTime( $end_datetime );
				$ics     .= "DTEND:" . $datetime->format( 'Ymd\THis\Z' ) . "\r\n";
			} catch ( \Exception $e ) {
				// Skip this field if date parsing fails
				// Log the error for debugging purposes
				error_log( 'Google Calendar: Failed to parse end datetime: ' . $e->getMessage() );
			}
		}

		$ics .= "SUMMARY:" . $this->escape_ics_text( $title ) . "\r\n";

		if ( ! empty( $description ) ) {
			$ics .= "DESCRIPTION:" . $this->escape_ics_text( $description ) . "\r\n";
		}

		if ( ! empty( $location ) ) {
			$ics .= "LOCATION:" . $this->escape_ics_text( $location ) . "\r\n";
		}

		$ics .= "STATUS:CONFIRMED\r\n";
		$ics .= "SEQUENCE:0\r\n";
		$ics .= "END:VEVENT\r\n";
		$ics .= "END:VCALENDAR\r\n";

		return $ics;
	}

	/**
	 * Escape text for ICS format
	 *
	 * @param string $text
	 * @return string
	 */
	private function escape_ics_text( $text ) {
		$text = str_replace( array( "\r\n", "\r", "\n" ), "\\n", $text );
		$text = str_replace( array( "\\", ";" ), array( "\\\\", "\\;" ), $text );
		return $text;
	}

	/**
	 * Validate attendee email addresses
	 *
	 * @param string $attendees Comma-separated list of email addresses
	 * @throws \Exception If any email is invalid
	 * @return void
	 */
	private function validate_attendee_emails( $attendees ) {
		// Split by comma and clean up whitespace
		$email_list = array_map( 'trim', explode( ',', $attendees ) );

		// Remove empty values
		$email_list = array_filter( $email_list );

		if ( empty( $email_list ) ) {
			return;
		}

		$invalid_emails = array();

		foreach ( $email_list as $email ) {
			// Use both WordPress is_email() and PHP filter_var() for robust validation
			$is_valid_wordpress = is_email( $email );
			$is_valid_php       = filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;

			// Email must pass both validations
			if ( ! $is_valid_wordpress || ! $is_valid_php ) {
				$invalid_emails[] = $email;
			}
		}

		if ( ! empty( $invalid_emails ) ) {
			$invalid_list = implode( ', ', $invalid_emails );
			// translators: %s: List of invalid email addresses
			throw new \Exception(
				sprintf(
					esc_html_x( 'Invalid email address(es) in attendees field: %s. Please provide valid email addresses separated by commas.', 'Google Calendar', 'uncanny-automator' ),
					esc_html( $invalid_list )
				)
			);
		}
	}
}
