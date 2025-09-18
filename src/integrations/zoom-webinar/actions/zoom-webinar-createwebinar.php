<?php

namespace Uncanny_Automator\Integrations\Zoom_Webinar;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class ZOOM_WEBINAR_CREATEWEBINAR
 *
 * @package Uncanny_Automator
 *
 * @property Zoom_Webinar_App_Helpers $helpers
 * @property Zoom_Webinar_Api_Caller $api
 */
class ZOOM_WEBINAR_CREATEWEBINAR extends App_Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOOMWEBINAR' );
		$this->set_action_code( 'ZOOMWEBINARCREATEWEBINAR' );
		$this->set_action_meta( 'ZOOMWEBINAR' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// translators: %1$s Webinar topic
		$this->set_sentence( sprintf( esc_html_x( 'Create {{a webinar:%1$s}}', 'Zoom Webinar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create {{a webinar}}', 'Zoom Webinar', 'uncanny-automator' ) );
		$this->set_background_processing( true );
		$this->set_action_tokens(
			array(
				'WEBINAR_ID'       => array(
					'name' => esc_html_x( 'Webinar ID', 'Zoom Webinar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'WEBINAR_LINK'     => array(
					'name' => esc_html_x( 'Webinar link', 'Zoom Webinar', 'uncanny-automator' ),
					'type' => 'url',
				),
				'WEBINAR_TOPIC'    => array(
					'name' => esc_html_x( 'Webinar topic', 'Zoom Webinar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'START_TIME'       => array(
					'name' => esc_html_x( 'Start time', 'Zoom Webinar', 'uncanny-automator' ),
					'type' => 'text',
				),
				'DURATION'         => array(
					'name' => esc_html_x( 'Duration', 'Zoom Webinar', 'uncanny-automator' ),
					'type' => 'int',
				),
				'WEBINAR_PASSWORD' => array(
					'name' => esc_html_x( 'Webinar password', 'Zoom Webinar', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$webinar_topic_field = array(
			'option_code' => 'WEBINARTOPIC',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Webinar topic', 'Zoom Webinar', 'uncanny-automator' ),
			'placeholder' => esc_html_x( 'Enter webinar topic', 'Zoom Webinar', 'uncanny-automator' ),
			'description' => '',
			'required'    => true,
			'tokens'      => true,
		);

		$start_date_field = array(
			'option_code' => 'STARTDATE',
			'input_type'  => 'date',
			'label'       => esc_html_x( 'Start date', 'Zoom Webinar', 'uncanny-automator' ),
			'description' => '',
			'required'    => true,
			'tokens'      => true,
		);

		$start_time_field = array(
			'option_code' => 'STARTTIME',
			'input_type'  => 'time',
			'label'       => esc_html_x( 'Start time', 'Zoom Webinar', 'uncanny-automator' ),
			'description' => '',
			'required'    => true,
			'tokens'      => true,
		);

		$timezone_field = array(
			'input_type'    => 'select',
			'option_code'   => 'TIMEZONE',
			'label'         => esc_html_x( 'Timezone', 'Zoom Webinar', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Select the timezone for the webinar', 'Zoom Webinar', 'uncanny-automator' ),
			'required'      => false,
			'default_value' => wp_timezone_string(),
			'options'       => $this->helpers->get_timezone_options(),
		);

		$duration_field = array(
			'option_code'   => 'DURATION',
			'input_type'    => 'int',
			'label'         => esc_html_x( 'Duration (minutes)', 'Zoom Webinar', 'uncanny-automator' ),
			'placeholder'   => esc_html_x( 'Enter duration in minutes', 'Zoom Webinar', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Duration in minutes', 'Zoom Webinar', 'uncanny-automator' ),
			'required'      => true,
			'default_value' => 60,
			'tokens'        => true,
		);

		$zoom_user_field = array(
			'option_code'           => 'ZOOMUSER',
			'label'                 => esc_html_x( 'Account user', 'Zoom Webinar', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'options'               => array(),
			'relevant_tokens'       => array(),
			'supports_custom_value' => false,
			'ajax'                  => array(
				'endpoint' => 'uap_zoom_webinar_api_get_account_users',
				'event'    => 'on_load',
			),
		);

		$webinar_description_field = array(
			'option_code' => 'WEBINARDESCRIPTION',
			'input_type'  => 'textarea',
			'label'       => esc_html_x( 'Webinar description', 'Zoom Webinar', 'uncanny-automator' ),
			'placeholder' => esc_html_x( 'Enter webinar description', 'Zoom Webinar', 'uncanny-automator' ),
			'description' => esc_html_x( 'Optional description for the webinar', 'Zoom Webinar', 'uncanny-automator' ),
			'required'    => false,
			'tokens'      => true,
		);

		$webinar_password_field = array(
			'option_code' => 'WEBINARPASSWORD',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Webinar password', 'Zoom Webinar', 'uncanny-automator' ),
			'placeholder' => esc_html_x( 'Enter webinar password', 'Zoom Webinar', 'uncanny-automator' ),
			'description' => esc_html_x( 'Optional password for the webinar', 'Zoom Webinar', 'uncanny-automator' ),
			'required'    => false,
			'tokens'      => true,
		);

		$host_video_field = array(
			'option_code'   => 'HOSTVIDEO',
			'input_type'    => 'checkbox',
			'label'         => esc_html_x( 'Host video on', 'Zoom Webinar', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Enable host video when joining', 'Zoom Webinar', 'uncanny-automator' ),
			'required'      => false,
			'is_toggle'     => true,
			'default_value' => true,
		);

		$panelists_video_field = array(
			'option_code'   => 'PANELISTSVIDEO',
			'input_type'    => 'checkbox',
			'label'         => esc_html_x( 'Panelists video on', 'Zoom Webinar', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Enable panelists video when joining', 'Zoom Webinar', 'uncanny-automator' ),
			'required'      => false,
			'is_toggle'     => true,
			'default_value' => true,
		);

		$practice_session_field = array(
			'option_code'   => 'PRACTICESESSION',
			'input_type'    => 'checkbox',
			'label'         => esc_html_x( 'Practice session', 'Zoom Webinar', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Enable practice session before the webinar', 'Zoom Webinar', 'uncanny-automator' ),
			'required'      => false,
			'is_toggle'     => true,
			'default_value' => false,
		);

		$hd_video_field = array(
			'option_code'   => 'HDVIDEO',
			'input_type'    => 'checkbox',
			'label'         => esc_html_x( 'HD video', 'Zoom Webinar', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Enable HD video for the webinar', 'Zoom Webinar', 'uncanny-automator' ),
			'required'      => false,
			'is_toggle'     => true,
			'default_value' => true,
		);

		$audio_field = array(
			'input_type'    => 'select',
			'option_code'   => 'AUDIO',
			'label'         => esc_html_x( 'Audio', 'Zoom Webinar', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Select audio options', 'Zoom Webinar', 'uncanny-automator' ),
			'required'      => false,
			'options'       => array(
				array(
					'value' => 'both',
					'text'  => esc_html_x( 'Both', 'Zoom Webinar', 'uncanny-automator' ),
				),
				array(
					'value' => 'telephony',
					'text'  => esc_html_x( 'Telephony', 'Zoom Webinar', 'uncanny-automator' ),
				),
				array(
					'value' => 'computer_audio',
					'text'  => esc_html_x( 'Computer Audio', 'Zoom Webinar', 'uncanny-automator' ),
				),
			),
			'default_value' => 'both',
		);

		$auto_recording_field = array(
			'input_type'    => 'select',
			'option_code'   => 'AUTORECORDING',
			'label'         => esc_html_x( 'Auto recording', 'Zoom Webinar', 'uncanny-automator' ),
			'description'   => esc_html_x( 'Select auto recording option', 'Zoom Webinar', 'uncanny-automator' ),
			'required'      => false,
			'options'       => array(
				array(
					'value' => 'none',
					'text'  => esc_html_x( 'None', 'Zoom Webinar', 'uncanny-automator' ),
				),
				array(
					'value' => 'local',
					'text'  => esc_html_x( 'Local', 'Zoom Webinar', 'uncanny-automator' ),
				),
				array(
					'value' => 'cloud',
					'text'  => esc_html_x( 'Cloud', 'Zoom Webinar', 'uncanny-automator' ),
				),
			),
			'default_value' => 'none',
		);

		$alternative_hosts_field = array(
			'option_code' => 'ALTERNATIVEHOSTS',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Alternative hosts', 'Zoom Webinar', 'uncanny-automator' ),
			'placeholder' => esc_html_x( 'Enter email addresses separated by commas', 'Zoom Webinar', 'uncanny-automator' ),
			'description' => esc_html_x( 'Email addresses of alternative hosts (comma separated)', 'Zoom Webinar', 'uncanny-automator' ),
			'required'    => false,
			'tokens'      => true,
		);

		// Registration type field
		$registration_type_field = array(
			'input_type'  => 'select',
			'option_code' => 'REGISTRATIONTYPE',
			'label'       => esc_html_x( 'Registration type', 'Zoom Webinar', 'uncanny-automator' ),
			'description' => esc_html_x( 'How should attendees register for the webinar?', 'Zoom Webinar', 'uncanny-automator' ),
			'required'    => false,
			'tokens'      => false,
			'default_value' => '1',
			'options'     => array(
				array(
					'value' => 1,
					'text'  => esc_html_x( 'No registration required', 'Zoom Webinar', 'uncanny-automator' ),
				),
				array(
					'value' => 2,
					'text'  => esc_html_x( 'Registration required', 'Zoom Webinar', 'uncanny-automator' ),
				),
			),
		);

		// Approval type field
		$approval_type_field = array(
			'input_type'        => 'select',
			'option_code'       => 'APPROVALTYPE',
			'label'             => esc_html_x( 'Approval type', 'Zoom Webinar', 'uncanny-automator' ),
			'description'       => esc_html_x( 'How should registrations be approved?', 'Zoom Webinar', 'uncanny-automator' ),
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
						),
						'resulting_visibility' => 'show',
					),
				),
			),
			'options'           => array(
				array(
					'value' => '0',
					'text'  => esc_html_x( 'Automatically approve', 'Zoom Webinar', 'uncanny-automator' ),
				),
				array(
					'value' => '1',
					'text'  => esc_html_x( 'Manually approve', 'Zoom Webinar', 'uncanny-automator' ),
				),
			),
		);

		// Close registration field
		$close_registration_field = array(
			'option_code'        => 'CLOSEREGISTRATION',
			'label'              => esc_html_x( 'Close registration', 'Zoom Webinar', 'uncanny-automator' ),
			'description'        => esc_html_x( 'Close registration after event date', 'Zoom Webinar', 'uncanny-automator' ),
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
						),
						'resulting_visibility' => 'show',
					),
				),
			),
		);

		return array(
			$webinar_topic_field,
			$start_date_field,
			$start_time_field,
			$timezone_field,
			$duration_field,
			$zoom_user_field,
			$webinar_description_field,
			$webinar_password_field,
			$registration_type_field,
			$approval_type_field,
			$close_registration_field,
			$host_video_field,
			$panelists_video_field,
			$practice_session_field,
			$hd_video_field,
			$audio_field,
			$auto_recording_field,
			$alternative_hosts_field,
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$webinar_topic = $this->get_parsed_meta_value( 'WEBINARTOPIC' );

		if ( empty( $webinar_topic ) ) {
			throw new Exception( esc_html_x( 'Webinar topic is missing.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$start_date = $this->get_parsed_meta_value( 'STARTDATE' );

		if ( empty( $start_date ) ) {
			throw new Exception( esc_html_x( 'Start date is missing.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$start_time = $this->get_parsed_meta_value( 'STARTTIME' );

		if ( empty( $start_time ) ) {
			throw new Exception( esc_html_x( 'Start time is missing.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$duration = $this->get_parsed_meta_value( 'DURATION' );

		if ( empty( $duration ) ) {
			throw new Exception( esc_html_x( 'Duration is missing.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		// Validate duration - Zoom doesn't allow webinars longer than 1440 minutes (24 hours).
		$duration_minutes = intval( $duration );
		if ( $duration_minutes > 1440 ) {
			throw new Exception( esc_html_x( 'Webinar duration cannot exceed 1440 minutes (24 hours). Zoom does not allow longer webinars.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		// Get timezone (default to site timezone if not specified).
		$timezone = $this->get_parsed_meta_value( 'TIMEZONE' );
		if ( empty( $timezone ) ) {
			$timezone = wp_timezone_string();
		}

		// Convert 12-hour time format to 24-hour format for Zoom API with proper exception handling.
		try {
			$date_time      = new \DateTime( $start_date . ' ' . $start_time, new \DateTimeZone( $timezone ) );
			$start_datetime = $date_time->format( 'Y-m-d\TH:i:s' );
		} catch ( Exception $e ) {
			throw new Exception( esc_html_x( 'Invalid date, time, or timezone format. Please use YYYY-MM-DD for date and HH:MM AM/PM for time.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$webinar_data = array(
			'topic'      => $webinar_topic,
			'type'       => 5, // Scheduled webinar
			'start_time' => $start_datetime,
			'duration'   => intval( $duration ),
			'timezone'   => $timezone,
		);

		// Zoom user (optional).
		$zoom_user = $this->get_parsed_meta_value( 'ZOOMUSER' );
		if ( ! empty( $zoom_user ) ) {
			$webinar_data['user'] = $zoom_user;
		}

		// Registration type (default to 1 if not set).
		$registration_type = $this->get_parsed_meta_value( 'REGISTRATIONTYPE' );
		if ( ! empty( $registration_type ) ) {
			$webinar_data['registration_type'] = intval( $registration_type );
		} else {
			$webinar_data['registration_type'] = 1; // Default: No registration required
		}

		// Optional fields.
		$webinar_description = $this->get_parsed_meta_value( 'WEBINARDESCRIPTION' );
		if ( ! empty( $webinar_description ) ) {
			$webinar_data['agenda'] = $webinar_description;
		}

		$webinar_password = $this->get_parsed_meta_value( 'WEBINARPASSWORD' );
		if ( ! empty( $webinar_password ) ) {
			$webinar_data['password'] = $webinar_password;
		}

		// Settings.
		$webinar_data['settings'] = array(
			'host_video'        => $this->get_parsed_boolean( 'HOSTVIDEO' ),
			'panelists_video'   => $this->get_parsed_boolean( 'PANELISTSVIDEO' ),
			'practice_session'  => $this->get_parsed_boolean( 'PRACTICESESSION' ),
			'hd_video'          => $this->get_parsed_boolean( 'HDVIDEO' ),
			'audio'             => $this->get_parsed_meta_value( 'AUDIO' ),
			'auto_recording'    => $this->get_parsed_meta_value( 'AUTORECORDING' ),
			'alternative_hosts' => $this->get_parsed_meta_value( 'ALTERNATIVEHOSTS' ),
		);

		// Add registration settings for webinars
		if ( isset( $webinar_data['registration_type'] ) ) {
			$registration_type = $webinar_data['registration_type'];

			// For webinars, move registration_type to settings
			$webinar_data['settings']['registration_type'] = $registration_type;
			unset( $webinar_data['registration_type'] ); // Remove from webinar level

			// Add approval settings if registration is required
			if ( 1 < $registration_type ) {
				$approval_type = $this->get_parsed_meta_value( 'APPROVALTYPE' );
				if ( ! empty( $approval_type ) ) {
					$webinar_data['settings']['approval_type'] = intval( $approval_type );
				}

				$close_registration = $this->get_parsed_meta_value( 'CLOSEREGISTRATION' );
				if ( ! empty( $close_registration ) ) {
					$webinar_data['settings']['close_registration'] = $this->get_parsed_boolean( 'CLOSEREGISTRATION' );
				}
			}
		}

		$response = $this->api->create_webinar( $webinar_data, $action_data );

		// Hydrate action tokens with webinar information.
		if ( ! empty( $response['data'] ) ) {
			$webinar_info = $response['data'];

			// Store tokens for use in other actions.
			$this->hydrate_tokens(
				array(
					'WEBINAR_ID'       => $webinar_info['id'] ?? '',
					'WEBINAR_LINK'     => $webinar_info['join_url'] ?? '',
					'WEBINAR_TOPIC'    => $webinar_info['topic'] ?? $webinar_topic,
					'START_TIME'       => $webinar_info['start_time'] ?? $start_datetime,
					'DURATION'         => $webinar_info['duration'] ?? $duration,
					'WEBINAR_PASSWORD' => $webinar_info['password'] ?? '',
				)
			);
		}

		return true;
	}

	/**
	 * Convert string boolean values to actual booleans.
	 *
	 * @param string $option_code
	 *
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
}
