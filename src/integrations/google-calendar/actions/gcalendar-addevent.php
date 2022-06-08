<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Actions;

/**
 * Class GCALENDAR_ADDEVENT
 *
 * @package Uncanny_Automator
 */
class GCALENDAR_ADDEVENT {

	use Actions;

	/**
	 * The prefix for the action fields.
	 *
	 * @var string
	 */
	const PREFIX = 'GCALENDAR_ADDEVENT';

	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'GOOGLE_CALENDAR' );

		$this->set_action_code( self::PREFIX . '_CODE' );

		$this->set_action_meta( self::PREFIX . '_META' );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/google-calendar/' ) );

		$this->set_is_pro( false );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
			/* translators: Action sentence */
				esc_attr__( 'Add {{an event:%1$s}} to {{a Google Calendar:%2$s}}', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->get_formatted_code( 'calendar' ) . ':' . $this->get_action_meta()
			)
		);

		$date_format_wp = get_option( 'date_format', 'F j, Y' );

		$timezone_wp = wp_timezone_string();

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Add {{an event}} to {{a Google Calendar}}', 'uncanny-automator' ) );

		$options_group = array(
			$this->get_action_meta() => array(
				// Calendar list.
				array(
					'option_code'           => $this->get_formatted_code( 'calendar' ),
					/* translators: Calendar field */
					'label'                 => esc_attr__( 'Calendar', 'uncanny-automator' ),
					'input_type'            => 'select',
					'is_ajax'               => true,
					'endpoint'              => 'automator_google_calendar_list_calendars_dropdown',
					'required'              => true,
					'supports_custom_value' => false,
					'required'              => true,
					'options_show_id'       => false,
				),
				// Summary.
				array(
					'option_code'           => $this->get_action_meta(),
					/* translators: Calendar field */
					'label'                 => esc_attr__( 'Title', 'uncanny-automator' ),
					'input_type'            => 'text',
					'supports_custom_value' => true,
					'required'              => true,
				),
				// Location.
				array(
					'option_code'           => $this->get_formatted_code( 'location' ),
					/* translators: Calendar field */
					'label'                 => esc_attr__( 'Location', 'uncanny-automator' ),
					'input_type'            => 'text',
					'supports_custom_value' => true,
					'required'              => false,
				),
				// Description.
				array(
					'option_code'           => $this->get_formatted_code( 'description' ),
					/* translators: Calendar field */
					'label'                 => esc_attr__( 'Description', 'uncanny-automator' ),
					'input_type'            => 'textarea',
					'supports_custom_value' => true,
					'required'              => false,
				),
				// Start date.
				array(
					'option_code'     => $this->get_formatted_code( 'start_date' ),
					/* translators: Calendar field */
					'label'           => esc_attr__( 'Start date', 'uncanny-automator' ),
					'input_type'      => 'date',
					'supports_tokens' => true,
					'required'        => true,
					'description'     => sprintf(
						'%1$s <a target="_blank" title="%3$s" href="%2$s">%3$s</a>.',
						esc_attr__( 'Start date must be in the date format set in', 'uncanny-automator' ),
						admin_url( 'options-general.php#timezone_string' ),
						esc_attr__( 'WordPress', 'uncanny-automator' )
					),
				),
				// Start time.
				array(
					'option_code'     => $this->get_formatted_code( 'start_time' ),
					/* translators: Calendar field */
					'label'           => esc_attr__( 'Start time', 'uncanny-automator' ),
					'input_type'      => 'time',
					'supports_tokens' => true,
					'required'        => false,
					'description'     => sprintf(
						'%1$s <a target="_blank" title="%3$s" href="%2$s">%3$s</a>. %4$s',
						esc_attr__( 'The event time will match the timezone set in', 'uncanny-automator' ),
						admin_url( 'options-general.php#timezone_string' ),
						esc_attr__( 'WordPress Settings', 'uncanny-automator' ),
						esc_attr__( 'Leave blank to create an all-day event.', 'uncanny-automator' )
					),
				),
				// End date.
				array(
					'option_code'     => $this->get_formatted_code( 'end_date' ),
					/* translators: Calendar field */
					'label'           => esc_attr__( 'End date', 'uncanny-automator' ),
					'input_type'      => 'date',
					'supports_tokens' => true,
					'required'        => true,
					'description'     => sprintf(
						'%1$s <a target="_blank" title="%3$s" href="%2$s">%3$s</a>.',
						esc_attr__( 'End date must be in the date format set in', 'uncanny-automator' ),
						admin_url( 'options-general.php#timezone_string' ),
						esc_attr__( 'WordPress', 'uncanny-automator' )
					),
				),
				// End time.
				array(
					'option_code'     => $this->get_formatted_code( 'end_time' ),
					/* translators: Calendar field */
					'label'           => esc_attr__( 'End time', 'uncanny-automator' ),
					'input_type'      => 'time',
					'supports_tokens' => true,
					'required'        => false,
					'description'     => sprintf(
						'%1$s <a target="_blank" title="%3$s" href="%2$s">%3$s</a>. %4$s',
						esc_attr__( 'The event time will match the timezone set in', 'uncanny-automator' ),
						admin_url( 'options-general.php#timezone_string' ),
						esc_attr__( 'WordPress Settings', 'uncanny-automator' ),
						esc_attr__( 'Leave blank to create an all-day event.', 'uncanny-automator' )
					),
				),
				// Attendees.
				array(
					'option_code'           => $this->get_formatted_code( 'attendees' ),
					/* translators: Calendar field */
					'label'                 => esc_attr__( 'Attendees', 'uncanny-automator' ),
					'description'           => esc_attr__( 'Comma separated email addresses of the attendees', 'uncanny-automator' ),
					'input_type'            => 'text',
					'supports_custom_value' => true,
					'required'              => false,
				),
				// Email Notifications.
				array(
					'option_code'   => $this->get_formatted_code( 'notification_email' ),
					/* translators: Calendar field */
					'label'         => esc_attr__( 'Enable email notifications in Google Calendar', 'uncanny-automator' ),
					'input_type'    => 'checkbox',
					'default_value' => true,
				),

				// Notification time.
				array(
					'option_code'   => $this->get_formatted_code( 'notification_time_email' ),
					/* translators: Calendar field */
					'label'         => esc_attr__( 'Minutes before event to trigger email notification', 'uncanny-automator' ),
					'description'   => esc_attr__( 'If no value is entered, the notification will fire 15 minutes before the event.', 'uncanny-automator' ),
					'placeholder'   => esc_attr__( '15', 'uncanny-automator' ),
					'input_type'    => 'text',
					'default_value' => 15,
					'required'      => false,
				),
				// Popup Notifications.
				array(
					'option_code'   => $this->get_formatted_code( 'notification_popup' ),
					/* translators: Calendar field */
					'label'         => esc_attr__( 'Enable popup notifications in Google Calendar', 'uncanny-automator' ),
					'input_type'    => 'checkbox',
					'default_value' => true,
				),
				array(
					'option_code'   => $this->get_formatted_code( 'notification_time_popup' ),
					/* translators: Calendar field */
					'label'         => esc_attr__( 'Minutes before event to trigger popup notification', 'uncanny-automator' ),
					'description'   => esc_attr__( 'If no value is entered, the notification will fire 15 minutes before the event.', 'uncanny-automator' ),
					'placeholder'   => esc_attr__( '15', 'uncanny-automator' ),
					'input_type'    => 'text',
					'default_value' => 15,
					'required'      => false,
				),
			),
		);

		$this->set_options_group( $options_group );

		$this->register_action();

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

		$helper = Automator()->helpers->recipe->google_calendar->options;

		$body = array(
			'action'                  => 'create_event',
			'access_token'            => $helper->get_client(),
			'summary'                 => $summary,
			'location'                => $location,
			'calendar_id'             => $calendar_id,
			'description'             => $description,
			'start_date'              => $start_date,
			'start_time'              => $start_time,
			'end_date'                => $end_date,
			'end_time'                => $end_time,
			'attendees'               => str_replace( ' ', '', trim( $attendees ) ),
			'notification_email'      => $notification_email,
			'notification_popup'      => $notification_popup,
			'notification_time_email' => $notification_time_email,
			'notification_time_popup' => $notification_time_popup,
			'timezone'                => apply_filters( 'automator_google_calendar_add_event_timezone', wp_timezone_string() ),
			'date_format'             => 'Y-m-d',
		);

		try {
			$response = $helper->api_call(
				$body,
				$action_data
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

}
