<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Webhooks\Integrations_Webhook_Controller;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Rest_Bearer_Authenticator;

/**
 * Lets the routes Automator authenticates itself answer on a site where
 * BuddyBoss Platform's "Private REST APIs" setting is on.
 *
 * With that setting on, BuddyBoss answers every logged-out REST request with
 * `401 bb_rest_authorization_required` before the route runs
 * (`bb_restricate_rest_api()`, on `rest_request_before_callbacks` at 100).
 * These routes are called logged out by design, and each checks its own
 * requests:
 *
 *  - the App Platform's registration challenge
 *    ({@see Registration_Challenge_Controller}) signs only this install's own
 *    pending attempt, and answers `404 unknown_attempt` to anything else;
 *  - the App Platform's event receiver ({@see Integrations_Webhook_Controller})
 *    accepts only deliveries signed with this site's secret, and answers
 *    `401 SIGNATURE_REJECTED` to anything else;
 *  - Connect your AI's MCP and OAuth routes
 *    ({@see Rest_Bearer_Authenticator::owned_routes()}) check the bearer token
 *    or the OAuth request themselves, and keep the WordPress user logged out
 *    even for a valid token. Without the exemption no AI client can connect
 *    or call a tool.
 *
 * BuddyBoss lets a request through when its route is in the list
 * `bb_exclude_endpoints_from_restriction` returns, compared exactly with
 * {@see \WP_REST_Request::get_route()}. So the list gets the routes as their
 * controllers register them, and no other route. Without BuddyBoss the filter
 * never runs.
 *
 * Registered once however often the bootstrap runs: the callback is static.
 *
 * @since 8.0.4
 */
final class BuddyBoss_Private_Rest_Exemption {

	/**
	 * Last, so a callback that returns a list of its own instead of adding to
	 * the one it was given cannot drop these routes.
	 *
	 * @return void
	 */
	public function register_hooks(): void {

		add_filter( 'bb_exclude_endpoints_from_restriction', array( self::class, 'exempt' ), PHP_INT_MAX );
	}

	/**
	 * The routes called logged out that answer for themselves: the App
	 * Platform's two, then Connect your AI's MCP and OAuth routes.
	 *
	 * @since 8.0.4 Includes Connect your AI's MCP and OAuth routes.
	 *
	 * @return string[]
	 */
	public static function routes(): array {

		return array_merge(
			array(
				Registration_Challenge_Controller::route(),
				Integrations_Webhook_Controller::route(),
			),
			Rest_Bearer_Authenticator::owned_routes()
		);
	}

	/**
	 * Filter callback: add these routes to the routes BuddyBoss lets through,
	 * keeping every route already there.
	 *
	 * BuddyBoss passes an array. A callback before this one may have returned
	 * no list at all (a callback without a return gives null), or one route as
	 * a string; that route is kept.
	 *
	 * @param mixed $endpoints The routes let through so far: BuddyBoss's own and other plugins'.
	 *
	 * @return array The same routes, then each of these routes not already there.
	 */
	public static function exempt( $endpoints ): array {

		$list = is_array( $endpoints ) ? $endpoints : array_filter( array( $endpoints ), 'is_string' );

		foreach ( self::routes() as $route ) {
			if ( ! in_array( $route, $list, true ) ) {
				$list[] = $route;
			}
		}

		return $list;
	}
}
