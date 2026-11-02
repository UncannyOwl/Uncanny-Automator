<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use Uncanny_Automator\Resend_Action_Service;
use WP_REST_Request;

/**
 * Action-log resend REST handler.
 *
 * `POST /uap/v2/action-log/{action_log_id}/resend` — replays the stored
 * API payload for one action_log row via `Resend_Action_Service` (shared
 * with the bulk "Resend App actions" tool). Never re-executes the action
 * or re-resolves tokens. Supersedes the legacy `resend_api_request`
 * endpoint (src/core), pending the FE repoint.
 *
 * Not recipe-scoped (the log id addresses the row directly), so no
 * `Recipe_Entity` trait — just a static `dispatch()` like `Run_Now_Rest`.
 * Thin per `src/app` Rule 11: all replay logic lives in the service.
 *
 * Response is HTTP 200 whether the replay succeeded, failed, or was
 * skipped — the body's `success` flag mirrors the service's `ok` and the
 * `outcome` string (`resent` | `failed` | `skipped`) carries the detail,
 * matching the service contract and the legacy delegator's semantics.
 */
final class Resend_Action_Rest {

	use Rest_Responses;

	/**
	 * REST callback. Referenced from `Recipe_Rest_Controller::register_routes()`
	 * as `[ Resend_Action_Rest::class, 'dispatch' ]`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function dispatch( WP_REST_Request $request ) {
		$action_log_id = absint( $request['action_log_id'] );

		if ( $action_log_id <= 0 ) {
			return self::failure(
				esc_html_x( 'Missing or invalid action_log_id.', 'Resend action REST error', 'uncanny-automator' ),
				400,
				'automator_invalid_action_log_id'
			);
		}

		$result = ( new Resend_Action_Service() )->resend_action( $action_log_id );

		return self::success(
			array(
				'success'    => $result['ok'],
				'outcome'    => $result['outcome'],
				'message'    => $result['message'],
				'api_log_id' => $result['api_log_id'],
			)
		);
	}
}
