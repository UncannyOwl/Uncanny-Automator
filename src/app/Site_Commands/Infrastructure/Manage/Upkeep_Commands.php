<?php
/**
 * Upkeep commands: maintenance mode, cron, caches, transients and rewrite rules.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Option;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Domain\Time_Budget;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Settings_Commands;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Request_Time_Budget;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Mcp_Settings_Guard;

/**
 * Upkeep as WP-CLI does it, with differences this connection needs:
 *
 * - Cron events are started through WordPress's own cron runner, in a
 *   separate request with no user logged in, as WordPress runs them. Their
 *   code may run for minutes, which no request here could wait for.
 * - Maintenance mode is turned on only for a stated number of minutes, at
 *   most once an hour. WordPress shows its maintenance page to every
 *   request, this connection's included, before any plugin loads, so it
 *   cannot be turned off from here early.
 * - Expired transients are deleted in batches while time allows.
 */
final class Upkeep_Commands implements Site_Command_Group {

	/** WordPress ends maintenance mode by itself after this long. */
	private const MAX_MAINTENANCE_MINUTES = 10;

	/** Shortest wait between two maintenance windows turned on from here. */
	private const MAINTENANCE_COOLDOWN = HOUR_IN_SECONDS;

	/** When maintenance mode was last turned on from here. */
	private const MAINTENANCE_OPTION = 'automator_site_manage_maintenance';

	/** Time for quick changes and answering. */
	private const QUICK_SECONDS = 3.0;

	/**
	 * Hooks whose next event is not moved to now: they would update WordPress
	 * itself, or publish a scheduled post early.
	 *
	 * @var array<string, string>
	 */
	private const NOT_MOVED = array(
		'wp_maybe_auto_update'           => 'it would update WordPress itself, which is not done here. WordPress runs it on its own schedule',
		'wp_version_check'               => 'in WordPress\'s cron runner it also starts WordPress\'s automatic updates, which can update WordPress itself, which is not done here. WordPress runs it on its own schedule',
		'publish_future_post'            => 'it would publish a scheduled post early. Publish the post from Posts in WordPress instead',
		'wp_delete_temp_updater_backups' => 'it would remove the backups WordPress keeps of plugins and themes during updates, which may be the only copy of one. WordPress runs it weekly',
	);

	/** Expired transients deleted per batch. */
	private const TRANSIENT_BATCH = 200;

	/**
	 * Starts the time budget of each command.
	 *
	 * @var callable(): Time_Budget
	 */
	private $budget;

	/**
	 * Use the current request's time budget unless another is supplied.
	 *
	 * @param callable(): Time_Budget|null $budget Starts a command's time budget.
	 */
	public function __construct( ?callable $budget = null ) {
		$this->budget = $budget ?? array( Request_Time_Budget::class, 'start' );
	}

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		return array(
			Command_Definition::changes( 'maintenance-mode activate', 'Show WordPress\'s maintenance page to every visitor for 1 to 10 minutes, after which WordPress ends it by itself. Every request gets that page meanwhile, this connection\'s included, so nothing can turn it off from here before it ends. Allowed once an hour.' )
				->with_option( Command_Option::number( 'minutes', 'Required: how long, from 1 to 10 minutes.', 1, self::MAX_MAINTENANCE_MINUTES ) )
				->handled_by( array( $this, 'activate_maintenance' ) ),
			Command_Definition::changes( 'maintenance-mode deactivate', 'Remove a .maintenance file that is no longer in effect, such as one an interrupted update left behind.' )
				->handled_by( array( $this, 'deactivate_maintenance' ) ),
			Command_Definition::changes( 'cron event run', 'Start cron events through WordPress\'s own cron runner, which runs them in a separate request with no user logged in: every due event with --due-now, or the next event of one hook, which is moved to now first. WordPress\'s automatic updates, its version check that starts them, scheduled post publishing and its update-backup cleanup are not moved. The answer says what was started; check the result with "cron event list" in site_command.' )
				->with_argument( Command_Argument::optional( 'hook', 'Hook name of the event to run now, such as wp_update_plugins. Omit it with --due-now.' ) )
				->with_option( Command_Option::flag( 'due-now', 'Start every event that is due.' ) )
				->handled_by( array( $this, 'run_cron' ) ),
			Command_Definition::changes( 'cache flush', 'Flush the object cache. On a network this flushes every site\'s cache.' )
				->handled_by( array( $this, 'flush_cache' ) ),
			Command_Definition::changes( 'transient delete', 'Delete expired transients from the database, in batches while time allows. Only --expired is supported; transients that have not expired are kept.' )
				->with_option( Command_Option::flag( 'expired', 'Required: delete only expired transients.' ) )
				->handled_by( array( $this, 'delete_transients' ) ),
			Command_Definition::changes( 'rewrite flush', 'Regenerate the rewrite rules, which fixes "page not found" errors after plugins or permalinks change. The .htaccess file is not changed.' )
				->handled_by( array( $this, 'flush_rewrites' ) ),
		);
	}

	/**
	 * The "maintenance-mode activate" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When it cannot be turned on.
	 */
	public function activate_maintenance( array $arguments, array $options ): Command_Output {
		if ( ! isset( $options['minutes'] ) ) {
			throw new Command_Error( 'Say how long: --minutes=<1-10>. While maintenance mode is on, every request to the site, this connection\'s included, gets WordPress\'s maintenance page, so it cannot be turned off from here before it ends. Nothing was changed.' );
		}
		$minutes = (int) $options['minutes'];
		Site_Changes::require_capability( 'manage_options', 'turn on maintenance mode' );
		self::require_cooldown_passed();
		self::require_maintenance_file_free( 'turned on' );
		Site_Changes::require_file_changes( ABSPATH );

		return Change_Session::run(
			$this->budget(),
			static function ( Change_Session $session ) use ( $minutes ): Command_Output {
				$session->budget()->require( self::QUICK_SECONDS, 'Turning on maintenance mode' );
				$session->lock();
				// Again under the lock: another command may have turned it on meanwhile.
				self::require_cooldown_passed();
				if ( file_exists( ABSPATH . '.maintenance' ) ) {
					throw new Command_Error( 'A .maintenance file appeared while this command started, so it was left alone. Nothing was changed. Check with "maintenance-mode status" in site_command.' );
				}

				global $wp_filesystem;
				// WordPress ends maintenance ten minutes after this time, so a shorter
				// stay is written as a start that many minutes earlier.
				$started = time() - ( self::MAX_MAINTENANCE_MINUTES - $minutes ) * MINUTE_IN_SECONDS;
				$target  = trailingslashit( $wp_filesystem->abspath() ) . '.maintenance';
				if ( ! $wp_filesystem->put_contents( $target, '<?php $upgrading = ' . $started . '; ?>', FS_CHMOD_FILE ) ) {
					// A partly written file must not keep the site down.
					if ( $wp_filesystem->exists( $target ) && ! $wp_filesystem->delete( $target ) ) {
						throw new Command_Error( 'WordPress could not write the .maintenance file completely, and could not remove what it wrote. Check the site now: remove the .maintenance file from the WordPress folder if the site shows an error.' );
					}
					throw new Command_Error( 'WordPress could not write the .maintenance file in its folder. Maintenance mode is not on. Nothing was changed.' );
				}
				// AI work may not write this option; site_manage records it itself. The
				// .maintenance file is shared by every site of a network, so is the record.
				Mcp_Settings_Guard::permit(
					self::MAINTENANCE_OPTION,
					static function () {
						update_site_option( self::MAINTENANCE_OPTION, time() );
					}
				);

				return Command_Output::text(
					sprintf(
						'Maintenance mode is on for %1$d minute%2$s, until about %3$s UTC; WordPress turns it off by itself then. Until then every visitor and every request, this connection\'s included, gets WordPress\'s maintenance page.',
						$minutes,
						1 === $minutes ? '' : 's',
						gmdate( 'H:i', $started + self::MAX_MAINTENANCE_MINUTES * MINUTE_IN_SECONDS )
					)
				);
			}
		);
	}

	/**
	 * The "maintenance-mode deactivate" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the file must stay.
	 */
	public function deactivate_maintenance( array $arguments, array $options ): Command_Output {
		Site_Changes::require_capability( 'manage_options', 'turn off maintenance mode' );
		if ( ! file_exists( ABSPATH . '.maintenance' ) ) {
			return Command_Output::text( 'Maintenance mode is not on. Nothing was changed.' );
		}
		self::require_maintenance_file_free( 'removed' );
		Site_Changes::require_file_changes( ABSPATH );

		return Change_Session::run(
			$this->budget(),
			static function ( Change_Session $session ): Command_Output {
				$session->budget()->require( self::QUICK_SECONDS, 'Removing the .maintenance file' );
				$session->lock();
				global $wp_filesystem;
				if ( ! $wp_filesystem->delete( trailingslashit( $wp_filesystem->abspath() ) . '.maintenance' ) ) {
					throw new Command_Error( 'WordPress could not remove the .maintenance file. Nothing was changed.' );
				}

				return Command_Output::text( 'The leftover .maintenance file was removed. Maintenance mode had already ended.' );
			}
		);
	}

	/**
	 * Refuse while maintenance mode was turned on from here, on any site of
	 * the network, within the last hour.
	 *
	 * @return void
	 *
	 * @throws Command_Error Within the hour.
	 */
	private static function require_cooldown_passed(): void {
		$last = get_site_option( self::MAINTENANCE_OPTION );
		if ( is_numeric( $last ) && (int) $last > time() - self::MAINTENANCE_COOLDOWN ) {
			throw new Command_Error( sprintf( 'Maintenance mode was turned on from here %1$d minutes ago. It can be turned on again from %2$s UTC, so a site is never kept down from here. Nothing was changed.', (int) floor( ( time() - (int) $last ) / MINUTE_IN_SECONDS ), gmdate( 'H:i', (int) $last + self::MAINTENANCE_COOLDOWN ) ) );
		}
	}

	/**
	 * Refuse while a .maintenance file is in effect, is not one WordPress
	 * wrote, or may belong to an update that is running.
	 *
	 * @param string $doing What would happen to the file, such as "removed".
	 * @return void
	 *
	 * @throws Command_Error When the file must be left alone.
	 */
	private static function require_maintenance_file_free( string $doing ): void {
		$file = ABSPATH . '.maintenance';
		if ( file_exists( $file ) ) {
			$contents = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read as text instead of run.
			if ( false === $contents ) {
				throw new Command_Error( 'A .maintenance file exists, but it cannot be read, so it was left alone. Nothing was changed.' );
			}
			$active = Settings_Commands::maintenance_active( $contents, time() );
			if ( null === $active ) {
				throw new Command_Error( 'A .maintenance file exists, but it is not in the form WordPress writes, so it was left alone. Nothing was changed.' );
			}
			if ( $active ) {
				throw new Command_Error( 'Maintenance mode is on right now, possibly for an update that is running. Nothing was changed. WordPress ends it within 10 minutes by itself.' );
			}
		}
		foreach ( array(
			'core_updater'          => 15 * MINUTE_IN_SECONDS,
			'auto_updater'          => HOUR_IN_SECONDS,
			'automator_site_manage' => Change_Session::LOCK_SECONDS,
		) as $lock => $timeout ) {
			$since = Change_Session::lock_time( $lock );
			if ( null !== $since && $since > time() - $timeout ) {
				throw new Command_Error( sprintf( 'Another update or site_manage change started %d seconds ago and may need the .maintenance file, so it was not %s. Nothing was changed. Try again in a few minutes.', max( 0, time() - $since ), $doing ) );
			}
		}
	}

	/**
	 * The "cron event run" command.
	 *
	 * @param array<string, string>      $arguments The hook, unless --due-now.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When nothing can be started.
	 */
	public function run_cron( array $arguments, array $options ): Command_Output {
		$hook    = $arguments['hook'] ?? null;
		$due_now = true === ( $options['due-now'] ?? false );
		if ( ( null !== $hook ) === $due_now ) {
			throw new Command_Error( 'Name one hook, or use --due-now, but not both. Usage: cron event run [<hook>] [--due-now]. Nothing was changed.' );
		}
		Site_Changes::require_capability( 'manage_options', 'run cron events' );
		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			throw new Command_Error( 'This site runs cron through ALTERNATE_WP_CRON, which starts only with a visitor\'s page load, not from here. Nothing was changed. Due events run on the next page view.' );
		}
		$held = (float) get_transient( 'doing_cron' );
		if ( $held > 0 && $held <= microtime( true ) + 10 * MINUTE_IN_SECONDS && $held + ( defined( 'WP_CRON_LOCK_TIMEOUT' ) ? WP_CRON_LOCK_TIMEOUT : MINUTE_IN_SECONDS ) > microtime( true ) ) {
			throw new Command_Error( sprintf( 'WordPress started its cron runner %d seconds ago, and it is still running or waiting. Nothing was changed. Try again in a minute.', max( 0, (int) ( microtime( true ) - $held ) ) ) );
		}

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $hook ): Command_Output {
				$session->budget()->require( self::QUICK_SECONDS, 'Starting cron events' );
				$moved = null === $hook ? '' : $this->move_to_now( $hook );
				$due   = $this->due_hooks();
				if ( array() === $due ) {
					return Command_Output::text( 'No cron events are due. Nothing was changed.' );
				}
				$started = microtime( true );
				if ( ! spawn_cron() ) {
					$host   = (string) wp_parse_url( site_url(), PHP_URL_HOST );
					$reason = $session->failed_requests()[ $host ] ?? null;
					if ( null !== $reason && (float) get_transient( 'doing_cron' ) >= $started ) {
						// WordPress took the cron lock for the request that failed; let the next run start.
						delete_transient( 'doing_cron' );
					}
					$cause = null !== $reason
						? sprintf( 'the site could not reach its own wp-cron.php (%s); Tools › Site Health reports this', rtrim( $reason, '. ' ) )
						: 'another cron run may have just started';
					throw new Command_Error( sprintf( 'WordPress did not start its cron runner: %s.%s Due events: %s. They run on the next cron run.', $cause, '' !== $moved ? ' ' . $moved : ' Nothing was changed.', implode( ', ', $due ) ) );
				}

				return Command_Output::text(
					trim(
						sprintf(
							'%s Started WordPress\'s cron runner for %d due event%s: %s. It runs them in a separate request with no user logged in. Check the result with "cron event list" in site_command in a minute; events that stay due mean the site\'s requests to itself are blocked, which Tools › Site Health reports.',
							$moved,
							count( $due ),
							1 === count( $due ) ? '' : 's',
							implode( ', ', array_slice( $due, 0, 20 ) ) . ( count( $due ) > 20 ? sprintf( ' and %d more', count( $due ) - 20 ) : '' )
						)
					)
				);
			}
		);
	}

	/**
	 * Move a hook's next event to now, so WordPress's cron runner runs it.
	 *
	 * @param string $hook Hook name.
	 * @return string What was moved.
	 *
	 * @throws Command_Error When no such event is scheduled.
	 */
	private function move_to_now( string $hook ): string {
		if ( isset( self::NOT_MOVED[ $hook ] ) ) {
			throw new Command_Error( sprintf( 'The next %1$s event was not moved to now: %2$s. Nothing was changed.', $hook, self::NOT_MOVED[ $hook ] ) );
		}
		$next = null;
		foreach ( (array) _get_cron_array() as $time => $hooks ) {
			if ( is_array( $hooks ) && isset( $hooks[ $hook ] ) && is_array( $hooks[ $hook ] ) ) {
				$instance = reset( $hooks[ $hook ] );
				$next     = array(
					'time'     => (int) $time,
					'args'     => is_array( $instance ) && isset( $instance['args'] ) && is_array( $instance['args'] ) ? $instance['args'] : array(),
					'schedule' => is_array( $instance ) && isset( $instance['schedule'] ) && is_string( $instance['schedule'] ) ? $instance['schedule'] : false,
				);
				break;
			}
		}
		if ( null === $next ) {
			throw new Command_Error( sprintf( 'No cron event with the hook %s is scheduled. Run "cron event list" in site_command to see the scheduled events. Nothing was changed.', $hook ) );
		}
		// Arguments may hold personal data, so only their number is shown.
		$count = count( $next['args'] );
		$args  = 0 === $count ? '' : sprintf( ' with %d argument%s', $count, 1 === $count ? '' : 's' );
		if ( $next['time'] <= time() ) {
			return sprintf( 'The next %s event%s was already due.', $hook, $args );
		}
		if ( false !== $next['schedule'] && ! isset( wp_get_schedules()[ $next['schedule'] ] ) ) {
			throw new Command_Error( sprintf( 'The next %1$s event repeats on the schedule "%2$s", which is not registered in this request, so it was not moved: WordPress could not repeat it afterwards. Nothing was changed.', $hook, Site_Changes::text( $next['schedule'] ) ) );
		}

		$kept = sprintf( 'Nothing was changed; it still runs at %s UTC.', gmdate( 'Y-m-d H:i', $next['time'] ) );
		if ( false === $next['schedule'] ) {
			// WordPress refuses a second identical one-off event within ten minutes, so the old one goes first.
			$unscheduled = wp_unschedule_event( $next['time'], $hook, $next['args'], true );
			if ( is_wp_error( $unscheduled ) ) {
				throw new Command_Error( sprintf( 'The next %1$s event could not be moved to now: %2$s. %3$s', $hook, rtrim( Site_Changes::text( $unscheduled->get_error_message() ), '. ' ), $kept ) );
			}
			$scheduled = wp_schedule_single_event( time() - 1, $hook, $next['args'], true );
			if ( is_wp_error( $scheduled ) ) {
				$back = wp_schedule_single_event( $next['time'], $hook, $next['args'], true );
				throw new Command_Error(
					sprintf(
						'The next %1$s event could not be moved to now: %2$s. %3$s',
						$hook,
						rtrim( Site_Changes::text( $scheduled->get_error_message() ), '. ' ),
						true === $back ? $kept : sprintf( 'It could not be put back at %s UTC either, so it may no longer be scheduled; check with "cron event list" in site_command.', gmdate( 'Y-m-d H:i', $next['time'] ) )
					)
				);
			}
		} else {
			// A time a whole number of intervals before the next run, so that WordPress
			// schedules the run after this one at the usual time of day.
			$interval = max( 1, (int) wp_get_schedules()[ $next['schedule'] ]['interval'] );
			$at       = $next['time'] - $interval * (int) ceil( ( $next['time'] - time() + 1 ) / $interval );
			// Add the event now first, so a failure never leaves it unscheduled.
			$scheduled = wp_schedule_event( max( 1, $at ), $next['schedule'], $hook, $next['args'], true );
			if ( is_wp_error( $scheduled ) ) {
				throw new Command_Error( sprintf( 'The next %1$s event could not be moved to now: %2$s. %3$s', $hook, rtrim( Site_Changes::text( $scheduled->get_error_message() ), '. ' ), $kept ) );
			}
			$unscheduled = wp_unschedule_event( $next['time'], $hook, $next['args'], true );
			if ( is_wp_error( $unscheduled ) ) {
				// Keep one event, not two.
				$removed = wp_unschedule_event( max( 1, $at ), $hook, $next['args'], true );
				throw new Command_Error(
					sprintf(
						'The next %1$s event could not be moved to now: %2$s. %3$s',
						$hook,
						rtrim( Site_Changes::text( $unscheduled->get_error_message() ), '. ' ),
						true === $removed ? $kept : 'It may now be scheduled twice, now and at its usual time; check with "cron event list" in site_command.'
					)
				);
			}
		}

		return sprintf( 'Moved the next %1$s event%2$s, scheduled for %3$s UTC, to now.%4$s', $hook, $args, gmdate( 'Y-m-d H:i', $next['time'] ), false === $next['schedule'] ? '' : ' Its later runs keep their usual time.' );
	}

	/**
	 * Hooks of the events that are due, oldest first.
	 *
	 * @return string[]
	 */
	private function due_hooks(): array {
		$due = array();
		foreach ( (array) wp_get_ready_cron_jobs() as $hooks ) {
			foreach ( is_array( $hooks ) ? $hooks : array() as $name => $instances ) {
				foreach ( is_array( $instances ) ? $instances : array() as $unused ) {
					$due[] = (string) $name;
				}
			}
		}

		return $due;
	}

	/**
	 * The "cache flush" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the cache was not flushed.
	 */
	public function flush_cache( array $arguments, array $options ): Command_Output {
		Site_Changes::require_capability( 'manage_options', 'flush the object cache' );
		if ( ! wp_using_ext_object_cache() ) {
			return Command_Output::text( 'This site has no persistent object cache, so there was nothing to flush: WordPress\'s cache lasts only for each request. Nothing was changed.' );
		}

		return Change_Session::run(
			$this->budget(),
			static function ( Change_Session $session ): Command_Output {
				$session->budget()->require( self::QUICK_SECONDS, 'Flushing the object cache' );
				if ( false === wp_cache_flush() ) {
					throw new Command_Error( 'The object cache reported that it could not be flushed, so cached data may remain. Try again; if it keeps failing, flush it from the cache plugin\'s settings.' );
				}

				return Command_Output::text( 'The object cache was flushed.' );
			}
		);
	}

	/**
	 * The "transient delete --expired" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error Without --expired.
	 */
	public function delete_transients( array $arguments, array $options ): Command_Output {
		if ( true !== ( $options['expired'] ?? false ) ) {
			throw new Command_Error( 'Only expired transients can be deleted here. Run "transient delete --expired". Nothing was changed.' );
		}
		Site_Changes::require_capability( 'manage_options', 'delete transients' );
		if ( wp_using_ext_object_cache() ) {
			return Command_Output::text( 'This site keeps transients in its persistent object cache, which expires them by itself. Nothing was changed.' );
		}

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ): Command_Output {
				$session->budget()->require( self::QUICK_SECONDS, 'Deleting expired transients' );
				global $wpdb;
				$tables = array( array( $wpdb->options, 'option_name', 'option_value' ) );
				if ( is_multisite() ) {
					$tables[] = array( $wpdb->sitemeta, 'meta_key', 'meta_value' );
				}
				$deleted = 0;
				$more    = false;
				$failed  = false;
				foreach ( $tables as list( $table, $name, $value ) ) {
					foreach ( array( '_transient_', '_site_transient_' ) as $prefix ) {
						while ( true ) {
							if ( ! $session->budget()->allows( self::QUICK_SECONDS ) ) {
								$more = true;
								break 3;
							}
							list( $batch, $refused ) = $this->delete_expired_batch( $table, $name, $value, $prefix );
							$deleted                += $batch;
							if ( $refused ) {
								$failed = true;
								break 3;
							}
							if ( 0 === $batch ) {
								break;
							}
						}
					}
				}
				if ( $deleted > 0 ) {
					wp_cache_delete( 'alloptions', 'options' );
				}

				$output = Command_Output::text( sprintf( 'Deleted %d expired transient%s.', $deleted, 1 === $deleted ? '' : 's' ) );
				if ( $failed ) {
					$output = $output->with_warning( 'The database refused a deletion, so the rest of the expired transients were left as they were. Try again later.' );
					return 0 === $deleted ? $output->with_failure() : $output;
				}

				return $more ? $output->with_warning( 'More expired transients may remain; time ran short. Run the command again to delete them.' ) : $output;
			}
		);
	}

	/**
	 * Delete one batch of expired transients and their timeouts.
	 *
	 * @param string $table  Table.
	 * @param string $name   Name column.
	 * @param string $value  Value column.
	 * A transient and its timeout are deleted together, and only while the
	 * timeout is still past, as WordPress's own cleanup does, so one renewed
	 * meanwhile is kept. A timeout is deleted alone only when its transient is
	 * already gone: a transient without a timeout never expires.
	 *
	 * @param string $prefix "_transient_" or "_site_transient_".
	 * @return array{0: int, 1: bool} Transients deleted, and whether the database refused a deletion.
	 */
	private function delete_expired_batch( string $table, string $name, string $value, string $prefix ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and column names come from $wpdb, not input; WordPress has no batched API for this.
		$timeouts = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT `' . esc_sql( $name ) . '` FROM `' . esc_sql( $table ) . '` WHERE `' . esc_sql( $name ) . '` LIKE %s AND `' . esc_sql( $value ) . '` < %d LIMIT %d',
				$wpdb->esc_like( $prefix . 'timeout_' ) . '%',
				time(),
				self::TRANSIENT_BATCH
			)
		);
		if ( '' !== (string) $wpdb->last_error ) {
			return array( 0, true );
		}
		if ( ! is_array( $timeouts ) || array() === $timeouts ) {
			return array( 0, false );
		}
		$values = array();
		foreach ( $timeouts as $timeout ) {
			$values[] = $prefix . substr( (string) $timeout, strlen( $prefix . 'timeout_' ) );
		}
		$now = time();
		// Expiry is checked again as rows are deleted, as WordPress's own cleanup does, so a
		// transient renewed meanwhile is kept.
		$pairs = $wpdb->query(
			$wpdb->prepare(
				'DELETE a, b FROM `' . esc_sql( $table ) . '` a INNER JOIN `' . esc_sql( $table ) . '` b ON b.`' . esc_sql( $name ) . '` = CONCAT( %s, SUBSTRING( a.`' . esc_sql( $name ) . '`, %d ) ) WHERE a.`' . esc_sql( $name ) . '` IN (' . implode( ',', array_fill( 0, count( $values ), '%s' ) ) . ') AND b.`' . esc_sql( $value ) . '` < %d',
				array_merge( array( $prefix . 'timeout_', strlen( $prefix ) + 1 ), $values, array( $now ) )
			)
		);
		if ( false === $pairs ) {
			return array( 0, true );
		}
		// Each transient is a value row and a timeout row.
		$deleted = (int) ceil( (int) $pairs / 2 );
		// Expired timeouts whose transient is already gone.
		$orphans = $wpdb->query(
			$wpdb->prepare(
				'DELETE b FROM `' . esc_sql( $table ) . '` b LEFT JOIN `' . esc_sql( $table ) . '` a ON a.`' . esc_sql( $name ) . '` = CONCAT( %s, SUBSTRING( b.`' . esc_sql( $name ) . '`, %d ) ) WHERE b.`' . esc_sql( $name ) . '` IN (' . implode( ',', array_fill( 0, count( $timeouts ), '%s' ) ) . ') AND b.`' . esc_sql( $value ) . '` < %d AND a.`' . esc_sql( $name ) . '` IS NULL',
				array_merge( array( $prefix, strlen( $prefix . 'timeout_' ) + 1 ), array_map( 'strval', $timeouts ), array( $now ) )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false === $orphans ) {
			return array( $deleted, true );
		}

		return array( $deleted + (int) $orphans, false );
	}

	/**
	 * The "rewrite flush" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function flush_rewrites( array $arguments, array $options ): Command_Output {
		Site_Changes::require_capability( 'manage_options', 'flush rewrite rules' );

		return Change_Session::run(
			$this->budget(),
			static function ( Change_Session $session ): Command_Output {
				$session->budget()->require( self::QUICK_SECONDS, 'Flushing the rewrite rules' );
				flush_rewrite_rules( false );
				$rules = get_option( 'rewrite_rules' );

				return Command_Output::text(
					'' === (string) get_option( 'permalink_structure' )
						? 'The rewrite rules were flushed. This site uses plain permalinks, so it has no rewrite rules.'
						: sprintf( 'The rewrite rules were flushed: %d rules now.', is_array( $rules ) ? count( $rules ) : 0 )
				);
			}
		);
	}

	/**
	 * A fresh time budget for one command.
	 *
	 * @return Time_Budget
	 */
	private function budget(): Time_Budget {
		return call_user_func( $this->budget );
	}
}
