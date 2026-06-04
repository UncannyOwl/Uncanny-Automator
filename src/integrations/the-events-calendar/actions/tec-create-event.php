<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class EC_CREATE_EVENT
 *
 * Creates a new event via Tribe__Events__API::createEvent().
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_CREATE_EVENT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EC' );
		$this->set_action_code( 'EC_CREATE_EVENT' );
		$this->set_action_meta( 'EVENT_TITLE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );

		/* translators: %1$s is the event title field */
		$this->set_sentence( sprintf( esc_html_x( 'Create {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		return array(
			'EC_CREATED_EVENT_ID'    => array(
				'name' => esc_html_x( 'Event ID', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EC_CREATED_EVENT_URL'   => array(
				'name' => esc_html_x( 'Event URL', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'url',
			),
			'EC_CREATED_EVENT_TITLE' => array(
				'name' => esc_html_x( 'Event title', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Event title', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'EVENT_START_DATE',
				'label'       => esc_html_x( 'Start date', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'date',
				'required'    => true,
			),
			array(
				'option_code' => 'EVENT_START_TIME',
				'label'       => esc_html_x( 'Start time', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'time',
				'required'    => false,
				'description' => esc_html_x( 'Defaults to 00:00 if empty. Ignored for all-day events.', 'The Events Calendar', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'EVENT_END_DATE',
				'label'       => esc_html_x( 'End date', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'date',
				'required'    => true,
			),
			array(
				'option_code' => 'EVENT_END_TIME',
				'label'       => esc_html_x( 'End time', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'time',
				'required'    => false,
				'description' => esc_html_x( 'Defaults to 00:00 if empty. Ignored for all-day events.', 'The Events Calendar', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'EVENT_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
			array(
				'option_code'           => 'EVENT_STATUS',
				'label'                 => esc_html_x( 'Status', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'default_value'         => 'publish',
				'supports_custom_value' => true,
				'options'               => array(
					array(
						'value' => 'publish',
						'text'  => esc_html_x( 'Published', 'The Events Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => 'draft',
						'text'  => esc_html_x( 'Draft', 'The Events Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => 'pending',
						'text'  => esc_html_x( 'Pending', 'The Events Calendar', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code'           => 'EVENT_VENUE_ID',
				'label'                 => esc_html_x( 'Venue', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'venues_strict' ),
			),
			array(
				'option_code'           => 'EVENT_ORGANIZER_ID',
				'label'                 => esc_html_x( 'Organizer', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'organizers_strict' ),
			),
			array(
				'option_code' => 'EVENT_ALL_DAY',
				'label'       => esc_html_x( 'All day event', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'required'    => false,
			),
			array(
				'option_code' => 'EVENT_TIMEZONE',
				'label'       => esc_html_x( 'Timezone', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'placeholder' => 'UTC',
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( 'Tribe__Events__API' ) ) {
			$this->add_log_error( esc_html_x( 'The Events Calendar API is not available.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$title      = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$start_date = sanitize_text_field( $parsed['EVENT_START_DATE'] ?? '' );
		$start_time = sanitize_text_field( $parsed['EVENT_START_TIME'] ?? '' );
		$end_date   = sanitize_text_field( $parsed['EVENT_END_DATE'] ?? '' );
		$end_time   = sanitize_text_field( $parsed['EVENT_END_TIME'] ?? '' );

		if ( '' === $title || '' === $start_date || '' === $end_date ) {
			$this->add_log_error( esc_html_x( 'Title, start date and end date are required.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		// TEC's saveEventMeta() expects the DATE in its own datepicker format and
		// the TIME separately via EventStartTime/EventEndTime (24h). If no time is
		// supplied it sets date_provided=false and silently falls back to the
		// event's existing meta — i.e. "now" for a brand-new event, which is why
		// a combined "Y-m-d H:i:s" string produced today's date. Format the date
		// in TEC's datepicker format and pass the time as a separate 24h value.
		$datepicker_format = $this->get_datepicker_format();

		$event_start_date = $this->format_event_date( $start_date, $datepicker_format );
		$event_end_date   = $this->format_event_date( $end_date, $datepicker_format );

		if ( '' === $event_start_date || '' === $event_end_date ) {
			$this->add_log_error( esc_html_x( 'Could not parse the start or end date.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$event_start_time = $this->format_event_time( $start_time );
		$event_end_time   = $this->format_event_time( $end_time );

		$description = wp_kses_post( $parsed['EVENT_DESCRIPTION'] ?? '' );
		$status      = sanitize_text_field( $parsed['EVENT_STATUS'] ?? 'publish' );
		if ( '' === $status ) {
			$status = 'publish';
		}
		$venue_id     = absint( $parsed['EVENT_VENUE_ID'] ?? 0 );
		$organizer_id = absint( $parsed['EVENT_ORGANIZER_ID'] ?? 0 );

		// Canonical Automator checkbox parser — handles 'true'/'false'/'1'/'0'/'on'/'' uniformly.
		$all_day  = filter_var( strtolower( (string) ( $parsed['EVENT_ALL_DAY'] ?? '' ) ), FILTER_VALIDATE_BOOLEAN );

		$timezone = sanitize_text_field( $parsed['EVENT_TIMEZONE'] ?? '' );

		// Validate timezone against PHP's list; fall back to site tz on
		// empty or invalid input rather than letting TEC silently ignore
		// a typo like "UTC+5".
		if ( '' === $timezone || ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$timezone = wp_timezone_string();
		}

		$event_args = array(
			'post_title'     => $title,
			'post_content'   => $description,
			'post_status'    => $status,
			'EventStartDate' => $event_start_date,
			'EventStartTime' => $event_start_time,
			'EventEndDate'   => $event_end_date,
			'EventEndTime'   => $event_end_time,
			'EventAllDay'    => $all_day ? 'yes' : 'no',
			'EventTimezone'  => $timezone,
		);

		if ( 0 !== $venue_id ) {
			$event_args['Venue'] = array( 'VenueID' => $venue_id );
		}

		if ( 0 !== $organizer_id ) {
			$event_args['Organizer'] = array( 'OrganizerID' => $organizer_id );
		}

		$event_id = \Tribe__Events__API::createEvent( $event_args );

		if ( is_wp_error( $event_id ) ) {
			$this->add_log_error( $event_id->get_error_message() );
			return false;
		}

		if ( empty( $event_id ) ) {
			$this->add_log_error( esc_html_x( 'Failed to create event.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'EC_CREATED_EVENT_ID'    => (int) $event_id,
				'EC_CREATED_EVENT_URL'   => get_permalink( $event_id ),
				'EC_CREATED_EVENT_TITLE' => get_the_title( $event_id ),
			)
		);

		return true;
	}

	/**
	 * The datepicker format TEC parses EventStartDate/EventEndDate with. We
	 * format our date in the same format so TEC's datetime_from_format() reads
	 * it back correctly regardless of the site's datepicker setting.
	 *
	 * @return string
	 */
	private function get_datepicker_format() {

		if ( ! class_exists( 'Tribe__Date_Utils' ) ) {
			return 'Y-m-d';
		}

		$format = \Tribe__Date_Utils::datepicker_formats( \tribe_get_option( 'datepickerFormat' ) );

		return ( is_string( $format ) && '' !== $format ) ? $format : 'Y-m-d';
	}

	/**
	 * Reformat the date field's ISO value ("2026-06-02") into TEC's datepicker
	 * format. Returns an empty string when the value cannot be parsed.
	 *
	 * @param string $date              ISO date from the date field.
	 * @param string $datepicker_format Target format from get_datepicker_format().
	 *
	 * @return string
	 */
	private function format_event_date( $date, $datepicker_format ) {

		$date = trim( (string) $date );
		if ( '' === $date ) {
			return '';
		}

		$parsed = date_create( $date );

		return false === $parsed ? '' : $parsed->format( $datepicker_format );
	}

	/**
	 * Reformat the time field's localized value ("12:00 PM") into the 24h
	 * H:i:s TEC's EventStartTime/EventEndTime expect. Empty defaults to
	 * midnight; unparseable values also fall back to midnight.
	 *
	 * @param string $time Localized time from the time field.
	 *
	 * @return string
	 */
	private function format_event_time( $time ) {

		$time = trim( (string) $time );
		if ( '' === $time ) {
			return '00:00:00';
		}

		$parsed = date_create( $time );

		return false === $parsed ? '00:00:00' : $parsed->format( 'H:i:s' );
	}
}
