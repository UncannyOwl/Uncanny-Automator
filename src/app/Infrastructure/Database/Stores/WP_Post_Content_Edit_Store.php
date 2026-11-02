<?php
/**
 * Serialize content edits with native WordPress post-row writes.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Content_Edit_Store;

final class WP_Post_Content_Edit_Store implements Post_Content_Edit_Store {

	private \wpdb $wpdb;

	/** Use the current site's database connection. */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Lock, refresh, run native saving, then release the local transaction.
	 * Existing caller transactions are preserved with a savepoint.
	 */
	public function with_post_lock( int $post_id, callable $edit ) {
		$db      = $this->wpdb;
		$engines = $db->get_col( $db->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)', $db->posts, $db->postmeta ) );
		if ( 2 !== count( $engines ) || array( 'InnoDB' ) !== array_values( array_unique( $engines ) ) ) {
			return $this->unavailable();
		}
		$savepoint = 'automator_content_' . bin2hex( random_bytes( 8 ) );
		// SAVEPOINT is ignored outside a transaction; RELEASE then reports that
		// it does not exist. Probe without START committing a caller's transaction.
		$errors = $db->suppress_errors( true );
		try {
			if ( false === $db->query( 'SAVEPOINT ' . $savepoint ) ) {
				return $this->unavailable();
			}
			$nested = false !== $db->query( 'RELEASE SAVEPOINT ' . $savepoint );
		} finally {
			$db->suppress_errors( $errors );
		}
		$start = $nested ? 'SAVEPOINT ' . $savepoint : 'START TRANSACTION';
		if ( false === $db->query( $start ) ) {
			return $this->unavailable();
		}
		$complete = false;
		try {
			$post = $db->get_row( $db->prepare( "SELECT * FROM {$db->posts} WHERE ID = %d FOR UPDATE", $post_id ) );
			if ( '' !== $db->last_error ) {
				return $this->unavailable();
			}
			if ( null === $post ) {
				return new \WP_Error( 'rest_post_invalid_id', 'The post could not be locked.', array( 'status' => 404 ) );
			}
			clean_post_cache( $post_id );
			// A locking read sees the current row even when an existing transaction
			// has an older snapshot. Native get_post must use that same current row.
			wp_cache_set( $post_id, $post, 'posts' );
			$result = $edit();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$finish = $nested ? 'RELEASE SAVEPOINT ' . $savepoint : 'COMMIT';
			if ( false === $db->query( $finish ) ) {
				return new \WP_Error( 'post_content_edit_unverified', 'The save could not be confirmed. Read the post before retrying.', array( 'status' => 503 ) );
			}
			$complete = true;
			return $result;
		} finally {
			if ( ! $complete ) {
				$db->query( $nested ? 'ROLLBACK TO SAVEPOINT ' . $savepoint : 'ROLLBACK' );
				if ( $nested ) {
					$db->query( 'RELEASE SAVEPOINT ' . $savepoint );
				}
			}
			clean_post_cache( $post_id );
		}
	}

	/** Refuse a write when the adapter cannot guarantee the row lock. */
	private function unavailable(): \WP_Error {
		return new \WP_Error( 'post_content_edit_storage_unavailable', 'Content editing needs transactional WordPress posts storage. Nothing was saved.', array( 'status' => 503 ) );
	}
}
