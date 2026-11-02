<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job;

/**
 * Persistence for background app-action jobs (`uap_async_jobs`): one row per accepted job,
 * resolvable by the platform's job id for the callback and enumerable by age for the poll.
 */
interface Background_Job_Store {

	/**
	 * Store an accepted job. A job id the table already holds returns that row unchanged.
	 *
	 * @param Background_Job $job The job as accepted; `dispatched_at` is stamped here.
	 *
	 * @throws \RuntimeException When the row cannot be written.
	 *
	 * @return Background_Job The stored job, with its row id.
	 */
	public function record( Background_Job $job ): Background_Job;

	/**
	 * The job the platform knows by this id.
	 *
	 * @param string $job_id The platform job id.
	 *
	 * @return Background_Job|null
	 */
	public function find_by_job_id( string $job_id ): ?Background_Job;

	/**
	 * How many jobs this run has recorded for this action, whatever their status.
	 *
	 * @param int $recipe_log_id The run.
	 * @param int $action_id     The action post id.
	 *
	 * @return int
	 */
	public function count_attempts( int $recipe_log_id, int $action_id ): int;

	/**
	 * Open jobs dispatched at least this long ago, oldest first.
	 *
	 * @param int $seconds Minimum age.
	 * @param int $limit   Max rows.
	 *
	 * @return Background_Job[]
	 */
	public function open_jobs_dispatched_before( int $seconds, int $limit = 50 ): array;

	/**
	 * Open site-originated jobs dispatched at least this long ago whose row id is above the cursor, lowest id first.
	 *
	 * @since 8.0.0
	 *
	 * @param int $after_id Row id the page starts after; 0 for the first page.
	 * @param int $seconds  Minimum age.
	 * @param int $limit    Max rows.
	 *
	 * @return Background_Job[]
	 */
	public function open_site_jobs_after( int $after_id, int $seconds, int $limit ): array;

	/**
	 * Close a job with its outcome and where it came from.
	 *
	 * @param int    $id     Row id.
	 * @param string $status One of Background_Job::STATUS_COMPLETED, STATUS_FAILED, STATUS_LOST.
	 * @param string $source One of Background_Job::SOURCE_CALLBACK, SOURCE_POLL, SOURCE_SITE.
	 *
	 * @return void
	 */
	public function mark_finished( int $id, string $status, string $source ): void;

	/**
	 * Replace what the action remembered for its job: a site-originated job's retry carries its attempt count and
	 * whatever the action learned between attempts.
	 *
	 * @since 8.0.0
	 *
	 * @param int                  $id         Row id.
	 * @param array<string, mixed> $remembered The values; must survive JSON.
	 *
	 * @return bool False when the values do not encode (the row keeps its old values) or the write failed.
	 */
	public function update_remembered( int $id, array $remembered ): bool;

	/**
	 * Claims an open job for one completion path. Exactly one caller wins.
	 *
	 * @param int    $id     Row id.
	 * @param string $source One of Background_Job::SOURCE_CALLBACK, SOURCE_POLL, SOURCE_SITE.
	 *
	 * @return bool True when this call moved the job from open to processing.
	 */
	public function claim( int $id, string $source ): bool;

	/**
	 * Refresh a processing job's claim: the completion holding it is still making progress, so the poll must not
	 * treat the claim as abandoned.
	 *
	 * @param int $id Row id.
	 *
	 * @return void
	 */
	public function touch_claim( int $id ): void;

	/**
	 * Releases a processing job whose claim is older than the window back to
	 * open: the request that claimed it died before finishing, and the next
	 * completion path claims it afresh. Counts the release on the job (redrives).
	 *
	 * @param int $id      Row id.
	 * @param int $seconds Age of the claim that counts as abandoned.
	 *
	 * @return bool True when this call released the claim.
	 */
	public function release_stale_claim( int $id, int $seconds ): bool;

	/**
	 * Hand a processing job back to open on purpose: the site's retry of a site-originated job settled or must wait
	 * again, and the next path claims it afresh. Unlike release_stale_claim(), not counted as a redrive.
	 *
	 * @since 8.0.0
	 *
	 * @param int $id Row id.
	 *
	 * @return bool True when this call released the claim.
	 */
	public function release_claim( int $id ): bool;

	/**
	 * Processing jobs whose claim is at least this old, oldest first.
	 *
	 * @param int $seconds Age of the claim that counts as abandoned.
	 * @param int $limit   Max rows.
	 *
	 * @return Background_Job[]
	 */
	public function processing_jobs_claimed_before( int $seconds, int $limit = 50 ): array;

	/**
	 * Delete the finished jobs of a recipe's runs: completed, failed or lost, the statuses mark_finished() sets and
	 * nothing moves a job out of. An open or processing job stays, so its retry or completion still runs.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe the jobs ran for.
	 *
	 * @return int Rows deleted.
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the delete fails.
	 */
	public function delete_finished_for_recipe( int $recipe_id ): int;

	/**
	 * Delete the finished jobs of one run. An open or processing job stays.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return int Rows deleted.
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the delete fails.
	 */
	public function delete_finished_for_run( int $recipe_log_id ): int;
}
