<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Services;

use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon_Install_State;
use Uncanny_Automator\App\Plan\Services\Plugin\Plugin_Service;

/**
 * Resolves what this site currently has of an addon, per request.
 *
 * Nothing here is cached: install state is read from disk on every request,
 * so a freshly installed or activated addon renders correctly immediately.
 */
final class Addon_Status_Service {

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Plugin install lookups.
	 *
	 * @var Plugin_Service
	 */
	private Plugin_Service $plugins;

	/**
	 * Construct with a lazy-default plugin service.
	 *
	 * @param Plugin_Service|null $plugins Plugin lookups.
	 */
	private function __construct( ?Plugin_Service $plugins = null ) {
		$this->plugins = $plugins ?? new Plugin_Service();
	}

	/**
	 * Shared-instance accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Build with explicit dependencies.
	 *
	 * @param Plugin_Service|null $plugins Plugin lookups.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( ?Plugin_Service $plugins = null ): self {
		return new self( $plugins );
	}

	/**
	 * Resolve the current install state of an addon.
	 *
	 * @param Addon $addon The addon to inspect.
	 *
	 * @return Addon_Install_State
	 */
	public function get_state( Addon $addon ): Addon_Install_State {

		$plugin_file = $this->plugins->get_addon_plugin_file(
			array(
				'key'  => $addon->constant_key(),
				'name' => $addon->name(),
			)
		);

		// An empty plugin file resolves to the plugins directory itself, which
		// would answer "installed" for an addon that is not there.
		if ( '' === $plugin_file ) {
			return new Addon_Install_State( false, false, false );
		}

		$is_installed      = $this->plugins->is_plugin_installed( $plugin_file );
		$is_active         = $this->plugins->is_plugin_active( $plugin_file );
		$installed_version = $this->plugins->get_addon_plugin_version( $addon->constant_key() );

		$is_update_available = $is_active
			&& '' !== $installed_version
			&& version_compare( $addon->version(), $installed_version, '>' );

		return new Addon_Install_State( $is_installed, $is_active, $is_update_available );
	}
}
