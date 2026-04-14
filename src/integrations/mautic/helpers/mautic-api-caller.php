<?php

namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\App_Integrations\Api_Caller;

/**
 * Handles all outbound API requests to the Mautic proxy endpoint on the
 * Automator API server, including credential preparation and error handling.
 *
 * @package Uncanny_Automator\Integrations\Mautic
 *
 * @property Mautic_App_Helpers $helpers
 */
class Mautic_Api_Caller extends Api_Caller {

	/**
	 * Inspect an API response for errors and throw an exception if found.
	 *
	 * Handles two Mautic-specific error formats: an 'errors' array in the
	 * response data, and a 'message' string. Falls back to the parent
	 * implementation for any unrecognized error shapes.
	 *
	 * @param array $response The full API response including statusCode and data.
	 * @param array $args     Additional arguments passed from the api_request call.
	 *
	 * @throws \Exception With the error message and HTTP status code when the response indicates failure.
	 *
	 * @return void
	 */
	public function check_for_errors( $response, $args = array() ) {

		$status_code = absint( $response['statusCode'] ?? 200 );
		if ( 200 === $status_code ) {
			return;
		}

		// Check for errors array format — extract the message from the first entry.
		if ( isset( $response['data']['errors'] ) ) {
			$errors = (array) $response['data']['errors'];
			$first  = reset( $errors );
			$error  = is_array( $first ) && ! empty( $first['message'] )
				? $first['message']
				: 'API has returned an error with unknown format';
			throw new \Exception( esc_html( $error ), absint( $status_code ) );
		}

		// Check for message format.
		if ( isset( $response['data']['message'] ) ) {
			throw new \Exception( esc_html( $response['data']['message'] ), absint( $status_code ) );
		}

		// Fallback to parent handling.
		parent::check_for_errors( $response, $args );
	}
}
