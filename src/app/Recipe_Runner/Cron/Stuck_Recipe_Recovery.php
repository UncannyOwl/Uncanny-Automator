<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Cron;

use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\App\Bridge\Automator_Recipe_Runner_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Runner_Bridge;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Recovers recipes stuck at NOT_COMPLETED (status 0) after PHP fatal,
 * timeout, memory exhaustion, or MySQL deadlock.
 *
 * Runs every 15 minutes via WP-Cron. Finds stuck recipes older than the
 * configured threshold, calls finalize_recipe() on each, and logs a
 * recovery event to uap_error_log.
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
	 * Default threshold in seconds (1 hour).
	 */
	const DEFAULT_THRESHOLD = 3600;

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
	 * @param Recipe_Runner_Bridge|null $runner      Optional bridge override (tests).
	 * @param Action_Error_Store|null   $error_store Optional error store override (tests).
	 */
	public function __construct( ?Recipe_Runner_Bridge $runner = null, ?Action_Error_Store $error_store = null ) {
		$this->runner      = $runner ?? new Automator_Recipe_Runner_Bridge();
		$this->error_store = $error_store ?? Database::get_action_error_store();
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

		// Cluster safety — advisory lock prevents two WP nodes from
		// finalizing the same batch concurrently. Without this, multi-node
		// hosts (load-balanced WP Engine / AWS setups) double-fire
		// `automator_recipe_completed` and log duplicate RECIPE_STUCK
		// entries when both nodes' cron schedulers land in the same window.
		//
		// GET_LOCK waits up to 5s; if another node holds the lock we skip
		// this tick and try again in 15 minutes. Lock is released on
		// normal exit, connection drop, or MySQL restart.
		//
		// MySQL 5.7.5+ supports multiple named locks per session. On older
		// versions (5.7.4 and earlier) acquiring a new GET_LOCK silently
		// releases any prior lock held by the same session. Automator's
		// effective minimum is 5.7 across the hosting stack we target, and
		// no other code in the cron tick path grabs named locks, so the
		// single-lock assumption is safe. If a future caller adds another
		// GET_LOCK on the same connection, revisit this.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$lock_acquired = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', 'automator_stuck_recovery', 5 ) );

		if ( 1 !== $lock_acquired ) {
			return 0;
		}

		try {

			$threshold  = (int) Dispatcher::filter( 'automator_stuck_recipe_threshold_seconds', self::DEFAULT_THRESHOLD );
			$batch_size = (int) Dispatcher::filter( 'automator_stuck_recipe_batch_size', self::DEFAULT_BATCH_SIZE );

			$stuck_recipes = $this->find_stuck_recipes( $threshold, $batch_size );

			if ( empty( $stuck_recipes ) ) {
				return 0;
			}

			$recovered = 0;

			foreach ( $stuck_recipes as $row ) {

				$recipe_id     = (int) $row->automator_recipe_id;
				$user_id       = (int) $row->user_id;
				$recipe_log_id = (int) $row->ID;

				// Per-row try/catch — one poison row must not abandon the
				// remaining batch. Without this, an exception on recipe #3
				// of 50 leaves #4-50 stuck until the next tick (and the
				// same row will likely throw again, starving the rest).
				try {
					// Recovery context: this recipe has been stuck at NOT_COMPLETED for
					// 1h+, so a NOT_COMPLETED action row is a genuinely stuck/partial run
					// (PHP fatal / OOM mid-Stage 3), not a transient mid-completion state.
					// Pass true so the resolver surfaces it as COMPLETED_WITH_ERRORS rather
					// than silently upgrading the partial run to COMPLETED.
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
					// Continue — do not rethrow. The lock is released in
					// the finally block below; the remaining batch runs.
				}
			}

			return $recovered;

		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'automator_stuck_recovery' ) );
		}
	}

	/**
	 * Find recipes stuck at NOT_COMPLETED whose actions stopped progressing.
	 *
	 * Two conditions define "stuck", and both are about *actions*:
	 *
	 * 1. The run reached Stage 3 — it has at least one action log row. A run
	 *    with no action rows has not started executing: it is a multi-trigger
	 *    recipe still collecting triggers. Those legitimately stay open for
	 *    days or weeks (22 "view a page" triggers under ALL logic, satisfied
	 *    over an onboarding period), and finalizing them destroys the run.
	 *    The INNER JOIN below is what excludes them.
	 *
	 * 2. No action row has been touched for $threshold_seconds. Age is measured
	 *    from MAX( al.date_time ), NOT from rl.date_time — uap_recipe_log.date_time
	 *    is a *completion* timestamp. insert_recipe_log_row() seeds it as
	 *    '0000-00-00 00:00:00' and only mark_recipe_complete() writes a real value,
	 *    so an in-flight row always compares as older than any threshold. Ageing
	 *    against it made the threshold inert and swept live runs on the next tick.
	 *
	 * Recipes with an in-flight action — IN_PROGRESS, COMPLETED_AWAITING, QUEUED,
	 * or IN_PROGRESS_WITH_ERROR — are excluded regardless of age. This mirrors the
	 * "has_scheduled" whitelist in Recipe_Status_Resolver::resolve() so recovery
	 * cannot finalize a recipe the resolver would still call ongoing.
	 *
	 * Trade-off: a run that fataled *before* writing its first action row is no
	 * longer recovered. From uap_recipe_log alone it is indistinguishable from a
	 * recipe awaiting triggers, and leaving it open (the pre-7.3 behaviour) is far
	 * cheaper than destroying valid long-running runs.
	 *
	 * @param int $threshold_seconds How long actions must be idle before recovery.
	 * @param int $batch_size        Max recipes per run.
	 *
	 * @return array Row objects with ID, automator_recipe_id, user_id, date_time, last_action_activity.
	 */
	public function find_stuck_recipes( int $threshold_seconds, int $batch_size ): array {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rl.ID, rl.automator_recipe_id, rl.user_id, rl.date_time,
				        MAX( al.date_time ) AS last_action_activity
				FROM {$wpdb->prefix}uap_recipe_log rl
				INNER JOIN {$wpdb->prefix}uap_action_log al
				        ON al.automator_recipe_log_id = rl.ID
				WHERE rl.completed = 0
				  AND rl.ID NOT IN (
				      SELECT DISTINCT al2.automator_recipe_log_id
				      FROM {$wpdb->prefix}uap_action_log al2
				      WHERE al2.completed IN ( %d, %d, %d, %d )
				  )
				GROUP BY rl.ID, rl.automator_recipe_id, rl.user_id, rl.date_time
				HAVING MAX( al.date_time ) < DATE_SUB( NOW(), INTERVAL %d SECOND )
				ORDER BY last_action_activity ASC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				Automator_Status::IN_PROGRESS,
				Automator_Status::IN_PROGRESS_WITH_ERROR,
				Automator_Status::COMPLETED_AWAITING,
				Automator_Status::QUEUED,
				$threshold_seconds,
				$batch_size
			)
		);

		return is_array( $results ) ? $results : array();
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

		// Guard against an unparseable timestamp. Ageing against a zero date
		// previously reported ~2027 years of "age" in the log UI.
		$last_activity_ts = strtotime( $last_activity );
		$age_seconds      = false === $last_activity_ts ? 0 : max( 0, time() - $last_activity_ts );

		$error = new Action_Error(
			Error_Code::RECIPE_STUCK,
			'Recipe recovered by system — original execution did not complete',
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
