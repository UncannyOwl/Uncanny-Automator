<?php

namespace Uncanny_Automator\Integrations\Microsoft_Teams;

/**
 * Class TEAMS_CREATE_MEETING
 *
 * @package Uncanny_Automator
 *
 * @property Microsoft_Teams_App_Helpers $helpers
 * @property Microsoft_Teams_Api_Caller $api
 */
class TEAMS_CREATE_MEETING extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'MICROSOFT_TEAMS' );
		$this->set_action_code( 'TEAMS_CREATE_MEETING_CODE' );
		$this->set_action_meta( 'TEAMS_MEETING_SUBJECT' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/microsoft-teams/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create an online meeting titled {{a subject}}', 'Microsoft Teams', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: 1: Meeting subject
				esc_attr_x( 'Create an online meeting titled {{a subject:%1$s}}', 'Microsoft Teams', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'TEAMS_MEETING_JOIN_URL' => array(
					'name' => esc_html_x( 'Join URL', 'Microsoft Teams', 'uncanny-automator' ),
					'type' => 'url',
				),
				'TEAMS_MEETING_ID'       => array(
					'name' => esc_html_x( 'Meeting ID', 'Microsoft Teams', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_subject_option_config(),
			$this->get_start_option_config(),
			$this->get_end_option_config(),
			$this->get_lobby_option_config(),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$meeting = array(
			'subject'       => $this->helpers->get_text_value_from_parsed( $parsed, $this->get_action_meta(), esc_html_x( 'Meeting subject', 'Microsoft Teams', 'uncanny-automator' ) ),
			'startDateTime' => $this->normalize_iso8601( $this->helpers->get_text_value_from_parsed( $parsed, 'TEAMS_MEETING_START', esc_html_x( 'Start date/time', 'Microsoft Teams', 'uncanny-automator' ) ) ),
			'endDateTime'   => $this->normalize_iso8601( $this->helpers->get_text_value_from_parsed( $parsed, 'TEAMS_MEETING_END', esc_html_x( 'End date/time', 'Microsoft Teams', 'uncanny-automator' ) ) ),
		);

		$lobby = $this->get_parsed_meta_value( 'TEAMS_MEETING_LOBBY', false );
		if ( ! empty( $lobby ) ) {
			$meeting['lobbyBypassSettings'] = array(
				'scope' => $lobby,
			);
		}

		$body = array(
			'action'  => 'create_online_meeting',
			'meeting' => wp_json_encode( $meeting ),
		);

		$response = $this->api->api_request( $body, $action_data );
		$data     = $response['data'] ?? array();

		$this->hydrate_tokens(
			array(
				'TEAMS_MEETING_JOIN_URL' => $data['joinWebUrl'] ?? '',
				'TEAMS_MEETING_ID'       => $data['id'] ?? '',
			)
		);

		return true;
	}

	////////////////////////////////////////////////////////////
	// Option configurations
	////////////////////////////////////////////////////////////

	/**
	 * Get the subject option configuration.
	 *
	 * @return array
	 */
	private function get_subject_option_config() {
		return array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Meeting subject', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}

	/**
	 * Get the start date/time option configuration.
	 *
	 * @return array
	 */
	private function get_start_option_config() {
		return array(
			'option_code' => 'TEAMS_MEETING_START',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'Start date/time', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( 'ISO 8601 format, UTC. Example: 2026-03-15T14:00:00Z', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}

	/**
	 * Get the end date/time option configuration.
	 *
	 * @return array
	 */
	private function get_end_option_config() {
		return array(
			'option_code' => 'TEAMS_MEETING_END',
			'input_type'  => 'text',
			'label'       => esc_html_x( 'End date/time', 'Microsoft Teams', 'uncanny-automator' ),
			'description' => esc_html_x( 'ISO 8601 format, UTC. Example: 2026-03-15T15:00:00Z', 'Microsoft Teams', 'uncanny-automator' ),
			'required'    => true,
		);
	}

	/**
	 * Get the lobby bypass option configuration.
	 *
	 * @return array
	 */
	private function get_lobby_option_config() {
		$options = array(
			'organization' => esc_html_x( 'People in my organization', 'Microsoft Teams', 'uncanny-automator' ),
			'organizer'    => esc_html_x( 'Only the organizer', 'Microsoft Teams', 'uncanny-automator' ),
			'everyone'     => esc_html_x( 'Everyone', 'Microsoft Teams', 'uncanny-automator' ),
		);
		return array(
			'option_code'           => 'TEAMS_MEETING_LOBBY',
			'label'                 => esc_html_x( 'Who can bypass the lobby?', 'Microsoft Teams', 'uncanny-automator' ),
			'placeholder'           => esc_html_x( 'Select an option', 'Microsoft Teams', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'default_value'         => 'organization',
			'options'               => automator_array_as_options( $options ),
			'supports_custom_value' => false,
		);
	}

	/**
	 * Normalize a datetime string to ISO 8601 format.
	 *
	 * Parses the input with PHP's DateTime to handle common formatting issues
	 * like missing zero-padding (e.g. "2026-03-15T1:18:00Z" → "2026-03-15T01:18:00Z").
	 *
	 * @param string $datetime The datetime string to normalize.
	 *
	 * @return string The normalized ISO 8601 datetime string.
	 * @throws \Exception If the datetime string cannot be parsed.
	 */
	private function normalize_iso8601( $datetime ) {

		try {
			$dt = new \DateTime( $datetime );
			$dt->setTimezone( new \DateTimeZone( 'UTC' ) );
			return $dt->format( 'Y-m-d\TH:i:s\Z' );
		} catch ( \Exception $e ) {
			throw new \Exception(
				sprintf(
					// translators: %s is the invalid datetime string.
					esc_html_x( '"%s" is not a valid date/time. Expected ISO 8601 format, e.g. 2026-03-15T14:00:00Z', 'Microsoft Teams', 'uncanny-automator' ),
					esc_html( $datetime )
				)
			);
		}
	}
}
