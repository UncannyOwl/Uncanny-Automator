<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Ai;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Site_Client;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Failure;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Success;
use WP_Error;
use WP_REST_Response;

/**
 * Envelopes for the AI routes. Every reply carries `Cache-Control: no-store`
 * because AI activity can hold what an AI wrote about the site. A hosted
 * outage is a retryable 503 with `Retry-After`; the Dashboard retries.
 */
final class Ai_Response {

	private const STATUSES = array(
		'rest_invalid_param'            => 400,
		'rest_forbidden'                => 403,
		Hosted_Site_Client::UNAVAILABLE => 503,
	);

	/**
	 * Wrap a service result.
	 *
	 * @param array|WP_Error $result Service result.
	 */
	public static function from( $result ): WP_REST_Response {
		if ( ! $result instanceof WP_Error ) {
			$success = new Rest_Success( $result );

			return self::no_store( new WP_REST_Response( $success->to_array(), $success->get_status_code() ) );
		}

		$code     = (string) $result->get_error_code();
		$status   = self::STATUSES[ $code ] ?? 502;
		$failure  = new Rest_Failure( $result->get_error_message(), $status, $code );
		$response = self::no_store( new WP_REST_Response( $failure->to_array(), $status ) );

		$retry_after = (int) ( $result->get_error_data()['retry_after'] ?? 0 );
		if ( 503 === $status && $retry_after > 0 ) {
			$response->header( 'Retry-After', (string) $retry_after );
		}

		return $response;
	}

	/**
	 * Forbid any cache from keeping the reply.
	 */
	private static function no_store( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
