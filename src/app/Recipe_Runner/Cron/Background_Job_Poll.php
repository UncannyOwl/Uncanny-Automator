<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Cron;

use Uncanny_Automator\App\Bridge\Automator_Recipe_Runner_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Runner_Bridge;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Http;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Background_Job_Store;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Dispatch_Policy;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Site_Retry;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job_Result;

/**
 * Every fifteen minutes, ask the platform about each open background job older than one interval: a finished job
 * is completed from the poll body, a job the platform no longer has or one open past the lost cap is completed as
 * lost, anything else waits for the callback. The callback normally wins; this is the recovery path. Exposes
 * callbacks only; Background_Bootstrap binds them to `cron_schedules`, `init` and CRON_HOOK.
 *
 * It also re-drives a job whose claim a completing request abandoned: a processing job claimed more than
 * one interval ago is released back to open and settled through the same path.
 *
 * A site-originated job is the site's own to retry: the platform is never asked about it. One an attempt already
 * settled finishes with the outcome stored on its row, so a completion that died is re-driven without running the
 * write again. Past the lost cap an unsettled one is lost like any other; before that, a job whose retry event went
 * missing (a retry that died while it held the claim) gets one again.
 */
final class Background_Job_Poll {

	/**
	 * Cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'automator_background_job_poll';

	/**
	 * A job younger than this is left to its callback.
	 *
	 * @var int
	 */
	const POLL_AFTER_SECONDS = 15 * MINUTE_IN_SECONDS;

	/**
	 * Jobs asked about per tick.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 50;

	/**
	 * The jobs this site dispatched.
	 *
	 * @var Background_Job_Store
	 */
	private Background_Job_Store $jobs;

	/**
	 * The platform transport; anything with `get( string $url, array $headers ): array`.
	 *
	 * @var object
	 */
	private object $http;

	/**
	 * The runner, through the bridge.
	 *
	 * @var Recipe_Runner_Bridge
	 */
	private Recipe_Runner_Bridge $runner;

	/**
	 * Owner of the lost cap.
	 *
	 * @var Background_Dispatch_Policy
	 */
	private Background_Dispatch_Policy $policy;

	/**
	 * Bind the collaborators.
	 *
	 * @param Background_Job_Store|null       $jobs   Defaults to the shared store.
	 * @param object|null                     $http   Defaults to Platform_Http; the license headers ride in through the request-args injector.
	 * @param Recipe_Runner_Bridge|null       $runner Defaults to the legacy-facade bridge.
	 * @param Background_Dispatch_Policy|null $policy Defaults to a fresh policy.
	 */
	public function __construct( ?Background_Job_Store $jobs = null, ?object $http = null, ?Recipe_Runner_Bridge $runner = null, ?Background_Dispatch_Policy $policy = null ) {
		$this->jobs   = $jobs ?? Database::get_background_job_store();
		$this->http   = $http ?? new Platform_Http();
		$this->runner = $runner ?? new Automator_Recipe_Runner_Bridge();
		$this->policy = $policy ?? new Background_Dispatch_Policy();
	}

	/**
	 * The fifteen-minute interval, shared with the stuck-recipe recovery.
	 *
	 * @param array $schedules Existing schedules.
	 *
	 * @return array
	 */
	public function add_cron_interval( array $schedules ): array {
		if ( ! isset( $schedules[ Stuck_Recipe_Recovery::INTERVAL_NAME ] ) ) {
			$schedules[ Stuck_Recipe_Recovery::INTERVAL_NAME ] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => esc_html__( 'Every 15 minutes', 'uncanny-automator' ),
			);
		}

		return $schedules;
	}

	/**
	 * Schedule the tick once.
	 *
	 * @return void
	 */
	public function schedule_event(): void {
		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), Stuck_Recipe_Recovery::INTERVAL_NAME, self::CRON_HOOK );
		}
	}

	/**
	 * Remove the schedule; deactivation and tests.
	 *
	 * @return void
	 */
	public static function unregister(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * One tick.
	 *
	 * @return int Jobs settled.
	 */
	public function poll(): int {
		$settled    = 0;
		$now        = time();
		$lost_after = $this->policy->lost_after_seconds();

		foreach ( $this->jobs->open_jobs_dispatched_before( self::POLL_AFTER_SECONDS, self::BATCH_SIZE ) as $job ) {
			$result = $this->outcome_of( $job, $now, $lost_after );
			if ( null === $result ) {
				continue;
			}
			$this->runner->complete_background_action( $this->completion_job( $job ), $result );
			++$settled;
		}

		// A processing job whose claim is a poll interval old was claimed by a request
		// that died before mark_finished(). Release the claim and drive the job again;
		// complete_background_action() claims it like any other arrival.
		foreach ( $this->jobs->processing_jobs_claimed_before( self::POLL_AFTER_SECONDS, self::BATCH_SIZE ) as $job ) {
			if ( ! $this->jobs->release_stale_claim( $job->id(), self::POLL_AFTER_SECONDS ) ) {
				continue;
			}
			// Re-read: the release counted itself on the row, and the runner gives up a job re-driven too often.
			$job = $this->jobs->find_by_job_id( $job->job_id() );
			if ( null === $job ) {
				continue;
			}
			$result = $this->outcome_of( $job, $now, $lost_after );
			if ( null === $result ) {
				continue;
			}
			$this->runner->complete_background_action( $this->completion_job( $job ), $result );
			++$settled;
		}

		return $settled;
	}

	/**
	 * The platform's `GET /app/v1/jobs/{id}` resource.
	 *
	 * @param string $job_id The job.
	 *
	 * @return string
	 */
	public static function job_url( string $job_id ): string {
		return rtrim( (string) AUTOMATOR_API_URL, '/' ) . AUTOMATOR_PLATFORM_API_PATH . '/jobs/' . rawurlencode( $job_id );
	}

	/**
	 * Ask the platform. A finished job is its outcome; a job the platform no longer has is lost now; an open job past
	 * the cap is lost; anything else, including an unreachable platform, is not yet.
	 *
	 * @param Background_Job $job        The open job.
	 * @param int            $now        The tick's clock.
	 * @param int            $lost_after The cap in seconds.
	 *
	 * @return Background_Job_Result|null
	 */
	private function outcome_of( Background_Job $job, int $now, int $lost_after ): ?Background_Job_Result {
		if ( $job->is_site_originated() ) {
			return $this->site_outcome( $job, $now, $lost_after );
		}

		$body   = (array) $this->http->get( self::job_url( $job->job_id() ), array() );
		$result = Background_Job_Result::from_poll( $body );
		if ( null !== $result ) {
			return $result;
		}

		if ( 'JOB_UNAVAILABLE' === (string) ( $body['error']['type'] ?? '' ) ) {
			return Background_Job_Result::lost( sprintf( 'The platform no longer has background job %s; the action failed and the steps waiting on it were skipped.', $job->job_id() ) );
		}

		if ( $job->age_seconds( $now ) < $lost_after ) {
			return null;
		}

		return $this->lost_past_cap( $job, $lost_after );
	}

	/**
	 * The job as the completion receives it: a site job without the framework's own remembered values.
	 *
	 * @param Background_Job $job The job.
	 *
	 * @return Background_Job
	 */
	private function completion_job( Background_Job $job ): Background_Job {
		return $job->is_site_originated() ? Background_Site_Retry::for_action( $job ) : $job;
	}

	/**
	 * A site job an attempt settled finishes with that outcome; an unsettled one past the cap is lost, and before
	 * it, its next retry is made sure of.
	 *
	 * @param Background_Job $job        The open site job.
	 * @param int            $now        The tick's clock.
	 * @param int            $lost_after The cap in seconds.
	 *
	 * @return Background_Job_Result|null
	 */
	private function site_outcome( Background_Job $job, int $now, int $lost_after ): ?Background_Job_Result {
		$settled = Background_Site_Retry::settled_outcome( $job );
		if ( null !== $settled ) {
			return $settled;
		}

		if ( $job->age_seconds( $now ) >= $lost_after ) {
			return $this->lost_past_cap( $job, $lost_after );
		}

		Background_Site_Retry::schedule( $job->job_id(), Background_Site_Retry::counters( $job )['delay'] );

		return null;
	}

	/**
	 * The outcome of a job open past the cap.
	 *
	 * @param Background_Job $job        The job.
	 * @param int            $lost_after The cap in seconds.
	 *
	 * @return Background_Job_Result
	 */
	private function lost_past_cap( Background_Job $job, int $lost_after ): Background_Job_Result {
		return Background_Job_Result::lost( sprintf( 'Background job %s produced no result within %d hours; the action failed and the steps waiting on it were skipped.', $job->job_id(), (int) round( $lost_after / HOUR_IN_SECONDS ) ) );
	}
}
