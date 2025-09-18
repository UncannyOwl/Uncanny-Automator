<?php

namespace Uncanny_Automator\Integrations\Zoom;

use Uncanny_Automator\Recipe\App_Action;
use Uncanny_Automator\Integrations\Zoom\Zoom_Common_Trait;
use Uncanny_Automator\Integrations\Zoom\Zoom_Registration_Trait;

/**
 * Class ZOOM_REGISTERUSER
 *
 * @package Uncanny_Automator
 * @property Zoom_App_Helpers $helpers
 * @property Zoom_Api_Caller $api
 */
class ZOOM_REGISTERUSER extends App_Action {

	use Zoom_Common_Trait;
	use Zoom_Registration_Trait;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOOM' );
		$this->set_action_code( 'ZOOMREGISTERUSER' );
		$this->set_action_meta( 'ZOOMMEETING' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );
		// translators: %1$s Meeting topic
		$this->set_sentence( sprintf( esc_html_x( 'Add the user to {{a meeting:%1$s}}', 'Zoom', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add the user to {{a meeting}}', 'Zoom', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_account_users_field(),
			$this->get_user_meetings_field( $this->get_action_meta() ),
			$this->get_meeting_occurrences_field( $this->get_action_meta() ),
			$this->get_meeting_questions_repeater( $this->get_action_meta() ),
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
		// Parse options.
		$user_id     = $this->parse_user_id( $user_id );
		$meeting_key = $this->parse_meeting_key( $this->get_action_meta() );

		// Build user data.
		$meeting_user = $this->build_user_data( $user_id );

		// Add custom questions.
		$questions    = $this->get_parsed_meta_value( 'MEETINGQUESTIONS' );
		$meeting_user = $this->parse_meeting_questions( $meeting_user, $questions, $recipe_id, $user_id, $args );

		// Get meeting occurrences.
		$meeting_occurrences = array();
		$occurrences         = $this->get_parsed_meta_value( 'OCCURRENCES' );
		if ( ! empty( $occurrences ) ) {
			$meeting_occurrences = json_decode( $occurrences );
		}

		// Register user for meeting.
		$this->api->register_user_for_meeting( $meeting_user, $meeting_key, $meeting_occurrences, $action_data );

		return true;
	}
}
