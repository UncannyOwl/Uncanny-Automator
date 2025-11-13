<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Google_Calendar_Helpers
 *
 * @property Google_Calendar_Api_Caller $api
 */
class Google_Calendar_Helpers extends App_Helpers {

	/**
	 * "Account info" transient key.
	 *
	 * @var string
	 */
	const RESOURCE_OWNER_KEY = 'automator_google_calendar_user_info';

	/**
	 * "Calendar list" transient key.
	 *
	 * @var string
	 */
	const CALENDARS_KEY = 'automator_google_calendar_calendar_list';


	/////////////////////////////////////////////////////////////
	// Abstract overrides.
	/////////////////////////////////////////////////////////////

	/**
	 * Method get_user_info - Override to use transients like Google Contacts pattern
	 *
	 * @return array The user info.
	 */
	public function get_account_info() {

		$user_info = array(
			'avatar_uri' => '',
			'name'       => '',
			'email'      => '',
		);

		$saved_user_info = get_transient( self::RESOURCE_OWNER_KEY );

		if ( false !== $saved_user_info && ! empty( $saved_user_info['email'] ) ) {
			return $saved_user_info;
		}

		try {
			$user = $this->api->request_resource_owner();

			if ( empty( $user['data'] ) ) {
				throw new Exception( 'No user info found', 404 );
			}

			$user_info = array(
				'name'       => $user['data']['name'] ?? '',
				'avatar_uri' => $user['data']['picture'] ?? '',
				'email'      => $user['data']['email'] ?? '',
			);

			$this->store_account_info( $user_info );

			return $user_info;

		} catch ( Exception $e ) {
			// Clear the connection.
			$this->clear_connection();

			// Customize the error message.
			$error_message = sprintf(
				'An error has occurred while fetching the resource owner: (%s) %s',
				absint( $e->getCode() ),
				esc_html( $e->getMessage() )
			);

			throw new Exception( esc_html( $error_message ), absint( $e->getCode() ) );
		}
	}

	/**
	 * Store account info - Override to use transients like Google Contacts pattern
	 *
	 * @param array $user_info The user info.
	 *
	 * @return void
	 */
	public function store_account_info( $user_info ) {
		set_transient( self::RESOURCE_OWNER_KEY, $user_info, DAY_IN_SECONDS );
	}

	/**
	 * Delete account info - Override to use transients like Google Contacts pattern
	 *
	 * @return void
	 */
	public function delete_account_info() {
		delete_transient( self::RESOURCE_OWNER_KEY );
	}

	/////////////////////////////////////////////////////////////
	// Data methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Get calendars data.
	 *
	 * @param bool $refresh Whether to refresh the options.
	 *
	 * @return array The calendar options.
	 * @throws Exception If no calendars are found.
	 */
	public function get_calendars( $refresh = false ) {

		$response = $refresh ? array() : get_transient( self::CALENDARS_KEY );
		$data     = $response['data'] ?? array();

		if ( ! empty( $data ) ) {
			return $data;
		}

		$response = $this->api->api_request( 'list_calendars' );
		$data     = $response['data'] ?? array();

		if ( empty( $data ) || ! is_array( $data ) ) {
			throw new Exception( 'No calendars found', 404 );
		}

		set_transient( self::CALENDARS_KEY, $response, 5 * MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Method ajax_get_calendar_options.
	 *
	 * A wp_ajax callback method.
	 *
	 * @return void.
	 */
	public function ajax_get_calendar_options() {
		Automator()->utilities->ajax_auth_check();

		try {
			$calendars = $this->get_calendars( $this->is_ajax_refresh() );
			wp_send_json(
				array(
					'success' => true,
					'options' => $this->parse_response( $calendars ),
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * Get events data.
	 *
	 * @param string $calendar_id The calendar ID.
	 *
	 * @return array The event options.
	 * @throws Exception If no events are found.
	 */
	public function get_event_options( $calendar_id ) {

		if ( empty( $calendar_id ) ) {
			throw new Exception(
				esc_html_x( 'Please select a calendar.', 'Google Calendar', 'uncanny-automator' )
			);
		}

		$response = $this->api->api_request(
			array(
				'action'      => 'list_events',
				'calendar_id' => $calendar_id,
				'timezone'    => Automator()->get_timezone_string(),
			)
		);

		$events = $response['data'] ?? array();

		if ( empty( $events ) || ! is_array( $events ) ) {
			throw new Exception( 'No events found', 404 );
		}

		$options = array();

		foreach ( $events as $event ) {

			if ( empty( $event['summary'] ) ) {
				continue;
			}

			// Date start can either be from [date_start] or from [datetime_start].
			// Google API returns date_start if its wholeday event. Otherwise, datetime_start.
			$date_start = $event['datetime_start'] ?? '';
			$type       = 'datetime';

			if ( empty( $date_start ) ) {
				// Try the [date_start]. The [date_start] AND the [datetime_start] cannot be both nulled at the same time.
				$date_start = $event['date_start'] ?? '';
				$type       = 'date';
			}

			$date        = new \DateTime( $date_start, new \DateTimeZone( Automator()->get_timezone_string() ) );
			$date_string = $date->format( 'F j, Y' );

			if ( 'datetime' === $type ) {
				$date_string = $date->format( 'F j, Y g:i A' );
			}

			$options[] = array(
				'text'  => sprintf( '%1$s (%2$s)', $event['summary'], $date_string ),
				'value' => $event['id'],
			);
		}

		return $options;
	}

	/**
	 * Ajax get event options for a calendar.
	 *
	 * @return void.
	 */
	public function ajax_get_event_options() {
		Automator()->utilities->ajax_auth_check();

		// Get values array.
		$values = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();

		// Get the calendar ID by replacing _event_id with _calendar_id from the field_id.
		$field_id     = automator_filter_input( 'field_id', INPUT_POST );
		$calendar_key = str_replace( '_event_id', '_calendar_id', $field_id );
		$calendar_id  = $values[ $calendar_key ] ?? '';

		try {
			$events = $this->get_event_options( $calendar_id );
			wp_send_json(
				array(
					'success' => true,
					'options' => $events,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/////////////////////////////////////////////////////////////
	// Helper methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Method parse_response.
	 *
	 * @param mixed $response The response from API Call.
	 *
	 * @return array The parsed response.
	 */
	public function parse_response( $response ) {
		return array_map(
			function ( $response ) {
				return array(
					'text'  => $response['summary'],
					'value' => $response['id'],
				);
			},
			$response
		);
	}

	/**
	 * Clears the connection data.
	 *
	 * @return true
	 */
	public function clear_connection() {
		$this->delete_credentials();
		$this->delete_account_info();
		return true;
	}

	/////////////////////////////////////////////////////////////
	// Common recipe config methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Method get_calendar_config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array The calendar config.
	 */
	public function get_calendar_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'Calendar', 'Google Calendar', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'supports_token'        => true,
			'supports_custom_value' => true,
			'options'               => array(),
			'options_show_id'       => false,
			'ajax'                  => array(
				'event'    => 'on_load',
				'endpoint' => 'automator_google_calendar_get_calendar_options',
			),
		);
	}

	/**
	 * Method get_event_config.
	 *
	 * @param string $option_code The option code.
	 * @param string $listen_field_code The listen field code.
	 *
	 * @return array The event config.
	 */
	public function get_event_config( $option_code, $listen_field_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'Event', 'Google Calendar', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'supports_token'        => true,
			'supports_custom_value' => true,
			'options_show_id'       => false,
			'ajax'                  => array(
				'event'         => 'parent_fields_change',
				'endpoint'      => 'automator_google_calendar_get_event_options',
				'listen_fields' => array( $listen_field_code ),
			),
		);
	}

	/**
	 * Method get_attendee_email_config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array The attendee email config.
	 */
	public function get_attendee_email_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'Attendee email', 'Google Calendar', 'uncanny-automator' ),
			'input_type'            => 'email',
			'required'              => true,
			'supports_token'        => true,
			'supports_custom_value' => true,
		);
	}

	/////////////////////////////////////////////////////////////
	// Common recipe parsing methods.
	/////////////////////////////////////////////////////////////

	/**
	 * Get calendar ID from parsed data.
	 *
	 * @param array $parsed The parsed data.
	 * @param string $option_code The option code.
	 *
	 * @return string The calendar ID.
	 * @throws Exception If the calendar ID is not found.
	 */
	public function get_calendar_from_parsed( $parsed, $option_code ) {
		$calendar_id = sanitize_text_field( $parsed[ $option_code ] ?? '' );
		if ( empty( $calendar_id ) ) {
			throw new Exception( esc_html_x( 'Invalid calendar ID', 'Google Calendar', 'uncanny-automator' ) );
		}
		return $calendar_id;
	}

	/**
	 * Get event ID from parsed data.
	 *
	 * @param array $parsed The parsed data.
	 * @param string $option_code The option code.
	 *
	 * @return string The event ID.
	 * @throws Exception If the event ID is not found.
	 */
	public function get_event_from_parsed( $parsed, $option_code ) {
		$event_id = sanitize_text_field( $parsed[ $option_code ] ?? '' );
		if ( empty( $event_id ) ) {
			throw new Exception( esc_html_x( 'Invalid event ID', 'Google Calendar', 'uncanny-automator' ) );
		}
		return $event_id;
	}

	/**
	 * Get attendee email from parsed data.
	 *
	 * @param array $parsed The parsed data.
	 * @param string $option_code The option code.
	 *
	 * @return string The attendee email.
	 * @throws Exception If the attendee email is not found.
	 */
	public function get_attendee_email_from_parsed( $parsed, $option_code ) {
		$email = sanitize_text_field( $parsed[ $option_code ] ?? '' );
		if ( empty( $email ) || false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( esc_html_x( 'Invalid attendee email', 'Google Calendar', 'uncanny-automator' ) );
		}
		return $email;
	}
}
