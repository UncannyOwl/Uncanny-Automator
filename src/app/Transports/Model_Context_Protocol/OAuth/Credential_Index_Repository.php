<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

/**
 * Direct token-hash index for MCP and first-party Agent credentials.
 *
 * The option key is derived from a one-way token hash, so validation is a
 * single indexed WordPress option lookup instead of a scan across user meta.
 */
final class Credential_Index_Repository {

	private const OPTION_PREFIX = 'automator_mcp_credential_v2_';

	/**
	 * Read a credential record by SHA-256 token hash.
	 */
	public function get( string $token_hash ): ?array {
		if ( ! $this->is_hash( $token_hash ) ) {
			return null;
		}

		$record = get_option( $this->option_name( $token_hash ), null );

		return is_array( $record ) ? $record : null;
	}

	/**
	 * Store a credential record without autoloading it.
	 */
	public function put( string $token_hash, array $record ): bool {
		if ( ! $this->is_hash( $token_hash ) ) {
			return false;
		}

		$option = $this->option_name( $token_hash );
		if ( null === get_option( $option, null ) ) {
			return add_option( $option, $record, '', false );
		}

		return update_option( $option, $record, false );
	}

	/**
	 * Delete an indexed credential.
	 */
	public function delete( string $token_hash ): bool {
		global $wpdb;

		if ( ! $this->is_hash( $token_hash ) ) {
			return false;
		}

		$option = $this->option_name( $token_hash );
		// delete_option() can cache absence before its database DELETE fails.
		// A retry must inspect the persisted row instead of trusting that cache.
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live state read, must not be cached: failed deletion retries require proven absence.
		$stored = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from wpdb.
				$option
			)
		);
		if ( '' !== $wpdb->last_error ) {
			return false;
		}
		return null === $stored || delete_option( $option );
	}

	/**
	 * Update last-used time without touching encrypted legacy rollback data.
	 */
	public function touch( string $token_hash, int $timestamp ): bool {
		global $wpdb;

		$record = $this->get( $token_hash );
		if ( null === $record ) {
			return false;
		}

		$option              = $this->option_name( $token_hash );
		$serialized_current  = maybe_serialize( $record );
		$record['last_used'] = $timestamp;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic compare-and-swap write; the cache is invalidated explicitly right after.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is derived from wpdb.
				maybe_serialize( $record ),
				$option,
				$serialized_current
			)
		);

		wp_cache_delete( $option, 'options' );

		if ( false === $updated ) {
			return false;
		}

		if ( 1 === $updated ) {
			return true;
		}

		// Another validation may have won the compare-and-swap. Accept its newer
		// timestamp, but never insert a row that revocation already removed.
		$current = $this->get( $token_hash );

		return null !== $current
			&& isset( $current['last_used'] )
			&& (int) $current['last_used'] >= $timestamp;
	}
	/**
	 * Option name.
	 *
	 * @param string $token_hash The destination.
	 * @return string
	 */
	private function option_name( string $token_hash ): string {
		return self::OPTION_PREFIX . $token_hash;
	}
	/**
	 * Is hash.
	 *
	 * @param string $token_hash The destination.
	 * @return bool
	 */
	private function is_hash( string $token_hash ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/D', $token_hash );
	}
}
