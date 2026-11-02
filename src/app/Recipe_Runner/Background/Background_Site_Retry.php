<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Background;

use Uncanny_Automator\App\Bridge\Automator_Recipe_Runner_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Runner_Bridge;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Background_Job_Store;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;

/**
 * Retries a site-originated background job: a write the platform could not finish in the request, which the action
 * handed to App_Action::defer_to_site(). Each attempt claims the job, asks the action's retry_job() once, and then
 * either settles it or keeps what the action learned, counts the attempt and schedules the next one. Past the
 * action's cap the job fails. A settled outcome is stored on the row before the claim is released for the runner's
 * complete_background_action() (which claims the job again), so every later arrival, a retry event or the poll
 * re-driving a completion that died, finishes the job with that outcome and never runs the write again. A retry
 * that dies during the attempt itself runs again, so an action's retry_job() must be safe to repeat. Each attempt is
 * due one delay after the one before it ran (the first, one delay after the dispatch), and the counters keep that
 * time. A job whose retry event was lost is run by the one-minute sweep once its attempt is due; a job nothing
 * reschedules is lost by the poll's cap. Exposes the callbacks only; Background_Bootstrap binds them to CRON_HOOK
 * and SWEEP_HOOK.
 */
final class Background_Site_Retry {

	/**
	 * Cron hook; one single event per pending attempt, its argument the job id.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'automator_background_site_retry';

	/**
	 * Recurring cron hook of the sweep that runs the site jobs whose retry event was lost.
	 *
	 * @var string
	 */
	const SWEEP_HOOK = 'automator_background_site_sweep';

	/**
	 * The sweep's cron schedule: Action Scheduler's one-minute schedule, which the plugin always loads. A site
	 * without it gets no sweep event and leaves a lost retry to the poll.
	 *
	 * @var string
	 */
	const SWEEP_INTERVAL = 'every_minute';

	/**
	 * Seconds a site job is left to its own retry event before the sweep looks at it.
	 *
	 * @var int
	 */
	const SWEEP_AFTER_SECONDS = 30;

	/**
	 * Site jobs one sweep looks at, lowest id first, starting after the cursor.
	 *
	 * @var int
	 */
	const SWEEP_BATCH_SIZE = 50;

	/**
	 * Option holding the row id the next sweep starts after: the last id of a full batch, so jobs still waiting on
	 * their events never hold back the ones behind them. Absent when the last batch reached the end.
	 *
	 * @var string
	 */
	const SWEEP_CURSOR_OPTION = 'automator_background_site_sweep_cursor';

	/**
	 * The key under a site job's remembered values that holds the counters (attempt, delay, max); the action's
	 * retry_job() never sees it.
	 *
	 * @var string
	 */
	const COUNTERS_KEY = '_site_retry';

	/**
	 * The key under a site job's remembered values that holds its settled outcome (status and envelope) once an
	 * attempt settled it; the action's retry_job() and complete_job() never see it.
	 *
	 * @var string
	 */
	const OUTCOME_KEY = '_site_outcome';

	/**
	 * The jobs this site recorded.
	 *
	 * @var Background_Job_Store
	 */
	private Background_Job_Store $jobs;

	/**
	 * The runner, through the bridge.
	 *
	 * @var Recipe_Runner_Bridge
	 */
	private Recipe_Runner_Bridge $runner;

	/**
	 * Bind the collaborators.
	 *
	 * @param Background_Job_Store|null $jobs   Defaults to the shared store.
	 * @param Recipe_Runner_Bridge|null $runner Defaults to the legacy-facade bridge.
	 */
	public function __construct( ?Background_Job_Store $jobs = null, ?Recipe_Runner_Bridge $runner = null ) {
		$this->jobs   = $jobs ?? Database::get_background_job_store();
		$this->runner = $runner ?? new Automator_Recipe_Runner_Bridge();
	}

	/**
	 * Record a site job for a write and schedule its first attempt.
	 *
	 * @param array<string, mixed> $data          Background_Job constructor data without job id, key and origin.
	 * @param int                  $delay_seconds Seconds before each attempt.
	 * @param int                  $max_attempts  Attempts before the job fails.
	 *
	 * @return Background_Job The recorded job.
	 *
	 * @throws \InvalidArgumentException When the attempts cannot all run before the poll declares the job lost.
	 * @throws \RuntimeException         When the job cannot be recorded.
	 */
	public function defer( array $data, int $delay_seconds, int $max_attempts ): Background_Job {
		$delay_seconds = max( 1, $delay_seconds );
		$max_attempts  = max( 1, $max_attempts );
		$lost_after    = ( new Background_Dispatch_Policy() )->lost_after_seconds();

		if ( $delay_seconds * $max_attempts >= $lost_after ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Three integers for the log, never printed.
			throw new \InvalidArgumentException( sprintf( 'A site job retried %d times every %d seconds outlives the %d-second lost cap; the poll would declare it lost before its last attempt.', $max_attempts, $delay_seconds, $lost_after ) );
		}

		$data['job_id']          = 'site_' . wp_generate_uuid4();
		$data['idempotency_key'] = '';
		$data['origin']          = Background_Job::ORIGIN_SITE;
		$data['remembered']      = (array) ( $data['remembered'] ?? array() );

		$data['remembered'][ self::COUNTERS_KEY ] = array(
			'attempt' => 1,
			'delay'   => $delay_seconds,
			'max'     => $max_attempts,
			'due_at'  => time() + $delay_seconds,
		);

		$job = $this->jobs->record( new Background_Job( $data ) );
		self::schedule( $job->job_id(), $delay_seconds );

		return $job;
	}

	/**
	 * Schedule the job's next attempt, unless one is already pending.
	 *
	 * @param string $job_id        The site job id.
	 * @param int    $delay_seconds Seconds from now.
	 *
	 * @return void
	 */
	public static function schedule( string $job_id, int $delay_seconds ): void {
		if ( false === wp_next_scheduled( self::CRON_HOOK, array( $job_id ) ) ) {
			wp_schedule_single_event( time() + max( 1, $delay_seconds ), self::CRON_HOOK, array( $job_id ) );
		}
	}

	/**
	 * Schedule the sweep once.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function schedule_sweep(): void {
		if ( false === wp_next_scheduled( self::SWEEP_HOOK ) ) {
			wp_schedule_event( time(), self::SWEEP_INTERVAL, self::SWEEP_HOOK );
		}
	}

	/**
	 * Remove the sweep's schedule and cursor, on deactivation.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public static function unregister_sweep(): void {
		wp_clear_scheduled_hook( self::SWEEP_HOOK );
		automator_delete_option( self::SWEEP_CURSOR_OPTION );
	}

	/**
	 * Run the open site jobs whose retry event is gone and whose next attempt is due. WordPress keeps every
	 * scheduled event in one option that each request rewrites whole from the copy it loaded, so a cron run that
	 * overlaps the request scheduling a retry can erase that retry's event; the job row still says the retry is
	 * owed. The sweep's own event recurs, so it survives those rewrites: every copy holds it. Each sweep reads one
	 * batch after the cursor and moves the cursor past a full batch, so every open site job is looked at within one
	 * sweep per batch of jobs ahead of it.
	 *
	 * @since 8.0.0
	 *
	 * @return int Jobs handed to retry().
	 */
	public function sweep(): int {
		// This request's copy of the cron array can predate events other requests wrote since; read it again.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'cron', 'options' );

		$cursor = (int) automator_get_option( self::SWEEP_CURSOR_OPTION, 0 );
		$batch  = $this->jobs->open_site_jobs_after( $cursor, self::SWEEP_AFTER_SECONDS, self::SWEEP_BATCH_SIZE );
		$this->move_sweep_cursor( $cursor, $batch );

		$now = time();
		$ran = 0;
		foreach ( $batch as $job ) {
			if ( false !== wp_next_scheduled( self::CRON_HOOK, array( $job->job_id() ) ) || $now < self::due_at( $job ) ) {
				continue;
			}
			$this->retry( $job->job_id() );
			++$ran;
		}

		return $ran;
	}

	/**
	 * Start the next sweep after a full batch, or over from the first job after a short one.
	 *
	 * @param int              $cursor The id this sweep started after.
	 * @param Background_Job[] $batch  The jobs this sweep read.
	 *
	 * @return void
	 */
	private function move_sweep_cursor( int $cursor, array $batch ): void {
		if ( self::SWEEP_BATCH_SIZE === count( $batch ) ) {
			automator_update_option( self::SWEEP_CURSOR_OPTION, end( $batch )->id(), false );

			return;
		}

		if ( 0 !== $cursor ) {
			automator_delete_option( self::SWEEP_CURSOR_OPTION );
		}
	}

	/**
	 * When a site job's next attempt is due, as a timestamp; 0 (due now) for a job recorded without one.
	 *
	 * @since 8.0.0
	 *
	 * @param Background_Job $job The job.
	 *
	 * @return int
	 */
	public static function due_at( Background_Job $job ): int {
		return max( 0, (int) ( $job->remembered()[ self::COUNTERS_KEY ]['due_at'] ?? 0 ) );
	}

	/**
	 * The framework's counters for a site job: attempt (the one about to run), delay and max.
	 *
	 * @param Background_Job $job The job.
	 *
	 * @return array{attempt: int, delay: int, max: int}
	 */
	public static function counters( Background_Job $job ): array {
		$counters = (array) ( $job->remembered()[ self::COUNTERS_KEY ] ?? array() );

		return array(
			'attempt' => max( 1, (int) ( $counters['attempt'] ?? 1 ) ),
			'delay'   => max( 1, (int) ( $counters['delay'] ?? MINUTE_IN_SECONDS ) ),
			'max'     => max( 1, (int) ( $counters['max'] ?? 1 ) ),
		);
	}

	/**
	 * The outcome an earlier attempt settled the job with, or null while it is unsettled.
	 *
	 * @since 8.0.0
	 *
	 * @param Background_Job $job The job.
	 *
	 * @return Background_Job_Result|null
	 */
	public static function settled_outcome( Background_Job $job ): ?Background_Job_Result {
		$stored = $job->remembered()[ self::OUTCOME_KEY ] ?? null;
		if ( ! is_array( $stored ) || ! is_string( $stored['status'] ?? null ) || ! is_array( $stored['envelope'] ?? null ) ) {
			return null;
		}

		return Background_Job_Result::settled_on_site( $stored['status'], $stored['envelope'] );
	}

	/**
	 * The job as its action sees it: the remembered values without the framework's counters and outcome.
	 *
	 * @since 8.0.0
	 *
	 * @param Background_Job $job The job.
	 *
	 * @return Background_Job
	 */
	public static function for_action( Background_Job $job ): Background_Job {
		return $job->with_remembered( self::action_values( $job->remembered() ) );
	}

	/**
	 * Remembered values without the framework's own keys.
	 *
	 * @param array<string, mixed> $remembered The stored values.
	 *
	 * @return array<string, mixed>
	 */
	private static function action_values( array $remembered ): array {
		unset( $remembered[ self::COUNTERS_KEY ], $remembered[ self::OUTCOME_KEY ] );

		return $remembered;
	}

	/**
	 * One attempt, on the cron hook.
	 *
	 * @param string $job_id The site job id.
	 *
	 * @return void
	 */
	public function retry( string $job_id ): void {
		$job = $this->jobs->find_by_job_id( $job_id );
		if ( null === $job || ! $job->is_site_originated() || Background_Job::STATUS_OPEN !== $job->status() ) {
			return;
		}
		if ( ! $this->jobs->claim( $job->id(), Background_Job::SOURCE_SITE ) ) {
			return;
		}

		$settled = self::settled_outcome( $job );
		if ( null !== $settled ) {
			$this->jobs->release_claim( $job->id() );
			$this->runner->complete_background_action( self::for_action( $job ), $settled );
			return;
		}

		$counters = self::counters( $job );
		$outcome  = $this->attempt( $job, self::action_values( $job->remembered() ) );
		if ( null === $outcome['result'] && $counters['attempt'] >= $counters['max'] ) {
			$outcome['result'] = Background_Job_Result::from_site(
				Background_Job_Result::STATUS_FAILED,
				array(),
				array(
					'code'        => 504,
					'description' => sprintf( 'Background job %s did not finish after %d attempts; the action failed and the steps waiting on it were skipped.', $job->job_id(), $counters['max'] ),
				)
			);
		}

		if ( null !== $outcome['result'] ) {
			// The row keeps what this attempt learned, the counters and the outcome before the claim goes, so an arrival
			// after the release finishes the job with this outcome; the completion gets the action's values only. When
			// the row cannot keep them, this request still completes: keeping the claim instead would only hand the job
			// to the poll's stale-claim release, which finds no outcome and runs the write again.
			$stored = $this->jobs->update_remembered(
				$job->id(),
				$outcome['remembered'] + array(
					self::COUNTERS_KEY => $counters,
					self::OUTCOME_KEY  => array(
						'status'   => $outcome['result']->status(),
						'envelope' => $outcome['result']->to_envelope(),
					),
				)
			);
			if ( ! $stored ) {
				automator_log( sprintf( 'Background job %s: its settled outcome could not be stored; completing it in this request.', $job->job_id() ), 'Background_Site_Retry', true );
			}
			$this->jobs->release_claim( $job->id() );
			$this->runner->complete_background_action( $job->with_remembered( $outcome['remembered'] ), $outcome['result'] );
			return;
		}

		$next                       = $outcome['remembered'];
		$next[ self::COUNTERS_KEY ] = array(
			'attempt' => $counters['attempt'] + 1,
			'due_at'  => time() + $counters['delay'],
		) + $counters;
		if ( ! $this->jobs->update_remembered( $job->id(), $next ) ) {
			automator_log( sprintf( 'Background job %s: attempt %d could not be counted; the poll\'s lost cap still bounds its retries.', $job->job_id(), $counters['attempt'] ), 'Background_Site_Retry', true );
		}
		$this->jobs->release_claim( $job->id() );
		self::schedule( $job->job_id(), $counters['delay'] );
	}

	/**
	 * Ask the job's action for one retry. An action that cannot retry, or a retry that throws, is a failed outcome.
	 *
	 * @param Background_Job       $job        The claimed job.
	 * @param array<string, mixed> $remembered The action's own remembered values.
	 *
	 * @return array{result: Background_Job_Result|null, remembered: array<string, mixed>}
	 */
	private function attempt( Background_Job $job, array $remembered ): array {
		$resolved = $this->runner->resolve_background_action( $job );
		if ( null === $resolved || ! method_exists( $resolved['action'], 'retry_site_job' ) ) {
			return array(
				'result'     => $this->failed( sprintf( 'Background job %s belongs to action %d, which cannot retry it.', $job->job_id(), $job->action_id() ) ),
				'remembered' => $remembered,
			);
		}

		try {
			return $resolved['action']->retry_site_job( $resolved['action_data'], $job->parsed(), $remembered );
		} catch ( \Throwable $e ) {
			return array(
				'result'     => $this->failed( $e->getMessage() ),
				'remembered' => $remembered,
			);
		}
	}

	/**
	 * A failed outcome with the given description.
	 *
	 * @param string $description What the run's error log shows.
	 *
	 * @return Background_Job_Result
	 */
	private function failed( string $description ): Background_Job_Result {
		return Background_Job_Result::from_site(
			Background_Job_Result::STATUS_FAILED,
			array(),
			array(
				'code'        => 500,
				'description' => $description,
			)
		);
	}
}
