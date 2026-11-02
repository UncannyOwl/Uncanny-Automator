<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Account;

use Uncanny_Automator\App\Plan\Services\Account\Account_Summary_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Admin_Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Account REST registration hub.
 *
 * Static-only. Provides the read-only `GET /uap/v2/account/get-summary`
 * endpoint the Dashboard header, Connect your AI block and Usage card read.
 * Single endpoint — no `Routes/` split.
 */
final class Account_Rest_Controller {

	use Restful_Admin_Permissions;
	use Rest_Responses;

	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	private const REST_BASE = 'account';

	/**
	 * Register the account routes. Called from `Restful_Bootstrap::register_rest_routes()`.
	 */
	public static function register_routes(): void {

		// GET /uap/v2/account/get-summary
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get-summary',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_summary' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * GET handler. The account summary, or the service's error with its status.
	 * A 503 carries `Retry-After`; the Dashboard retries.
	 */
	public static function get_summary( WP_REST_Request $request ): WP_REST_Response {
		$summary = ( new Account_Summary_Service() )->get_summary();

		return $summary instanceof WP_Error ? self::error_reply( $summary ) : self::success( array( 'account' => $summary ) );
	}
}
