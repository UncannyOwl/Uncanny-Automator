<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Cron;

use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Bridge\Automator_Recipe_Runner_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Runner_Bridge;
use Uncanny_Automator\App\Infrastructure\Async_Jobs\Inbox_Drainer;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Site_Time;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Recovers runs that stopped progressing after a PHP fatal, timeout, memory
 * exhaustion or MySQL deadlock: a run left at NOT_COMPLETED or IN_PROGRESS whose
 * action rows have been idle for the configured threshold.
 *
 * Runs every 15 minutes via WP-Cron. The candidate query is
 * Execution_Log_Store::find_stuck_recipe_logs(), whose docblock defines "stuck"
 * and the invariant it relies on. Each candidate is finalized through the runner
 * bridge and a RECIPE_STUCK entry is written to uap_error_log.
 *
 * The same tick fails background dispatches whose worker never finished, which that
 * query skips because their row is still In progress. They are never run again: a
 * worker that died partway may already have sent the data.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Cron
 * @since   7.3
 */
class Stuck_Recipe_Recovery {

	/**
	 * WP-Cron hook name.
	 */
	const CRON_HOOK = 'automator_recover_stuck_recipes';

	/**
	 * Custom cron interval name.
	 */
	const INTERVAL_NAME = 'automator_every_fifteen_minutes';

	/**
	 * MySQL advisory lock held while a batch is finalized.
	 */
	const LOCK_NAME = 'automator_stuck_recovery';

	/**
	 * Default threshold in seconds (1 hour).
	 */
	const DEFAULT_THRESHOLD = 3600;

	/**
	 * Seconds a background dispatch may stay In progress. A worker starts within 60 seconds or is refused
	 * (Background_Actions::validate_rest_call()), so the rest is time for a running action to finish.
	 */
	const LOST_DISPATCH_THRESHOLD = 900;

	/**
	 * Default batch size.
	 */
	const DEFAULT_BATCH_SIZE = 50;

	/**
	 * Bridge to the legacy recipe runner facade.
	 *
	 * @var Recipe_Runner_Bridge
	 */
	private Recipe_Runner_Bridge $runner;

	/**
	 * @var Action_Error_Store
	 */
	private Action_Error_Store $error_store;

	/**
	 * @var Execution_Log_Store
	 */
	private Execution_Log_Store $log_store;

	/**
	 * @param Recipe_Runner_Bridge|null $runner      Optional bridge override (tests).
	 * @param Action_Error_Store|null   $error_store Optional error store override (tests).
	 * @param Execution_Log_Store|null  $log_store   Optional log store override (tests).
	 */
	public function __construct( ?Recipe_Runner_Bridge $runner = null, ?Action_Error_Store $error_store = null, ?Execution_Log_Store $log_store = null ) {
		$this->runner      = $runner ?? new Automator_Recipe_Runner_Bridge();
		$this->error_store = $error_store ?? Database::get_action_error_store();
		$this->log_store   = $log_store ?? Database::get_execution_log_store();
	}

	/**
	 * Register cron hooks and schedule the event.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
		add_action( 'init', array( $this, 'schedule_event' ) );
		add_action( self::CRON_HOOK, array( $this, 'recover' ) );
	}

	/**
	 * Add a custom 15-minute cron interval.
	 *
	 * @param array $schedules Existing cron schedules.
	 *
	 * @return array
	 */
	public function add_cron_interval( array $schedules ): array {

		if ( ! isset( $schedules[ self::INTERVAL_NAME ] ) ) {
			$schedules[ self::INTERVAL_NAME ] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every 15 minutes', 'uncanny-automator' ),
			);
		}

		return $schedules;
	}

	/**
	 * Schedule the cron event if not already scheduled.
	 *
	 * @return void
	 */
	public function schedule_event(): void {

		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::INTERVAL_NAME, self::CRON_HOOK );
		}
	}

	/**
	 * Find and recover stuck recipes.
	 *
	 * @return int Number of recipes recovered.
	 */
	public function recover(): int {

		$enabled = Dispatcher::filter( 'automator_stuck_recipe_recovery_enabled', true );

		if ( true !== $enabled ) {
			return 0;
		}

		// Cluster safety: two WP nodes whose cron schedulers land in the same
		// window must not finalize the same batch, or both fire
		// `automator_recipe_completed` and log duplicate RECIPE_STUCK entries.
		// Wait up to 5s; if another node holds the lock, skip this tick.
		if ( ! $this->log_store->acquire_lock( self::LOCK_NAME, 5 ) ) {
			return 0;
		}

		try {

			// Durable-ingress sweep rides this tick BEFORE the stuck-recipe
			// early-return (healthy sites still need the sweep): requeue
			// crashed/retryable uap_platform_inbox rows, re-fire the drain
			// per pending bucket, drop rows past the audit-retention window
			// (Inbox_Drainer::RETENTION_SECONDS — the only path that removes
			// rows now that success retains them). Isolated so a sweep
			// failure never starves recipe recovery.
			try {
				( new Inbox_Drainer() )->sweep();
			} catch ( \Throwable $e ) {
				automator_log( $e->getMessage(), 'Stuck_Recipe_Recovery inbox sweep', false );
			}

			$threshold  = (int) Dispatcher::filter( 'automator_stuck_recipe_threshold_seconds', self::DEFAULT_THRESHOLD );
			$batch_size = (int) Dispatcher::filter( 'automator_stuck_recipe_batch_size', self::DEFAULT_BATCH_SIZE );

			$recovered = $this->fail_lost_dispatches( $batch_size );

			$stuck_recipes = $this->log_store->find_stuck_recipe_logs( $threshold, $batch_size );

			if ( empty( $stuck_recipes ) ) {
				return $recovered;
			}

			foreach ( $stuck_recipes as $row ) {

				$recipe_id     = (int) $row->automator_recipe_id;
				$user_id       = (int) $row->user_id;
				$recipe_log_id = (int) $row->ID;

				// Per-row try/catch: one poison row must not abandon the rest of
				// the batch, and the same row would throw again next tick.
				try {
					// The run has been idle past the threshold, so a NOT_COMPLETED
					// action row is a genuine partial (fatal / OOM mid-Stage 3), not a
					// transient: true has the resolver surface it as COMPLETED_WITH_ERRORS.
					// All-terminal rows resolve as they would at a normal close.
					$this->runner->finalize_recipe( $recipe_id, $user_id, $recipe_log_id, true );
					$this->log_recovery( $recipe_log_id, $threshold, (string) $row->last_action_activity );
					++$recovered;
				} catch ( \Throwable $e ) {
					automator_log(
						sprintf(
							'Stuck recovery failed for recipe_log_id=%d: %s',
							$recipe_log_id,
							$e->getMessage()
						),
						'Stuck_Recipe_Recovery'
					);
					// Continue; the lock is released in the finally block.
				}
			}

			return $recovered;

		} finally {
			$this->log_store->release_lock( self::LOCK_NAME );
		}
	}

	/**
	 * Fail each background dispatch whose worker never finished, then close its run.
	 *
	 * The run closes only when nothing else is waiting: a Queue block's row keeps it open. A worker that finished
	 * after the query read its row keeps its result. One that finishes after the row failed replaces the row's
	 * status with its real result, and the run keeps Completed with errors, because finalizing never lowers a run's
	 * status. A run that fails to finalize here is left to the one-hour pass in recover().
	 *
	 * @param int $batch_size Maximum rows.
	 *
	 * @return int Number of runs finalized.
	 */
	private function fail_lost_dispatches( int $batch_size ): int {

		$finalized = array();

		foreach ( $this->log_store->find_lost_background_dispatches( self::LOST_DISPATCH_THRESHOLD, $batch_size ) as $row ) {

			$action_log_id = (int) $row->ID;
			$recipe_log_id = (int) $row->automator_recipe_log_id;

			// Per-row try/catch: one poison row must not abandon the rest of the batch.
			try {
				if ( ! $this->log_store->mark_action_complete_if_in_progress( $action_log_id, Automator_Status::COMPLETED_WITH_ERRORS ) ) {
					continue;
				}
				$this->error_store->store(
					$recipe_log_id,
					$action_log_id,
					new Action_Error(
						Error_Code::BACKGROUND_JOB_LOST,
						'The background request for this action never finished, so the action may not have run.',
						array(
							'threshold_seconds' => self::LOST_DISPATCH_THRESHOLD,
							'dispatched_at'     => (string) $row->date_time,
						)
					)
				);
				$this->runner->finalize_recipe( (int) $row->automator_recipe_id, (int) $row->user_id, $recipe_log_id, true );
				$finalized[ $recipe_log_id ] = true;
			} catch ( \Throwable $e ) {
				automator_log(
					sprintf(
						'Lost dispatch recovery failed for action_log_id=%d: %s',
						$action_log_id,
						$e->getMessage()
					),
					'Stuck_Recipe_Recovery'
				);
			}
		}

		return count( $finalized );
	}

	/**
	 * Log a recovery event to the uap_error_log table.
	 *
	 * @param int    $recipe_log_id     The recovered recipe log ID.
	 * @param int    $threshold_seconds The threshold that was used.
	 * @param string $last_activity     Timestamp of the run's most recent action row.
	 *
	 * @return void
	 */
	private function log_recovery( int $recipe_log_id, int $threshold_seconds, string $last_activity ): void {

		// Row times are site time; read as UTC they'd be off by the site's offset. A time that names no instant
		// reports no age: ageing against a zero date previously reported ~2027 years of "age" in the log UI.
		$last_activity_at = ( new Site_Time( wp_timezone() ) )->latest_instant( $last_activity );
		$age_seconds      = null === $last_activity_at ? 0 : max( 0, time() - $last_activity_at );

		// COMPLETED, COMPLETED_WITH_NOTICE and DID_NOTHING all mean every action finished; only Stage 4 was missed.
		$finished = array( Automator_Status::COMPLETED, Automator_Status::COMPLETED_WITH_NOTICE, Automator_Status::DID_NOTHING );
		$message  = in_array( $this->log_store->get_recipe_status( $recipe_log_id ), $finished, true )
			? 'Run finalized by system: every action had completed but the run was never closed'
			: 'Recipe recovered by system: original execution did not complete';

		$error = new Action_Error(
			Error_Code::RECIPE_STUCK,
			$message,
			array(
				'threshold_seconds' => $threshold_seconds,
				'age_seconds'       => $age_seconds,
				'recovered_at'      => current_time( 'mysql' ),
			)
		);

		$this->error_store->store_system_error( $recipe_log_id, $error );
	}

	/**
	 * Unregister the cron event on plugin deactivation.
	 *
	 * @return void
	 */
	public static function unregister(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
