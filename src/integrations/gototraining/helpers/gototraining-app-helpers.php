<?php

namespace Uncanny_Automator\Integrations\Gototraining;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Gototraining_App_Helpers
 *
 * @package Uncanny_Automator
 */
class Gototraining_App_Helpers extends App_Helpers {

	/**
	 * Option key for Client ID.
	 *
	 * @var string
	 */
	const CLIENT_ID_OPTION = 'uap_automator_gtt_api_consumer_key';

	/**
	 * Option key for Client Secret.
	 *
	 * @var string
	 */
	const CLIENT_SECRET_OPTION = 'uap_automator_gtt_api_consumer_secret';

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Set properties for the helper.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Preserve existing option names.
		$this->set_credentials_option_name( '_uncannyowl_gtt_settings' );
	}

	/**
	 * Prepare credentials for storage.
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

		return array(
			'access_token'  => $credentials['access_token'] ?? '',
			'refresh_token' => $credentials['refresh_token'] ?? '',
			'organizer_key' => $credentials['organizer_key'] ?? '',
			'expires_at'    => time() + intval( $expires_in ),
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
	 * Get training dropdown option configuration.
	 *
	 * @param string $option_code The option code for the dropdown.
	 *
	 * @return array
	 */
	public function get_training_options_config( $option_code ) {
		return array(
			'option_code'           => $option_code,
			'label'                 => esc_attr_x( 'Training', 'GoToTraining', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options'               => array(),
			'options_show_id'       => false,
			'supports_custom_value' => false,
			'remote_data'           => $this->remote_data_load_config( 'trainings' ),
		);
	}

	/**
	 * Fetch trainings for the dropdown.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_trainings( $request ): array {

		try {
			$response = $this->api->api_request( 'get_trainings' );

			if ( 200 !== $response['statusCode'] ) {
				throw new Exception( esc_html_x( 'Unable to fetch trainings from this account', 'GoToTraining', 'uncanny-automator' ) );
			}

			if ( count( $response['data'] ) < 1 ) {
				throw new Exception( esc_html_x( 'No trainings were found in this account', 'GoToTraining', 'uncanny-automator' ) );
			}

			$trainings = array();

			foreach ( $response['data'] as $training ) {
				$trainings[] = array(
					'text'  => $training['name'],
					'value' => (string) $training['trainingKey'] . '-objectkey',
				);
			}

			return $this->remote_data_success( $trainings );

		} catch ( Exception $e ) {
			return $this->remote_data_error( $e->getMessage() );
		}
	}

	/**
	 * Get training key from parsed action data.
	 *
	 * @param array  $parsed   The parsed action data.
	 * @param string $meta_key The meta key for the training field.
	 *
	 * @return string The training key.
	 * @throws Exception If the training is not set or invalid.
	 */
	public function get_training_from_parsed( $parsed, $meta_key ) {

		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Training is required.', 'GoToTraining', 'uncanny-automator' ) );
		}

		// Remove the -objectkey suffix and sanitize.
		$training_key = str_replace( '-objectkey', '', sanitize_text_field( $parsed[ $meta_key ] ) );

		if ( empty( $training_key ) ) {
			throw new Exception( esc_html_x( 'Invalid training key.', 'GoToTraining', 'uncanny-automator' ) );
		}

		return $training_key;
	}
}
