<?php

namespace Uncanny_Automator\Integrations\Keap;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Keap_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Keap_App_Helpers $helpers
 */
class Keap_Api_Caller extends Api_Caller {

	////////////////////////////////////////////////////////////
	// Abstract override methods
	////////////////////////////////////////////////////////////

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * @param array $credentials The raw credentials from options to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string The prepared credentials JSON string.
	 * @throws Action_Error_Exception If credentials are missing keap_id or vault_signature: the reconnect message, with the settings link.
	 */
	public function prepare_request_credentials( $credentials, $args ) {

		// Raw tokens stored before the vault (Free < 7.2.3) are never sent: the site owner reconnects.
		if ( empty( $credentials['keap_id'] ) || empty( $credentials['vault_signature'] ) ) {
			throw Action_Error_Exception::app_not_connected( esc_html( $this->format_error_message( $this->error_messages['invalid credentials'] ) ) );
		}

		return wp_json_encode(
			array(
				'vault_signature' => $credentials['vault_signature'],
				'keap_id'         => $credentials['keap_id'],
			)
		);
	}

	/**
	 * Check for errors in API response.
	 *
	 * @param array $response
	 * @param array $args
	 * @return void
	 *
	 * @throws Exception
	 */
	public function check_for_errors( $response, $args = array() ) {

		// Parent handles credentials and 4xx (with the shared message extraction).
		parent::check_for_errors( $response, $args );

		// Catch any remaining non-success status (e.g. 5xx) the parent skips.
		$valid_codes = array( 200, 201, 204 );
		if ( in_array( $response['statusCode'] ?? 0, $valid_codes, true ) ) {
			return;
		}

		$message = $this->extract_error_message( $response );

		if ( '' === $message ) {
			$message = sprintf(
				// translators: %s Status code.
				esc_html_x( 'Keap API Error: request failed with status code: %s', 'Keap', 'uncanny-automator' ),
				absint( $response['statusCode'] ?? 0 )
			);
		}

		throw new Exception( esc_html( $message ), absint( $response['statusCode'] ?? 0 ) );
	}
}
