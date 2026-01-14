<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Utilities\Traits;

use WP_REST_Response;
use WP_Error;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Rest_Success;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Rest_Failure;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Interfaces\Rest_Response_Context;

/**
 * Trait for standardized REST API responses.
 *
 * Provides helper methods for creating consistent REST API responses.
 *
 * @since 7.0
 */
trait Rest_Responses {

	/**
	 * Create a successful REST response.
	 *
	 * @since 7.0
	 * @param array  $data        Response data.
	 * @param string $message     Success message.
	 * @param int    $status_code HTTP status code (default: 200).
	 * @return WP_REST_Response
	 */
	protected function success( array $data = array(), string $message = 'Success', int $status_code = 200 ): WP_REST_Response {
		$response = new Rest_Success( $data, $message, $status_code );
		return new WP_REST_Response( $response->to_array(), $response->get_status_code() );
	}

	/**
	 * Create a failed REST response.
	 *
	 * @since 7.0
	 * @param string $message     Error message.
	 * @param int    $status_code HTTP status code (default: 400).
	 * @param string $error_code  Error code identifier.
	 * @param array  $data        Additional error data.
	 * @return WP_Error
	 */
	protected function failure( string $message, int $status_code = 400, string $error_code = '', array $data = array() ): WP_Error {
		$response = new Rest_Failure( $message, $status_code, $error_code, $data );
		return new WP_Error(
			! empty( $error_code ) ? $error_code : 'rest_error',
			$response->get_message(),
			array_merge(
				array( 'status' => $response->get_status_code() ),
				$response->get_data()
			)
		);
	}

	/**
	 * Convert a Rest_Response_Context to WP_REST_Response or WP_Error.
	 *
	 * @since 7.0
	 * @param Rest_Response_Context $response Response context object.
	 * @return WP_REST_Response|WP_Error
	 */
	protected function to_rest_response( Rest_Response_Context $response ) {
		if ( $response->is_success() ) {
			return new WP_REST_Response( $response->to_array(), $response->get_status_code() );
		}

		$failure = $response instanceof Rest_Failure ? $response : new Rest_Failure( $response->get_message(), $response->get_status_code() );
		return new WP_Error(
			! empty( $failure->get_error_code() ) ? $failure->get_error_code() : 'rest_error',
			$failure->get_message(),
			array_merge(
				array( 'status' => $failure->get_status_code() ),
				$failure->get_data()
			)
		);
	}
}
