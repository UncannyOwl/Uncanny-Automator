<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

// phpcs:disable PSR2.Methods.FunctionClosingBrace.SpacingBeforeClose -- Deliberate breathing room at function boundaries.

/**
 * Provides atomic, site-local lock ownership through the options table.
 *
 * WordPress add_option() may overwrite a row during concurrent insertion, and
 * its cache can return an owner that no longer exists in the database. This
 * boundary therefore performs direct unique inserts and value-conditional
 * deletes for acquisition, stale takeover, and release.
 *
 * @since 7.4.1
 */
final class Atomic_Option_Lock {

	/**
	 * WordPress database adapter.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null $wpdb WordPress database adapter.
	 */
	public function __construct( $wpdb = null ) {

		$this->wpdb = null !== $wpdb ? $wpdb : $GLOBALS['wpdb'];

	}

	/**
	 * Acquire an option-backed lock through a unique database insert.
	 *
	 * @param string    $lock_name Lock option name.
	 * @param int       $ttl Lock lifetime in seconds.
	 * @param int|float $wait_seconds Maximum seconds to wait.
	 * @return string|false Lock owner identifier or false on timeout.
	 */
	public function acquire( string $lock_name, int $ttl, $wait_seconds ) {

		$owner    = wp_generate_uuid4();
		$deadline = microtime( true ) + max( 0, (float) $wait_seconds );

		while ( true ) {
			$now  = microtime( true );
			$lock = array(
				'owner'      => $owner,
				'expires_at' => $now + max( 1, $ttl ),
			);

			if ( $this->insert( $lock_name, $lock ) ) {
				return $owner;
			}

			if ( $this->remove_stale_lock( $lock_name, $now ) ) {
				continue;
			}

			if ( $now >= $deadline ) {
				return false;
			}

			usleep( 50000 );
		}

	}

	/**
	 * Release a lock only if the database still contains this owner's value.
	 *
	 * @param string $lock_name Lock option name.
	 * @param string $owner Expected owner identifier.
	 * @return bool Whether the owned lock was removed.
	 */
	public function release( string $lock_name, string $owner ): bool {

		$stored_value = $this->read_stored_value( $lock_name );

		if ( false === $stored_value ) {
			return false;
		}

		$lock = maybe_unserialize( $stored_value );

		if ( ! $this->is_owned_by( $lock, $owner ) ) {
			return false;
		}

		return $this->delete_if_unchanged( $lock_name, $stored_value );

	}

	// -------------------------------------------------------------------------
	// Atomic persistence.
	// -------------------------------------------------------------------------

	/**
	 * Insert one lock without permitting duplicate-key updates.
	 *
	 * @param string $lock_name Lock option name.
	 * @param array  $lock Lock value.
	 * @return bool Whether this request inserted the unique row.
	 */
	private function insert( string $lock_name, array $lock ): bool {

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $this->wpdb->prepare(
			"INSERT IGNORE INTO {$this->wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
			$lock_name,
			maybe_serialize( $lock ),
			'no'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$inserted = 1 === (int) $this->wpdb->query( $query );

		if ( $inserted ) {
			$this->clear_option_cache( $lock_name );
		}

		return $inserted;

	}

	/**
	 * Read the current lock value without consulting the option cache.
	 *
	 * @param string $lock_name Lock option name.
	 * @return string|false Serialized option value or false when absent.
	 */
	private function read_stored_value( string $lock_name ) {

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $this->wpdb->prepare(
			"SELECT option_value FROM {$this->wpdb->options} WHERE option_name = %s LIMIT 1",
			$lock_name
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$stored_value = $this->wpdb->get_var( $query );

		return is_string( $stored_value ) ? $stored_value : false;

	}

	/**
	 * Delete exactly the lock value that was inspected.
	 *
	 * A contender that replaced an expired value between the read and delete
	 * remains protected because its serialized value no longer matches.
	 *
	 * @param string $lock_name Lock option name.
	 * @param string $stored_value Exact serialized value previously read.
	 * @return bool Whether that exact row was deleted.
	 */
	private function delete_if_unchanged( string $lock_name, string $stored_value ): bool {

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $this->wpdb->prepare(
			"DELETE FROM {$this->wpdb->options} WHERE option_name = %s AND BINARY option_value = %s",
			$lock_name,
			$stored_value
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$deleted = 1 === (int) $this->wpdb->query( $query );

		if ( $deleted ) {
			$this->clear_option_cache( $lock_name );
		}

		return $deleted;

	}

	// -------------------------------------------------------------------------
	// Ownership and expiry.
	// -------------------------------------------------------------------------

	/**
	 * Remove a malformed or expired lock only if its value is unchanged.
	 *
	 * @param string    $lock_name Lock option name.
	 * @param int|float $now Current timestamp.
	 * @return bool Whether the inspected stale row was removed.
	 */
	private function remove_stale_lock( string $lock_name, $now ): bool {

		$stored_value = $this->read_stored_value( $lock_name );

		if ( false === $stored_value ) {
			return false;
		}

		$lock = maybe_unserialize( $stored_value );

		if ( ! $this->is_stale( $lock, $now ) ) {
			return false;
		}

		return $this->delete_if_unchanged( $lock_name, $stored_value );

	}

	/**
	 * Determine whether a stored lock is malformed or expired.
	 *
	 * @param mixed     $lock Stored lock value.
	 * @param int|float $now Current timestamp.
	 * @return bool Whether another request may replace the lock.
	 */
	private function is_stale( $lock, $now ): bool {

		if ( ! is_array( $lock ) || ! isset( $lock['owner'], $lock['expires_at'] ) ) {
			return true;
		}

		if ( ! is_string( $lock['owner'] ) || ! is_numeric( $lock['expires_at'] ) ) {
			return true;
		}

		return (float) $lock['expires_at'] <= (float) $now;

	}

	/**
	 * Determine whether a lock still belongs to the supplied request.
	 *
	 * @param mixed  $lock Stored lock value.
	 * @param string $owner Expected owner identifier.
	 * @return bool Whether release is authorized.
	 */
	private function is_owned_by( $lock, string $owner ): bool {

		return is_array( $lock )
			&& isset( $lock['owner'] )
			&& is_string( $lock['owner'] )
			&& hash_equals( $lock['owner'], $owner );

	}

	/**
	 * Invalidate WordPress option caches after direct persistence.
	 *
	 * @param string $lock_name Lock option name.
	 * @return void
	 */
	private function clear_option_cache( string $lock_name ): void {

		wp_cache_delete( $lock_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

	}
}
