<?php
/**
 * The "cron" command group.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Rows;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Scheduled events and schedules. Event arguments are not shown; they can
 * contain user data.
 */
final class Cron_Commands implements Site_Command_Group {

	private const EVENT_DEFAULTS = array( 'hook', 'next_run_gmt', 'next_run_relative', 'recurrence' );
	private const EVENT_FIELDS   = array( 'hook', 'next_run_gmt', 'next_run_relative', 'recurrence', 'time', 'schedule', 'interval' );

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		return array(
			Command_Definition::reads( 'cron event list', 'List scheduled cron events, soonest first.' )
				->with_fields( self::EVENT_DEFAULTS, self::EVENT_FIELDS, 'Events' )
				->handled_by( array( $this, 'list_events' ) ),
			Command_Definition::reads( 'cron schedule list', 'List the available cron schedules.' )
				->with_fields( array( 'name', 'display', 'interval' ), array( 'name', 'display', 'interval' ), 'Schedules' )
				->handled_by( array( $this, 'list_schedules' ) ),
		);
	}

	/**
	 * The "cron event list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function list_events( array $arguments, array $options ): Command_Output {
		// Read the stored option directly: _get_cron_array() upgrades and saves
		// cron data in an older format, and this command must not write.
		$crons = get_option( 'cron', array() );
		if ( ! is_array( $crons ) ) {
			throw new Command_Error( 'WordPress\'s scheduled events are unreadable, so they cannot be listed.' );
		}
		if ( array() !== $crons && ! isset( $crons['version'] ) ) {
			throw new Command_Error( 'The scheduled events are stored in an older format that WordPress converts the next time cron runs. Try again after the site has loaded a page.' );
		}
		unset( $crons['version'] );

		$now     = time();
		$rows    = array();
		$skipped = 0;
		foreach ( $crons as $time => $hooks ) {
			// Entries WordPress could never run are skipped and declared, not
			// given a 1970 date or a made-up schedule.
			if ( ! Values::is_whole_number( (string) $time ) || (int) $time <= 0 || ! is_array( $hooks ) ) {
				++$skipped;
				continue;
			}
			foreach ( $hooks as $hook => $events ) {
				if ( ! is_string( $hook ) || '' === $hook || ! is_array( $events ) ) {
					++$skipped;
					continue;
				}
				foreach ( $events as $event ) {
					$schedule = $this->schedule( $event );
					if ( null === $schedule ) {
						++$skipped;
						continue;
					}
					list( $schedule, $interval ) = $schedule;

					$rows[] = array(
						'hook'              => $hook,
						'next_run_gmt'      => gmdate( 'Y-m-d H:i:s', (int) $time ),
						'next_run_relative' => $this->interval( (int) $time - $now ),
						'recurrence'        => '' === $schedule ? 'Non-repeating' : $this->interval( $interval ),
						'time'              => (int) $time,
						'schedule'          => '' === $schedule ? false : $schedule,
						'interval'          => '' === $schedule ? false : $interval,
					);
				}
			}
		}

		$overdue = count(
			array_filter(
				$rows,
				static function ( array $row ) use ( $now ): bool {
					return $row['time'] <= $now;
				}
			)
		);
		$once    = count( array_keys( array_column( $rows, 'schedule' ), false, true ) );
		$output  = Command_Output::rows( Rows::sort_by( $rows, 'time' ) )
			->with_stats( sprintf( 'Recurring: %d. One-off: %d. Due or overdue now: %d.', count( $rows ) - $once, $once, $overdue ) );

		return Values::with_skipped( $output, $skipped );
	}

	/**
	 * An event's schedule and interval, the way WordPress stores them: no
	 * schedule (false) for a one-off event, or a schedule name with a whole
	 * number of seconds.
	 *
	 * @param mixed $event Stored event.
	 * @return array{0: string, 1: int}|null The schedule ('' for one-off) and interval, or null when unreadable.
	 */
	private function schedule( $event ): ?array {
		if ( ! is_array( $event ) || ! array_key_exists( 'schedule', $event ) || ! is_array( $event['args'] ?? null ) ) {
			return null;
		}
		if ( false === $event['schedule'] ) {
			return array( '', 0 );
		}
		$interval = Values::whole_number( $event['interval'] ?? null );
		if ( ! is_string( $event['schedule'] ) || '' === $event['schedule'] || null === $interval || 0 === $interval ) {
			return null;
		}

		return array( $event['schedule'], $interval );
	}

	/**
	 * The "cron schedule list" command, shortest interval first.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function list_schedules( array $arguments, array $options ): Command_Output {
		$rows    = array();
		$skipped = 0;
		foreach ( wp_get_schedules() as $name => $schedule ) {
			$interval = is_array( $schedule ) ? Values::whole_number( $schedule['interval'] ?? null ) : null;
			if ( null === $interval || 0 === $interval ) {
				++$skipped;
				continue;
			}
			$rows[] = array(
				'name'     => (string) $name,
				'display'  => Values::text( $schedule['display'] ?? '' ),
				'interval' => $interval,
			);
		}

		return Values::with_skipped( Command_Output::rows( Rows::sort_by( $rows, 'interval' ) ), $skipped );
	}

	/**
	 * Seconds as WP-CLI writes them: the two largest units, such as
	 * "1 day 1 hour", or "now".
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	private function interval( int $seconds ): string {
		if ( $seconds <= 0 ) {
			return 'now';
		}
		$units = array(
			'year'   => 365 * DAY_IN_SECONDS,
			'month'  => 30 * DAY_IN_SECONDS,
			'week'   => WEEK_IN_SECONDS,
			'day'    => DAY_IN_SECONDS,
			'hour'   => HOUR_IN_SECONDS,
			'minute' => MINUTE_IN_SECONDS,
			'second' => 1,
		);
		$names = array_keys( $units );
		foreach ( $names as $index => $name ) {
			$count = intdiv( $seconds, $units[ $name ] );
			if ( 0 === $count ) {
				continue;
			}
			$output = $count . ' ' . $name . ( 1 === $count ? '' : 's' );
			if ( isset( $names[ $index + 1 ] ) ) {
				$next   = $names[ $index + 1 ];
				$second = intdiv( $seconds - $count * $units[ $name ], $units[ $next ] );
				if ( 0 !== $second ) {
					$output .= ' ' . $second . ' ' . $next . ( 1 === $second ? '' : 's' );
				}
			}

			return $output;
		}

		return 'now';
	}
}
