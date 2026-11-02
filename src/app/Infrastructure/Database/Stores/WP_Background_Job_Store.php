<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Background_Job_Store;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Background_Job;

/**
 * wpdb implementation of the background job store over `uap_async_jobs`.
 */
final class WP_Background_Job_Store implements Background_Job_Store {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Bind to a database connection.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * The prefixed table name.
	 *
	 * @return string
	 */
	private function table(): string {
		return $this->wpdb->prefix . 'uap_async_jobs';
	}

	/**
	 * {@inheritDoc}
	 */
	public function record( Background_Job $job ): Background_Job {
		$existing = $this->find_by_job_id( $job->job_id() );
		if ( null !== $existing ) {
			return $existing;
		}

		$row                  = $job->to_row();
		$row['dispatched_at'] = '' === $row['dispatched_at'] ? gmdate( 'Y-m-d H:i:s' ) : $row['dispatched_at'];
		$row['claimed_at']    = '' === $row['claimed_at'] ? null : $row['claimed_at'];
		$row['finished_at']   = '' === $row['finished_at'] ? null : $row['finished_at'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table write.
		$inserted = $this->wpdb->insert(
			$this->table(),
			$row,
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			// The unique key on job_id may have lost a race with a second dispatch of the
			// same job; the row that won is the record. Anything else is a real failure.
			$existing = $this->find_by_job_id( $job->job_id() );
			if ( null !== $existing ) {
				return $existing;
			}
			throw new \RuntimeException( esc_html( sprintf( 'Could not record background job %s.', $job->job_id() ) ) );
		}

		return Background_Job::from_row( array_merge( $row, array( 'ID' => (int) $this->wpdb->insert_id ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function find_by_job_id( string $job_id ): ?Background_Job {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_async_jobs WHERE job_id = %s LIMIT 1",
				$job_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? Background_Job::from_row( $row ) : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_attempts( int $recipe_log_id, int $action_id ): int {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}uap_async_jobs WHERE recipe_log_id = %d AND action_id = %d",
				$recipe_log_id,
				$action_id
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function open_jobs_dispatched_before( int $seconds, int $limit = 50 ): array {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_async_jobs WHERE status = %s AND dispatched_at <= %s ORDER BY dispatched_at ASC, ID ASC LIMIT %d",
				Background_Job::STATUS_OPEN,
				gmdate( 'Y-m-d H:i:s', time() - $seconds ),
				max( 1, $limit )
			),
			ARRAY_A
		);

		return array_map( array( Background_Job::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function open_site_jobs_after( int $after_id, int $seconds, int $limit ): array {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_async_jobs WHERE status = %s AND origin = %s AND dispatched_at <= %s AND ID > %d ORDER BY ID ASC LIMIT %d",
				Background_Job::STATUS_OPEN,
				Background_Job::ORIGIN_SITE,
				gmdate( 'Y-m-d H:i:s', time() - $seconds ),
				max( 0, $after_id ),
				max( 1, $limit )
			),
			ARRAY_A
		);

		return array_map( array( Background_Job::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function mark_finished( int $id, string $status, string $source ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table write.
		$this->wpdb->update(
			$this->table(),
			array(
				'status'      => $status,
				'source'      => $source,
				'finished_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'ID' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_remembered( int $id, array $remembered ): bool {
		$json = wp_json_encode( $remembered );
		if ( false === $json ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table write.
		$written = $this->wpdb->update(
			$this->table(),
			array( 'remembered' => $json ),
			array( 'ID' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $written;
	}

	/**
	 * {@inheritDoc}
	 */
	public function claim( int $id, string $source ): bool {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_async_jobs SET status = %s, source = %s, claimed_at = %s WHERE ID = %d AND status = %s",
				Background_Job::STATUS_PROCESSING,
				$source,
				gmdate( 'Y-m-d H:i:s' ),
				$id,
				Background_Job::STATUS_OPEN
			)
		);
		if ( false === $updated ) {
			// A query error is not a lost race: the row never moves, and every completion would be dropped as
			// "already claimed" without this line.
			automator_log( sprintf( 'Background job row %d could not be claimed: %s', $id, $this->wpdb->last_error ), 'Recipe_Runner' );
		}

		return 1 === (int) $updated;
	}

	/**
	 * {@inheritDoc}
	 */
	public function touch_claim( int $id ): void {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_async_jobs SET claimed_at = %s WHERE ID = %d AND status = %s",
				gmdate( 'Y-m-d H:i:s' ),
				$id,
				Background_Job::STATUS_PROCESSING
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function release_stale_claim( int $id, int $seconds ): bool {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_async_jobs SET status = %s, claimed_at = NULL, redrives = redrives + 1 WHERE ID = %d AND status = %s AND claimed_at <= %s",
				Background_Job::STATUS_OPEN,
				$id,
				Background_Job::STATUS_PROCESSING,
				gmdate( 'Y-m-d H:i:s', time() - $seconds )
			)
		);

		return 1 === (int) $updated;
	}

	/**
	 * {@inheritDoc}
	 */
	public function release_claim( int $id ): bool {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}uap_async_jobs SET status = %s, claimed_at = NULL WHERE ID = %d AND status = %s",
				Background_Job::STATUS_OPEN,
				$id,
				Background_Job::STATUS_PROCESSING
			)
		);

		return 1 === (int) $updated;
	}

	/**
	 * {@inheritDoc}
	 */
	public function processing_jobs_claimed_before( int $seconds, int $limit = 50 ): array {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; job state is read and written live and must never be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}uap_async_jobs WHERE status = %s AND claimed_at <= %s ORDER BY claimed_at ASC, ID ASC LIMIT %d",
				Background_Job::STATUS_PROCESSING,
				gmdate( 'Y-m-d H:i:s', time() - $seconds ),
				max( 1, $limit )
			),
			ARRAY_A
		);

		return array_map( array( Background_Job::class, 'from_row' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_finished_for_recipe( int $recipe_id ): int {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table write.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}uap_async_jobs WHERE recipe_id = %d AND status IN ( %s, %s, %s )",
				$recipe_id,
				Background_Job::STATUS_COMPLETED,
				Background_Job::STATUS_FAILED,
				Background_Job::STATUS_LOST
			)
		);

		if ( false === $deleted ) {
			throw new Store_Exception( sprintf( 'The finished background jobs of recipe %d could not be deleted: %s', absint( $recipe_id ), esc_html( $wpdb->last_error ) ) );
		}

		return (int) $deleted;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_finished_for_run( int $recipe_log_id ): int {
		$wpdb = $this->wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table write.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}uap_async_jobs WHERE recipe_log_id = %d AND status IN ( %s, %s, %s )",
				$recipe_log_id,
				Background_Job::STATUS_COMPLETED,
				Background_Job::STATUS_FAILED,
				Background_Job::STATUS_LOST
			)
		);

		if ( false === $deleted ) {
			throw new Store_Exception( sprintf( 'The finished background jobs of run %d could not be deleted: %s', absint( $recipe_log_id ), esc_html( $wpdb->last_error ) ) );
		}

		return (int) $deleted;
	}
}
