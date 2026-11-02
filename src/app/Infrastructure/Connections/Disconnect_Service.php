<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Exception;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Client_Interface;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Error_Exception;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;

/**
 * Coordinator for v3 connection disconnect — keeps local
 * `uap_connections` state in lockstep with the platform-authoritative
 * `DELETE /app/v1/connections/{id}`, which marks the platform's
 * connection disconnected (wire contract W5).
 *
 * Rule table:
 *
 *  | Platform response                         | Local row | Result     |
 *  |-------------------------------------------|-----------|------------|
 *  | 2xx                                       | deleted   | deleted    |
 *  | 404 / 410 (the connection is gone)        | deleted   | reconciled |
 *  | 401 / 408 / 425 / 429 / 5xx / transport   | preserved | retry      |
 *  | a license, site-signature, throttle or    | preserved | retry      |
 *  | credit answer, whatever its status        |           |            |
 *  | any other status (400, 403, 409, 422 …)   | preserved | failed     |
 *  | (no call: the row is switched off)        | deleted   | removed_locally |
 *
 * Only 2xx, 404 and 410 say anything about the connection. A 401 (the
 * license check, the site-signature check, or the platform's throttle,
 * which also answers 401), a 409, a 422 or a 429 says only that the
 * platform did not act — deleting the local row on one of those would leave
 * a live platform connection this site can no longer see or disconnect.
 * The same holds for a 404 or 410 whose `error.type` puts it in one of those
 * layers (`SITE_NOT_REGISTERED`, for one, can be a 404): no such answer says
 * anything about a connection (platform answers contract 4.8,
 * {@see Platform_Failure::says_nothing_about_a_connection()}).
 * Status is read whether {@see Client_Interface::send()} returned the
 * response or threw it, so an error answered without an error body is
 * classified the same way.
 *
 * A row switched off because this site is a copy holds the source site's
 * connection; removing it is local, with no call (staging-copies R7).
 *
 * On removal (deleted, reconciled or removed locally) the service fires
 * `automator_v3_connection_deleted` via Dispatcher::action carrying
 * `connection_id` + `integration` so recipe-builder account pickers
 * can refresh without reaching through the registry.
 *
 * @since 8.0.0
 */
final class Disconnect_Service {

	/**
	 * Statuses below 500 on which the platform did not act but a later
	 * attempt can succeed: an expired or throttled license / site-signature
	 * check (401 — the site re-registers on its own when the platform rejects
	 * its credentials), a timeout, too early, rate limited, or no HTTP status
	 * at all.
	 */
	private const RETRYABLE_STATUSES = array( 0, 401, 408, 425, 429 );

	/**
	 * @var Client_Interface
	 */
	private $client;

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @param Client_Interface    $client   Platform HTTP client.
	 * @param Connection_Registry $registry Local uap_connections store.
	 */
	public function __construct( Client_Interface $client, Connection_Registry $registry ) {

		$this->client   = $client;
		$this->registry = $registry;
	}

	/**
	 * Disconnect the v3 connection identified by `$connection_id`.
	 *
	 * Looks up the local row first — no row means nothing to call. The
	 * request carries no per-connection signature: like every platform-plane
	 * request it is signed with the per-site signing secret by
	 * `Platform_Request_Signature_Injector`. The platform marks its
	 * connection disconnected, ends the site's webhook subscription for it
	 * and keeps the record for its grace window (30 days); authorizing the
	 * same vendor account again within that window reactivates the same
	 * connection.
	 *
	 * @param string $connection_id The platform connection id.
	 *
	 * @return Disconnect_Result
	 */
	public function disconnect( string $connection_id ): Disconnect_Result {

		$connection = $this->registry->get_by_connection_id( $connection_id );
		if ( null === $connection ) {
			return Disconnect_Result::not_found_local( $connection_id );
		}

		// A row this copy inherited and switched off holds the SOURCE site's
		// connection, never this site's: the platform would refuse it, and the
		// id must not be sent anywhere. Removing it is a local delete; the
		// source site keeps its connection.
		if ( $connection->is_quarantined() ) {
			return $this->delete_local( $connection, Disconnect_Result::removed_locally() );
		}

		$request = new Request(
			ltrim( AUTOMATOR_PLATFORM_API_PATH, '/' ) . '/connections/' . rawurlencode( $connection_id ),
			array(),
			'DELETE',
			null,
			null,
			array(),
			$connection_id
		);

		$refusal = false;

		try {
			$http_status = $this->client->send( $request )->status_code();
			$message     = '';
		} catch ( Exception $e ) {
			// Client::send() throws an error envelope with its HTTP status as
			// the code, and a transport failure with 500.
			$http_status = (int) $e->getCode();
			$message     = $e->getMessage();
			$refusal     = $e instanceof Platform_Error_Exception && $e->failure()->says_nothing_about_a_connection();
		}

		return $this->settle( $connection, $http_status, $message, $refusal );
	}

	/**
	 * Apply the rule table to the platform's answer.
	 *
	 * @param Connection $connection  The local row.
	 * @param int        $http_status The platform's status, or 0.
	 * @param string     $message     The platform's description, if any.
	 * @param bool       $refusal     Whether the answer's type puts it in the license,
	 *                                site-signature, throttle or credit layer, which
	 *                                refused before looking at the connection.
	 *
	 * @return Disconnect_Result
	 */
	private function settle( Connection $connection, int $http_status, string $message, bool $refusal ): Disconnect_Result {

		if ( $http_status >= 200 && $http_status < 300 ) {
			return $this->delete_local( $connection, Disconnect_Result::deleted( $http_status ) );
		}

		if ( ! $refusal && ( 404 === $http_status || 410 === $http_status ) ) {
			return $this->delete_local( $connection, Disconnect_Result::reconciled( $http_status, $message ) );
		}

		if ( '' === $message ) {
			$message = sprintf( 'The platform did not disconnect the connection (HTTP %d).', $http_status );
		}

		return ( $refusal || $http_status >= 500 || in_array( $http_status, self::RETRYABLE_STATUSES, true ) )
			? Disconnect_Result::retry( $message, $http_status )
			: Disconnect_Result::failed( $message, $http_status );
	}

	/**
	 * Remove the local row and broadcast the event.
	 *
	 * @param Connection        $connection The local row.
	 * @param Disconnect_Result $result     The outcome being reported.
	 *
	 * @return Disconnect_Result
	 */
	private function delete_local( Connection $connection, Disconnect_Result $result ): Disconnect_Result {

		$this->registry->delete( $connection->id() );

		// Down to one connection, that one is the app's default (Saad, 29 Oct).
		$this->registry->default_sole_connections();

		/**
		 * Fires after a v3 connection has been deleted locally as the
		 * result of a platform `DELETE /app/v1/connections/{id}` — the
		 * platform disconnected it (2xx) or said it was gone (404 / 410).
		 *
		 * @param string $connection_id The platform connection ULID.
		 * @param string $integration   The integration slug.
		 * @param string $status        Disconnect_Result status constant.
		 */
		Dispatcher::action(
			'automator_v3_connection_deleted',
			$connection->connection_id(),
			$connection->integration(),
			$result->status()
		);

		return $result;
	}
}
