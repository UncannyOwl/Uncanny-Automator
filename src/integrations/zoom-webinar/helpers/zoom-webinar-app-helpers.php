<?php

namespace Uncanny_Automator\Integrations\Zoom_Webinar;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Zoom_Webinar_App_Helpers
 *
 * @package Uncanny_Automator
 * @property Zoom_Webinar_Api_Caller $api
 */
class Zoom_Webinar_App_Helpers extends App_Helpers {

	/**
	 * Client ID option name.
	 *
	 * @var string
	 */
	const CLIENT_ID = 'uap_automator_zoom_webinar_api_client_id';

	/**
	 * Client secret option name.
	 *
	 * @var string
	 */
	const CLIENT_SECRET = 'uap_automator_zoom_webinar_api_client_secret';

	/**
	 * Account ID option name.
	 *
	 * @var string
	 */
	const ACCOUNT_ID = 'uap_automator_zoom_webinar_api_account_id';

	/**
	 * Set up the helper properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Set existing option names to preserve compatibility.
		$this->set_credentials_option_name( '_uncannyowl_zoom_webinar_settings' );
		$this->set_account_option_name( 'uap_zoom_webinar_api_connected_user' );
	}

	/**
	 * Get const option value.
	 *
	 * @param string $const_name
	 *
	 * @return string
	 */
	public function get_const_option_value( $const_name ) {
		return trim( automator_get_option( $this->get_const( $const_name ), '' ) );
	}

	/**
	 * Ajax get webinar options.
	 *
	 * @return void
	 */
	public function ajax_get_webinars() {

		// Nonce and post object validation.
		Automator()->utilities->ajax_auth_check();

		// Extract the user from POST data.
		$values = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();
		$user   = $values['ZOOMUSER'] ?? '';

		try {
			if ( empty( $user ) ) {
				throw new Exception( esc_html_x( 'Please select a valid user', 'Zoom', 'uncanny-automator' ) );
			}
			// Use API caller to get meetings
			$options = $this->api->get_webinar_options( $user );
			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'options' => array(),
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * AJAX get webinar questions repeater rows.
	 *
	 * @return void
	 */
	public function ajax_get_meeting_questions_repeater() {

		Automator()->utilities->ajax_auth_check();

		$webinar_id = $this->get_group_id_value_from_ajax();

		try {
			if ( empty( $webinar_id ) ) {
				throw new Exception( esc_html_x( 'Webinar ID is required', 'Zoom Webinar', 'uncanny-automator' ) );
			}

			$response = $this->api->get_webinar_questions( $webinar_id );

			if ( 200 !== $response['statusCode'] ) {
				$message = $response['data']['message'] ?? '';
				$message = empty( $message )
					? sprintf(
						// translators: %d Status code
						esc_html_x( 'Could not fetch meeting questions from Zoom. Status code: %d', 'Zoom', 'uncanny-automator' ),
						absint( $response['statusCode'] )
					)
					: $message;
				throw new Exception( esc_html( $message ) );
			}

			$rows      = array();
			$questions = $response['data']['questions'] ?? array();
			$custom    = $response['data']['custom_questions'] ?? array();

			foreach ( $questions as $question ) {
				// Do not add last name field because we already have it in the form.
				if ( 'last_name' === $question['field_name'] ) {
					continue;
				}

				$rows[] = array(
					'QUESTION_NAME' => $question['field_name'],
				);
			}

			foreach ( $custom as $question ) {
				$rows[] = array(
					'QUESTION_NAME' => $question['title'],
				);
			}

			wp_send_json(
				array(
					'success' => true,
					'rows'    => $rows,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'rows'    => array(),
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * AJAX get webinar occurrences.
	 *
	 * @return void
	 */
	public function ajax_get_webinar_occurrences() {
		// Nonce and post object validation.
		Automator()->utilities->ajax_auth_check();

		$webinar_id = $this->get_group_id_value_from_ajax();

		try {
			if ( empty( $webinar_id ) ) {
				throw new Exception( esc_html_x( 'Webinar ID is required', 'Zoom Webinar', 'uncanny-automator' ) );
			}

			$options = $this->api->get_webinar_occurrences_options( $webinar_id );
			wp_send_json(
				array(
					'success' => true,
					'options' => $options,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'options' => array(),
					'error'   => esc_html( $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * AJAX get account users.
	 *
	 * @return void
	 */
	public function ajax_get_account_users() {
		// Nonce and post object validation.
		Automator()->utilities->ajax_auth_check();

		try {
			$users = $this->api->get_account_user_options();
			wp_send_json(
				array(
					'success' => true,
					'options' => $users,
				)
			);
		} catch ( Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'options' => array(),
					'error'   => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Get group id value from AJAX.
	 *
	 * @return string
	 */
	private function get_group_id_value_from_ajax() {
		$group_id = automator_filter_input( 'group_id', INPUT_POST );
		$values   = automator_filter_has_var( 'values', INPUT_POST )
			? automator_filter_input_array( 'values', INPUT_POST )
			: array();
		return $values[ $group_id ] ?? '';
	}

	/**
	 * Get timezone options.
	 *
	 * @return array
	 */
	public function get_timezone_options() {
		$timezones = \DateTimeZone::listIdentifiers( \DateTimeZone::ALL );
		$options   = array();
		foreach ( $timezones as $timezone ) {
			$options[] = array(
				'value' => $timezone,
				'text'  => $timezone,
			);
		}
		return $options;
	}

	/**
	 * Convert datetime string to formatted string.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function convert_datetime( $str ) {

		$timezone    = wp_timezone();
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		$date = new \DateTime( $str );
		$date->setTimezone( $timezone );

		return $date->format( $time_format . ', ' . $date_format );
	}
}
