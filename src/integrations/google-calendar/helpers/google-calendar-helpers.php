<?php
namespace Uncanny_Automator\Integrations\Google_Calendar;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * Class Google_Calendar_Helpers
 *
 * @package Uncanny_Automator
 */
class Google_Calendar_Helpers {

	/**
	 * The Pro helpers options object.
	 *
	 * @var string|object
	 */
	public $pro = '';

	/**
	 * The helpers options object.
	 *
	 * @var string|object
	 */
	public $options = '';

	/**
	 * The wp_options table key for selecting the integration options.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'automator_google_calendar_credentials';

	/**
	 * The nonce key for the Google Calendar integration.
	 *
	 * @var string
	 */
	const NONCE = 'automator_api_google_calendar_authorize';

	/**
	 * The public API edge.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'v2/google-calendar';

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Process authentication.
		add_action( 'wp_ajax_automator_google_calendar_process_code_callback', array( $this, 'automator_google_calendar_process_code_callback' ) );

		// Disconnect.
		add_action( 'wp_ajax_automator_google_calendar_disconnect_user', array( $this, 'disconnect_user' ) );

		// List calendars - Legacy
		add_action( 'wp_ajax_automator_google_calendar_list_calendars', array( $this, 'list_calendars' ) );

		// List calendars dropdown - Legacy
		add_action( 'wp_ajax_automator_google_calendar_list_calendars_dropdown', array( $this, 'list_calendars_dropdown' ) );

		// List events - Legacy
		add_action( 'wp_ajax_automator_google_calendar_list_events', array( $this, 'list_events' ) );

		// List calendars dropdown - Modern
		add_action( 'wp_ajax_automator_google_calendar_updated_calendars_dropdown', array( $this, 'get_updated_calendars_dropdown' ) );

		// List events - Modern
		add_action( 'wp_ajax_automator_google_calendar_updated_events_dropdown', array( $this, 'get_updated_events_dropdown' ) );

		require_once __DIR__ . '/../settings/settings-google-calendar.php';

		new Google_Calendar_Settings( $this );
	}

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
	 * Method list_calendars_dropdown.
	 *
	 * A wp_ajax callback method.
	 *
	 * @return void.
	 */
	public function list_calendars_dropdown() {

		Automator()->utilities->ajax_auth_check();

		$response = get_transient( 'automator_google_calendar_calendar_list' );

		// Serve from cache.
		if ( false !== $response ) {
			wp_send_json( $this->parse_response( $response['data'] ) );
		}

		// Otherwise, request live data.
		try {

			$response = $this->api_call(
				array(
					'action' => 'list_calendars',
				)
			);

			set_transient( 'automator_google_calendar_calendar_list', $response, 5 * MINUTE_IN_SECONDS );

			wp_send_json( $this->parse_response( $response['data'] ) );

		} catch ( \Exception $e ) {

			wp_send_json(
				array(
					array(
						/* translators: Error message */
						'text'  => sprintf( esc_html_x( '%1$s: %2$s Please try again later.', 'Google Calendar', 'uncanny-automator' ), $e->getCode(), $e->getMessage() ),
						'value' => '-1',
					),
				)
			);

		}
	}


	/**
	 * Method list_calendars.
	 *
	 * A wp_ajax callback method.
	 *
	 * @return void.
	 */
	public function list_calendars() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
			wp_send_json(
				array(
					'error' => 'Authentication failed. Invalid nonce.',
				)
			);
		}

		$response = get_transient( 'automator_google_calendar_calendar_list' );

		if ( false !== $response ) {
			wp_send_json( $response );
		}

		$body = array(
			'action' => 'list_calendars',
		);

		try {

			$response = $this->api_call( $body, null );

			set_transient( 'automator_google_calendar_calendar_list', $response, 5 * MINUTE_IN_SECONDS );

			wp_send_json( $response );

		} catch ( \Exception $e ) {

			$error_message = $e->getMessage();

			wp_send_json(
				array(
					'error'      => $error_message,
					'error_code' => $e->getCode(),
				)
			);

		}
	}

	/**
	 * Method list_events.
	 *
	 * A wp_ajax callback method.
	 *
	 * @return void.
	 */
	public function list_events() {

		Automator()->utilities->ajax_auth_check();

		$items = array();

		try {

			$response = $this->api_call(
				array(
					'action'      => 'list_events',
					'calendar_id' => automator_filter_input( 'value', INPUT_POST ),
					'timezone'    => Automator()->get_timezone_string(),
				)
			);

			if ( is_array( $response['data'] ) && ! empty( $response['data'] ) ) {

				foreach ( $response['data'] as $event ) {

					if ( ! empty( $event['summary'] ) ) {

						// Date start can either be from [date_start] or from [datetime_start].
						// Google API returns date_start if its wholeday event. Otherwise, datetime_start.
						$date_start = isset( $event['datetime_start'] ) ? $event['datetime_start'] : '';

						$type = 'datetime';

						if ( empty( $date_start ) ) {
							// Try the [date_start]. The [date_start] AND the [datetime_start] cannot be both nulled at the same time.
							$date_start = isset( $event['date_start'] ) ? $event['date_start'] : '';
							$type       = 'date';
						}

						$date = new \DateTime( $date_start, new \DateTimeZone( Automator()->get_timezone_string() ) );

						$date_string = $date->format( 'F j, Y' );

						if ( 'datetime' === $type ) {
							$date_string = $date->format( 'F j, Y g:i A' );
						}

						$items[] = array(
							'text'  => sprintf( '%1$s (%2$s)', $event['summary'], $date_string ),
							'value' => $event['id'],
						);

					}
				}
			}
		} catch ( \Exception $e ) {

			$items = array(
				array(
					/* translators: Error message */
					'text'  => sprintf( esc_html_x( '%1$s: %2$s Please try again later.', 'Google Calendar', 'uncanny-automator' ), $e->getCode(), $e->getMessage() ),
					'value' => '',
				),
			);

		}

		if ( ! empty( $events ) ) {
			foreach ( $events as $event ) {
				$items[] = array(
					'text'  => $event['summary'],
					'value' => $event['id'],
				);
			}
		}

		wp_send_json( $items );
	}

	/**
	 * Method automator_google_calendar_process_code_callback.
	 *
	 * A wp_ajax callback.
	 *
	 * @return void.
	 */
	public function automator_google_calendar_process_code_callback() {

		// Redirect if there are any errors.
		$this->auth_redirect_when_error(
			automator_filter_input( 'nonce' ),
			automator_filter_input( 'auth_error' )
		);

		// Persist connection if okay.
		$is_connected = $this->auth_persist_connection(
			automator_filter_input( 'automator_api_message' ),
			wp_create_nonce( self::NONCE )
		);

		if ( $is_connected ) {
			$this->redirect_with_success( 200 );
		}

		$this->redirect_with_error( 'generic_error' );

		wp_die();
	}

	/**
	 * Method auth_redirect_when_error
	 *
	 * Redirects if nonce is invalid, or if auth is successful.
	 *
	 * @param string $nonce The nonce.
	 * @param string $invoked_errors The displayed error message.
	 *
	 * @return void.
	 */
	protected function auth_redirect_when_error( $nonce = '', $invoked_errors = '' ) {

		// Check nonce.
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'manage_options' ) ) {
			$this->redirect_with_error( 'You are not allowed to do this.' );
		}

		// Redirect if there are any errors.
		if ( ! empty( $invoked_errors ) ) {
			$this->redirect_with_error( str_replace( ' ', '_', strtolower( rawurlencode( $invoked_errors ) ) ) );
		}
	}

	/**
	 * Method redirect_with_success.
	 *
	 * Redirects with a success message.
	 *
	 * @param string $success_message The success message.
	 *
	 * return void.
	 */
	public function redirect_with_success( $success_message = '' ) {

		wp_safe_redirect(
			$this->get_settings_page_url(
				array(
					'auth_success' => $success_message,
				)
			)
		);

		exit;
	}

	/**
	 * Method redirect_with_error.
	 *
	 * Redirects with an error message.
	 *
	 * @param string $error_message The error message.
	 *
	 * return void.
	 */
	public function redirect_with_error( $error_message = '' ) {

		wp_safe_redirect(
			$this->get_settings_page_url(
				array(
					'auth_error' => $error_message,
				)
			)
		);

		exit;
	}

	/**
	 * Method auth_persist_connection.
	 *
	 * Save the connection data to wp_options.
	 *
	 * @param string $api_message The secret message from the API.
	 * @param string $secret The secret token.
	 *
	 * @return boolean True if persists successfully. Otherwise, false.
	 */
	protected function auth_persist_connection( $api_message = '', $secret = '' ) {

		$tokens = Automator_Helpers_Recipe::automator_api_decode_message( $api_message, $secret );

		if ( false !== $tokens ) {

			if ( $this->has_missing_scopes( $tokens ) ) {

				$this->redirect_with_error( esc_html_x( 'missing_scope', 'Google Calendar', 'uncanny-automator' ) );

			}

			automator_update_option( self::OPTION_KEY, $tokens );

			return true;

		}

		return false;
	}

	/**
	 * Method has_missing_scopes.
	 *
	 * Checks if the user has missing scopes. Scopes are checked during OAuth consent screen.
	 *
	 * @param mixed $token The access token combination.
	 *
	 * @return boolean True if there are scopes missing. Otherwise, false.
	 */
	public function has_missing_scopes( $token ) {

		if ( ! isset( $token['scope'] ) || empty( $token['scope'] ) ) {
			return true;
		}

		$scopes = array(
			'https://www.googleapis.com/auth/calendar',
			'https://www.googleapis.com/auth/calendar.events',
		);

		$has_missing_scope = false;

		foreach ( $scopes as $scope ) {
			if ( false === strpos( $token['scope'], $scope ) ) {
				$has_missing_scope = true;
			}
		}

		return $has_missing_scope;
	}

	/**
	 * Method get_settings_page_url.
	 *
	 * Create and retrieve the settings page uri.
	 *
	 * @param array $params The url query parameters.
	 *
	 * @return string The Google Calendar settings page.
	 */
	public function get_settings_page_url( $params = array() ) {

		return add_query_arg(
			array_merge(
				array(
					'post_type'   => 'uo-recipe',
					'page'        => 'uncanny-automator-config',
					'tab'         => 'premium-integrations',
					'integration' => 'google-calendar',
				),
				$params
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Method get_disconnect_url.
	 *
	 * @return string Returns the wp_ajax disconnect url callback.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'action' => 'automator_google_calendar_disconnect_user',
				'nonce'  => wp_create_nonce( 'automator-google-calendar-user-disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Method get_authentication_url.
	 *
	 * @return string The OAuth consent screen url.
	 */
	public function get_authentication_url() {

		// Create nonce.
		$nonce = wp_create_nonce( self::NONCE );

		// Construct the redirect uri.
		$redirect_uri = add_query_arg(
			array(
				'action' => 'automator_google_calendar_process_code_callback',
				'nonce'  => $nonce,
			),
			admin_url( 'admin-ajax.php' )
		);

		// Construct the OAuth uri.
		$auth_uri = add_query_arg(
			array(
				'action'       => 'authorization_request',
				'redirect_url' => rawurlencode( $redirect_uri ),
				'nonce'        => $nonce,
				'plugin_ver'   => AUTOMATOR_PLUGIN_VERSION,
			),
			AUTOMATOR_API_URL . 'v2/google-calendar'
		);

		return $auth_uri;
	}

	/**
	 * Method get_client.
	 *
	 * @return mixed The Google Client that was saved in wp_options (self::OPTION_KEY)
	 */
	public function get_client() {

		return automator_get_option( self::OPTION_KEY, false );
	}


	/**
	 * Method get_user_info.
	 *
	 * @return array The user info.
	 */
	public function get_user_info() {

		$user_info = array(
			'avatar_uri' => '',
			'name'       => '',
			'email'      => '',
		);

		$transient_key = 'automator_google_calendar_user_info';

		$saved_user_info = get_transient( $transient_key );

		if ( false !== $saved_user_info ) {

			return $saved_user_info;

		}

		try {

			$user = $this->api_user_info();

			if ( empty( $user['data'] ) ) {
				return $user_info;
			}

			$user_info['name'] = $user['data']['name'];

			$user_info['avatar_uri'] = $user['data']['picture'];

			$user_info['email'] = $user['data']['email'];

			set_transient( $transient_key, $user_info, DAY_IN_SECONDS );

		} catch ( \Exception $e ) {

			return $user_info;

		}

		return $user_info;
	}

	/**
	 * Method get_calendar_options.
	 *
	 * Retrieves the calendar options fields.
	 *
	 * @return array The option items.
	 */
	public function get_calendar_options() {

		$response = get_transient( 'automator_google_calendar_calendar_list' );

		$items = array();

		// Serve from cache.
		if ( false !== $response ) {

			if ( is_array( $response ) && ! empty( $response ) ) {

				$response = $this->parse_response( $response['data'] );

				foreach ( $response as $calendar ) {
					$items[] = array(
						'value' => $calendar['value'],
						'text'  => $calendar['text'],
					);
				}
			}

			return $items;
		}

		// Otherwise, request live data.
		try {

			$response = $this->api_call(
				array(
					'action' => 'list_calendars',
				),
				null
			);

			if ( is_array( $response ) && ! empty( $response ) ) {

				$response = $this->parse_response( $response['data'] );

				foreach ( $response as $calendar ) {
					$items[] = array(
						'value' => $calendar['value'],
						'text'  => $calendar['text'],
					);
				}
			}

			return $items;

		} catch ( \Exception $e ) {

			$items = array(
				/* translators: Error message */
				'' => sprintf( esc_html_x( '%1$s: %2$s Please try again later.', 'Google Calendar', 'uncanny-automator' ), $e->getCode(), $e->getMessage() ),
			);

		}

		return $items;
	}


	/**
	 * Method is_user_connected
	 *
	 * @return boolean True if has Google Client. Otherwise, false.
	 */
	public function is_user_connected() {

		return ! empty( $this->get_client() );
	}

	/**
	 * Method api_get_user_info.
	 *
	 * @return string The api response.
	 */
	public function api_user_info() {

		$client = $this->get_client();

		if ( empty( $client['scope'] ) ) {
			return;
		}

		$body = array(
			'action' => 'user_info',
		);

		$response = false;

		try {

			$response = $this->api_call( $body, null );

		} catch ( \Exception $e ) {

			automator_log( $e->getMessage() );

		}

		return $response;
	}

	/**
	 * Method disconnect_user.
	 *
	 * Removes the Google Calendar settings from wp_options table.
	 *
	 * @return void|null|array.
	 */
	public function disconnect_user() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), 'automator-google-calendar-user-disconnect' ) ) {
			wp_die( esc_html_x( 'Nonce Verification Failed', 'Google Calendar', 'uncanny-automator' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html_x( 'Unauthorized', 'Google Calendar', 'uncanny-automator' ) );
		}

		// De-authorize app.
		$this->api_revoke_access();

		// Delete the connection settings.
		$this->disconnect_client();

		wp_safe_redirect( $this->get_settings_page_url() );

		exit;
	}

	/**
	 * Method api_revoke_access.
	 *
	 * @return void
	 */
	public function api_revoke_access() {

		try {

			$body = array(
				'action' => 'revoke_access',
			);

			$response = $this->api_call( $body );

		} catch ( \Exception $e ) {

			automator_log( $e->getMessage(), true );

		}
	}

	/**
	 * Method disconnect_client.
	 *
	 * Deletes all Google Calendar transients and option.
	 *
	 * @return boolean True.
	 */
	public function disconnect_client() {

		delete_transient( 'automator_google_calendar_calendar_list' );

		delete_transient( 'automator_google_calendar_user_info' );

		automator_delete_option( self::OPTION_KEY );

		return true;
	}


	/**
	 * Method api_call
	 *
	 * @param  array $body The request body form-data.
	 * @param  array $action The Automator Action parameters.
	 *
	 * @return string Json encoded response from API.
	 */
	public function api_call( $body, $action = null ) {

		$body['access_token'] = wp_json_encode( $this->get_client() );

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action,
			'timeout'  => 15,
		);

		$response = Api_Server::api_call( $params );

		if ( 200 !== $response['statusCode'] ) {
			throw new \Exception(
				sprintf(
				/* translators: %s: API endpoint */
					esc_html_x( '%s failed', 'Google Calendar', 'uncanny-automator' ),
					esc_html( $params['endpoint'] )
				)
			);
		}

		return $response;
	}

	/**
	 * Get calendar options for dropdown in modern dropdown.
	 *
	 * @param bool $include_any Whether to include "Any" option.
	 *
	 * @return array The calendar options.
	 */
	public function get_calendar_dropdown_options( $include_any = true ) {
		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any calendar', 'Calendar selection', 'uncanny-automator' ),
			);
		}

		$response = get_transient( 'automator_google_calendar_calendar_list' );

		if ( false !== $response ) {
			return array_merge( $options, $this->parse_response( $response['data'] ) );
		}

		try {
			$response = $this->api_call(
				array(
					'action' => 'list_calendars',
				)
			);

			set_transient( 'automator_google_calendar_calendar_list', $response, 5 * MINUTE_IN_SECONDS );

			return array_merge( $options, $this->parse_response( $response['data'] ) );

		} catch ( \Exception $e ) {
			return array(
				array(
					'value' => '',
					// translators: %1$s: Error code, %2$s: Error message
					'text'  => sprintf( esc_html_x( '%1$s: %2$s Please try again later.', 'Google Calendar', 'uncanny-automator' ), $e->getCode(), $e->getMessage() ),
				),
			);
		}
	}

	/**
	 * Get event options for dropdown.
	 *
	 * @param string $calendar_id The calendar ID.
	 * @param bool   $include_any Whether to include "Any" option.
	 *
	 * @return array The event options.
	 */
	protected function get_event_dropdown_options( $calendar_id, $include_any = true ) {
		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any event', 'Event selection', 'uncanny-automator' ),
			);
		}

		if ( intval( '-1' ) === intval( $calendar_id ) || 'automator_custom_value' === $calendar_id ) {
			return $options;
		}

		try {
			$response = $this->api_call(
				array(
					'action'      => 'list_events',
					'calendar_id' => $calendar_id,
					'timezone'    => Automator()->get_timezone_string(),
				)
			);

			if ( is_array( $response['data'] ) && ! empty( $response['data'] ) ) {
				foreach ( $response['data'] as $event ) {
					if ( ! empty( $event['summary'] ) ) {
						$date_start = isset( $event['datetime_start'] ) ? $event['datetime_start'] : '';
						$type       = 'datetime';

						if ( empty( $date_start ) ) {
							$date_start = isset( $event['date_start'] ) ? $event['date_start'] : '';
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
				}
			}
		} catch ( \Exception $e ) {

			$options = array(
				array(
					// translators: %1$s: Error code, %2$s: Error message
					'text'  => sprintf( esc_html_x( '%1$s: %2$s Please try again later.', 'Google Calendar', 'uncanny-automator' ), esc_html( $e->getCode() ), esc_html( $e->getMessage() ) ),
					'value' => '',
				),
			);
		}

		return $options;
	}

	/**
	 * Modern handler for calendar dropdown.
	 *
	 * @return void
	 */
	public function get_updated_calendars_dropdown() {
		Automator()->utilities->ajax_auth_check();

		$options = $this->get_calendar_dropdown_options();

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}


	/**
	 * Modern handler for dynamically loading events based on selected calendar.
	 *
	 * @return void
	 */

	public function get_updated_events_dropdown() {

		// Check for proper AJAX request authorization and nonce verification
		Automator()->utilities->ajax_auth_check();

		$values = automator_filter_has_var( 'values', INPUT_POST )
		? automator_filter_input_array( 'values', INPUT_POST )
		: array();

		$calendar_id = '';

		if ( ! empty( $values ) ) {
			foreach ( $values as $key => $val ) {
				if ( false !== stripos( $key, 'calendar_id' ) ) {
					$calendar_id = sanitize_text_field( $val );
					break;
				}
			}
		}

		if ( empty( $calendar_id ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html_x( 'Calendar ID is required.', 'Google Calendar', 'uncanny-automator' ),
				)
			);
		}

		try {
			$events = $this->get_event_dropdown_options( $calendar_id, false );

			wp_send_json(
				array(
					'success' => true,
					'options' => $events,
				)
			);
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}
}
