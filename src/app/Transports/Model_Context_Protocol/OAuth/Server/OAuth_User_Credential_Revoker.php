<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use WP_User;

/**
 * Retire every MCP credential bound to a user when that user's standing changes.
 *
 * WordPress destroys sessions on a password change and removes capabilities on
 * a role change. Bearer tokens bound to that user would otherwise keep working
 * until they expire, which is not what an administrator running a
 * compromised-account playbook expects.
 */
final class OAuth_User_Credential_Revoker {

	private Token_Manager $token_manager;

	private OAuth_Artifact_Store $store;

	private OAuth_User_Revocation_Marker $marker;

	/**
	 * Password hash each user's credentials were last revoked for in this request.
	 *
	 * @var array<int, string>
	 */
	private array $revoked_password_hashes = array();

	/**
	 * Users whose credentials on the other network sites wait for shutdown,
	 * keyed by user ID with the site already revoked as the value.
	 *
	 * @var array<int, int>
	 */
	private array $pending_network_sweeps = array();

	/**
	 * Former super admins whose credentials on the other network sites wait
	 * for shutdown, keyed by user ID with the site already handled as the value.
	 *
	 * @var array<int, int>
	 */
	private array $pending_super_admin_sweeps = array();

	/**
	 * Users wp_insert_user() created in this request. Before this request they
	 * had no role, no capability and no MCP credential.
	 *
	 * @var array<int, true>
	 */
	private array $users_created_in_request = array();

	/**
	 * Role removals decided in this request that no set_user_role has closed
	 * yet, per site and user: the roles the user held after them, the roles removed and
	 * whether they revoked. set_role() reports each removed role through
	 * remove_user_role and then the whole change through set_user_role; the
	 * second report is not decided again.
	 *
	 * @var array<int, array<int, array{roles: string[], removed: array<string, true>, revoked: bool}>>
	 */
	private array $role_removals = array();

	/**
	 * Constructor.
	 */
	public function __construct( ?Token_Manager $token_manager = null, ?OAuth_Artifact_Store $store = null, ?OAuth_User_Revocation_Marker $marker = null ) {
		$this->token_manager = $token_manager ?? new Token_Manager();
		$this->store         = $store ?? new OAuth_Artifact_Store();
		$this->marker        = $marker ?? new OAuth_User_Revocation_Marker( $this->store );
	}

	/**
	 * Hook the user lifecycle events that end a user's authority on this site.
	 */
	public function register(): void {
		// Core added this hook in WordPress 6.2; the user ID is its second
		// argument and 6.7 added the previous user data as the third. Keep the
		// reset/profile hooks for older supported cores.
		add_action( 'wp_set_password', array( $this, 'on_password_set' ), 10, 3 );
		add_action( 'after_password_reset', array( $this, 'on_password_reset' ), 10, 1 );
		add_action( 'profile_update', array( $this, 'on_profile_update' ), 10, 2 );
		// wp_insert_user() gives a new user their first role through set_role().
		// This filter runs before that, so the role change can tell the user is new.
		add_filter( 'insert_user_meta', array( $this, 'remember_created_user' ), 10, 3 );
		add_action( 'set_user_role', array( $this, 'on_role_set' ), 10, 3 );
		// remove_role() does not fire set_user_role. Retire lost authority now
		// so restoring the administrator role cannot revive old credentials.
		add_action( 'remove_user_role', array( $this, 'on_role_removed' ), 10, 2 );
		add_action( 'add_user_role', array( $this, 'on_role_added' ), 10, 2 );
		// Fires only once the user is no longer a super admin; revoke_super_admin
		// fires before, also for a user who is not one.
		add_action( 'revoked_super_admin', array( $this, 'on_super_admin_revoked' ), 10, 1 );
		add_action( 'remove_user_from_blog', array( $this, 'on_user_removed' ), 10, 1 );
		// delete_user fires while the user's meta still exists; deleted_user
		// would find the encrypted aggregate already gone.
		add_action( 'delete_user', array( $this, 'on_user_removed' ), 10, 1 );
		add_action( 'wpmu_delete_user', array( $this, 'on_user_removed' ), 10, 1 );
	}

	/**
	 * Revoke every credential bound to a user on the current site.
	 *
	 * @return int Number of access credentials revoked.
	 */
	public function revoke_for_user( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}

		// Before cleanup: a family skipped below must still be refused.
		$this->marker->mark( $user_id );
		$this->store->revoke_families_for_user( $user_id );

		return (int) $this->token_manager->revoke_user_tokens( $user_id );
	}

	/**
	 * A password reset through the lost-password flow.
	 *
	 * @param WP_User|mixed $user Reset user.
	 */
	public function on_password_reset( $user ): void {
		if ( $user instanceof WP_User ) {
			$this->revoke_after_password_change( (int) $user->ID );
		}
	}

	/**
	 * Direct password updates through the core setter (WordPress 6.2+).
	 *
	 * WordPress 6.8+ also calls the setter at login to rehash the same
	 * password. When core passes the previous user data (6.7+), a password
	 * that still matches the previous hash is not a change.
	 *
	 * @param mixed         $password      Plaintext password; only compared, never retained.
	 * @param int|mixed     $user_id       Updated user.
	 * @param WP_User|mixed $old_user_data User before the update (WordPress 6.7+).
	 */
	public function on_password_set( $password, $user_id, $old_user_data = null ): void {
		if (
			$old_user_data instanceof WP_User
			&& is_string( $password )
			&& '' !== (string) $old_user_data->user_pass
			// No user ID: older cores rehash an MD5 match through wp_set_password().
			&& wp_check_password( $password, (string) $old_user_data->user_pass )
		) {
			return;
		}

		$this->revoke_after_password_change( (int) $user_id );
	}

	/**
	 * Passwords are network-global, while MCP credentials are site-specific.
	 *
	 * A reset fires both the setter and the reset hook for one new hash, so
	 * revoke once per hash. Other network sites are swept once per user at
	 * shutdown instead of inside the login, reset or profile flow.
	 */
	private function revoke_after_password_change( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_userdata( $user_id );
		$hash = $user instanceof WP_User ? (string) $user->user_pass : '';
		if ( '' !== $hash && ( $this->revoked_password_hashes[ $user_id ] ?? null ) === $hash ) {
			return;
		}
		$this->revoked_password_hashes[ $user_id ] = $hash;

		$this->revoke_for_user( $user_id );
		if ( ! is_multisite() ) {
			return;
		}

		if ( did_action( 'shutdown' ) ) {
			$this->revoke_on_other_sites( $user_id, get_current_blog_id() );
			return;
		}
		$this->schedule_network_sweeps();
		$this->pending_network_sweeps[ $user_id ] = get_current_blog_id();
	}

	/**
	 * Run the queued network sweeps once, at shutdown.
	 */
	private function schedule_network_sweeps(): void {
		if ( false === has_action( 'shutdown', array( $this, 'run_pending_network_sweeps' ) ) ) {
			add_action( 'shutdown', array( $this, 'run_pending_network_sweeps' ) );
		}
	}

	/**
	 * Sweep the other network sites for each user queued during this request.
	 *
	 * A former super admin whose password also changed is already swept on
	 * every other site by the password sweep.
	 */
	public function run_pending_network_sweeps(): void {
		$pending                          = $this->pending_network_sweeps;
		$super_admins                     = array_diff_key( $this->pending_super_admin_sweeps, $pending );
		$this->pending_network_sweeps     = array();
		$this->pending_super_admin_sweeps = array();
		foreach ( $pending as $user_id => $revoked_site ) {
			$this->sweep_other_sites( (int) $user_id, (int) $revoked_site, false, 'password_change_revocation' );
		}
		foreach ( $super_admins as $user_id => $handled_site ) {
			$this->sweep_other_sites( (int) $user_id, (int) $handled_site, true, 'super_admin_revocation' );
		}
	}

	/**
	 * One user's queued sweep of the other network sites.
	 *
	 * @param string $event Telemetry event recorded when the sweep fails.
	 */
	private function sweep_other_sites( int $user_id, int $handled_site, bool $only_where_site_control_lost, string $event ): void {
		// This runs after the response. An error here must not become a fatal
		// error page or stop the other users' sweeps; record it instead.
		try {
			$this->revoke_on_other_sites( $user_id, $handled_site, $only_where_site_control_lost );
		} catch ( \Throwable $error ) {
			try {
				OAuth_Telemetry::record( $event, 'other_sites_failed', '', $user_id );
			} catch ( \Throwable $unrecorded ) {
				unset( $unrecorded );
			}
		}
	}

	/**
	 * Visit every other site, including sites without current user membership
	 * or an access-token aggregate: a super-admin's refresh family may remain there.
	 *
	 * @param bool $only_where_site_control_lost Skip the sites where the user can still manage options.
	 */
	private function revoke_on_other_sites( int $user_id, int $revoked_site, bool $only_where_site_control_lost = false ): void {
		$offset = 0;
		do {
			$site_ids = get_sites(
				array(
					'fields'        => 'ids',
					'number'        => 100,
					'offset'        => $offset,
					'orderby'       => 'id',
					'order'         => 'ASC',
					'no_found_rows' => true,
				)
			);
			foreach ( $site_ids as $site_id ) {
				if ( $revoked_site === (int) $site_id ) {
					continue;
				}
				switch_to_blog( (int) $site_id );
				try {
					$this->revoke_on_current_site( $user_id, $only_where_site_control_lost );
				} finally {
					restore_current_blog();
				}
			}
			$page_count = count( $site_ids );
			$offset    += $page_count;
		} while ( 100 === $page_count );
	}

	/**
	 * Revoke on the current site, or only where the user can no longer manage options.
	 */
	private function revoke_on_current_site( int $user_id, bool $only_where_site_control_lost ): void {
		if ( $only_where_site_control_lost && user_can( $user_id, 'manage_options' ) ) {
			return;
		}

		$this->revoke_for_user( $user_id );
	}

	/**
	 * A user stopped being a super admin (multisite).
	 *
	 * Super admin status let the user manage options on every site of the
	 * network. On each site where no role still lets them, their credentials
	 * are retired, as for an administrator demoted there; a site where they
	 * remain an administrator keeps them. This site is handled now, the other
	 * sites at shutdown, as for a password change.
	 *
	 * @since 8.0.0
	 *
	 * @param int|mixed $user_id Former super admin.
	 */
	public function on_super_admin_revoked( $user_id ): void {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! is_multisite() ) {
			return;
		}

		$this->revoke_on_current_site( $user_id, true );
		if ( did_action( 'shutdown' ) ) {
			$this->revoke_on_other_sites( $user_id, get_current_blog_id(), true );
			return;
		}
		$this->schedule_network_sweeps();
		$this->pending_super_admin_sweeps[ $user_id ] = get_current_blog_id();
	}

	/**
	 * A profile save that changed the password hash.
	 *
	 * @param int|mixed     $user_id       Updated user.
	 * @param WP_User|mixed $old_user_data User before the save.
	 */
	public function on_profile_update( $user_id, $old_user_data = null ): void {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! $old_user_data instanceof WP_User ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User && (string) $user->user_pass !== (string) $old_user_data->user_pass ) {
			$this->revoke_after_password_change( $user_id );
		}
	}

	/**
	 * A role removed through WP_User::remove_role() or WP_User::set_role().
	 *
	 * The same rule as a role set: revokes when the user can no longer manage
	 * options and either could before the removal or holds MCP credentials on
	 * this site. A user created in this request holds none. When the removed
	 * role or the user cannot be read, it revokes. set_role() reports every
	 * role it removes after the new role is in place, so one change revokes
	 * once however many roles it removes.
	 *
	 * @since 8.0.0
	 *
	 * @param int|mixed $user_id Changed user.
	 * @param mixed     $role    Removed role.
	 */
	public function on_role_removed( $user_id, $role = null ): void {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}

		$roles_after = $this->current_roles( $user_id );
		$removal     = $this->removal_leaving( $user_id, $roles_after );
		if ( is_string( $role ) && '' !== $role ) {
			$removal['removed'][ $role ] = true;
		}

		if (
			! user_can( $user_id, 'manage_options' )
			&& (
				$removal['revoked']
					? $this->holds_credentials_since_decision( $user_id )
					: $this->role_change_needs_revocation( $user_id, $this->roles_before_removal( $roles_after, $role ) )
			)
		) {
			$this->revoke_for_user( $user_id );
			$removal['revoked'] = true;
		}

		if ( null !== $roles_after ) {
			$this->role_removals[ get_current_blog_id() ][ $user_id ] = $removal;
		}
	}

	/**
	 * A role added back after its removal was decided: a later report of that
	 * removal is a new change, decided again.
	 *
	 * @since 8.0.0
	 *
	 * @param int|mixed $user_id Changed user.
	 * @param mixed     $role    Added role.
	 */
	public function on_role_added( $user_id, $role = null ): void {
		$user_id = (int) $user_id;
		if ( is_string( $role ) && isset( $this->role_removals[ get_current_blog_id() ][ $user_id ]['removed'][ $role ] ) ) {
			unset( $this->role_removals[ get_current_blog_id() ][ $user_id ] );
		}
	}

	/**
	 * The removals in progress that left the user with these roles, or a new record.
	 *
	 * @param string[]|null $roles_after The user's roles now; null when the user cannot be read.
	 * @return array{roles: string[], removed: array<string, true>, revoked: bool}
	 */
	private function removal_leaving( int $user_id, ?array $roles_after ): array {
		$removal = $this->role_removals[ get_current_blog_id() ][ $user_id ] ?? null;
		if ( null !== $roles_after && null !== $removal && $removal['roles'] === $roles_after ) {
			return $removal;
		}

		return array(
			'roles'   => (array) $roles_after,
			'removed' => array(),
			'revoked' => false,
		);
	}

	/**
	 * The roles a user held before a removal: the roles held now and the removed one.
	 *
	 * @param string[]|null $roles_after  The user's roles now.
	 * @param mixed         $removed_role The removed role.
	 * @return string[]|null Null when either cannot be read.
	 */
	private function roles_before_removal( ?array $roles_after, $removed_role ): ?array {
		if ( null === $roles_after || ! is_string( $removed_role ) || '' === $removed_role ) {
			return null;
		}

		return array_merge( $roles_after, array( $removed_role ) );
	}

	/**
	 * The user's roles on this site, sorted.
	 *
	 * @return string[]|null Null when the user cannot be loaded.
	 */
	private function current_roles( int $user_id ): ?array {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return null;
		}

		$roles = array_values( array_map( 'strval', (array) $user->roles ) );
		sort( $roles );

		return $roles;
	}

	/**
	 * Whether remove_user_role already decided every role this role set removed.
	 *
	 * Closes the user's removals in progress either way. They decided the
	 * change when every role set_user_role names as removed is one of theirs
	 * and the user still holds the roles they left.
	 *
	 * @param mixed $role      New role.
	 * @param mixed $old_roles Roles before the change.
	 */
	private function removals_decided( int $user_id, $role, $old_roles ): bool {
		$removal = $this->role_removals[ get_current_blog_id() ][ $user_id ] ?? null;
		unset( $this->role_removals[ get_current_blog_id() ][ $user_id ] );
		if ( null === $removal || ! is_array( $old_roles ) ) {
			return false;
		}

		$removed = array();
		foreach ( $old_roles as $old_role ) {
			if ( ! is_string( $old_role ) || '' === $old_role ) {
				return false;
			}
			if ( $old_role !== $role ) {
				$removed[ $old_role ] = true;
			}
		}

		return array() !== $removed
			&& array() === array_diff_key( $removed, $removal['removed'] )
			&& $removal['roles'] === $this->current_roles( $user_id );
	}

	/**
	 * Record a user wp_insert_user() is creating, before it assigns their first role.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $meta   User meta about to be saved; returned unchanged.
	 * @param mixed $user   User being saved.
	 * @param mixed $update Whether an existing user is being updated.
	 * @return mixed
	 */
	public function remember_created_user( $meta, $user = null, $update = true ) {
		if ( false === $update && $user instanceof WP_User && $user->ID > 0 ) {
			$this->users_created_in_request[ (int) $user->ID ] = true;
		}

		return $meta;
	}

	/**
	 * A role assigned through WP_User::set_role(), including a new user's first role.
	 *
	 * Revokes when the user can no longer manage options and either could
	 * before the change or holds MCP credentials on this site. A user created
	 * in this request could not and holds none. When the previous roles or the
	 * stored credentials cannot be read, it revokes. A change whose removed
	 * roles remove_user_role already decided is not decided again.
	 *
	 * @since 8.0.0
	 *
	 * @param int|mixed $user_id   Changed user.
	 * @param mixed     $role      New role; only tells which previous roles were removed.
	 * @param mixed     $old_roles Roles before the change, which core passes.
	 */
	public function on_role_set( $user_id, $role = '', $old_roles = null ): void {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		$decided = $this->removals_decided( $user_id, $role, $old_roles );
		if ( user_can( $user_id, 'manage_options' ) ) {
			return;
		}

		if ( $decided ? $this->holds_credentials_since_decision( $user_id ) : $this->role_change_needs_revocation( $user_id, $old_roles ) ) {
			$this->revoke_for_user( $user_id );
		}
	}

	/**
	 * Whether a role removal already decided left work to do. Revoking removes
	 * every credential, so one found now was issued after that decision, and a
	 * single role change still revokes once. When it cannot be read, it revokes.
	 */
	private function holds_credentials_since_decision( int $user_id ): bool {
		return false !== $this->holds_mcp_credentials( $user_id );
	}

	/**
	 * Decide a role change for a user who can no longer manage options.
	 *
	 * @param mixed $old_roles Roles before the change.
	 */
	private function role_change_needs_revocation( int $user_id, $old_roles ): bool {
		if ( false !== $this->could_manage_options_before( $user_id, $old_roles ) ) {
			return true;
		}
		if ( isset( $this->users_created_in_request[ $user_id ] ) ) {
			return false;
		}

		return false !== $this->holds_mcp_credentials( $user_id );
	}

	/**
	 * Whether the user could manage options with the roles they held before the change.
	 *
	 * set_role() swaps the roles and keeps the user's own capabilities, so
	 * this puts the previous roles back on a copy of the user and asks
	 * WordPress. Capability filters and super admin status apply as usual.
	 *
	 * @param mixed $old_roles Roles before the change.
	 * @return bool|null Null when the previous roles or the user cannot be read.
	 */
	private function could_manage_options_before( int $user_id, $old_roles ): ?bool {
		if ( ! is_array( $old_roles ) ) {
			return null;
		}

		$before = new WP_User( $user_id );
		if ( ! $before->exists() ) {
			return null;
		}

		$caps = array_diff_key( (array) $before->caps, array_flip( $before->roles ) );
		foreach ( $old_roles as $old_role ) {
			if ( ! is_string( $old_role ) || '' === $old_role ) {
				return null;
			}
			$caps[ $old_role ] = true;
		}
		$before->caps = $caps;
		$before->get_role_caps();

		return $before->has_cap( 'manage_options' );
	}

	/**
	 * Whether the user holds access tokens, refresh families or pending
	 * authorization codes on this site. A pending code counts because it becomes
	 * a token when exchanged, after the change this is deciding.
	 *
	 * @return bool|null Null when any store cannot be read.
	 */
	private function holds_mcp_credentials( int $user_id ): ?bool {
		$tokens = $this->token_manager->has_user_credentials( $user_id );
		if ( false !== $tokens ) {
			return $tokens;
		}

		$families = $this->store->has_live_families_for_user( $user_id );
		if ( false !== $families ) {
			return $families;
		}

		return $this->store->has_pending_codes_for_user( $user_id );
	}

	/**
	 * A user removed from this site or deleted.
	 *
	 * @param int|mixed $user_id Removed user.
	 */
	public function on_user_removed( $user_id ): void {
		$this->revoke_for_user( (int) $user_id );
	}
}
