<?php
/**
 * Public helpers for background app actions.
 *
 * The seam src/core's action framework uses; nothing here throws.
 *
 * @package Uncanny_Automator\App
 */

use Uncanny_Automator\App\Infrastructure\Async_Jobs\Idempotency_Key_Minter;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Actions_Setting;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Dispatch_Policy;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Site_Retry;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'automator_background_app_actions_enabled' ) ) {
	/**
	 * Whether the "Background app actions" setting is on.
	 *
	 * @return bool
	 */
	function automator_background_app_actions_enabled(): bool {
		return Background_Actions_Setting::enabled();
	}
}

if ( ! function_exists( 'automator_background_dispatch_allowed' ) ) {
	/**
	 * Whether an integration's write may be sent to the platform as a background job.
	 *
	 * @param string $integration_code The platform integration the write goes to (`facebook` for Instagram), or the
	 *                                 action's integration code when it has no platform caller.
	 *
	 * @return bool
	 */
	function automator_background_dispatch_allowed( string $integration_code ): bool {
		try {
			return ( new Background_Dispatch_Policy() )->allows( $integration_code );
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}

if ( ! function_exists( 'automator_background_idempotency_key' ) ) {
	/**
	 * The idempotency key for this action's next background attempt in this run.
	 *
	 * @param int $recipe_log_id The run.
	 * @param int $action_id     The action post id.
	 *
	 * @return string
	 */
	function automator_background_idempotency_key( int $recipe_log_id, int $action_id ): string {
		$attempt = automator_get_background_job_store()->count_attempts( $recipe_log_id, $action_id );

		return ( new Idempotency_Key_Minter() )->mint( $recipe_log_id, $action_id, $attempt );
	}
}

if ( ! function_exists( 'automator_record_background_job' ) ) {
	/**
	 * Record an accepted background job and keep the run's snapshot alive past the poll cap.
	 *
	 * @param array<string, mixed> $data The Background_Job constructor data.
	 *
	 * @return int The job row id, 0 when the data does not describe a job.
	 */
	function automator_record_background_job( array $data ): int {
		try {
			$job = automator_get_background_job_store()->record( new Background_Job( $data ) );
		} catch ( \Throwable $e ) {
			automator_log( $e->getMessage(), 'automator_record_background_job', false );
			return 0;
		}

		$keep_until = gmdate( 'Y-m-d H:i:s', time() + ( new Background_Dispatch_Policy() )->snapshot_keep_seconds() );
		Database::get_run_snapshot_store()->extend_expiry( $job->recipe_log_id(), $keep_until );

		return $job->id();
	}
}

if ( ! function_exists( 'automator_defer_background_job_to_site' ) ) {
	/**
	 * Record a site-originated background job for a write the platform could not finish in the request, keep the
	 * run's snapshot alive past the poll cap, and schedule the first retry.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string, mixed> $data          The Background_Job constructor data, without job id, key and origin.
	 * @param int                  $delay_seconds Seconds before each retry.
	 * @param int                  $max_attempts  Retries before the action fails.
	 *
	 * @return bool False when the job could not be recorded; the reason is logged.
	 */
	function automator_defer_background_job_to_site( array $data, int $delay_seconds, int $max_attempts ): bool {
		// The snapshot is kept first: a job recorded and scheduled is a job that will run, so nothing after the
		// record may fail and send the caller down its no-job path.
		try {
			$keep_until = gmdate( 'Y-m-d H:i:s', time() + ( new Background_Dispatch_Policy() )->snapshot_keep_seconds() );
			Database::get_run_snapshot_store()->extend_expiry( (int) ( $data['recipe_log_id'] ?? 0 ), $keep_until );

			( new Background_Site_Retry() )->defer( $data, $delay_seconds, $max_attempts );
		} catch ( \Throwable $e ) {
			automator_log( $e->getMessage(), 'automator_defer_background_job_to_site', true );
			return false;
		}

		return true;
	}
}
