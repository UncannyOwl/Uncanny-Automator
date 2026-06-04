<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Run_Snapshot;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Run_Snapshot_Store;
use Uncanny_Automator\App\Events\Dispatcher;

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
	 * Default time-to-live in hours for snapshots.
	 *
	 * @var int
	 */
	const DEFAULT_TTL_HOURS = 48;

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
	public function capture( int $recipe_log_id, int $recipe_id, int $user_id, array $snapshot_data, int $ttl_hours = self::DEFAULT_TTL_HOURS ): ?int {

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
			return null;
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

		$checksum = md5( $json );
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT snapshot_data, checksum FROM {$this->wpdb->prefix}uap_run_snapshots WHERE recipe_log_id = %d AND expires_at > %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT 1 FROM {$this->wpdb->prefix}uap_run_snapshots WHERE recipe_log_id = %d AND expires_at > %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT s.recipe_log_id, s.user_id, s.created_at, s.expires_at, r.completed
				FROM {$this->wpdb->prefix}uap_run_snapshots s
				INNER JOIN {$this->wpdb->prefix}uap_recipe_log r ON r.ID = s.recipe_log_id
				WHERE s.recipe_id = %d AND s.expires_at > %s
				ORDER BY s.created_at DESC
				LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->wpdb->prefix}uap_run_snapshots WHERE expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				current_time( 'mysql', true )
			)
		);

		return is_int( $deleted ) ? $deleted : 0;
	}
}
