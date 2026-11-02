<?php
/**
 * Whether an integration's plugin is installed.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Services\Availability;

use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Store;
use Uncanny_Automator\App\Plan\Services\Plugin\Plugin_Service;

/**
 * An inactive integration is missing from the registry, so availability
 * cannot tell an inactive plugin from a missing one. The catalog names every
 * integration's plugin file, which is then looked for on disk.
 *
 * @since 8.0.0
 */
final class Integration_Plugin_Presence {

	private Plugin_Service $plugins;

	/**
	 * @param Plugin_Service|null $plugins Plugin lookups; defaults to the site's.
	 */
	public function __construct( ?Plugin_Service $plugins = null ) {
		$this->plugins = $plugins ?? new Plugin_Service();
	}

	/**
	 * Whether the plugin, or one of its alternative main files, is on disk.
	 *
	 * @param string $integration_code Integration code, such as SEO_BY_RANK_MATH.
	 * @return bool
	 */
	public function is_installed( string $integration_code ): bool {
		$integration = Integration_Store::get_instance()->get_by_code( $integration_code );
		$plugin      = null !== $integration ? $integration->get_details()->get_plugin() : null;
		if ( null === $plugin ) {
			return false;
		}
		foreach ( array_merge( array( $plugin->get_plugin_file() ), $plugin->get_plugin_variations() ) as $file ) {
			if ( is_string( $file ) && '' !== $file && $this->plugins->is_plugin_installed( $file ) ) {
				return true;
			}
		}
		return false;
	}
}
