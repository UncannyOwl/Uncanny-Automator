<?php

namespace Uncanny_Automator\Integrations\Zoom;

use Uncanny_Automator\Recipe\App_Action;
use Uncanny_Automator\Integrations\Zoom\Zoom_Common_Trait;
use Uncanny_Automator\Integrations\Zoom\Zoom_Meeting_Trait;

/**
 * Class ZOOM_CREATEMEETING
 *
 * @package Uncanny_Automator
 * @property Zoom_App_Helpers $helpers
 * @property Zoom_Api_Caller $api
 */
class ZOOM_CREATEMEETING extends App_Action {

	use Zoom_Common_Trait;
	use Zoom_Meeting_Trait;

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOOM' );
		$this->set_action_code( 'ZOOMCREATEMEETING' );
		$this->set_action_meta( 'ZOOMMEETING' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// translators: %1$s Meeting topic
		$this->set_sentence( sprintf( esc_html_x( 'Create {{a meeting:%1$s}}', 'Zoom', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create {{a meeting}}', 'Zoom', 'uncanny-automator' ) );
		$this->set_background_processing( true );
		$this->set_action_tokens(
			array(
				'MEETING_ID'       => array(
					'name' => esc_html_x( 'Meeting ID', 'Zoom', 'uncanny-automator' ),
					'type' => 'text',
				),
				'MEETING_LINK_URL' => array(
					'name' => esc_html_x( 'Meeting link URL', 'Zoom', 'uncanny-automator' ),
					'type' => 'url',
				),
				'MEETING_LINK'     => array(
					'name' => esc_html_x( 'Meeting link', 'Zoom', 'uncanny-automator' ),
					'type' => 'text',
				),
				'MEETING_TOPIC'    => array(
					'name' => esc_html_x( 'Meeting topic', 'Zoom', 'uncanny-automator' ),
					'type' => 'text',
				),
				'START_TIME'       => array(
					'name' => esc_html_x( 'Start time', 'Zoom', 'uncanny-automator' ),
					'type' => 'text',
				),
				'DURATION'         => array(
					'name' => esc_html_x( 'Duration', 'Zoom', 'uncanny-automator' ),
					'type' => 'text',
				),
				'MEETING_PASSWORD' => array(
					'name' => esc_html_x( 'Meeting password', 'Zoom', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_meeting_topic_field(),
			$this->get_start_date_field(),
			$this->get_start_time_field(),
			$this->get_timezone_field(),
			$this->get_duration_field(),
			$this->get_registration_type_field(),
			$this->get_approval_type_field(),
			$this->get_close_registration_field(),
			$this->get_account_users_field(),
			$this->get_meeting_description_field(),
			$this->get_meeting_password_field(),
			$this->get_waiting_room_field(),
			$this->get_join_before_host_field(),
			$this->get_host_video_field(),
			$this->get_participant_video_field(),
			$this->get_audio_field(),
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
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Parse data.
		$meeting_topic  = $this->parse_meeting_topic();
		$start_date     = $this->parse_start_date();
		$start_time     = $this->parse_start_time();
		$duration       = $this->parse_duration();
		$timezone       = $this->parse_timezone();
		$start_datetime = $this->parse_datetime( $start_date, $start_time, $timezone, true );

		// Build request data.
		$meeting_data             = $this->build_meeting_data( $meeting_topic, 2, $start_datetime, $duration, $timezone );
		$meeting_data['settings'] = $this->build_meeting_settings();

		// Create meeting.
		$response = $this->api->create_meeting( $meeting_data, $action_data );

		if ( 201 !== $response['statusCode'] ) {
			throw new \Exception( esc_html_x( 'Failed to create meeting. Please check your Zoom account settings and try again.', 'Zoom', 'uncanny-automator' ) );
		}

		if ( ! empty( $response['data'] ) ) {
			$this->hydrate_meeting_tokens( $response['data'], $meeting_topic, $start_datetime, $duration );
		}

		return true;
	}
}
