<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use InvalidArgumentException;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Http;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Heartbeat_Cron;

/**
 * Connection sync — staging-copies handoff R5.
 *
 * The `uap_connections` rows are a local record of what the platform holds,
 * and several ordinary events make that record wrong: a staging database
 * pushed back to live (or a backup restored) leaves the live site holding the
 * copy's rows, quarantined; a platform key rotation retires every stored
 * signature; a `401` used to mark a row `requires_reauth` that nothing set
 * back; and a row taken down here (`revoked`, `expired`) stays down after
 * another site signs in to the account again and the platform restores every
 * binding of it (L239). One rule repairs all of them: the platform's list of this site's
 * connections (`GET /app/v1/connections`) is the truth, and the rows can be
 * rebuilt from it at any time.
 *
 * How each row is brought in line with the list is {@see Connection_List_Applier}:
 * active listed connections rebind or create rows, nothing the platform does
 * not list as active changes (bar the row a refused call was made with: a
 * `CONNECTION_INACTIVE` the list confirms, or a connection the list no longer
 * holds), nothing is deleted and legacy (/v2) rows are never touched. This class owns when that
 * runs and what follows: it never sends a `connection_id` anywhere — it only
 * reads the list — and it registers the trigger subscriptions the rows are
 * owed (R6, {@see Trigger_Subscriptions}).
 *
 * Runs only while the site's registration is settled
 * ({@see Platform_Site_Registration::is_settled()}): before that the secret on
 * file is not this site's, and a signed read would be refused or answered for
 * another site. When it runs:
 *
 *  - after a registration that completed for an address the platform knew (a
 *    recovery or a move): owed, then run by whichever comes first — its cron
 *    event, the next wp-admin page load, or the heartbeat — or, for a copy,
 *    as soon as the copy lifecycle finishes, so the rows it quarantines are
 *    repaired after the quarantine and not before;
 *  - after an adoption, and when the admin presses "Repair connections";
 *  - with the heartbeat;
 *  - when `automator_platform_connection_out_of_sync` fires with the stored
 *    row the platform answered CONNECTION_NOT_FOUND,
 *    CONNECTION_SIGNATURE_INVALID or CONNECTION_INACTIVE about: at once, that
 *    row first, so the caller can make its call again; a burst for one
 *    connection reads the list once a minute and never holds back another
 *    connection's repair.
 *
 * A sync that did not finish — the list did not arrive, a row could not be
 * written, a trigger subscription is still owed — changes nothing it could
 * not finish and stays owed ({@see Owed_Work}).
 *
 * @since 8.0.0
 */
final class Connection_Sync {

	/**
	 * WP-Cron hook that runs an owed sync.
	 */
	public const CRON_HOOK = 'automator_platform_connection_sync';

	/**
	 * Seconds during which a repeated out-of-sync answer for the same
	 * connection does not read the list again.
	 */
	private const REPAIR_PAUSE_SECONDS = MINUTE_IN_SECONDS;

	/**
	 * @var Platform_Connections_Api
	 */
	private $api;

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @var Platform_Site_Registration
	 */
	private $registration;

	/**
	 * @var Trigger_Subscriptions
	 */
	private $triggers;

	/**
	 * @var Owed_Work
	 */
	private $owed;

	/**
	 * @var Connection_List_Applier
	 */
	private $applier;

	/**
	 * @param Platform_Connections_Api     $api          The platform's connection routes.
	 * @param Connection_Registry          $registry     Local uap_connections store.
	 * @param Platform_Site_Registration   $registration Says whether the registration is settled.
	 * @param Trigger_Subscriptions|null   $triggers     Restores owed trigger subscriptions.
	 * @param Owed_Work|null               $owed         Keeps an unfinished sync owed.
	 * @param Connection_List_Applier|null $applier      Brings the rows in line with the list.
	 */
	public function __construct(
		Platform_Connections_Api $api,
		Connection_Registry $registry,
		Platform_Site_Registration $registration,
		?Trigger_Subscriptions $triggers = null,
		?Owed_Work $owed = null,
		?Connection_List_Applier $applier = null
	) {
		$this->api          = $api;
		$this->registry     = $registry;
		$this->registration = $registration;
		$this->triggers     = $triggers ?? new Trigger_Subscriptions( $registry );
		$this->owed         = $owed ?? new Owed_Work( 'connection_sync', self::CRON_HOOK );
		$this->applier      = $applier ?? new Connection_List_Applier( $registry );
	}

	/**
	 * Attach the sync to every moment it runs.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action( 'automator_platform_site_recovered', array( $this, 'request' ) );
		add_action( 'automator_platform_site_migrated', array( $this, 'request' ) );
		// L247 — a local site registered again without its old secret: the
		// platform disconnected its connections; read which, at once.
		add_action( 'automator_platform_site_restarted', array( $this, 'request' ) );
		add_action( 'automator_platform_site_copy_completed', array( $this, 'run_owed' ), 20 );
		add_action( Site_Heartbeat_Cron::CRON_HOOK, array( $this, 'run_with_heartbeat' ), 20 );
		add_action( 'automator_platform_connection_out_of_sync', array( $this, 'on_out_of_sync' ), 10, 2 );
		add_action( self::CRON_HOOK, array( $this, 'run_owed' ) );
		add_action( 'admin_init', array( $this, 'run_owed_on_admin_load' ) );
	}

	/**
	 * Owe a sync: it runs on its own cron event, now.
	 *
	 * @return void
	 */
	public function request(): void {

		$this->owed->owe();
	}

	/**
	 * Whether a sync is owed: some row may disagree with the platform's list.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_owed(): bool {

		return $this->owed->is_owed();
	}

	/**
	 * Whether a sync is owed and retrying on its own has not done it within
	 * the ladder's quick rungs ({@see Owed_Work::is_overdue()}) — when the
	 * admin is offered to re-check now (handoff R1, L210), instead of being
	 * told to reconnect an app.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_stuck(): bool {

		return $this->owed->is_overdue();
	}

	/**
	 * Run the sync if one is owed.
	 *
	 * @return void
	 */
	public function run_owed(): void {

		if ( $this->owed->is_owed() ) {
			$this->sync();
		}
	}

	/**
	 * Run an owed sync on a wp-admin page load, when its attempt is due — so a
	 * sync a recovery or a move queued never waits for WP-Cron alone, which a
	 * site may have disabled or rarely spawns. A failed attempt waits its rung
	 * of the ladder ({@see Owed_Work::is_due()}), so page loads never turn a
	 * retry into one per request.
	 *
	 * admin-ajax.php is excluded: front-end visitors hit it constantly, and a
	 * visitor's request must never pay for a platform round trip. So is a site
	 * whose platform plane is switched off.
	 *
	 * @return void
	 */
	public function run_owed_on_admin_load(): void {

		if ( wp_doing_ajax() || ! Platform_Http::platform_enabled() || ! $this->owed->is_due() ) {
			return;
		}

		$this->sync();
	}

	/**
	 * The heartbeat's sync: a full sync, which also settles one that is owed. A
	 * site whose registration is not settled has nothing to sync yet, and owes
	 * nothing for it.
	 *
	 * @return void
	 */
	public function run_with_heartbeat(): void {

		if ( $this->registration->is_settled() ) {
			$this->sync();
		}
	}

	/**
	 * Listener for `automator_platform_connection_out_of_sync`.
	 *
	 * The platform-answers handling fires the notice with the stored row the
	 * platform disagreed about — a {@see Connection} this site holds — and
	 * the answer's `error.type` (`Connection_Error_Handler`). A notice that
	 * names no stored row is not one: it repairs nothing and starts no pause,
	 * so it can never hold back the repair of a connection that needs it.
	 *
	 * @param mixed $connection The stored row the platform answered about.
	 * @param mixed $type       The answer's error.type.
	 *
	 * @return void
	 */
	public function on_out_of_sync( $connection, $type = '' ): void {

		if ( ! $connection instanceof Connection || '' === $connection->connection_id() || ! is_string( $type ) ) {
			automator_log( 'A connection out-of-sync notice that names no stored connection was ignored.', 'Connection_Sync', false );
			return;
		}

		$this->repair( $connection->connection_id(), $type );
	}

	/**
	 * Make every row agree with the platform's list.
	 *
	 * @return Connection_Sync_Result
	 */
	public function sync(): Connection_Sync_Result {

		return $this->reconcile( null, '' );
	}

	/**
	 * Repair one connection the platform just disagreed about — that row
	 * first — then every other row from the same list. Repeated answers about
	 * the same connection read the list once a minute; the pause is that
	 * connection's own, so it never holds back the repair of another.
	 *
	 * @param string $connection_id The connection the platform answered about.
	 * @param string $type          The answer's error.type.
	 *
	 * @return Connection_Sync_Result
	 *
	 * @throws InvalidArgumentException When no connection is named.
	 */
	public function repair( string $connection_id, string $type ): Connection_Sync_Result {

		if ( '' === $connection_id ) {
			throw new InvalidArgumentException( 'A repair names the connection it repairs.' );
		}

		$pause = 'automator_platform_connection_repair_' . md5( $connection_id );
		if ( false !== get_transient( $pause ) ) {
			return Connection_Sync_Result::deferred();
		}
		set_transient( $pause, 1, self::REPAIR_PAUSE_SECONDS );

		$first = $this->registry->get_by_connection_id( $connection_id );

		return $this->reconcile( $first, $type );
	}

	/**
	 * Read the list, apply it, register the trigger subscriptions owed, and
	 * keep the sync owed unless all of it finished.
	 *
	 * @param Connection|null $first A row to repair before the others.
	 * @param string          $type  The answer that asked for the repair, '' for a full sync.
	 *
	 * @return Connection_Sync_Result
	 */
	private function reconcile( ?Connection $first, string $type ): Connection_Sync_Result {

		if ( ! $this->registration->is_settled() ) {
			$this->owed->failed();
			return Connection_Sync_Result::deferred();
		}

		$list = $this->api->list_connections();
		if ( ! $list->is_ok() ) {
			$this->owed->failed();
			automator_log( 'Connection sync could not read this site\'s connections from the platform (status ' . $list->http_status() . '); nothing was changed and it will be retried.', 'Connection_Sync', false );
			return Connection_Sync_Result::failed( $list->http_status() );
		}

		$result = $this->applier->apply( $list->ordered_for( $first ), $first, $type );

		if ( ! $this->restore_triggers() ) {
			$result = $result->as_incomplete();
		}

		if ( $result->is_synced() ) {
			$this->owed->settle();
		} else {
			$this->owed->failed();
		}

		return $result;
	}

	/**
	 * @return bool Whether no trigger subscription is still owed.
	 */
	private function restore_triggers(): bool {

		try {
			return 0 === $this->triggers->restore_owed();
		} catch ( Registry_Read_Failed_Exception $e ) {
			automator_log( 'Connection sync could not read the connections owed a trigger subscription: ' . $e->getMessage(), 'Connection_Sync', false );
			return false;
		}
	}
}
