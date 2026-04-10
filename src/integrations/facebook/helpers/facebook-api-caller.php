<?php

namespace Uncanny_Automator\Integrations\Facebook;

use Uncanny_Automator\App_Integrations\Api_Caller;
use Exception;

/**
 * Class Facebook_Api_Caller
 *
 * @package Uncanny_Automator
 *
 * @property Facebook_App_Helpers $helpers
 */
class Facebook_Api_Caller extends Api_Caller {

	/**
	 * Prepare credentials for use in API requests.
	 *
	 * Delegates to Facebook_Bridge for credential validation and preparation.
	 * Page tokens are handled by the API proxy when page_id is included in the request body.
	 *
	 * @param array $credentials The credentials to prepare.
	 * @param array $args        Additional arguments that may be needed for preparation.
	 *
	 * @return string - Prepared credentials for API request.
	 * @throws Exception If credentials are invalid or empty.
	 */
	public function prepare_request_credentials( $credentials, $args ) {
		return Facebook_Bridge::get_instance()->prepare_vault_credentials( $credentials );
	}

	/**
	 * Check for errors in API response.
	 *
	 * @param array $response The response.
	 * @param array $args     Additional arguments.
	 *
	 * @return void
	 * @throws Exception If an error occurs
	 */
	public function check_for_errors( $response, $args = array() ) {
		$error = $response['data']['error']['message'] ?? false;
		if ( $error ) {
			throw new Exception( esc_html( $error ), absint( $response['statusCode'] ) );
		}
	}
}
