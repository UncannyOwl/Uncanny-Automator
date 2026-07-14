<?php

namespace Uncanny_Automator\Integrations\Gotomeeting;

use Uncanny_Automator\Recipe\App_Action;

/**
 * Class GTM_CREATEMEETING
 *
 * @property Gotomeeting_App_Helpers $helpers
 * @property Gotomeeting_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class GTM_CREATEMEETING extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GTM' );
		$this->set_action_code( 'GTMCREATEMEETING' );
		$this->set_action_meta( 'GTMSUBJECT' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				// translators: %s: Meeting subject
				esc_html_x( 'Create a meeting {{titled:%s}}', 'GoToMeeting', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Create a meeting {{titled}}', 'GoToMeeting', 'uncanny-automator' ) );

		$this->set_background_processing( true );
	}

	/**
	 * Define action options.
	 *
	 * Separate date + time fields for start and end (Google Calendar style).
	 * They are interpreted in the site timezone and converted to ISO 8601
	 * UTC at run time — GoToMeeting V1 expects start/end in UTC and its
	 * `timezonekey` body field is deprecated, so no timezone picker.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_attr_x( 'Subject', 'GoToMeeting', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'GTMSTARTDATE',
				'label'       => esc_attr_x( 'Start date', 'GoToMeeting', 'uncanny-automator' ),
				'input_type'  => 'date',
				'required'    => true,
				'description' => esc_attr_x( 'Interpreted in the site timezone.', 'GoToMeeting', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'GTMSTARTTIME',
				'label'       => esc_attr_x( 'Start time', 'GoToMeeting', 'uncanny-automator' ),
				'input_type'  => 'time',
				'required'    => true,
			),
			array(
				'option_code' => 'GTMENDDATE',
				'label'       => esc_attr_x( 'End date', 'GoToMeeting', 'uncanny-automator' ),
				'input_type'  => 'date',
				'required'    => true,
				'description' => esc_attr_x( 'Interpreted in the site timezone.', 'GoToMeeting', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'GTMENDTIME',
				'label'       => esc_attr_x( 'End time', 'GoToMeeting', 'uncanny-automator' ),
				'input_type'  => 'time',
				'required'    => true,
			),
			array(
				'option_code'           => 'GTMCONFERENCECALLINFO',
				'label'                 => esc_attr_x( 'Conference call', 'GoToMeeting', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => false,
				'options'               => array(
					array(
						'text'  => esc_html_x( 'Built-in VoIP and conference call numbers', 'GoToMeeting', 'uncanny-automator' ),
						'value' => 'Hybrid',
					),
					array(
						'text'  => esc_html_x( 'VoIP only', 'GoToMeeting', 'uncanny-automator' ),
						'value' => 'VoIP',
					),
					array(
						'text'  => esc_html_x( 'Conference call numbers only', 'GoToMeeting', 'uncanny-automator' ),
						'value' => 'PSTN',
					),
				),
			),
			array(
				'option_code'           => 'GTMMEETINGTYPE',
				'label'                 => esc_attr_x( 'Meeting type', 'GoToMeeting', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => false,
				'default_value'         => 'scheduled',
				'options'               => array(
					array(
						'text'  => esc_html_x( 'Scheduled (fixed start and end time)', 'GoToMeeting', 'uncanny-automator' ),
						'value' => 'scheduled',
					),
					array(
						'text'  => esc_html_x( 'Recurring', 'GoToMeeting', 'uncanny-automator' ),
						'value' => 'recurring',
					),
					array(
						'text'  => esc_html_x( 'Immediate (starts now)', 'GoToMeeting', 'uncanny-automator' ),
						'value' => 'immediate',
					),
				),
			),
			array(
				'option_code' => 'GTMPASSWORDREQUIRED',
				'label'       => esc_attr_x( 'Require password', 'GoToMeeting', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'required'    => false,
			),
		);
	}

	/**
	 * Define the action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'MEETING_ID'           => array(
				'name' => esc_html_x( 'Meeting ID', 'GoToMeeting', 'uncanny-automator' ),
				'type' => 'text',
			),
			'JOIN_URL'             => array(
				'name' => esc_html_x( 'Join URL', 'GoToMeeting', 'uncanny-automator' ),
				'type' => 'url',
			),
			'MAX_PARTICIPANTS'     => array(
				'name' => esc_html_x( 'Max participants', 'GoToMeeting', 'uncanny-automator' ),
				'type' => 'int',
			),
			'UNIQUE_MEETING_ID'    => array(
				'name' => esc_html_x( 'Unique meeting ID', 'GoToMeeting', 'uncanny-automator' ),
				'type' => 'text',
			),
			'CONFERENCE_CALL_INFO' => array(
				'name' => esc_html_x( 'Conference call info', 'GoToMeeting', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $action_data Action data.
	 * @param int   $recipe_id   Recipe ID.
	 * @param array $args        Action arguments.
	 * @param array $parsed      Parsed action data.
	 *
	 * @return bool
	 * @throws \Exception If the dates/times are invalid, the start is not in the future, or end is not after start.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$start = $this->to_utc(
			sanitize_text_field( $parsed['GTMSTARTDATE'] ?? '' ),
			sanitize_text_field( $parsed['GTMSTARTTIME'] ?? '' )
		);
		$end   = $this->to_utc(
			sanitize_text_field( $parsed['GTMENDDATE'] ?? '' ),
			sanitize_text_field( $parsed['GTMENDTIME'] ?? '' )
		);

		// GoToMeeting V1 requires start/end to be in the future; a past value
		// returns the same generic "malformed/mandatory attributes" 400. Catch
		// it here so the operator sees an actionable error instead.
		$now = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		if ( $start <= $now ) {
			throw new \Exception( esc_html_x( 'The start time must be in the future.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		if ( $end <= $start ) {
			throw new \Exception( esc_html_x( 'The end time must be after the start time.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		$meeting = array(
			'subject'            => sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ),
			'starttime'          => $start->format( 'Y-m-d\TH:i:s\Z' ),
			'endtime'            => $end->format( 'Y-m-d\TH:i:s\Z' ),
			'conferencecallinfo' => sanitize_text_field( $parsed['GTMCONFERENCECALLINFO'] ?? 'Hybrid' ),
			'passwordrequired'   => filter_var( $parsed['GTMPASSWORDREQUIRED'] ?? false, FILTER_VALIDATE_BOOLEAN ),
			// DEPRECATED by GoToMeeting but must still be present and empty.
			'timezonekey'        => '',
			// GoToMeeting V1 requires meetingtype (enum: scheduled|recurring|immediate).
			// Missing it returns 400 "mandatory attributes are missing".
			'meetingtype'        => sanitize_text_field( $parsed['GTMMEETINGTYPE'] ?? 'scheduled' ),
		);

		$created = $this->api->create_meeting( $meeting, $action_data );

		$this->hydrate_tokens(
			array(
				'MEETING_ID'           => $created['meetingid'] ?? '',
				'JOIN_URL'             => $created['joinURL'] ?? '',
				'MAX_PARTICIPANTS'     => $created['maxParticipants'] ?? '',
				'UNIQUE_MEETING_ID'    => $created['uniqueMeetingId'] ?? '',
				'CONFERENCE_CALL_INFO' => $created['conferenceCallInfo'] ?? '',
			)
		);

		return true;
	}

	/**
	 * Resolve a date + time (entered in the site timezone) to a UTC DateTime.
	 *
	 * GoToMeeting V1 expects start/end as ISO 8601 UTC instants. Times are
	 * interpreted in the site timezone (there is no timezone picker — the
	 * vendor's `timezonekey` is deprecated).
	 *
	 * @param string $date Date in Y-m-d.
	 * @param string $time Time in H:i (24h) or h:i A.
	 *
	 * @return \DateTime UTC DateTime.
	 * @throws \Exception If the date or time is invalid.
	 */
	private function to_utc( $date, $time ) {

		try {
			$dt = new \DateTime( trim( $date . ' ' . $time ), wp_timezone() );
		} catch ( \Exception $e ) {
			throw new \Exception( esc_html_x( 'Invalid date or time provided.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		$dt->setTimezone( new \DateTimeZone( 'UTC' ) );

		return $dt;
	}
}
