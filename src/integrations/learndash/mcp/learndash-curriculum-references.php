<?php
/**
 * Authorization boundary for the references LearnDash may reconcile.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */

declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Learndash\Mcp;

use RuntimeException;

/**
 * Defines the storage checks needed before native relationship reconciliation.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */
interface LearnDash_Curriculum_References {

	/**
	 * Detect an existing transaction without committing or replacing it.
	 *
	 * @return bool Whether the caller already owns a transaction.
	 *
	 * @throws RuntimeException When the transaction state cannot be determined.
	 */
	public function has_transaction(): bool;

	/**
	 * Find posts whose course relationships the native model may change.
	 *
	 * @param int      $course_id  Target course ID.
	 * @param string[] $step_types Native course-step post types for legacy ownership links.
	 * @param int      $limit      Maximum number of referenced posts to return.
	 * @param bool     $lock       Whether to lock matching rows inside a transaction.
	 *
	 * @return int[] Referenced post IDs, including stale, trashed and retyped shared links.
	 *
	 * @throws RuntimeException When the references cannot be read.
	 */
	public function post_ids( int $course_id, array $step_types, int $limit, bool $lock = false ): array;

	/**
	 * Lock posts and metadata inside a transaction, then refresh their caches.
	 *
	 * @param int[] $post_ids Posts whose current state must be used for validation.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the posts or metadata cannot be locked.
	 */
	public function lock_post_ids( array $post_ids ): void;
}
