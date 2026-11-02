<?php
/**
 * Post meta rows read from the database and changed through the core meta API.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Content_Edit_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Meta_Store_Unavailable;

/**
 * Reads bypass get_post_meta(), which decodes with maybe_unserialize() and reads
 * a cache that direct database writes can leave stale. Writes use the same core
 * calls as the Custom Fields panel, so sanitizers and meta hooks still run.
 */
final class WP_Post_Meta_Store implements Post_Meta_Store {

	private \wpdb $wpdb;
	private Post_Content_Edit_Store $lock;

	/** Use the current site's database connection. */
	public function __construct( \wpdb $wpdb, ?Post_Content_Edit_Store $lock = null ) {
		$this->wpdb = $wpdb;
		$this->lock = $lock ?? new WP_Post_Content_Edit_Store( $wpdb );
	}

	/** Share the content editor's post-row lock and transaction. */
	public function with_post_lock( int $post_id, callable $edit ) {
		$result = $this->lock->with_post_lock( $post_id, $edit );
		if ( ! is_wp_error( $result ) ) {
			return $result;
		}
		if ( 'post_content_edit_storage_unavailable' === $result->get_error_code() ) {
			return new \WP_Error( 'post_meta_storage_unavailable', 'Meta editing needs transactional WordPress storage. Nothing was saved.', array( 'status' => 503 ) );
		}
		if ( 'post_content_edit_unverified' === $result->get_error_code() ) {
			return new \WP_Error( 'post_meta_unverified', 'The save could not be confirmed. Read the meta before retrying.', array( 'status' => 503 ) );
		}
		return $result;
	}

	/** Every row's ID, key and size, without values. */
	public function key_index( int $post_id ): array {
		$db   = $this->wpdb;
		$rows = $this->answered( $db->get_results( $db->prepare( "SELECT meta_id, meta_key, LENGTH(meta_value) AS bytes FROM {$db->postmeta} WHERE post_id = %d ORDER BY meta_id ASC", $post_id ), ARRAY_A ) );
		return array_map(
			static function ( array $row ): array {
				return array(
					'meta_id' => (int) $row['meta_id'],
					'meta_key' => (string) $row['meta_key'],
					'bytes' => (int) $row['bytes'],
				);
			},
			$rows
		);
	}

	/** Rows matching the keys under the column collation. */
	public function rows( int $post_id, array $keys, bool $lock = false ): array {
		if ( array() === $keys ) {
			return array();
		}
		$db     = $this->wpdb;
		$places = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$sql    = $db->prepare( "SELECT meta_id, meta_key, meta_value FROM {$db->postmeta} WHERE post_id = %d AND meta_key IN ($places) ORDER BY meta_id ASC", array_merge( array( $post_id ), array_values( $keys ) ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per key.
		// A locking read sees the latest committed rows, not the transaction's snapshot.
		$rows = $this->answered( $db->get_results( $lock ? $sql . ' FOR UPDATE' : $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		return array_map(
			static function ( array $row ): array {
				return array(
					'meta_id' => (int) $row['meta_id'],
					'meta_key' => (string) $row['meta_key'],
					'meta_value' => $row['meta_value'],
				);
			},
			$rows
		);
	}

	/** Each matching row's ID, key and size, without values. */
	public function key_sizes( int $post_id, array $keys, bool $lock = false ): array {
		if ( array() === $keys ) {
			return array();
		}
		$db     = $this->wpdb;
		$places = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		$sql    = $db->prepare( "SELECT meta_id, meta_key, LENGTH(meta_value) AS bytes FROM {$db->postmeta} WHERE post_id = %d AND meta_key IN ($places) ORDER BY meta_id ASC", array_merge( array( $post_id ), array_values( $keys ) ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One placeholder per key.
		$rows   = $this->answered( $db->get_results( $lock ? $sql . ' FOR UPDATE' : $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		return array_map(
			static function ( array $row ): array {
				return array(
					'meta_id' => (int) $row['meta_id'],
					'meta_key' => (string) $row['meta_key'],
					'bytes' => (int) $row['bytes'],
				);
			},
			$rows
		);
	}

	/** The first rows of one key, leaving values over $max_bytes in the database. */
	public function preview_rows( int $post_id, string $key, int $max_bytes, int $limit ): array {
		$db = $this->wpdb;
		// The collation also matches other spellings; BINARY keeps the limit for this one.
		$rows = $this->answered( $db->get_results( $db->prepare( "SELECT meta_id, meta_key, IF(LENGTH(meta_value) > %d, NULL, meta_value) AS meta_value, LENGTH(meta_value) AS bytes FROM {$db->postmeta} WHERE post_id = %d AND meta_key = %s AND BINARY meta_key = BINARY %s ORDER BY meta_id ASC LIMIT %d", $max_bytes, $post_id, $key, $key, $limit ), ARRAY_A ) );
		return array_map(
			static function ( array $row ) use ( $max_bytes ): array {
				return array(
					'meta_id' => (int) $row['meta_id'],
					'meta_key' => (string) $row['meta_key'],
					'meta_value' => $row['meta_value'],
					'bytes' => (int) $row['bytes'],
					'omitted' => (int) $row['bytes'] > $max_bytes,
				);
			},
			$rows
		);
	}

	/**
	 * Refuse a failed read instead of treating it as no rows, then release wpdb's
	 * own copy of the result, which can hold large values.
	 *
	 * @param mixed $rows Query result.
	 * @return array
	 * @throws Post_Meta_Store_Unavailable When the database did not answer.
	 */
	private function answered( $rows ): array {
		$failed = '' !== $this->wpdb->last_error || ! is_array( $rows );
		$this->wpdb->flush();
		if ( $failed ) {
			throw new Post_Meta_Store_Unavailable( 'The database did not answer a post meta read.' );
		}
		return $rows;
	}

	/** Add one row. */
	public function add( int $post_id, string $key, $value ) {
		// add_metadata() unslashes the key and value; slash both so backslashes survive.
		return add_post_meta( $post_id, wp_slash( $key ), wp_slash( $value ) );
	}

	/** Replace one row's value. */
	public function update( int $meta_id, $value ): bool {
		// Unlike update_post_meta(), update_metadata_by_mid() does not unslash.
		return update_metadata_by_mid( 'post', $meta_id, $value );
	}

	/** Delete one row. */
	public function delete( int $meta_id ): bool {
		return delete_metadata_by_mid( 'post', $meta_id );
	}

	/**
	 * Notify hosts and cache plugins through the normal WordPress post-save lifecycle.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether WordPress saved it.
	 */
	public function touch_post( int $post_id ): bool {
		// There is no universal full-page cache purge API: hosts and plugins use different
		// post-save hooks, so clean_post_cache() alone is insufficient. Keep this lifecycle.
		// Tradeoff: save_post listeners (including recipes and webhooks) run, and WordPress
		// re-filters existing content for users without unfiltered_html, potentially stripping
		// scripts or iframes. Passing only ID does not guarantee unchanged post content.
		return ! is_wp_error( wp_update_post( array( 'ID' => $post_id ), true ) );
	}
}
