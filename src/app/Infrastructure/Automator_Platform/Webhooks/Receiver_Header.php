<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Webhooks;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Marks every answer of the platform callback receiver with
 * `X-Automator-Receiver: <plugin version>` (callback delivery contract §4).
 *
 * The header means "this answer is the plugin receiver's decision". The
 * platform reads an answer without it as something in front of the plugin —
 * a firewall, a cache, the web server, WordPress with the plugin inactive —
 * and never as the plugin's refusal. So it goes on every answer the receiver
 * produces, and on nothing else.
 *
 * WHY `rest_request_after_callbacks`: it is the one WordPress seam that sees
 * every answer of a matched route — the route callback's response, and any
 * `WP_Error` from before it (a parameter check, another plugin's
 * `rest_request_before_callbacks`). It runs inside the REST server's
 * dispatch, for a served request and an internal one alike. Every answer the
 * receiver produces is the route callback's `WP_REST_Response`: the 200 and
 * the 422 / 5xx ({@see \Uncanny_Automator\App\Infrastructure\Webhooks\Webhook_Router::handle()}),
 * and the 401, which the receiver answers itself instead of refusing in the
 * permission check ({@see \Uncanny_Automator\App\Infrastructure\Webhooks\Answers_Own_Refusal}).
 * A `WP_Error` at this seam is therefore never the receiver's — WordPress
 * refusing a body that is not JSON before the receiver sees it, say — and is
 * left without the header, as the contract requires. A request that never
 * reaches a route (`rest_authentication_errors`, `rest_pre_dispatch`, no
 * route at all) does not pass this seam.
 *
 * Matched on the route the way WordPress matched it: the receiver's route
 * ({@see Integrations_Webhook_Controller::route()}) as a case-insensitive
 * pattern over the request's route. A third-party vendor's webhook route —
 * through the same router or not — never carries the header.
 *
 * Registered once however often the bootstrap runs: the callback is static.
 *
 * @since 8.0.0
 */
final class Receiver_Header {

	/**
	 * The header's name.
	 */
	public const NAME = 'X-Automator-Receiver';

	/**
	 * @return void
	 */
	public function register_hooks(): void {

		add_filter( 'rest_request_after_callbacks', array( self::class, 'stamp' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Filter callback: add the header to an answer of the receiver's route.
	 *
	 * @param mixed $response The route's answer: a WP_REST_Response, a WP_Error, or whatever a callback returned.
	 * @param mixed $handler  The matched route handler.
	 * @param mixed $request  The request.
	 *
	 * @return mixed The response, with the header when it is the receiver's.
	 */
	public static function stamp( $response, $handler, $request ) {

		if ( ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request || ! self::is_receiver_route( $request ) ) {
			return $response;
		}

		$response->header( self::NAME, AUTOMATOR_PLUGIN_VERSION );

		return $response;
	}

	/**
	 * Whether WordPress dispatched the request to the receiver's route: the
	 * route is a pattern WordPress matches case-insensitively against the
	 * whole request route.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	private static function is_receiver_route( WP_REST_Request $request ): bool {

		return 1 === preg_match( '@^' . Integrations_Webhook_Controller::route() . '$@i', (string) $request->get_route() );
	}
}
