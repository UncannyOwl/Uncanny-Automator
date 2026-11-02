<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Utilities\Traits;

use WP_REST_Response;
use WP_Error;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Success;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Failure;

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
	 * Static so static-only route classes (`Get_Recipe_Route`, etc.) can
	 * call it via `self::success(...)` after `use Rest_Responses;`.
	 * Instance-style callers (`$this->success(...)`) continue to work via
	 * PHP's static-via-instance dispatch.
	 *
	 * @param array  $data        Response data.
	 * @param string $message     Success message.
	 * @param int    $status_code HTTP status code (default: 200).
	 * @return WP_REST_Response
	 */
	protected static function success( array $data = array(), string $message = 'Success', int $status_code = 200 ): WP_REST_Response {
		$response = new Rest_Success( $data, $message, $status_code );
		return new WP_REST_Response( $response->to_array(), $response->get_status_code() );
	}

	/**
	 * Create a validation-rejection REST response (HTTP 422).
	 *
	 * Must be a WP_REST_Response, never a WP_Error — WP core serializes
	 * WP_Error into `{ code, message, data: { status } }`, which can never
	 * satisfy the FE's strict `{ success, message, data: { violations,
	 * recipe } }` envelope. `data` carries EXACTLY violations + recipe;
	 * any additive key breaks the FE's strict parse.
	 *
	 * @param array  $violations Wire-shape violation list (non-empty).
	 * @param array  $recipe     Full wire recipe, status unchanged.
	 * @param string $message    Localized top-level summary.
	 * @return WP_REST_Response
	 */
	protected static function rejection( array $violations, array $recipe, string $message ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
				'data'    => array(
					'violations' => $violations,
					'recipe'     => $recipe,
				),
			),
			422
		);
	}

	/**
	 * A service's WP_Error as a failure reply in the envelope: the error's
	 * `status` (500 when absent), plus a `Retry-After` header when it carries
	 * `retry_after`. A WP_Error returned to WordPress cannot carry a header.
	 *
	 * @param WP_Error $error Service error.
	 * @return WP_REST_Response
	 */
	protected static function error_reply( WP_Error $error ): WP_REST_Response {
		$data     = (array) $error->get_error_data();
		$status   = (int) ( $data['status'] ?? 500 );
		$failure  = new Rest_Failure( $error->get_error_message(), $status, (string) $error->get_error_code() );
		$response = new WP_REST_Response( $failure->to_array(), $status );

		if ( ! empty( $data['retry_after'] ) ) {
			$response->header( 'Retry-After', (string) $data['retry_after'] );
		}

		return $response;
	}

	/**
	 * Create a failed REST response.
	 *
	 * @param string $message     Error message.
	 * @param int    $status_code HTTP status code (default: 400).
	 * @param string $error_code  Error code identifier.
	 * @param array  $data        Additional error data.
	 * @return WP_Error
	 */
	protected static function failure( string $message, int $status_code = 400, string $error_code = '', array $data = array() ): WP_Error {
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
}
