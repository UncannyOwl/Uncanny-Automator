<?php
/**
 * Read the complete mutation set of LearnDash's native relationship cleanup.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */

declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Learndash\Mcp;

use RuntimeException;
use wpdb;

/**
 * Locks and reads the references touched by LearnDash's native cleanup.
 *
 * These queries establish the authorization and locking boundary only.
 * LearnDash's model remains responsible for reading and saving the curriculum.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */
final class WP_LearnDash_Curriculum_References implements LearnDash_Curriculum_References {

	/**
	 * Database connection for the current WordPress site.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Initialize the adapter with the current site's connection.
	 *
	 * @param wpdb $wpdb WordPress database connection.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Detect an existing transaction without changing its data or lifecycle.
	 *
	 * @return bool Whether the caller already owns a transaction.
	 *
	 * @throws RuntimeException When the transaction state cannot be determined.
	 */
	public function has_transaction(): bool {
		// Releasing a savepoint succeeds only inside a transaction. A unique name
		// avoids replacing the caller's savepoints; no COMMIT or ROLLBACK is needed.
		$savepoint = 'automator_curriculum_' . bin2hex( random_bytes( 8 ) );
		$errors    = $this->wpdb->suppress_errors( true );
		try {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internally generated hexadecimal identifier.
			if ( false === $this->wpdb->query( 'SAVEPOINT ' . $savepoint ) ) {
				throw new RuntimeException( 'The caller transaction state could not be inspected.' );
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internally generated hexadecimal identifier.
			if ( false !== $this->wpdb->query( 'RELEASE SAVEPOINT ' . $savepoint ) ) {
				return true;
			}
			if ( false === strpos( $this->wpdb->last_error, 'SAVEPOINT ' . $savepoint . ' does not exist' ) ) {
				throw new RuntimeException( 'The caller transaction state could not be inspected.' );
			}
			return false;
		} finally {
			$this->wpdb->suppress_errors( $errors );
		}
	}

	/**
	 * Find all posts whose links may be changed by native reconciliation.
	 *
	 * @param int      $course_id  Target course ID.
	 * @param string[] $step_types Native course-step post types for legacy ownership links.
	 * @param int      $limit      Maximum number of referenced posts to return.
	 * @param bool     $lock       Whether to lock matching rows inside a transaction.
	 *
	 * @return int[] Referenced post IDs in ascending order.
	 *
	 * @throws RuntimeException When the references cannot be read.
	 */
	public function post_ids( int $course_id, array $step_types, int $limit, bool $lock = false ): array {
		// Shared cleanup removes every matching reverse key, even on trashed or
		// retyped posts. Filtering those out would miss records we must authorize.
		$args = array_merge( array( 'ld_course_' . $course_id, 'course_id', $course_id ), $step_types, array( $limit ) );
		// The dynamic fragment contains placeholders only, never caller values.
		$query = $this->wpdb->prepare(
			"SELECT DISTINCT pm.post_id FROM {$this->wpdb->postmeta} pm LEFT JOIN {$this->wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s OR (pm.meta_key = %s AND pm.meta_value = %d AND p.post_type IN (" . implode( ', ', array_fill( 0, count( $step_types ), '%s' ) ) . ')) ORDER BY pm.post_id LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$args
		);
		$ids   = $this->wpdb->get_col( $query . ( $lock ? ' FOR UPDATE' : '' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( '' !== $this->wpdb->last_error ) {
			throw new RuntimeException( 'LearnDash relationship references could not be read.' );
		}
		return array_map( 'intval', $ids );
	}

	/**
	 * Lock posts and metadata, then prime caches from the locked values.
	 *
	 * @param int[] $post_ids Posts whose current state must be used for validation.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the posts or metadata cannot be locked.
	 */
	public function lock_post_ids( array $post_ids ): void {
		$db       = $this->wpdb;
		$post_ids = array_unique( array_map( 'intval', $post_ids ) );
		// A consistent lock order reduces deadlocks when courses share steps.
		sort( $post_ids, SORT_NUMERIC );
		foreach ( $post_ids as $post_id ) {
			$post = $db->get_row( $db->prepare( "SELECT * FROM {$db->posts} WHERE ID = %d FOR UPDATE", $post_id ) );
			if ( '' !== $this->wpdb->last_error ) {
				throw new RuntimeException( 'LearnDash post references could not be locked.' );
			}
			// Lock the whole post_id range so another save cannot insert a missing
			// ownership key between validation and the native curriculum update.
			$rows = $db->get_results( $db->prepare( "SELECT meta_key, meta_value FROM {$db->postmeta} WHERE post_id = %d ORDER BY meta_id FOR UPDATE", $post_id ) );
			if ( '' !== $this->wpdb->last_error ) {
				throw new RuntimeException( 'LearnDash metadata could not be locked.' );
			}
			// Native validation must see these locked values, not a warmed object cache.
			clean_post_cache( $post_id );
			if ( $post ) {
				wp_cache_set( $post_id, $post, 'posts' );
			}
			$meta = array();
			foreach ( $rows as $row ) {
				$meta[ $row->meta_key ][] = $row->meta_value;
			}
			wp_cache_set( $post_id, $meta, 'post_meta' );
		}
	}
}
