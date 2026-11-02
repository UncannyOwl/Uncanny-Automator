<?php
/**
 * Checks and messages shared by the commands that change the site.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Time_Budget;

/**
 * Before anything changes, a command is refused when the connected user
 * lacks the capability, the site does not allow file changes, or WordPress
 * cannot write files without FTP or SSH credentials, which nobody can enter
 * here. Afterwards, a failure is described with what it left changed, which
 * depends on the kind of work: WordPress keeps a backup only while it updates
 * a plugin or theme, and removes a translation's old files first.
 */
final class Site_Changes {

	/** An update of an installed plugin or theme, which WordPress backs up first. */
	public const UPDATE = 'update';

	/** A new plugin or theme, which has no previous version to keep. */
	public const INSTALL = 'install';

	/** A translation, which replaces its files without a backup. */
	public const TRANSLATION = 'translation';

	/** Capabilities WordPress denies to everyone when file changes are off. */
	private const FILE_CAPABILITIES = array( 'install_plugins', 'update_plugins', 'install_themes', 'update_themes', 'update_core', 'update_languages', 'install_languages' );

	private const FILE_MODS_OFF = 'This site does not allow changes to plugin, theme or WordPress files: DISALLOW_FILE_MODS is set in wp-config.php, or a plugin turned file changes off. Nothing was changed.';

	/**
	 * Error codes WordPress returns before it changes any file.
	 *
	 * @var string[]
	 */
	private const BEFORE_ANY_CHANGE = array(
		Change_Session::OUT_OF_TIME,
		Change_Session::NOT_WORDPRESS_ORG,
		'download_failed',
		'no_package',
		'bad_request',
		'up_to_date',
		'locked',
		'source_read_failed',
		'incompatible_archive',
		'incompatible_archive_empty',
		'incompatible_archive_no_plugins',
		'incompatible_archive_theme_no_style',
		'incompatible_archive_theme_no_index',
		'incompatible_archive_theme_no_name',
		'incompatible_archive_pomo',
		'incompatible_wp_required_version',
		'incompatible_php_required_version',
		'fs_unavailable',
		'fs_error',
		'fs_no_folder',
		'fs_no_root_dir',
		'fs_no_content_dir',
		'fs_no_plugins_dir',
		'fs_no_themes_dir',
		'fs_temp_backup_mkdir',
		'fs_temp_backup_move',
		'mkdir_failed_ziparchive',
		'mkdir_failed_pclzip',
		'copy_failed_ziparchive',
		'copy_failed_pclzip',
		'stat_failed_ziparchive',
		'extract_failed_ziparchive',
		'empty_archive_pclzip',
		'disk_full_unzip_file',
	);

	/**
	 * Error codes WordPress returns after an update moved the old version
	 * aside, but before a new install writes anything.
	 *
	 * @var string[]
	 */
	private const BEFORE_INSTALL_WRITES = array(
		'folder_exists',
		'new_source_read_failed',
		'mkdir_failed_destination',
	);

	/**
	 * Refuse unless the connected WordPress user has a capability. On a
	 * network, Automator lets only super administrators manage sites from AI
	 * apps, since plugins, themes and caches are shared by every site.
	 *
	 * @param string $capability WordPress capability, such as update_plugins.
	 * @param string $doing      What it allows, such as "update plugins".
	 * @param mixed  ...$object  The object a meta capability is about, such as a plugin file.
	 * @return void
	 *
	 * @throws Command_Error When the user may not.
	 */
	public static function require_capability( string $capability, string $doing, ...$object ): void {
		if ( is_multisite() && ! is_super_admin() ) {
			throw new Command_Error( sprintf( 'This site is part of a WordPress network, where Automator lets only super administrators %s from AI apps, and the WordPress user this connection uses is not one. Nothing was changed.', $doing ) );
		}
		if ( current_user_can( $capability, ...$object ) ) {
			return;
		}
		if ( in_array( $capability, self::FILE_CAPABILITIES, true ) && ! wp_is_file_mod_allowed( 'capability_update_core' ) ) {
			// WordPress denies these capabilities to everyone when file changes are off.
			throw new Command_Error( self::FILE_MODS_OFF );
		}

		throw new Command_Error( sprintf( 'The WordPress user this connection uses does not have the %1$s capability, so it cannot %2$s. Nothing was changed.', $capability, $doing ) );
	}

	/**
	 * Refuse unless WordPress may change files in a folder, and can do it
	 * directly, then connect WordPress's filesystem.
	 *
	 * @param string $folder Folder the change writes to, such as WP_PLUGIN_DIR.
	 * @param string $screen WordPress screen that makes the same change, where credentials can be entered.
	 * @return void
	 *
	 * @throws Command_Error When files cannot be changed.
	 */
	public static function require_file_changes( string $folder, string $screen = Time_Budget::UPDATES_SCREEN ): void {
		if ( ! wp_is_file_mod_allowed( 'automator_site_manage' ) ) {
			throw new Command_Error( self::FILE_MODS_OFF );
		}

		self::load_admin_files();
		$method = get_filesystem_method( array(), $folder );
		if ( 'direct' !== $method ) {
			throw new Command_Error( sprintf( 'WordPress cannot write files directly on this host: it would need %s credentials, which cannot be entered here. Nothing was changed. Use %s in WordPress, or ask the host to let WordPress write its own files.', self::method_name( $method ), $screen ) );
		}
		if ( ! WP_Filesystem() ) {
			throw new Command_Error( 'WordPress could not open its filesystem for writing. Nothing was changed.' );
		}
	}

	/**
	 * Why an upgrader run failed, or null when it succeeded. Checked in the
	 * order WordPress's own update screens check it.
	 *
	 * @param mixed                  $result The upgrader's return value.
	 * @param \WP_Ajax_Upgrader_Skin $skin   The skin it ran with.
	 * @param string                 $kind   UPDATE, INSTALL or TRANSLATION.
	 * @param string|null            $key    For a bulk run, the item's key in the result.
	 * @return string|null
	 */
	public static function outcome( $result, \WP_Ajax_Upgrader_Skin $skin, string $kind, ?string $key = null ): ?string {
		$item = null !== $key && is_array( $result ) ? ( $result[ $key ] ?? null ) : $result;
		foreach ( array( $result, $item, $skin->result ) as $candidate ) {
			if ( is_wp_error( $candidate ) ) {
				return self::failure( $candidate, $skin->get_upgrade_messages(), $kind );
			}
		}
		if ( $skin->get_errors()->has_errors() ) {
			return self::failure( $skin->get_errors(), $skin->get_upgrade_messages(), $kind );
		}
		if ( false === $result ) {
			return 'WordPress could not connect to its filesystem to replace the files. Nothing was changed.';
		}
		if ( empty( $item ) ) {
			return self::failure( null, $skin->get_upgrade_messages(), $kind );
		}

		return null;
	}

	/**
	 * Whether a failure happened before any file changed.
	 *
	 * @param mixed  $error The failure.
	 * @param string $kind  UPDATE, INSTALL or TRANSLATION: an update has moved the old version aside earlier.
	 * @return bool
	 */
	public static function changed_nothing( $error, string $kind = self::UPDATE ): bool {
		if ( ! $error instanceof \WP_Error ) {
			return false;
		}
		$code = (string) $error->get_error_code();

		return in_array( $code, self::BEFORE_ANY_CHANGE, true ) || ( self::UPDATE !== $kind && in_array( $code, self::BEFORE_INSTALL_WRITES, true ) );
	}

	/**
	 * An upgrader's failure as one line, with what it left changed.
	 *
	 * @param mixed    $result   The failure: WP_Error, or anything else when unknown.
	 * @param string[] $messages Messages the upgrader's skin collected.
	 * @param string   $kind     UPDATE, INSTALL or TRANSLATION.
	 * @return string
	 */
	public static function failure( $result, array $messages, string $kind ): string {
		if ( ! $result instanceof \WP_Error ) {
			$messages = array_values( array_filter( array_map( array( self::class, 'text' ), $messages ) ) );
			$reason   = array() === $messages ? 'WordPress\'s upgrader reported a failure without saying why.' : implode( ' ', array_slice( $messages, -3 ) );
			return rtrim( $reason, '. ' ) . '. ' . self::consequence( $kind );
		}

		$message = self::text( $result->get_error_message() );
		$data    = self::text( $result->get_error_data() );
		if ( '' !== $data && false === strpos( $message, $data ) ) {
			$message = rtrim( $message, '. ' ) . ': ' . $data;
		}
		$message = rtrim( $message, '. ' ) . '.';

		if ( Change_Session::OUT_OF_TIME === $result->get_error_code() ) {
			return $message . ' Nothing was changed. Run the command again; a new request has the full time.';
		}

		return $message . ' ' . ( self::changed_nothing( $result, $kind ) ? 'Nothing was changed.' : self::consequence( $kind ) );
	}

	/**
	 * A package download failed before the updater started replacing files.
	 * Providers can return their own error codes, not just download_failed.
	 *
	 * @param \WP_Error $error Download or provider error.
	 * @return string
	 */
	public static function download_failure( \WP_Error $error ): string {
		if ( Change_Session::OUT_OF_TIME === $error->get_error_code() ) {
			return self::failure( $error, array(), self::UPDATE );
		}
		$message = self::text( $error->get_error_message() );
		$data    = self::text( $error->get_error_data() );
		if ( '' !== $data && false === strpos( $message, $data ) ) {
			$message = rtrim( $message, '. ' ) . ': ' . $data;
		}

		return sprintf(
			'Download failed (%s): %s. Nothing was changed. Check Dashboard › Updates and the plugin\'s provider or license settings before trying again.',
			self::text( $error->get_error_code() ),
			'' === $message ? 'the provider gave no reason' : rtrim( $message, '. ' )
		);
	}

	/**
	 * An update's failure with what actually happened to the previous
	 * version, when it was put back or could not be, in place of what
	 * WordPress usually does.
	 *
	 * @param string $failure From failure() for an UPDATE.
	 * @param string $outcome What happened to the previous version.
	 * @return string
	 */
	public static function with_outcome( string $failure, string $outcome ): string {
		$usual = self::consequence( self::UPDATE );
		if ( false !== strpos( $failure, $usual ) ) {
			return str_replace( $usual, $outcome . ' Check it with site_command before trying again.', $failure );
		}

		if ( '' === $outcome || Change_Session::STILL_IN_PLACE === $outcome ) {
			return $failure;
		}
		// Files were moved after all, whatever the error said.
		return false !== strpos( $failure, 'Nothing was changed.' ) ? str_replace( 'Nothing was changed.', $outcome, $failure ) : rtrim( $failure ) . ' ' . $outcome;
	}

	/**
	 * Plain text from a WordPress string, which may hold HTML, entities and
	 * server paths. Paths are shown relative to the WordPress folder.
	 *
	 * @param mixed $value Text.
	 * @return string
	 */
	public static function text( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = (string) preg_replace( '#<(/?)(p|div|br|li|ul|ol|h[1-6])\b[^>]*>#i', ' ', (string) $value );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		// Provider errors may echo signed package URLs or credentials in paths.
		// Keep the error text, never the download URL, query or user information.
		$text  = (string) preg_replace( '~https?://[^\s<>"\']+~i', '[URL omitted]', $text );
		$text  = (string) preg_replace( '/\s*Learn more about updating PHP\.?/i', '', $text );
		$text  = (string) preg_replace( '/^\s*Error:\s*/i', '', $text );
		$names = array(
			array( WP_PLUGIN_DIR, 'wp-content/plugins' ),
			array( get_theme_root(), 'wp-content/themes' ),
			array( WP_CONTENT_DIR, 'wp-content' ),
			array( ABSPATH, 'the WordPress folder' ),
			array( get_temp_dir(), 'the temporary folder' ),
		);
		if ( defined( 'WP_TEMP_DIR' ) ) {
			$names[] = array( (string) WP_TEMP_DIR, 'the temporary folder' );
		}
		$paths = array();
		foreach ( $names as list( $path, $name ) ) {
			// Messages may use a folder's real path, behind symbolic links.
			foreach ( array( $path, realpath( $path ) ) as $form ) {
				$form = is_string( $form ) ? untrailingslashit( $form ) : '';
				if ( '' !== $form && '/' !== $form && ! isset( $paths[ $form ] ) ) {
					$paths[ $form ] = $name;
				}
			}
		}
		// strtr tries longer paths first, so a plugin's folder is not named by wp-content's.
		$text = strtr( $text, $paths );

		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * What a failure that may have touched files left behind.
	 *
	 * @param string $kind UPDATE, INSTALL or TRANSLATION.
	 * @return string
	 */
	private static function consequence( string $kind ): string {
		if ( self::UPDATE === $kind ) {
			return 'Check it with site_command before trying again.';
		}
		if ( self::INSTALL === $kind ) {
			return 'Part of the new files may have been written. Check with site_command; if a broken folder is left, it must be removed from the server before installing again.';
		}

		// WordPress removes a translation's old files before it writes the new ones.
		return 'The translation\'s old files may have been removed before the new ones were written, so it may be missing until it is installed again. Run the command again to install it.';
	}

	/**
	 * WordPress's admin functions for upgrades, which REST requests do not load.
	 *
	 * @return void
	 */
	public static function load_admin_files(): void {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
	}

	/**
	 * A filesystem method as people know it.
	 *
	 * @param mixed $method WordPress's method name.
	 * @return string
	 */
	private static function method_name( $method ): string {
		$names = array(
			'ftpext'     => 'FTP',
			'ftpsockets' => 'FTP',
			'ssh2'       => 'SSH',
		);

		return is_string( $method ) && isset( $names[ $method ] ) ? $names[ $method ] : 'FTP or SSH';
	}
}
