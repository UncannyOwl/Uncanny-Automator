<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Completion_Poll;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Completion_Schedule;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Copy_Registration;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Register_Dispatch;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Backoff;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Outcomes;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Recorder;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Wire;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Reverification_Ledger;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Site_Address_Drift;
use Uncanny_Automator\App\Infrastructure\Connections\Copy_Quarantine_State;
use Uncanny_Automator\App\Infrastructure\Connections\Site_Copy_Record;
use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;

/**
 * Plugin-initiated site registration with the Automator Platform —
 * MVP plan A.3 + Contract A.
 *
 * Wire shape (POST `/app/v1/sites/register`, site registration recovery
 * wire contract W1):
 *  - Headers: License-Key, Item-Name, Item-Version, Content-Type. The
 *    `Site-Name` license-tenancy key is supplied by the shared header
 *    injectors on `http_request_args`, NOT by this class (see headers()).
 *    A site holding a signing secret (and not registering as a copy) also
 *    sends the M4 proof: `X-Automator-Timestamp` + `X-Automator-Signature:
 *    v1=<hmac_sha256("v1:{ts}:register:{callback_url}", raw_secret)>`.
 *    A move this install cannot sign (the secret on file no longer opens)
 *    sends no proof and names the old address's challenge URL instead
 *    (`migrate_from.without_secret`, move contract §10).
 *  - Body: `{ site_id: <UUIDv4>, callback_url: <url>, plugin_version: <v>,
 *    environment_type: <env> }`, plus `site_uuid` when one is held,
 *    `migrate_from` when the caller supplies one, and ALWAYS the C1 attempt
 *    fields `attempt_id`, `attempt_secret`, `challenge_url`. `site_id` is
 *    our legacy local UUID — sent for wire-shape completeness but ignored
 *    server-side, the platform derives its site_id from the normalized
 *    Site-Name.
 *  - 200: the platform accepted the register (a valid proof, or a gate-off
 *    first registration). `data` carries `site_uuid` (platform-issued,
 *    custodied here, never used as authorization) and `migrated` when the
 *    platform moved the site (spec 2026-09-01 §9); a re-registration never
 *    returns the secret.
 *  - 202: the platform started its host-control challenge — a first
 *    registration, a recovery (`data.recovery`) for a site it knows whose
 *    proof is absent or no longer matches, or a move (`migrate_from`, L199)
 *    whose new address it must confirm before it moves anything. The
 *    completion poll redeems it ({@see Completion_Poll::complete_challenge()}); a recovery or
 *    a move keeps the site_uuid and replaces the secret. The 202 body is
 *    never read: the attempt record says what the attempt is for
 *    ({@see is_move_pending()}).
 *
 * Replaces the platform-pushed lifecycle handshake from PR 7227 (A.6
 * killed `Credentials_Handshake_Service` + `Platform_Webhook_Controller`).
 *
 * Idempotency:
 *  - `register_if_needed()` no-ops when a `signing_secret` is already
 *    persisted in `Site_Credentials_Store`, {@see Site_Address_Drift::drift()} reports 'none'
 *    AND no register is owed ({@see Reverification_Ledger::is_register_owed()}). The drift gate
 *    runs AHEAD of that short-circuit, so a site whose Site-Name or
 *    License-Key moved re-registers even though it holds a secret. So does
 *    a site that owes the platform a register: one whose credentials the
 *    platform rejected ({@see Reverification_Ledger::request_reverification()}, wire contract W4)
 *    or whose callback receiver refused a delivery no secret it holds
 *    verifies ({@see Reverification_Ledger::request_reverification_for_refused_delivery()},
 *    callback delivery contract §6), at most once per ten-minute window —
 *    for a refused delivery alone, at most once an hour
 *    ({@see Reverification_Ledger::REFUSED_DELIVERY_PACE_SECONDS}), the
 *    site staying settled meanwhile; and
 *    one whose last attempt it
 *    superseded (a 410 at completion, W3), after the short window the
 *    first time and after the ten-minute window for a repeat with no
 *    registration succeeding in between — on every register path
 *    ({@see Completion_Poll::retire_superseded_attempt()}). Use `force_register()` to
 *    re-POST unconditionally (ops recovery, or to drive a migration with an
 *    explicit `migrate_from`).
 *  - Backoff on failures so a stuck network or 5xx cascade can't DOS the
 *    platform from a busy site: 60 seconds for transient failures, ten
 *    minutes for permanent-state ones, longer when the platform says so
 *    (Retry-After). License-status changes and the admin's site-address
 *    answers clear it ({@see Registration_Backoff::clear_backoff()}).
 *
 * @since 8.0.0
 */
final class Platform_Site_Registration {

	/**
	 * How long an admin action that got a 202 keeps polling the completion
	 * within its own request ({@see confirm()}, handoff R2).
	 *
	 * @since 8.0.0
	 */
	public const CONFIRM_SECONDS = 30;

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * C1 first-registration challenge (attempt custody + proof signing).
	 *
	 * @var Registration_Challenge
	 */
	private $challenge;

	/**
	 * Pauses an admin action between completion polls ({@see confirm()}).
	 *
	 * @var callable(int): void
	 */
	private $wait;

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @var Registration_Backoff
	 */
	private $backoff;

	/**
	 * @var Reverification_Ledger
	 */
	private $ledger;

	/**
	 * @var Registration_Outcomes
	 */
	private $outcomes;

	/**
	 * @var Completion_Schedule
	 */
	private $schedule;

	/**
	 * @var Site_Address_Drift
	 */
	private $address;

	/**
	 * @var Register_Dispatch
	 */
	private $register;

	/**
	 * @var Completion_Poll
	 */
	private $poll;

	/**
	 * @var Copy_Registration
	 */
	private $copy;

	/**
	 * @param Site_Identity_Service       $identity  Owner of the site UUIDv4.
	 * @param Site_Credentials_Store      $store     Persistence target for the
	 *                                               signing_secret + site_uuid +
	 *                                               callback_url returned by the
	 *                                               platform, plus the two drift
	 *                                               markers we registered with.
	 * @param object|null                 $http      Collaborator with a
	 *                                               `post(string, array, array): array`
	 *                                               shape. Defaults to
	 *                                               {@see Platform_Http}.
	 * @param Registration_Challenge|null $challenge C1 attempt store + signer.
	 * @param Site_Copy_Record|null       $copy_record The copy's answer and source.
	 * @param License_Provider_Interface|null $license The license provider.
	 * @param callable|null               $wait      Pauses for the given seconds
	 *                                               between completion polls in
	 *                                               {@see confirm()}; sleep() by
	 *                                               default.
	 */
	public function __construct(
		Site_Identity_Service $identity,
		Site_Credentials_Store $store,
		?object $http = null,
		?Registration_Challenge $challenge = null,
		?Site_Copy_Record $copy_record = null,
		?License_Provider_Interface $license = null,
		?callable $wait = null
	) {

		$http        = $http ?? new Platform_Http();
		$challenge   = $challenge ?? new Registration_Challenge();
		$copy_record = $copy_record ?? new Site_Copy_Record();

		$this->store     = $store;
		$this->challenge = $challenge;
		$this->wait      = $wait ?? static function ( int $seconds ): void {
			sleep( $seconds );
		};

		$this->wire     = new Registration_Wire( $license );
		$this->backoff  = new Registration_Backoff();
		$this->ledger   = new Reverification_Ledger( $store );
		$this->outcomes = new Registration_Outcomes( $this->wire );
		$this->schedule = new Completion_Schedule( $challenge );
		$this->address  = new Site_Address_Drift( $store, $this->wire );

		$recorder       = new Registration_Recorder( $store, $this->wire, $this->ledger, $this->outcomes );
		$this->register = new Register_Dispatch( $identity, $store, $http, $challenge, $this->wire, $this->backoff, $this->outcomes, $this->schedule, $recorder );
		$this->poll     = new Completion_Poll( $store, $http, $challenge, $this->wire, $recorder, $this->backoff, $this->outcomes, $this->ledger, $this->schedule );
		$this->copy     = new Copy_Registration( $store, $challenge, $copy_record, $this->register, $this->address, $this->wire, $this->backoff );
	}

	/**
	 * Register the site if not already registered. Idempotent. Skips
	 * when a signing_secret is on file; respects the 60s backoff window
	 * on the failure path.
	 *
	 * Runs the drift gate FIRST, ahead of the already-registered
	 * short-circuit — a drifted site holds a signing_secret, so
	 * `has()` would otherwise report "nothing to do" forever:
	 *
	 *  - 'license' → re-register immediately (the free → Pro flip).
	 *  - 'url'     → in production the {@see Site_Address_Drift::drift()} contract hands the
	 *                next step to the site-address drift notice (a
	 *                staging push looks exactly like this and carries the
	 *                production secret); anywhere else re-register as a
	 *                site of its own.
	 *  - 'none'    → fall through to the normal idempotent path, where a
	 *                register the platform is owed — a re-verification it
	 *                asked for, or the retry of an attempt it superseded —
	 *                runs ahead of the already-registered short-circuit.
	 *
	 * @return bool True when registered (either already-registered or
	 *              freshly registered this call), false when skipped or
	 *              the platform call failed.
	 */
	public function register_if_needed(): bool {

		// C1: once a challenge is in flight (a prior register answered 202 — a
		// first registration, a recovery or a move) the ONLY way forward is to
		// poll /sites/register/complete and redeem the winning secret. This runs
		// BEFORE the drift gate so a COPY or a MOVE — whose Site-Name still reads
		// as 'url' drift until it completes — can't be starved, re-dispatching a
		// fresh 202 every tick instead of finishing. A pending attempt only ever
		// exists after a 202, so a drifted site nobody has answered for (it
		// holds a secret and never got a 202) has none and falls through to the
		// drift gate untouched.
		//
		// An attempt that aged out unproven is abandoned first, with a backoff.
		$this->poll->abandon_if_expired();

		$pending = $this->challenge->pending_attempt();
		if ( null !== $pending ) {

			// This runs on `init` — every front-end hit, admin page, REST call and
			// admin-ajax request. The poll is a blocking platform round trip, so
			// it is paced by the attempt's own ladder rather than paid per request.
			if ( ! $this->challenge->poll_due() ) {
				return false;
			}

			$completed = $this->poll->complete_challenge( $pending );

			// A copy runs the handshake behind the fail-closed block; once its
			// own secret is redeemed, quarantine the inherited rows and lift the
			// block. An ordinary first registration engages no block, so
			// is_blocking() is false and this is a plain return.
			$state = new Copy_Quarantine_State();
			if ( $completed && $state->is_blocking() ) {
				return $this->copy->finish_copy_quarantine( $state );
			}

			return $completed;
		}

		// L286 — the platform refused this site's host when it challenged it.
		// Final for this address: registering again would only be refused again.
		// A changed address is a new question.
		if ( null !== $this->outcomes->host_refusal() ) {
			return false;
		}

		// A copy whose registration SUCCEEDED but whose quarantine has not yet
		// completed sits in the ACTIVE state with its own secret already stored —
		// so both the drift gate ('none') and the has() short-circuit below would
		// otherwise return `true` and leave the block engaged forever. Retry ONLY
		// the quarantine (never re-register); the block stays engaged until it
		// lands, so no inherited connection resolves in the meantime.
		$copy_state = new Copy_Quarantine_State();
		if ( Copy_Quarantine_State::STATE_ACTIVE === $copy_state->state() ) {
			return $this->copy->finish_copy_quarantine( $copy_state );
		}

		// Any OTHER engaged state (prepared / failed) is a copy whose registration
		// has not succeeded yet.
		if ( $copy_state->is_blocking() ) {
			return $this->copy->resume_copy( $copy_state );
		}

		$drift = $this->address->drift();
		if ( 'license' === $drift ) {
			return $this->register->force_register();
		}
		if ( 'url' === $drift ) {
			// L247 — a LOCAL site cannot move, and a copy of it is a new local
			// site: it registers as its own site, its inherited connections kept
			// off, with no question asked whatever the environment.
			if ( ( new Local_Site_Status() )->is_local() ) {
				return $this->copy->register_as_copy( Site_Copy_Record::ANSWER_KEEP_OFF );
			}

			// Production: Site_Address_Drift_Notice owns the next step.
			// Anything else registers as its own site (a copy).
			if ( $this->address->is_production() ) {
				return false;
			}

			// Same contract as the admin "This is a copy" answer: a staging or
			// dev clone carries the source site's uap_connections rows, but the
			// platform hands a fresh site no bindings. register_as_copy() runs
			// the fail-closed lifecycle — block engaged before registration,
			// inherited rows quarantined only after it succeeds.
			return $this->copy->register_as_copy();
		}

		// A register is owed: the platform rejected this install's site
		// credentials (W4), this site refused a platform delivery no secret it
		// holds verifies, or the platform superseded its last attempt (W3 410)
		// and no registration has succeeded since. Holding a secret proves nothing
		// then, so re-register ahead of the short-circuit below — after the
		// drift gate, which owns a moved site.
		if ( $this->ledger->is_register_owed() ) {
			return $this->reverify();
		}

		if ( $this->store->has() ) {
			return true;
		}

		// Gate on an active license. Registering before activation just
		// burns the 60s backoff on a 401 and risks missing the
		// activation window — and the platform's V3LicenseAuth can't
		// resolve the license anyway. Skip silently; the next `init@20`
		// after activation picks up the registration.
		if ( ! $this->wire->has_active_license() ) {
			return false;
		}

		if ( $this->backoff->is_in_backoff_window() ) {
			return false;
		}

		return $this->register->dispatch();
	}

	/**
	 * {@see register_if_needed()} for an admin action — Re-check registration,
	 * the connect click, license activation — that should not leave the
	 * handshake to the next wp-admin load (handoff R2). After a 202 the
	 * completion is polled on the attempt's own ladder within this request,
	 * for about {@see CONFIRM_SECONDS}: the first poll at once, then each
	 * rung, longer when the platform said Retry-After. What does not finish in
	 * that time finishes as it does for every other request, on the attempt's
	 * cron event; the attempt's lifetime and the backoff are unchanged.
	 *
	 * @since 8.0.0
	 *
	 * @param int $within Seconds this request may spend; {@see CONFIRM_SECONDS}.
	 *
	 * @return bool Whether the registration is settled when it returns.
	 */
	public function confirm( int $within = self::CONFIRM_SECONDS ): bool {

		$started = time();
		$waited  = 0;

		$registered = $this->register_if_needed();

		while ( ! $registered && $this->is_challenge_pending() ) {
			$pause = max( 0, $this->challenge->next_poll_at() - time() );
			if ( max( time() - $started, $waited ) + $pause > $within ) {
				break;
			}

			if ( 0 < $pause ) {
				( $this->wait )( $pause );
				$waited += $pause;
			}

			$registered = $this->register_if_needed();
		}

		return $registered || $this->is_settled();
	}

	/**
	 * The per-request (`init`) entry point: {@see register_if_needed()}, but only
	 * on a request a visitor is not waiting on.
	 *
	 * Registration and the C1 completion poll are blocking platform round trips.
	 * A site only NEEDS to be registered before its first app connection, and
	 * that can only happen in wp-admin — so a front-end page load gains nothing
	 * from paying for one, and a slow or down platform must never be able to
	 * slow a customer's public site. WP-Cron is included so a pending handshake
	 * finishes without an admin sitting on a page.
	 *
	 * admin-ajax.php is excluded on purpose: is_admin() is TRUE there, and
	 * front-end visitors hit it constantly (cart fragments, form submits).
	 *
	 * Deliberate callers — the license-activation hook, the site-address notice —
	 * call register_if_needed() directly; they are already admin actions.
	 *
	 * @return bool
	 */
	public function register_on_request(): bool {

		if ( ! wp_doing_cron() && ( ! is_admin() || wp_doing_ajax() ) ) {
			return false;
		}

		// Kill switch: Platform_Http would refuse the call anyway, but returning
		// here keeps a disabled site from stamping a backoff on every admin load.
		if ( ! Platform_Http::platform_enabled() ) {
			return false;
		}

		return $this->register_if_needed();
	}

	/**
	 * Re-POST the registration whether or not a secret is on file: ops recovery,
	 * or the admin's "This site moved here" answer with an explicit
	 * `migrate_from`. {@see Register_Dispatch::force_register()} holds the rule.
	 *
	 * @param array<string, string>|null $migrate_from Prior identity (`site_uuid` /
	 *        `site_name`) the platform should move to this registration.
	 * @param bool                       $as_first_registration Register as a site the
	 *        platform has never seen, whatever secret is on file.
	 *
	 * @return bool True on success, false on failure (within backoff).
	 */
	public function force_register( ?array $migrate_from = null, bool $as_first_registration = false ): bool {

		return $this->register->force_register( $migrate_from, $as_first_registration );
	}

	/**
	 * Register this install as a fresh copy of another site, behind the
	 * fail-closed quarantine lifecycle. {@see Copy_Registration::register_as_copy()}
	 * holds the rule.
	 *
	 * @param string $answer The admin's answer, Site_Copy_Record::ANSWER_*.
	 *
	 * @return bool True when the copy registered and its inherited connections
	 *              were quarantined; false otherwise.
	 */
	public function register_as_copy( string $answer = Site_Copy_Record::ANSWER_PENDING ): bool {

		return $this->copy->register_as_copy( $answer );
	}

	/**
	 * Whether a re-verification a refused callback delivery asked for is
	 * pending and can run ({@see can_reverify()}) — what
	 * {@see \Uncanny_Automator\App\Presentation\Admin\Site_Reverification_Notice}
	 * tells the admin is under way. One that cannot run yet (no active
	 * license, the platform plane disabled) is still owed
	 * ({@see Reverification_Ledger::is_refused_delivery_owed()}) and runs once it can; until then
	 * nothing is under way to tell of.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_reverifying_for_refused_delivery(): bool {

		return $this->ledger->is_refused_delivery_owed() && $this->can_reverify();
	}

	/**
	 * Re-register because a register is owed ({@see Reverification_Ledger::is_register_owed()}): the
	 * M4 proof when a secret is held, plus the attempt fields, so the platform
	 * answers 200 (the credentials are fine after all) or 202 (a first
	 * registration, or a recovery the completion poll finishes). Either
	 * success settles what was owed in {@see Registration_Recorder::persist_registration()}; a
	 * failure leaves it standing.
	 *
	 * Loop guard: whatever the outcome, the next re-verification waits out at
	 * least the ten-minute window (longer when the platform said so). One owed
	 * only for a refused delivery, which anyone who can post to this install
	 * can cause, also waits an hour after the last such one
	 * ({@see Reverification_Ledger::REFUSED_DELIVERY_PACE_SECONDS}). Signed
	 * requests can keep failing although the register just answered 200 —
	 * something other than the secret is wrong — and must not become one
	 * register call per failing request. A license-status change or an admin
	 * answer clears the window ({@see Registration_Backoff::clear_backoff()}); a successful recovery
	 * deliberately does not ({@see Completion_Poll::poll_completion()}).
	 *
	 * A 200 is reported as inconclusive unless the attempt before this
	 * register was superseded — once for each re-verification cause pending,
	 * each with its own reason and its own place to look. Not superseded, the
	 * register was owed for a W4 rejection or a refused delivery (nothing else
	 * owes one), and a platform that accepts the registration with the very
	 * credentials it rejected, or whose delivery this site could not verify,
	 * is worth a look. A 410 says the platform's side changed after any
	 * rejection was heard, so a register accepted after one is simply
	 * settled: it is no evidence that something rewrites the signed traffic.
	 *
	 * @since 8.0.0
	 *
	 * @return bool True when the platform accepted the credentials (200).
	 */
	private function reverify(): bool {

		if ( ! $this->can_reverify() || $this->backoff->is_in_backoff_window() ) {
			return false;
		}

		// Read before dispatching: a 200 settles every cause.
		$platform_rejection = $this->ledger->platform_rejection();
		$refused_delivery   = $this->ledger->refused_delivery();
		$superseded         = $this->ledger->is_attempt_superseded();
		$refused_alone      = '' !== $refused_delivery && '' === $platform_rejection && ! $superseded;
		$accepted           = $this->register->dispatch();

		$this->backoff->hold_backoff( Registration_Backoff::PERMANENT_BACKOFF_SECONDS );
		if ( $refused_alone ) {
			$this->ledger->note_refused_delivery_reverified();
		}

		if ( $accepted && ! $superseded ) {
			$this->report_inconclusive( $platform_rejection, $refused_delivery );
		}

		return $accepted;
	}

	/**
	 * Whether a re-verification can run on this install, its backoff window
	 * aside: the platform plane is enabled and the license is active. The one
	 * test {@see reverify()} runs by and
	 * {@see is_reverifying_for_refused_delivery()} reports by, so the admin is
	 * never told of a re-verification that cannot run. The plane is checked
	 * here, not only in {@see register_on_request()}: the license-status hook
	 * calls {@see register_if_needed()} directly.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	private function can_reverify(): bool {

		return Platform_Http::platform_enabled() && $this->wire->has_active_license();
	}

	/**
	 * Tell an operator that the platform accepted a re-verification with the
	 * credentials whose signed traffic failed — for each cause that asked:
	 * the platform's rejection of this site's requests, where something may
	 * change them on the way to the platform; and a delivery this site
	 * refused, where something may change deliveries on the way to this site,
	 * or the refused request did not come from the platform at all.
	 *
	 * @since 8.0.0
	 *
	 * @param string $platform_rejection The W4 `error.type`, or ''.
	 * @param string $refused_delivery   The refused delivery's recorded reason, or ''.
	 *
	 * @return void
	 */
	private function report_inconclusive( string $platform_rejection, string $refused_delivery ): void {

		if ( '' !== $platform_rejection ) {
			Dispatcher::action( 'automator_platform_site_reverify_inconclusive', $platform_rejection );
			automator_log(
				'The Automator platform rejected a signed request from this site (' . $platform_rejection . ') but accepted its registration with the same credentials. Check for a proxy, firewall or security plugin that changes request bodies, query strings or headers on their way to ' . $this->wire->register_url() . ', and for requests sent under a site address other than ' . $this->wire->current_site_name() . '. The next check is in ' . human_time_diff( time(), $this->backoff->backoff_ends_at() ) . '.',
				'Platform_Site_Registration',
				true
			);
		}

		if ( '' !== $refused_delivery ) {
			Dispatcher::action( 'automator_platform_site_reverify_inconclusive', $refused_delivery );
			automator_log(
				'This site refused a delivery to ' . $this->wire->callback_url() . ' (' . $refused_delivery . ') but the Automator platform accepted its registration with the same credentials. Either that request did not come from the platform, or something changes deliveries on their way to this site: check for a proxy, firewall or security plugin that changes request bodies or headers. The next check is in ' . human_time_diff( time(), $this->backoff->backoff_ends_at() ) . '.',
				'Platform_Site_Registration',
				true
			);
		}
	}

	/**
	 * Where this site's registration stands (handoff R1) — the one answer the
	 * registration notice, the system status page, the connect page and held
	 * app actions read. Decided in this order, the first that holds:
	 *
	 *  1. refused   — the platform refused this address when it challenged it
	 *                 (L286); final until the address changes or a re-check.
	 *                 Told first, as it is what the admin must fix; while the
	 *                 address is in question it holds dispatch too (R6);
	 *  2. copy_pending / move_pending — the admin's answer to the site-address
	 *                 question is being registered; dispatch is held (R6);
	 *  3. pending   — any other attempt is being confirmed, since it began;
	 *  4. address_changed — a production site's address changed and the
	 *                 question is open; dispatch is held (R6);
	 *  5. registered — {@see is_settled()};
	 *  6. no_license — nothing registers without an active license;
	 *  7. how the last attempt failed — refused, unreachable or retrying;
	 *  8. reverifying — the platform asked for a register, owed next check;
	 *  9. retrying  — inside a backoff window no failure record explains;
	 * 10. not_registered — licensed, and nothing has been tried yet.
	 *
	 * @since 8.0.0
	 *
	 * @return Registration_Status
	 */
	public function status(): Registration_Status {

		$refusal = $this->outcomes->host_refusal();
		if ( null !== $refusal ) {
			return Registration_Status::of(
				Registration_Status::REFUSED,
				$refusal + array(
					'reason'         => Registration_Status::REASON_HOST,
					'challenge_url'  => $this->wire->challenge_url(),
					'holds_dispatch' => $this->is_address_in_question(),
				)
			);
		}

		if ( $this->is_copy_pending() ) {
			return Registration_Status::of( Registration_Status::COPY_PENDING );
		}

		if ( $this->is_challenge_pending() ) {
			$progress = $this->challenge->progress();
			$checked  = null !== $progress && $progress->has_failed_check()
				? array(
					'reason' => $progress->last_outcome(),
					'status' => $progress->last_http_status(),
				)
				: array();

			return Registration_Status::of(
				$this->challenge->is_move() ? Registration_Status::MOVE_PENDING : Registration_Status::PENDING,
				array( 'since' => $this->challenge->begun_at() ) + $checked
			);
		}

		if ( $this->address->is_address_question_open() ) {
			return Registration_Status::of( Registration_Status::ADDRESS_CHANGED );
		}

		// The license is told before "registered" (decision k): no app works
		// until it does. After the states above, which hold dispatch.
		if ( ! $this->wire->has_active_license() ) {
			return Registration_Status::of( Registration_Status::NO_LICENSE );
		}

		$license_refusal = $this->outcomes->license_refusal();
		if ( null !== $license_refusal ) {
			return Registration_Status::of(
				Registration_Status::LICENSE_REFUSED,
				array(
					'status'      => $license_refusal['status'],
					'type'        => $license_refusal['type'],
					'reason'      => $license_refusal['reason'],
					'description' => $license_refusal['message'],
				)
			);
		}

		if ( $this->is_settled() ) {
			return Registration_Status::of( Registration_Status::REGISTERED );
		}

		$failure = $this->outcomes->last_failure();
		if ( is_array( $failure ) && isset( $failure['state'] ) ) {
			return Registration_Status::of( (string) $failure['state'], array( 'until' => $this->backoff->backoff_ends_at() ) + $failure );
		}

		if ( $this->ledger->is_register_owed() ) {
			return Registration_Status::of( Registration_Status::REVERIFYING );
		}

		if ( $this->backoff->is_in_backoff_window() ) {
			return Registration_Status::of( Registration_Status::RETRYING, array( 'until' => $this->backoff->backoff_ends_at() ) );
		}

		return Registration_Status::of( Registration_Status::NOT_REGISTERED );
	}

	/**
	 * "Re-check registration" (handoff R1): the admin asks for the
	 * registration to be checked now. The backoff and a refused delivery's
	 * pace no longer apply, and a
	 * refused address is asked about again (the admin may have fixed DNS or
	 * the A-label); then the normal registration step runs, which never
	 * answers the site-address question for the admin — and, being an admin
	 * action, keeps polling a 202's completion within the request
	 * ({@see confirm()}, R2).
	 *
	 * @since 8.0.0
	 *
	 * @return Registration_Status Where the registration stands afterwards.
	 */
	public function recheck(): Registration_Status {

		$this->backoff->clear_backoff();
		$this->outcomes->forget_host_refusal();
		$this->ledger->forget_refused_delivery_pace();

		$this->confirm();

		return $this->status();
	}

	/**
	 * Whether this site's address is in question (R6): the site-address
	 * question is open, or the admin's answer — a copy, a move — is being
	 * registered. The secret on file is then not this site's to sign with: a
	 * copy holds its source's, a move's is the old address's until the
	 * platform moves it.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	private function is_address_in_question(): bool {

		return $this->is_copy_pending() || $this->is_move_pending() || $this->address->is_address_question_open();
	}

	/**
	 * Whether a C1 handshake is in flight — registration was accepted (202) and
	 * is waiting on the platform confirming this site.
	 *
	 * @return bool
	 */
	public function is_challenge_pending(): bool {

		return null !== $this->challenge->pending_attempt();
	}

	/**
	 * Whether this site's registration is settled for the address it runs on:
	 * it holds a secret, the secret was registered for this address and
	 * license, no copy or handshake is in flight, and the platform is owed no
	 * register. Only then is the secret on file this site's own and accepted —
	 * the precondition for anything that signs a request about this site's
	 * connections. Unsettled, such a request would be signed with a secret that
	 * is not this site's (a copy still holds its source's) or that the platform
	 * already refused.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_settled(): bool {

		return $this->store->has()
			&& 'none' === $this->address->drift()
			&& ! $this->is_copy_pending()
			&& ! $this->is_challenge_pending()
			&& ! $this->ledger->is_register_owed();
	}

	/**
	 * Whether this install is mid-way through registering as a copy. The secret
	 * on file is then still the SOURCE site's, kept only until the platform
	 * accepts the copy — it is not this site's to sign with.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_copy_pending(): bool {

		return ( new Copy_Quarantine_State() )->is_blocking();
	}

	/**
	 * Whether the handshake in flight is a move (L199): the admin answered
	 * "This site moved here" and the platform is confirming the new address
	 * before it moves anything. Read from the attempt record, so it lasts
	 * exactly as long as the attempt — through page loads and cache flushes.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_move_pending(): bool {

		return $this->is_challenge_pending() && $this->challenge->is_move();
	}

	/**
	 * Whether the handshake in flight is a move without the old secret (move
	 * contract §10): the platform is confirming the new address and then that
	 * the old one no longer serves this site — minutes, not seconds. Read from
	 * the attempt record, like {@see is_move_pending()}.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_move_without_secret_pending(): bool {

		return $this->is_move_pending() && $this->challenge->is_move_without_secret();
	}

	/**
	 * Whether a pending move can still be dropped. Nothing moves before the
	 * platform's completion succeeds, and only this install holds the attempt
	 * secret that completes it — so until a completion request is sent, and
	 * whenever every one sent was answered "not completed", nothing can have
	 * moved. A completion still in flight, or one whose answer was lost, may
	 * have moved and re-keyed the site: the attempt is then the only way to
	 * redeem the new secret, and must be kept.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function can_cancel_move(): bool {

		return $this->is_move_pending() && ! $this->challenge->is_completion_unanswered();
	}

	/**
	 * Drop a pending move — the admin answered "moved" by mistake. No
	 * backoff: the site goes straight back to the question. Does nothing
	 * unless the move can be dropped ({@see can_cancel_move()}).
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function cancel_move(): void {

		if ( ! $this->can_cancel_move() ) {
			return;
		}

		$this->challenge->clear_attempt();
		$this->schedule->sync();

		automator_log( 'The site move was cancelled before the platform confirmed it; nothing was moved.', 'Platform_Site_Registration', false );
	}
}
