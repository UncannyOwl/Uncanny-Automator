<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Request;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Response;

/**
 * What a platform answer does to the stored connection it was made with —
 * decided by the answer's `error.type`, never by its status alone and never by
 * its description (platform answers contract 4.3 and 4.8).
 *
 *  | Class ({@see Platform_Failure})  | error.type                         | Stored row                     |
 *  |----------------------------------|------------------------------------|--------------------------------|
 *  | identity_revoked                 | IDENTITY_REVOKED                   | marked revoked                 |
 *  | scope_insufficient               | SCOPE_INSUFFICIENT                 | needs re-authorization (scopes |
 *  |                                  |                                    | merged into metadata)          |
 *  | connection_out_of_sync           | CONNECTION_NOT_FOUND,              | untouched; one                 |
 *  |                                  | CONNECTION_SIGNATURE_INVALID,      | `automator_platform_connection |
 *  |                                  | CONNECTION_INACTIVE                | _out_of_sync` action names it  |
 *  | anything else                    |                                    | untouched                      |
 *
 * `IDENTITY_REVOKED` is the one answer that means the vendor account itself is
 * no longer authorized, so it is the one that marks the row. The three
 * `CONNECTION_*` answers mean "the stored row and the platform disagree": the
 * platform's list of this site's connections decides, so the row is left as it
 * is and the action asks connection sync (built separately) to repair it. The
 * hook fires inside Client::send(), before the answer reaches the caller: when
 * sync repaired the row on the spot, the shared /v3 caller
 * ({@see \Uncanny_Automator\App_Integrations\Automator_Platform\Caller}) sends the request
 * once more with the repaired envelope and takes that answer. No
 * answer from the license layer, the site signature or the credit check says
 * anything about a connection; they change nothing here. A platform that still
 * answers the old way — every refusal a `401 UNAUTHENTICATED` — therefore
 * changes nothing either.
 *
 * Token refresh happens inside the platform; the plugin never refreshes.
 *
 * Runtime activation — passive observer: registered via {@see register()} on
 * the `automator_platform_response` action that `Client::send()` fires once per
 * answer. A request that names no connection (`/v2`, `/v3/sites/register`) is
 * ignored.
 *
 * @since 8.0.0
 */
final class Connection_Error_Handler {

	/**
	 * Fires once per platform answer that says the stored row and the
	 * platform disagree about a connection (contract 4.3). The row is not
	 * changed; a listener — connection sync — asks the platform's list and
	 * repairs or retires it.
	 *
	 * Arguments:
	 *  1. Connection $connection The local row the request was made with, as
	 *                            it is stored (status, signature and metadata
	 *                            unchanged by the answer).
	 *  2. string     $type       The answer's `error.type`:
	 *                            `CONNECTION_NOT_FOUND`,
	 *                            `CONNECTION_SIGNATURE_INVALID` or
	 *                            `CONNECTION_INACTIVE`.
	 *
	 * Not fired when the site holds no row for the request's `connection_id`:
	 * there is nothing local to name.
	 *
	 * @since 8.0.0
	 */
	public const OUT_OF_SYNC_HOOK = 'automator_platform_connection_out_of_sync';

	public const EFFECT_NONE            = 'none';
	public const EFFECT_REQUIRES_REAUTH = 'requires_reauth';
	public const EFFECT_REVOKED         = 'revoked';
	public const EFFECT_OUT_OF_SYNC     = 'out_of_sync';

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @param Connection_Registry|null $registry Local uap_connections store; defaults to a fresh instance.
	 */
	public function __construct( ?Connection_Registry $registry = null ) {

		$this->registry = $registry ?? new Connection_Registry();
	}

	/**
	 * Attach the observer to `automator_platform_response`. Called once
	 * from `Application_Bootstrap`. Idempotent — WP's `add_action` with
	 * the same callback + priority is silently deduplicated.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action( 'automator_platform_response', array( $this, 'observe' ), 10, 2 );
	}

	/**
	 * Observer callback for `automator_platform_response`. Extracts
	 * connection_id from the outbound Request and routes through
	 * `handle()`; no-ops when the Request isn't connection-scoped.
	 *
	 * `Client::send()` fires the action with `(Response, Request)`, but
	 * third-party code can emit the action with anything — BackCompat's
	 * legacy-hook forwarder test fires it with `(null, null)` to prove
	 * the bridge. We therefore accept mixed and type-check defensively
	 * rather than strict-typing the signature.
	 *
	 * @param mixed $response Expected Response instance.
	 * @param mixed $request  Expected Request instance.
	 *
	 * @return void
	 */
	public function observe( $response = null, $request = null ): void {

		if ( ! $response instanceof Response || ! $request instanceof Request ) {
			return;
		}

		$connection_id = $request->connection_id();

		if ( null === $connection_id || '' === $connection_id ) {
			return;
		}

		$this->handle( $response, $connection_id );
	}

	/**
	 * Apply what `$response` does to the stored connection `$connection_id`.
	 *
	 * @param Response $response      The platform's answer.
	 * @param string   $connection_id The connection the request was made with.
	 *
	 * @return string One of the EFFECT_* constants: what was done.
	 */
	public function handle( Response $response, string $connection_id ): string {

		$failure = Platform_Failure::from_response( $response );
		if ( null === $failure ) {
			return self::EFFECT_NONE;
		}

		switch ( $failure->kind() ) {
			case Platform_Failure::KIND_IDENTITY_REVOKED:
				return $this->revoke( $connection_id );
			case Platform_Failure::KIND_SCOPE_INSUFFICIENT:
				return $this->require_reauth( $response, $connection_id );
			case Platform_Failure::KIND_CONNECTION_OUT_OF_SYNC:
				return $this->name_out_of_sync( $failure, $connection_id );
			default:
				return self::EFFECT_NONE;
		}
	}

	/**
	 * IDENTITY_REVOKED: the vendor account is no longer authorized.
	 *
	 * @param string $connection_id The connection.
	 *
	 * @return string
	 */
	private function revoke( string $connection_id ): string {

		$connection = $this->registry->get_by_connection_id( $connection_id );
		if ( null === $connection ) {
			return self::EFFECT_NONE;
		}

		$this->registry->mark_revoked( $connection->id() );

		return self::EFFECT_REVOKED;
	}

	/**
	 * SCOPE_INSUFFICIENT: the account must grant more; keep the scopes the
	 * answer names so the settings page can show the difference.
	 *
	 * @param Response $response      The answer, carrying the scope arrays.
	 * @param string   $connection_id The connection.
	 *
	 * @return string
	 */
	private function require_reauth( Response $response, string $connection_id ): string {

		$connection = $this->registry->get_by_connection_id( $connection_id );
		if ( null === $connection ) {
			return self::EFFECT_NONE;
		}

		$error = (array) $response->error();

		$this->registry->mark_requires_reauth(
			$connection->id(),
			self::strings( $error['required_scopes'] ?? array() ),
			self::strings( $error['granted_scopes'] ?? array() )
		);

		return self::EFFECT_REQUIRES_REAUTH;
	}

	/**
	 * CONNECTION_*: leave the row as it is and name it, once, for connection sync.
	 *
	 * @param Platform_Failure $failure       The failure.
	 * @param string           $connection_id The connection.
	 *
	 * @return string
	 */
	private function name_out_of_sync( Platform_Failure $failure, string $connection_id ): string {

		$connection = $this->registry->get_by_connection_id( $connection_id );
		if ( null === $connection ) {
			return self::EFFECT_NONE;
		}

		Dispatcher::action( self::OUT_OF_SYNC_HOOK, $connection, $failure->type() );

		return self::EFFECT_OUT_OF_SYNC;
	}

	/**
	 * A list of strings from an answer's array field (anything else: none).
	 *
	 * @param mixed $values The field.
	 *
	 * @return array<int, string>
	 */
	private static function strings( $values ): array {

		return is_array( $values ) ? array_values( array_map( 'strval', $values ) ) : array();
	}
}
