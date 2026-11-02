<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Challenge;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;

/**
 * Redeems a registration attempt the platform accepted with a 202. Polls
 * register/complete on the attempt's ladder and reads the answer: the secret (stored
 * before the attempt is cleared), a refusal that ends the attempt, an attempt the
 * platform superseded, or one that was never proven.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Completion_Poll {

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
	 * @var Registration_Recorder
	 */
	private $recorder;

	/**
	 * @var Registration_Backoff
	 */
	private $backoff;

	/**
	 * @var Registration_Outcomes
	 */
	private $outcomes;

	/**
	 * @var Reverification_Ledger
	 */
	private $ledger;

	/**
	 * @var Completion_Schedule
	 */
	private $schedule;

	/**
	 * @param Site_Credentials_Store $store     Holds the secret the redeemed attempt stores.
	 * @param object                 $http      Collaborator with a `post(string, array, array): array` shape.
	 * @param Registration_Challenge $challenge The attempt on file and its proofs.
	 * @param Registration_Wire      $wire      Where the completion goes and what it says.
	 * @param Registration_Recorder  $recorder  Accepts the registration the completion answers with.
	 * @param Registration_Backoff   $backoff   The pause between attempts.
	 * @param Registration_Outcomes  $outcomes  How attempts ended.
	 * @param Reverification_Ledger  $ledger    Marks a superseded attempt.
	 * @param Completion_Schedule    $schedule  The cron event that finishes a pending attempt.
	 */
	public function __construct(
		Site_Credentials_Store $store,
		object $http,
		Registration_Challenge $challenge,
		Registration_Wire $wire,
		Registration_Recorder $recorder,
		Registration_Backoff $backoff,
		Registration_Outcomes $outcomes,
		Reverification_Ledger $ledger,
		Completion_Schedule $schedule
	) {

		$this->store     = $store;
		$this->http      = $http;
		$this->challenge = $challenge;
		$this->wire      = $wire;
		$this->recorder  = $recorder;
		$this->backoff   = $backoff;
		$this->outcomes  = $outcomes;
		$this->ledger    = $ledger;
		$this->schedule  = $schedule;
	}

	/**
	 * C1 — redeem a proven attempt (a first registration or a recovery) for the
	 * winning signing secret. Runs on the register_if_needed tick AFTER the
	 * register answered 202: signs the domain-separated completion proof with
	 * the stored attempt secret and POSTs it to /sites/register/complete.
	 * Classified on (statusCode, error.type) — wire contract W3:
	 *
	 *  - 200 → store the secret + clear the attempt (registered). A recovery
	 *          (`recovered: true`) or a move (`migrated: true`, L199) carries
	 *          the existing site_uuid and a new secret that replaces the one
	 *          on file.
	 *  - 401 VERIFICATION_ERROR / 404 → the completion proof failed, or the
	 *          attempt is unknown, expired or bound elsewhere. Can never become
	 *          a 200 — abandon the attempt on the ten-minute window
	 *          ({@see abandon_attempt()}).
	 *  - 410 → the secret this attempt set out to replace changed, or a row
	 *          appeared this attempt did not create. Can never become a 200,
	 *          but a fresh register now turns into a recovery — retire the
	 *          attempt and mark it superseded: the first supersession
	 *          re-registers after the short window, a repeat with no
	 *          registration succeeding in between after the ten-minute one, and
	 *          neither shortens a window that already stands
	 *          ({@see retire_superseded_attempt()}).
	 *  - anything else → transient: 425 (not yet proven), the license
	 *          middleware's 401 (a license problem or its throttle, with
	 *          Retry-After), 429, 5xx, a transport error or a non-envelope
	 *          body (status 0). Keep the attempt — the recovery credential —
	 *          and poll again when the ladder and Retry-After allow; the
	 *          attempt's own expiry still bounds it.
	 *
	 * @param array{attempt_id: string, attempt_secret: string} $attempt Pending attempt.
	 *
	 * @return bool True when the secret was redeemed, false while pending / abandoned.
	 */
	public function complete_challenge( array $attempt ): bool {

		$completed = $this->poll_completion( $attempt );

		// Whichever exit the poll took — redeemed, abandoned, or pushed to the
		// next ladder rung — the self-driving cron follows the attempt from
		// this one seam, so no branch can forget it.
		$this->schedule->sync();

		return $completed;
	}

	/**
	 * The completion poll itself. Split from {@see complete_challenge()} only so
	 * the cron bookkeeping has a single seam.
	 *
	 * @param array{attempt_id: string, attempt_secret: string} $attempt Pending attempt.
	 *
	 * @return bool True when the secret was redeemed, false while pending / abandoned.
	 */
	private function poll_completion( array $attempt ): bool {

		$nonce   = bin2hex( random_bytes( 16 ) );
		$headers = $this->wire->headers();

		$headers['X-Automator-Signature'] = 'v1=' . $this->challenge->sign_completion(
			$attempt['attempt_secret'],
			$attempt['attempt_id'],
			$nonce
		);

		// From here the platform may complete the attempt whatever we read
		// back; a move must not be cancelled until an answer says it did not
		// ({@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::can_cancel_move()}).
		$this->challenge->mark_completion_sent();

		$response = (array) $this->http->post(
			$this->wire->complete_url(),
			array(
				'attempt_id'       => $attempt['attempt_id'],
				'completion_nonce' => $nonce,
			),
			$headers
		);

		$answer  = new Register_Answer( $response );
		$status  = $answer->status();
		$payload = $answer->data();

		if ( 200 === $status ) {

			// A 200 is a completion ONLY if it actually carries the winning
			// secret. A non-empty-but-secretless payload is NOT success — the
			// old `array() !== $payload` test would clear the attempt and persist
			// an empty secret, destroying the recovery credential. Keep the
			// attempt and poll again (the platform's completion window is
			// idempotent, so the next tick redeems the same winner).
			$secret = isset( $payload['signing_secret'] ) ? (string) $payload['signing_secret'] : '';
			if ( '' === $secret ) {
				$this->challenge->record_poll();
				return false;
			}

			// No clear_backoff() here: the register that started this attempt
			// already ended the wait on its 202 — except a re-verification,
			// whose ten-minute window must outlive a successful recovery
			// ({@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::reverify()}).
			// The registration settles, so the refusal ladder starts again.
			$this->backoff->forget_refusals();

			// Persist FIRST, then read it back. The attempt is the ONLY thing
			// that lets us re-poll /complete, so it must survive until the secret
			// is durably stored — clearing it before a failed write would strand
			// the site with no secret and no way to retry.
			$this->recorder->persist_registration( $payload, $this->wire->callback_url() );

			if ( $secret !== (string) $this->store->signing_secret() ) {
				// The write did not stick — keep the attempt so the next tick can
				// re-redeem the (idempotent) winner within the completion window.
				// Compared against the redeemed secret, not emptiness: a copy still
				// holds the secret it inherited until this write lands.
				$this->challenge->record_poll();
				return false;
			}

			$this->challenge->clear_attempt();
			return true;
		}

		if ( 410 === $status ) {
			$this->retire_superseded_attempt();
			return false;
		}

		$type = $answer->type();
		if ( ( 400 === $status && 'VALIDATION_ERROR' === $type ) || ( 409 === $status && 'SITE_IS_LOCAL' === $type ) ) {
			if ( $answer->is_unresolved_host() && ! $this->challenge->is_move() ) {
				$this->retry_unresolved_host( $answer, $status );
				return false;
			}

			$this->refuse_host( $answer, $status, $type );
			return false;
		}

		// Move contract §10.4 — every other 409 is final for the attempt: for a
		// move without the secret, the old address is still a live site
		// (SITE_STILL_LIVE) or never answered clearly (OLD_ADDRESS_UNDETERMINED).
		if ( 409 === $status ) {
			$this->refuse_completion( $answer, $type );
			return false;
		}

		if ( 404 === $status || ( 401 === $status && 'VERIFICATION_ERROR' === $answer->type() ) ) {
			$this->abandon_attempt( (string) $status, $status );
			return false;
		}

		// The challenge of this site's own address is over without proving it:
		// no answer that follows can become a 200, so the attempt ends now
		// instead of polling until it expires.
		$progress = $answer->challenge_progress();
		if ( null !== $progress && $progress->is_over() ) {
			$this->abandon_attempt( '' !== $progress->last_outcome() ? $progress->last_outcome() : 'expired', $progress->last_http_status() );
			return false;
		}

		// A 4xx (425 not yet proven, 429, the license middleware's 401) says the
		// platform did not complete the attempt. A 5xx or no answer does not.
		$this->challenge->record_poll( $answer->retry_after(), 400 <= $status && $status < 500, $progress );

		return false;
	}

	/**
	 * L286 — register/complete answered the challenge job's refusal of this
	 * site's host. The registration's refused state: the attempt ends (it can
	 * never become a 200), and the refusal is kept with the platform's
	 * description until the site's address changes or an admin re-checks. It is
	 * the one record of a refused move too: the registration notice tells it
	 * and offers the re-check, and the site-address question waits for it — a
	 * second, generic "the move was refused" ending would only repeat it. A
	 * move's new address that does not resolve yet is held here as well; any
	 * other attempt's is retried ({@see retry_unresolved_host()}).
	 *
	 * @param Register_Answer $answer The completion answer.
	 * @param int             $status Its status.
	 * @param string          $type   Its `error.type`.
	 *
	 * @return void
	 */
	private function refuse_host( Register_Answer $answer, int $status, string $type ): void {

		$this->outcomes->record_host_refusal( $status, $type, $answer->description(), $answer->reason() );
		$this->challenge->clear_attempt();

		Dispatcher::action( 'automator_platform_site_register_host_refused', $answer->to_array() );
		automator_log( 'The Automator platform refused this site\'s address when it confirmed it (' . $status . ' ' . $type . '): ' . $answer->description() . ' Registration waits until the site\'s address changes.', 'Platform_Site_Registration', true );
	}

	/**
	 * register/complete answered that this site's host still had no
	 * address at the challenge's last try (`error.reason`
	 * host_does_not_resolve). A domain that just changed reaches the platform's
	 * resolver minutes later, so unlike the other host refusals this one can
	 * pass by itself: the attempt ends and the site registers again on the
	 * refusal ladder, the platform's Retry-After lengthening a rung. A move is
	 * the exception ({@see refuse_host()}): it is the admin's answer and never
	 * runs again on its own.
	 *
	 * @since 8.0.0
	 *
	 * @param Register_Answer $answer The completion answer.
	 * @param int             $status Its status.
	 *
	 * @return void
	 */
	private function retry_unresolved_host( Register_Answer $answer, int $status ): void {

		$this->challenge->clear_attempt();
		$this->backoff->stamp_refusal_backoff( $answer->retry_after() );
		$this->outcomes->record_unresolved_host( $answer, $this->wire->challenge_url() );

		Dispatcher::action( 'automator_platform_site_register_failed', $answer->to_array() );
		automator_log( 'The Automator platform found no address for this site\'s host at the challenge\'s last try (' . $status . ' ' . $answer->type() . ', ' . $answer->reason() . '): ' . $answer->description() . ' Registration is tried again in ' . ( $this->backoff->backoff_ends_at() - time() ) . ' seconds.', 'Platform_Site_Registration', true );
	}

	/**
	 * register/complete refused the attempt for good (a 409 other than the host
	 * refusal). The attempt ends — its id is never sent again — and a move ends
	 * with why; nothing moved. The next rung of the refusal ladder keeps any
	 * non-move register from looping; in production a move goes back to the
	 * site-address notice, whose next answer clears it.
	 *
	 * @since 8.0.0
	 *
	 * @param Register_Answer $answer The completion answer.
	 * @param string          $type   Its `error.type`.
	 *
	 * @return void
	 */
	private function refuse_completion( Register_Answer $answer, string $type ): void {

		$this->drop_attempt( $this->outcomes->move_refusal( $type ), $answer->message() );
		$this->backoff->stamp_refusal_backoff( $answer->retry_after() );
		$this->outcomes->record_failure( Registration_Status::REFUSED, $answer, 409 );

		Dispatcher::action( 'automator_platform_site_register_failed', $answer->to_array() );
		automator_log( 'The Automator platform refused to complete this site\'s registration (409 ' . $type . '). Nothing was changed.', 'Platform_Site_Registration', true );
	}

	/**
	 * Give up on the in-flight attempt when it aged out UNPROVEN.
	 *
	 * An attempt that aged out unproven is a failure, not a clean slate: the
	 * platform could not reach this site's challenge endpoint for the whole
	 * attempt lifetime. Back off like any other failed registration —
	 * re-registering on the very next tick just starts another unprovable
	 * handshake, forever.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function abandon_if_expired(): void {

		if ( $this->challenge->attempt_expired() ) {
			$this->abandon_attempt( 'expired' );
		}
	}

	/**
	 * Give up on an in-flight C1 attempt that could not be proven or redeemed
	 * (it expired, its completion proof failed, or the platform no longer
	 * knows it) and back off before registering again.
	 *
	 * Without the backoff an unprovable site loops forever: abandon → re-register
	 * on the next tick → fresh 202 → poll out another attempt lifetime → abandon.
	 * That is the steady state for any site whose challenge endpoint the platform
	 * cannot reach (a REST-blocking security plugin, bot protection, staging
	 * basic-auth, an IP allowlist), so it walks the refusal ladder — ten
	 * minutes three times, then longer — and a license-status flip or an admin
	 * answer clears it.
	 *
	 * An abandoned move moved nothing, and is never retried on its own: in
	 * production the drift gate hands the site back to the notice, which asks
	 * again and says why ('unconfirmed').
	 *
	 * @param string $reason 'expired'; the completion status ('401' / '404');
	 *                       or what the challenge's last try came to, when the
	 *                       platform said no try is coming
	 *                       ({@see Challenge_Progress::is_over()}).
	 * @param int    $status The completion status, or the last try's HTTP
	 *                       status; 0 when none.
	 *
	 * @return void
	 */
	private function abandon_attempt( string $reason, int $status = 0 ): void {

		$this->drop_attempt( 'unconfirmed' );
		$this->schedule->sync();
		$this->backoff->stamp_refusal_backoff();
		$this->outcomes->record_failure(
			Registration_Status::UNREACHABLE,
			new Register_Answer(),
			$status,
			array(
				'challenge_url' => $this->wire->challenge_url(),
				'reason'        => $reason,
			)
		);

		Dispatcher::action( 'automator_platform_site_register_unproven', $reason );
		automator_log(
			'Platform site registration could not be confirmed (' . $reason . '). The platform must be able to reach ' . $this->wire->challenge_url() . ' to confirm this site; registration will be retried later.',
			'Platform_Site_Registration',
			true
		);
	}

	/**
	 * W3 410 — the platform will never redeem this attempt: the secret it set
	 * out to replace changed (another recovery or a rotation won the race), or
	 * a row appeared that this first registration did not create. Unlike an
	 * unprovable attempt this is not the site's fault, and the next register
	 * is exactly what fixes it — the platform turns it into a recovery. So
	 * drop the attempt and mark it superseded ({@see Reverification_Ledger::OPTION_SUPERSEDED_AT}):
	 * until a registration succeeds a register is owed, so a site holding a
	 * secret re-registers instead of short-circuiting as "already registered"
	 * ({@see Reverification_Ledger::is_register_owed()}).
	 *
	 * The first supersession holds the short window; a repeat — the marker is
	 * still set, so no registration has succeeded since — is a refusal that
	 * stands and holds the next rung of the refusal ladder. The window held
	 * here paces every register path, the drift and copy paths included, whose
	 * registers end the wait on their 202: without the ladder, a platform that
	 * keeps answering 410 (two installs answering on one address supersede each
	 * other) would drive one register and one poll a minute. Neither shortens a
	 * window that already stands ({@see Registration_Backoff::hold_backoff()},
	 * {@see Registration_Backoff::hold_refusal_backoff()}).
	 *
	 * A superseded move (the source row changed after the 202, or another row
	 * took the new address) moved nothing. It is not re-registered either: in
	 * production the drift gate runs ahead of {@see Reverification_Ledger::is_register_owed()}, so
	 * the notice asks again and says why ('superseded').
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	private function retire_superseded_attempt(): void {

		$this->drop_attempt( 'superseded' );

		if ( $this->ledger->is_attempt_superseded() ) {
			$this->backoff->hold_refusal_backoff();
		} else {
			$this->backoff->hold_backoff( Registration_Backoff::BACKOFF_SECONDS );
		}

		$this->ledger->mark_superseded();
		$this->outcomes->record_failure( Registration_Status::RETRYING, new Register_Answer(), 410 );

		automator_log( 'The platform superseded this site\'s registration attempt (410). The next registration waits ' . ( $this->backoff->backoff_ends_at() - time() ) . ' seconds.', 'Platform_Site_Registration', false );
	}

	/**
	 * Drop the attempt on file; when it was a move, record why the move ended.
	 *
	 * @since 8.0.0
	 *
	 * @param string $move_failure The reason, should the attempt be a move.
	 * @param string $description  The platform's description of it, if any.
	 *
	 * @return void
	 */
	private function drop_attempt( string $move_failure, string $description = '' ): void {

		if ( $this->challenge->is_move() ) {
			$this->outcomes->fail_move( $move_failure, $description );
		}

		$this->challenge->clear_attempt();
	}
}
