<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

use Uncanny_Automator\Recipe\Action;

/**
 * Class GCALENDAR_REMOVEATTENDEE
 *
 * @package Uncanny_Automator
 */
class GCALENDAR_REMOVEATTENDEE extends Action {

	/**
	 * The prefix for the action fields.
	 *
	 * @var string
	 */
	const PREFIX = 'GCALENDAR_REMOVEATTENDEE';

	protected $helper;

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {
		$this->helper = array_shift( $this->dependencies );
		$this->set_integration( 'GOOGLE_CALENDAR' );
		$this->set_action_code( self::PREFIX . '_CODE' );
		$this->set_action_meta( self::PREFIX . '_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/google-calendar/' ) );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s attendee, %2$s event title, %3$s calendar name
				esc_attr_x( 'Remove {{an attendee:%1$s}} from {{an event:%2$s}} in {{a Google Calendar:%3$s}}', 'Google Calendar', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->get_formatted_code( 'event_id' ) . ':' . $this->get_action_meta(),
				$this->get_formatted_code( 'calendar_id' ) . ':' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove {{an attendee}} from {{an event}} in {{a Google Calendar}}', 'Google Calendar', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}
	/**
	 * Get formatted code.
	 *
	 * @param mixed $option_code The option code.
	 * @return mixed
	 */
	protected function get_formatted_code( $option_code = '' ) {
		return sprintf( '%1$s_%2$s', self::PREFIX, $option_code );
	}
	/**
	 * Options.
	 *
	 * @return mixed
	 */
	public function options() {
		$calendar_option_code = $this->get_formatted_code( 'calendar_id' );
		$event_option_code    = $this->get_formatted_code( 'event_id' );
		$calendar_options     = $this->helper->get_calendar_dropdown_options( false );

		return array(
			array(
				'option_code'           => $calendar_option_code,
				'label'                 => esc_attr_x( 'Calendar', 'Google Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_token'        => true,
				'supports_custom_value' => true,
				'options'               => $calendar_options,
				'is_ajax'               => true,
				'endpoint'              => 'automator_google_calendar_updated_calendars_dropdown',
				'fill_values_in'        => $event_option_code,
				'options_show_id'       => false,
			),
			array(
				'option_code'           => $event_option_code,
				'label'                 => esc_attr_x( 'Event', 'Google Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_token'        => true,
				'supports_custom_value' => true,
				'ajax'                  => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_google_calendar_updated_events_dropdown',
					'listen_fields' => array( $calendar_option_code ),
				),
				'options_show_id'       => false,
			),
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_attr_x( 'Attendee email', 'Google Calendar', 'uncanny-automator' ),
				'input_type'            => 'email',
				'required'              => true,
				'supports_custom_value' => true,
				'supports_token'        => true,
			),
		);
	}
	/**
	 * Process action.
	 *
	 * @param mixed $user_id The user ID.
	 * @param mixed $action_data The data.
	 * @param mixed $recipe_id The ID.
	 * @param mixed $args The arguments.
	 * @param mixed $parsed The parsed.
	 * @return mixed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$calendar_id    = sanitize_text_field( $parsed[ $this->get_formatted_code( 'calendar_id' ) ] ?? '' );
		$event_id       = sanitize_text_field( $parsed[ $this->get_formatted_code( 'event_id' ) ] ?? '' );
		$attendee_email = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		$helper       = $this->helper;
		$client       = $helper->get_client();
		$access_token = is_array( $client ) && isset( $client['access_token'] ) ? $client['access_token'] : '';

		try {
			$body = array(
				'access_token'   => $access_token,
				'action'         => 'remove_attendee',
				'calendar_id'    => $calendar_id,
				'event_id'       => $event_id,
				'attendee_email' => $attendee_email,
			);

			$helper->api_call( $body, $action_data );
			Automator()->complete->action( $user_id, $action_data, $recipe_id );
		} catch ( \Exception $e ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}
		return true;
	}
}
