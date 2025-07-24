<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

use DateTime;
use Uncanny_Automator\Recipe\Action;

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
				'input_type'    => 'text',
				'default_value' => 15,
				'required'      => false,
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
				'timezone'                => apply_filters( 'automator_google_calendar_add_event_timezone', Automator()->get_timezone_string() ),
				// Google Calendar endpoint is written so the date format can be changed from the Client.
				'date_format'             => $this->get_date_format(),
				'time_format'             => $this->get_time_format(),
			);

			$this->helper->api_call(
				$body,
				$action_data
			);

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
}
