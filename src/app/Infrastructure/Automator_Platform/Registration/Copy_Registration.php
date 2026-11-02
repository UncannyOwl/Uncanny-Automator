<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Connections\Copy_Quarantine_State;
use Uncanny_Automator\App\Infrastructure\Connections\Quarantine_Failed_Exception;
use Uncanny_Automator\App\Infrastructure\Connections\Site_Copy_Record;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Challenge;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;

/**
 * Registers this install as a fresh copy of another site, behind a fail-closed block: the
 * block is engaged before the registration, the inherited connections are quarantined
 * only after it succeeds, and a failure leaves it engaged.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Copy_Registration {

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @var Registration_Challenge
	 */
	private $challenge;

	/**
	 * What this site is when it is a copy: the admin's answer and the source
	 * site's address.
	 *
	 * @var Site_Copy_Record
	 */
	private $copy_record;

	/**
	 * @var Register_Dispatch
	 */
	private $register;

	/**
	 * @var Site_Address_Drift
	 */
	private $address;

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @var Registration_Backoff
	 */
	private $backoff;

	/**
	 * @param Site_Credentials_Store $store       Holds the secret the copy inherited, then its own.
	 * @param Registration_Challenge $challenge   The attempt on file.
	 * @param Site_Copy_Record       $copy_record The admin's answer and the source site's address.
	 * @param Register_Dispatch      $register    Sends the registration.
	 * @param Site_Address_Drift     $address     Says whether this request is on the registered address.
	 * @param Registration_Wire      $wire        Says whether a license is active.
	 * @param Registration_Backoff   $backoff     The pause between attempts.
	 */
	public function __construct(
		Site_Credentials_Store $store,
		Registration_Challenge $challenge,
		Site_Copy_Record $copy_record,
		Register_Dispatch $register,
		Site_Address_Drift $address,
		Registration_Wire $wire,
		Registration_Backoff $backoff
	) {

		$this->store       = $store;
		$this->challenge   = $challenge;
		$this->copy_record = $copy_record;
		$this->register    = $register;
		$this->address     = $address;
		$this->wire        = $wire;
		$this->backoff     = $backoff;
	}

	/**
	 * Keep driving a copy whose registration has not succeeded yet (the block is
	 * engaged in the prepared or failed state).
	 *
	 * The decision "this is a copy" is already durable, so the production drift
	 * gate must NOT get a second say: deferring to the admin notice again would
	 * leave every connection blocked, forever. Retry behind the same license +
	 * backoff gates force_register() applies (checked here so a backed-off tick
	 * costs option reads, not the writes register_as_copy() makes before it
	 * reaches them).
	 *
	 * The one way out that is not a registration: this request is on the address
	 * the stored credentials were issued for. The copy decision was then taken by
	 * a request that saw another home URL (WP-Cron on an internal host, WP-CLI, a
	 * proxy that rewrites the host) and never succeeded, so the install is not a
	 * copy and its credentials still stand. Lift the block instead of registering
	 * a known site unsigned, which the platform refuses.
	 *
	 * @since 8.0.0
	 *
	 * @param Copy_Quarantine_State $state The engaged copy block.
	 *
	 * @return bool True when the block lifted, false while the copy is pending.
	 */
	public function resume_copy( Copy_Quarantine_State $state ): bool {

		if ( $this->address->is_on_registered_address() ) {
			$state->complete();
			return true;
		}

		if ( ! $this->wire->has_active_license() || $this->backoff->is_in_backoff_window() ) {
			return false;
		}

		return $this->register_as_copy();
	}

	/**
	 * Register this install as a fresh COPY of another site, running the
	 * fail-closed quarantine lifecycle so the new site identity is never
	 * observable alongside the connections the copy inherited from its source.
	 *
	 * A cloned WordPress database brings the source's `uap_connections` rows,
	 * but the platform gives the fresh copy no bindings. The ordering is the
	 * whole point:
	 *
	 *  1. Engage the {@see Copy_Quarantine_State} block BEFORE the registration
	 *     call. From here, {@see Connection_Registry::get_default()} resolves
	 *     nothing — so even though `dispatch()` persists this copy's own signing
	 *     secret mid-call, there is no window where the new identity could
	 *     dispatch using an inherited connection.
	 *  2. On success, quarantine the inherited rows, then lift the block.
	 *  3. On failure, LEAVE the block engaged (fail-closed) and the inherited
	 *     rows AND credentials untouched: clearing them on a transient platform
	 *     failure would be destructive (no connections, no secret, and the
	 *     platform refuses an unsigned re-registration of a site it knows). The
	 *     next attempt completes the lifecycle and lifts the block.
	 *
	 * A copy is never begun while a move is pending: its register would go
	 * out under the move's attempt id, the platform would complete the MOVE,
	 * and step 2 would quarantine the moved site's own connections (L199).
	 * That is refused before step 1, so nothing changes.
	 *
	 * A copy that begins here (the block was not engaged) records what it is
	 * ({@see Site_Copy_Record}): the admin's answer — use the live site's
	 * connections, or keep them switched off — or pending when the plugin
	 * recognised the copy by itself, and the source site's address, read from
	 * the registration marker the copied database carries. A retry of the same
	 * copy (the block is engaged) keeps the record as it is. The record
	 * outlives the asynchronous registration, so whatever follows the copy
	 * lifecycle acts on the answer given when it began.
	 *
	 * @param string $answer The admin's answer, Site_Copy_Record::ANSWER_*.
	 *
	 * @return bool True when the copy registered and its inherited connections
	 *              were quarantined; false otherwise (block stays engaged, or
	 *              was never engaged because a move is pending).
	 */
	public function register_as_copy( string $answer = Site_Copy_Record::ANSWER_PENDING ): bool {

		if ( $this->register->refuses_other_intention( false ) ) {
			return false;
		}

		$state = new Copy_Quarantine_State();
		if ( ! $state->is_blocking() ) {
			$this->copy_record->begin( (string) $this->store->registered_site_name(), $answer );
		}
		$state->engage();

		// A cloned DB carries the SOURCE site's signing secret. Register as a
		// genuine FIRST registration without presenting it: under C1 that is the
		// challenge handshake (attempt + out-of-band proof), and gate-off it is a
		// direct 200. An M4 proof would be for a secret the platform never issued
		// THIS site — which a C1-enforcing platform rejects, so the copy could
		// never register. The inherited secret stays on file until the platform
		// has accepted the copy; store() then rotates it into the previous slot.
		// The platform-issued site_uuid still rides along as the clone signal.
		if ( ! $this->register->force_register( null, true ) ) {
			// force_register returns false for BOTH a started C1 handshake (202,
			// which leaves a pending attempt) and a hard failure (dispatch clears
			// the attempt on any non-2xx). A pending attempt therefore means the
			// handshake is in flight — keep the block engaged and let the
			// completion poll (register_if_needed, ahead of the drift gate) finish
			// it. No pending attempt means a real failure: fail closed.
			if ( null === $this->challenge->pending_attempt() ) {
				$state->fail();
			}

			return false;
		}

		// Direct 200 (gate-off platform): the secret is already stored. Finish the
		// copy lifecycle now.
		return $this->finish_copy_quarantine( $state );
	}

	/**
	 * Quarantine the inherited connections and lift the fail-closed copy block —
	 * the LAST step of the copy lifecycle, run only once the copy holds its OWN
	 * signing secret (a direct 200, or a redeemed C1 completion).
	 *
	 * Quarantine is fail-closed: if it cannot neutralize EVERY inherited
	 * connection (a SELECT/UPDATE error), leave the block engaged and DON'T
	 * complete — lifting the block while an inherited row is still
	 * active/resolvable is exactly the leak this lifecycle prevents.
	 *
	 * On a quarantine failure the block is left in the ACTIVE state (NOT failed):
	 * registration already succeeded, so the copy holds its own secret and only
	 * the sweep remains. {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::register_if_needed()} sees the ACTIVE state and
	 * retries JUST this method on the next tick (the sweep is idempotent —
	 * already-quarantined rows are skipped) without re-registering. Marking it
	 * `failed` instead would strand the copy: the next tick finds a secret on
	 * file (has() true) and drift 'none', short-circuits to `true`, and the block
	 * would never lift, so connections never resolve.
	 *
	 * @param Copy_Quarantine_State $state The engaged copy block.
	 *
	 * @return bool True when the inherited rows were quarantined and the block
	 *              lifted; false when quarantine failed (block stays ACTIVE for a
	 *              retry).
	 */
	public function finish_copy_quarantine( Copy_Quarantine_State $state ): bool {

		$state->activate();

		try {
			( new Connection_Registry() )->quarantine_all();
		} catch ( Quarantine_Failed_Exception $e ) {
			automator_log( 'Copy quarantine failed, will retry (block stays engaged): ' . $e->getMessage(), 'Platform_Site_Registration', false );
			return false;
		}

		$state->complete();

		// The copy holds its own secret and every inherited row is switched
		// off: what follows the copy — attaching to the live site's accounts,
		// a sync a recovery owed — can run now, and not before.
		Dispatcher::action( 'automator_platform_site_copy_completed' );

		return true;
	}
}
