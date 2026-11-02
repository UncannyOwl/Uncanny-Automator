<?php
/**
 * Site configuration commands: options, rewrites, roles, languages, cache
 * and maintenance mode.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Private_Object_Cache;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * How the site is configured. Only core site settings are readable with
 * option get: plugin options often hold API keys and tokens.
 */
final class Settings_Commands implements Site_Command_Group {

	/**
	 * Core settings that hold no secrets or personal data.
	 */
	private const CORE_SETTINGS = array(
		'blogname',
		'blogdescription',
		'siteurl',
		'home',
		'users_can_register',
		'default_role',
		'timezone_string',
		'gmt_offset',
		'date_format',
		'time_format',
		'start_of_week',
		'WPLANG',
		'permalink_structure',
		'category_base',
		'tag_base',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'posts_per_page',
		'posts_per_rss',
		'rss_use_excerpt',
		'blog_public',
		'default_comment_status',
		'default_ping_status',
		'comment_registration',
		'require_name_email',
		'comment_moderation',
		'close_comments_for_old_posts',
		'close_comments_days_old',
		'thread_comments',
		'thread_comments_depth',
		'page_comments',
		'comments_per_page',
		'default_category',
		'default_post_format',
		'template',
		'stylesheet',
		'uploads_use_yearmonth_folders',
		'thumbnail_size_w',
		'thumbnail_size_h',
		'medium_size_w',
		'medium_size_h',
		'large_size_w',
		'large_size_h',
		'db_version',
		'wp_page_for_privacy_policy',
		'site_icon',
	);

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		return array(
			Command_Definition::reads( 'option get', 'Show the value of one core site setting, such as blogname or permalink_structure. Plugin options are not readable.' )
				->with_argument( Command_Argument::required( 'key', 'Core setting name. One of: ' . implode( ', ', self::CORE_SETTINGS ) . '.' ) )
				->handled_by( array( $this, 'get_option' ) ),
			Command_Definition::reads( 'rewrite list', 'List the permalink rewrite rules. Empty when the site uses plain permalinks.' )
				->with_fields( array( 'match', 'query' ), array( 'match', 'query' ), 'Rewrite rules' )
				->handled_by( array( $this, 'list_rewrites' ) ),
			Command_Definition::reads( 'role list', 'List user roles.' )
				->with_fields( array( 'name', 'role' ), array( 'name', 'role' ), 'Roles' )
				->handled_by( array( $this, 'list_roles' ) ),
			Command_Definition::reads( 'cap list', 'List the capabilities a role has, one per line.' )
				->with_argument( Command_Argument::required( 'role', 'Role, such as editor.' ) )
				->handled_by( array( $this, 'list_caps' ) ),
			Command_Definition::reads( 'language core list', 'List the installed WordPress languages. Available languages are not looked up online.' )
				->with_fields( array( 'language', 'status' ), array( 'language', 'status' ), 'Languages' )
				->handled_by( array( $this, 'list_core_languages' ) ),
			Command_Definition::reads( 'language plugin list', 'List installed plugin translations.' )
				->with_fields( array( 'plugin', 'language', 'status' ), array( 'plugin', 'language', 'status' ), 'Translations' )
				->handled_by( array( $this, 'list_plugin_languages' ) ),
			Command_Definition::reads( 'cache type', 'Show which object cache the site uses.' )
				->handled_by( array( $this, 'cache_type' ) ),
			Command_Definition::reads( 'maintenance-mode status', 'Show whether maintenance mode is active.' )
				->handled_by( array( $this, 'maintenance_status' ) ),
		);
	}

	/**
	 * The "option get" command, for core settings only.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the setting is not readable here.
	 */
	public function get_option( array $arguments, array $options ): Command_Output {
		$key = $arguments['key'];
		if ( ! in_array( $key, self::CORE_SETTINGS, true ) ) {
			throw new Command_Error( sprintf( "'%s' cannot be read here. Only core site settings without secrets or personal data are readable, such as blogname, home or permalink_structure. Run \"help option get\" for the full list.", $key ) );
		}

		$value = get_option( $key );
		if ( false === $value ) {
			throw new Command_Error( sprintf( "Could not get '%s' option. Does it exist?", $key ) );
		}
		if ( ! is_scalar( $value ) ) {
			throw new Command_Error( sprintf( "The '%s' option does not hold a single value; a plugin may have changed it.", $key ) );
		}

		return Command_Output::text( (string) $value );
	}

	/**
	 * The "rewrite list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the stored rules are unreadable.
	 */
	public function list_rewrites( array $arguments, array $options ): Command_Output {
		// Read the stored rules; generating them would write to the database.
		// Plain permalinks store none, as an empty value.
		$rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $rules ) && ! in_array( $rules, array( '', false ), true ) ) {
			throw new Command_Error( 'WordPress\'s stored rewrite rules are unreadable, so they cannot be listed.' );
		}
		$rows    = array();
		$skipped = 0;
		foreach ( is_array( $rules ) ? $rules : array() as $match => $query ) {
			if ( ! is_string( $query ) ) {
				++$skipped;
				continue;
			}
			$rows[] = array(
				'match' => (string) $match,
				'query' => $query,
			);
		}

		return Values::with_skipped( Command_Output::rows( $rows ), $skipped );
	}

	/**
	 * The "role list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the roles are unreadable.
	 */
	public function list_roles( array $arguments, array $options ): Command_Output {
		$roles = wp_roles()->roles;
		if ( ! is_array( $roles ) ) {
			throw new Command_Error( 'WordPress\'s user roles are unreadable, so they cannot be listed.' );
		}
		$rows    = array();
		$skipped = 0;
		foreach ( $roles as $role => $details ) {
			if ( ! is_array( $details ) || ! is_string( $details['name'] ?? null ) || '' === $details['name'] ) {
				++$skipped;
				continue;
			}
			$rows[] = array(
				'name' => $details['name'],
				'role' => (string) $role,
			);
		}

		return Values::with_skipped( Command_Output::rows( $rows ), $skipped );
	}

	/**
	 * The "cap list" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the role does not exist or its capabilities are unreadable.
	 */
	public function list_caps( array $arguments, array $options ): Command_Output {
		$role = get_role( $arguments['role'] );
		if ( ! $role instanceof \WP_Role ) {
			throw new Command_Error( sprintf( "'%s' role not found.", $arguments['role'] ) );
		}

		if ( ! is_array( $role->capabilities ) ) {
			throw new Command_Error( sprintf( "The '%s' role's capabilities are unreadable.", $arguments['role'] ) );
		}
		$caps = array_keys( array_filter( $role->capabilities ) );

		return Command_Output::text( implode( "\n", $caps ) )->with_stats( sprintf( 'Capabilities: %d listed.', count( $caps ) ) );
	}

	/**
	 * The "language core list" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the installed languages are unreadable.
	 */
	public function list_core_languages( array $arguments, array $options ): Command_Output {
		$active    = get_locale();
		$installed = get_available_languages();
		if ( ! is_array( $installed ) ) {
			throw new Command_Error( 'WordPress\'s list of installed languages is unreadable.' );
		}
		$readable  = array_filter( $installed, 'is_string' );
		$languages = array_unique( array_merge( array( 'en_US' ), $readable ) );
		sort( $languages );

		$rows = array();
		foreach ( $languages as $language ) {
			$rows[] = array(
				'language' => (string) $language,
				'status'   => $language === $active ? 'active' : 'installed',
			);
		}

		return Values::with_skipped( Command_Output::rows( $rows ), count( $installed ) - count( $readable ) );
	}

	/**
	 * The "language plugin list" command. Translations are found by their
	 * file names, "<text domain>-<locale>.mo" or ".l10n.php", in the plugin
	 * languages folder. WordPress's own reader would run the .l10n.php files
	 * to read their headers, and nothing here runs code from disk.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function list_plugin_languages( array $arguments, array $options ): Command_Output {
		$active = get_locale();
		$found  = array();
		$files  = array();
		// GLOB_BRACE is missing on some systems, so each kind is listed on its own.
		foreach ( array( '*.mo', '*.l10n.php' ) as $pattern ) {
			$matches = is_dir( WP_LANG_DIR . '/plugins' ) ? glob( WP_LANG_DIR . '/plugins/' . $pattern ) : array();
			$files   = array_merge( $files, is_array( $matches ) ? $matches : array() );
		}
		foreach ( $files as $file ) {
			if ( 1 === preg_match( '/^(.+)-([a-z]{2,3}(?:_[A-Z]{2})?(?:_[0-9a-z]+)?)\.(?:mo|l10n\.php)$/', basename( $file ), $match ) ) {
				$found[ $match[1] . "\n" . $match[2] ] = array(
					'plugin'   => $match[1],
					'language' => $match[2],
					'status'   => $match[2] === $active ? 'active' : 'installed',
				);
			}
		}
		ksort( $found );

		return Command_Output::rows( array_values( $found ) );
	}

	/**
	 * The "cache type" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function cache_type( array $arguments, array $options ): Command_Output {
		global $_wp_using_ext_object_cache, $wp_object_cache;

		// The same detection, in the same order, as WP-CLI's wp_get_cache_type().
		if ( empty( $_wp_using_ext_object_cache ) ) {
			return Command_Output::text( 'Default' );
		}
		// The site's own cache, not the private one a command runs with.
		$cache = Private_Object_Cache::site_cache( $wp_object_cache );
		$cache = is_object( $cache ) ? $cache : null;
		if ( null === $cache ) {
			return Command_Output::text( 'Unknown' );
		}
		if ( isset( $cache->m ) && class_exists( 'Memcached' ) && $cache->m instanceof \Memcached ) {
			return Command_Output::text( 'Memcached' );
		}
		if ( isset( $cache->mc ) && is_iterable( $cache->mc ) ) {
			foreach ( $cache->mc as $bucket ) {
				if ( ! ( class_exists( 'Memcache' ) && $bucket instanceof \Memcache ) && ! ( class_exists( 'Memcached' ) && $bucket instanceof \Memcached ) ) {
					return Command_Output::text( 'Unknown' );
				}
			}
			return Command_Output::text( 'Memcache' );
		}
		if ( class_exists( 'XCache_Object_Cache' ) && $cache instanceof \XCache_Object_Cache ) {
			return Command_Output::text( 'Xcache' );
		}
		if ( class_exists( 'WinCache_Object_Cache' ) ) {
			return Command_Output::text( 'WinCache' );
		}
		if ( class_exists( 'APC_Object_Cache' ) ) {
			return Command_Output::text( 'APC' );
		}
		if ( ( isset( $cache->redis ) && class_exists( 'Redis' ) && $cache->redis instanceof \Redis )
			|| ( method_exists( $cache, 'redis_instance' ) && method_exists( $cache, 'redis_status' ) )
			|| ( method_exists( $cache, 'config' ) && method_exists( $cache, 'connection' ) ) ) {
			return Command_Output::text( 'Redis' );
		}
		if ( isset( $cache->lcache ) && class_exists( 'LCache\Integrated' ) && $cache->lcache instanceof \LCache\Integrated ) {
			return Command_Output::text( 'WP LCache' );
		}
		if ( function_exists( 'w3_instance' ) ) {
			$config = w3_instance( 'W3_Config' );
			if ( is_object( $config ) && method_exists( $config, 'get_boolean' ) && method_exists( $config, 'get_string' ) && $config->get_boolean( 'objectcache.enabled' ) ) {
				return Command_Output::text( 'W3TC ' . Values::text( $config->get_string( 'objectcache.engine' ) ) );
			}
		}

		return Command_Output::text( 'Unknown' );
	}

	/**
	 * The "maintenance-mode status" command. As in WP-CLI, the .maintenance
	 * file is read as text; wp_is_maintenance_mode() would run it as PHP.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the file exists but its timestamp cannot be read.
	 */
	public function maintenance_status( array $arguments, array $options ): Command_Output {
		$file = ABSPATH . '.maintenance';
		if ( ! file_exists( $file ) || wp_installing() ) {
			return Command_Output::text( 'Maintenance mode is not active.' );
		}
		$contents = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read as text instead of run.
		$active   = self::maintenance_active( is_string( $contents ) ? $contents : '', time() );
		if ( null === $active ) {
			throw new Command_Error( 'The .maintenance file exists, but it is not in the form WordPress writes, so its timestamp cannot be read without running it. The maintenance status is unknown.' );
		}

		return Command_Output::text( $active ? 'Maintenance mode is active.' : 'Maintenance mode is not active.' );
	}

	/**
	 * Whether a .maintenance file keeps the site in maintenance mode: as in
	 * WordPress, for ten minutes after the $upgrading timestamp it sets. Only
	 * the file WordPress itself writes is understood, "<?php $upgrading = <time>; ?>";
	 * any other contents, such as comments or more statements, would need PHP
	 * to be run to know the timestamp, so the answer is unknown.
	 *
	 * @param string $contents The file's text.
	 * @param int    $now      The current Unix time.
	 * @return bool|null Null when the file is not in the form WordPress writes.
	 */
	public static function maintenance_active( string $contents, int $now ): ?bool {
		$started = self::maintenance_started( $contents );

		return null === $started ? null : $now - $started < 10 * MINUTE_IN_SECONDS;
	}

	/**
	 * The $upgrading timestamp of a .maintenance file in the form WordPress
	 * writes, or null for any other contents.
	 *
	 * @param string $contents The file's text.
	 * @return int|null
	 */
	public static function maintenance_started( string $contents ): ?int {
		if ( 1 !== preg_match( '/\A\s*<\?php\s+\$upgrading\s*=\s*(\d{1,18})\s*;\s*(?:\?>\s*)?\z/', $contents, $match ) ) {
			return null;
		}

		return (int) $match[1];
	}
}
