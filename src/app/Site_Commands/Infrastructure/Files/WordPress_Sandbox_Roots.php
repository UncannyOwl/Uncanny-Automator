<?php
/**
 * The directories file commands may read: the ones WordPress resolves.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged -- realpath() warns under open_basedir; a missing root is skipped.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Files;

use Uncanny_Automator\App\Site_Commands\Ports\Files\Sandbox_Roots;

/**
 * The directories WordPress itself resolves. A set, not a tree: on Bedrock
 * and Composer layouts the content directory is a sibling of ABSPATH.
 */
final class WordPress_Sandbox_Roots implements Sandbox_Roots {

	/**
	 * Folders of the operating system, never part of a WordPress site.
	 */
	private const SYSTEM_FOLDERS = array( '/bin', '/boot', '/dev', '/etc', '/lib', '/lib32', '/lib64', '/proc', '/root', '/run', '/sbin', '/sys', '/usr' );

	/**
	 * Absolute, canonical directories; ABSPATH first, as the base for relative paths.
	 *
	 * @return string[]
	 */
	public function roots(): array {
		return array_keys( $this->folders() );
	}

	/**
	 * WordPress's own folder by every name it has, canonical first; empty when unknown.
	 *
	 * @return string[]
	 */
	public function site_root(): array {
		if ( ! defined( 'ABSPATH' ) ) {
			return array();
		}
		$named = rtrim( (string) ABSPATH, '/\\' );
		$real  = '' === $named ? false : @realpath( $named );
		$names = false === $real ? null : ( $this->folders()[ $real ] ?? null );

		return null === $names ? array() : array_values( array_unique( array_merge( array( $real ), $names ) ) );
	}

	/**
	 * Each root that WordPress names by other paths, such as an uploads
	 * folder that is a link to a shared folder: canonical => every name.
	 *
	 * @return array<string, string[]>
	 */
	public function aliases(): array {
		$aliases = array();
		foreach ( $this->folders() as $real => $names ) {
			$others = array_values( array_diff( $names, array( $real ) ) );
			if ( array() !== $others ) {
				$aliases[ $real ] = $others;
			}
		}

		return $aliases;
	}

	/**
	 * Where a path relative to a readable folder really is, for each folder
	 * that holds it, after links.
	 *
	 * @param string $relative A path such as "wp-content/uploads/invoices".
	 * @return string[]
	 */
	public function canonical( string $relative ): array {
		$found = array();
		foreach ( $this->roots() as $root ) {
			$real = @realpath( $root . '/' . ltrim( $relative, '/' ) );
			if ( false !== $real ) {
				$found[] = $real;
			}
		}

		return $found;
	}

	/**
	 * The readable folders: canonical path => the paths WordPress names it by.
	 *
	 * @return array<string, string[]>
	 */
	private function folders(): array {
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			// Windows paths are not supported yet; the sandbox reasons in
			// POSIX paths. Empty roots fail closed with a clear message.
			return array();
		}
		$candidates = array();
		if ( defined( 'ABSPATH' ) ) {
			$candidates[] = (string) ABSPATH;
		}
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$candidates[] = (string) WP_CONTENT_DIR;
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$candidates[] = (string) WP_PLUGIN_DIR;
		}
		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$candidates[] = (string) WPMU_PLUGIN_DIR;
		}
		global $wp_theme_directories;
		if ( is_array( $wp_theme_directories ) ) {
			foreach ( $wp_theme_directories as $directory ) {
				$candidates[] = (string) $directory;
			}
		}
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir( null, false );
			if ( is_array( $uploads ) && ! empty( $uploads['basedir'] ) ) {
				$candidates[] = (string) $uploads['basedir'];
			}
		}
		$folders = array();
		foreach ( $candidates as $candidate ) {
			$named = rtrim( $candidate, '/\\' );
			// realpath( '' ) is the working directory, never a root.
			$real = '' === $named ? false : @realpath( $named );
			if ( false === $real || ! is_dir( $real ) ) {
				continue;
			}
			// One root per folder, keeping every name WordPress gives it.
			$folders[ $real ][] = $named;
		}
		$roots = array_keys( $folders );
		$sites = array_slice( $roots, 0, 1 );
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$sites[] = (string) @realpath( rtrim( (string) WP_CONTENT_DIR, '/\\' ) );
		}

		return array_filter(
			$folders,
			static function ( string $root ) use ( $roots, $sites ): bool {
				return $root === $roots[0] || self::may_be_root( $root, $sites );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Whether a folder WordPress names may be read. The upload folder follows
	 * an option and every folder follows filters, so none may hold the
	 * WordPress or content folder, such as "/" or a parent, or be a system
	 * folder.
	 *
	 * @param string   $root  Canonical folder.
	 * @param string[] $sites The canonical WordPress and content folders.
	 * @return bool
	 */
	private static function may_be_root( string $root, array $sites ): bool {
		if ( '/' === $root ) {
			return false;
		}
		foreach ( $sites as $site ) {
			if ( '' !== $site && $root !== $site && 0 === strpos( $site . '/', $root . '/' ) ) {
				return false;
			}
		}
		foreach ( self::SYSTEM_FOLDERS as $system ) {
			if ( $root === $system || 0 === strpos( $root, $system . '/' ) ) {
				return false;
			}
		}

		return true;
	}
}
