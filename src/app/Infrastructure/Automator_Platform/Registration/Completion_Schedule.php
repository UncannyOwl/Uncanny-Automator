<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Challenge;

/**
 * Keeps the completion poll driving itself: while a registration attempt is pending
 * it owns exactly one scheduled WP-Cron event, on the ladder rung the attempt is on;
 * with no attempt it owns none.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Completion_Schedule {

	/**
	 * WP-Cron hook that finishes a pending C1 handshake. Wired in
	 * `Application_Bootstrap::wire_site_registration()` to
	 * {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::register_if_needed()}; scheduled by
	 * {@see sync()} for as long as an attempt is pending.
	 *
	 * @since 8.0.0
	 */
	public const CRON_COMPLETE = 'automator_platform_complete_registration';

	/**
	 * @var Registration_Challenge
	 */
	private $challenge;

	/**
	 * @param Registration_Challenge $challenge The attempt on file and its poll ladder.
	 */
	public function __construct( Registration_Challenge $challenge ) {

		$this->challenge = $challenge;
	}

	/**
	 * Keep the completion poll driving itself: while an attempt is pending it
	 * owns exactly one scheduled {@see CRON_COMPLETE} event, sitting on the
	 * ladder rung the attempt is currently on; with no attempt it owns none.
	 *
	 * WP-Cron only spawns when an event is DUE, and the nearest events this
	 * plugin schedules are 15 minutes and 6 hours out while an attempt lives
	 * {@see Registration_Challenge::ATTEMPT_TTL} seconds. Without an event of
	 * its own, a 202 leaves completion entirely dependent on an admin loading
	 * another wp-admin page — the `init@20` entry point does nothing on a REST
	 * request, which is what the OAuth connect button is — so a proven attempt
	 * can expire unredeemed and fall into the ten-minute unproven backoff.
	 *
	 * The event is cleared before it is re-added rather than left alone:
	 * `wp_schedule_single_event()` refuses a duplicate within 10 minutes of an
	 * identical one, which is wider than the whole poll ladder.
	 *
	 * @return void
	 */
	public function sync(): void {

		$scheduled = wp_next_scheduled( self::CRON_COMPLETE );

		if ( null === $this->challenge->pending_attempt() ) {
			if ( false !== $scheduled ) {
				wp_clear_scheduled_hook( self::CRON_COMPLETE );
			}

			return;
		}

		// A fresh attempt polls immediately (next_poll_at 0), so the event is
		// due now — the next request of any kind spawns cron and finishes it.
		$when = max( $this->challenge->next_poll_at(), time() );
		if ( (int) $scheduled === $when ) {
			return;
		}

		if ( false !== $scheduled ) {
			wp_clear_scheduled_hook( self::CRON_COMPLETE );
		}

		wp_schedule_single_event( $when, self::CRON_COMPLETE );
	}
}
