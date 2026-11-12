<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Connections\Copy_Quarantine_State;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Http;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Challenge;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Challenge_Controller;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Identity_Service;

/**
 * Sends the registration request and reconciles the platform's answer: a first
 * registration, a re-register with proof, a recovery, a copy, or a move with or without
 * the old secret. A 202 starts the host challenge, a 200 completes at once, anything
 * else is a refusal that backs the site off and tells the admin why.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Register_Dispatch {

	/**
	 * Platform error types that mean "a human or ops step is needed". Each
	 * entry carries both halves of the response: the `signal` other code
	 * listens on, and the operator-facing `log` line naming the recovery
	 * step (logged forced, alongside the signal).
	 *
	 * @since 8.0.0
	 */
	private const ERROR_HANDLING = array(
		'SITE_LICENSE_TRANSFER_DETECTED' => array(
			'signal' => 'automator_platform_site_register_transfer_detected',
			'log'    => 'Platform registration refused (409): the license changed on a registered host but the platform could not verify this install, or platform migration is not yet available. Connections stay paused until it succeeds.',
		),
		'SITE_MIGRATION_CONFLICT'        => array(
			'signal' => 'automator_platform_site_migration_conflict',
			'log'    => 'Platform registration refused (409): another site already holds this address. Support must release the destination row.',
		),
		'SITE_ENVIRONMENT_MISMATCH'      => array(
			'signal' => 'automator_platform_site_environment_mismatch',
			'log'    => 'Platform registration refused (409): only a production environment can claim a site move. Set WP_ENVIRONMENT_TYPE to production on the moved site, or register it as a copy.',
		),
		// Reserved from the FIRST /v3 client on purpose: a protocol version can
		// only ever be retired if the oldest install in the field already knows
		// how to hear "you are too old" and tell its admin. The platform pairs it
		// with a Retry-After so the refused client also stops asking.
		// L247 — a local site asked for something never available to it (a
		// move onto its address). Final: never retried, never re-registered on.
		'SITE_IS_LOCAL'                  => array(
			'signal' => 'automator_platform_site_is_local',
			'log'    => 'Platform registration refused (409): this site is local (its address is not a public one), and a site cannot move onto a local address. It registers as its own local site.',
		),
		'PLUGIN_UPGRADE_REQUIRED'        => array(
			'signal' => 'automator_platform_upgrade_required',
			'log'    => 'Platform registration refused (426): this version of Uncanny Automator is too old for the platform. Update the plugin to restore app connections.',
		),
	);

	/**
	 * @var Site_Identity_Service
	 */
	private $identity;

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * Typed as object so any collaborator with a matching `post()` method can be
	 * injected for tests.
	 *
	 * @var object
	 */
	private $http;

	/**
	 * @var Registration_Challenge
	 */
	private $challenge;

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @var Registration_Backoff
	 */
	private $backoff;

	/**
	 * @var Registration_Outcomes
	 */
	private $outcomes;

	/**
	 * @var Completion_Schedule
	 */
	private $schedule;

	/**
	 * @var Registration_Recorder
	 */
	private $recorder;

	/**
	 * Which door the platform reaches this site through; a new attempt may
	 * choose it from how the last one was recorded ({@see dispatch()}).
	 *
	 * @var Registration_Door
	 */
	private $door;

	/**
	 * @param Site_Identity_Service  $identity  Owner of the site UUIDv4.
	 * @param Site_Credentials_Store $store     Holds the secret, the identity and the markers.
	 * @param object                 $http      Collaborator with a `post(string, array, array): array` shape.
	 * @param Registration_Challenge $challenge The attempt on file.
	 * @param Registration_Wire      $wire      Where the request goes and what it says.
	 * @param Registration_Backoff   $backoff   The pause between attempts.
	 * @param Registration_Outcomes  $outcomes  How attempts ended.
	 * @param Completion_Schedule    $schedule  The cron event that finishes a pending attempt.
	 * @param Registration_Recorder  $recorder  Accepts a registration the platform answered.
	 * @param Registration_Door|null $door      Which door the platform reaches this site through; the wire's addresses are of it.
	 */
	public function __construct(
		Site_Identity_Service $identity,
		Site_Credentials_Store $store,
		object $http,
		Registration_Challenge $challenge,
		Registration_Wire $wire,
		Registration_Backoff $backoff,
		Registration_Outcomes $outcomes,
		Completion_Schedule $schedule,
		Registration_Recorder $recorder,
		?Registration_Door $door = null
	) {

		$this->identity  = $identity;
		$this->store     = $store;
		$this->http      = $http;
		$this->challenge = $challenge;
		$this->wire      = $wire;
		$this->backoff   = $backoff;
		$this->outcomes  = $outcomes;
		$this->schedule  = $schedule;
		$this->recorder  = $recorder;
		$this->door      = $door ?? new Registration_Door( $store );
	}

	/**
	 * Re-POST registration regardless of whether we already have a
	 * signing_secret. Use for ops recovery or when the
	 * `Site_Identity_Service` has just minted a new UUID after URL drift.
	 *
	 * Reconciliation is keyed entirely on the `Site-Name` header (the
	 * platform's license-tenancy key): a re-register with the same
	 * Site-Name rebinds to the existing platform Site row. The body
	 * `site_id` UUID plays no part — the platform ignores it and derives
	 * site_id from the normalized Site-Name — so URL drift matters only
	 * insofar as it changes the home_url the Site-Name is built from.
	 *
	 * @param array<string, string>|null $migrate_from Prior identity
	 *        (`site_uuid` / `site_name`) the platform should move to this
	 *        registration. Null on plain ops recovery.
	 * @param bool                       $as_first_registration Register as a
	 *        site the platform has never seen, whatever secret is on file —
	 *        see {@see dispatch()}.
	 *
	 * @return bool True on success, false on failure (within backoff).
	 */
	public function force_register( ?array $migrate_from = null, bool $as_first_registration = false ): bool {

		// Same license + backoff gates as register_if_needed — force_register
		// is for ops recovery, but a re-register without an active license
		// still 401s at V3LicenseAuth and burns the backoff window.
		if ( $this->wire->has_active_license() && ! $this->backoff->is_in_backoff_window() ) {
			return $this->dispatch( $migrate_from, true, $as_first_registration );
		}

		// A move that never reached the platform still ended without moving:
		// the admin who answered lands back on the question, and silence there
		// reads as success.
		if ( null !== $migrate_from ) {
			$this->outcomes->fail_move( 'refused' );
		}

		return false;
	}

	/**
	 * POST the registration request and reconcile the response. Stamps
	 * the last-attempt timestamp regardless of outcome so the backoff
	 * window applies to both success and failure paths uniformly.
	 *
	 * @param array<string, string>|null $migrate_from Prior identity to hand
	 *        the platform, when this dispatch is a migration.
	 * @param bool                       $is_drift     Whether this dispatch
	 *        was triggered by a drift check rather than first registration —
	 *        classifies the failure backoff.
	 * @param bool                       $as_first_registration Send no M4
	 *        proof even though a secret is on file. A copy holds its SOURCE
	 *        site's secret: it must not present it, and must not lose it
	 *        before the platform accepts the copy.
	 *
	 * @return bool True when the platform answered with a 200 envelope and
	 *              the credentials store was updated, false otherwise.
	 */
	public function dispatch( ?array $migrate_from = null, bool $is_drift = false, bool $as_first_registration = false ): bool {

		$is_move = null !== $migrate_from;

		// A pending attempt is reused below, and the platform keeps the FIRST
		// intention it stored for an attempt id (L199). A register asking for
		// something else must never go out under it.
		if ( $this->refuses_other_intention( $is_move ) ) {
			return false;
		}

		// A new register answers the drift afresh; how the last move ended no
		// longer applies.
		$this->outcomes->forget_move_failure();

		// A site that updated from 8.0.x may hold that version's record of the
		// platform finding its REST API answered by something that is not the
		// plugin, and no door chosen from it. A new attempt chooses from it now,
		// before the addresses below are read, so the door is open when the
		// register naming it goes out; a pending attempt is resent as it began.
		if ( null === $this->challenge->pending_attempt() && $this->door->after_recorded_unproven( $this->outcomes->last_failure() ) ) {
			automator_log(
				'The platform\'s last check of ' . Registration_Challenge_Controller::url() . ' was answered by something that is not the plugin. This registration gives the platform ' . $this->wire->challenge_url() . ' to confirm this site and ' . $this->wire->callback_url() . ' for its callbacks.',
				'Platform_Site_Registration',
				true
			);
		}

		$callback_url = $this->wire->callback_url();
		$headers      = $this->wire->headers();

		// Platform audit M4: re-registering an EXISTING site requires
		// HMAC proof-of-possession of the current signing secret —
		// otherwise a license-key-only attacker could hijack the callback
		// channel by re-registering with their own callback_url. When we
		// hold a secret (force_register() after URL drift / license flip),
		// sign `v1:{ts}:register:{callback_url}` with the RAW secret bytes
		// (the stored value is the base64 wire form). First registrations
		// have no secret — the platform accepts license auth + callback-
		// host binding for those. A copy registers as a first registration
		// on instruction, so the secret it inherited is treated as absent.
		$secret_b64 = $as_first_registration ? '' : (string) $this->store->signing_secret();

		// MOVE-NO-SECRET (move contract §10): a move this install cannot sign —
		// its WordPress security keys changed, as a managed host's migration
		// does, so the secret sealed with them no longer opens. It carries no
		// proof and names the OLD address's challenge URL instead: the platform
		// moves the site only once the old address shows the site left it (it
		// no longer resolves, redirects here, or now serves this install).
		$without_secret = $is_move && '' === $secret_b64;
		if ( $without_secret ) {
			$old_challenge_url = (string) $this->store->registered_challenge_url();

			// Nothing on file says where the site was registered: the platform
			// could not verify the move, so it is not sent.
			if ( '' === $old_challenge_url ) {
				$this->outcomes->fail_move( 'not_verified' );
				return false;
			}

			$migrate_from['without_secret'] = true;
			$migrate_from['challenge_url']  = $old_challenge_url;
		}

		if ( '' !== $secret_b64 ) {
			$ts  = (string) time();
			$raw = base64_decode( $secret_b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding the stored signing secret's wire form, not obfuscation.

			$headers['X-Automator-Timestamp'] = $ts;
			$headers['X-Automator-Signature'] = 'v1=' . hash_hmac(
				'sha256',
				"v1:{$ts}:register:{$callback_url}",
				false === $raw ? $secret_b64 : $raw
			);
		}

		// `site_id` (our local UUID) is sent per the Contract A wire shape,
		// but the platform IGNORES the body field — SiteController::register()
		// resolves site_id from `$request->attributes` (the normalized
		// Site-Name), never `$request->input('site_id')`. Kept in the body
		// for wire-shape completeness / forward-compat; it drives nothing
		// server-side today. `site_uuid` (the platform-issued identity) and
		// `migrate_from` are only sent when we actually have them — an empty
		// value would read as a claim rather than an omission.
		$body = array(
			'site_id'          => $this->identity->ensure(),
			'callback_url'     => $callback_url,
			'plugin_version'   => $this->wire->plugin_version(),
			'environment_type' => $this->wire->environment_type(),
		);

		$site_uuid = (string) $this->store->site_uuid();
		if ( '' !== $site_uuid ) {
			$body['site_uuid'] = $site_uuid;
		}

		if ( null !== $migrate_from ) {
			$body['migrate_from'] = $migrate_from;
		}

		// C1 (ClickUp 868jw791t) + wire contract W1 — EVERY register carries an
		// attempt so the platform can run the requester-bound host-control
		// challenge: a random attempt secret sent once over TLS, plus this
		// install's dedicated challenge endpoint. For a first registration that
		// is the only way in. For a site the platform knows, it is the way back
		// when the M4 proof above is missing or no longer matches (the secret
		// was lost, rotated away, or never this site's): the platform answers
		// 202 and runs a recovery instead of refusing the same request forever.
		// A valid proof answers 200 and the attempt is ignored (and cleared
		// below) — except for a move, which the platform proves at the new
		// address before it moves anything (L199). Reuse a pending attempt on
		// retry so both requests converge on ONE attempt; it carries the same
		// intention (checked above).
		$attempt                = $this->challenge->pending_attempt() ?? $this->challenge->begin_attempt( $is_move, $without_secret );
		$body['attempt_id']     = $attempt['attempt_id'];
		$body['attempt_secret'] = $attempt['attempt_secret'];
		$body['challenge_url']  = $this->wire->challenge_url();

		// The attempt is on file from here, so a concurrent request's tick sees
		// it pending. Until the platform answers this register it cannot redeem
		// the attempt, and a poll now would read the 404 as "unknown attempt"
		// and abandon it. Hold polls for the round trip; the 202 releases them.
		$this->challenge->hold_polls_until( time() + Platform_Http::TIMEOUT_SECONDS );

		$response = (array) $this->http->post( $this->wire->register_url(), $body, $headers );

		// The platform wraps every response in a `{requestID, statusCode,
		// data: {...}}` envelope per Contract A. SUCCESS IS THE ENVELOPE —
		// 200 with a `data` payload — never the presence of `signing_secret`:
		// re-registering a site the platform already knows deliberately
		// returns no secret, and reading that as failure would stamp a
		// backoff and re-POST forever (spec 2026-09-01 §9).
		$answer  = new Register_Answer( $response );
		$status  = $answer->status();
		$payload = $answer->data();

		// C1 — 202 means the platform accepted the attempt and the challenge
		// handshake has started: a first registration, a recovery of a site it
		// knows (W2), or a move (L199) — nothing has moved yet. Not a failure
		// (don't stamp a backoff) and not yet registered: the attempt is
		// already stored, and the next register_if_needed tick polls
		// /sites/register/complete to redeem the secret once the out-of-band
		// challenge proves out — starting at once, or once the platform's
		// Retry-After ends: while its challenge workers are backed up, a poll
		// before its first try can only read a 425.
		if ( 202 === $status ) {
			$retry_after = $answer->retry_after();
			$this->challenge->hold_polls_until( 0 < $retry_after ? time() + $retry_after : 0 );
			// The wait ends, the refusal ladder does not: the attempt can still
			// end unconfirmed, and a site the platform never reaches must climb.
			$this->backoff->clear_window();
			$this->schedule->sync();
			return false;
		}

		if ( 200 !== $status || array() === $payload ) {
			$this->handle_refused_register( $answer, $is_drift, $is_move );
			return false;
		}

		// Success path — clear any prior backoff so a future re-register
		// (e.g. after drift) isn't blocked by stale state. A direct 200 (a
		// valid M4 proof, or the gate-off platform) completes the registration
		// here, so the attempt this request carried is now stale.
		$this->backoff->clear_backoff();
		$this->challenge->clear_attempt();
		$this->schedule->sync();
		$this->recorder->persist_registration( $payload, $callback_url );

		return true;
	}

	/**
	 * Reconcile a register the platform did not accept — any answer but a
	 * 202 or a 200 envelope with a payload, a transport failure included.
	 * In order: the orphaned-site signal, the typed-error signal, the upgrade
	 * marker, the stale attempt and its cron event, the backoff, the generic
	 * failed signal, and — for a move — why the move ended.
	 *
	 * @since 8.0.0
	 *
	 * @param Register_Answer $answer   The platform's answer.
	 * @param bool            $is_drift Whether a drift check sent the
	 *        register — classifies the backoff.
	 * @param bool            $is_move  Whether the register asked for a
	 *        move.
	 *
	 * @return void
	 */
	private function handle_refused_register( Register_Answer $answer, bool $is_drift, bool $is_move = false ): void {

		$status = $answer->status();

		// Orphaned-site state: the platform knows this site but we hold no
		// secret to prove with — DB restore, wiped options, or AUTH_KEY
		// rotation (see Site_Credentials_Store::seal()) — and it answered
		// this secret-less register with 401 instead of starting its
		// host-control challenge. A platform running the challenge answers
		// 202 here and recovers the site once it proves control of its
		// host (W2); a 401 means the platform is not running it, so this
		// install cannot prove itself and keeps retrying after the backoff.
		// The plugin never mints itself a secret: the platform keys sites by
		// (license, Site-Name), so only the challenge, or support releasing
		// the Site row, can let a license-key holder back in. Fire a
		// dedicated signal + a forced log line so support tooling can
		// surface it.
		if ( 401 === $status && '' === (string) $this->store->signing_secret() ) {
			Dispatcher::action( 'automator_platform_site_register_orphaned', $answer->to_array() );
			automator_log(
				'Platform site registration refused (401) for an install holding no signing secret: the platform already knows this site and did not start the site confirmation that recovers it. Registration is retried after the backoff; if this persists, contact support.',
				'Platform_Site_Registration',
				true
			);
		}

		$error_type = $answer->type();

		// Platform-refused states that no retry can clear on its own —
		// each gets a dedicated signal plus a forced log line naming the
		// recovery step. Unknown error types fall through to the generic
		// `automator_platform_site_register_failed` below.
		if ( isset( self::ERROR_HANDLING[ $error_type ] ) ) {
			$handling = self::ERROR_HANDLING[ $error_type ];
			Dispatcher::action( $handling['signal'], $answer->to_array() );
			automator_log( $handling['log'], 'Platform_Site_Registration', true );
		}

		if ( 'PLUGIN_UPGRADE_REQUIRED' === $error_type ) {
			$this->outcomes->record_upgrade_required();
		}

		// A non-2xx register did NOT start a challenge handshake, so any C1
		// attempt begun for this request is stale — drop it so the next tick
		// respects the backoff window instead of polling completion for an
		// attempt the platform never accepted. (Only a 202 keeps the attempt.)
		$this->challenge->clear_attempt();
		$this->schedule->sync();

		// A register the platform answered without the challenge looked the
		// host up then, so a name that has no address yet is refused here as
		// it is at register/complete, and passes by itself the same way: the
		// refusal ladder, never held. A move's stays the admin's answer.
		// A host only its A-label form reaches is a property of the address:
		// held as the same refusal is at register/complete, until the address
		// changes or an admin re-checks, never retried up the ladder.
		if ( $answer->is_unresolved_host() && ! $is_move ) {
			$this->backoff->stamp_refusal_backoff( $answer->retry_after() );
			$this->outcomes->record_unresolved_host( $answer, $this->wire->challenge_url() );
		} elseif ( $answer->is_unpinnable_host() ) {
			$this->backoff->stamp_backoff_for_failure( $answer, $is_drift );
			$this->outcomes->record_host_refusal( $status, $error_type, $answer->description(), $answer->reason() );
		} else {
			$this->backoff->stamp_backoff_for_failure( $answer, $is_drift );
			$this->outcomes->record_failure(
				400 <= $status && $status < 500 && 429 !== $status ? Registration_Status::REFUSED : Registration_Status::RETRYING,
				$answer,
				$status
			);
		}
		Dispatcher::action( 'automator_platform_site_register_failed', $answer->to_array() );

		if ( $is_move ) {
			$this->outcomes->fail_move( $this->outcomes->register_move_refusal( $error_type ), $answer->message() );
		}
	}

	/**
	 * Whether an earlier answer to the site-address question stands against
	 * this register, which is then refused here with no platform call and
	 * nothing changed:
	 *
	 *  - a pending attempt begun for the other intention — a move when this
	 *    register is not one, or the reverse. The platform completes an
	 *    attempt id as the intention it stored first.
	 *  - for a move, a copy the admin already decided. Until the copy
	 *    registers, the secret on file is the SOURCE site's: a move signed
	 *    with it would carry the source's row here, and the copy block would
	 *    then lift without quarantining anything.
	 *
	 * @since 8.0.0
	 *
	 * @param bool $is_move Whether this register asks for a move.
	 *
	 * @return bool True when the register must not be sent.
	 */
	public function refuses_other_intention( bool $is_move ): bool {

		$other_pending = null !== $this->challenge->pending_attempt() && $is_move !== $this->challenge->is_move();
		$copy_decided  = $is_move && ( new Copy_Quarantine_State() )->is_blocking();

		if ( ! $other_pending && ! $copy_decided ) {
			return false;
		}

		automator_log( 'A site registration was not sent: an earlier answer to the site-address question is still being confirmed with the platform.', 'Platform_Site_Registration', false );

		return true;
	}
}
