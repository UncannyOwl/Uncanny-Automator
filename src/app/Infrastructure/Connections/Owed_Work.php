<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Work the site still owes itself — a connection sync, an adoption — kept
 * until it succeeds, and retried on its own.
 *
 * Durable (three options, so it survives the request and a cache flush) and
 * self-driving: while the work is owed it owns exactly one WP-Cron single
 * event on its hook, due now the first time and further out after each
 * failure (1, 5, 15 minutes, then hourly). A signal that the work is owed
 * again never brings a backed-off retry forward, so a burst of signals costs
 * one attempt, not one per signal. The ladder starts again only once the work
 * settles.
 *
 * When the next attempt is due is also kept in an option ({@see is_due()}), so
 * work is never left to WP-Cron alone: a request that can afford the work (a
 * wp-admin page load) runs it when it is due, on a site whose WP-Cron is
 * disabled or never spawned too.
 *
 * The event is cleared before it is re-added rather than left alone:
 * `wp_schedule_single_event()` refuses a duplicate within 10 minutes of an
 * identical one, which is wider than the first rungs.
 *
 * @since 8.0.0
 */
final class Owed_Work {

	/**
	 * Seconds to wait after the 1st, 2nd, 3rd and every later failure.
	 */
	private const LADDER = array( 60, 300, 900, 3600 );

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $cron_hook;

	/**
	 * @param string $name      Names the work's options: `automator_platform_{$name}_*`.
	 * @param string $cron_hook The WP-Cron hook that performs the work.
	 */
	public function __construct( string $name, string $cron_hook ) {

		$this->name      = $name;
		$this->cron_hook = $cron_hook;
	}

	/**
	 * Record that the work is owed and make sure an attempt is scheduled —
	 * now, unless a retry is already waiting.
	 *
	 * @return void
	 */
	public function owe(): void {

		if ( ! $this->is_owed() ) {
			automator_update_option( $this->option( 'owed_at' ), time() );
			automator_update_option( $this->option( 'attempts' ), 0 );
			automator_update_option( $this->option( 'next_at' ), time() );
		}

		if ( false === wp_next_scheduled( $this->cron_hook ) ) {
			$this->schedule( max( time(), $this->next_at() ) );
		}
	}

	/**
	 * An attempt failed: the work stays owed and the next attempt waits the
	 * next rung of the ladder.
	 *
	 * @return void
	 */
	public function failed(): void {

		if ( ! $this->is_owed() ) {
			automator_update_option( $this->option( 'owed_at' ), time() );
		}

		$attempts = (int) automator_get_option( $this->option( 'attempts' ), 0 ) + 1;
		$next_at  = time() + self::LADDER[ min( $attempts, count( self::LADDER ) ) - 1 ];

		automator_update_option( $this->option( 'attempts' ), $attempts );
		automator_update_option( $this->option( 'next_at' ), $next_at );

		$this->schedule( $next_at );
	}

	/**
	 * The work is done: forget it and its event.
	 *
	 * @return void
	 */
	public function settle(): void {

		automator_delete_option( $this->option( 'owed_at' ) );
		automator_delete_option( $this->option( 'attempts' ) );
		automator_delete_option( $this->option( 'next_at' ) );
		wp_clear_scheduled_hook( $this->cron_hook );
	}

	/**
	 * @return bool
	 */
	public function is_owed(): bool {

		return 0 < (int) automator_get_option( $this->option( 'owed_at' ), 0 );
	}

	/**
	 * Whether the work is owed and has failed through the ladder's quick rungs
	 * (1, 5 and 15 minutes): retrying on its own has not done it, and it now
	 * waits an hour between attempts.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_overdue(): bool {

		return $this->is_owed() && (int) automator_get_option( $this->option( 'attempts' ), 0 ) >= count( self::LADDER ) - 1;
	}

	/**
	 * Whether the work is owed and its next attempt is due: at once when it was
	 * first owed, after the rung of the ladder once an attempt failed. Read
	 * from the option, never from WP-Cron.
	 *
	 * @return bool
	 */
	public function is_due(): bool {

		return $this->is_owed() && time() >= $this->next_at();
	}

	/**
	 * @return int When the next attempt is due, as a unix timestamp; 0 when unknown.
	 */
	private function next_at(): int {

		return (int) automator_get_option( $this->option( 'next_at' ), 0 );
	}

	/**
	 * @param int $when Unix timestamp.
	 *
	 * @return void
	 */
	private function schedule( int $when ): void {

		wp_clear_scheduled_hook( $this->cron_hook );
		wp_schedule_single_event( $when, $this->cron_hook );
	}

	/**
	 * @param string $suffix The option's role.
	 *
	 * @return string
	 */
	private function option( string $suffix ): string {

		return 'automator_platform_' . $this->name . '_' . $suffix;
	}
}
