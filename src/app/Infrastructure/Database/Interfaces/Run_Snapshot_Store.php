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
	 * Capture a run snapshot for later replay.
	 *
	 * Returns null when the recipe-level TTL is 0 (capture skipped).
	 *
	 * @param int   $recipe_log_id The recipe log ID to associate the snapshot with.
	 * @param int   $recipe_id     The recipe post ID.
	 * @param int   $user_id       The user ID for this run.
	 * @param array $snapshot_data The snapshot payload (trigger args + meta).
	 * @param int   $ttl_hours     Hours until the snapshot expires.
	 *
	 * @return int|null The inserted row ID, or null if skipped.
	 */
	public function capture( int $recipe_log_id, int $recipe_id, int $user_id, array $snapshot_data, int $ttl_hours ): ?int;

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
}
