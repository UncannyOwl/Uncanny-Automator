<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

// phpcs:disable PSR2.Methods.FunctionClosingBrace.SpacingBeforeClose -- Deliberate breathing room at function boundaries.

/**
 * Repository for one user's site-scoped MCP credential aggregate.
 *
 * Every aggregate mutation is serialized through a site-local option lock.
 * Existing rows are updated by metadata ID so a row deleted after the read
 * cannot be silently recreated by WordPress's update_user_meta() insert path.
 *
 * @since 7.4.1
 */
final class Token_Credential_Repository {

	/**
	 * Maximum lifetime of a credential mutation lock.
	 *
	 * @since 7.4.1
	 * @var int
	 */
	const LOCK_TTL = 30;

	/**
	 * Maximum seconds to wait for an in-flight credential mutation.
	 *
	 * @since 7.4.1
	 * @var int
	 */
	const LOCK_WAIT = 2;

	/**
	 * WordPress database adapter.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Atomic site-option lock boundary.
	 *
	 * @var Atomic_Option_Lock
	 */
	private $lock;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|null              $wpdb WordPress database adapter.
	 * @param Atomic_Option_Lock|null $lock Atomic site-option lock boundary.
	 */
	public function __construct( $wpdb = null, ?Atomic_Option_Lock $lock = null ) {

		$this->wpdb = null !== $wpdb ? $wpdb : $GLOBALS['wpdb'];
		$this->lock = $lock ?? new Atomic_Option_Lock( $this->wpdb );

	}

	// -------------------------------------------------------------------------
	// Aggregate reads and mutations.
	// -------------------------------------------------------------------------

	/**
	 * Read the current credential aggregate.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $meta_key Site-scoped credential meta key.
	 * @return array Credential records keyed by token hash.
	 */
	public function get( $user_id, $meta_key ) {

		$records = $this->get_records( $user_id, $meta_key );

		if ( empty( $records ) ) {
			return array();
		}

		$value = maybe_unserialize( $records[0]->meta_value );

		return is_array( $value ) ? $value : array();

	}

	/**
	 * Mutate the current aggregate while holding its shared write lock.
	 *
	 * The callback always receives a fresh value read after lock acquisition.
	 * It must return the complete replacement aggregate, or false to abort.
	 *
	 * @param int      $user_id WordPress user ID.
	 * @param string   $meta_key Site-scoped credential meta key.
	 * @param callable $mutation Aggregate mutation callback.
	 * @return bool Whether the requested state was persisted.
	 */
	public function mutate( $user_id, $meta_key, callable $mutation ) {

		$lock_name  = $this->get_lock_name( $user_id, $meta_key );
		$lock_owner = $this->lock->acquire( $lock_name, self::LOCK_TTL, self::LOCK_WAIT );

		if ( false === $lock_owner ) {
			return false;
		}

		try {
			$records = $this->get_records( $user_id, $meta_key );
			$current = array();

			if ( ! empty( $records ) ) {
				$value   = maybe_unserialize( $records[0]->meta_value );
				$current = is_array( $value ) ? $value : array();
			}

			$replacement = $mutation( $current );

			if ( ! is_array( $replacement ) ) {
				return false;
			}

			// An unreadable or explicitly empty stored row still needs removal
			// when the requested aggregate is empty.
			if ( $this->is_unchanged_mutation( $current, $replacement, $records ) ) {
				return true;
			}

			return $this->persist( $user_id, $meta_key, $records, $replacement );
		} finally {
			$this->lock->release( $lock_name, $lock_owner );
		}

	}

	/**
	 * Determine whether a mutation already matches a valid stored state.
	 *
	 * An explicitly empty but unreadable row must still be deleted, so an empty
	 * replacement is unchanged only when no metadata row currently exists.
	 *
	 * @since 7.4.1
	 *
	 * @param array    $current Current readable aggregate.
	 * @param array    $replacement Requested replacement aggregate.
	 * @param object[] $records Current metadata records.
	 * @return bool Whether persistence can be skipped safely.
	 */
	private function is_unchanged_mutation( array $current, array $replacement, array $records ) {

		return $replacement === $current
			&& ( ! empty( $replacement ) || empty( $records ) );

	}

	/**
	 * Persist a replacement without permitting update-after-delete insertion.
	 *
	 * @param int      $user_id WordPress user ID.
	 * @param string   $meta_key Site-scoped credential meta key.
	 * @param object[] $records Existing database records.
	 * @param array    $replacement Complete replacement aggregate.
	 * @return bool Whether the replacement was persisted.
	 */
	private function persist( $user_id, $meta_key, array $records, array $replacement ) {

		if ( empty( $replacement ) ) {
			foreach ( $records as $record ) {
				$meta_id = (int) $record->umeta_id;

				if ( ! $this->delete_metadata_record( $meta_id ) ) {
					return false;
				}
			}

			return empty( $this->get_records( $user_id, $meta_key ) );
		}

		if ( empty( $records ) ) {
			return (bool) add_user_meta( $user_id, $meta_key, $replacement, true );
		}

		$canonical_meta_id = (int) $records[0]->umeta_id;

		if ( ! update_metadata_by_mid( 'user', $canonical_meta_id, $replacement, $meta_key ) ) {
			return false;
		}

		// Collapse any historical duplicate aggregates after the canonical row
		// has been updated. New writes cannot create duplicates under this lock.
		foreach ( array_slice( $records, 1 ) as $record ) {
			$meta_id = (int) $record->umeta_id;

			if ( ! $this->delete_metadata_record( $meta_id ) ) {
				return false;
			}
		}

		return true;

	}

	/**
	 * Delete one metadata row and verify that a false return means absence.
	 *
	 * @since 7.4.1
	 *
	 * @param int $meta_id User metadata ID.
	 * @return bool Whether the row is absent.
	 */
	private function delete_metadata_record( $meta_id ) {

		return delete_metadata_by_mid( 'user', $meta_id )
			|| false === get_metadata_by_mid( 'user', $meta_id );

	}

	/**
	 * Read matching rows directly so the mutation snapshot and metadata IDs
	 * describe the same database state.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $meta_key Site-scoped credential meta key.
	 * @return object[] Matching rows ordered by metadata ID.
	 */
	private function get_records( $user_id, $meta_key ) {

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $this->wpdb->prepare(
			"SELECT umeta_id, meta_value FROM {$this->wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id ASC",
			$user_id,
			$meta_key
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$records = $this->wpdb->get_results( $query );

		return is_array( $records ) ? $records : array();

	}

	/**
	 * Build the site-local option name for an aggregate lock.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $meta_key Site-scoped credential meta key.
	 * @return string Lock option name.
	 */
	private function get_lock_name( $user_id, $meta_key ) {

		return 'automator_mcp_token_credentials_lock_'
			. (int) $user_id
			. '_'
			. substr( hash( 'sha256', $meta_key ), 0, 16 );

	}
}
