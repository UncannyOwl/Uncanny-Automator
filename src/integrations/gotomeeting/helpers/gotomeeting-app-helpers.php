<?php

namespace Uncanny_Automator\Integrations\Gotomeeting;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Gotomeeting_App_Helpers
 *
 * @package Uncanny_Automator
 */
class Gotomeeting_App_Helpers extends App_Helpers {

	/**
	 * Option key for Client ID.
	 *
	 * @var string
	 */
	const CLIENT_ID_OPTION = 'uap_automator_gtm_api_consumer_key';

	/**
	 * Option key for Client Secret.
	 *
	 * @var string
	 */
	const CLIENT_SECRET_OPTION = 'uap_automator_gtm_api_consumer_secret';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties for the helper
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->set_credentials_option_name( '_uncannyowl_gtm_settings' );
	}

	/**
	 * Prepare credentials for storage
	 *
	 * Extracts token data and user info from GoTo OAuth response.
	 *
	 * @param array $credentials The credentials from GoTo OAuth token response.
	 *
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		// Calculate expiry timestamp from expires_in (typically 3600 seconds).
		$expires_in = $credentials['expires_in'] ?? 3600;
		$expires_at = time() + intval( $expires_in );

		return array(
			'access_token'  => $credentials['access_token'] ?? '',
			'refresh_token' => $credentials['refresh_token'] ?? '',
			'organizer_key' => $credentials['organizer_key'] ?? '',
			'expires_at'    => $expires_at,
			'firstName'     => $credentials['firstName'] ?? '',
			'lastName'      => $credentials['lastName'] ?? '',
			'email'         => $credentials['email'] ?? '',
		);
	}

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Get the Client ID.
	 *
	 * @return string
	 */
	public function get_client_id() {
		return trim( automator_get_option( self::CLIENT_ID_OPTION, '' ) );
	}

	/**
	 * Get the Client Secret.
	 *
	 * @return string
	 */
	public function get_client_secret() {
		return trim( automator_get_option( self::CLIENT_SECRET_OPTION, '' ) );
	}

	/**
	 * Get meeting dropdown option configuration.
	 *
	 * @param string $option_code The option code for the dropdown.
	 *
	 * @return array
	 */
	public function get_meeting_options_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'Meeting', 'GoToMeeting', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'options_show_id'       => false,
			'supports_custom_value' => false,
			'remote_data'           => $this->remote_data_load_config( 'meetings' ),
		);
	}

	/**
	 * Fetch upcoming meetings for the dropdown.
	 *
	 * GoToMeeting V1 /upcomingMeetings returns a flat array of meetings.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_meetings( $request ): array {

		try {
			$response = $this->api->api_request( 'get_upcoming_meetings' );

			if ( 200 !== $response['statusCode'] ) {
				throw new Exception( esc_html_x( 'Unable to fetch meetings from this account', 'GoToMeeting', 'uncanny-automator' ) );
			}

			$jsondata = is_array( $response['data'] ) ? $response['data'] : array();

			if ( count( $jsondata ) < 1 ) {
				throw new Exception( esc_html_x( 'No upcoming meetings were found in this account', 'GoToMeeting', 'uncanny-automator' ) );
			}

			$meetings = array();

			foreach ( $jsondata as $meeting ) {
				// V1 responses use meetingId; create uses meetingid — accept either.
				$meeting_id = $meeting['meetingId'] ?? ( $meeting['meetingid'] ?? '' );

				if ( empty( $meeting_id ) ) {
					continue;
				}

				$meetings[] = array(
					'text'  => $meeting['subject'] ?? (string) $meeting_id,
					'value' => (string) $meeting_id . '-objectkey',
				);
			}

			return $this->remote_data_success( $meetings );

		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get meeting id from parsed action data.
	 *
	 * @param array  $parsed   The parsed action data.
	 * @param string $meta_key The meta key for the meeting field.
	 *
	 * @return string The meeting id.
	 * @throws Exception If the meeting is not set or invalid.
	 */
	public function get_meeting_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Meeting is required.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		// Remove the -objectkey suffix and sanitize.
		$meeting_id = str_replace( '-objectkey', '', sanitize_text_field( $parsed[ $meta_key ] ) );

		if ( empty( $meeting_id ) ) {
			throw new Exception( esc_html_x( 'Invalid meeting id.', 'GoToMeeting', 'uncanny-automator' ) );
		}

		return $meeting_id;
	}
}
