<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Integration_Catalog\Dependency\Enums;

use Uncanny_Automator\Services\Plugin\Info;

/**
 * Plugin State Enum.
 *
 * The state of the plugin an item (trigger, action, loop filter or condition) needs beyond its integration,
 * worked out once per request by work_out(), the one rule, and kept as `requirements.plugin_state`. The item
 * rows read it to pick the install, activate or update row.
 *
 * @since 8.1.0
 */
class Plugin_State {

	/**
	 * The item names no plugin beyond its integration.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const NONE = 'none';

	/**
	 * The plugin the item needs is loaded and the item's code can run against it.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const MET = 'met';

	/**
	 * The plugin the item needs is not installed.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const MISSING = 'missing';

	/**
	 * The plugin the item needs is installed and not active.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const INACTIVE = 'inactive';

	/**
	 * The plugin the item needs is active below the item's minimum version.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const OUTDATED = 'outdated';

	/**
	 * The plugin the item needs is active and the item's code cannot run against it.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const UNSUPPORTED = 'unsupported';

	/**
	 * The state of a declared plugin: presence read from the site, the item's guard kept apart.
	 *
	 * With no guard, presence and version decide. A guard that passes reads the plugin as loaded even
	 * when its file is not active (a renamed folder). A guard that fails with the file active, and the
	 * version not below a declared minimum, means our code does not match the installed version.
	 *
	 * @since 8.1.0
	 *
	 * @param array     $plugin_details The plugin details (`plugin_file`, `min_version`; a key left out reads as '').
	 * @param bool|null $guard          The item's compatibility guard; null when it has none.
	 *
	 * @return string One of this class's values.
	 */
	public static function work_out( array $plugin_details, ?bool $guard ): string {
		$plugin_file = (string) ( $plugin_details['plugin_file'] ?? '' );

		if ( '' === $plugin_file ) {
			return self::NONE;
		}

		if ( ! Info::is_plugin_active( $plugin_file ) ) {
			// A guard that passes means the plugin is loaded, from another folder.
			if ( true === $guard ) {
				return self::MET;
			}

			return Info::is_plugin_installed( $plugin_file ) ? self::INACTIVE : self::MISSING;
		}

		if ( self::is_below_min_version( $plugin_file, (string) ( $plugin_details['min_version'] ?? '' ) ) ) {
			return self::OUTDATED;
		}

		return false === $guard ? self::UNSUPPORTED : self::MET;
	}

	/**
	 * Whether the installed plugin's header carries a version below the declared minimum.
	 *
	 * @since 8.1.0
	 *
	 * @param string $plugin_file The plugin file.
	 * @param string $min_version The declared minimum version; '' when none.
	 *
	 * @return bool False when no minimum is declared or the header carries no version.
	 */
	private static function is_below_min_version( string $plugin_file, string $min_version ): bool {
		if ( '' === $min_version ) {
			return false;
		}

		$plugin_data = Info::get_plugin_data( $plugin_file );
		$installed   = (string) ( $plugin_data['Version'] ?? '' );

		return '' !== $installed && version_compare( $installed, $min_version, '<' );
	}
}
