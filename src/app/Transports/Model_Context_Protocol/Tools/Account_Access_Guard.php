<?php
/**
 * Keeps the Automator actions AI apps run from giving out administrator-level access.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

/**
 * Account_Access_Policy checks an action's fields before it is saved or run,
 * but a field cannot show everything an action gives: a role from a token, a
 * custom role whose capabilities changed, or another plugin's user action.
 * While an AI app runs Automator actions (run_action, run_recipe), this guard
 * refuses the access where WordPress saves it, before anything is written:
 *
 * - user capabilities, from a role, an added capability or a direct meta
 *   write, that add a capability in Account_Access_Policy::ADMIN_CAPABILITIES,
 *   and a user level of 8 to 10, which older plugins treat as administrator,
 *   for a user without that access;
 * - a role definition that gains such a capability, except while a plugin is
 *   being activated, since plugins create their own roles then;
 * - a new user with such a role, and a password or email address change for a
 *   user who has that access on any site;
 * - a network super admin.
 *
 * The refusal stops the action with a message for the AI app, and the tool
 * reports it even when the action's own error handling caught it.
 *
 * wp_set_password() saves without a filter. The one Automator action that
 * calls it, Sucuri's password reset, sets a random password and mails the
 * reset link to the account's own address, so it hands no account over.
 *
 * Runs the site owner starts, including recipes an AI app saved, are not
 * guarded; Account_Access_Policy decides what those recipes may contain.
 */
final class Account_Access_Guard {

	/** Older plugins treat user levels 8 to 10 as administrator access. */
	private const ADMIN_USER_LEVEL = 8;

	/**
	 * How many guarded runs are running, nested.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * The first refusal of the latest guarded run.
	 *
	 * @var Account_Access_Refused|null
	 */
	private static ?Account_Access_Refused $refusal = null;

	/**
	 * Run AI work with the access guarded.
	 *
	 * @template T
	 * @param callable(): T $work The run.
	 * @return T
	 */
	public static function during( callable $work ) {
		self::hook();
		if ( 0 === self::$depth ) {
			self::$refusal = null;
		}
		++self::$depth;
		try {
			return $work();
		} finally {
			--self::$depth;
		}
	}

	/**
	 * The first access the latest guarded run tried to give, or null.
	 *
	 * @return Account_Access_Refused|null
	 */
	public static function refusal(): ?Account_Access_Refused {
		return self::$refusal;
	}

	/**
	 * Refuse user capabilities and user levels before they are saved.
	 *
	 * @param mixed $check      Another filter's answer; null lets WordPress write.
	 * @param mixed $user_id    User ID.
	 * @param mixed $meta_key   Meta key.
	 * @param mixed $meta_value New value.
	 * @return mixed
	 *
	 * @throws Account_Access_Refused When the value gives administrator-level access.
	 */
	public static function check_user_meta( $check, $user_id, $meta_key, $meta_value ) {
		if ( 0 === self::$depth || null !== $check ) {
			return $check;
		}
		$key = self::user_meta_key( $meta_key );
		if ( null === $key ) {
			return $check;
		}

		list( $kind, $site_id ) = $key;
		$user                   = self::user_on( (int) $user_id, $site_id );
		$had                    = self::admin_capabilities_of( $user );
		if ( self::initializing_site( $site_id ) ) {
			return $check;
		}

		if ( 'capabilities' === $kind ) {
			$caps  = is_array( $meta_value ) ? $meta_value : array();
			$added = array_values( array_diff( self::admin_capabilities_given( $caps, self::roles_on( $site_id ) ), $had ) );
			if ( array() !== $added ) {
				self::refuse( self::grant_message( $user, self::what_is_given( $caps, $user->caps, self::roles_on( $site_id ) ), $added ) );
			}
			return $check;
		}

		if ( is_numeric( $meta_value ) && (int) $meta_value >= self::ADMIN_USER_LEVEL && (int) $meta_value > array_reduce( array_keys( array_filter( $user->allcaps ) ), array( $user, 'level_reduction' ), 0 ) ) {
			self::refuse(
				sprintf(
					'Automator refused to set the user level of %1$s to %2$d. Older plugins treat levels 8 to 10 as administrator access, which AI apps can\'t give through Automator actions, so it was not saved. Change roles with a role action instead, using a role without account or code rights.',
					self::user_label( $user ),
					(int) $meta_value
				)
			);
		}

		return $check;
	}

	/**
	 * Refuse a role definition that gains administrator-level capabilities.
	 *
	 * @param mixed $value     New value.
	 * @param mixed $option    Option name.
	 * @param mixed $old_value Current value.
	 * @return mixed
	 *
	 * @throws Account_Access_Refused When a role gains such a capability.
	 */
	public static function check_roles( $value, $option, $old_value ) {
		if ( 0 === self::$depth || self::on_stack( 'activate_plugin' ) || self::initializing_site( get_current_blog_id() ) || ! is_array( $value ) || null === self::site_of( $option, 'user_roles' ) ) {
			return $value;
		}
		$old = is_array( $old_value ) ? $old_value : array();

		foreach ( $value as $slug => $role ) {
			$role   = is_array( $role ) ? $role : array();
			$before = is_array( $old[ $slug ] ?? null ) ? $old[ $slug ] : array();
			$added  = array_values( array_diff( self::admin_in( $role['capabilities'] ?? null ), self::admin_in( $before['capabilities'] ?? null ) ) );
			if ( array() === $added ) {
				continue;
			}
			// WP_Roles and WP_Role mutate memory before update_option. Roll back
			// the actual site-specific object as well as any held role reference.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Restore role objects core mutated before its rejected write.
			foreach ( debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
				$object = $frame['object'] ?? null;
				if ( $object instanceof \WP_Role ) {
					$object->capabilities = (array) ( $old[ $object->name ]['capabilities'] ?? array() );
				}
				if ( $object instanceof \WP_Roles && $object->role_key === $option ) {
					foreach ( $object->role_objects as $name => $role_object ) {
						$role_object->capabilities = (array) ( $old[ $name ]['capabilities'] ?? array() );
					}
					$object->roles        = $old;
					$object->role_objects = array();
					$object->role_names   = array();
					$object->init_roles();
				}
			}
			self::refuse(
				sprintf(
					'Automator refused to %1$s the role %2$s (%3$s) with capabilities that manage accounts or run code (%4$s). AI apps can\'t define roles with that access through Automator actions, so the role was not saved. Leave those capabilities out, or ask the site owner to add them with a role editor.',
					isset( $old[ $slug ] ) ? 'change' : 'create',
					is_string( $role['name'] ?? null ) ? translate_user_role( $role['name'] ) : (string) $slug,
					(string) $slug,
					implode( ', ', $added )
				)
			);
		}

		return $value;
	}

	/**
	 * Refuse a new administrator, a role change that gives that access, and a
	 * new password or email address for an account that has it, before the
	 * user is saved.
	 *
	 * @param mixed    $data     User row to save.
	 * @param bool     $update   Whether an existing user is updated.
	 * @param int|null $user_id  User ID when updating.
	 * @param mixed    $userdata What wp_insert_user() was given.
	 * @return mixed
	 *
	 * @throws Account_Access_Refused When the save gives or takes over such access.
	 */
	public static function check_user_data( $data, $update, $user_id, $userdata ) {
		if ( 0 === self::$depth || ! is_array( $data ) ) {
			return $data;
		}
		$userdata = is_array( $userdata ) ? $userdata : array();

		if ( ! $update ) {
			$role  = array_key_exists( 'role', $userdata ) ? (string) $userdata['role'] : (string) get_option( 'default_role' );
			$admin = self::admin_capabilities_given( array( $role => true ), wp_roles() );
			if ( array() !== $admin ) {
				self::refuse(
					sprintf(
						'Automator refused to create the user %1$s with %2$s, which can manage accounts or run code (%3$s). AI apps can\'t give that access through Automator actions, so the user was not created. Create it with a role without those rights: %4$s. The site owner can change the role later on the WordPress Users screen.',
						(string) ( $data['user_login'] ?? '' ),
						self::role_label( $role ),
						implode( ', ', $admin ),
						self::ordinary_roles()
					)
				);
			}
			return $data;
		}

		$user = self::user_on( (int) $user_id, get_current_blog_id() );
		if ( isset( $userdata['role'] ) ) {
			$added = array_values( array_diff( self::admin_capabilities_given( array( (string) $userdata['role'] => true ), wp_roles() ), self::admin_capabilities_of( $user ) ) );
			if ( array() !== $added ) {
				self::refuse( self::grant_message( $user, self::role_label( (string) $userdata['role'] ), $added ) );
			}
		}

		$changes = array();
		if ( isset( $data['user_pass'] ) && $data['user_pass'] !== $user->user_pass ) {
			$changes[] = 'password';
		}
		if ( isset( $data['user_email'] ) && $data['user_email'] !== $user->user_email ) {
			$changes[] = 'email address';
		}
		if ( array() !== $changes && self::has_admin_access_anywhere( $user ) ) {
			self::refuse(
				sprintf(
					'Automator refused to change the %1$s of %2$s, who can manage accounts or run code. AI apps can\'t change how such an account signs in, so the account was not changed. Ask the site owner to change it on that user\'s profile screen.',
					implode( ' and ', $changes ),
					self::user_label( $user )
				)
			);
		}

		return $data;
	}

	/**
	 * Refuse a network super admin before WordPress saves one.
	 *
	 * @param mixed $user_id User ID.
	 * @return void
	 *
	 * @throws Account_Access_Refused During a guarded run.
	 */
	public static function check_super_admin( $user_id ): void {
		if ( 0 === self::$depth ) {
			return;
		}
		self::refuse(
			sprintf(
				'Automator refused to make %s a network super admin, who can manage every site and account on the network. AI apps can\'t give that access through Automator actions, so it was not given. Ask a network super admin to do it under Network Admin, Users.',
				self::user_label( new \WP_User( (int) $user_id ) )
			)
		);
	}

	/** A real global function frame cannot be opened by firing its action hook. */
	private static function on_stack( string $function_name ): bool {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Verify a core function frame, not an action name.
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
			if ( ! isset( $frame['class'] ) && ( $frame['function'] ?? '' ) === $function_name ) {
				return true;
			}
		}
		return false;
	}

	/** Core creates roles and the initial administrator while installing a new site. */
	private static function initializing_site( int $site_id ): bool {
		if ( ! is_multisite() || ! wp_installing() ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Match the site being initialized by core.
		foreach ( debug_backtrace( 0 ) as $frame ) {
			if ( isset( $frame['class'] ) || 'wp_initialize_site' !== ( $frame['function'] ?? '' ) ) {
				continue;
			}
			$site = $frame['args'][0] ?? null;
			return (int) ( $site instanceof \WP_Site ? $site->blog_id : $site ) === $site_id;
		}
		return false;
	}

	/** Protect the default role before either adding or updating the option. */
	public static function check_default_role( $value ) {
		if ( 0 < self::$depth && is_string( $value ) && array() !== self::admin_capabilities_given( array( $value => true ), wp_roles() ) ) {
			self::refuse( 'Automator refused to make an administrator-level role the default for new users. Choose a role without account or code rights.' );
		}
		return $value;
	}

	/** Protect direct network-option writes, including the first site_admins value. */
	public static function check_site_admins( $value, $old_value = array() ) {
		if ( 0 < self::$depth && is_array( $value ) && array() !== array_diff( $value, (array) $old_value ) ) {
			self::refuse( 'Automator refused to add a network super administrator. The site owner can do this in Network Admin.' );
		}
		return $value;
	}

	/** Check protected fields before another action or worker receives them. */
	public static function check_action( $action ) {
		if ( 0 < self::$depth && is_array( $action ) ) {
			$fields  = (array) ( $action['action_data']['meta'] ?? array() );
			$refusal = ( new Account_Access_Policy() )->action_refusal( (string) ( $fields['code'] ?? '' ), $fields );
			if ( null !== $refusal ) {
				self::refuse( $refusal );
			}
		}
		return $action;
	}

	/**
	 * Register write checks; their depth check leaves owner-initiated runs alone.
	 */
	private static function hook(): void {
		// Checked each time: tests and other code may reset WordPress's hooks.
		if ( false !== has_filter( 'update_user_metadata', array( self::class, 'check_user_meta' ) ) ) {
			return;
		}
		// Each runs before WordPress writes anything.
		add_filter( 'automator_before_action_executed', array( self::class, 'check_action' ), PHP_INT_MIN );
		add_filter( 'update_user_metadata', array( self::class, 'check_user_meta' ), PHP_INT_MAX, 4 );
		add_filter( 'add_user_metadata', array( self::class, 'check_user_meta' ), PHP_INT_MAX, 4 );
		add_filter( 'pre_update_option', array( self::class, 'check_roles' ), PHP_INT_MAX, 3 );
		add_filter( 'wp_pre_insert_user_data', array( self::class, 'check_user_data' ), PHP_INT_MAX, 4 );
		add_action( 'grant_super_admin', array( self::class, 'check_super_admin' ), PHP_INT_MAX, 1 );
		add_filter( 'sanitize_option_default_role', array( self::class, 'check_default_role' ), PHP_INT_MAX );
		add_filter( 'pre_update_site_option_site_admins', array( self::class, 'check_site_admins' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_add_site_option_site_admins', array( self::class, 'check_site_admins' ), PHP_INT_MAX );
	}

	/**
	 * @param string $message For the AI app.
	 * @return void
	 *
	 * @throws Account_Access_Refused Always.
	 */
	private static function refuse( string $message ): void {
		$refusal       = new Account_Access_Refused( $message );
		self::$refusal = self::$refusal ?? $refusal;
		throw $refusal;
	}

	/**
	 * Which user meta a key reaches, as the database matches it: the kind and
	 * the site, or null.
	 *
	 * @param mixed $meta_key Meta key.
	 * @return array{0: string, 1: int}|null
	 */
	private static function user_meta_key( $meta_key ): ?array {
		foreach ( array( 'capabilities', 'user_level' ) as $kind ) {
			$site_id = self::site_of( $meta_key, $kind );
			if ( null !== $site_id ) {
				return array( $kind, $site_id );
			}
		}

		return null;
	}

	/**
	 * The site whose per-site key a name reaches, or null. Names are compared
	 * as the database compares them: without regard to case, accents or
	 * trailing spaces, and without bytes that are not UTF-8.
	 *
	 * @param mixed  $name   Meta key or option name.
	 * @param string $suffix The per-site key without its table prefix.
	 * @return int|null
	 */
	private static function site_of( $name, string $suffix ): ?int {
		if ( ! is_string( $name ) ) {
			return null;
		}
		global $wpdb;
		$plain  = strtolower( rtrim( remove_accents( wp_check_invalid_utf8( $name, true ) ), ' ' ) );
		$prefix = preg_quote( strtolower( $wpdb->base_prefix ), '/' );
		if ( 1 !== preg_match( '/^' . $prefix . '(?:(\d+)_)?' . preg_quote( $suffix, '/' ) . '$/', $plain, $match ) ) {
			return null;
		}

		return isset( $match[1] ) && '' !== $match[1] ? (int) $match[1] : ( is_multisite() ? 1 : get_current_blog_id() );
	}

	/**
	 * @param int $user_id User ID.
	 * @param int $site_id Site whose access is read.
	 * @return \WP_User
	 */
	private static function user_on( int $user_id, int $site_id ): \WP_User {
		$user = new \WP_User( $user_id );
		if ( is_multisite() && $user->get_site_id() !== $site_id ) {
			$user->for_site( $site_id );
		}

		return $user;
	}

	/**
	 * @param int $site_id Site ID.
	 * @return \WP_Roles The site's roles.
	 */
	private static function roles_on( int $site_id ): \WP_Roles {
		$roles = wp_roles();

		return ! is_multisite() || $roles->get_site_id() === $site_id ? $roles : new \WP_Roles( $site_id );
	}

	/**
	 * The administrator-level capabilities a capabilities value gives, worked
	 * out as WordPress does: every role it names, then its own capabilities.
	 *
	 * @param array     $caps  Capabilities value.
	 * @param \WP_Roles $roles The site's roles.
	 * @return string[]
	 */
	private static function admin_capabilities_given( array $caps, \WP_Roles $roles ): array {
		$all = array();
		foreach ( array_keys( $caps ) as $name ) {
			$role = $roles->get_role( (string) $name );
			if ( null !== $role ) {
				$all = array_merge( $all, (array) $role->capabilities );
			}
		}

		return self::admin_in( array_merge( $all, $caps ) );
	}

	/**
	 * @param \WP_User $user User, on the site being changed.
	 * @return string[] The administrator-level capabilities the user has there.
	 */
	private static function admin_capabilities_of( \WP_User $user ): array {
		if ( $user->exists() && is_multisite() && is_super_admin( $user->ID ) ) {
			return Account_Access_Policy::ADMIN_CAPABILITIES;
		}

		return self::admin_in( $user->allcaps );
	}

	/**
	 * @param \WP_User $user User.
	 * @return bool Whether the user has administrator-level access on any of their sites.
	 */
	private static function has_admin_access_anywhere( \WP_User $user ): bool {
		if ( array() !== self::admin_capabilities_of( $user ) ) {
			return true;
		}
		if ( ! is_multisite() || ! $user->exists() ) {
			return false;
		}
		foreach ( array_keys( get_blogs_of_user( $user->ID, true ) ) as $site_id ) {
			$user->for_site( (int) $site_id );
			if ( array() !== self::admin_in( $user->allcaps ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param mixed $caps Capability => granted.
	 * @return string[] The granted ones that manage accounts or run code.
	 */
	private static function admin_in( $caps ): array {
		$granted = is_array( $caps ) ? array_keys( array_filter( $caps ) ) : array();

		return array_values( array_intersect( Account_Access_Policy::ADMIN_CAPABILITIES, array_map( 'strval', $granted ) ) );
	}

	/**
	 * Name the roles and capabilities a new value adds.
	 *
	 * @param array     $caps  New capabilities value.
	 * @param array     $old   Current capabilities value.
	 * @param \WP_Roles $roles The site's roles.
	 * @return string
	 */
	private static function what_is_given( array $caps, array $old, \WP_Roles $roles ): string {
		$given = array();
		foreach ( array_diff_key( array_filter( $caps ), array_filter( $old ) ) as $name => $on ) {
			$name = (string) $name;
			if ( null !== $roles->get_role( $name ) ) {
				$given[] = self::role_label( $name );
			} elseif ( in_array( $name, Account_Access_Policy::ADMIN_CAPABILITIES, true ) ) {
				$given[] = 'the capability ' . $name;
			}
		}

		return array() === $given ? 'these capabilities' : implode( ' and ', $given );
	}

	/**
	 * @param \WP_User $user  User.
	 * @param string   $what  The roles or capabilities given.
	 * @param string[] $added The administrator-level capabilities they add.
	 * @return string
	 */
	private static function grant_message( \WP_User $user, string $what, array $added ): string {
		return sprintf(
			'Automator refused to give %1$s %2$s, which can manage accounts or run code (%3$s). AI apps can\'t give that access through Automator actions, so it was not given. Use a role without those rights: %4$s. If this person needs that access, ask the site owner to give it on the WordPress Users screen.',
			self::user_label( $user ),
			$what,
			implode( ', ', $added ),
			self::ordinary_roles()
		);
	}

	/**
	 * @param \WP_User $user User.
	 * @return string
	 */
	private static function user_label( \WP_User $user ): string {
		return $user->exists() ? sprintf( 'user %d (%s)', $user->ID, $user->user_login ) : 'the user';
	}

	/**
	 * @param string $slug Role slug.
	 * @return string Such as "the Administrator role (administrator)".
	 */
	private static function role_label( string $slug ): string {
		$names = wp_roles()->get_names();

		return sprintf( 'the %1$s role (%2$s)', isset( $names[ $slug ] ) ? translate_user_role( $names[ $slug ] ) : $slug, $slug );
	}

	/**
	 * @return string The site's roles without administrator-level access, for a refusal.
	 */
	private static function ordinary_roles(): string {
		$roles = Account_Access_Policy::roles_without_admin_access();

		return array() === $roles ? 'none on this site' : implode( ', ', $roles );
	}
}
