<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

/**
 * The pause between registration attempts. A failed register opens a window and a
 * registration that settles clears it: 60 seconds for a transient failure, a
 * ladder for a refusal that stands ({@see REFUSAL_LADDER_SECONDS}: ten minutes
 * three times, then half an hour, then an hour), two quick rungs ahead of it
 * for a license-level 401 right after a drift, and longer when the platform says
 * so (Retry-After). A window is lengthened, never shortened.
 *
 * The ladder counts refusals in a row — at register, at completion, an attempt
 * the platform could not confirm, a repeated supersession — until the
 * registration settles or someone with the authority to say "try now" clears
 * the backoff: a license-status change, an admin re-check, an answer to the
 * site-address notice ({@see clear_backoff()}). An attempt the platform
 * accepted (a 202) ends the wait but not the ladder ({@see clear_window()}):
 * the attempt can still end unconfirmed.
 *
 * Owns the options that hold it: when the last attempt was, how long to wait, how
 * many drift attempts have walked the quick rungs, and how many refusals in a
 * row have walked the ladder.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Registration_Backoff {

	/**
	 * Default backoff for transient failures (5xx / network) — short
	 * enough that the next page load picks up the retry.
	 */
	public const BACKOFF_SECONDS = 60;

	/**
	 * The first rung of the refusal ladder, and the window a re-verification
	 * holds. 401 / 403 / 422 from `/sites/register` indicate either a license
	 * issue or an orphan Site row blocking re-registration — neither resolves
	 * on page-reload alone, so retrying every 60s just spams the platform +
	 * clogs the user's debug log. Ten minutes, so a cause fixed somewhere else
	 * (the license's site list, a firewall rule) clears without an
	 * administrator re-checking. The next license-status flip cancels the
	 * backoff via {@see clear_backoff()} so the user can recover without
	 * waiting out the window.
	 */
	public const PERMANENT_BACKOFF_SECONDS = 10 * MINUTE_IN_SECONDS;

	/**
	 * The window after each refusal in a row: three at ten minutes, so a cause
	 * fixed elsewhere is picked up within the half hour, then half an hour,
	 * then an hour, which repeats — a refusal that stands costs the platform
	 * one register an hour, not six.
	 *
	 * @since 8.0.0
	 */
	public const REFUSAL_LADDER_SECONDS = array( self::PERMANENT_BACKOFF_SECONDS, self::PERMANENT_BACKOFF_SECONDS, self::PERMANENT_BACKOFF_SECONDS, 30 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS );

	/**
	 * Quick rungs for drift re-registers that hit a license-level 401 — the
	 * EDD → platform site_names sync can race the activation write, so the
	 * next few minutes are when a retry pays off (spec Decision 14). A license
	 * still refused after them is a refusal that stands, and walks
	 * {@see REFUSAL_LADDER_SECONDS}.
	 *
	 * @since 8.0.0
	 */
	private const DRIFT_LADDER_SECONDS = array( 30, 120 );

	private const OPTION_LAST_ATTEMPT   = 'automator_site_register_last_attempt_at';
	private const OPTION_LAST_BACKOFF   = 'automator_site_register_last_backoff';
	private const OPTION_DRIFT_ATTEMPTS = 'automator_site_register_drift_attempts';
	private const OPTION_REFUSALS       = 'automator_site_register_refusals';

	/**
	 * Whether the current attempt is still within the backoff window
	 * stamped by the previous failure. Differentiated by failure class
	 * (drift 401 → the quick rungs, then the refusal ladder; other 4xx → the
	 * refusal ladder; everything else → 60s) so a permanent-state error
	 * doesn't spam the platform every page load.
	 *
	 * @return bool
	 */
	public function is_in_backoff_window(): bool {

		return time() < $this->backoff_ends_at();
	}

	/**
	 * When the last failed attempt was made, as a unix timestamp; 0 when none
	 * is on file.
	 *
	 * @since 8.0.0
	 *
	 * @return int
	 */
	public function last_attempt_at(): int {

		return (int) automator_get_option( self::OPTION_LAST_ATTEMPT, 0 );
	}

	/**
	 * When the standing backoff window ends, as a unix timestamp — in the past
	 * when none stands.
	 *
	 * @since 8.0.0
	 *
	 * @return int
	 */
	public function backoff_ends_at(): int {

		return $this->last_attempt_at()
			+ (int) automator_get_option( self::OPTION_LAST_BACKOFF, self::BACKOFF_SECONDS );
	}

	/**
	 * Stamp the appropriate backoff window after a failed registration.
	 * Reads the platform's response envelope to classify:
	 *
	 *  - drift re-register + statusCode 401 → the quick rungs
	 *    ({@see DRIFT_LADDER_SECONDS}), then the refusal ladder. The license
	 *    moved (free → Pro, or a renewal) and the platform's copy of
	 *    `site_names` may not have caught up yet, so the next few minutes are
	 *    exactly when a retry pays off.
	 *
	 *  - any other statusCode 4xx → a refusal: the next rung of
	 *    {@see REFUSAL_LADDER_SECONDS}. License issue, expired registration
	 *    HMAC, or orphan Site row blocking re-registration. Doesn't resolve via
	 *    page-reload alone.
	 *
	 *  - everything else (5xx, transport, malformed) → transient (60s).
	 *
	 * @param Register_Answer $answer   The platform's answer.
	 * @param bool            $is_drift Whether the failed dispatch came
	 *        from a drift re-register — only that path takes the quick rungs.
	 *
	 * @return void
	 */
	public function stamp_backoff_for_failure( Register_Answer $answer, bool $is_drift = false ): void {

		$status = $answer->status();

		if ( 400 > $status || 500 <= $status ) {
			$this->stamp_backoff( max( self::BACKOFF_SECONDS, $answer->retry_after() ) );
			return;
		}

		$attempts = max( 0, (int) automator_get_option( self::OPTION_DRIFT_ATTEMPTS, 0 ) );
		if ( $is_drift && 401 === $status && $attempts < count( self::DRIFT_LADDER_SECONDS ) ) {
			automator_update_option( self::OPTION_DRIFT_ATTEMPTS, $attempts + 1 );
			$this->stamp_backoff( max( self::DRIFT_LADDER_SECONDS[ $attempts ], $answer->retry_after() ) );
			return;
		}

		// Server-directed backoff: the platform may LENGTHEN the window (a rate
		// limit, an incident, an upgrade-required refusal) but never shorten it.
		$this->stamp_refusal_backoff( $answer->retry_after() );
	}

	/**
	 * Open the window the next refusal in a row earns — the next rung of
	 * {@see REFUSAL_LADDER_SECONDS}, its last rung repeating — and count the
	 * refusal. The platform may lengthen it (Retry-After), never shorten it.
	 *
	 * @since 8.0.0
	 *
	 * @param int $retry_after Seconds the platform asked for; 0 for none.
	 *
	 * @return void
	 */
	public function stamp_refusal_backoff( int $retry_after = 0 ): void {

		$this->stamp_backoff( max( $this->next_refusal_rung(), $retry_after ) );
		$this->count_refusal();
	}

	/**
	 * {@see stamp_refusal_backoff()}, unless the window already standing ends
	 * later: a window is lengthened, never shortened, and a refusal that opens
	 * no window does not move the ladder on.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function hold_refusal_backoff(): void {

		if ( $this->backoff_ends_at() > time() + $this->next_refusal_rung() ) {
			return;
		}

		$this->stamp_refusal_backoff();
	}

	/**
	 * Open a backoff window of the given length, starting now.
	 *
	 * @param int $duration Seconds.
	 *
	 * @return void
	 */
	public function stamp_backoff( int $duration ): void {

		automator_update_option( self::OPTION_LAST_ATTEMPT, time() );
		automator_update_option( self::OPTION_LAST_BACKOFF, $duration );
	}

	/**
	 * Hold a backoff window of the given length: open one, starting now,
	 * unless the window already standing ends later. A window is lengthened,
	 * never shortened.
	 *
	 * @since 8.0.0
	 *
	 * @param int $duration Seconds.
	 *
	 * @return void
	 */
	public function hold_backoff( int $duration ): void {

		if ( $this->backoff_ends_at() > time() + $duration ) {
			return;
		}

		$this->stamp_backoff( $duration );
	}

	/**
	 * Clear stamped backoff state: the window, the drift attempt counter and
	 * the refusal ladder, so the next failure starts from the first rung.
	 * Called when a register is answered 200 and when someone with the
	 * authority to say "try now" says so — a license-status flip, an admin
	 * re-check, an answer to the site-address notice — so the user never waits
	 * out a window for a cause they fixed.
	 *
	 * @return void
	 */
	public function clear_backoff(): void {

		$this->clear_window();
		$this->forget_refusals();
	}

	/**
	 * End the wait, keep the refusal ladder: the platform accepted an attempt
	 * (a 202), so there is nothing to wait for while it runs, but it can still
	 * end unconfirmed — a site the platform cannot reach must climb the ladder
	 * like any refusal that stands. The drift counter is cleared: the drift's
	 * register got through.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function clear_window(): void {

		automator_delete_option( self::OPTION_LAST_ATTEMPT );
		automator_delete_option( self::OPTION_LAST_BACKOFF );
		automator_delete_option( self::OPTION_DRIFT_ATTEMPTS );
	}

	/**
	 * Start the refusal ladder again without ending a window that stands: a
	 * completed challenge settled the registration, and a re-verification's
	 * window must outlive its own successful recovery.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_refusals(): void {

		automator_delete_option( self::OPTION_REFUSALS );
	}

	/**
	 * @return int The window the next refusal in a row earns.
	 */
	private function next_refusal_rung(): int {

		$refusals = (int) automator_get_option( self::OPTION_REFUSALS, 0 );

		return self::REFUSAL_LADDER_SECONDS[ max( 0, min( $refusals, count( self::REFUSAL_LADDER_SECONDS ) - 1 ) ) ];
	}

	/**
	 * @return void
	 */
	private function count_refusal(): void {

		automator_update_option( self::OPTION_REFUSALS, (int) automator_get_option( self::OPTION_REFUSALS, 0 ) + 1 );
	}
}
