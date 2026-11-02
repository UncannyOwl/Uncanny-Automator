<?php
declare( strict_types=1 );

// phpcs:disable WordPress.DB.PreparedSQL -- Every query below runs through $wpdb->prepare() with placeholders; the only interpolated token is table() (= $wpdb->prefix . 'uap_connections'), never user input.
namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * CRUD surface over `uap_connections` — the plugin's per-site registry
 * of Automator Platform connections (both `legacy_*` v2 rows and `v3`
 * rows; the `platform_version` field discriminates).
 *
 * `connection_signature` (MVP Contract G, walked back to Laravel reality
 * 2026-04-29) is the platform-issued opaque static auth token the plugin
 * echoes verbatim on every /v3/* request body. Stored alongside
 * `connection_id`; nullable for `legacy_*` v2 rows that haven't been
 * promoted yet. Plugin treats it as opaque — never mints, never verifies.
 *
 * No `credentials_encrypted` field — platform owns the vault
 * (`Identity.credentials`); the plugin never duplicates token storage
 * post-lift. Pre-lift legacy credentials live in their original
 * `wp_options` home until P16 Path B (G.7) wipes them on upgrade.
 *
 * Disconnect is a HARD DELETE (G.6 + G.9), made only once the platform
 * has disconnected the connection or says it is gone (Disconnect_Service).
 * The platform keeps its record, marked disconnected, and reactivates it
 * when the same vendor account is authorized again.
 *
 * `metadata` is plaintext JSON (workspace_id, team_name, scope arrays
 * for the reconnect UI) — non-secret per G.2 ("no credentials in
 * registry").
 *
 * @since 8.0.0
 */
final class Connection_Registry {

	/**
	 * @return string Fully-prefixed table name.
	 */
	private function table(): string {

		global $wpdb;
		return $wpdb->prefix . 'uap_connections';
	}

	/**
	 * Persist a new connection. UNIQUE constraints on `connection_id`
	 * and `(integration, identity_id)` reject duplicates; this method
	 * returns 0 in that case so callers can distinguish from a real
	 * insert id.
	 *
	 * Setting `is_default=true` demotes all other rows for the same
	 * integration to `is_default=0` first.
	 *
	 * @param array{
	 *   connection_id: string,
	 *   connection_signature?: string|null,
	 *   integration: string,
	 *   identity_id?: string|null,
	 *   platform_version?: string,
	 *   label?: string|null,
	 *   metadata?: array<string, mixed>,
	 *   status?: string,
	 *   is_default?: bool,
	 *   last_used_at?: string|null
	 * } $row
	 *
	 * @return int Insert ID on success, 0 on constraint violation.
	 */
	public function store( array $row ): int {

		global $wpdb;

		$now  = current_time( 'mysql', true );
		$data = array(
			'connection_id'        => (string) $row['connection_id'],
			'connection_signature' => isset( $row['connection_signature'] ) && '' !== (string) $row['connection_signature']
				? (string) $row['connection_signature']
				: null,
			'identity_id'          => isset( $row['identity_id'] ) && '' !== (string) $row['identity_id']
				? (string) $row['identity_id']
				: null,
			'integration'          => strtolower( (string) $row['integration'] ),
			'label'                => isset( $row['label'] ) ? (string) $row['label'] : null,
			'platform_version'     => isset( $row['platform_version'] )
				? (string) $row['platform_version']
				: Connection::PLATFORM_VERSION_V2,
			'metadata'             => isset( $row['metadata'] ) && is_array( $row['metadata'] )
				? (string) wp_json_encode( $row['metadata'] )
				: null,
			'status'               => isset( $row['status'] ) ? (string) $row['status'] : Connection::STATUS_ACTIVE,
			'is_default'           => ! empty( $row['is_default'] ) ? 1 : 0,
			'created_at'           => $now,
			'updated_at'           => $now,
			'last_used_at'         => $row['last_used_at'] ?? null,
		);

		if ( 1 === $data['is_default'] ) {
			$this->demote_other_defaults( $data['integration'], 0 );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write.
		$inserted = $wpdb->insert( $this->table(), $data );
		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}
	/**
	 * The integration's active default row, of either platform version, or
	 * null ({@see lookup_default()} says why).
	 *
	 * @param string $integration The integration.
	 * @return Connection|null
	 */
	public function get_default( string $integration ): ?Connection {

		return $this->lookup_default( $integration )->active_connection();
	}

	/**
	 * Resolve the integration's default connection, and when none resolves,
	 * say why ({@see Default_Connection_Lookup}).
	 *
	 * Fails closed while a site copy is mid-quarantine. The inherited rows may
	 * still be `active` for the brief window before quarantine_all()
	 * neutralizes them, but a copy must never resolve one and dispatch as the
	 * site it was cloned from. The block engages BEFORE registration and lifts
	 * only once the inherited rows are quarantined — see
	 * {@see Copy_Quarantine_State}.
	 *
	 * When no active default resolves, one more read finds why: the
	 * integration's default account in a status that resolves nothing —
	 * switched off, or needing the account connected again. With no default
	 * account at all the answer is "none", whatever other rows the integration
	 * holds: they are not the account recipes use, and nothing makes one of
	 * them the default by itself ({@see quarantine_all()}). A read the database
	 * did not answer is a failed lookup, never "no connection".
	 *
	 * The answer is about the default row whatever its platform version; a
	 * `/v3` reader reads it through
	 * {@see Default_Connection_Lookup::for_v3_dispatch()}, where a pre-lift
	 * legacy (`/v2`) default is no `/v3` connection.
	 *
	 * @since 8.0.0
	 *
	 * @param string $integration The integration slug.
	 *
	 * @return Default_Connection_Lookup
	 */
	public function lookup_default( string $integration ): Default_Connection_Lookup {

		global $wpdb;

		if ( ( new Copy_Quarantine_State() )->is_blocking() ) {
			return Default_Connection_Lookup::rechecking();
		}

		$integration = strtolower( $integration );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$default = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE integration = %s AND is_default = 1 AND status = %s LIMIT 1",
				$integration,
				Connection::STATUS_ACTIVE
			),
			ARRAY_A
		);
		if ( '' !== (string) $wpdb->last_error ) {
			return Default_Connection_Lookup::lookup_failed();
		}
		if ( is_array( $default ) ) {
			return Default_Connection_Lookup::found( $this->hydrate( $default ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$explaining = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE integration = %s AND is_default = 1 AND status IN (%s, %s, %s, %s) ORDER BY id ASC LIMIT 1",
				$integration,
				Connection::STATUS_QUARANTINED,
				Connection::STATUS_REQUIRES_REAUTH,
				Connection::STATUS_EXPIRED,
				Connection::STATUS_REVOKED
			),
			ARRAY_A
		);
		if ( '' !== (string) $wpdb->last_error ) {
			return Default_Connection_Lookup::lookup_failed();
		}
		if ( ! is_array( $explaining ) ) {
			return $this->lookup_without_default( $integration );
		}

		$row = $this->hydrate( $explaining );

		return $row->is_quarantined()
			? Default_Connection_Lookup::switched_off( $row )
			: Default_Connection_Lookup::inactive( $row );
	}
	/**
	 * No default row, active or explaining: the app's active `/v3` accounts
	 * are there to choose from, or there is none.
	 *
	 * @since 8.0.0
	 *
	 * @param string $integration The integration slug, lower case.
	 *
	 * @return Default_Connection_Lookup
	 */
	private function lookup_without_default( string $integration ): Default_Connection_Lookup {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$choosable = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table()} WHERE integration = %s AND status = %s AND platform_version = %s",
				$integration,
				Connection::STATUS_ACTIVE,
				Connection::PLATFORM_VERSION_V3
			)
		);
		if ( '' !== (string) $wpdb->last_error ) {
			return Default_Connection_Lookup::lookup_failed();
		}

		return 0 < $choosable ? Default_Connection_Lookup::unchosen() : Default_Connection_Lookup::none();
	}

	/**
	 * Get by connection id.
	 *
	 * @param string $connection_id The connection ID.
	 * @return Connection|null
	 */
	public function get_by_connection_id( string $connection_id ): ?Connection {

		return $this->find_one( 'connection_id = %s', array( $connection_id ) );
	}

	/**
	 * Re-auth idempotency lookup: when an OAuth callback returns a
	 * platform-issued identity_id we already have a row for, the caller
	 * resolves to that row and updates it in place rather than INSERTing
	 * a duplicate.
	 */
	public function get_by_identity( string $integration, string $identity_id ): ?Connection {

		return $this->find_one(
			'integration = %s AND identity_id = %s',
			array( strtolower( $integration ), $identity_id )
		);
	}
	/**
	 * Get by id.
	 *
	 * @param int $id The row ID.
	 * @return Connection|null
	 */
	public function get_by_id( int $id ): ?Connection {

		return $this->find_one( 'id = %d', array( $id ) );
	}

	/**
	 * @param string $integration
	 * @param bool   $active_only
	 * @return array<int, Connection>
	 */
	public function list_for_integration( string $integration, bool $active_only = true ): array {

		global $wpdb;

		$integration = strtolower( $integration );

		if ( $active_only ) {
			$prepared = $wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $this->table() ) . '` WHERE integration = %s AND status = %s ORDER BY is_default DESC, id ASC',
				$integration,
				Connection::STATUS_ACTIVE
			);
		} else {
			$prepared = $wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $this->table() ) . '` WHERE integration = %s ORDER BY is_default DESC, id ASC',
				$integration
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'hydrate' ), $rows );
	}

	/**
	 * Promote `$id` to default within its integration, demoting any
	 * currently-default row.
	 *
	 * @return void
	 */
	public function set_default( string $integration, int $id ): void {

		global $wpdb;

		$normalized = strtolower( $integration );
		$this->demote_other_defaults( $normalized, $id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->update(
			$this->table(),
			array(
				'is_default' => 1,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id'          => $id,
				'integration' => $normalized,
			),
			array( '%d', '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Make the one connection of every app that has exactly one the default
	 * (Saad, 29 Oct): with one account there is nothing to choose. An app with
	 * two or more and no default is left for the admin; this never chooses
	 * among them. The row keeps its status, so a sole switched-off default
	 * still stops recipes visibly.
	 *
	 * Called once at the end of a write that changes how many connections an
	 * app has (a sync or repair, a disconnect), never per row: mid-way through
	 * a batch an app can hold one row that the batch then joins with another.
	 *
	 * @since 8.0.0
	 *
	 * @return int How many rows became their app's default.
	 */
	public function default_sole_connections(): int {

		global $wpdb;

		// The derived table is read in full before the update, so the same
		// table can be both read and written.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} AS c
				INNER JOIN ( SELECT integration FROM {$this->table()} GROUP BY integration HAVING COUNT(*) = 1 AND MAX(is_default) = 0 ) AS sole ON sole.integration = c.integration
				SET c.is_default = 1, c.updated_at = %s",
				current_time( 'mysql', true )
			)
		);

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * Mark a connection as requiring reauth (e.g. platform returned
	 * SCOPE_INSUFFICIENT). The required + current scope arrays are
	 * persisted into `metadata` so the reconnect UI can render the diff.
	 *
	 * Per MVP G.5 the scope arrays are first-class arguments — callers
	 * MUST pass them rather than smuggling via a generic `$metadata`
	 * hash, so the discriminator is unambiguous.
	 *
	 * @param array<int, string> $required_scopes
	 * @param array<int, string> $current_scopes
	 *
	 * @return bool Whether the database took the write.
	 */
	public function mark_requires_reauth( int $id, array $required_scopes = array(), array $current_scopes = array() ): bool {

		return $this->update_status_with_metadata(
			$id,
			Connection::STATUS_REQUIRES_REAUTH,
			array(
				'required_scopes' => array_values( $required_scopes ),
				'granted_scopes'  => array_values( $current_scopes ),
			)
		);
	}

	/**
	 * Mark a connection as expired (vendor refresh-token expired, etc.).
	 * Row is preserved; status flips so dispatch routes to reconnect UX.
	 *
	 * @return bool Whether the database took the write.
	 */
	public function mark_expired( int $id ): bool {

		return $this->update_status( $id, Connection::STATUS_EXPIRED );
	}

	/**
	 * Mark a connection the platform no longer holds: it refused a call made
	 * with the row, and its list names neither the connection nor an active
	 * one for the same account (flow spec §9.3). The row is preserved with its
	 * default flag and stops resolving; its metadata says why, so every later
	 * step tells the customer to connect the app again.
	 *
	 * @since 8.0.0
	 *
	 * @param int $id The row.
	 *
	 * @return bool Whether the database took the write.
	 */
	public function mark_not_on_platform( int $id ): bool {

		return $this->update_status_with_metadata( $id, Connection::STATUS_EXPIRED, array( Connection::METADATA_NOT_ON_PLATFORM => true ) );
	}

	/**
	 * Mark a connection as revoked (platform returned IDENTITY_REVOKED).
	 * Per the Phase G error-mapping table, callers should also invalidate
	 * the G.8 account-info transient for this connection_id.
	 *
	 * @return bool Whether the database took the write.
	 */
	public function mark_revoked( int $id ): bool {

		return $this->update_status( $id, Connection::STATUS_REVOKED );
	}

	/**
	 * Quarantine every resolvable connection on this site.
	 *
	 * For the site-copy path. A cloned WordPress database brings the source
	 * site's `uap_connections` rows with it, but the platform registers the
	 * copy as a brand-new site with no bindings. Left alone, the two sides
	 * disagree: the plugin keeps resolving an active default connection that
	 * belongs to the ORIGINAL site, so actions either authorize as the wrong
	 * tenant or fail — while the UI has told the user the copy starts with no
	 * app connections.
	 *
	 * Uses the distinct {@see Connection::STATUS_QUARANTINED}, NOT `revoked`:
	 * `revoked` means the remote credential is no longer valid, but a copied
	 * connection may still be perfectly valid for the ORIGINAL site — so
	 * `revoked` would give a misleading UI and the wrong recovery. Each row is
	 * stamped `quarantine_reason=site_copy` (existing metadata preserved) so the
	 * reconnect UI can explain why. Rows already in a terminal-bad state
	 * (`quarantined`, `revoked`) are left as they are — they cannot resolve
	 * anyway and their status still carries meaning.
	 *
	 * Quarantine rather than delete: the rows stay visible so the user can see
	 * what was inherited and reconnect deliberately (reconnect binds a NEW
	 * connection), and nothing that pins a `connection_id` dangles.
	 *
	 * The default flag is left as it is. Which account an integration's
	 * recipes act on is the user's choice, and switching a row off does not
	 * change it — the same holds for every other status a row can take
	 * (`requires_reauth`, `expired`, `revoked`). A switched-off default
	 * resolves nothing, so recipes stop and say why
	 * ({@see lookup_default()}); and when the row is switched back on — an
	 * attachment or a connection sync rewrites it in place — it is the default
	 * again in that same write. No step ever has to pick an account, so none
	 * can pick another one.
	 *
	 * MUST be called only AFTER a fresh registration succeeds. Clearing first
	 * would make a transient platform failure destructive — the site would
	 * lose its connections and still be bound to the original.
	 *
	 * Fail-LOUD on any DB error. A SELECT error is NOT read as "no rows", and a
	 * failed row UPDATE is NOT counted as success — either throws a
	 * {@see Quarantine_Failed_Exception} so {@see
	 * Platform_Site_Registration::register_as_copy()} stays fail-closed instead
	 * of lifting the block over connections that were never neutralized.
	 *
	 * The sweep is NOT wrapped in an explicit transaction: it is idempotent (the
	 * status filter skips already-quarantined rows), so a mid-set UPDATE failure
	 * throws, leaves the block engaged, and the next attempt simply resumes on
	 * the rows that are still resolvable. A partially quarantined set is
	 * therefore self-healing — and an explicit `START TRANSACTION`/`COMMIT` here
	 * would wrongly commit any transaction the caller already holds (WP's own
	 * test harness wraps each test in one, and a future caller could too).
	 *
	 * @return int Rows quarantined.
	 *
	 * @throws Quarantine_Failed_Exception When a SELECT or UPDATE errors.
	 */
	public function quarantine_all(): int {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, metadata FROM {$this->table()} WHERE status NOT IN (%s, %s)",
				Connection::STATUS_QUARANTINED,
				Connection::STATUS_REVOKED
			),
			ARRAY_A
		);

		// An errored SELECT sets last_error and can return null/empty — that
		// is a FAILURE, never "nothing to quarantine".
		if ( '' !== (string) $wpdb->last_error ) {
			throw new Quarantine_Failed_Exception(
				esc_html( sprintf( 'Quarantine SELECT failed: %s', $wpdb->last_error ) )
			);
		}

		if ( ! is_array( $rows ) || array() === $rows ) {
			return 0;
		}

		$now   = current_time( 'mysql', true );
		$count = 0;
		foreach ( $rows as $row ) {

			// Merge, don't clobber: a quarantined row keeps its workspace /
			// account metadata so the reconnect UI can still name what was
			// inherited; only the reason key is added.
			$metadata = array();
			if ( ! empty( $row['metadata'] ) ) {
				$decoded = json_decode( (string) $row['metadata'], true );
				if ( is_array( $decoded ) ) {
					$metadata = $decoded;
				}
			}
			$metadata['quarantine_reason'] = 'site_copy';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
			$updated = $wpdb->update(
				$this->table(),
				array(
					'status'     => Connection::STATUS_QUARANTINED,
					'metadata'   => (string) wp_json_encode( $metadata ),
					'updated_at' => $now,
				),
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			// update() returns false ONLY on a query error (0 rows affected
			// is int 0, and updated_at always changes so a match never yields
			// 0). A failed UPDATE aborts the whole set — a silently skipped
			// row would stay active and resolvable under the new site. The
			// rows already quarantined this pass stay quarantined (safe), and
			// the caller stays fail-closed until a later retry finishes the set.
			if ( false === $updated ) {
				throw new Quarantine_Failed_Exception(
					esc_html( sprintf( 'Quarantine UPDATE failed for row %d: %s', (int) $row['id'], (string) $wpdb->last_error ) )
				);
			}
			++$count;
		}

		return $count;
	}

	/**
	 * Promote a `legacy_*` v2 row to a fresh /v3 row in place
	 * (MVP plan G.7, Contract H §"v3 promotion envelope").
	 *
	 * Called when the platform's response carries `upgraded_connection_id`
	 * + `upgraded_connection_signature` + `upgraded_identity_id` (P16
	 * Path B — scope-unchanged promotion). Plugin updates the existing
	 * row in place rather than INSERTing a new one so any pinning to
	 * `connection_id` (future multi-connection UX) doesn't dangle.
	 *
	 * Idempotent at the call level: if the row's `platform_version` is
	 * already `v3` the upgrade returns early — covers the case where the
	 * platform re-emits the envelope on a stale-cached request after
	 * the row has already been upgraded.
	 *
	 * @param int    $id                       Local row ID (from get_by_connection_id).
	 * @param string $new_connection_id        Platform-issued post-promotion connection_id.
	 * @param string $new_connection_signature Platform-issued post-promotion signature.
	 * @param string $new_identity_id          Platform-issued post-promotion identity_id.
	 *
	 * @return void
	 */
	public function upgrade_legacy_to_v3(
		int $id,
		string $new_connection_id,
		string $new_connection_signature,
		string $new_identity_id
	): void {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->update(
			$this->table(),
			array(
				'connection_id'        => $new_connection_id,
				'connection_signature' => $new_connection_signature,
				'identity_id'          => $new_identity_id,
				'platform_version'     => Connection::PLATFORM_VERSION_V3,
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}
	/**
	 * Touch last used.
	 *
	 * @param int $id The ID.
	 */
	public function touch_last_used( int $id ): void {

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->update(
			$this->table(),
			array( 'last_used_at' => current_time( 'mysql', true ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * HARD delete — disconnect drops the row entirely (MVP G.6 + G.9).
	 * The platform keeps its own disconnected record; the plugin doesn't track it.
	 */
	public function delete( int $id ): void {

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Every row in one status, oldest first.
	 *
	 * @since 8.0.0
	 *
	 * @param string $status One of the Connection::STATUS_* constants.
	 *
	 * @return array<int, Connection>
	 *
	 * @throws Registry_Read_Failed_Exception When the database does not answer.
	 */
	public function list_by_status( string $status ): array {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE status = %s ORDER BY id ASC", $status ),
			ARRAY_A
		);

		if ( '' !== (string) $wpdb->last_error ) {
			throw new Registry_Read_Failed_Exception( esc_html( sprintf( 'Reading %s connections failed: %s', $status, $wpdb->last_error ) ) );
		}

		return is_array( $rows ) ? array_map( array( $this, 'hydrate' ), $rows ) : array();
	}

	/**
	 * Rewrite a row's platform binding in place — the connection it uses, the
	 * signature that proves it, the account it is for, its status and its
	 * metadata — keeping the row's id, integration, label and default flag.
	 * Used where the platform says what the row should be: an attachment
	 * (bind-existing) or a connection sync. Keeping the flag is what makes a
	 * row switched back on its integration's default exactly when it was
	 * before, in this one write; neither caller ever chooses a default.
	 *
	 * @since 8.0.0
	 *
	 * @param int                  $id                   Local row id.
	 * @param string               $connection_id        The platform connection id.
	 * @param string               $connection_signature Its current signature.
	 * @param string               $identity_id          The platform identity (the account).
	 * @param string               $status               One of the Connection::STATUS_* constants.
	 * @param array<string, mixed> $metadata             The row's whole metadata, replacing what it held.
	 *
	 * @return bool False when the write failed — a database error, or a
	 *              UNIQUE collision with another row.
	 */
	public function bind( int $id, string $connection_id, string $connection_signature, string $identity_id, string $status, array $metadata ): bool {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$updated = $wpdb->update(
			$this->table(),
			array(
				'connection_id'        => $connection_id,
				'connection_signature' => $connection_signature,
				'identity_id'          => '' !== $identity_id ? $identity_id : null,
				'status'               => $status,
				'metadata'             => (string) wp_json_encode( $metadata ),
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Replace a row's whole metadata bag. Callers read the row, change the
	 * keys they own and write the result back.
	 *
	 * @since 8.0.0
	 *
	 * @param int                  $id       Local row id.
	 * @param array<string, mixed> $metadata The metadata to store.
	 *
	 * @return bool False when the write failed.
	 */
	public function replace_metadata( int $id, array $metadata ): bool {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$updated = $wpdb->update(
			$this->table(),
			array(
				'metadata'   => (string) wp_json_encode( $metadata ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	// ── private ──

	/**
	 * @param string              $where
	 * @param array<int, scalar> $args
	 *
	 * @return Connection|null
	 */
	private function find_one( string $where, array $args ): ?Connection {

		global $wpdb;

		$sql = 'SELECT * FROM `' . esc_sql( $this->table() ) . "` WHERE {$where} LIMIT 1";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; live state read, must not be cached. $where is a literal fragment from this class's own callers, never user input.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * @param array<string, mixed> $row
	 *
	 * @return Connection
	 */
	private function hydrate( array $row ): Connection {

		$metadata = array();
		if ( ! empty( $row['metadata'] ) ) {
			$decoded = json_decode( (string) $row['metadata'], true );
			if ( is_array( $decoded ) ) {
				$metadata = $decoded;
			}
		}

		return new Connection(
			(int) ( $row['id'] ?? 0 ),
			(string) ( $row['connection_id'] ?? '' ),
			isset( $row['connection_signature'] ) && '' !== (string) $row['connection_signature']
				? (string) $row['connection_signature']
				: null,
			isset( $row['identity_id'] ) && '' !== (string) $row['identity_id']
				? (string) $row['identity_id']
				: null,
			(string) ( $row['integration'] ?? '' ),
			isset( $row['label'] ) ? (string) $row['label'] : null,
			(string) ( $row['platform_version'] ?? Connection::PLATFORM_VERSION_V2 ),
			$metadata,
			(string) ( $row['status'] ?? Connection::STATUS_ACTIVE ),
			(bool) ( $row['is_default'] ?? false ),
			(string) ( $row['created_at'] ?? '' ),
			(string) ( $row['updated_at'] ?? '' ),
			isset( $row['last_used_at'] ) ? (string) $row['last_used_at'] : null
		);
	}
	/**
	 * Demote other defaults.
	 *
	 * @param string $integration The integration.
	 * @param int $except_id The ID.
	 */
	private function demote_other_defaults( string $integration, int $except_id ): void {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()} SET is_default = 0, updated_at = %s WHERE integration = %s AND is_default = 1 AND id <> %d",
				current_time( 'mysql', true ),
				strtolower( $integration ),
				$except_id
			)
		);
	}
	/**
	 * Update status.
	 *
	 * @param int $id The ID.
	 * @param string $status The status.
	 *
	 * @return bool Whether the database took the write.
	 */
	private function update_status( int $id, string $status ): bool {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		return false !== $wpdb->update(
			$this->table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update status + merge `$extra_metadata` into the row's metadata
	 * JSON, preserving any existing keys not overlapping the merge.
	 *
	 * @param array<string, mixed> $extra_metadata
	 *
	 * @return bool Whether the database took the write.
	 */
	private function update_status_with_metadata( int $id, string $status, array $extra_metadata ): bool {

		global $wpdb;

		$table    = $this->table();
		$existing = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
		$row      = $wpdb->get_row(
			$wpdb->prepare( 'SELECT metadata FROM `' . esc_sql( $table ) . '` WHERE id = %d', $id ),
			ARRAY_A
		);
		if ( is_array( $row ) && ! empty( $row['metadata'] ) ) {
			$decoded = json_decode( (string) $row['metadata'], true );
			if ( is_array( $decoded ) ) {
				$existing = $decoded;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		return false !== $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'metadata'   => (string) wp_json_encode( array_merge( $existing, $extra_metadata ) ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}
}
