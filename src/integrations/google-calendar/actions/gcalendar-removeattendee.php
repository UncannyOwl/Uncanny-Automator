<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

/**
 * Class GCALENDAR_REMOVEATTENDEE
 *
 * @property Google_Calendar_Helpers $helpers
 * @property Google_Calendar_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class GCALENDAR_REMOVEATTENDEE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * The prefix for the action fields.
	 *
	 * @var string
	 */
	private $prefix = 'GCALENDAR_REMOVEATTENDEE';

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GOOGLE_CALENDAR' );
		$this->set_action_code( "{$this->prefix}_CODE" );
		$this->set_action_meta( "{$this->prefix}_META" );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/google-calendar/' ) );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s attendee, %2$s event title, %3$s calendar name
				esc_attr_x( 'Remove {{an attendee:%1$s}} from {{an event:%2$s}} in {{a Google Calendar:%3$s}}', 'Google Calendar', 'uncanny-automator' ),
				$this->get_action_meta(),
				"{$this->prefix}_event_id" . ':' . $this->get_action_meta(),
				"{$this->prefix}_calendar_id" . ':' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove {{an attendee}} from {{an event}} in {{a Google Calendar}}', 'Google Calendar', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Options.
	 *
	 * @return mixed
	 */
	public function options() {
		return array(
			$this->helpers->get_calendar_config( "{$this->prefix}_calendar_id" ),
			$this->helpers->get_event_config( "{$this->prefix}_event_id", "{$this->prefix}_calendar_id" ),
			$this->helpers->get_attendee_email_config( $this->get_action_meta() ),
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
	 * @return void
	 * @throws Exception If any fields are invalid or if the API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Validate required fields.
		$calendar_id    = $this->helpers->get_calendar_from_parsed( $parsed, "{$this->prefix}_calendar_id" );
		$event_id       = $this->helpers->get_event_from_parsed( $parsed, "{$this->prefix}_event_id" );
		$attendee_email = $this->helpers->get_attendee_email_from_parsed( $parsed, $this->get_action_meta() );

		$body = array(
			'action'         => 'remove_attendee',
			'calendar_id'    => $calendar_id,
			'event_id'       => $event_id,
			'attendee_email' => $attendee_email,
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}
}
