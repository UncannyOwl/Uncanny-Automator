<?php
/**
 * Keeps AI apps from giving out administrator-level access.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

/**
 * WordPress's user routes are closed to AI apps, so a connected or misled AI
 * cannot open a way back into the site. These rules close the same door in
 * Automator's actions and user selector, for every MCP caller: no role that
 * manages accounts or runs code, no new role with those capabilities, no
 * capability meta, no network super admin. An update is checked with the
 * fields it keeps. The recipe builder never consults them, so the site owner
 * can still do all of this. Account_Access_Guard refuses what fields cannot
 * show while an AI app runs the actions.
 */
final class Account_Access_Policy {

	/**
	 * Capabilities that manage accounts or run code on the site. A role with
	 * any of them is administrator-level.
	 *
	 * @var string[]
	 */
	public const ADMIN_CAPABILITIES = array(
		'manage_options',
		'promote_users',
		'edit_users',
		'create_users',
		'delete_users',
		'install_plugins',
		'activate_plugins',
		'edit_plugins',
		'edit_themes',
		'install_themes',
		'unfiltered_upload',
		'edit_files',
		'manage_network',
		'manage_network_users',
		'manage_network_plugins',
		'manage_network_themes',
		'manage_network_options',
		'manage_sites',
		'upgrade_network',
		'setup_network',
	);

	/**
	 * Action code => field that names the role to give.
	 *
	 * @var array<string, string>
	 */
	public const ROLE_FIELDS = array(
		'CREATEWPUSER'       => 'WPROLE',
		'USERROLE'           => 'WPROLE',
		'ADDROLE'            => 'WPROLE',
		'UPDATEUSER'         => 'WPROLE',
		'UMUSERROLECHANGE'   => 'WPROLE',
		'UMADDROLE'          => 'UMROLE',
		'WPMU_SET_USER_ROLE' => 'WPMU_ROLE',
	);

	/**
	 * Action code => field that lists a new role's capabilities.
	 *
	 * @var array<string, string>
	 */
	public const CAPABILITY_FIELDS = array(
		'WP_CREATE_ROLE' => 'CAPABILITIES',
	);

	/**
	 * Action code => repeater of user meta rows and the column holding the key.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	public const USER_META_FIELDS = array(
		'SETUSERMETA'  => array( 'META_PAIRS', 'KEY' ),
		'CREATEWPUSER' => array( 'USERMETA_PAIRS', 'meta_key' ),
		'UPDATEUSER'   => array( 'USERMETA_PAIRS', 'meta_key' ),
	);

	/**
	 * Actions that give administrator-level access whatever their fields say.
	 *
	 * @var array<string, string> Action code => why it is refused.
	 */
	public const REFUSED_ACTIONS = array(
		'WPMU_GRANT_SUPER_ADMIN' => 'a network super admin can manage every site and account on the network, so AI apps can\'t make one.',
	);

	/** Run Code actions cannot be constrained by WordPress storage filters. */
	public const RUN_CODE_ACTIONS = array(
		'UOA_CALL_FUNC_CODE',
		'UOA_CALL_FUNC_EVERYONE_CODE',
		'UOADOACTION',
		'DO_ACTION_CODE',
		'UOADOACTION_EVERYONE',
		'UOA_RUN_JS',
	);

	/** @return string|null Refusal shared by discovery, saving and execution. */
	public static function code_refusal( string $action_code ): ?string {
		return in_array( $action_code, self::RUN_CODE_ACTIONS, true )
			? sprintf( "Action '%s' runs arbitrary code and is not available to AI apps. The site owner can run it from the recipe builder.", $action_code )
			: null;
	}

	/** Where the owner does it instead. */
	private const BUILDER_HINT = 'The site owner can set this in the recipe builder.';

	/**
	 * Why an action's fields would give out administrator-level access, or null.
	 *
	 * An update is checked as it will be saved: a field it changes is read
	 * together with the saved fields it keeps, such as a custom role whose
	 * choice stays saved. Fields it leaves alone are the owner's.
	 *
	 * @param string $action_code Action code.
	 * @param array  $fields      Field values keyed by option code, tokens unresolved.
	 * @param array  $saved       The action's saved field values, for an update.
	 * @return string|null
	 */
	public function action_refusal( string $action_code, array $fields, array $saved = array() ): ?string {
		$code_refusal = self::code_refusal( $action_code );
		if ( null !== $code_refusal ) {
			return $code_refusal;
		}
		$refusal = self::REFUSED_ACTIONS[ $action_code ] ?? null;
		if ( null !== $refusal ) {
			return sprintf( "Action '%s' was refused: %s %s", $action_code, $refusal, self::BUILDER_HINT );
		}

		$kept    = array_merge( $saved, $fields );
		$refusal = $this->role_field_refusal( $action_code, $fields, $kept )
			?? $this->capability_field_refusal( $action_code, $fields, $kept )
			?? $this->user_meta_refusal( $action_code, $fields );

		return null === $refusal ? null : sprintf( "Action '%s' was refused: %s", $action_code, $refusal );
	}

	/**
	 * Check saved actions before a recipe can hand them to a later worker.
	 *
	 * A recipe's user can be selected or changed after dispatch. Credential
	 * changes need the synchronous write guard, not an earlier target check.
	 *
	 * @param string $action_code Action code.
	 * @param array $fields Saved fields, tokens unresolved.
	 * @return string|null
	 */
	public function recipe_action_refusal( string $action_code, array $fields ): ?string {
		$refusal = $this->action_refusal( $action_code, $fields );
		if ( null !== $refusal ) {
			return $refusal;
		}
		if ( 'UPDATEUSER' === $action_code && ( ! empty( $fields['EMAIL'] ) || ! empty( $fields['PASSWORD'] ) ) ) {
			return 'This recipe changes an existing user email or password. Use run_action for that update so Automator can check the account while it changes, or ask the site owner to run the recipe in the builder.';
		}
		return null;
	}

	/**
	 * Why a recipe's user selector would create administrator-level users, or null.
	 *
	 * @param array $user_selector User selector input.
	 * @return string|null
	 */
	public function user_selector_refusal( array $user_selector ): ?string {
		$user_data = $user_selector['user_data'] ?? null;
		if ( ! is_array( $user_data ) || ! array_key_exists( 'role', $user_data ) ) {
			return null;
		}

		$refusal = $this->role_refusal( $user_data['role'] );

		return null === $refusal ? null : 'The user selector was refused: ' . $refusal;
	}

	/**
	 * @param string $action_code Action code.
	 * @param array  $fields      Field values sent.
	 * @param array  $kept        Field values as they will be saved.
	 * @return string|null
	 */
	private function role_field_refusal( string $action_code, array $fields, array $kept ): ?string {
		$field = self::ROLE_FIELDS[ $action_code ] ?? null;
		if ( null === $field || ( ! self::changes( $fields, $field ) && ! ( 'WPMU_SET_USER_ROLE' === $action_code && self::changes( $fields, 'WPMU_SITE_ID' ) ) ) ) {
			return null;
		}

		$role = $kept[ $field ] ?? '';

		// A custom value keeps the real role in the companion field.
		if ( 'automator_custom_value' === $role ) {
			$role = $kept[ $field . '_custom' ] ?? '';
		}

		$site_id = get_current_blog_id();
		if ( 'WPMU_SET_USER_ROLE' === $action_code ) {
			$target = $kept['WPMU_SITE_ID'] ?? $site_id;
			if ( 'automator_custom_value' === $target ) {
				$target = $kept['WPMU_SITE_ID_custom'] ?? '';
			}
			if ( ! is_numeric( $target ) || (int) $target <= 0 ) {
				return 'Choose a fixed target site so its role permissions can be checked before saving.';
			}
			$site_id = (int) $target;
		}

		return $this->role_refusal( $role, $site_id );
	}

	/**
	 * @param string $action_code Action code.
	 * @param array  $fields      Field values sent.
	 * @param array  $kept        Field values as they will be saved.
	 * @return string|null
	 */
	private function capability_field_refusal( string $action_code, array $fields, array $kept ): ?string {
		$field = self::CAPABILITY_FIELDS[ $action_code ] ?? null;
		if ( null === $field || ! self::changes( $fields, $field ) ) {
			return null;
		}

		$capabilities = self::list_of( $kept[ $field ] ?? array() );

		// A custom value adds the capabilities in the companion field.
		if ( in_array( 'automator_custom_value', $capabilities, true ) ) {
			$capabilities = array_merge( array_diff( $capabilities, array( 'automator_custom_value' ) ), self::list_of( $kept[ $field . '_custom' ] ?? array() ) );
		}

		foreach ( $capabilities as $capability ) {
			if ( self::is_token( $capability ) ) {
				return 'a capability comes from a token, so it cannot be checked before it is used. List the capabilities by name. ' . self::BUILDER_HINT;
			}

			// The run unescapes the field and runs its shortcodes before the action reads it, so only a plain name reads the same then as now.
			if ( 1 !== preg_match( '/^[a-z0-9_]+$/i', $capability ) ) {
				return sprintf( 'the capability "%s" is not a plain name. Use letters, numbers and underscores, such as edit_posts. %s', $capability, self::BUILDER_HINT );
			}
		}

		$admin = array_values( array_intersect( self::ADMIN_CAPABILITIES, $capabilities ) );
		if ( array() === $admin ) {
			return null;
		}

		return sprintf( 'the new role could manage accounts or run code (%s). Leave those capabilities out. %s', implode( ', ', $admin ), self::BUILDER_HINT );
	}

	/**
	 * @param string $action_code Action code.
	 * @param array  $fields      Field values.
	 * @return string|null
	 */
	private function user_meta_refusal( string $action_code, array $fields ): ?string {
		$spec = self::USER_META_FIELDS[ $action_code ] ?? null;
		if ( null === $spec || ! array_key_exists( $spec[0], $fields ) ) {
			return null;
		}

		foreach ( self::rows_of( $fields[ $spec[0] ] ) as $row ) {
			$key = is_array( $row ) && is_scalar( $row[ $spec[1] ] ?? null ) ? trim( (string) $row[ $spec[1] ] ) : '';

			if ( self::is_token( $key ) ) {
				return 'a user meta key comes from a token, so it cannot be checked before it is written. Name the key. ' . self::BUILDER_HINT;
			}

			// Covers multisite prefixes such as wp_2_capabilities.
			if ( 1 === preg_match( '/(capabilities|user_level)$/i', $key ) ) {
				return sprintf( "the user meta key %s holds a user's roles and capabilities. Change roles with a role action instead. %s", $key, self::BUILDER_HINT );
			}
		}

		return null;
	}

	/**
	 * Why giving this role is refused, or null.
	 *
	 * @param mixed $role Role slug as submitted.
	 * @return string|null
	 */
	private function role_refusal( $role, ?int $site_id = null ): ?string {
		$role = is_scalar( $role ) ? trim( (string) $role ) : '';
		if ( '' === $role ) {
			return null;
		}

		if ( self::is_token( $role ) ) {
			return 'the role comes from a token, so it cannot be checked before it is given. Choose a fixed role without account or site management rights. ' . self::BUILDER_HINT;
		}

		$admin = self::admin_capabilities_of( $role, $site_id );
		if ( array() === $admin ) {
			return null;
		}

		$ordinary = self::roles_without_admin_access( $site_id );

		return sprintf(
			'the %s role can manage accounts or run code (%s). Choose a role without those rights%s. %s',
			$role,
			implode( ', ', $admin ),
			array() === $ordinary ? '' : ': ' . implode( ', ', $ordinary ),
			self::BUILDER_HINT
		);
	}

	/**
	 * The site's roles without administrator-level capabilities.
	 *
	 * @return string[] Role slugs.
	 */
	public static function roles_without_admin_access( ?int $site_id = null ): array {
		$roles = array();
		foreach ( ( null === $site_id ? wp_roles() : new \WP_Roles( $site_id ) )->roles as $slug => $role ) {
			if ( array() === array_intersect( self::ADMIN_CAPABILITIES, array_keys( array_filter( (array) ( $role['capabilities'] ?? array() ) ) ) ) ) {
				$roles[] = (string) $slug;
			}
		}

		return $roles;
	}

	/**
	 * Whether the fields sent change a field or its custom value.
	 *
	 * @param array  $fields Field values sent.
	 * @param string $field  Option code.
	 * @return bool
	 */
	private static function changes( array $fields, string $field ): bool {
		return array_key_exists( $field, $fields ) || array_key_exists( $field . '_custom', $fields );
	}

	/**
	 * The administrator-level capabilities a site role grants.
	 *
	 * @param string $role Role slug.
	 * @return string[]
	 */
	private static function admin_capabilities_of( string $role, ?int $site_id = null ): array {
		$definition = ( null === $site_id ? wp_roles() : new \WP_Roles( $site_id ) )->get_role( $role );
		if ( null === $definition ) {
			return array();
		}

		$granted = array_keys( array_filter( (array) $definition->capabilities ) );

		return array_values( array_intersect( self::ADMIN_CAPABILITIES, $granted ) );
	}

	/**
	 * @param string $value Field value.
	 * @return bool
	 */
	private static function is_token( string $value ): bool {
		return false !== strpos( $value, '{{' );
	}

	/**
	 * A multi-value field as a list of trimmed strings: an array, JSON text or
	 * comma-separated text.
	 *
	 * @param mixed $value Field value.
	 * @return string[]
	 */
	private static function list_of( $value ): array {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$items = array();
		foreach ( $value as $item ) {
			if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
				$items[] = trim( (string) $item );
			}
		}

		return $items;
	}

	/**
	 * Repeater rows from an array or JSON text.
	 *
	 * @param mixed $value Field value.
	 * @return array
	 */
	private static function rows_of( $value ): array {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		return is_array( $value ) ? $value : array();
	}
}
