<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Run_Snapshot;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Run_Snapshot_Store;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;

/**
 * WordPress implementation of {@see Run_Snapshot_Store}.
 *
 * Persists compressed trigger context for replay into uap_run_snapshots.
 *
 * Phase 5 of the api-layer refactor moved this from
 * `infrastructure/run-snapshot/Run_Snapshot_Store` to its correct home in
 * `database/stores/`, added constructor `$wpdb` injection, and changed
 * `get()` to return a typed {@see Run_Snapshot} value object instead of
 * a raw `?array`.
 *
 * @since 7.4.0
 * @package Uncanny_Automator\App\Infrastructure\Database\Stores
 */
final class WP_Run_Snapshot_Store implements Run_Snapshot_Store {

	/**
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * @param \wpdb $wpdb wpdb instance.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * @inheritDoc
	 */
	public function capture( int $recipe_log_id, int $recipe_id, int $user_id, array $snapshot_data, int $ttl_hours = self::DEFAULT_TTL_HOURS, bool $allow_skip = true ): ?int {

		// Per-recipe override from post meta.
		$recipe_ttl = get_post_meta( $recipe_id, '_automator_snapshot_ttl_hours', true );

		if ( '' !== $recipe_ttl ) {
			$ttl_hours = (int) $recipe_ttl;
		}

		/**
		 * Filter the snapshot TTL in hours.
		 *
		 * @param int $ttl_hours     The TTL in hours.
		 * @param int $recipe_id     The recipe post ID.
		 * @param int $recipe_log_id The recipe log ID.
		 */
		$ttl_hours = (int) Dispatcher::filter( 'automator_run_snapshot_ttl_hours', $ttl_hours, $recipe_id, $recipe_log_id );

		// TTL of 0 = skip capture entirely.
		if ( 0 === $ttl_hours ) {
			if ( $allow_skip ) {
				return null;
			}
			$ttl_hours = self::DEFAULT_TTL_HOURS;
		}

		$json = wp_json_encode( $snapshot_data );

		if ( false === $json ) {
			automator_log( 'Run snapshot capture failed: wp_json_encode returned false (non-UTF-8 data likely). Recipe log ID: ' . $recipe_log_id, 'ERROR' );
			return null;
		}

		$compressed = gzcompress( $json );

		if ( false === $compressed ) {
			automator_log( 'Run snapshot capture failed: gzcompress returned false. Recipe log ID: ' . $recipe_log_id, 'ERROR' );
			return null;
		}

		$checksum   = md5( $json );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + ( $ttl_hours * HOUR_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->replace(
			$this->wpdb->prefix . 'uap_run_snapshots',
			array(
				'recipe_log_id' => $recipe_log_id,
				'recipe_id'     => $recipe_id,
				'user_id'       => $user_id,
				'snapshot_data' => $compressed,
				'checksum'      => $checksum,
				'expires_at'    => $expires_at,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$insert_id = (int) $this->wpdb->insert_id;

		return 0 < $insert_id ? $insert_id : null;
	}

	/**
	 * @inheritDoc
	 */
	public function get( int $recipe_log_id ): ?Run_Snapshot {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT snapshot_data, checksum FROM {$wpdb->prefix}uap_run_snapshots WHERE recipe_log_id = %d AND expires_at > %s LIMIT 1",
				$recipe_log_id,
				current_time( 'mysql', true )
			)
		);

		if ( null === $row ) {
			return null;
		}

		$decompressed = gzuncompress( $row->snapshot_data );

		if ( false === $decompressed ) {
			return null;
		}

		// Verify checksum integrity.
		if ( md5( $decompressed ) !== $row->checksum ) {
			return null;
		}

		$data = json_decode( $decompressed, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		return new Run_Snapshot( $data );
	}

	/**
	 * @inheritDoc
	 */
	public function is_replayable( int $recipe_log_id ): bool {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}uap_run_snapshots WHERE recipe_log_id = %d AND expires_at > %s LIMIT 1",
				$recipe_log_id,
				current_time( 'mysql', true )
			)
		);

		return null !== $result;
	}

	/**
	 * @inheritDoc
	 */
	public function get_replayable_runs( int $recipe_id, int $limit = 50 ): array {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.recipe_log_id, s.user_id, s.created_at, s.expires_at, r.completed
				FROM {$wpdb->prefix}uap_run_snapshots s
				INNER JOIN {$wpdb->prefix}uap_recipe_log r ON r.ID = s.recipe_log_id
				WHERE s.recipe_id = %d AND s.expires_at > %s
				ORDER BY s.created_at DESC
				LIMIT %d",
				$recipe_id,
				current_time( 'mysql', true ),
				$limit
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * @inheritDoc
	 */
	public function purge_expired(): int {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}uap_run_snapshots WHERE expires_at < %s",
				current_time( 'mysql', true )
			)
		);

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * @inheritDoc
	 */
	public function purge_recipe( int $recipe_id ): int {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}uap_run_snapshots WHERE recipe_id = %d",
				$recipe_id
			)
		);

		if ( false === $deleted ) {
			throw new Store_Exception( sprintf( 'The run snapshots of recipe %d could not be deleted: %s', absint( $recipe_id ), esc_html( $wpdb->last_error ) ) );
		}

		return (int) $deleted;
	}

	/**
	 * @inheritDoc
	 */
	public function extend_expiry( int $recipe_log_id, string $expires_at ): void {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_run_snapshots SET expires_at = %s WHERE recipe_log_id = %d AND expires_at < %s",
				$expires_at,
				$recipe_log_id,
				$expires_at
			)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function purge_run( int $recipe_log_id ): int {

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}uap_run_snapshots WHERE recipe_log_id = %d",
				$recipe_log_id
			)
		);

		if ( false === $deleted ) {
			throw new Store_Exception( sprintf( 'The run snapshot of recipe log %d could not be deleted: %s', absint( $recipe_log_id ), esc_html( $wpdb->last_error ) ) );
		}

		return (int) $deleted;
	}

	/**
	 * @inheritDoc
	 */
	public function purge_user( int $user_id ): int {

		if ( $user_id <= 0 ) {
			return 0;
		}

		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Write to Automator's own table.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}uap_run_snapshots WHERE user_id = %d",
				$user_id
			)
		);

		if ( false === $deleted ) {
			throw new Store_Exception( sprintf( 'The run snapshots of user %d could not be deleted: %s', absint( $user_id ), esc_html( $wpdb->last_error ) ) );
		}

		return (int) $deleted;
	}
}
