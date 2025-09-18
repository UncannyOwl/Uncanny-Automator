<?php

namespace Uncanny_Automator\Integrations\Zoom;

use Exception;

/**
 * Trait Zoom_Meeting_Trait
 *
 * Provides meeting-related functionality for Zoom actions.
 *
 * @package Uncanny_Automator\Integrations\Zoom
 */
trait Zoom_Meeting_Trait {

	/**
	 * Get meeting topic field.
	 *
	 * @return array
	 */
	protected function get_meeting_topic_field() {
		return array(
			'option_code' => 'MEETINGTOPIC',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Meeting topic', 'Zoom', 'uncanny-automator' ),
			'placeholder' => esc_html_x( 'Enter meeting topic', 'Zoom', 'uncanny-automator' ),
			'description' => '',
			'required'    => true,
			'tokens'      => true,
		);
	}

	/**
	 * Get start date field.
	 *
	 * @return array
	 */
	protected function get_start_date_field() {
		return array(
			'option_code' => 'STARTDATE',
			'input_type'  => 'date',
			'label'       => esc_html_x( 'Start date', 'Zoom', 'uncanny-automator' ),
			'description' => '',
			'required'    => true,
			'tokens'      => true,
		);
	}

	/**
	 * Get start time field.
	 *
	 * @return array
	 */
	protected function get_start_time_field() {
		return array(
			'option_code' => 'STARTTIME',
			'input_type'  => 'time',
			'label'       => esc_html_x( 'Start time', 'Zoom', 'uncanny-automator' ),
			'description' => '',
			'required'    => true,
			'tokens'      => true,
		);
	}

	/**
	 * Get timezone field.
	 *
	 * @return array
	 */
	protected function get_timezone_field() {
		return array(
			'input_type'    => 'select',
			'option_code'   => 'TIMEZONE',
			'label'         => esc_html_x( 'Timezone', 'Zoom', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Select the timezone for the meeting', 'Zoom', 'uncanny-automator' ),
			'required'      => false,
			'default_value' => wp_timezone_string(),
			'options'       => $this->get_timezone_options(),
			'tokens'        => true,
		);
	}

	/**
	 * Get timezone options.
	 *
	 * @return array
	 */
	private function get_timezone_options() {
		$timezones = \DateTimeZone::listIdentifiers( \DateTimeZone::ALL );
		$options   = array();
		foreach ( $timezones as $timezone ) {
			$options[] = array(
				'value' => $timezone,
				'text'  => $timezone,
			);
		}
		return $options;
	}

	/**
	 * Get duration field.
	 *
	 * @return array
	 */
	protected function get_duration_field() {
		return array(
			'option_code' => 'DURATION',
			'input_type'  => 'int',
			'label'       => esc_html_x( 'Duration (minutes)', 'Zoom', 'uncanny-automator' ),
			'placeholder' => '60',
			'description' => '',
			'required'    => true,
			'tokens'      => true,
			'default'     => '30',
		);
	}

	/**
	 * Get registration type field.
	 *
	 * @return array
	 */
	protected function get_registration_type_field() {
		return array(
			'input_type'  => 'select',
			'option_code' => 'REGISTRATIONTYPE',
			'label'       => esc_html_x( 'Registration type', 'Zoom', 'uncanny-automator' ),
			'required'    => true,
			'tokens'      => false,
			'options'     => array(
				array(
					'value' => '1',
					'text'  => esc_html_x( 'No registration required', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => '2',
					'text'  => esc_html_x( 'Registration required', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => '3',
					'text'  => esc_html_x( 'Registration required, manually approve', 'Zoom', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Get approval type field.
	 *
	 * @return array
	 */
	protected function get_approval_type_field() {
		return array(
			'input_type'        => 'select',
			'option_code'       => 'APPROVALTYPE',
			'label'             => esc_html_x( 'Approval type', 'Zoom', 'uncanny-automator' ),
			'description'       => esc_html_x( 'How should registrations be approved?', 'Zoom', 'uncanny-automator' ),
			'required'          => false,
			'tokens'            => false,
			'default_value'     => '0',
			'dynamic_visibility' => array(
				'default_state'    => 'hidden',
				'visibility_rules' => array(
					array(
						'operator'             => 'OR',
						'rule_conditions'      => array(
							array(
								'option_code' => 'REGISTRATIONTYPE',
								'compare'     => '==',
								'value'       => 2,
							),
							array(
								'option_code' => 'REGISTRATIONTYPE',
								'compare'     => '==',
								'value'       => 3,
							),
						),
						'resulting_visibility' => 'show',
					),
				),
			),
			'options'           => array(
				array(
					'value' => '0',
					'text'  => esc_html_x( 'Automatically approve', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => '1',
					'text'  => esc_html_x( 'Manually approve', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => '2',
					'text'  => esc_html_x( 'No registration required', 'Zoom', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Get close registration field.
	 *
	 * @return array
	 */
	protected function get_close_registration_field() {
		return array(
			'option_code'        => 'CLOSEREGISTRATION',
			'label'              => esc_html_x( 'Close registration', 'Zoom', 'uncanny-automator' ),
			'description'        => esc_html_x( 'Close registration after event date', 'Zoom', 'uncanny-automator' ),
			'input_type'         => 'checkbox',
			'required'           => false,
			'tokens'             => false,
			'is_toggle'          => true,
			'default_value'      => false,
			'dynamic_visibility' => array(
				'default_state'    => 'hidden',
				'visibility_rules' => array(
					array(
						'operator'             => 'OR',
						'rule_conditions'      => array(
							array(
								'option_code' => 'REGISTRATIONTYPE',
								'compare'     => '==',
								'value'       => 2,
							),
							array(
								'option_code' => 'REGISTRATIONTYPE',
								'compare'     => '==',
								'value'       => 3,
							),
						),
						'resulting_visibility' => 'show',
					),
				),
			),
		);
	}

	/**
	 * Get meeting description field.
	 *
	 * @return array
	 */
	protected function get_meeting_description_field() {
		return array(
			'option_code' => 'MEETINGDESCRIPTION',
			'label'       => esc_html_x( 'Meeting description', 'Zoom', 'uncanny-automator' ),
			'input_type'  => 'textarea',
			'placeholder' => esc_html_x( 'Enter meeting description', 'Zoom', 'uncanny-automator' ),
			'required'    => false,
			'tokens'      => true,
		);
	}

	/**
	 * Get meeting password field.
	 *
	 * @return array
	 */
	protected function get_meeting_password_field() {
		return array(
			'option_code' => 'MEETINGPASSWORD',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Meeting password', 'Zoom', 'uncanny-automator' ),
			'placeholder' => esc_html_x( 'Leave empty for no password', 'Zoom', 'uncanny-automator' ),
			'description' => esc_html_x( 'Leave empty to generate a password automatically', 'Zoom', 'uncanny-automator' ),
			'required'    => false,
			'tokens'      => true,
		);
	}

	/**
	 * Get waiting room field.
	 *
	 * @return array
	 */
	protected function get_waiting_room_field() {
		return array(
			'option_code' => 'WAITINGROOM',
			'label'       => esc_html_x( 'Enable waiting room', 'Zoom', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
			'tokens'      => false,
			'is_toggle'   => true,
		);
	}

	/**
	 * Get join before host field.
	 *
	 * @return array
	 */
	protected function get_join_before_host_field() {
		return array(
			'option_code' => 'JOINBEFOREHOST',
			'label'       => esc_html_x( 'Allow participants to join before host', 'Zoom', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
			'tokens'      => false,
			'is_toggle'   => true,
		);
	}

	/**
	 * Get host video field.
	 *
	 * @return array
	 */
	protected function get_host_video_field() {
		return array(
			'option_code' => 'HOSTVIDEO',
			'label'       => esc_html_x( 'Host video on', 'Zoom', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
			'tokens'      => false,
			'is_toggle'   => true,
		);
	}

	/**
	 * Get participant video field.
	 *
	 * @return array
	 */
	protected function get_participant_video_field() {
		return array(
			'option_code' => 'PARTICIPANTVIDEO',
			'label'       => esc_html_x( 'Participant video on', 'Zoom', 'uncanny-automator' ),
			'input_type'  => 'checkbox',
			'required'    => false,
			'tokens'      => false,
			'is_toggle'   => true,
		);
	}

	/**
	 * Get audio field.
	 *
	 * @return array
	 */
	protected function get_audio_field() {
		return array(
			'option_code' => 'AUDIO',
			'input_type'  => 'select',
			'label'       => esc_html_x( 'Audio', 'Zoom', 'uncanny-automator' ),
			'description' => '',
			'required'    => false,
			'tokens'      => false,
			'options'     => array(
				array(
					'value' => 'both',
					'text'  => esc_html_x( 'Both', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => 'telephony',
					'text'  => esc_html_x( 'Telephony', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => 'computer_audio',
					'text'  => esc_html_x( 'Computer Audio', 'Zoom', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Get meeting type field (for recurring meetings).
	 *
	 * @return array
	 */
	protected function get_meeting_type_field() {
		return array(
			'input_type'    => 'select',
			'option_code'   => 'MEETINGTYPE',
			'label'         => esc_html_x( 'Recurring meeting type', 'Zoom', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Select the type of recurring meeting to create', 'Zoom', 'uncanny-automator' ),
			'required'      => true,
			'tokens'        => false,
			'default_value' => 8,
			'options'       => array(
				array(
					'value' => 3,
					'text'  => esc_html_x( 'Recurring meeting (no fixed time)', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => 8,
					'text'  => esc_html_x( 'Recurring meeting (fixed time)', 'Zoom', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Get recurrence type field.
	 *
	 * @return array
	 */
	protected function get_recurrence_type_field() {
		return array(
			'input_type'  => 'select',
			'option_code' => 'RECURRENCETYPE',
			'label'       => esc_html_x( 'Recurrence type', 'Zoom', 'uncanny-automator' ),
			'description' => esc_html_x( 'How often should the meeting repeat?', 'Zoom', 'uncanny-automator' ),
			'required'    => true,
			'tokens'      => false,
			'options'     => array(
				array(
					'value' => 1,
					'text'  => esc_html_x( 'Daily', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => 2,
					'text'  => esc_html_x( 'Weekly', 'Zoom', 'uncanny-automator' ),
				),
				array(
					'value' => 3,
					'text'  => esc_html_x( 'Monthly', 'Zoom', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Get repeat interval field.
	 *
	 * @return array
	 */
	protected function get_repeat_interval_field() {
		return array(
			'option_code' => 'REPEATINTERVAL',
			'input_type'  => 'int',
			'label'       => esc_html_x( 'Repeat every (interval)', 'Zoom', 'uncanny-automator' ),
			'description' => esc_html_x( 'How many days/weeks/months between occurrences (e.g., 2 for every 2 weeks)', 'Zoom', 'uncanny-automator' ),
			'placeholder' => '1',
			'required'    => true,
			'tokens'      => true,
			'default'     => '1',
		);
	}

	/**
	 * Get weekly days field.
	 *
	 * @return array
	 */
	protected function get_weekly_days_field() {
		return array(
			'option_code'        => 'WEEKLYDAYS',
			'input_type'         => 'text',
			'label'              => esc_html_x( 'Days of week', 'Zoom', 'uncanny-automator' ),
			'description'        => esc_html_x( 'Comma-separated days (1=Sunday, 2=Monday, etc.)', 'Zoom', 'uncanny-automator' ),
			'placeholder'        => '2,4',
			'required'           => false,
			'tokens'             => true,
			'dynamic_visibility' => array(
				'default_state'    => 'hidden',
				'visibility_rules' => array(
					array(
						'operator'             => 'AND',
						'rule_conditions'      => array(
							array(
								'option_code' => 'MEETINGTYPE',
								'compare'     => '==',
								'value'       => 8,
							),
							array(
								'option_code' => 'RECURRENCETYPE',
								'compare'     => '==',
								'value'       => 2,
							),
						),
						'resulting_visibility' => 'show',
					),
				),
			),
		);
	}

	/**
	 * Get end date field.
	 *
	 * @return array
	 */
	protected function get_end_date_field() {
		return array(
			'option_code' => 'ENDDATE',
			'input_type'  => 'date',
			'label'       => esc_html_x( 'End date', 'Zoom', 'uncanny-automator' ),
			'description' => esc_html_x( 'When should the recurring meeting stop?', 'Zoom', 'uncanny-automator' ),
			'required'    => false,
			'tokens'      => true,
		);
	}

	/**
	 * Get end times field.
	 *
	 * @return array
	 */
	protected function get_end_times_field() {
		return array(
			'option_code' => 'ENDTIMES',
			'input_type'  => 'int',
			'label'       => esc_html_x( 'Number of occurrences', 'Zoom', 'uncanny-automator' ),
			'description' => esc_html_x( 'How many times should the meeting occur? (alternative to end date)', 'Zoom', 'uncanny-automator' ),
			'placeholder' => '10',
			'required'    => false,
			'tokens'      => true,
		);
	}

	/**
	 * Get monthly day field.
	 *
	 * @return array
	 */
	protected function get_monthly_day_field() {
		return array(
			'option_code'        => 'MONTHLYDAY',
			'input_type'         => 'int',
			'label'              => esc_html_x( 'Day of month', 'Zoom', 'uncanny-automator' ),
			'description'        => esc_html_x( 'Which day of the month should the meeting occur? (1-31)', 'Zoom', 'uncanny-automator' ),
			'placeholder'        => '15',
			'required'           => false,
			'tokens'             => true,
			'dynamic_visibility' => array(
				'default_state'    => 'hidden',
				'visibility_rules' => array(
					array(
						'operator'             => 'AND',
						'rule_conditions'      => array(
							array(
								'option_code' => 'RECURRENCETYPE',
								'compare'     => '==',
								'value'       => 3,
							),
						),
						'resulting_visibility' => 'show',
					),
				),
			),
		);
	}

	/**
	 * Parse and validate meeting topic.
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function parse_meeting_topic() {
		$meeting_topic = $this->get_parsed_meta_value( 'MEETINGTOPIC' );

		if ( empty( $meeting_topic ) ) {
			throw new Exception( esc_html_x( 'Meeting topic is missing.', 'Zoom', 'uncanny-automator' ) );
		}

		return $meeting_topic;
	}

	/**
	 * Parse and validate start date.
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function parse_start_date() {
		$start_date = $this->get_parsed_meta_value( 'STARTDATE' );

		if ( empty( $start_date ) ) {
			throw new Exception( esc_html_x( 'Start date is missing.', 'Zoom', 'uncanny-automator' ) );
		}

		return $start_date;
	}

	/**
	 * Parse and validate start time.
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function parse_start_time() {
		$start_time = $this->get_parsed_meta_value( 'STARTTIME' );

		if ( empty( $start_time ) ) {
			throw new Exception( esc_html_x( 'Start time is missing.', 'Zoom', 'uncanny-automator' ) );
		}

		return $start_time;
	}

	/**
	 * Parse and validate duration.
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function parse_duration() {
		$duration = $this->get_parsed_meta_value( 'DURATION' );

		if ( empty( $duration ) ) {
			throw new Exception( esc_html_x( 'Duration is missing.', 'Zoom', 'uncanny-automator' ) );
		}

		$duration_minutes = intval( $duration );
		if ( $duration_minutes > 1440 ) {
			throw new Exception( esc_html_x( 'Meeting duration cannot exceed 1440 minutes (24 hours). Zoom does not allow longer meetings.', 'Zoom', 'uncanny-automator' ) );
		}

		return $duration_minutes;
	}

	/**
	 * Parse timezone with fallback to site timezone.
	 *
	 * @return string
	 */
	protected function parse_timezone() {
		$timezone = $this->get_parsed_meta_value( 'TIMEZONE' );
		return empty( $timezone ) ? wp_timezone_string() : $timezone;
	}

	/**
	 * Parse datetime with timezone conversion
	 *
	 * @param string $start_date
	 * @param string $start_time
	 * @param string $timezone
	 * @param bool $include_timezone_offset Whether to include timezone offset in output
	 *
	 * @return string
	 * @throws Exception
	 */
	protected function parse_datetime( $start_date, $start_time, $timezone, $include_timezone_offset = false ) {
		try {
			$date_time = new \DateTime( $start_date . ' ' . $start_time, new \DateTimeZone( $timezone ) );
			$format    = $include_timezone_offset ? 'Y-m-d\TH:i:sP' : 'Y-m-d\TH:i:s';
			return $date_time->format( $format );
		} catch ( Exception $e ) {
			throw new Exception( esc_html_x( 'Invalid date, time, or timezone format. Please use YYYY-MM-DD for date and HH:MM AM/PM for time.', 'Zoom', 'uncanny-automator' ) );
		}
	}

	/**
	 * Build meeting data array with common fields.
	 *
	 * @param string $topic
	 * @param int $type
	 * @param string $start_datetime
	 * @param int $duration
	 * @param string $timezone
	 *
	 * @return array
	 */
	protected function build_meeting_data( $topic, $type, $start_datetime, $duration, $timezone ) {
		$meeting_data = array(
			'topic'      => $topic,
			'type'       => $type,
			'start_time' => $start_datetime,
			'duration'   => $duration,
			'timezone'   => $timezone,
		);

		// Zoom user (optional - defaults to 'me' if not specified).
		$zoom_user = $this->get_parsed_meta_value( 'ZOOMUSER' );
		if ( ! empty( $zoom_user ) ) {
			$meeting_data['user'] = $zoom_user;
		}

		// Registration type (default to 1 if not set).
		$registration_type = $this->get_parsed_meta_value( 'REGISTRATIONTYPE' );
		if ( ! empty( $registration_type ) ) {
			$meeting_data['registration_type'] = intval( $registration_type );
		} else {
			$meeting_data['registration_type'] = 1; // Default: No registration required
		}

		// Optional fields.
		$meeting_description = $this->get_parsed_meta_value( 'MEETINGDESCRIPTION' );
		if ( ! empty( $meeting_description ) ) {
			$meeting_data['agenda'] = $meeting_description;
		}

		$meeting_password = $this->get_parsed_meta_value( 'MEETINGPASSWORD' );
		if ( ! empty( $meeting_password ) ) {
			$meeting_data['password'] = $meeting_password;
		}

		return $meeting_data;
	}

	/**
	 * Build meeting settings array.
	 *
	 * @return array
	 */
	protected function build_meeting_settings() {
		$settings = array(
			'waiting_room'      => $this->get_parsed_boolean( 'WAITINGROOM' ),
			'join_before_host'  => $this->get_parsed_boolean( 'JOINBEFOREHOST' ),
			'host_video'        => $this->get_parsed_boolean( 'HOSTVIDEO' ),
			'participant_video' => $this->get_parsed_boolean( 'PARTICIPANTVIDEO' ),
			'audio'             => $this->get_parsed_meta_value( 'AUDIO', 'both' ),
		);

		// Add approval type and close registration for registration settings
		$approval_type = $this->get_parsed_meta_value( 'APPROVALTYPE' );
		if ( ! empty( $approval_type ) ) {
			$settings['approval_type'] = intval( $approval_type );
		}

		$close_registration = $this->get_parsed_meta_value( 'CLOSEREGISTRATION' );
		if ( ! empty( $close_registration ) ) {
			$settings['close_registration'] = $this->get_parsed_boolean( 'CLOSEREGISTRATION' );
		}

		return $settings;
	}

	/**
	 * Convert string boolean values to actual booleans
	 *
	 * @param string $option_code
	 * @return bool
	 */
	private function get_parsed_boolean( $option_code ) {
		$value = $this->get_parsed_meta_value( $option_code, false );
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			return in_array( $value, array( 'true', '1', 'yes', 'on' ), true );
		}

		return (bool) $value;
	}

	/**
	 * Build recurrence data array.
	 *
	 * @param string $timezone
	 *
	 * @return array
	 */
	protected function build_recurrence_data( $timezone ) {
		$recurrence_type  = $this->get_parsed_meta_value( 'RECURRENCETYPE' );
		$repeat_interval  = $this->get_parsed_meta_value( 'REPEATINTERVAL' );
		$weekly_days      = $this->get_parsed_meta_value( 'WEEKLYDAYS' );
		$monthly_day      = $this->get_parsed_meta_value( 'MONTHLYDAY' );
		$monthly_week     = $this->get_parsed_meta_value( 'MONTHLYWEEK' );
		$monthly_week_day = $this->get_parsed_meta_value( 'MONTHLYWEEKDAY' );
		$end_date         = $this->get_parsed_meta_value( 'ENDDATE' );
		$end_times        = $this->get_parsed_meta_value( 'ENDTIMES' );

		$recurrence = array(
			'type'            => (int) $recurrence_type,
			'repeat_interval' => (int) $repeat_interval,
		);

		// Add weekly days for weekly recurrence.
		if ( '2' === $recurrence_type && ! empty( $weekly_days ) ) {
			$recurrence['weekly_days'] = $weekly_days;
		}

		// Add monthly recurrence fields.
		if ( '3' === $recurrence_type ) {
			// Simple monthly recurrence by day of month (1-31)
			if ( ! empty( $monthly_day ) ) {
				$recurrence['monthly_day'] = (int) $monthly_day;
			} elseif ( ! empty( $monthly_week ) && ! empty( $monthly_week_day ) ) {
				$recurrence['monthly_week']     = (int) $monthly_week;
				$recurrence['monthly_week_day'] = (int) $monthly_week_day;
			}
		}

		// Add end date or end times.
		if ( ! empty( $end_date ) ) {
			try {
				$end_datetime                = new \DateTime( $end_date, new \DateTimeZone( $timezone ) );
				$recurrence['end_date_time'] = $end_datetime->format( 'Y-m-d\TH:i:sP' );
			} catch ( Exception $e ) {
				// If end date is invalid, ignore it and continue without end date.
				$recurrence['end_date_time'] = null;
			}
		} elseif ( ! empty( $end_times ) ) {
			$recurrence['end_times'] = (int) $end_times;
		}

		return $recurrence;
	}

	/**
	 * Create clickable meeting link.
	 *
	 * @param string $url
	 * @param string $topic
	 *
	 * @return string
	 */
	protected function create_clickable_meeting_link( $url, $topic = '' ) {
		if ( empty( $url ) ) {
			return '';
		}

		$link_text = $url;

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $url ),
			esc_html( $link_text )
		);
	}

	/**
	 * Hydrate meeting tokens.
	 *
	 * @param array $meeting_info
	 * @param string $meeting_topic
	 * @param string $start_datetime
	 * @param int $duration
	 *
	 * @return void
	 */
	protected function hydrate_meeting_tokens( $meeting_info, $meeting_topic, $start_datetime, $duration ) {
		$this->hydrate_tokens(
			array(
				'MEETING_ID'       => $meeting_info['id'] ?? '',
				'MEETING_LINK_URL' => $meeting_info['join_url'] ?? '',
				'MEETING_LINK'     => $this->create_clickable_meeting_link( $meeting_info['join_url'] ?? '', $meeting_info['topic'] ?? $meeting_topic ),
				'MEETING_TOPIC'    => $meeting_info['topic'] ?? $meeting_topic,
				'START_TIME'       => $meeting_info['start_time'] ?? $start_datetime,
				'DURATION'         => $meeting_info['duration'] ?? $duration,
				'MEETING_PASSWORD' => $meeting_info['password'] ?? '',
			)
		);
	}
}
