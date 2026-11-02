<?php
/**
 * Update facts from WordPress's update transients, read defensively.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

/**
 * Other plugins can write anything into the update transients. Entries are
 * read as arrays or objects of scalars; anything else counts as no update,
 * so malformed data never fails a listing or invents an update.
 */
final class Update_Status {

	/** Why update fields are unknown when the site's object cache holds no update check. */
	public const NOT_CHECKED = 'Update status unknown: this site\'s object cache holds no update check from WordPress. It returns after WordPress checks again.';

	/** The stem of core's auto-update names, as WP_Automatic_Updater builds them per type. */
	private const AUTO_UPDATE_STEM = 'auto_update_';

	/**
	 * Core's site option that lists the plugins or themes with auto-updates
	 * on, named as WP_Automatic_Updater names it: "auto_update_{$type}s".
	 *
	 * @param string $type "plugin" or "theme".
	 * @return string
	 */
	public static function auto_update_option( string $type ): string {
		return self::AUTO_UPDATE_STEM . $type . 's';
	}

	/**
	 * Core's filter that can override one item's auto-update decision:
	 * "auto_update_{$type}".
	 *
	 * @param string $type "plugin" or "theme".
	 * @return string
	 */
	public static function auto_update_filter( string $type ): string {
		return self::AUTO_UPDATE_STEM . $type;
	}

	/**
	 * Whether the update check answers the question. A persistent object
	 * cache can drop the check at any time, and an absent check there is not
	 * "no updates". On other sites an absent check reads as none, as WP-CLI
	 * reports it.
	 *
	 * @param mixed $transient The update_plugins or update_themes transient.
	 * @return bool
	 */
	public static function known( $transient ): bool {
		return ! wp_using_ext_object_cache() || is_object( $transient );
	}

	/**
	 * Valid entries from one transient list, keyed by plugin file or stylesheet.
	 *
	 * @param mixed  $transient The update_plugins or update_themes transient.
	 * @param string $list      "response" (offered updates) or "no_update".
	 * @return array<string, array{new_version: string|null, requires: string, requires_php: string}>
	 *         A new_version of null means the entry exists but its version is not one.
	 */
	public static function entries( $transient, string $list ): array {
		if ( ! is_object( $transient ) || ! isset( $transient->$list ) || ! is_array( $transient->$list ) ) {
			return array();
		}

		$entries = array();
		foreach ( $transient->$list as $key => $raw ) {
			$raw = is_object( $raw ) ? get_object_vars( $raw ) : $raw;
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$version = $raw['new_version'] ?? '';
			if ( '' === $version ) {
				continue;
			}
			// A version is text such as "3.8.0" or a whole number; true or an
			// array is not one, and is never printed as if it were.
			$version                  = ( is_string( $version ) || is_int( $version ) ) && 1 === preg_match( '/^[0-9A-Za-z][0-9A-Za-z.+_-]{0,63}$/', (string) $version ) ? (string) $version : null;
			$entry                    = array(
				'new_version'  => $version,
				'requires'     => Values::text( $raw['requires'] ?? '' ),
				'requires_php' => Values::text( $raw['requires_php'] ?? '' ),
			);
			$entries[ (string) $key ] = $entry;
		}

		return $entries;
	}

	/**
	 * WP-CLI's update fields: "available", "unavailable" when this site's PHP
	 * or WordPress is too old for the new version, or "none"; "unknown" when
	 * the update check holds a version that is not one.
	 *
	 * @param array|null $offered   Entry from the "response" list.
	 * @param array|null $withheld  Entry from the "no_update" list.
	 * @param string     $installed Installed version.
	 * @return array{0: string, 1: string} The update status and the new version.
	 */
	public static function status( ?array $offered, ?array $withheld, string $installed ): array {
		if ( ( null !== $offered && null === $offered['new_version'] ) || ( null === $offered && null !== $withheld && null === $withheld['new_version'] ) ) {
			return array( 'unknown', '' );
		}
		if ( null !== $offered ) {
			$too_old = ( '' !== $offered['requires_php'] && ! is_php_version_compatible( $offered['requires_php'] ) )
				|| ( '' !== $offered['requires'] && ! is_wp_version_compatible( $offered['requires'] ) );

			return array( $too_old ? 'unavailable' : 'available', $offered['new_version'] );
		}

		// WordPress.org lists an update this site cannot install under no_update.
		if ( null !== $withheld && '' !== $installed && version_compare( $withheld['new_version'], $installed, '>' ) ) {
			return array( 'unavailable', $withheld['new_version'] );
		}

		return array( 'none', '' );
	}
}
