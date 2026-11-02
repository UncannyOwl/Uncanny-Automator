<?php
/**
 * The current theme folder list, as WordPress computes it.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure;

/**
 * WordPress keeps the list of installed theme folders in the theme_roots
 * site transient, and saves it again whenever it lists themes and finds the
 * stored copy out of date; an expired copy is deleted when read. This
 * computes the list exactly as search_theme_directories() does, so a command
 * can answer reads of the transient with it and nothing is saved or deleted.
 */
final class Theme_Roots {

	/**
	 * Theme folders mapped to their theme root, relative to wp-content where
	 * it lies inside it: search_theme_directories()'s $theme_roots.
	 *
	 * @return array<string, string> Empty when no theme root is registered, which
	 *                               WordPress reads as its default /themes.
	 */
	public static function current(): array {
		global $wp_theme_directories;

		if ( empty( $wp_theme_directories ) ) {
			return array();
		}

		$relative_theme_roots = array();
		foreach ( (array) $wp_theme_directories as $theme_root ) {
			if ( 0 === strpos( $theme_root, WP_CONTENT_DIR ) ) {
				$relative_theme_roots[ str_replace( WP_CONTENT_DIR, '', $theme_root ) ] = $theme_root;
			} else {
				$relative_theme_roots[ $theme_root ] = $theme_root;
			}
		}

		// The same walk, in the same order, as search_theme_directories().
		$found_themes = array();
		foreach ( (array) $wp_theme_directories as $theme_root ) {
			$dirs = @scandir( $theme_root ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- As WordPress does; an unreadable root is skipped.
			if ( ! $dirs ) {
				continue;
			}
			foreach ( $dirs as $dir ) {
				if ( ! is_dir( $theme_root . '/' . $dir ) || '.' === $dir[0] || 'CVS' === $dir ) {
					continue;
				}
				if ( file_exists( $theme_root . '/' . $dir . '/style.css' ) ) {
					$found_themes[ $dir ] = array(
						'theme_file' => $dir . '/style.css',
						'theme_root' => $theme_root,
					);
					continue;
				}
				$found_theme = false;
				$sub_dirs    = @scandir( $theme_root . '/' . $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- As WordPress does.
				if ( ! $sub_dirs ) {
					continue;
				}
				foreach ( $sub_dirs as $sub_dir ) {
					if ( ! is_dir( $theme_root . '/' . $dir . '/' . $sub_dir ) || '.' === $dir[0] || 'CVS' === $dir ) {
						continue;
					}
					if ( ! file_exists( $theme_root . '/' . $dir . '/' . $sub_dir . '/style.css' ) ) {
						continue;
					}
					$found_themes[ $dir . '/' . $sub_dir ] = array(
						'theme_file' => $dir . '/' . $sub_dir . '/style.css',
						'theme_root' => $theme_root,
					);
					$found_theme                           = true;
				}
				if ( ! $found_theme ) {
					$found_themes[ $dir ] = array(
						'theme_file' => $dir . '/style.css',
						'theme_root' => $theme_root,
					);
				}
			}
		}

		asort( $found_themes );

		$theme_roots          = array();
		$relative_theme_roots = array_flip( $relative_theme_roots );
		foreach ( $found_themes as $theme_dir => $theme_data ) {
			$theme_roots[ $theme_dir ] = $relative_theme_roots[ $theme_data['theme_root'] ];
		}

		return $theme_roots;
	}
}
