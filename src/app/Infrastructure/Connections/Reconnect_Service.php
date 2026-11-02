<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Coordinator for the "user clicks Reconnect" flow per MVP plan G.6.
 *
 * Reconnect is operationally identical to disconnect-then-authorize: the
 * plugin disconnects the integration's default v3 connection on the
 * platform (`DELETE /app/v1/connections/{id}`, which marks it
 * disconnected), removes the local row once the platform confirms
 * (`Connection_Registry::delete()`, via {@see Disconnect_Service}), then
 * surfaces a fresh OAuth start URL to the browser. Authorizing the same
 * vendor account again reactivates the platform's existing connection and
 * the OAuth callback returns the SAME `connection_id`; a different account
 * gets a fresh one. The same `connection_id` holds because a reconnect is an
 * immediate disconnect-then-connect: the authorization follows the
 * disconnect within one OAuth round trip, inside the grace window the
 * platform keeps a disconnected connection for
 * ({@see Disconnect_Service::disconnect()}). The plugin doesn't need to know
 * which case it's in — recipe steps reference the integration's default
 * connection (`is_default = 1`), not a pinned `connection_id`, so they swap
 * transparently when the new row lands.
 *
 * The disconnect half must succeed before the authorize half starts. When
 * the platform did not disconnect (the result reports a failure and the
 * local row is kept), the old connection is still live there; authorizing a
 * different account would leave it live beside the new one, invisible to
 * this site. The caller stops on a failed result.
 *
 * This class owns the disconnect half only; the OAuth-start URL is
 * built by the existing `OAuth_App_Integration::handle_oauth_init()`
 * trait, so the rest-processing wiring composes the two halves rather
 * than duplicating OAuth-arg construction here.
 *
 * Multi-connection caveat: when recipe steps eventually gain
 * per-step `connection_id` pinning, a step pinned to a deleted
 * `connection_id` would dangle. Out of MVP scope; flag for revisit.
 *
 * @since 8.0.0
 */
final class Reconnect_Service {

	/**
	 * @var Disconnect_Service
	 */
	private $disconnect;

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @param Disconnect_Service  $disconnect Owns the platform DELETE + local row removal.
	 * @param Connection_Registry $registry   Local uap_connections store.
	 */
	public function __construct( Disconnect_Service $disconnect, Connection_Registry $registry ) {

		$this->disconnect = $disconnect;
		$this->registry   = $registry;
	}

	/**
	 * Disconnect the integration's default v3 connection in preparation
	 * for a fresh OAuth flow.
	 *
	 *  - No active registry row OR row is pre-lift legacy → returns null;
	 *    nothing to disconnect, OAuth start runs cleanly.
	 *  - Platform 2xx / 404 / 410 → registry row removed; the returned
	 *    {@see Disconnect_Result} reports deleted or reconciled.
	 *  - Anything else → registry row preserved; the result reports a
	 *    failure (`is_failure()`), and whether a retry can help
	 *    (`should_retry()`). The caller must not proceed to OAuth start.
	 *
	 * @param string $integration Integration slug (e.g. 'dropbox').
	 *
	 * @return Disconnect_Result|null Null when no v3 row existed.
	 */
	public function reconnect( string $integration ): ?Disconnect_Result {

		$default = $this->registry->get_default( $integration );
		if ( null === $default || $default->is_legacy() ) {
			return null;
		}

		return $this->disconnect->disconnect( $default->connection_id() );
	}
}
