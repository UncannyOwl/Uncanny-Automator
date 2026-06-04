<?php

namespace Uncanny_Automator\Integrations\Gotowebinar;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Gotowebinar_App_Helpers
 *
 * @package Uncanny_Automator
 */
class Gotowebinar_App_Helpers extends App_Helpers {

	/**
	 * Option key for Client ID.
	 *
	 * @var string
	 */
	const CLIENT_ID_OPTION = 'uap_automator_gtw_api_consumer_key';

	/**
	 * Option key for Client Secret.
	 *
	 * @var string
	 */
	const CLIENT_SECRET_OPTION = 'uap_automator_gtw_api_consumer_secret';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties for the helper
	 *
	 * @return void
	 */
	public function set_properties() {
		// Preserve existing option names.
		$this->set_credentials_option_name( '_uncannyowl_gtw_settings' );
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
	 * Get webinar dropdown option configuration.
	 *
	 * @param string $option_code The option code for the dropdown.
	 *
	 * @return array
	 */
	public function get_webinar_options_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'Webinar', 'GoToWebinar', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'options_show_id'       => false,
			'supports_custom_value' => false,
			'remote_data'           => $this->remote_data_load_config( 'webinars' ),
		);
	}

	/**
	 * Fetch webinars for the dropdown.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_webinars( $request ): array {

		try {
			$response = $this->api->api_request( 'get_webinars' );

			if ( 200 !== $response['statusCode'] ) {
				throw new Exception( esc_html_x( 'Unable to fetch webinars from this account', 'GoToWebinar', 'uncanny-automator' ) );
			}

			// Handle embedded webinars structure.
			$jsondata = isset( $response['data']['_embedded']['webinars'] ) ? $response['data']['_embedded']['webinars'] : array();

			if ( count( $jsondata ) < 1 ) {
				throw new Exception( esc_html_x( 'No webinars were found in this account', 'GoToWebinar', 'uncanny-automator' ) );
			}

			$webinars = array();

			foreach ( $jsondata as $webinar ) {
				$webinars[] = array(
					'text'  => $webinar['subject'],
					'value' => (string) $webinar['webinarKey'] . '-objectkey',
				);
			}

			return $this->remote_data_success( $webinars );

		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get webinar key from parsed action data.
	 *
	 * @param array  $parsed   The parsed action data.
	 * @param string $meta_key The meta key for the webinar field.
	 *
	 * @return string The webinar key.
	 * @throws Exception If the webinar is not set or invalid.
	 */
	public function get_webinar_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Webinar is required.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		// Remove the -objectkey suffix and sanitize.
		$webinar_key = str_replace( '-objectkey', '', sanitize_text_field( $parsed[ $meta_key ] ) );

		if ( empty( $webinar_key ) ) {
			throw new Exception( esc_html_x( 'Invalid webinar key.', 'GoToWebinar', 'uncanny-automator' ) );
		}

		return $webinar_key;
	}
}
