<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Makes the local `uap_connections` rows agree with the platform's list of
 * this site's connections — the row work of a connection sync
 * ({@see Connection_Sync}, which decides when this runs and what follows).
 *
 * For every connection the platform lists as active, with a signature:
 *
 *  - the local row with that `connection_id`, or failing that the row for the
 *    same account (`integration`, `identity_id`), takes the listed
 *    `connection_id` and `connection_signature`, and a row in any other
 *    status comes back `active`: switched off (`quarantined`), needing
 *    re-authorising, `revoked` by an IDENTITY_REVOKED answer, or `expired` by
 *    a CONNECTION_INACTIVE answer or a restart. The list is the truth: when
 *    any site signs in to an account again, the platform restores every
 *    binding of it (L239), and this is how the other sites learn it;
 *  - with no such row, a row is created.
 *
 * A connection this site disconnected needs no guard either. A confirmed
 * disconnect deletes the local row ({@see Disconnect_Service}), so there is
 * no local inactive state to preserve. The platform does not delete its
 * binding: it holds it `disconnected` for a grace window, and a sign-in to the
 * same account inside that window restores the same binding, active. That
 * restore is expected to come back through sync: the list shows the
 * connection active again, and it is created here as a new local row, not the
 * default — the intended outcome.
 *
 * Which row is an integration's default never changes here while it has
 * two or more. The default is the account the user chose, and recipes act on
 * it: a row keeps its flag through every status
 * ({@see Connection_Registry::quarantine_all()}), so a row switched back on is
 * the default again exactly when it was before, in the same write. Once the
 * whole list is applied, an integration left with exactly one row uses it
 * ({@see Connection_Registry::default_sole_connections()}, Saad 29 Oct): with
 * one account there is nothing to choose. One left with several and no
 * default is the admin's to choose. A run that stops part-way therefore never
 * leaves recipes on another account.
 *
 * A row the platform does not list as active is left as it is, with two
 * exceptions, both about the row a refused call was just made with:
 *
 *  - the platform-answers contract (§4.3): when the call was answered
 *    `CONNECTION_INACTIVE` and the list confirms the connection is not
 *    active, that row takes the platform's status (`expired`);
 *  - the flow spec (§9.3): when the list names neither that connection nor an
 *    active one for the same account, the platform no longer holds it, and
 *    nothing brings it back. The row is marked `expired` and not on the
 *    platform ({@see Connection::METADATA_NOT_ON_PLATFORM}), so steps stop
 *    failing against it and the customer is told to connect the app again.
 *
 * A full sync names no refused call, so it retires nothing. Nothing is
 * deleted, a retired row keeps its default flag, a legacy (/v2) row is never
 * touched, and a row given a new `connection_id` is owed a trigger
 * subscription (R6).
 *
 * @since 8.0.0
 */
final class Connection_List_Applier {

	/**
	 * Metadata keys a row sheds when it is switched back on.
	 */
	private const SWITCHED_OFF_KEYS = array( 'quarantine_reason', 'adoption_refusal', 'required_scopes', 'granted_scopes', Connection::METADATA_NOT_ON_PLATFORM );

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * Per-run tallies, reset by {@see apply()}.
	 *
	 * @var array{restored: int, updated: int, created: int, failed: int}
	 */
	private $tally = array(
		'restored' => 0,
		'updated'  => 0,
		'created'  => 0,
		'failed'   => 0,
	);

	/**
	 * @param Connection_Registry $registry Local uap_connections store.
	 */
	public function __construct( Connection_Registry $registry ) {

		$this->registry = $registry;
	}

	/**
	 * Apply the platform's list, in the order given (a repair puts the
	 * connections that concern its row first: {@see Connection_List_Result::ordered_for()}).
	 *
	 * @param array<int, Platform_Connection> $connections The platform's list.
	 * @param Connection|null                 $first       A row to repair before the others.
	 * @param string                          $type        The answer that asked for the repair, '' for a full sync.
	 *
	 * @return Connection_Sync_Result Synced, or incomplete when a row could not be written.
	 */
	public function apply( array $connections, ?Connection $first, string $type ): Connection_Sync_Result {

		$this->tally = array(
			'restored' => 0,
			'updated'  => 0,
			'created'  => 0,
			'failed'   => 0,
		);

		foreach ( $connections as $connection ) {
			$this->apply_one( $connection, $first, $type );
		}

		$this->retire_if_missing( $connections, $first, $type );

		// After the whole list, never per row: an app the list leaves with one
		// connection uses it (Saad, 29 Oct); one it leaves with several is the
		// admin's to choose.
		$this->registry->default_sole_connections();

		return Connection_Sync_Result::applied(
			0 === $this->tally['failed'] ? Connection_Sync_Result::STATUS_SYNCED : Connection_Sync_Result::STATUS_INCOMPLETE,
			$this->tally['restored'],
			$this->tally['updated'],
			$this->tally['created']
		);
	}

	/**
	 * @param Platform_Connection $connection A listed connection.
	 * @param Connection|null     $first      The row the repair was asked for.
	 * @param string              $type       The answer that asked for it.
	 *
	 * @return void
	 */
	private function apply_one( Platform_Connection $connection, ?Connection $first, string $type ): void {

		if ( ! $connection->is_active() ) {
			$this->take_inactive_status( $connection, $first, $type );
			return;
		}

		if ( $connection->is_usable() ) {
			$this->apply_active( $connection );
		}
	}

	/**
	 * Apply one active connection: rebind its row, or create one.
	 *
	 * @param Platform_Connection $connection An active, signed connection.
	 *
	 * @return void
	 */
	private function apply_active( Platform_Connection $connection ): void {

		$row = $this->registry->get_by_connection_id( $connection->connection_id() );

		if ( null === $row && '' !== $connection->identity_id() ) {
			$row = $this->registry->get_by_identity( $connection->integration(), $connection->identity_id() );
		}

		if ( null === $row ) {
			$this->create( $connection );
			return;
		}

		// A legacy (/v2) row dispatches through its own credentials; and a
		// connection id filed under another integration is not this row's to
		// guess about.
		if ( $row->is_legacy() || $row->integration() !== $connection->integration() ) {
			return;
		}

		$this->rebind( $row, $connection );
	}

	/**
	 * Bring a row in line with the platform's connection. The platform lists
	 * it active and signed, so whatever took the row down locally, it is
	 * active again.
	 *
	 * @param Connection          $row        The local row.
	 * @param Platform_Connection $connection The platform's active connection.
	 *
	 * @return void
	 */
	private function rebind( Connection $row, Platform_Connection $connection ): void {

		$switched_on = ! $row->is_active();
		$binding     = array(
			'connection_id' => $connection->connection_id(),
			'signature'     => $connection->connection_signature(),
			'identity_id'   => '' !== $connection->identity_id() ? $connection->identity_id() : (string) $row->identity_id(),
			'status'        => $switched_on ? Connection::STATUS_ACTIVE : $row->status(),
			'metadata'      => $this->metadata_for( $row, $connection, $switched_on ),
		);

		if ( $this->is_unchanged( $row, $binding ) ) {
			return;
		}

		if ( ! $this->registry->bind( $row->id(), $binding['connection_id'], $binding['signature'], $binding['identity_id'], $binding['status'], $binding['metadata'] ) ) {
			$this->write_failed( $connection );
			return;
		}

		++$this->tally[ $switched_on ? 'restored' : 'updated' ];
	}

	/**
	 * @param Connection           $row     The local row.
	 * @param array<string, mixed> $binding What the row would become.
	 *
	 * @return bool Whether the row already is that.
	 */
	private function is_unchanged( Connection $row, array $binding ): bool {

		return $row->connection_id() === $binding['connection_id']
			&& $row->connection_signature() === $binding['signature']
			&& (string) $row->identity_id() === $binding['identity_id']
			&& $row->status() === $binding['status']
			&& $row->metadata() === $binding['metadata'];
	}

	/**
	 * The row's metadata as it should be after the rebind.
	 *
	 * @param Connection          $row         The local row.
	 * @param Platform_Connection $connection  The platform's connection.
	 * @param bool                $switched_on Whether the row comes back on.
	 *
	 * @return array<string, mixed>
	 */
	private function metadata_for( Connection $row, Platform_Connection $connection, bool $switched_on ): array {

		$metadata = $row->metadata();

		if ( $switched_on ) {
			foreach ( self::SWITCHED_OFF_KEYS as $key ) {
				unset( $metadata[ $key ] );
			}
		}

		$metadata = $this->with_platform_facts( $metadata, $connection );

		return $row->connection_id() !== $connection->connection_id() ? Trigger_Subscriptions::mark_owed( $metadata ) : $metadata;
	}

	/**
	 * Create a row for a connection this site had no row for. It is not made
	 * the default here: recipes keep acting on the account the user chose, and
	 * an app this leaves with one row is given its default once the whole list
	 * is applied ({@see apply()}).
	 *
	 * @param Platform_Connection $connection The platform's active connection.
	 *
	 * @return void
	 */
	private function create( Platform_Connection $connection ): void {

		$label = '' !== $connection->label() ? $connection->label() : $connection->external_user_label();

		$id = $this->registry->store(
			array(
				'connection_id'        => $connection->connection_id(),
				'connection_signature' => $connection->connection_signature(),
				'identity_id'          => $connection->identity_id(),
				'integration'          => $connection->integration(),
				'platform_version'     => Connection::PLATFORM_VERSION_V3,
				'label'                => '' !== $label ? $label : null,
				'status'               => Connection::STATUS_ACTIVE,
				'is_default'           => false,
				'metadata'             => Trigger_Subscriptions::mark_owed( $this->with_platform_facts( array(), $connection ) ),
			)
		);

		if ( 0 === $id ) {
			$this->write_failed( $connection );
			return;
		}

		++$this->tally['created'];
	}

	/**
	 * After a call was answered CONNECTION_INACTIVE: when the list confirms
	 * that connection is not active, its row takes the platform's status.
	 *
	 * @param Platform_Connection $connection A listed connection that is not active.
	 * @param Connection|null     $first      The row the repair was asked for.
	 * @param string              $type       The answer that asked for it.
	 *
	 * @return void
	 */
	private function take_inactive_status( Platform_Connection $connection, ?Connection $first, string $type ): void {

		$confirms = null !== $first
			&& 'CONNECTION_INACTIVE' === $type
			&& $first->is_active()
			&& $first->connection_id() === $connection->connection_id();

		if ( ! $confirms ) {
			return;
		}

		$this->count_status_write( $this->registry->mark_expired( $first->id() ), $first );
	}

	/**
	 * After a call made with `$first` was refused (a CONNECTION_* answer):
	 * when the list names neither that connection, in any status, nor an
	 * active one for the same account, the platform no longer holds it (flow
	 * spec §9.3) and the row is marked so. `$first` is the row as it was before
	 * the list was applied; a rebind by account has an active entry for that
	 * account, so it is never retired here.
	 *
	 * @since 8.0.0
	 *
	 * @param array<int, Platform_Connection> $connections The platform's list.
	 * @param Connection|null                 $first       The row the refused call was made with.
	 * @param string                          $type        The answer that asked for the repair, '' for a full sync.
	 *
	 * @return void
	 */
	private function retire_if_missing( array $connections, ?Connection $first, string $type ): void {

		if ( null === $first || '' === $type || ! $first->is_active() || $first->is_legacy() ) {
			return;
		}

		foreach ( $connections as $connection ) {
			if ( $connection->connection_id() === $first->connection_id() || $this->is_active_for_account( $connection, $first ) ) {
				return;
			}
		}

		$this->count_status_write( $this->registry->mark_not_on_platform( $first->id() ), $first );
	}

	/**
	 * Count a status change of a row the repair was asked for: done when the
	 * database took it, failed (and logged) when it did not, so the repair is
	 * incomplete instead of reporting the row as changed. Only a repair makes
	 * these changes, so the next call the platform refuses asks again.
	 *
	 * @param bool       $written Whether the database took the write.
	 * @param Connection $row     The row.
	 *
	 * @return void
	 */
	private function count_status_write( bool $written, Connection $row ): void {

		if ( $written ) {
			++$this->tally['updated'];
			return;
		}

		++$this->tally['failed'];
		automator_log( 'Connection sync could not change the status of the ' . $row->integration() . ' connection ' . $row->connection_id() . '; the next refused call asks again.', 'Connection_Sync', false );
	}

	/**
	 * @param Platform_Connection $connection A listed connection.
	 * @param Connection          $row        A local row.
	 *
	 * @return bool Whether the listed connection is an active one for the row's account.
	 */
	private function is_active_for_account( Platform_Connection $connection, Connection $row ): bool {

		return $connection->is_active()
			&& '' !== $connection->identity_id()
			&& $connection->integration() === $row->integration()
			&& $connection->identity_id() === (string) $row->identity_id();
	}

	/**
	 * Record what the platform says about the connection.
	 *
	 * `origin` is always recorded: a value the platform does not know (`null`,
	 * contract §9) is recorded as `null`, which counts as "not this site's
	 * own". A connection that is not `shared` is no longer one this copy
	 * attached to: the site signed in to the account itself, and the platform
	 * made it its own (§9), so it sheds the adoption marker.
	 *
	 * @param array<string, mixed> $metadata   Metadata to extend.
	 * @param Platform_Connection  $connection The platform's connection.
	 *
	 * @return array<string, mixed>
	 */
	private function with_platform_facts( array $metadata, Platform_Connection $connection ): array {

		$metadata['origin'] = '' !== $connection->origin() ? $connection->origin() : null;

		if ( Platform_Connection::ORIGIN_SHARED !== $connection->origin() ) {
			unset( $metadata['adopted'] );
		}

		if ( '' !== $connection->external_user_label() ) {
			$metadata['external_user_label'] = $connection->external_user_label();
		}

		return $metadata;
	}

	/**
	 * @param Platform_Connection $connection The connection that could not be written.
	 *
	 * @return void
	 */
	private function write_failed( Platform_Connection $connection ): void {

		++$this->tally['failed'];
		automator_log( 'Connection sync could not write the ' . $connection->integration() . ' connection ' . $connection->connection_id() . '; it will be retried.', 'Connection_Sync', false );
	}
}
