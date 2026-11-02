<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Run_Snapshot;

/**
 * Contract for the run-snapshot store.
 *
 * Persists compressed trigger context so that failed recipe runs can be
 * replayed with the exact same trigger data. Data is gzcompressed JSON
 * with an md5 checksum for integrity verification.
 *
 * @since 7.4.0
 */
interface Run_Snapshot_Store {

	/**
	 * Default time-to-live in hours for snapshots.
	 *
	 * @var int
	 */
	const DEFAULT_TTL_HOURS = 48;

	/**
	 * Capture a run snapshot for later replay.
	 *
	 * Returns null when the recipe-level TTL is 0 (capture skipped).
	 *
	 * @param int   $recipe_log_id The recipe log ID to associate the snapshot with.
	 * @param int   $recipe_id     The recipe post ID.
	 * @param int   $user_id       The user ID for this run.
	 * @param array $snapshot_data The snapshot payload (trigger args + meta).
	 * @param int   $ttl_hours     Hours until the snapshot expires.
	 * @param bool  $allow_skip    When false a 0 TTL captures for the default lifetime instead of skipping.
	 *
	 * @return int|null The inserted row ID, or null if skipped.
	 */
	public function capture( int $recipe_log_id, int $recipe_id, int $user_id, array $snapshot_data, int $ttl_hours = self::DEFAULT_TTL_HOURS, bool $allow_skip = true ): ?int;

	/**
	 * Retrieve a non-expired snapshot by recipe_log_id.
	 *
	 * Decompresses the blob, verifies the md5 checksum, and returns a
	 * {@see Run_Snapshot} value object. Returns null if expired, missing,
	 * or corrupted.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 * @return Run_Snapshot|null
	 */
	public function get( int $recipe_log_id ): ?Run_Snapshot;

	/**
	 * Check whether a recipe log has a valid, non-expired snapshot.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 * @return bool True if a replayable snapshot exists.
	 */
	public function is_replayable( int $recipe_log_id ): bool;

	/**
	 * Get replayable runs for a recipe, joined with recipe log for status.
	 *
	 * @param int $recipe_id The recipe post ID.
	 * @param int $limit     Maximum rows to return.
	 * @return array Row objects.
	 */
	public function get_replayable_runs( int $recipe_id, int $limit = 50 ): array;

	/**
	 * Delete all expired snapshots.
	 *
	 * @return int Number of rows deleted.
	 */
	public function purge_expired(): int;

	/**
	 * Delete every snapshot of a recipe's runs, expired or not.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id The recipe post ID.
	 *
	 * @return int Number of rows deleted.
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the delete fails.
	 */
	public function purge_recipe( int $recipe_id ): int;

	/**
	 * Keep a snapshot at least until the given instant; an earlier value than the current expiry is ignored.
	 *
	 * @param int    $recipe_log_id The run.
	 * @param string $expires_at    UTC `Y-m-d H:i:s`.
	 *
	 * @return void
	 */
	public function extend_expiry( int $recipe_log_id, string $expires_at ): void;

	/**
	 * Delete a run's snapshot, expired or not, so the run can't be replayed once its log is deleted.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_log_id The run.
	 *
	 * @return int Number of rows deleted (0 when the run kept no snapshot).
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the delete fails.
	 */
	public function purge_run( int $recipe_log_id ): int;

	/**
	 * Delete every snapshot of a user's runs, expired or not: a snapshot holds the trigger's data, which must not
	 * outlive a deleted user. The runs themselves are not touched.
	 *
	 * A run of no user (user 0, an Everyone recipe's run) belongs to no one, so 0 deletes nothing.
	 *
	 * @since 8.0.0
	 *
	 * @param int $user_id The user.
	 *
	 * @return int Number of rows deleted (0 when the user's runs kept no snapshot).
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the delete fails.
	 */
	public function purge_user( int $user_id ): int;
}
